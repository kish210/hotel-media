<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request};
use App\Services\TvheadendService;

/**
 * پخش کانال TVHeadend برای تلویزیون و مرورگر.
 *
 * چرا لازم است: کانال واردشده آدرس `http://127.0.0.1:9981/stream/channel/…`
 * داشت. تلویزیون به loopback سرور دسترسی ندارد و TVHeadend هم عمدا فقط روی
 * 127.0.0.1 گوش می‌دهد و احراز هویت Digest می‌خواهد (بدون رمز ۴۰۱). نتیجه:
 * کانال وارد می‌شد ولی هیچ دستگاهی نمی‌توانست پخشش کند.
 *
 * اینجا برنامه با اعتبارنامهٔ ذخیره‌شده از TVHeadend می‌خواند و همان بایت‌ها
 * را به دستگاه می‌دهد. به تنظیمات TVHeadend دست نمی‌خورد.
 *
 * دسترسی: آدرس با HMAC امضا شده (`sig`). کانال قفل‌شده آدرسش را از API
 * نمی‌گیرد، پس امضایش را هم ندارد؛ حدس‌زدن uuid به‌تنهایی کافی نیست.
 *
 * محدودیت: هر بیننده یک کارگر PHP-FPM را اشغال می‌کند (در نصب ما ۹۶ تا).
 * برای تعداد خیلی بیشتر باید پروکسی در nginx جایگزین شود.
 */
class StreamProxyController extends Controller
{
    /**
     * کانال‌هایی که مستقیماً از TVHeadend نمی‌آیند (مثلاً جریان HTTP سرور
     * قدیمی) با همین امضا از /tv/relay/… رد می‌شوند تا Orsay فایل MP4
     * تدریجی بگیرد. آدرس اصلی هرگز به تلویزیون نمی‌رود، پس نشانی داخلی
     * شبکه هم لو نمی‌رود.
     */
    public static function relaySign(string $url): string
    {
        return substr(hash_hmac('sha256', 'relay:' . $url, (string)env('APP_KEY', 'hotel-media')), 0, 24);
    }

    /** GET /tv/stream/{uuid}?sig=… */
    public function stream(Request $req, array $p): void
    {
        $uuid = strtolower((string)($p['uuid'] ?? ''));
        $sig  = (string)$req->get('sig', '');
        if (!preg_match('/^[a-f0-9]{32}$/', $uuid) || !hash_equals(TvheadendService::sign($uuid), $sig)) {
            http_response_code(403); echo 'forbidden'; return;
        }

        $row = $this->db->row(
            "SELECT s.* FROM iptv_channels c JOIN tvheadend_sources s ON s.id = c.source_id
              WHERE c.tvh_uuid = ? AND c.is_active = 1 AND s.is_active = 1 LIMIT 1",
            [$uuid]
        );
        if (!$row) { http_response_code(404); echo 'not found'; return; }
        /* سقف اتصال هم‌زمان برای هر IP: پلیرِ در حال ریتری و تب‌های رهاشده هر کدام
           یک اشتراک روی تیونر نگه می‌داشتند (روی سرور آزمایشی ۱۱ اشتراک بی‌حاصل
           دیده شد). قفل فایل با پایان پردازش خودکار آزاد می‌شود، پس نشتی ندارد.
           loopback (ffmpeg حالت compat) سقف ندارد. */
        $slot = null;
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip !== '127.0.0.1' && $ip !== '::1') {
            $slot = $this->acquireSlot($ip, 3);
            if ($slot === null) {
                http_response_code(429); header('Retry-After: 10'); echo 'too_many_streams'; return;
            }
        }

        // مرورگر دسکتاپ: ترنسکد سبک به H.264 پروگرسیو
        if ($req->get('compat') === '1') { $this->compat($uuid); return; }
        // تلویزیون Orsay (بدون MSE): پنجرهٔ MP4
        if ($req->get('mp4') === '1') { $this->mp4($uuid); return; }
        if (!function_exists('curl_init')) { http_response_code(500); echo 'curl missing'; return; }

        $profile = $_GET['profile'] ?? '';
        $profile = preg_match('/^[A-Za-z0-9._-]{1,40}$/', (string)$profile) ? $profile : ($row['stream_profile'] ?: 'pass');
        $url = rtrim((string)$row['server_url'], '/') . '/stream/channel/' . $uuid . '?profile=' . rawurlencode((string)$profile);

        // نشست را آزاد کن: استریم ساعت‌ها باز می‌ماند و نباید درخواست‌های دیگر
        // همین کاربر را قفل کند
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        @set_time_limit(0);
        ignore_user_abort(false);
        while (ob_get_level() > 0) ob_end_clean();

        $started = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPAUTH       => CURLAUTH_DIGEST | CURLAUTH_BASIC,
            CURLOPT_USERPWD        => ($row['username'] ?? '') . ':' . ($row['password'] ?? ''),
            CURLOPT_CONNECTTIMEOUT => 5,
            /* «۱ بایت بر ثانیه» هرگز فعال نمی‌شد: تی‌وی‌هدند روی فرکانس خراب هم چند
               بایت می‌فرستد. زیر ۸ کیلوبایت بر ثانیه (کانال عادی ۲۰۰+) به‌مدت ۱۰ ثانیه
               یعنی سیگنال/کانال عملا مرده است و اشتراک را رها می‌کنیم. */
            CURLOPT_LOW_SPEED_LIMIT => 8000,
            CURLOPT_LOW_SPEED_TIME  => 10,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION  => function ($c, string $data) use (&$started): int {
                if (!$started) {
                    $code = (int)curl_getinfo($c, CURLINFO_RESPONSE_CODE);
                    if ($code !== 200) { http_response_code($code === 401 ? 502 : 503); return 0; }
                    header('Content-Type: video/MP2T');
                    header('Cache-Control: no-store');
                    header('X-Accel-Buffering: no');
                    $started = true;
                }
                echo $data;
                flush();
                return connection_aborted() ? 0 : strlen($data);
            },
        ]);
        curl_exec($ch);
        curl_close($ch);
        if (!$started) { http_response_code(503); echo 'stream unavailable'; }
    }

    /**
     * GET /tv/relay?u=<base64url>&sig=…&mp4=1&win=600
     *
     * فقط برای Orsay. آدرس اصلی هرگز به تلویزیون نمی‌رود؛ کسی که
     * امضا ندارد نمی‌تواند از این مسیر به آدرس دلخواه سرور برسد (وگرنه
     * یک SSRF بود).
     */
    public function relay(Request $req): void
    {
        $enc = (string)$req->get('u', '');
        $sig = (string)$req->get('sig', '');
        $url = base64_decode(strtr($enc, '-_', '+/'), true);
        if ($url === false || !$url
            || !hash_equals(self::relaySign($url), $sig)
            || !preg_match('#^https?://#i', $url)) {
            http_response_code(403); echo 'forbidden'; return;
        }

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip !== '127.0.0.1' && $ip !== '::1' && $this->acquireSlot($ip, 3) === null) {
            http_response_code(429); header('Retry-After: 10'); echo 'too_many_streams'; return;
        }

        if ($req->get('mp4') === '1') {
            $win = (int)(filter_input(INPUT_GET, 'win', FILTER_VALIDATE_INT) ?: 600);
            $win = max(30, min(3600, $win));
            $this->transcode($url, true, $win);
            return;
        }
        $this->relayPassthrough($url);
    }

    /** عبور خام منبع HTTP — برای پلیرهایی که خودشان HLS/TS می‌خوانند. */
    private function relayPassthrough(string $url): void
    {
        if (!function_exists('curl_init')) { http_response_code(500); echo 'curl missing'; return; }
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        @set_time_limit(0);
        ignore_user_abort(false);
        while (ob_get_level() > 0) ob_end_clean();

        $started = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION   => true,
            CURLOPT_MAXREDIRS        => 3,
            CURLOPT_CONNECTTIMEOUT   => 5,
            CURLOPT_LOW_SPEED_LIMIT  => 8000,
            CURLOPT_LOW_SPEED_TIME   => 10,
            CURLOPT_RETURNTRANSFER   => false,
            CURLOPT_WRITEFUNCTION    => function ($c, string $data) use (&$started): int {
                if (!$started) { header('Content-Type: application/octet-stream'); header('Cache-Control: no-store'); $started = true; }
                echo $data; flush();
                return connection_aborted() ? 0 : strlen($data);
            },
        ]);
        curl_exec($ch);
        curl_close($ch);
        if (!$started) { http_response_code(503); echo 'relay unavailable'; }
    }

    /** یک اسلات قفل‌فایلی برای IP یا null اگر همهٔ $max اسلات پر است. */
    private function acquireSlot(string $ip, int $max)
    {
        $dir = '/tmp/hm-stream';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $key = preg_replace('/[^0-9a-fA-F.:]/', '_', $ip);
        for ($i = 0; $i < $max; $i++) {
            $fh = @fopen("$dir/$key.$i", 'c');
            if ($fh && flock($fh, LOCK_EX | LOCK_NB)) { return $fh; }
            if ($fh) { fclose($fh); }
        }
        return null;
    }

    /**
     * GET /tv/stream-status — فقط وضعیت درشت و بی‌رمز، برای اینکه پلیر به‌جای صفحهٔ
     * سیاه علت را بگوید: تیونر نیست / سیگنال ضعیف / تیونر روی فرکانس دیگری است.
     */
    public function status(Request $req): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $out = ['tuner' => false, 'weak' => false, 'mux' => '', 'snr' => null];
        try {
            $svc = new \App\Services\TvheadendTunerService();
            $src = $svc->source(1);
            if ($src) {
                $st = $svc->status($src);
                if (!empty($st['inputs'])) {
                    $in = $st['inputs'][0];
                    $out['tuner'] = true;
                    $out['mux']   = preg_match('/^(\d+)MHz/', (string)$in['stream'], $m) ? $m[1] : '';
                    $out['snr']   = $in['snr_db'] ?? null;
                    $out['weak']  = $out['snr'] !== null && $out['snr'] < 15;
                }
            }
        } catch (\Throwable) {}
        echo json_encode($out);
    }

    /**
     * حالت سازگار برای مرورگر دسکتاپ: کانال‌های زمینی ایران H.264 **ردیفی**
     * (۵۷۶i، ۴ فریم مرجع) هستند و دیکودر مرورگر آن را با
     * PIPELINE_ERROR_DECODE رد می‌کند (تلویزیون با دیکودر سخت‌افزاری می‌پخشد).
     * ffmpeg همان استریم امضاشدهٔ خودمان را می‌خواند (بدون رمز دوم)، deinterlace
     * و به H.264 پروگرسیو تبدیل می‌کند. هر بیننده یک ffmpeg دارد، پس فقط برای
     * آزمایش در مرورگر است نه تلویزیون‌ها.
     */
    private function compat(string $uuid): void
    {
        $in = 'http://127.0.0.1:' . ((int)($_SERVER['SERVER_PORT'] ?? 80) ?: 80) . TvheadendService::streamPath($uuid);
        $this->transcode($in, false, 0);
    }

    /**
     * GET /tv/stream/{uuid}?sig=…&mp4=1&win=600 — پنجرهٔ MP4 برای تلویزیون Orsay
     *
     * چرا: موتور Maple (سامسونگ ۲۰۱۳) نه MediaSource دارد نه HLS بومی. پس
     * hls.js و mpegts.js هر دو بی‌اثرند و تنها چیزی که <video> می‌پذیرد یک
     * فایل MP4 واقعی است. اینجا ffmpeg همان کانال را به MP4 تکه‌تکه
     * (`frag_keyframe`) تبدیل می‌کند تا بدون نیاز به seek بتوان از لوله
     * فرستاد، و پس از `win` ثانیه خودش تمام می‌شود. پلیر ۴ ثانیه پیش از
     * پایان پنجرهٔ بعدی را می‌گیرد، پس تصویر قطع نمی‌شود.
     *
     * هزینه: هر بیننده یک ffmpeg (مثل compat). برای تعداد زیاد اتاق،
     * ترنسکدر دائمی با خروجی HLS بهتر است؛ این مسیر برای هتل‌هایی است که
     * تلویزیون قدیمی دارند و تعدادشان کم است.
     */
    private function mp4(string $uuid): void
    {
        $win = (int)(filter_input(INPUT_GET, 'win', FILTER_VALIDATE_INT) ?: 600);
        $win = max(30, min(3600, $win));
        $in  = 'http://127.0.0.1:' . ((int)($_SERVER['SERVER_PORT'] ?? 80) ?: 80) . TvheadendService::streamPath($uuid);
        $this->transcode($in, true, $win);
    }

    /**
     * @param bool $mp4  خروجی قطعه‌بندی‌شدهٔ MP4 به‌جای MPEG-TS
     * @param int  $win  سقف ثانیه (۰ = بی‌پایان)
     */
    private function transcode(string $in, bool $mp4, int $win): void
    {
        // is_executable()/file_exists() روی /usr/bin با open_basedir استخر ممنوع است؛
        // اگر ffmpeg نبود proc_open خودش شکست می‌خورد
        $ffmpeg = '/usr/bin/ffmpeg';
        if (strtolower((string)($_SERVER['HTTPS'] ?? '')) === 'on') { http_response_code(501); echo 'compat unavailable'; return; }

        $cmd = [$ffmpeg, '-hide_banner', '-loglevel', 'quiet', '-fflags', 'nobuffer', '-i', $in];
        if ($win > 0) { $cmd[] = '-t'; $cmd[] = (string)$win; }
        /* yuv420p اجباری است: profile main تنها همین را می‌پذیرد و دیکودر
           سخت‌افزاری تلویزیون هم فقط همین را می‌پخشد. بدون این، منبع
           غیر 4:2:0 (مثلاً 4:4:4) بی‌صدا هیچ خروجی نمی‌دهد: ffmpeg با
           «main profile doesn't support 4:4:4» می‌میرد و بیننده صفحهٔ
           سیاه می‌بیند. */
        array_push($cmd,
            '-vf', 'yadif,format=yuv420p', '-c:v', 'libx264', '-preset', 'ultrafast', '-tune', 'zerolatency',
            '-profile:v', 'main', '-g', '50', '-c:a', 'aac', '-b:a', '128k', '-ac', '2');
        if ($mp4) {
            /* frag_keyframe + empty_moov: فایل بدون نوشتن moov در ابتدا/انتها
               قابل‌پخش تدریجی است — بدون این دو، ffmpeg تا آخر باید seek کند
               و لوله اصلا کار نمی‌کند. +faststart اینجا زیان می‌رساند. */
            array_push($cmd, '-movflags', 'frag_keyframe+empty_moov+default_base_moof',
                            '-f', 'mp4', 'pipe:1');
        } else {
            array_push($cmd, '-f', 'mpegts', 'pipe:1');
        }

        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) { http_response_code(500); echo 'ffmpeg failed'; return; }
        fclose($pipes[2]);   // loglevel quiet: چیزی نمی‌نویسد

        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        @set_time_limit(0);
        ignore_user_abort(false);
        /* nginx بافر نکند وگرنه MP4 تکه‌تکه جمع می‌شود و تلویزیون تا آخر
           پنجره منتظر می‌ماند */
        header('X-Accel-Buffering: no');
        if ($mp4) {
            header('Content-Type: video/mp4');
            header('Cache-Control: no-store');
        } else {
            header('Content-Type: video/MP2T');
            header('Cache-Control: no-store');
        }

        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 65536);
            if ($chunk === false || $chunk === '') { usleep(20000); continue; }
            echo $chunk;
            flush();
            if (connection_aborted()) break;
        }
        proc_terminate($proc, 15);
        fclose($pipes[1]);
        proc_close($proc);
    }
}
