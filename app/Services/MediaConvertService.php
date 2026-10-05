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
        /* یک پیاده‌سازی در helpers نگه داشته شده تا این تشخیص در چند
           سرویس از هم جدا نشود. این متد برای سازگاری صداکننده‌ها ماند. */
        return binPath($bin);
    }

    public function available(): bool
    {
        return $this->ffmpeg !== '' && $this->ffprobe !== '';
    }

    /**
     * ویژگی‌های یک ویدیو برای تصمیم تبدیل.
     *
     * ابعاد «نمایشی» برگردانده می‌شود: ویدیوی عمودی موبایل در فایل
     * ۱۹۲۰×۱۰۸۰ ذخیره می‌شود با برچسب چرخش ۹۰ درجه، و ffmpeg هنگام تبدیل
     * خودش می‌چرخاند؛ پس محاسبه‌ی اندازه باید با ابعاد چرخیده باشد.
     *
     * @return array{video:string,audio:string,duration:float,width:int,height:int,fps:float,
     *               vfr:bool,pix_fmt:string,level:int,profile:string,hdr:bool,faststart:bool}
     */
    public function probe(string $path): array
    {
        $out = ['video' => '', 'audio' => '', 'duration' => 0.0, 'width' => 0, 'height' => 0, 'fps' => 0.0, 'fps_nominal' => 0.0,
                'vfr' => false, 'pix_fmt' => '', 'level' => 0, 'profile' => '', 'hdr' => false, 'faststart' => true,
                'bitrate' => 0];
        if ($this->ffprobe === '' || !is_file($path)) return $out;

        $cmd = escapeshellarg($this->ffprobe)
             . ' -v quiet -print_format json -show_streams -show_format '
             . escapeshellarg($path) . ' 2>/dev/null';
        $d = json_decode((string)shell_exec($cmd), true);
        if (!is_array($d)) return $out;

        foreach (($d['streams'] ?? []) as $s) {
            $type = $s['codec_type'] ?? '';
            /* جلد آلبوم یا تصویر کوچک داخل MOV هم «ویدیو» است؛ نباید آن را
               به‌جای خود فیلم برداشت */
            if ($type === 'video' && $out['video'] === '' && empty($s['disposition']['attached_pic'])) {
                $out['video']   = (string)($s['codec_name'] ?? '');
                $out['width']   = (int)($s['width'] ?? 0);
                $out['height']  = (int)($s['height'] ?? 0);
                $out['pix_fmt'] = (string)($s['pix_fmt'] ?? '');
                $out['level']   = (int)($s['level'] ?? 0);
                $out['profile'] = (string)($s['profile'] ?? '');
                $out['hdr']     = in_array($s['color_transfer'] ?? '', ['smpte2084', 'arib-std-b67'], true);
                $out['bitrate'] = (int)($s['bit_rate'] ?? 0);

                $avg = self::rate((string)($s['avg_frame_rate'] ?? ''));
                $r   = self::rate((string)($s['r_frame_rate'] ?? ''));
                $out['fps'] = $avg > 0 ? $avg : $r;
                $out['fps_nominal'] = $r;
                /* نرخ فریم متغیر (گوشی آیفون و اندروید): نرخ اسمی و میانگین
                   فرق دارند. پخش‌کننده‌ی تلویزیون قدیمی روی آن تپق می‌زند. */
                $out['vfr'] = $avg > 0 && $r > 0 && abs($avg - $r) / $r > 0.02;

                $rot = 0;
                foreach (($s['side_data_list'] ?? []) as $sd) {
                    if (isset($sd['rotation'])) $rot = (int)$sd['rotation'];
                }
                if (!$rot && isset($s['tags']['rotate'])) $rot = (int)$s['tags']['rotate'];
                if (abs($rot) % 180 === 90) {
                    [$out['width'], $out['height']] = [$out['height'], $out['width']];
                }
            } elseif ($type === 'audio' && $out['audio'] === '') {
                $out['audio'] = (string)($s['codec_name'] ?? '');
            }
        }
        $out['duration']  = (float)($d['format']['duration'] ?? 0);
        $out['faststart'] = self::moovFirst($path);

        /* بعضی فایل‌ها نرخ بیت را روی خود جریان ندارند (MKV و خروجی
           بعضی دوربین‌ها). نرخ کل فایل تقریب خوبی است: صدا معمولا
           کمتر از ۱۰ درصد آن است و برای سنجیدن «خیلی سنگین است یا نه»
           همین کافی است. */
        if ($out['bitrate'] === 0) {
            $out['bitrate'] = (int)($d['format']['bit_rate'] ?? 0);
        }

        return $out;
    }

    private static function rate(string $r): float
    {
        if (preg_match('#^(\d+)/(\d+)$#', $r, $m) && (int)$m[2] > 0) return (int)$m[1] / (int)$m[2];
        return (float)$r;
    }

    /**
     * آیا فهرست فایل (moov) پیش از داده (mdat) است؟ بدون این، تلویزیون
     * باید کل فایل را بگیرد تا پخش شروع شود و روی شبکه‌ی کند وسط کار
     * منتظر می‌ماند. فقط سرِ جعبه‌های بالایی خوانده می‌شود، نه کل فایل.
     */
    private static function moovFirst(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) return true;
        $size = (int)filesize($path);
        $pos  = 0;
        for ($i = 0; $i < 64 && $pos + 8 <= $size; $i++) {
            fseek($fh, $pos);
            $h = fread($fh, 16);
            if (strlen($h) < 8) break;
            $len  = unpack('N', substr($h, 0, 4))[1];
            $type = substr($h, 4, 4);
            if ($type === 'moov') { fclose($fh); return true; }
            if ($type === 'mdat') { fclose($fh); return false; }
            if ($len === 1 && strlen($h) >= 16) {
                $len = (int)(unpack('N', substr($h, 8, 4))[1] * 4294967296 + unpack('N', substr($h, 12, 4))[1]);
            } elseif ($len === 0) {
                break;
            }
            if ($len < 8) break;
            $pos += $len;
        }
        fclose($fh);
        return true;
    }

    /**
     * چه باید کرد تا این فایل روی تلویزیون هتلی روان پخش شود؟
     *   ok        — همین‌طور پخش می‌شود
     *   remux     — فقط فهرست فایل جابه‌جا شود (چند ثانیه، بدون افت کیفیت)
     *   transcode — تبدیل کامل
     *
     * معیارها همان سقف رمزگشای سخت‌افزاری تلویزیون‌های هتلی از ۲۰۱۳ به بعد
     * است: H.264 تا High@4.1، حداکثر ۱۹۲۰×۱۰۸۰، حداکثر ۳۰ فریم، 8 بیت
     * 4:2:0، نرخ فریم ثابت. فایلی که یکی از این‌ها را ندارد ممکن است
     * شروع به پخش کند و وسط کار، در صحنه‌ی سنگین، گیر کند.
     *
     * @return array{action:string,reasons:list<string>}
     */
    /**
     * سقف نرخ بیت (کیلوبیت بر ثانیه) برای یک ارتفاع تصویر.
     *
     * بدون سقف، CRF در صحنه‌ی شلوغ تا چند ده مگابیت بالا می‌رود و
     * رمزگشای تلویزیون یا شبکه‌ی هتل کم می‌آورد.
     *
     * همین اعداد هم برای تولید خروجی و هم برای قضاوت درباره‌ی فایل
     * ورودی به کار می‌روند. جدا بودنشان همان اشکالی بود که یک فایل
     * ۱۰۸۰p با ۱۴٫۵ مگابیت را «سالم» تشخیص می‌داد — فایلی که خودِ
     * همین مبدل هرگز حاضر نبود تولیدش کند.
     */
    public static function maxBitrateK(int $height): int
    {
        return $height >= 1000 ? 8000 : ($height >= 700 ? 5000 : ($height >= 460 ? 2500 : 1500));
    }

    public function plan(string $path, string $mime): array
    {
        $p = $this->probe($path);
        $why = [];
        if ($p['video'] === '')                          return ['action' => 'transcode', 'reasons' => ['ویدیو خوانده نشد']];
        if (!in_array($p['video'], self::SAFE_VIDEO, true)) $why[] = 'کدک ' . $p['video'];
        if ($p['audio'] !== '' && !in_array($p['audio'], self::SAFE_AUDIO, true)) $why[] = 'صدای ' . $p['audio'];
        if ($p['width'] > 1920 || $p['height'] > 1080) {
            /* ویدیوی عمودی ۱۰۸۰×۱۹۲۰ هم بیش از توان رمزگشای ۱۰۸۰p است */
            $why[] = 'ابعاد ' . $p['width'] . '×' . $p['height'];
        }
        if ($p['fps'] > 30.5)                            $why[] = round($p['fps']) . ' فریم در ثانیه';
        if ($p['vfr'])                                   $why[] = 'نرخ فریم متغیر';
        if ($p['pix_fmt'] !== '' && $p['pix_fmt'] !== 'yuv420p' && $p['pix_fmt'] !== 'yuvj420p') $why[] = 'قالب رنگ ' . $p['pix_fmt'];
        if ($p['level'] > 41)                            $why[] = 'سطح ' . ($p['level'] / 10);

        /* نرخ بیت — تنها ویژگی‌ای که بررسی نمی‌شد و در عمل شایع‌ترین
           علت گیر کردن تصویر روی تلویزیون هتل است. فایل ۱۰۸۰p با
           ۱۴٫۵ مگابیت از تک‌تک شرط‌های بالا رد می‌شد (ابعاد دقیقا
           ۱۹۲۰×۱۰۸۰، سطح ۴٫۱، پروفایل Main، ۲۵ فریم) و دست‌نخورده
           روی تلویزیون ۲۰۱۳ می‌رفت.

           ۲۰ درصد ارفاق: فایلی که همین حالا نزدیک هدف است، تبدیل
           دوباره‌اش فقط کیفیت را کم می‌کند بدون اینکه چیزی حل شود. */
        $cap = self::maxBitrateK(max(1, (int)$p['height']));
        if ($p['bitrate'] > $cap * 1000 * 1.2) {
            $why[] = 'نرخ بیت ' . round($p['bitrate'] / 1000000, 1) . ' مگابیت (سقف ' . round($cap / 1000, 1) . ')';
        }
        if ($p['hdr'])                                   $why[] = 'HDR';
        if (stripos($p['profile'], 'high 10') !== false || stripos($p['profile'], '4:2:2') !== false) $why[] = 'پروفایل ' . $p['profile'];

        if ($why) return ['action' => 'transcode', 'reasons' => $why];
        /* MOV با H.264 و AAC سالم فقط ظرفش عوض می‌شود — چند ثانیه و بدون افت کیفیت */
        if ($mime !== 'video/mp4') return ['action' => 'remux', 'reasons' => ['ظرف ' . $mime]];
        if (!$p['faststart']) return ['action' => 'remux', 'reasons' => ['فهرست فایل در انتهاست']];
        return ['action' => 'ok', 'reasons' => []];
    }

    /**
     * آیا این فایل همان‌طور که هست روی تلویزیون پخش می‌شود؟
     * (سازگاری با کد قدیمی؛ تصمیم واقعی در plan است)
     */
    public function isTvReady(string $path, string $mime): bool
    {
        return $this->plan($path, $mime)['action'] === 'ok';
    }

    /**
     * آرگومان‌های ffmpeg برای تبدیل به خروجی امن تلویزیون — تابع خالص تا تست شود.
     *
     * @param array $p     خروجی probe
     * @param bool  $zscale آیا ffmpeg فیلتر zscale دارد (برای HDR)
     * @return list<string>
     */
    public static function transcodeArgs(string $ffmpeg, string $src, string $dest, array $p, bool $zscale): array
    {
        // ── نرخ فریم: بالای ۳۰ نصف می‌شود (۶۰←۳۰، ۵۰←۲۵، ۱۲۰←۳۰) ──
        /* نرخ متغیر گوشی: میانگین (مثلا ۴۴٫۵ با فریم‌های افتاده) معیار
           نیست، نرخ اسمی (۶۰) است. نتیجه به نزدیک‌ترین نرخ استاندارد
           تلویزیون چسبانده می‌شود — ۲۲٫۲۵ فریم را هیچ تلویزیونی نمی‌شناسد. */
        $fps = !empty($p['vfr']) && (float)($p['fps_nominal'] ?? 0) > 0 ? (float)$p['fps_nominal'] : (float)($p['fps'] ?? 0);
        if ($fps <= 1 || $fps > 240) $fps = 25.0;
        while ($fps > 30.5) $fps /= 2;
        $std = ['24000/1001' => 23.976, '24' => 24.0, '25' => 25.0, '30000/1001' => 29.97, '30' => 30.0];
        $fpsExpr = '25'; $best = INF;
        foreach ($std as $expr => $val) {
            if (abs($val - $fps) < $best) { $best = abs($val - $fps); $fpsExpr = (string)$expr; }
        }
        /* ۱۵ یا ۱۲ فریم (دوربین مداربسته) به ۲۵ کشیده نشود — فقط اگر دور است */
        if ($best > 3) $fpsExpr = (string)max(10, (int)round($fps));
        $fps = $std[$fpsExpr] ?? (float)$fpsExpr;
        $gop = max(12, (int)round($fps * 2));

        // ── ابعاد: حداکثر ۱۹۲۰×۱۰۸۰، زوج، بدون بزرگ‌نمایی ──
        $w = max(2, (int)($p['width'] ?? 0));  $h = max(2, (int)($p['height'] ?? 0));
        $sc = min(1.0, 1920 / $w, 1080 / $h);
        $tw = max(2, (int)floor($w * $sc / 2) * 2);
        $th = max(2, (int)floor($h * $sc / 2) * 2);

        $vf = [];
        if (!empty($p['hdr']) && $zscale) {
            /* HDR آیفون (HLG/Dolby Vision) روی تلویزیون SDR رنگ‌پریده دیده
               می‌شود؛ tone-map به SDR */
            $vf[] = 'zscale=t=linear:npl=100,format=gbrpf32le,zscale=p=bt709,tonemap=hable:desat=0,zscale=t=bt709:m=bt709:r=tv';
        }
        $vf[] = "fps=$fpsExpr";
        $vf[] = "scale=$tw:$th:flags=lanczos";
        $vf[] = 'format=yuv420p';

        $max = self::maxBitrateK($th);

        return [
            $ffmpeg, '-y', '-hide_banner', '-nostdin', '-loglevel', 'error',
            '-i', $src,
            /* فقط اولین ویدیوی واقعی (نه جلد) و اولین صدا؛ داده، زیرنویس
               و ترک‌های متادیتای آیفون کنار گذاشته می‌شوند */
            '-map', '0:V:0', '-map', '0:a:0?', '-dn', '-sn',
            '-vf', implode(',', $vf),
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '22',
            '-maxrate', $max . 'k', '-bufsize', ($max * 2) . 'k',
            '-profile:v', 'high', '-level:v', '4.1',
            '-g', (string)$gop, '-keyint_min', (string)max(1, intdiv($gop, 2)),
            '-c:a', 'aac', '-b:a', '160k', '-ac', '2', '-ar', '48000',
            /* صدای گوشی گاهی شکاف یا پرش زمانی دارد؛ بدون این، صدا کم‌کم از
               تصویر جلو می‌افتد یا پخش‌کننده وسط کار منتظر صدا می‌ماند */
            '-af', 'aresample=async=1:first_pts=0',
            '-avoid_negative_ts', 'make_zero',
            /* بدون این، MOV با صدای پراکنده وسط تبدیل با «Too many packets
               buffered» می‌میرد و فایل نیمه‌کاره می‌ماند */
            '-max_muxing_queue_size', '4096',
            '-movflags', '+faststart',
            '-f', 'mp4', $dest,
        ];
    }

    /**
     * تبدیل را در پس‌زمینه شروع می‌کند و بلافاصله برمی‌گردد.
     *
     * خروجی اول در فایل موقت (.part.mp4) نوشته می‌شود و فقط بعد از اینکه
     * finalize طولش را با ورودی مقایسه کرد جای فایل نهایی می‌نشیند. پیش
     * از این، ffmpeg مستقیم روی مسیر نهایی می‌نوشت: فایل نیمه‌کاره با نام
     * نهایی وجود داشت، و برای .mp4 ای که باید تبدیل می‌شد ورودی و خروجی
     * یک مسیر بودند.
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

        $p    = $this->probe($srcAbs);
        $mime = (string)(@mime_content_type($srcAbs) ?: '');
        $plan = $this->plan($srcAbs, $mime === 'video/mp4' ? 'video/mp4' : ($mime ?: 'video/unknown'));
        $part = $destAbs . '.part.mp4';
        $log  = $destAbs . '.log';

        if ($plan['action'] === 'remux') {
            $args = [$this->ffmpeg, '-y', '-hide_banner', '-nostdin', '-loglevel', 'error', '-i', $srcAbs,
                     '-map', '0:V:0', '-map', '0:a:0?', '-c', 'copy', '-movflags', '+faststart', '-f', 'mp4', $part];
        } else {
            $args = self::transcodeArgs($this->ffmpeg, $srcAbs, $part, $p, $this->hasFilter('zscale'));
        }

        /* طول ورودی برای بررسی کامل بودن خروجی در finalize */
        $meta = json_decode((string)$this->db->value('SELECT meta FROM media WHERE id = ?', [$mediaId]), true) ?: [];
        $meta['source_duration'] = $p['duration'];
        $meta['convert_reasons'] = $plan['reasons'];
        $meta['convert_action']  = $plan['action'] === 'remux' ? 'remux' : 'transcode';
        $this->db->update('media', ['meta' => json_encode($meta, JSON_UNESCAPED_UNICODE)], ['id' => $mediaId]);

        /* عمدا PHP_BINARY نیست: در بستر FPM مقدارش /usr/sbin/php-fpm8.3
           است، و صدا زدنش با آرگومان‌های artisan فقط راهنمای php-fpm را
           چاپ می‌کند و رکورد برای همیشه روی processing می‌ماند. */
        $phpCli = phpCliPath();
        if ($phpCli === '') {
            return ['ok' => false, 'message' => 'باینری خط‌فرمان php پیدا نشد'];
        }

        /* nice: تبدیل فایل نباید پردازنده را از ترنسکدر زنده و سرو ویدیوی
           اتاق‌ها بگیرد — عجله‌ای نیست، ولی گیر کردن کانال زنده هست */
        $ff     = 'nice -n 10 ' . implode(' ', array_map('escapeshellarg', $args));
        $finish = escapeshellarg($phpCli) . ' ' . escapeshellarg(ROOT_PATH . '/artisan')
                . ' media:converted ' . (int)$mediaId . ' $?';
        $script = $ff . ' > ' . escapeshellarg($log) . ' 2>&1; ' . $finish . ' >> ' . escapeshellarg($log) . ' 2>&1';

        shell_exec('nohup sh -c ' . escapeshellarg($script) . ' > /dev/null 2>&1 &');

        return ['ok' => true, 'message' => $plan['action'] === 'remux' ? 'آماده‌سازی فایل شروع شد' : 'تبدیل شروع شد'];
    }

    public function hasFilter(string $name): bool
    {
        static $cache = [];
        if (!isset($cache[$name])) {
            $out = (string)shell_exec(escapeshellarg($this->ffmpeg) . ' -hide_banner -filters 2>/dev/null');
            $cache[$name] = (bool)preg_match('/^\s*\S+\s+' . preg_quote($name, '/') . '\s/m', $out);
        }
        return $cache[$name];
    }

    /**
     * بعد از پایان ffmpeg صدا زده می‌شود: رکورد را ready یا failed می‌کند.
     *
     * کد خروج صفر کافی نیست: خروجی باید تقریبا هم‌طول ورودی باشد. فایلی که
     * وسط کار قطع شده و «آماده» اعلام شود، روی تلویزیون تا همان‌جا پخش
     * می‌شود و بعد می‌ایستد.
     *
     * @param int $exitCode کد خروج ffmpeg — صفر یعنی موفق
     */
    public function finalize(int $mediaId, int $exitCode): void
    {
        $m = $this->db->row("SELECT * FROM media WHERE id=?", [$mediaId]);
        if (!$m) return;

        $rel  = (string)($m['file_path'] ?? '');
        $abs  = PUBLIC_PATH . $rel;
        $part = $abs . '.part.mp4';
        $log  = $abs . '.log';
        $meta = json_decode((string)($m['meta'] ?? ''), true) ?: [];

        $fail = function (string $note) use ($mediaId, $log, $part): void {
            if (is_file($log)) {
                $tail = trim((string)@file_get_contents($log));
                if ($tail !== '') $note .= ' — ' . mb_substr($tail, -200);
            }
            @unlink($part);
            $this->db->update('media', ['status' => 'failed', 'conv_note' => mb_substr($note, 0, 500)], ['id' => $mediaId]);
        };

        if ($exitCode !== 0 || !is_file($part) || filesize($part) === 0) { $fail('تبدیل ناموفق بود'); return; }

        $p   = $this->probe($part);
        $src = (float)($meta['source_duration'] ?? 0);
        if ($p['video'] !== 'h264') { $fail('خروجی ویدیوی قابل پخش ندارد'); return; }
        /* ۲ ثانیه یا ۲٪ اختلاف مجاز است (گرد شدن نرخ فریم و صدا) */
        if ($src > 0 && $p['duration'] < $src - max(2.0, $src * 0.02)) {
            $fail(sprintf('خروجی ناقص است (%d از %d ثانیه)', (int)$p['duration'], (int)$src));
            return;
        }

        if (!@rename($part, $abs)) { $fail('جایگزینی فایل نهایی ناموفق بود'); return; }

        $this->db->update('media', [
            'status'    => 'ready',
            'conv_note' => null,
            'file_size' => (int)filesize($abs),
            'duration'  => (int)round($p['duration']) ?: null,
            'width'     => $p['width']  ?: null,
            'height'    => $p['height'] ?: null,
            'mime_type' => 'video/mp4',
        ], ['id' => $mediaId]);

        @unlink($log);
        $orig = (string)($meta['original_file'] ?? '');
        if ($orig !== '' && $orig !== $rel) {
            $origAbs = PUBLIC_PATH . $orig;
            if (is_file($origAbs) && $origAbs !== $abs) @unlink($origAbs);
        }
    }

    /**
     * ویدیوهایی که قبلا «آماده» شده‌اند ولی روی تلویزیون گیر می‌کنند —
     * از جمله خروجی‌های نسخه‌ی قبلی همین تبدیل (۴K یا ۶۰ فریم با برچسب
     * سطح ۴٫۰) — دوباره تبدیل می‌شوند. فایل فعلی ورودی است و تا پایان
     * کار سر جایش می‌ماند.
     *
     * @return list<array{id:int,name:string,action:string,reasons:list<string>,started:bool}>
     */
    public function reconvertUnsafe(?int $onlyId = null, bool $dryRun = false): array
    {
        $sql = "SELECT id, name, file_path, mime_type, meta FROM media
                 WHERE type = 'video' AND status = 'ready' AND deleted_at IS NULL AND file_path LIKE '/uploads/%'";
        $par = [];
        if ($onlyId) { $sql .= ' AND id = ?'; $par[] = $onlyId; }

        $out = [];
        foreach ($this->db->rows($sql, $par) as $m) {
            $abs = PUBLIC_PATH . $m['file_path'];
            if (!is_file($abs)) continue;
            $plan = $this->plan($abs, (string)$m['mime_type']);
            if ($plan['action'] === 'ok') continue;

            $row = ['id' => (int)$m['id'], 'name' => (string)$m['name'], 'action' => $plan['action'],
                    'reasons' => $plan['reasons'], 'started' => false];
            if (!$dryRun) {
                /* خروجی کنار فایل فعلی با نام تازه؛ ورودی قدیمی بعد از موفقیت پاک می‌شود */
                $dir   = dirname($m['file_path']);
                $base  = pathinfo($m['file_path'], PATHINFO_FILENAME);
                $newRel = $dir . '/' . preg_replace('/(_tv\d*)+$/', '', $base) . '_tv' . time() . '.mp4';
                $meta = json_decode((string)($m['meta'] ?? ''), true) ?: [];
                $meta['original_file'] = $m['file_path'];
                $this->db->update('media', [
                    'status' => 'processing', 'file_path' => $newRel,
                    'meta'   => json_encode($meta, JSON_UNESCAPED_UNICODE),
                ], ['id' => (int)$m['id']]);
                $r = $this->startConversion((int)$m['id'], $abs, PUBLIC_PATH . $newRel);
                if (!$r['ok']) {
                    $this->db->update('media', ['status' => 'ready', 'file_path' => $m['file_path']], ['id' => (int)$m['id']]);
                }
                $row['started'] = $r['ok'];
            }
            $out[] = $row;
        }
        return $out;
    }
}
