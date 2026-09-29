<?php
/**
 * تست کتاب PDF روی تلویزیون (TODO ۱.۱۰): شمارش صفحه، تبدیل صفحه به
 * تصویر، کش، و رد مسیرهای بیرون از uploads.
 * بدون poppler-utils (pdftoppm) بخش تبدیل رد می‌شود.
 */
define('ROOT_PATH',    dirname(__DIR__, 2));
define('APP_PATH',     ROOT_PATH . '/app');
define('PUBLIC_PATH',  ROOT_PATH . '/public');
define('STORAGE_PATH', sys_get_temp_dir() . '/hm-book-test-' . getmypid());

spl_autoload_register(function (string $c): void {
    $p = APP_PATH . '/' . str_replace(['App\\', '\\'], ['', '/'], $c) . '.php';
    if (file_exists($p)) require $p;
});

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✅ $label\n"; }
    else     { $fail++; echo "  ❌ $label" . ($detail ? "\n       → $detail" : '') . "\n"; }
}

/* PDF سه‌صفحه‌ای معتبر با xref درست */
function makePdf(int $pages): string {
    $objs = ['<< /Type /Catalog /Pages 2 0 R >>'];
    $kids = [];
    for ($i = 0; $i < $pages; $i++) $kids[] = (3 + $i * 2) . ' 0 R';
    $objs[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . "] /Count $pages >>";
    $font = 3 + $pages * 2;
    for ($i = 0; $i < $pages; $i++) {
        $objs[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 $font 0 R >> >> /Contents " . (4 + $i * 2) . ' 0 R >>';
        $st = 'BT /F1 48 Tf 150 420 Td (Page ' . ($i + 1) . ') Tj ET';
        $objs[] = '<< /Length ' . strlen($st) . " >>\nstream\n$st\nendstream";
    }
    $objs[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $out = "%PDF-1.4\n"; $offs = [];
    foreach ($objs as $n => $o) { $offs[] = strlen($out); $out .= ($n + 1) . " 0 obj\n$o\nendobj\n"; }
    $x = strlen($out);
    $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($offs as $o) $out .= sprintf("%010d 00000 n \n", $o);
    return $out . 'trailer << /Size ' . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$x\n%%EOF\n";
}

$svc = new App\Services\BookService();
$rel = '/uploads/booktest-' . getmypid() . '.pdf';
file_put_contents(PUBLIC_PATH . $rel, makePdf(3));

echo "\n── مسیر ──\n";
check('PDF داخل uploads پذیرفته شد', $svc->localPdf($rel) !== null);
check('مسیر با .. رد شد', $svc->localPdf('/uploads/../.env.pdf') === null);
check('بیرون از uploads رد شد', $svc->localPdf('/etc/passwd.pdf') === null);
check('پسوند غیر PDF رد شد', $svc->localPdf('/uploads/x.php') === null);
check('آدرس اینترنتی رد شد (فقط فایل محلی)', $svc->localPdf('https://x/y.pdf') === null);

if (!$svc->available()) {
    echo "\n  ⏭ poppler-utils نصب نیست — بخش تبدیل رد شد\n";
} else {
    echo "\n── تبدیل ──\n";
    $pdf = PUBLIC_PATH . $rel;
    check('سه صفحه شمرده شد', $svc->pages($pdf) === 3);
    $png = $svc->page(999001, $pdf, 2);
    $info = $png ? @getimagesize($png) : false;
    check('صفحه‌ی ۲ به PNG تبدیل شد', $info !== false && $info[2] === IMAGETYPE_PNG, (string)$png);
    check('اندازه‌ی صفحه برای تلویزیون مناسب است', $info && $info[1] > 1000 && $info[1] < 1600, json_encode($info));
    $t = filemtime($png);
    sleep(1);
    check('بار دوم از کش آمد', $svc->page(999001, $pdf, 2) === $png && filemtime($png) === $t);
    check('صفحه‌ی بیش از تعداد null است', $svc->page(999001, $pdf, 4) === null);
    check('صفحه‌ی صفر null است', $svc->page(999001, $pdf, 0) === null);
}

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";
@unlink(PUBLIC_PATH . $rel);
shell_exec('rm -rf ' . escapeshellarg(STORAGE_PATH));
exit($fail === 0 ? 0 : 1);
