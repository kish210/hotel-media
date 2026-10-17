<?php
/**
 * تست نصب درایور — مرز امنیتی (اسکریپت root) در حالت آزمایشی، و
 * بررسی‌های سمت PHP پیش از آنکه چیزی به sudo برسد.
 *
 * خود نصب با root اینجا اجرا نمی‌شود: sudoers در محیط تست نصب نیست.
 */
define('ROOT_PATH',    dirname(__DIR__, 2));
define('APP_PATH',     ROOT_PATH . '/app');
define('STORAGE_PATH', sys_get_temp_dir() . '/hm-driver-test-' . getmypid());
define('PUBLIC_PATH',  ROOT_PATH . '/public');

spl_autoload_register(function (string $c): void {
    $p = APP_PATH . '/' . str_replace(['App\\', '\\'], ['', '/'], $c) . '.php';
    if (file_exists($p)) require $p;
});

use App\Services\DriverService;

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✅ $label\n"; }
    else     { $fail++; echo "  ❌ $label" . ($detail ? "\n       → $detail" : '') . "\n"; }
}

@mkdir(STORAGE_PATH . '/cache', 0777, true);
$helper = ROOT_PATH . '/deploy/hotel-media-driver.sh';
$app    = STORAGE_PATH . '/app';
$inc    = $app . '/storage/drivers/incoming';
@mkdir($inc, 0777, true);

function helper(string ...$args): array {
    global $helper, $app;
    $cmd = 'HM_DRIVER_DRYRUN=1 HM_APP_DIR_OVERRIDE=' . escapeshellarg($app) . ' bash ' . escapeshellarg($helper)
         . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1; echo "EXIT:$?"';
    $out = (string)shell_exec($cmd);
    preg_match('/EXIT:(\d+)\s*$/', $out, $m);
    return [(int)($m[1] ?? -1), $out];
}

// ── ۱) مرز امنیتی: اسکریپت root ─────────────────────────────────
echo "\n── ۱) اسکریپت root ──\n";
file_put_contents("$inc/ok.deb", "!<arch>\n");
file_put_contents("$inc/nv.run", "#!/bin/sh\n");
file_put_contents(STORAGE_PATH . '/outside.deb', "!<arch>\n");
@symlink('/etc/passwd', "$inc/link.deb");

[$c] = helper('check');                                    check('check جواب ok می‌دهد', $c === 0);
[$c, $o] = helper('online', 'ffmpeg;id');                  check('id با تزریق فرمان رد شد', $c !== 0 && !str_contains($o, '+ apt'));
[$c, $o] = helper('online', 'v4l');                        check('id مجاز فقط بسته‌ی ثابت خودش را نصب می‌کند', $c === 0 && str_contains($o, 'apt-get install -y -q v4l-utils'));
[$c] = helper('online', 'decklink');                       check('DeckLink از اینترنت رد شد (فقط دستی)', $c !== 0);
[$c] = helper('file', 'ffmpeg', STORAGE_PATH . '/outside.deb');           check('فایل بیرون از incoming رد شد', $c !== 0);
[$c] = helper('file', 'ffmpeg', "$inc/../../../../outside.deb");          check('مسیر با .. رد شد', $c !== 0);
[$c] = helper('file', 'ffmpeg', "$inc/link.deb");                         check('symlink رد شد', $c !== 0);
[$c] = helper('file', 'ffmpeg', "$inc/nv.run");                           check('.run برای غیر NVIDIA رد شد', $c !== 0);
[$c, $o] = helper('file', 'nvidia', "$inc/nv.run");                       check('.run برای NVIDIA با dkms', $c === 0 && str_contains($o, '--dkms'));
[$c, $o] = helper('file', 'v4l', "$inc/ok.deb");                          check('.deb داخل incoming نصب می‌شود', $c === 0 && str_contains($o, 'apt-get install -y -q ' . realpath("$inc/ok.deb")));
[$c] = helper('file', 'rootkit', "$inc/ok.deb");                          check('id ناشناخته برای فایل رد شد', $c !== 0);
[$c] = helper('rm', '-rf');                                               check('فرمان ناشناخته رد شد', $c !== 0);

// فهرست PHP و اسکریپت باید یکی باشند؛ یک id در یکی و نه دیگری یعنی دکمه‌ای که همیشه خطا می‌دهد
$sh = (string)file_get_contents($helper);
preg_match('/case "\$id" in ([a-z0-9|]+)\)/', $sh, $m);
$shIds = explode('|', $m[1] ?? '');
sort($shIds);
$phpIds = array_keys(DriverService::CATALOG);
sort($phpIds);
check('فهرست درایورهای PHP و اسکریپت root یکی است', $shIds === $phpIds, implode(',', $shIds) . ' ≠ ' . implode(',', $phpIds));

// ── قفل: نباید به فایل مالک-دیگر وابسته باشد ─────────────────────
// `/run/lock` همگانی و sticky است و اوبونتو `fs.protected_regular=2` دارد؛
// در آن‌جا حتی root نمی‌تواند فایلی را که کس دیگری ساخته با `>` باز کند.
// اگر یک کاربر غیر root قفل را یک بار بسازد، helper برای همیشه با
// «قفل ساخته نشد» می‌میرد و صفحهٔ درایور پنل خراب می‌ماند (روی سرور دوم
// رخ داد). قفل باید در پوشه‌ای باشد که فقط root می‌سازد.
check('قفل در /run/lock مشترک نیست (protected_regular)',
      !str_contains($sh, '/var/lock/') && !str_contains($sh, '/run/lock/'),
      'قفل هنوز در پوشهٔ همگانی است');
check('قفل در پوشهٔ مخصوص root', str_contains($sh, 'LOCKDIR=/run/hotel-media'));

/* رفتار واقعی، نه فقط متن: فایل قفل قدیمی با مالک دیگر در مسیر
   قدیمی هست و helper باید باز هم کار کند. اگر نمی‌توانیم مالکیت را عوض
   کنیم (تست بدون root)، این بخش را رد می‌کنیم نه جعل. */
$old = '/var/lock/hotel-media-driver.lock';
if (function_exists('posix_geteuid') && posix_geteuid() === 0 && @file_put_contents($old, '') !== false) {
    @chown($old, 'www-data'); @chmod($old, 0644);
    [$c] = helper('check');
    check('با قفل قدیمیِ مالک-دیگر هم helper کار می‌کند', $c === 0, 'EXIT=' . $c);
    @unlink($old);
} else {
    echo "  ⏭ بدون root — آزمون قفل مالک-دیگر رد شد\n";
}

// ── بستهٔ ناموجود نباید کل نصب را بکشد ──────────────────────────
// یک بستهٔ ناموجود کل `apt-get install` را با کد ۱۰۰ می‌کشد و بقیه را هم
// نصب نمی‌کند — همان که `php8.5-opcache` نصب‌کنندهٔ اصلی را زمین زد.
check('apt_install هر بسته را جدا بررسی می‌کند (apt-cache policy)',
      str_contains($sh, 'apt-cache policy'));
check('بستهٔ ناموجود نام برده می‌شود، نه بی‌صدا رد',
      str_contains($sh, 'رد شد (در مخزن این نسخه نیست)'));
check('dvb در کاتالوگ PHP است', isset(DriverService::CATALOG['dvb']));

// ── ۲) بررسی‌های PHP پیش از sudo ─────────────────────────────────
echo "\n── ۲) بارگذاری ──\n";
$svc = new DriverService();
$mk  = function (string $name, string $content, int $err = UPLOAD_ERR_OK): array {
    $t = tempnam(STORAGE_PATH, 'up');
    file_put_contents($t, $content);
    return ['name' => $name, 'tmp_name' => $t, 'error' => $err, 'size' => strlen($content)];
};
$r = $svc->installFile('v4l', $mk('x.run', "#!/bin/sh"));
check('.run برای درایور غیر NVIDIA رد شد', !$r['ok'], $r['message']);
$r = $svc->installFile('v4l', $mk('evil.deb', '<html>not a package</html>'));
check('فایل HTML با پسوند .deb رد شد', !$r['ok'] && str_contains($r['message'], 'جور نیست'), $r['message']);
$r = $svc->installFile('decklink', $mk('Blackmagic.tar.gz', 'PK zip really'));
check('zip با پسوند .tar.gz رد شد', !$r['ok']);
$r = $svc->installFile('v4l', $mk('a.deb', '', UPLOAD_ERR_PARTIAL));
check('بارگذاری ناقص رد شد', !$r['ok']);
$r = $svc->installFile('nope', $mk('a.deb', "!<arch>\n"));
check('درایور ناشناخته رد شد', !$r['ok']);
$r = $svc->installOnline('decklink');
check('DeckLink آنلاین از سمت PHP هم رد شد', !$r['ok']);

if (!is_file(DriverService::HELPER)) {
    $r = $svc->installOnline('v4l');
    check('بدون ابزار root، پیام راهنما و نه خطای مبهم', !$r['ok'] && str_contains($r['message'], 'تنظیم نشده'), $r['message']);
    check('helperReady بدون sudoers false است', !$svc->helperReady());
}

$list = $svc->all();
check('فهرست وضعیت همه‌ی درایورها را دارد', count($list) === count(DriverService::CATALOG));
$ff = array_values(array_filter($list, fn($d) => $d['id'] === 'ffmpeg'))[0] ?? [];
$hasFf = trim((string)shell_exec('command -v ffmpeg')) !== '';
check('نسخه‌ی ffmpeg نصب‌شده خوانده شد', !$hasFf || ($ff['version'] ?? '') !== '', json_encode($ff['version'] ?? null));

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";

shell_exec('rm -rf ' . escapeshellarg(STORAGE_PATH));
exit($fail === 0 ? 0 : 1);
