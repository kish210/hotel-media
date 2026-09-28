<?php
/**
 * تست دریافت پروازهای فرودگاه کیش.
 *
 * چرا این تست لازم است: نگاشت وضعیت از متن فارسیِ فرودگاه به ENUM ما
 * جایی است که خطا بی‌صدا می‌ماند. پروازِ «لغو شد» اگر اشتباه به
 * scheduled نگاشت شود، تابلو به مهمان می‌گوید پروازش سر ساعت است.
 *
 * تله‌ی دوم: realTime فقط ساعت است بدون تاریخ. پروازِ ۲۳:۵۰ که ۰۰:۱۵
 * بلند شده، بدون تصحیحِ روز، ۲۳ ساعت «زودتر» حساب می‌شود و تابلو
 * تاخیر منفی نشان می‌دهد.
 */
define('ROOT_PATH',   dirname(__DIR__, 2));
define('APP_PATH',    ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);
date_default_timezone_set('Asia/Tehran');

spl_autoload_register(function (string $c): void {
    $p = APP_PATH . '/' . str_replace(['App\\', '\\'], ['', '/'], $c) . '.php';
    if (file_exists($p)) require $p;
});

$pass = 0;
$fail = 0;

function check(string $name, bool $ok, string $extra = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  \033[32m✅\033[0m $name\n"; return; }
    $fail++;
    echo "  \033[31m❌\033[0m $name" . ($extra !== '' ? "  — $extra" : '') . "\n";
}

echo "\n\033[1mپروازهای فرودگاه کیش\033[0m\n";

/* سازنده به Database نیاز دارد و این تست دیتابیس نمی‌خواهد —
   فقط منطق نگاشت آزمایش می‌شود. */
$ref = new ReflectionClass(\App\Services\KishAirportFetcher::class);
$svc = $ref->newInstanceWithoutConstructor();

$call = function (string $method, array $args) use ($ref, $svc) {
    $m = $ref->getMethod($method);
    $m->setAccessible(true);
    return $m->invokeArgs($svc, $args);
};

// ── تبدیل تاریخ ───────────────────────────────────────────────────
$d = $call('toDateTime', ['2026-09-26T18:15:00+03:30']);
check('تاریخ ISO با آفست +03:30 خوانده می‌شود', $d instanceof DateTimeImmutable);
check('ساعت درست تبدیل شد', $d && $d->format('H:i') === '18:15', $d ? $d->format('H:i') : 'null');
check('تاریخ خالی null می‌دهد', $call('toDateTime', ['']) === null);
check('تاریخ بی‌معنی null می‌دهد', $call('toDateTime', ['چیزی نیست']) === null);

// ── ساعت واقعی ────────────────────────────────────────────────────
$sched = new DateTimeImmutable('2026-09-26 18:15:00');
$est   = $call('realTimeToDateTime', [$sched, '18:31']);
check('ساعت واقعی به تاریخ همان پرواز می‌چسبد',
    $est && $est->format('Y-m-d H:i') === '2026-09-26 18:31');

$late  = new DateTimeImmutable('2026-09-26 23:50:00');
$next  = $call('realTimeToDateTime', [$late, '00:15']);
check('گذر از نیمه‌شب یک روز جلو می‌رود',
    $next && $next->format('Y-m-d H:i') === '2026-09-27 00:15',
    $next ? $next->format('Y-m-d H:i') : 'null');
check('تاخیر بعد از نیمه‌شب مثبت می‌ماند',
    $next && $next->getTimestamp() > $late->getTimestamp());
check('ساعت خالی null می‌دهد', $call('realTimeToDateTime', [$sched, '']) === null);

// ── نگاشت وضعیت ───────────────────────────────────────────────────
$m = fn(string $t) => $call('mapStatus', [$t, $sched, null]);

check('«لغو شد» → cancelled',            $m('لغو شد') === 'cancelled');
check('«نشست» → arrived',                $m('نشست (۲۱:۲۰)') === 'arrived');
check('«پایان تحویل بار» → arrived',     $m('پایان تحویل بار (۲۱:۵۱)') === 'arrived', $m('پایان تحویل بار (۲۱:۵۱)'));
/* «اعلام ورود» یعنی ساعت ورود اعلام شده، نه اینکه نشسته باشد —
   اگر arrived شود، تابلو هواپیمای در حال نزدیک‌شدن را رسیده نشان
   می‌دهد و استقبال‌کننده زودتر از موعد می‌رود سراغ تحویل بار. */
check('«اعلام ورود» هنوز arrived نیست',  $m('اعلام ورود (۲۱:۱۵)') === 'scheduled', $m('اعلام ورود (۲۱:۱۵)'));
check('«برخاست» → departed',             $m('برخاست') === 'departed');
check('«پرواز کرد» → departed',          $m('پرواز کرد') === 'departed');
check('«سوار شوید» → boarding',          $m('سوار شوید') === 'boarding');
check('«پذیرش» → boarding',              $m('پذیرش مسافر') === 'boarding');
/* عبارت واقعیِ فرودگاه کیش، از داده‌ی زنده */
check('«آماده پرواز» → boarding',        $m('آماده پرواز') === 'boarding', $m('آماده پرواز'));
check('«تاخیر» → delayed',               $m('تاخیر دارد') === 'delayed');
check('«تأخیر» با همزه هم شناخته می‌شود', $m('تأخیر دارد') === 'delayed');
check('«طبق برنامه» → scheduled',        $m('طبق برنامه') === 'scheduled');
check('متن خالی → scheduled',            $m('') === 'scheduled');

/* ترتیب بررسی: پروازِ لغوشده ممکن است هنوز ساعت داشته باشد */
check('«لغو» بر بقیه اولویت دارد', $m('لغو شد — نشست ۲۱:۲۰') === 'cancelled');

/* تاخیرِ حساب‌شده از اختلاف ساعت */
$far = $sched->modify('+45 minutes');
check('اختلاف ۴۵ دقیقه‌ای بدون متن → delayed',
    $call('mapStatus', ['', $sched, $far]) === 'delayed');
check('اختلاف ۲ دقیقه‌ای تاخیر حساب نمی‌شود',
    $call('mapStatus', ['', $sched, $sched->modify('+2 minutes')]) === 'scheduled');

// ── کد هوا ────────────────────────────────────────────────────────
check('کد ۰ یعنی صاف',        $call('wmoLabel', [0])  === 'صاف');
check('کد ۲ یعنی نیمه‌ابری',  $call('wmoLabel', [2])  === 'نیمه‌ابری');
check('کد ۶۳ یعنی باران',     $call('wmoLabel', [63]) === 'باران');
check('کد ۹۵ یعنی رعدوبرق',   $call('wmoLabel', [95]) === 'رعدوبرق');
check('کد ناشناخته خالی است', $call('wmoLabel', [-1]) === '');

// ── ساختار واقعی سایت (اگر شبکه در دسترس باشد) ────────────────────
// این بخش عمدا تست را شکست نمی‌دهد: سرور هتل ممکن است موقع اجرای
// تست به اینترنت وصل نباشد، و آن دلیل قرمز شدن تست نیست.
echo "\n\033[1mساختار زنده‌ی سایت\033[0m\n";

$ctx  = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true,
        'header' => "User-Agent: HotelMedia-Test\r\n"]]);
$body = @file_get_contents('https://kishairport.ir/api/flight-info', false, $ctx);

if ($body === false) {
    echo "  \033[33m⏭\033[0m سایت در دسترس نبود — این بخش رد شد\n";
} else {
    $j = json_decode($body, true);
    check('پاسخ JSON است', is_array($j));
    check('data.fa.departure وجود دارد', isset($j['data']['fa']['departure']));
    check('data.fa.arrival وجود دارد',   isset($j['data']['fa']['arrival']));

    $first = $j['data']['fa']['departure'][0] ?? null;
    if ($first) {
        foreach (['flightId','airline','airlineIata','flightNumber','city','date'] as $k) {
            check("فیلد $k هنوز در پاسخ هست", array_key_exists($k, $first));
        }
    }
}

echo "\n";
printf("  \033[1m%d موفق، %d ناموفق\033[0m\n\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
