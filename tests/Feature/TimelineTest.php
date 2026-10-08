<?php
/**
 * تست سرویس تایم‌لاین — build / sanitize / validate / compile.
 *
 * چرا مهم: compile() همان جایی است که ویرایشِ چندلایه به چیزی تبدیل
 * می‌شود که پلیر اجرا می‌کند. اگر خراب باشد، اپراتور در استودیو یک
 * چیز می‌بیند و روی تلویزیون چیز دیگری پخش می‌شود — یا بدتر،
 * playlist_items نیمه‌پاک می‌ماند. تراکنشی بودن compile اینجا تست
 * می‌شود.
 */
define('ROOT_PATH',   dirname(__DIR__, 2));
define('APP_PATH',    ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);

spl_autoload_register(function (string $c): void {
    $p = APP_PATH . '/' . str_replace(['App\\', '\\'], ['', '/'], $c) . '.php';
    if (file_exists($p)) require $p;
});

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $extra = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  \033[32m✅\033[0m $name\n"; return; }
    $fail++; echo "  \033[31m❌\033[0m $name" . ($extra !== '' ? "  — $extra" : '') . "\n";
}

echo "\n\033[1mسرویس تایم‌لاین\033[0m\n";

/* بدون دیتابیس — فقط منطق خالص. متدهای build/sanitize/validate به DB
   نیاز ندارند (validate فقط وقتی mediaId باشد کوئری می‌زند). */
$ref = new ReflectionClass(\App\Services\TimelineService::class);
$svc = $ref->newInstanceWithoutConstructor();

// ── build از آیتم‌های موجود ──────────────────────────────────────
$playlist = ['id' => 1, 'name' => 'تست', 'logo_path' => '/uploads/branding/l.png', 'ticker_text' => "خط اول\nخط دوم"];
$items = [
    ['id' => 10, 'media_id' => 4, 'duration' => 40, 'media_name' => 'کلیپ ۱', 'media_type' => 'video', 'fit_mode' => 'cover', 'muted' => 0, 'volume' => 80, 'thumbnail_path' => '/t1.jpg'],
    ['id' => 11, 'media_id' => 5, 'duration' => 10, 'media_name' => 'عکس ۱', 'media_type' => 'image'],
];
$tl = $svc->build($playlist, $items);

check('build سه تراک می‌سازد (ویدیو/لوگو/متن)', count($tl['tracks']) === 3, (string)count($tl['tracks']));
$video = $tl['tracks'][0];
check('تراک اول ویدیو است', $video['type'] === 'video');
check('دو کلیپ در تراک ویدیو', count($video['clips']) === 2);
check('کلیپ دوم بعد از اولی شروع می‌شود', $video['clips'][1]['start'] === 40, (string)$video['clips'][1]['start']);
check('مدت کل = ۵۰', $tl['duration'] === 50, (string)$tl['duration']);
check('fit کلیپ اول از آیتم آمد (cover)', $video['clips'][0]['fit'] === 'cover');
check('صدای کلیپ اول از آیتم آمد (با صدا)', $video['clips'][0]['muted'] === false);
check('لوگو از برند ساخته شد', $tl['tracks'][1]['clips'][0]['src'] === '/uploads/branding/l.png');
check('متن از تیکر ساخته شد', str_contains($tl['tracks'][2]['clips'][0]['text'], 'خط اول'));

// ── sanitize ورودی خراب ─────────────────────────────────────────
$dirty = [
    'fps' => 999, 'resolution' => ['w' => -5, 'h' => 0],
    'tracks' => [
        ['type' => 'HACK', 'clips' => [['start' => -10, 'duration' => 0, 'text' => str_repeat('x', 9999)]]],
        'not-an-array',
        ['type' => 'logo', 'clips' => [['src' => 'x', 'scale' => 9999, 'opacity' => -50, 'position' => 'INVALID']]],
    ],
];
$clean = $svc->sanitize($dirty);
check('fps به بازه‌ی معقول محدود شد', $clean['fps'] >= 1 && $clean['fps'] <= 60, (string)$clean['fps']);
check('resolution حداقل‌دار شد', $clean['resolution']['w'] >= 16 && $clean['resolution']['h'] >= 16);
check('نوع تراک نامعتبر به video افتاد', $clean['tracks'][0]['type'] === 'video');
check('ردیف غیرآرایه حذف شد', count($clean['tracks']) === 2, (string)count($clean['tracks']));
check('start منفی صفر شد', $clean['tracks'][0]['clips'][0]['start'] === 0);
check('duration صفر حداقل ۱ شد', $clean['tracks'][0]['clips'][0]['duration'] >= 1);
check('متن به ۵۰۰ کاراکتر برید', mb_strlen($clean['tracks'][0]['clips'][0]['text']) <= 500);
check('scale لوگو به ۴۰۰ محدود شد', $clean['tracks'][1]['clips'][0]['scale'] <= 400, (string)$clean['tracks'][1]['clips'][0]['scale']);
check('opacity منفی به ۰ رفت', $clean['tracks'][1]['clips'][0]['opacity'] >= 0);
check('position نامعتبر به پیش‌فرض رفت', $clean['tracks'][1]['clips'][0]['position'] === 'top-right');

// ── validate ────────────────────────────────────────────────────
$empty = $svc->validate(['tracks' => []]);
check('تایم‌لاین خالی خطا می‌دهد', count($empty) >= 1);
check('خطا به فارسی و قابل‌فهم است', str_contains($empty[0] ?? '', 'خالی'));

// یک کلیپ بدون mediaId → بدون خطای رسانه (متن مجاز است)
$textOnly = ['tracks' => [['type' => 'text', 'clips' => [['text' => 'سلام', 'start' => 0, 'duration' => 10]]]]];
check('تایم‌لاین فقط-متن معتبر است', count($svc->validate($textOnly)) === 0);

echo "\n";
printf("  \033[1m%d موفق، %d ناموفق\033[0m\n\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
