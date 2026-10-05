<?php

namespace App\Services;

use App\Core\Database;

/**
 * خط لولهٔ پردازش فیلم‌های VOD — فاز ۵ بازسازی.
 *
 * ترتیب کار: probe → تصمیم سازگاری → تبدیل فقط در صورت لزوم → HLS →
 * پوستر → متادیتا → READY.
 *
 * ‏منطق «آیا این فایل روی تلویزیون هتل پخش می‌شود؟» عمدا اینجا دوباره
 * نوشته نشده. `MediaConvertService` همان تصمیم را برای رسانهٔ تابلو
 * می‌گیرد و چیزهایی را می‌داند که با حدس به دست نمی‌آیند: تصویر جلد
 * داخل MOV که به‌جای فیلم برداشته می‌شود، چرخش ویدیوی گوشی، نرخ فریم
 * متغیر، و سقف نرخ بیت که شایع‌ترین علت گیر کردن تصویر روی تلویزیون
 * ۲۰۱۳ است. دو نسخه از این تصمیم یعنی دو رفتار متفاوت برای یک فایل.
 *
 * اجرا داخل درخواست HTTP نیست: پردازش در `php artisan vod:process`
 * انجام می‌شود (نگاه کنید به دلیلش در migration 047).
 */
class VodPipelineService
{
    private Database $db;
    private MediaConvertService $conv;
    private string $ffmpeg;
    private string $ffprobe;

    public function __construct(?Database $db = null)
    {
        $this->db      = $db ?? Database::getInstance();
        $this->conv    = new MediaConvertService($this->db);
        /* binPath و نه مسیر دست‌ساز: زیر open_basedir تابع is_executable
           برای /usr/bin/ffmpeg مقدار false می‌دهد، در حالی که اجرای
           پوسته‌ای کار می‌کند. کد قبلی VOD رشتهٔ خالی «ffmpeg» را صدا
           می‌زد و اگر PATH کاربر www-data آن را نداشت، بی‌صدا شکست. */
        $this->ffmpeg  = binPath('ffmpeg');
        $this->ffprobe = binPath('ffprobe');
    }

    public function available(): bool { return $this->ffmpeg !== ''; }

    private function uploadDir(): string { return PUBLIC_PATH . '/uploads/vod'; }
    private function hlsDir(int $id): string { return $this->uploadDir() . '/hls/' . $id; }

    // ══════════════════════════════════════════════════════════════
    // صف
    // ══════════════════════════════════════════════════════════════

    /**
     * ردیف را در صف می‌گذارد و پردازشگر را در پس‌زمینه بالا می‌آورد.
     * خودِ درخواست HTTP منتظر نمی‌ماند.
     */
    public function enqueue(int $videoId): array
    {
        if (!$this->available()) {
            $this->fail($videoId, 'ffmpeg روی سرور نصب نیست');
            return ['ok' => false, 'message' => 'ffmpeg روی سرور نصب نیست'];
        }

        $php = phpCliPath();
        if ($php === '') {
            $this->fail($videoId, 'باینری خط‌فرمان php پیدا نشد');
            return ['ok' => false, 'message' => 'باینری خط‌فرمان php پیدا نشد'];
        }

        $this->db->update('vod_videos', [
            'status'        => 'queued',
            'conv_progress' => 0,
            'conv_note'     => null,
        ], ['id' => $videoId]);

        $log = $this->uploadDir() . '/' . $videoId . '.log';
        /* nice: پردازش یک فایل آپلودشده نباید پردازنده را از کانال زندهٔ
           اتاق‌ها بگیرد. عجله‌ای نیست؛ قطع شدن تصویر اتاق هست. */
        $cmd = 'nice -n 10 ' . escapeshellarg($php) . ' ' . escapeshellarg(ROOT_PATH . '/artisan')
             . ' vod:process ' . (int)$videoId;
        shell_exec('nohup sh -c ' . escapeshellarg($cmd . ' > ' . escapeshellarg($log) . ' 2>&1')
                 . ' > /dev/null 2>&1 &');

        return ['ok' => true, 'message' => 'فیلم در صف پردازش قرار گرفت'];
    }

    /**
     * کارهای جامانده را برمی‌دارد — برای cron.
     *
     * دو حالت جدا: صفی که پردازشگرش هرگز بالا نیامد (ریستارت سرور وسط
     * آپلود)، و ردیفی که روی processing ماسیده چون فرایندش کشته شده.
     * بی این، یک ریستارت یعنی فیلمی که تا ابد «در حال پردازش» است.
     */
    public function runQueue(int $maxItems = 2, int $staleMinutes = 90): array
    {
        $done = [];

        $stale = $this->db->rows(
            "SELECT id FROM vod_videos
              WHERE status = 'processing'
                AND updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$staleMinutes]
        );
        foreach ($stale as $r) {
            $this->fail((int)$r['id'], 'پردازش نیمه‌کاره رها شد (ریستارت سرور یا کشته‌شدن فرایند)');
            $done[] = 'stale #' . $r['id'];
        }

        $queued = $this->db->rows(
            "SELECT id FROM vod_videos WHERE status = 'queued' ORDER BY id LIMIT ?",
            [max(1, $maxItems)]
        );
        foreach ($queued as $r) {
            $this->process((int)$r['id']);
            $done[] = 'processed #' . $r['id'];
        }

        return $done;
    }

    public function cancel(int $videoId): array
    {
        $v = $this->db->row('SELECT status FROM vod_videos WHERE id = ?', [$videoId]);
        if (!$v) return ['ok' => false, 'message' => 'فیلم پیدا نشد'];
        if (in_array($v['status'], ['ready', 'cancelled'], true)) {
            return ['ok' => false, 'message' => 'این فیلم در حال پردازش نیست'];
        }
        $this->db->update('vod_videos', [
            'status'    => 'cancelled',
            'conv_note' => 'به‌دست اپراتور لغو شد',
        ], ['id' => $videoId]);
        /* فرایند ffmpeg با دیدن وضعیت در گام بعدی خودش بیرون می‌آید
           (نگاه کنید به cancelled در حلقهٔ پیشرفت) */
        return ['ok' => true, 'message' => 'لغو شد'];
    }

    // ══════════════════════════════════════════════════════════════
    // پردازش
    // ══════════════════════════════════════════════════════════════

    /** @return int کد خروج برای artisan */
    public function process(int $videoId): int
    {
        $v = $this->db->row('SELECT * FROM vod_videos WHERE id = ?', [$videoId]);
        if (!$v) { fwrite(STDERR, "vod #$videoId پیدا نشد\n"); return 1; }

        $src = $this->sourceAbs($v);
        if ($src === '') { $this->fail($videoId, 'فایل اصلی پیدا نشد'); return 1; }

        $this->db->update('vod_videos', [
            'status'        => 'processing',
            'conv_progress' => 1,
            'conv_note'     => null,
            /* از همین حالا نگه داشته می‌شود: کد قبلی فایل اصلی را پاک
               می‌کرد و پردازش دوباره ناممکن بود */
            'source_path'   => $v['source_path'] ?: $v['file_path'],
        ], ['id' => $videoId]);

        $mime = (string)(@mime_content_type($src) ?: 'video/unknown');
        $plan = $this->conv->plan($src, $mime);
        $p    = $this->conv->probe($src);

        $base  = preg_replace('/\.[A-Za-z0-9]{1,5}$/', '', basename($src));
        $final = $this->uploadDir() . '/' . $base . '.mp4';

        if ($plan['action'] === 'ok') {
            /* فایل همین حالا سازگار است. کد قبلی حتی این را هم به
               MPEG-TS بازنویسی می‌کرد — یعنی وقت پردازنده و افت ظرف،
               بی هیچ سودی. */
            $final  = $src;
            $action = 'passthrough';
            $this->db->update('vod_videos', ['conv_progress' => 90], ['id' => $videoId]);
        } else {
            $action = $plan['action'] === 'remux' ? 'remux' : 'transcode';
            $part   = $final . '.part.mp4';

            $args = $action === 'remux'
                ? [$this->ffmpeg, '-y', '-hide_banner', '-nostdin', '-i', $src,
                   '-map', '0:V:0', '-map', '0:a:0?', '-c', 'copy',
                   '-movflags', '+faststart', '-f', 'mp4', $part]
                /* zscale: بی این پرچم، فایل HDR با رنگ پریده تبدیل می‌شود.
                   مقدارش حدسی نیست — از خودِ ffmpeg نصب‌شده پرسیده می‌شود. */
                : MediaConvertService::transcodeArgs($this->ffmpeg, $src, $part, $p, $this->conv->hasFilter('zscale'));

            $this->db->update('vod_videos', [
                'conv_action' => $action,
                'conv_note'   => $plan['reasons'] ? 'دلیل تبدیل: ' . implode('، ', $plan['reasons']) : null,
            ], ['id' => $videoId]);

            $res = $this->runWithProgress($args, $videoId, (float)$p['duration'], 5, 80);
            if ($res === 'cancelled') { @unlink($part); return 0; }
            if ($res !== '') { @unlink($part); $this->fail($videoId, $res); return 1; }

            /* کد خروج صفر کافی نیست: ffmpeg‌ای که وسط کار قطع شده هم صفر
               برمی‌گرداند. فیلمی که «آماده» اعلام شود و دقیقهٔ ۱۲ بایستد
               بدترین حالت است، چون کسی گزارشش نمی‌دهد. */
            $op = $this->conv->probe($part);
            if ($p['duration'] > 0 && $op['duration'] < $p['duration'] - max(2.0, $p['duration'] * 0.02)) {
                @unlink($part);
                $this->fail($videoId, sprintf('خروجی ناقص است (%d از %d ثانیه)', (int)$op['duration'], (int)$p['duration']));
                return 1;
            }
            if (!@rename($part, $final)) { @unlink($part); $this->fail($videoId, 'انتقال فایل خروجی ناموفق بود'); return 1; }
        }

        if ($this->isCancelled($videoId)) return 0;

        $meta = $this->conv->probe($final);
        $hls  = $this->buildHls($videoId, $final);
        $thumb = $v['thumbnail'] && !$v['thumbnail_auto'] ? $v['thumbnail'] : $this->poster($videoId, $final, (float)$meta['duration']);

        $this->db->update('vod_videos', [
            'status'        => 'ready',
            'conv_progress' => 100,
            'conv_action'   => $action,
            'conv_note'     => null,
            'file_path'     => '/uploads/vod/' . basename($final),
            'hls_path'      => $hls,
            'mime_type'     => 'video/mp4',
            'file_size'     => @filesize($final) ?: null,
            'duration'      => (int)round($meta['duration']) ?: null,
            'duration_fmt'  => $meta['duration'] > 0 ? self::fmt((int)$meta['duration']) : null,
            'width'         => $meta['width'] ?: null,
            'height'        => $meta['height'] ?: null,
            'codec'         => $meta['video'] ?: null,
            'audio_codec'   => $meta['audio'] ?: null,
            'bitrate'       => $meta['bitrate'] ? (int)round($meta['bitrate'] / 1000) : null,
            'thumbnail'     => $thumb,
            'thumbnail_auto'=> $thumb && $thumb !== $v['thumbnail'] ? 1 : (int)$v['thumbnail_auto'],
        ], ['id' => $videoId]);

        return 0;
    }

    /** پردازش دوباره — برای ردیف failed یا فایلی که اپراتور شک دارد */
    public function reprocess(int $videoId): array
    {
        $v = $this->db->row('SELECT * FROM vod_videos WHERE id = ?', [$videoId]);
        if (!$v) return ['ok' => false, 'message' => 'فیلم پیدا نشد'];
        if ($this->sourceAbs($v) === '') {
            return ['ok' => false, 'message' => 'فایل اصلی روی دیسک نیست — پردازش دوباره ممکن نیست'];
        }
        return $this->enqueue($videoId);
    }

    // ══════════════════════════════════════════════════════════════
    // اجرای ffmpeg با پیشرفت واقعی
    // ══════════════════════════════════════════════════════════════

    /**
     * ‏ffmpeg را اجرا می‌کند و درصد را در دیتابیس می‌نویسد.
     *
     * @return string رشتهٔ خالی یعنی موفق؛ 'cancelled' یعنی لغو؛ وگرنه متن خطا
     */
    private function runWithProgress(array $args, int $videoId, float $duration, int $from, int $to): string
    {
        /* ‏-progress pipe:1 خروجی ماشین‌خوان می‌دهد؛ stderr جدا می‌ماند
           تا در صورت شکست، علت واقعی («codec not supported»، «No space
           left») را داشته باشیم. کد قبلی همه را به /dev/null می‌فرستاد
           و اپراتور فقط «ناموفق» می‌دید. */
        /* ‏-progress و -nostats گزینهٔ سراسری‌اند و باید *پیش از* فایل
           خروجی بیایند؛ چسبیدنشان به انتهای فرمان، ffmpeg را با
           «Unrecognized option» رد می‌کند. پس بعد از خودِ باینری تزریق
           می‌شوند، نه با append. */
        array_splice($args, 1, 0, ['-progress', 'pipe:1', '-nostats']);

        /* آرایه و نه رشته — این را تست لغو روی سرور نشان داد:
           ‏proc_open با رشته، فرمان را زیر `sh -c` اجرا می‌کند، پس
           فرزند مستقیم همان پوسته است. ‏proc_terminate به پوسته
           می‌رسید، پوسته می‌مرد و ffmpeg یتیم می‌شد و تا آخر فیلم
           پردازنده را می‌گرفت — در حالی که دیتابیس «لغو شد» نشان
           می‌داد. با آرایه، PHP بدون پوسته exec می‌کند و سیگنال به
           خودِ ffmpeg می‌رسد. (نیازی به escapeshellarg هم نیست.)

           ‏nice اینجا لازم نیست: فرایند artisan با nice بالا آمده و
           اولویت به فرزند ارث می‌رسد. */
        $pipes = [];
        $proc  = @proc_open($args, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) return 'اجرای ffmpeg ممکن نشد';

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $err = '';
        $last = 0;
        $lastCheck = time();
        $buf = '';

        while (true) {
            $st = proc_get_status($proc);

            $buf .= (string)fread($pipes[1], 8192);
            $err .= (string)fread($pipes[2], 8192);
            /* stderr ffmpeg روی فایل بزرگ می‌تواند مگابایت شود؛ فقط دنبالهٔ
               آن لازم است و رشد بی‌مرز حافظهٔ فرایند را می‌خورد */
            if (strlen($err) > 65536) $err = substr($err, -32768);

            /* هر دو کلید پذیرفته می‌شوند: ffmpeg قدیمی out_time_ms می‌دهد
               و جدید out_time_us — و هر دو در واقع میکروثانیه‌اند
               (نام ms یک اشتباه قدیمی در خودِ ffmpeg است). */
            if ($duration > 0 && preg_match_all('/out_time_(?:us|ms)=(\d+)/', $buf, $m)) {
                $sec = (int)end($m[1]) / 1000000;
                $pct = (int)min($to, $from + ($to - $from) * ($sec / $duration));
                if ($pct > $last) {
                    $this->db->update('vod_videos', ['conv_progress' => $pct], ['id' => $videoId]);
                    $last = $pct;
                }
                $buf = substr($buf, -4096);
            }

            if (!$st['running']) break;

            /* هر ۱۰ ثانیه: اگر اپراتور لغو کرده، ffmpeg را می‌کشیم.
               بی این، دکمهٔ «لغو» فقط یک برچسب در دیتابیس بود و پردازنده
               تا آخر فیلم مشغول می‌ماند. */
            if (time() - $lastCheck >= 10) {
                $lastCheck = time();
                if ($this->isCancelled($videoId)) {
                    proc_terminate($proc, 15);  // SIGTERM — ffmpeg خودش تمیز بیرون می‌آید
                    /* ولی اگر نیامد، منتظرش نمی‌مانیم: هدفِ لغو آزاد
                       کردن پردازنده است، و proc_close روی فرایندی که
                       نمرده تا ابد مسدود می‌شود. */
                    for ($i = 0; $i < 20 && proc_get_status($proc)['running']; $i++) usleep(100000);
                    if (proc_get_status($proc)['running']) proc_terminate($proc, 9);
                    @fclose($pipes[1]); @fclose($pipes[2]); proc_close($proc);
                    return 'cancelled';
                }
            }
            usleep(400000);
        }

        $err .= (string)stream_get_contents($pipes[2]);
        @fclose($pipes[1]); @fclose($pipes[2]);
        $exit = proc_close($proc);

        if ($exit !== 0) return self::lastErrLine($err) ?: ('ffmpeg با کد ' . $exit . ' شکست خورد');
        return '';
    }

    /** آخرین خط معنادار stderr — برای نشان دادن به اپراتور */
    private static function lastErrLine(string $err): string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $err)), static fn($l) => $l !== ''));
        return $lines ? mb_substr((string)end($lines), 0, 400) : '';
    }

    // ══════════════════════════════════════════════════════════════
    // خروجی‌های جانبی
    // ══════════════════════════════════════════════════════════════

    /**
     * ‏HLS از همان MP4 نهایی، با `-c copy`.
     *
     * بازرمزگذاری لازم نیست چون فایل نهایی از این مرحله H.264/AAC است.
     * ‏HLS برای تلویزیون‌هایی است که جستجو در MP4 روی شبکهٔ هتل را خوب
     * انجام نمی‌دهند؛ MP4 برای پیش‌نمایش مرورگر و پروفایل Orsay می‌ماند.
     * شکست اینجا کشنده نیست — فیلم با MP4 هم پخش می‌شود.
     */
    private function buildHls(int $videoId, string $mp4): ?string
    {
        $dir = $this->hlsDir($videoId);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return null;

        foreach ((array)glob($dir . '/*') as $old) @unlink($old);

        $m3u8 = $dir . '/index.m3u8';
        $args = [$this->ffmpeg, '-y', '-hide_banner', '-nostdin', '-loglevel', 'error', '-i', $mp4,
                 '-c', 'copy', '-f', 'hls', '-hls_time', '6', '-hls_list_size', '0',
                 '-hls_playlist_type', 'vod',
                 '-hls_segment_filename', $dir . '/seg%05d.ts', $m3u8];
        @shell_exec('nice -n 10 ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1');

        return is_file($m3u8) && filesize($m3u8) > 0 ? '/uploads/vod/hls/' . $videoId . '/index.m3u8' : null;
    }

    /**
     * پوستر از ۱۰٪ طول فیلم.
     * ثانیهٔ صفر تقریبا همیشه سیاه است یا لوگوی استودیو — کارت فیلمی که
     * قاب سیاه نشان بدهد، در فهرست به‌نظر خراب می‌آید.
     */
    private function poster(int $videoId, string $mp4, float $duration): ?string
    {
        $dir = $this->uploadDir() . '/thumbs';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return null;

        $name = 'vod' . $videoId . '.jpg';
        $at   = $duration > 20 ? $duration * 0.1 : 1.0;
        $args = [$this->ffmpeg, '-y', '-hide_banner', '-nostdin', '-loglevel', 'error',
                 '-ss', (string)round($at, 2), '-i', $mp4, '-frames:v', '1',
                 '-vf', 'scale=640:-2', '-q:v', '4', $dir . '/' . $name];
        @shell_exec(implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1');

        return is_file($dir . '/' . $name) ? '/uploads/vod/thumbs/' . $name : null;
    }

    // ══════════════════════════════════════════════════════════════
    // کمکی
    // ══════════════════════════════════════════════════════════════

    private function sourceAbs(array $v): string
    {
        foreach ([$v['source_path'] ?? '', $v['file_path'] ?? ''] as $rel) {
            if ($rel && is_file(PUBLIC_PATH . $rel)) return PUBLIC_PATH . $rel;
        }
        return '';
    }

    private function isCancelled(int $videoId): bool
    {
        return $this->db->value('SELECT status FROM vod_videos WHERE id = ?', [$videoId]) === 'cancelled';
    }

    private function fail(int $videoId, string $note): void
    {
        $this->db->update('vod_videos', [
            'status'    => 'failed',
            'conv_note' => mb_substr($note, 0, 500),
        ], ['id' => $videoId]);
    }

    public static function fmt(int $sec): string
    {
        return $sec >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60)
            : sprintf('%d:%02d', intdiv($sec, 60), $sec % 60);
    }
}
