<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Media Convert — ویدیوی آپلودشده را به قالبی می‌برد که تلویزیون هتلی بخواند.
 *
 * چرا لازم است: اپراتور فایل .mov یا .mkv می‌دهد و تلویزیون آن را باز
 * نمی‌کند. حتی .mp4 هم تضمینی نیست — اگر داخلش H.265/HEVC باشد،
 * سامسونگ Orsay ۲۰۱۳ و بیشتر تلویزیون‌های هتلی سیاه می‌مانند. پس
 * تصمیم بر اساس **کدک** گرفته می‌شود نه پسوند.
 *
 * چرا پس‌زمینه: ‏max_execution_time روی pool برابر ۳۰۰ ثانیه است و
 * تبدیل یک ویدیوی چندصد مگابایتی بیشتر طول می‌کشد. پس ffmpeg با nohup
 * جدا می‌شود و رسانه تا پایان کار status=processing دارد.
 *
 * فاز ۱۰ — docs/TODO.md
 */
class MediaConvertService
{
    /** کدک‌هایی که روی همه‌ی تلویزیون‌های هدف پخش می‌شوند */
    private const SAFE_VIDEO = ['h264'];
    private const SAFE_AUDIO = ['aac', 'mp3'];

    /** ورودی‌هایی که می‌پذیریم و در صورت لزوم تبدیل می‌کنیم */
    public const ACCEPTED_VIDEO_MIME = [
        'video/mp4', 'video/webm', 'video/ogg',
        'video/quicktime',            // ‏.mov — همان چیزی که رد می‌شد
        'video/x-msvideo',            // ‏.avi
        'video/x-matroska',           // ‏.mkv
        'video/mpeg', 'video/3gpp',
        'video/x-ms-wmv', 'video/x-flv',
        'video/x-m4v', 'video/m4v',
    ];

    public const ACCEPTED_IMAGE_MIME = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    ];

    private Database $db;
    private string $ffmpeg  = '';
    private string $ffprobe = '';

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();

        $this->ffmpeg  = self::locate('ffmpeg');
        $this->ffprobe = self::locate('ffprobe');
    }

    /**
     * مسیر یک باینری را پیدا می‌کند.
     *
     * عمدا از is_executable() استفاده نمی‌شود: روی این سرور pool مقدار
     * open_basedir دارد (`/var/www/hotel-media:/tmp:/usr/share/php`) و هر
     * بررسی فایل‌سیستمی بیرون از آن — از جمله /usr/bin/ffmpeg — از سمت
     * وب false می‌دهد، در حالی که همان باینری با shell کاملا اجرا می‌شود.
     * نتیجه‌ی آن اشتباه، پیام گمراه‌کننده‌ی «ffmpeg نصب نیست» بود.
     */
    public static function locate(string $bin): string
    {
        $out = trim((string)@shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null'));
        if ($out !== '' && $out[0] === '/') return $out;

        foreach (['/usr/bin/', '/usr/local/bin/'] as $dir) {
            $probe = trim((string)@shell_exec(
                'test -x ' . escapeshellarg($dir . $bin) . ' && echo ok 2>/dev/null'
            ));
            if ($probe === 'ok') return $dir . $bin;
        }
        return '';
    }

    public function available(): bool
    {
        return $this->ffmpeg !== '' && $this->ffprobe !== '';
    }

    /**
     * کدک‌های یک فایل را می‌خواند.
     * @return array{video:string,audio:string,duration:int,width:int,height:int}
     */
    public function probe(string $path): array
    {
        $out = ['video' => '', 'audio' => '', 'duration' => 0, 'width' => 0, 'height' => 0];
        if ($this->ffprobe === '' || !is_file($path)) return $out;

        $cmd = escapeshellarg($this->ffprobe)
             . ' -v quiet -print_format json -show_streams -show_format '
             . escapeshellarg($path) . ' 2>/dev/null';
        $json = (string)shell_exec($cmd);
        $d    = json_decode($json, true);
        if (!is_array($d)) return $out;

        foreach (($d['streams'] ?? []) as $s) {
            $type = $s['codec_type'] ?? '';
            if ($type === 'video' && $out['video'] === '') {
                $out['video']  = (string)($s['codec_name'] ?? '');
                $out['width']  = (int)($s['width'] ?? 0);
                $out['height'] = (int)($s['height'] ?? 0);
            } elseif ($type === 'audio' && $out['audio'] === '') {
                $out['audio'] = (string)($s['codec_name'] ?? '');
            }
        }
        $out['duration'] = (int)round((float)($d['format']['duration'] ?? 0));

        return $out;
    }

    /**
     * آیا این فایل همان‌طور که هست روی تلویزیون پخش می‌شود؟
     * ظرف باید mp4 باشد و کدک‌ها امن. صدای نداشته هم اشکالی ندارد
     * (تابلوی تبلیغاتی اغلب بی‌صداست).
     */
    public function isTvReady(string $path, string $mime): bool
    {
        if ($mime !== 'video/mp4') return false;

        $p = $this->probe($path);
        if ($p['video'] === '') return false;
        if (!in_array($p['video'], self::SAFE_VIDEO, true)) return false;
        if ($p['audio'] !== '' && !in_array($p['audio'], self::SAFE_AUDIO, true)) return false;

        return true;
    }

    /**
     * تبدیل را در پس‌زمینه شروع می‌کند و بلافاصله برمی‌گردد.
     *
     * خروجی کنار فایل اصلی با پسوند .mp4 ساخته می‌شود. وقتی ffmpeg
     * تمام شد، همین کلاس (از مسیر finalize) رکورد را به ready می‌برد —
     * اجراکننده‌اش یک اسکریپت کوچک شل است که بعد از ffmpeg می‌دود، تا
     * به cron یا worker دائمی نیاز نباشد.
     *
     * @return array{ok:bool,message:string}
     */
    public function startConversion(int $mediaId, string $srcAbs, string $destAbs): array
    {
        if (!$this->available()) {
            return ['ok' => false, 'message' => 'ffmpeg روی سرور نصب نیست'];
        }
        if (!is_file($srcAbs)) {
            return ['ok' => false, 'message' => 'فایل اصلی پیدا نشد'];
        }

        $log = $destAbs . '.log';

        /* ‏-movflags +faststart: بدون این، اطلاعات فایل ته آن می‌نشیند و
           تلویزیون باید کل ویدیو را بگیرد تا پخش شروع شود.
           ‏yuv420p: بعضی دوربین‌ها yuv422 می‌دهند که روی تلویزیون سیاه است.
           ابعاد فرد هم با scale به زوج گرد می‌شود، وگرنه libx264 خطا می‌دهد. */
        $ff = implode(' ', array_map('escapeshellarg', [
            $this->ffmpeg, '-y', '-loglevel', 'error',
            '-i', $srcAbs,
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23',
            '-profile:v', 'high', '-level', '4.0',
            '-pix_fmt', 'yuv420p',
            '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2',
            '-c:a', 'aac', '-b:a', '128k', '-ac', '2',
            '-movflags', '+faststart',
            $destAbs,
        ]));

        /* بعد از ffmpeg، وضعیت رکورد با همان php artisan به‌روز می‌شود.
           این‌طور نه cron لازم است نه سرویس جداگانه. */
        /* عمدا PHP_BINARY نیست: در بستر FPM مقدارش /usr/sbin/php-fpm8.3
           است، و صدا زدنش با آرگومان‌های artisan فقط راهنمای php-fpm را
           چاپ می‌کند و رکورد برای همیشه روی processing می‌ماند. باینری
           خط‌فرمان جدا پیدا می‌شود. */
        $phpCli = self::locate('php') ?: self::locate('php8.3');
        if ($phpCli === '') {
            return ['ok' => false, 'message' => 'باینری خط‌فرمان php پیدا نشد'];
        }

        $artisan = escapeshellarg(ROOT_PATH . '/artisan');
        $finish  = escapeshellarg($phpCli) . ' ' . $artisan . ' media:converted ' . (int)$mediaId . ' $?';

        $script  = $ff . ' > ' . escapeshellarg($log) . ' 2>&1; ' . $finish
                 . ' >> ' . escapeshellarg($log) . ' 2>&1';

        shell_exec('nohup sh -c ' . escapeshellarg($script) . ' > /dev/null 2>&1 &');

        return ['ok' => true, 'message' => 'تبدیل شروع شد'];
    }

    /**
     * بعد از پایان ffmpeg صدا زده می‌شود: رکورد را ready یا failed می‌کند.
     * @param int $exitCode کد خروج ffmpeg — صفر یعنی موفق
     */
    public function finalize(int $mediaId, int $exitCode): void
    {
        $m = $this->db->row("SELECT * FROM media WHERE id=?", [$mediaId]);
        if (!$m) return;

        $rel  = (string)($m['file_path'] ?? '');
        $abs  = PUBLIC_PATH . $rel;

        if ($exitCode !== 0 || !is_file($abs) || filesize($abs) === 0) {
            $note = 'تبدیل ناموفق بود';
            $log  = $abs . '.log';
            if (is_file($log)) {
                $tail = trim((string)@file_get_contents($log));
                if ($tail !== '') $note .= ' — ' . mb_substr($tail, -200);
            }
            $this->db->update('media', ['status' => 'failed', 'conv_note' => $note], ['id' => $mediaId]);
            return;
        }

        $p = $this->probe($abs);
        $this->db->update('media', [
            'status'    => 'ready',
            'conv_note' => null,
            'file_size' => (int)filesize($abs),
            'duration'  => $p['duration'] ?: null,
            'width'     => $p['width']  ?: null,
            'height'    => $p['height'] ?: null,
            'mime_type' => 'video/mp4',
        ], ['id' => $mediaId]);

        // فایل اصلی و لاگ دیگر لازم نیستند
        foreach ([$abs . '.log'] as $junk) { @unlink($junk); }
        $orig = $this->db->value("SELECT JSON_UNQUOTE(JSON_EXTRACT(meta,'$.original_file')) FROM media WHERE id=?", [$mediaId]);
        if (is_string($orig) && $orig !== '' && $orig !== 'null') {
            $origAbs = PUBLIC_PATH . $orig;
            if (is_file($origAbs) && $origAbs !== $abs) @unlink($origAbs);
        }
    }
}
