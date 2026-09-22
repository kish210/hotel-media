<?php
/**
 * تست تبدیل زیرنویس به WebVTT.
 *
 * چرا این تست مهم‌تر از چیزی است که به نظر می‌رسد: اگر تبدیل خراب
 * باشد، مرورگر تلویزیون هیچ خطایی نمی‌دهد — فقط زیرنویس نشان نمی‌دهد.
 * مهمان فکر می‌کند فیلم زیرنویس ندارد و کسی هیچ‌وقت نمی‌فهمد.
 *
 * مشهورترین تله: SRT وقت را با کاما می‌نویسد (00:00:01,500) و WebVTT
 * با نقطه. یک کاراکتر، و کل زیرنویس بی‌صدا ناپدید می‌شود.
 */
define('ROOT_PATH',   dirname(__DIR__, 2));
define('APP_PATH',    ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);

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
    echo "  \033[31m❌\033[0m $name\n";
    if ($extra !== '') echo "       → " . str_replace("\n", ' ⏎ ', $extra) . "\n";
}

/* سرویس بدون دیتابیس ساخته می‌شود — تبدیل هیچ کوئری‌ای لازم ندارد و
   تست نباید به MySQL وابسته باشد. */
$svc = (new ReflectionClass(App\Services\SubtitleService::class))->newInstanceWithoutConstructor();

echo "\n\033[36m── SRT استاندارد ──\033[0m\n";
$srt = "1\n00:00:01,500 --> 00:00:04,000\nسلام\n\n"
     . "2\n00:00:05,000 --> 00:00:07,250\nLine one\nLine two\n";
$o = $svc->toVtt($srt, 'srt');
check('تبدیل موفق بود', $o['ok'], $o['message']);
check('هر دو زیرنویس خوانده شد', $o['cues'] === 2, 'تعداد=' . $o['cues']);
check('سرآیند WEBVTT دارد', str_starts_with($o['vtt'], "WEBVTT\n"));
check('کاما به نقطه تبدیل شد',
    str_contains($o['vtt'], '00:00:01.500 --> 00:00:04.000'),
    substr($o['vtt'], 0, 90));
check('زیرنویس چندخطی شکسته نشد', str_contains($o['vtt'], "Line one\nLine two"));

echo "\n\033[36m── SRT بدون شماره بلوک ──\033[0m\n";
$o = $svc->toVtt("00:00:02,000 --> 00:00:03,000\nبدون شماره\n", 'srt');
check('بدون شماره هم خوانده شد', $o['ok'] && $o['cues'] === 1, $o['message']);

echo "\n\033[36m── BOM و پایان خط ویندوزی ──\033[0m\n";
/* هر دو رایج‌اند چون زیرنویس معمولا روی ویندوز ساخته می‌شود. BOM
   سرآیند WEBVTT را بی‌اعتبار می‌کند و CRLF الگوی بلوک را می‌شکند. */
$o = $svc->toVtt("\xEF\xBB\xBF1\r\n00:00:01,000 --> 00:00:02,000\r\nمتن\r\n", 'srt');
check('BOM حذف شد', $o['ok'] && str_starts_with($o['vtt'], 'WEBVTT'), $o['message']);
check('CRLF درست مدیریت شد', $o['cues'] === 1, 'تعداد=' . $o['cues']);

echo "\n\033[36m── زمان تک‌رقمی و میلی‌ثانیه ناقص ──\033[0m\n";
$o = $svc->toVtt("1\n0:00:01,5 --> 0:00:02,0\nکوتاه\n", 'srt');
check('ساعت و میلی‌ثانیه پد شد',
    $o['ok'] && str_contains($o['vtt'], '00:00:01.500'),
    substr($o['vtt'], 0, 100));

echo "\n\033[36m── ورودی VTT ──\033[0m\n";
$o = $svc->toVtt("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nسلام\n", 'vtt');
check('VTT سالم پذیرفته شد', $o['ok'] && $o['cues'] === 1, $o['message']);

$o = $svc->toVtt("00:00:01,000 --> 00:00:02,000\nبدون سرآیند\n", 'vtt');
check('سرآیند گمشده اضافه شد', $o['ok'] && str_starts_with($o['vtt'], 'WEBVTT'), $o['message']);
check('کاما در فایل VTT هم اصلاح شد', str_contains($o['vtt'], '00:00:01.000 --> 00:00:02.000'));

echo "\n\033[36m── ASS/SSA ──\033[0m\n";
$ass = "[Events]\n"
     . "Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n"
     . 'Dialogue: 0,0:00:01.00,0:00:03.50,Default,,0,0,0,,{\i1}متن ایتالیک{\i0}' . "\n"
     . 'Dialogue: 0,0:00:04.00,0:00:05.00,Default,,0,0,0,,خط اول\Nخط دوم' . "\n";
$o = $svc->toVtt($ass, 'ass');
check('دیالوگ‌ها خوانده شد', $o['ok'] && $o['cues'] === 2, $o['message'] . ' تعداد=' . $o['cues']);
check('کدهای سبک حذف شد', !str_contains($o['vtt'], '{'), substr($o['vtt'], 0, 100));
check('\\N به شکست خط تبدیل شد', str_contains($o['vtt'], "خط اول\nخط دوم"));
check('صدم ثانیه به میلی‌ثانیه شد',
    str_contains($o['vtt'], '00:00:03.500'), substr($o['vtt'], 0, 130));

echo "\n\033[36m── متن با کاما در ASS ──\033[0m\n";
/* متن آخرین فیلد است و خودش کاما دارد؛ تقسیم نامحدود آن را می‌برید */
$ass2 = 'Dialogue: 0,0:00:01.00,0:00:02.00,Default,,0,0,0,,سلام، حال شما چطور است؟' . "\n";
$o = $svc->toVtt($ass2, 'ass');
check('متن دارای کاما کامل ماند',
    $o['ok'] && str_contains($o['vtt'], 'سلام، حال شما چطور است؟'),
    $o['vtt']);

echo "\n\033[36m── ورودی‌های نامعتبر ──\033[0m\n";
$o = $svc->toVtt('', 'srt');
check('فایل خالی رد شد', !$o['ok'], $o['message']);

$o = $svc->toVtt("یک متن بی‌ربط\nبدون هیچ زمانی", 'srt');
check('فایل بدون زمان رد شد', !$o['ok'], $o['message']);

$o = $svc->toVtt('چیزی', 'xyz');
check('قالب ناشناخته رد شد', !$o['ok'], $o['message']);

echo "\n\033[36m── نام زبان ──\033[0m\n";
check('fa به فارسی نگاشت شد', $svc->langLabel('fa') === 'فارسی');
check('en به English نگاشت شد', $svc->langLabel('en') === 'English');
check('کد ناشناس بزرگ برمی‌گردد', $svc->langLabel('xx') === 'XX');

echo "\n" . str_repeat('─', 58) . "\n";
if ($fail === 0) {
    echo "\033[32m✅ هر $pass تست پاس شد\033[0m\n";
    exit(0);
}
echo "\033[31m❌ $fail شکست از " . ($pass + $fail) . " تست\033[0m\n";
exit(1);
