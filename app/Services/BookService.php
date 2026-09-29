<?php
declare(strict_types=1);

namespace App\Services;

/**
 * کتاب و روزنامه‌ی PDF روی تلویزیون (TODO ۱.۱۰).
 *
 * مرورگر تلویزیون هتلی PDF باز نمی‌کند. پس سرور هر صفحه را با pdftoppm
 * (بسته‌ی poppler-utils) به تصویر تبدیل می‌کند و تلویزیون فقط تصویر
 * می‌بیند. صفحه‌ها بار اول ساخته و بعد از کش سرو می‌شوند؛ کلید کش
 * زمان تغییر فایل را دارد تا جایگزین شدن PDF صفحه‌های کهنه نشان ندهد.
 */
final class BookService
{
    /** ۱۱۰ نقطه در اینچ برای A4 یعنی حدود ۹۱۰×۱۲۸۷ — روی تلویزیون ۱۰۸۰ خوانا، ولی سبک */
    private const DPI = 110;
    private const MAX_PAGES = 2000;

    private string $pdftoppm;
    private string $pdfinfo;

    public function __construct()
    {
        $this->pdftoppm = MediaConvertService::locate('pdftoppm');
        $this->pdfinfo  = MediaConvertService::locate('pdfinfo');
    }

    public function available(): bool { return $this->pdftoppm !== '' && $this->pdfinfo !== ''; }

    /** مسیر واقعی PDF برای file_url محلی، یا null */
    public function localPdf(?string $url): ?string
    {
        $url = (string)$url;
        if (!preg_match('#^/uploads/[A-Za-z0-9_./\-]+\.pdf$#i', $url) || str_contains($url, '..')) return null;
        $p = PUBLIC_PATH . $url;
        return is_file($p) ? $p : null;
    }

    public function pages(string $pdf): int
    {
        if ($this->pdfinfo === '') return 0;
        $out = (string)shell_exec(escapeshellarg($this->pdfinfo) . ' ' . escapeshellarg($pdf) . ' 2>/dev/null');
        return preg_match('/^Pages:\s+(\d+)/m', $out, $m) ? min(self::MAX_PAGES, (int)$m[1]) : 0;
    }

    /** مسیر PNG صفحه‌ی n (از ۱)، یا null */
    public function page(int $itemId, string $pdf, int $n): ?string
    {
        if (!$this->available() || $n < 1) return null;
        $total = $this->pages($pdf);
        if ($n > $total) return null;

        $dir = STORAGE_PATH . '/cache/books/' . $itemId . '-' . (int)filemtime($pdf);
        $png = $dir . '/' . $n . '.png';
        if (is_file($png)) return $png;

        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        /* نسخه‌های قدیمی همین کتاب (PDF جایگزین‌شده) پاک می‌شوند */
        foreach (glob(STORAGE_PATH . '/cache/books/' . $itemId . '-*', GLOB_ONLYDIR) ?: [] as $old) {
            if ($old !== $dir) { array_map('unlink', glob($old . '/*') ?: []); @rmdir($old); }
        }

        $base = $dir . '/' . $n;
        shell_exec(
            'timeout 30 ' . escapeshellarg($this->pdftoppm) . ' -f ' . $n . ' -l ' . $n
            . ' -r ' . self::DPI . ' -png -singlefile ' . escapeshellarg($pdf) . ' ' . escapeshellarg($base) . ' 2>/dev/null'
        );
        return is_file($png) ? $png : null;
    }
}
