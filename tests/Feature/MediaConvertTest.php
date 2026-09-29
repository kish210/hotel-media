<?php
/**
 * تست تبدیل ویدیو برای تلویزیون هتلی — با ffmpeg واقعی.
 *
 * گزارش میدانی: «MOV تبدیل‌شده وسط پخش گیر می‌کند». نسخه‌ی قبلی ابعاد
 * و نرخ فریم را دست نمی‌زد و خروجی ۴K یا ۶۰ فریمی را با برچسب سطح ۴٫۰
 * می‌ساخت که از توان رمزگشای تلویزیون بیشتر است. این تست منبع‌های
 * واقعی گوشی را می‌سازد و خروجی را با همان سقف تلویزیون می‌سنجد.
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

use App\Services\MediaConvertService as MC;

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✅ $label\n"; }
    else     { $fail++; echo "  ❌ $label" . ($detail ? "\n       → $detail" : '') . "\n"; }
}

// ── ۱) فرمان (بدون اجرا) ────────────────────────────────────────
echo "\n── ۱) فرمان تبدیل ──\n";
$a = MC::transcodeArgs('ffmpeg', 'in.mov', 'out.mp4',
    ['width' => 3840, 'height' => 2160, 'fps' => 59.94, 'hdr' => true], true);
$j = implode(' ', $a);
check('۴K به ۱۹۲۰×۱۰۸۰', str_contains($j, 'scale=1920:1080'));
check('۵۹٫۹۴ فریم به ۲۹٫۹۷', str_contains($j, 'fps=30000/1001'));
check('سطح ۴٫۱ و سقف بیت‌ریت', str_contains($j, '-level:v 4.1') && str_contains($j, '-maxrate 8000k'));
check('HDR به SDR', str_contains($j, 'tonemap'));
check('فقط ویدیوی واقعی و اولین صدا', str_contains($j, '-map 0:V:0 -map 0:a:0?'));
check('صف muxing بزرگ (جلوگیری از مرگ وسط تبدیل)', str_contains($j, '-max_muxing_queue_size 4096'));
$a = MC::transcodeArgs('ffmpeg', 'in', 'out', ['width' => 1080, 'height' => 1920, 'fps' => 30, 'hdr' => false], false);
check('ویدیوی عمودی در کادر ۱۰۸۰ جا می‌شود', str_contains(implode(' ', $a), 'scale=606:1080'), implode(' ', $a));
$a = MC::transcodeArgs('ffmpeg', 'in', 'out', ['width' => 640, 'height' => 360, 'fps' => 25, 'hdr' => false], false);
check('کوچک بزرگ‌نمایی نمی‌شود', str_contains(implode(' ', $a), 'scale=640:360') && str_contains(implode(' ', $a), 'fps=25'));
$a = MC::transcodeArgs('ffmpeg', 'in', 'out', ['width' => 1920, 'height' => 1080, 'fps' => 120, 'hdr' => false], false);
check('۱۲۰ فریم به ۳۰', str_contains(implode(' ', $a), 'fps=30'));

$db  = App\Core\Database::getInstance();
$svc = new MC($db);
if (!$svc->available()) {
    echo "\n  ⏭ ffmpeg نصب نیست — بخش اجرا رد شد\n";
} else {
    $dir = PUBLIC_PATH . '/uploads/mctest';
    @mkdir($dir, 0777, true);
    $ff = 'ffmpeg -hide_banner -loglevel error -y ';
    $src = "-f lavfi -i testsrc2=size=%s:rate=%s -f lavfi -i sine=frequency=440:sample_rate=48000 -t %d";

    /* آیفون: HEVC ده‌بیتی، ۶۰ فریم، نرخ متغیر، MOV */
    shell_exec($ff . sprintf($src, '2560x1440', 60, 8) . " -vf \"select='not(between(mod(n\\,120)\\,20\\,50))'\" -fps_mode vfr"
        . " -c:v libx265 -preset ultrafast -pix_fmt yuv420p10le -tag:v hvc1 -c:a aac -f mov $dir/iphone.mov 2>&1");
    /* MOV سالم: H.264 720p30 + AAC — فقط ظرف عوض شود */
    shell_exec($ff . sprintf($src, '1280x720', 30, 6) . " -c:v libx264 -preset ultrafast -pix_fmt yuv420p -c:a aac -f mov $dir/clean.mov 2>&1");
    /* MP4 با فهرست در انتها */
    shell_exec($ff . sprintf($src, '1280x720', 25, 6) . " -c:v libx264 -preset ultrafast -pix_fmt yuv420p -c:a aac $dir/tail.mp4 2>&1");
    /* MP4 با HEVC — ورودی و خروجی هم‌نام */
    shell_exec($ff . sprintf($src, '1280x720', 25, 6) . " -c:v libx265 -preset ultrafast -tag:v hvc1 -c:a aac -movflags +faststart $dir/hevc.mp4 2>&1");
    /* HDR (HLG) */
    shell_exec($ff . sprintf($src, '1920x1080', 30, 5) . " -c:v libx265 -preset ultrafast -pix_fmt yuv420p10le -color_trc arib-std-b67"
        . " -color_primaries bt2020 -colorspace bt2020nc -c:a aac -f mov $dir/hdr.mov 2>&1");

    echo "\n── ۲) تصمیم ──\n";
    $pl = fn(string $f, string $m) => $svc->plan("$dir/$f", $m);
    $x = $pl('iphone.mov', 'video/quicktime');
    check('آیفون: تبدیل کامل', $x['action'] === 'transcode', json_encode($x, JSON_UNESCAPED_UNICODE));
    check('دلیل‌ها: کدک، ابعاد، فریم، نرخ متغیر، ۱۰ بیت',
          count(array_filter($x['reasons'], fn($r) => preg_match('/hevc|ابعاد|فریم در ثانیه|متغیر|yuv420p10/u', $r))) >= 5,
          implode('، ', $x['reasons']));
    check('MOV سالم: فقط بازچینی', $pl('clean.mov', 'video/quicktime')['action'] === 'remux');
    check('MP4 با فهرست در انتها: بازچینی', $pl('tail.mp4', 'video/mp4')['action'] === 'remux');
    check('HDR: تبدیل', in_array('HDR', $pl('hdr.mov', 'video/quicktime')['reasons'], true));

    echo "\n── ۳) تبدیل واقعی در پس‌زمینه ──\n";
    $db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");
    $db->query("DELETE FROM media WHERE name LIKE 'MCTEST%'");
    $jobs = [];
    foreach ([['iphone.mov', 'iphone.mp4'], ['clean.mov', 'clean.mp4'], ['tail.mp4', 'tail.mp4'], ['hevc.mp4', 'hevc.mp4'], ['hdr.mov', 'hdr.mp4']] as [$in, $out]) {
        $id = (int)$db->insert('media', ['tenant_id' => 1, 'uploaded_by' => 1, 'name' => "MCTEST $in", 'original_name' => $in,
            'type' => 'video', 'file_path' => "/uploads/mctest/$out", 'mime_type' => 'video/mp4', 'file_size' => 1,
            'status' => 'processing', 'meta' => json_encode(['original_file' => "/uploads/mctest/$in"])]);
        $r = $svc->startConversion($id, "$dir/$in", "$dir/$out");
        $jobs[$in] = $id;
        if (!$r['ok']) echo "     start $in: {$r['message']}\n";
    }
    $deadline = time() + 150;
    do {
        sleep(2);
        $left = (int)$db->value("SELECT COUNT(*) FROM media WHERE name LIKE 'MCTEST%' AND status = 'processing'");
    } while ($left > 0 && time() < $deadline);

    $info = function (string $f): array {
        $j = json_decode((string)shell_exec('ffprobe -v quiet -print_format json -show_streams -show_format ' . escapeshellarg($f)), true) ?: [];
        $v = []; foreach ($j['streams'] ?? [] as $s) if (($s['codec_type'] ?? '') === 'video') { $v = $s; break; }
        return $v + ['_dur' => (float)($j['format']['duration'] ?? 0)];
    };

    $m = $db->row('SELECT * FROM media WHERE id=?', [$jobs['iphone.mov']]);
    check('آیفون: آماده شد', $m['status'] === 'ready', (string)$m['conv_note']);
    $v = $info(PUBLIC_PATH . $m['file_path']);
    check('خروجی H.264 High، سطح حداکثر ۴٫۱', ($v['codec_name'] ?? '') === 'h264' && (int)($v['level'] ?? 99) <= 41, json_encode([$v['profile'] ?? '', $v['level'] ?? '']));
    check('خروجی ۱۹۲۰×۱۰۸۰', (int)($v['width'] ?? 0) === 1920 && (int)($v['height'] ?? 0) === 1080);
    check('نرخ فریم ثابت ۳۰', ($v['r_frame_rate'] ?? '') === '30/1' && ($v['avg_frame_rate'] ?? '') === '30/1', ($v['r_frame_rate'] ?? '') . ' ' . ($v['avg_frame_rate'] ?? ''));
    check('۸ بیت 4:2:0', ($v['pix_fmt'] ?? '') === 'yuv420p');
    check('طول کامل ماند', abs($v['_dur'] - 8) < 1.0, (string)$v['_dur']);
    check('فهرست ابتدای فایل (faststart)', $svc->plan(PUBLIC_PATH . $m['file_path'], 'video/mp4')['action'] === 'ok');
    check('فایل اصلی و موقت پاک شد', !is_file("$dir/iphone.mov") && !is_file("$dir/iphone.mp4.part.mp4"));

    $m = $db->row('SELECT * FROM media WHERE id=?', [$jobs['clean.mov']]);
    $v = $info(PUBLIC_PATH . $m['file_path']);
    check('MOV سالم: بدون افت کیفیت، همان ۱۲۸۰×۷۲۰ (copy)', $m['status'] === 'ready' && (int)($v['width'] ?? 0) === 1280);

    $m = $db->row('SELECT * FROM media WHERE id=?', [$jobs['tail.mp4']]);
    check('MP4 فهرست‌انتها: حالا faststart و ready', $m['status'] === 'ready' && $svc->plan(PUBLIC_PATH . $m['file_path'], 'video/mp4')['action'] === 'ok');

    $m = $db->row('SELECT * FROM media WHERE id=?', [$jobs['hevc.mp4']]);
    $v = $info(PUBLIC_PATH . $m['file_path']);
    check('MP4 هم‌نام (HEVC): خروجی سالم جای ورودی نشست', $m['status'] === 'ready' && ($v['codec_name'] ?? '') === 'h264', (string)$m['conv_note']);

    $m = $db->row('SELECT * FROM media WHERE id=?', [$jobs['hdr.mov']]);
    $v = $info(PUBLIC_PATH . $m['file_path']);
    check('HDR به SDR با bt709', $m['status'] === 'ready' && ($v['pix_fmt'] ?? '') === 'yuv420p' && ($v['color_transfer'] ?? 'bt709') !== 'arib-std-b67',
          json_encode([$m['status'], $v['color_transfer'] ?? null, $m['conv_note']]));

    echo "\n── ۴) خروجی ناقص ──\n";
    /* ffmpeg «موفق» ولی فایل کوتاه‌تر از ورودی — مثل قطع شدن وسط کار */
    shell_exec($ff . sprintf($src, '640x360', 25, 3) . " -c:v libx264 -preset ultrafast -c:a aac -movflags +faststart $dir/short.mp4.part.mp4 2>&1");
    $id = (int)$db->insert('media', ['tenant_id' => 1, 'uploaded_by' => 1, 'name' => 'MCTEST short', 'original_name' => 'x',
        'type' => 'video', 'file_path' => '/uploads/mctest/short.mp4', 'mime_type' => 'video/mp4', 'file_size' => 1,
        'status' => 'processing', 'meta' => json_encode(['source_duration' => 60])]);
    $svc->finalize($id, 0);
    $m = $db->row('SELECT status, conv_note FROM media WHERE id=?', [$id]);
    check('خروجی ۳ ثانیه‌ای از ورودی ۶۰ ثانیه‌ای «ناقص» شد، نه ready', $m['status'] === 'failed' && str_contains((string)$m['conv_note'], 'ناقص'), (string)$m['conv_note']);
    check('فایل ناقص با نام نهایی باقی نماند', !is_file("$dir/short.mp4"));

    echo "\n── ۵) تبدیل دوباره‌ی قدیمی‌ها ──\n";
    /* خروجی نسخه‌ی قبلی: H.264 با ابعاد و فریم نامناسب، «ready» */
    shell_exec($ff . sprintf($src, '2560x1440', 60, 4) . " -c:v libx264 -preset ultrafast -level 4.0 -pix_fmt yuv420p -c:a aac -movflags +faststart $dir/old.mp4 2>&1");
    $old = (int)$db->insert('media', ['tenant_id' => 1, 'uploaded_by' => 1, 'name' => 'MCTEST old', 'original_name' => 'old',
        'type' => 'video', 'file_path' => '/uploads/mctest/old.mp4', 'mime_type' => 'video/mp4', 'file_size' => 1, 'status' => 'ready']);
    $dry = $svc->reconvertUnsafe($old, true);
    check('media:reconvert --dry-run فایل قدیمی را پیدا کرد', count($dry) === 1 && $dry[0]['action'] === 'transcode', json_encode($dry, JSON_UNESCAPED_UNICODE));
    $svc->reconvertUnsafe($old);
    for ($i = 0; $i < 60 && $db->value('SELECT status FROM media WHERE id=?', [$old]) === 'processing'; $i++) sleep(2);
    $m = $db->row('SELECT * FROM media WHERE id=?', [$old]);
    $v = $info(PUBLIC_PATH . $m['file_path']);
    check('دوباره تبدیل شد: ۱۰۸۰p ۳۰ فریم', $m['status'] === 'ready' && (int)($v['height'] ?? 0) === 1080 && ($v['r_frame_rate'] ?? '') === '30/1',
          json_encode([$m['status'], $m['file_path'], $v['height'] ?? null, $v['r_frame_rate'] ?? null, $v['avg_frame_rate'] ?? null, $m['conv_note']]));
    check('فایل قبلی پاک شد', !is_file("$dir/old.mp4"));

    $db->query("DELETE FROM media WHERE name LIKE 'MCTEST%'");
    shell_exec('rm -rf ' . escapeshellarg($dir));
}

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";
exit($fail === 0 ? 0 : 1);
