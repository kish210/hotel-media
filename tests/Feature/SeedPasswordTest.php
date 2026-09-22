<?php
/**
 * تست: رمز پیش‌فرضی که همه‌جا اعلام می‌شود واقعا کار می‌کند.
 *
 * چرا این تست وجود دارد: روی نصب واقعی، اسکریپت نصب و مستندات ISO و
 * صفحه‌ی ورود همگی «Admin@123456» را اعلام می‌کردند، ولی هش داخل
 * seed با آن نمی‌خورد. نتیجه: هیچ‌کس نمی‌توانست وارد پنل شود و تنها
 * پیامی که می‌گرفت «ایمیل یا رمز عبور اشتباه است» بود — بدون هیچ
 * سرنخی از اینکه مشکل از seed است نه از تایپ کاربر.
 *
 * این تست به دیتابیس نیاز ندارد؛ مستقیم هش را از فایل seed می‌خواند.
 */
define('ROOT_PATH', dirname(__DIR__, 2));

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);

$pass = 0;
$fail = 0;

function check(string $name, bool $ok, string $extra = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  \033[32m✅\033[0m $name\n"; return; }
    $fail++;
    echo "  \033[31m❌\033[0m $name\n";
    if ($extra !== '') echo "       → $extra\n";
}

/** رمزی که در اسکریپت نصب، مستندات و صفحه‌ی ورود وعده داده می‌شود */
const PROMISED = 'Admin@123456';
const ADMIN    = 'admin@hotelmedia.com';

$seedPath = ROOT_PATH . '/database/seeds/seed.sql';

echo "\n\033[36m── فایل seed ──\033[0m\n";
check('فایل seed وجود دارد', is_file($seedPath), $seedPath);
if (!is_file($seedPath)) { echo "\n❌ ادامه ممکن نیست\n"; exit(1); }

$seed = (string)file_get_contents($seedPath);

/* نام دیتابیس نباید در seed باشد — اتصال از قبل باز شده و USE روی
   نصبی با نام دیگر خطای ۱۰۴۴ می‌دهد و seed اصلا اجرا نمی‌شود. */
check('دستور USE در seed نیست',
    !preg_match('/^\s*USE\s+`/mi', $seed),
    'با USE، نصبی که نام دیتابیس دیگری دارد داده‌ی اولیه نمی‌گیرد');

check('CREATE DATABASE در seed نیست',
    !preg_match('/CREATE\s+DATABASE/i', $seed));

echo "\n\033[36m── کاربر مدیر ──\033[0m\n";

$adminLine = '';
foreach (preg_split('/\r?\n/', $seed) as $line) {
    if (str_contains($line, ADMIN)) { $adminLine = $line; break; }
}
check('کاربر مدیر در seed هست', $adminLine !== '', 'ایمیل: ' . ADMIN);

if ($adminLine === '') { echo "\n❌ ادامه ممکن نیست\n"; exit(1); }

preg_match('/\$2[aby]\$\d{2}\$[A-Za-z0-9.\/]{53}/', $adminLine, $m);
$hash = $m[0] ?? '';
check('هش bcrypt معتبر دارد', $hash !== '', substr($adminLine, 0, 90));

if ($hash === '') { echo "\n❌ ادامه ممکن نیست\n"; exit(1); }

echo "\n\033[36m── تطبیق رمز ──\033[0m\n";

/* همین یک تست است که باگ واقعی را می‌گیرد */
check('رمز اعلام‌شده با هش می‌خورد',
    password_verify(PROMISED, $hash),
    'رمز «' . PROMISED . '» با هش داخل seed نمی‌خورد — یعنی بعد از نصب '
    . 'هیچ‌کس نمی‌تواند وارد پنل شود');

check('نقش مدیر super_admin است',
    str_contains($adminLine, 'super_admin'),
    'بدون super_admin، بخش به‌روزرسانی سیستم دیده نمی‌شود');

check('کاربر فعال است', preg_match('/,\s*1\s*\)/', $adminLine) === 1);

echo "\n\033[36m── امنیت هش ──\033[0m\n";

$cost = (int)explode('$', $hash)[2];
check('هزینه‌ی bcrypt در محدوده‌ی معقول است',
    $cost >= 10 && $cost <= 13,
    'هزینه=' . $cost . ' — کمتر از ۱۰ ضعیف است، بیشتر از ۱۳ ورود را کند می‌کند');

/* اگر همه‌ی کاربران یک هش داشته باشند، یعنی همه یک رمز دارند */
preg_match_all('/\$2[aby]\$\d{2}\$[A-Za-z0-9.\/]{53}/', $seed, $all);
$counts = array_count_values($all[0] ?? []);
$dupes  = array_filter($counts, static fn(int $n): bool => $n > 1);
check('هش تکراری بین کاربران نیست',
    $dupes === [],
    $dupes ? 'یک هش برای ' . reset($dupes) . ' کاربر تکرار شده' : '');

echo "\n\033[36m── همخوانی با چیزی که اعلام می‌شود ──\033[0m\n";

$promisedIn = [];
foreach ([
    'deploy/install-production.sh',
    'deploy/iso/firstboot.sh',
    'deploy/iso/README.md',
    'resources/views/auth/login.php',
] as $rel) {
    $p = ROOT_PATH . '/' . $rel;
    if (is_file($p) && str_contains((string)file_get_contents($p), PROMISED)) {
        $promisedIn[] = $rel;
    }
}
check('رمز در جایی به کاربر اعلام می‌شود', $promisedIn !== [],
    'اگر هیچ‌جا اعلام نشود، تکنسین نمی‌داند با چه رمزی وارد شود');

echo "     اعلام‌شده در: " . implode('، ', $promisedIn) . "\n";

echo "\n" . str_repeat('─', 58) . "\n";
if ($fail === 0) {
    echo "\033[32m✅ هر $pass تست پاس شد\033[0m\n";
    exit(0);
}
echo "\033[31m❌ $fail شکست از " . ($pass + $fail) . " تست\033[0m\n";
exit(1);
