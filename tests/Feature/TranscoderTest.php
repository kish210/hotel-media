<?php
/**
 * تست ترنسکدر — ساخت فرمان، اعتبارسنجی، و اجرای واقعی ffmpeg:
 * شروع کانال، افتادن و برگشت با ناظر، تشخیص ورودی گیرکرده، تبدیل
 * فایل تا پایان، توقف و انتشار روی کانال IPTV.
 *
 * اگر ffmpeg نصب نباشد، بخش اجرا رد می‌شود و فقط منطق خالص تست می‌شود.
 */
define('ROOT_PATH',    dirname(__DIR__, 2));
define('APP_PATH',     ROOT_PATH . '/app');
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('VIEWS_PATH',   ROOT_PATH . '/resources/views');
define('PUBLIC_PATH',  ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('APP_DEBUG',    true);

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);

$ENV = [];
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) continue;
    [$k, $v] = explode('=', $l, 2);
    $ENV[trim($k)] = trim($v, " \t\"'");
}
function env(string $k, mixed $d = null): mixed { global $ENV; return $ENV[$k] ?? $d; }
date_default_timezone_set((string)env('APP_TIMEZONE', 'Asia/Tehran'));

spl_autoload_register(function (string $c): void {
    $p = APP_PATH . '/' . str_replace(['App\\', '\\'], ['', '/'], $c) . '.php';
    if (file_exists($p)) require $p;
});

use App\Services\TranscoderCommand as TC;
use App\Services\TranscoderService;

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✅ $label\n"; }
    else     { $fail++; echo "  ❌ $label" . ($detail ? "\n       → $detail" : '') . "\n"; }
}
function has(array $cmd, string ...$seq): bool {
    $n = count($seq);
    for ($i = 0; $i + $n <= count($cmd); $i++) if (array_slice($cmd, $i, $n) === $seq) return true;
    return false;
}

// ── ۱) اعتبارسنجی تنظیمات ────────────────────────────────────────
echo "\n── ۱) اعتبارسنجی ──\n";
$ok = fn(array $s, string $m = 'live', string $k = 'url') => TC::normalize($s, $m, $k);

check('بدون خروجی رد شد', !$ok(['outputs' => []])['ok']);
check('دو خروجی HLS رد شد', !$ok(['outputs' => [['type' => 'hls'], ['type' => 'hls']]])['ok']);
check('RTMP با HEVC رد شد', !$ok(['video' => ['codec' => 'hevc'], 'outputs' => [['type' => 'rtmp', 'url' => 'rtmp://x/y']]])['ok']);
check('MPEG-2 روی HLS رد شد', !$ok(['video' => ['codec' => 'mpeg2'], 'outputs' => [['type' => 'hls']]])['ok']);
check('MPEG-2 با NVENC رد شد', !$ok(['video' => ['codec' => 'mpeg2', 'hw' => 'nvenc'], 'outputs' => [['type' => 'udp', 'url' => 'udp://239.1.1.1:1234']]])['ok']);
check('ارتفاع دلخواه رد شد', !$ok(['renditions' => [['height' => 999, 'bitrate' => 1000]], 'outputs' => [['type' => 'hls']]])['ok']);
check('آدرس UDP با نقل‌قول رد شد', !$ok(['outputs' => [['type' => 'udp', 'url' => "udp://1.2.3.4:5'|x"]]])['ok']);
check('آدرس UDP با جداکننده‌ی tee رد شد', !$ok(['outputs' => [['type' => 'udp', 'url' => 'udp://1.2.3.4:5|rtmp://evil']]])['ok']);
check('لوگو بیرون از uploads رد شد', !$ok(['overlay' => ['image' => '/etc/passwd.png'], 'outputs' => [['type' => 'hls']]])['ok']);
check('لوگو با .. رد شد', !$ok(['overlay' => ['image' => '/uploads/../../x.png'], 'outputs' => [['type' => 'hls']]])['ok']);
check('لوگو با copy رد شد', !$ok(['video' => ['codec' => 'copy'], 'overlay' => ['image' => '/uploads/a.png'], 'outputs' => [['type' => 'hls']]])['ok']);
check('VOD با خروجی UDP رد شد', !$ok(['outputs' => [['type' => 'udp', 'url' => 'udp://239.1.1.1:1']]], 'vod', 'file')['ok']);
$n = $ok(['renditions' => [['height' => 480, 'bitrate' => 1000], ['height' => 1080, 'bitrate' => 5000]], 'outputs' => [['type' => 'hls']]]);
check('کیفیت‌ها از بالا به پایین مرتب شدند', ($n['settings']['renditions'][0]['height'] ?? 0) === 1080);
$n = $ok(['audio' => ['languages' => ['fas', 'EN', 'eng', 'x;rm']], 'outputs' => [['type' => 'hls']]]);
check('زبان‌های نامعتبر صدا حذف شدند', ($n['settings']['audio']['languages'] ?? []) === ['fas', 'eng'],
      json_encode($n['settings']['audio']['languages'] ?? []));

// ── ۲) ساخت فرمان ───────────────────────────────────────────────
echo "\n── ۲) فرمان ffmpeg ──\n";
$env = ['ffmpeg' => 'ffmpeg', 'out_dir' => '/o', 'work_dir' => '/w', 'rtsp_timeout_opt' => '-timeout'];
$job = fn(string $url, array $s, string $kind = 'url', string $mode = 'live') =>
    ['name' => 't', 'mode' => $mode, 'input_kind' => $kind, 'input_url' => $url, 'settings' => TC::normalize($s, $mode, $kind)['settings']];

$c = TC::build($job('udp://239.0.0.1:1234', ['outputs' => [['type' => 'hls']]]), $env);
check('UDP بدون گزینه‌ی rtsp_transport (باگ نسخه‌ی قبل)', !in_array('-rtsp_transport', $c, true));
check('UDP با بافر بزرگ', in_array('udp://239.0.0.1:1234?fifo_size=1000000&overrun_nonfatal=1', $c, true));
check('پیشرفت در پوشه‌ی کاری', has($c, '-progress', '/w/progress.txt'));

$c = TC::build($job('rtsp://cam/1', ['input' => ['rtsp_transport' => 'udp'], 'outputs' => [['type' => 'hls']]]), $env);
check('RTSP با transport انتخابی و timeout', has($c, '-rtsp_transport', 'udp') && has($c, '-timeout', '10000000'));

$c = TC::build($job('https://x/live.m3u8', ['outputs' => [['type' => 'hls']]]), $env);
check('HTTP/HLS با اتصال مجدد', has($c, '-reconnect', '1') && in_array('-rw_timeout', $c, true));

$c = TC::build($job('udp://239.0.0.1:1', [
    'renditions' => [['height' => 1080, 'bitrate' => 5000], ['height' => 720, 'bitrate' => 2500]],
    'audio' => ['languages' => ['fas', 'eng']],
    'outputs' => [['type' => 'hls'], ['type' => 'udp', 'url' => 'udp://239.9.9.9:5000', 'ttl' => 8]],
]), $env);
$vsm = $c[array_search('-var_stream_map', $c, true) + 1] ?? '';
check('ABR با گروه صدای دوزبانه', $vsm === 'a:0,agroup:aud,language:fas,default:yes a:1,agroup:aud,language:eng v:0,agroup:aud v:1,agroup:aud', $vsm);
check('master playlist به نام index.m3u8', has($c, '-master_pl_name', 'index.m3u8'));
check('فیلتر به سه شاخه تقسیم شد (دو کیفیت + UDP)', str_contains(implode(' ', $c), 'split=3[s0][s1][s2]'));
check('UDP با pkt_size و ttl داخل tee', str_contains(end($c), 'udp://239.9.9.9:5000?pkt_size=1316&ttl=8'));
check('مقصد مرده بقیه را نمی‌اندازد (onfail=ignore)', str_contains(end($c), 'onfail=ignore'));
check('فریم کلیدی زمانی', in_array('expr:gte(t,n_forced*2)', $c, true));

$c = TC::build($job('udp://239.0.0.1:1', ['video' => ['codec' => 'h264', 'hw' => 'nvenc'], 'outputs' => [['type' => 'hls']]]), $env);
check('NVENC با preset متناظر', has($c, '-c:v:0', 'h264_nvenc') && has($c, '-preset:v:0', 'p3'));
$c = TC::build($job('udp://239.0.0.1:1', ['video' => ['hw' => 'vaapi'], 'outputs' => [['type' => 'hls']]]), $env);
check('VAAPI با دستگاه و hwupload', in_array('-vaapi_device', $c, true) && str_contains(implode(' ', $c), 'hwupload'));

$c = TC::build($job('/x.ts', ['outputs' => [['type' => 'hls']]], 'file', 'vod'), $env);
check('VOD: پلی‌لیست vod و بدون -re', has($c, '-hls_playlist_type', 'vod') && !in_array('-re', $c, true));
$c = TC::build($job('/x.ts', ['input' => ['loop' => 1], 'outputs' => [['type' => 'hls']]], 'file', 'live'), $env);
check('فایل به‌عنوان کانال زنده: تکرار و سرعت واقعی', has($c, '-stream_loop', '-1') && in_array('-re', $c, true));

$c = TC::build($job('/dev/video0', ['input' => ['alsa' => 'hw:1,0'], 'outputs' => [['type' => 'hls']]], 'v4l2'), $env);
check('کارت کپچر HDMI با صدای ALSA', has($c, '-f', 'v4l2') && has($c, '-f', 'alsa', '-i', 'hw:1,0') && in_array('1:a:0?', $c, true));

$c = TC::build($job('udp://239.0.0.1:1', ['subtitles' => ['mode' => 'copy', 'languages' => ['fas']],
    'outputs' => [['type' => 'udp', 'url' => 'udp://239.2.2.2:1']]]), $env);
check('زیرنویس با فیلتر زبان روی MPEG-TS', in_array('0:s:m:language:fas?', $c, true) && has($c, '-c:s', 'copy'));

// ── ۳) اجرای واقعی ──────────────────────────────────────────────
$db  = App\Core\Database::getInstance();
$svc = new TranscoderService($db);
$db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");

function cleanup(App\Core\Database $db, TranscoderService $svc): void {
    foreach ($db->rows("SELECT id FROM transcoder_jobs WHERE tenant_id=1 AND slug LIKE 'tctest%'") as $r) {
        $svc->delete(1, (int)$r['id']);
    }
    $db->query("DELETE FROM iptv_channels WHERE tenant_id=1 AND name='TCTEST'");
    @unlink(PUBLIC_PATH . '/uploads/tctest/src.ts');
    @rmdir(PUBLIC_PATH . '/uploads/tctest');
}
cleanup($db, $svc);

echo "\n── ۳) اعتبارسنجی ورودی ──\n";
$base = ['name' => 'x', 'slug' => 'tctest-v', 'settings' => ['outputs' => [['type' => 'hls']]]];
check('پروتکل file:// رد شد', !$svc->save(1, $base + ['input_url' => 'file:///etc/passwd'])['ok']);
check('خروجی خود ترنسکدر به‌عنوان ورودی رد شد', !$svc->save(1, $base + ['input_url' => 'http://127.0.0.1/hls/tc-a/index.m3u8'])['ok']);
check('فایل بیرون از uploads رد شد', !$svc->save(1, $base + ['input_kind' => 'file', 'input_url' => '/etc/passwd'])['ok']);
check('فایل با .. رد شد', !$svc->save(1, $base + ['input_kind' => 'file', 'input_url' => '/uploads/../../.env'])['ok']);
check('دستگاه کپچر دلخواه رد شد', !$svc->save(1, $base + ['input_kind' => 'v4l2', 'input_url' => '/dev/sda'])['ok']);

if (!$svc->available()) {
    echo "\n  ⏭ ffmpeg نصب نیست — بخش اجرای واقعی رد شد\n";
} else {
    echo "\n── ۴) کانال زنده، افتادن و برگشت ──\n";
    @mkdir(PUBLIC_PATH . '/uploads/tctest', 0777, true);
    shell_exec('ffmpeg -hide_banner -loglevel error -y -f lavfi -i testsrc2=size=640x360:rate=25 '
        . '-f lavfi -i sine=frequency=440:sample_rate=48000 -t 12 -map 0:v -map 1:a -c:v libx264 -preset ultrafast -g 25 '
        . '-c:a mp2 -f mpegts ' . escapeshellarg(PUBLIC_PATH . '/uploads/tctest/src.ts') . ' 2>&1');

    $r = $svc->save(1, ['name' => 'کانال تست', 'slug' => 'tctest-live', 'input_kind' => 'file',
        'input_url' => '/uploads/tctest/src.ts', 'settings' => [
            'video' => ['preset' => 'ultrafast'], 'renditions' => [['height' => 360, 'bitrate' => 500]],
            'input' => ['loop' => 1], 'outputs' => [['type' => 'hls', 'segment' => 1]]]]);
    check('کار زنده ساخته شد', $r['ok'], $r['message']);
    $live = (int)($r['id'] ?? 0);
    check('نام مسیر تکراری رد شد', !$svc->save(1, ['name' => 'y', 'slug' => 'tctest-live', 'input_kind' => 'file',
        'input_url' => '/uploads/tctest/src.ts', 'settings' => ['outputs' => [['type' => 'hls']]]])['ok']);

    $st = $svc->start(1, $live);
    check('کانال شروع شد', $st['ok'], $st['message']);
    sleep(4);
    $job = $svc->find(1, $live);
    check('فرایند زنده است', $svc->alive($job));
    $s = $svc->stats($job);
    check('ffmpeg پیشرفت گزارش می‌دهد', $s['age'] >= 0 && $s['age'] < 5 && $s['fps'] > 0, json_encode($s));
    check('master playlist ساخته شد', is_file(TranscoderService::LIVE_ROOT . '/tc-tctest-live/index.m3u8'));
    $svc->superviseOnce();
    check('ناظر وضعیت را running کرد', $svc->find(1, $live)['status'] === 'running');

    // قتل از بیرون — مثل OOM یا خطای ورودی
    shell_exec('kill -9 ' . (int)$job['pid']);
    usleep(300000);
    $svc->superviseOnce(time() + 10);
    $job2 = $svc->find(1, $live);
    check('ناظر کانال افتاده را برگرداند', $svc->alive($job2) && (int)$job2['pid'] !== (int)$job['pid']);
    check('شمارنده‌ی راه‌اندازی مجدد', (int)$job2['restarts'] === 1);

    $svc->superviseOnce(time() + 10);
    check('فاصله‌ی راه‌اندازی رعایت شد (دو بار پشت هم نه)', (int)$svc->find(1, $live)['restarts'] === 1);

    $stop = $svc->stop(1, $live);
    $job3 = $svc->find(1, $live);
    check('توقف: فرایند بسته و وضعیت stopped', !$svc->alive($job2) && $job3['status'] === 'stopped' && $job3['desired'] === 'stopped');
    $svc->superviseOnce(time() + 120);
    check('ناظر کار متوقف‌شده را روشن نمی‌کند', !$svc->alive($svc->find(1, $live)));

    echo "\n── ۵) ورودی گیرکرده ──\n";
    /* UDP بدون فرستنده: ffmpeg زنده می‌ماند و منتظر می‌ماند — همان
       ماهواره‌ی قطع‌شده */
    $r = $svc->save(1, ['name' => 'بی‌سیگنال', 'slug' => 'tctest-stall', 'input_url' => 'udp://127.0.0.1:5999',
        'settings' => ['video' => ['preset' => 'ultrafast'], 'renditions' => [['height' => 360, 'bitrate' => 500]],
                       'outputs' => [['type' => 'hls']]]]);
    $stall = (int)$r['id'];
    $svc->start(1, $stall);
    sleep(1);
    $j = $svc->find(1, $stall);
    check('ffmpeg منتظر ورودی زنده است', $svc->alive($j));
    $svc->superviseOnce(time() + TranscoderService::STALL_SECONDS + 10);
    $j2 = $svc->find(1, $stall);
    check('گیرکردن تشخیص داده و دوباره راه‌اندازی شد', (int)$j2['restarts'] === 1 && (int)$j2['pid'] !== (int)$j['pid'],
          json_encode(['restarts' => $j2['restarts'], 'err' => $j2['last_error']], JSON_UNESCAPED_UNICODE));
    $svc->stop(1, $stall);

    echo "\n── ۶) تبدیل فایل (VOD) ──\n";
    $r = $svc->save(1, ['name' => 'فیلم', 'slug' => 'tctest-vod', 'mode' => 'vod', 'input_kind' => 'file',
        'input_url' => '/uploads/tctest/src.ts', 'settings' => [
            'video' => ['preset' => 'ultrafast'],
            'renditions' => [['height' => 360, 'bitrate' => 500], ['height' => 240, 'bitrate' => 300]],
            'outputs' => [['type' => 'hls', 'segment' => 4]]]]);
    $vod = (int)$r['id'];
    $svc->start(1, $vod);
    for ($i = 0; $i < 60; $i++) {
        sleep(1);
        $svc->superviseOnce();
        if ($svc->find(1, $vod)['status'] === 'finished') break;
    }
    $v = $svc->find(1, $vod);
    check('تبدیل تمام شد', $v['status'] === 'finished', $v['status'] . ' ' . $v['last_error']);
    check('پیشرفت ۱۰۰٪', (int)$v['progress'] === 100);
    $m = (string)@file_get_contents(PUBLIC_PATH . '/uploads/transcoded/tctest-vod/index.m3u8');
    check('master playlist در مسیر عمومی با دو کیفیت', substr_count($m, '#EXT-X-STREAM-INF') === 2);
    check('لاگ ffmpeg در مسیر عمومی نیست', !is_file(PUBLIC_PATH . '/uploads/transcoded/tctest-vod/ffmpeg.log'));
    check('ناظر کار تمام‌شده را دوباره اجرا نمی‌کند', $v['desired'] === 'stopped');

    echo "\n── ۷) بررسی ورودی و انتشار ──\n";
    $p = $svc->probe('file', '/uploads/tctest/src.ts');
    $types = array_column($p['streams'] ?? [], 'type');
    check('ffprobe تصویر و صدا را دید', $p['ok'] && in_array('video', $types, true) && in_array('audio', $types, true));

    $chId = (int)$db->insert('iptv_channels', ['tenant_id' => 1, 'name' => 'TCTEST', 'stream_url' => 'udp://239.0.0.1:1', 'protocol' => 'udp']);
    $pub = $svc->publish(1, $live, $chId);
    $ch  = $db->row('SELECT stream_url FROM iptv_channels WHERE id=?', [$chId]);
    check('کانال به خروجی HLS وصل شد', $pub['ok'] && $ch['stream_url'] === '/hls/tc-tctest-live/index.m3u8', $pub['message']);
}

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";

cleanup($db, $svc);
exit($fail === 0 ? 0 : 1);
