<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Teletext Service — متن ۴۰×۲۳ روی تلویزیون اتاق.
 *
 * چرا متن ساده و نه HTML: تلویزیون‌های Orsay 2013 و Tizen 2.x فونت و
 * چیدمان را جور دیگری رندر می‌کنند و «صفحهٔ تله‌تکست» به آخرین چیزی
 * شبیه می‌شد که طراح دیده بود. سیستم قدیمی هم همین محدودیت را داشت:
 * ۴۰ کاراکتر در خط، ۲۳ خط در صفحه (`validate_teletex_in_file`).
 *
 * تله‌تکست هنوز در هتل معنا دارد: صفحهٔ اطلاعات هتل، ساعات رستوران،
 * راهنمای کانال — بدون نیاز به هیچ ویدیویی، روی هر تلویزیونی.
 */
class TeletextService
{
    public const COLS = 40;
    public const ROWS = 23;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** @return list<array<string,mixed>> */
    public function pages(int $tenantId = 1, ?string $lang = null): array
    {
        $sql    = 'SELECT * FROM teletext_pages WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($lang !== null && $lang !== '') { $sql .= ' AND lang = ?'; $params[] = $lang; }
        $sql .= ' ORDER BY sort_order, page_no';
        return $this->db->rows($sql, $params);
    }

    /** فهرست صفحه‌ها برای تلویزیون — بدون متن، سبک برای شبکهٔ اتاق */
    public function index(int $tenantId = 1, string $lang = 'fa'): array
    {
        $rows = $this->db->rows(
            'SELECT page_no, lang, title FROM teletext_pages
              WHERE tenant_id = ? AND is_active = 1 AND lang = ?
              ORDER BY page_no',
            [$tenantId, $lang]
        );
        if (!$rows && $lang !== 'fa') {
            $rows = $this->db->rows(
                'SELECT page_no, lang, title FROM teletext_pages
                  WHERE tenant_id = ? AND is_active = 1 ORDER BY page_no',
                [$tenantId]
            );
        }
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function page(int $pageNo, int $tenantId = 1, string $lang = 'fa'): ?array
    {
        $row = $this->db->row(
            'SELECT * FROM teletext_pages WHERE tenant_id = ? AND page_no = ? AND lang = ? AND is_active = 1',
            [$tenantId, $pageNo, $lang]
        );
        if (!$row && $lang !== 'fa') {
            $row = $this->db->row(
                'SELECT * FROM teletext_pages WHERE tenant_id = ? AND page_no = ? AND is_active = 1 LIMIT 1',
                [$tenantId, $pageNo]
            );
        }
        return $row;
    }

    /**
     * ذخیرهٔ صفحه. متن به قالب ۴۰×۲۳ بریده می‌شود، نه اینکه رد شود:
     * اپراتور در پنل تایپ می‌کند و «ذخیره نشد» بدون گفتن کدام خط،
     * آزاردهنده است. بریدن قابل دیدن است، خطا نه.
     *
     * @param array<string,mixed> $in
     */
    public function save(array $in, int $tenantId = 1): array
    {
        $pageNo = (int)($in['page_no'] ?? 100);
        if ($pageNo < 100 || $pageNo > 899) {
            return ['ok' => false, 'message' => 'شمارهٔ صفحه باید بین ۱۰۰ تا ۸۹۹ باشد'];
        }

        $lang = in_array($in['lang'] ?? 'fa', ['fa', 'en', 'ar'], true) ? (string)$in['lang'] : 'fa';
        $body = $this->normalize((string)($in['body'] ?? ''));

        $data = [
            'tenant_id'  => $tenantId,
            'page_no'    => $pageNo,
            'lang'       => $lang,
            'title'      => mb_substr(trim((string)($in['title'] ?? '')), 0, 120),
            'body'       => $body,
            'color'      => $this->hex($in['color'] ?? null),
            'sort_order' => (int)($in['sort_order'] ?? 0),
            'is_active'  => !empty($in['is_active']) ? 1 : 0,
        ];

        $existing = $this->db->row(
            'SELECT id FROM teletext_pages WHERE tenant_id = ? AND page_no = ? AND lang = ?',
            [$tenantId, $pageNo, $lang]
        );

        if ($existing) {
            $this->db->update('teletext_pages', $data, ['id' => (int)$existing['id']]);
            return ['ok' => true, 'message' => 'صفحه به‌روز شد', 'id' => (int)$existing['id']];
        }

        $id = $this->db->insert('teletext_pages', $data);
        return ['ok' => true, 'message' => 'صفحه ساخته شد', 'id' => (int)$id];
    }

    public function delete(int $id, int $tenantId = 1): bool
    {
        return $this->db->delete('teletext_pages', ['id' => $id, 'tenant_id' => $tenantId]) > 0;
    }

    /**
     * قالب‌بندی: هر خط حداکثر ۴۰ کاراکتر، حداکثر ۲۳ خط.
     * تب به فاصله تبدیل می‌شود چون تلویزیون تب را گاهی چهار فاصله
     * نمی‌گیرد و ستون‌ها جابه‌جا می‌شوند.
     */
    public function normalize(string $text): string
    {
        $text  = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", '    '], $text);
        $lines = explode("\n", $text);

        /* صفحه‌شکن با فرم‌فید هم پذیرفته می‌شود — همان چیزی که فایل‌های
           متنی قدیمی برای جدا کردن صفحه داشتند */
        $out = [];
        foreach ($lines as $line) {
            foreach (explode("\f", $line) as $seg) {
                $out[] = $seg;
            }
        }

        $out = array_slice($out, 0, self::ROWS);
        return implode("\n", array_map(
            static fn($l) => mb_substr(rtrim((string)$l), 0, self::COLS),
            $out
        ));
    }

    /**
     * ورود صفحات از فایل متنی سیستم قدیمی.
     *
     * فایل‌ها `teletext<page>.conf` بودند و متن خام ۴۰ ستونی داشتند.
     * هر فایل می‌تواند چند صفحه باشد (با فرم‌فید).
     *
     * @return array{pages:int,files:int}
     */
    public function importDir(string $dir, int $tenantId = 1, string $lang = 'fa'): array
    {
        $files = glob(rtrim($dir, '/\\') . '/teletext*.conf') ?: [];
        $count = 0;

        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false || trim($raw) === '') continue;

            /* شمارهٔ صفحه از نام فایل: teletext101.conf */
            $pageNo = 100;
            if (preg_match('/(\d{2,3})/', basename($file), $m)) {
                $pageNo = max(100, min(899, (int)$m[1]));
            }

            foreach (explode("\f", $raw) as $i => $chunk) {
                if (trim($chunk) === '') continue;
                $this->save([
                    'page_no'    => $pageNo + $i,
                    'lang'       => $lang,
                    'title'      => 'صفحه ' . ($pageNo + $i),
                    'body'       => $chunk,
                    'is_active'  => 1,
                    'sort_order' => $pageNo + $i,
                ], $tenantId);
                $count++;
            }
        }

        return ['pages' => $count, 'files' => count($files)];
    }

    /** صفحه‌های فعال به شکل آمادهٔ نمایش: خط‌های آرایه‌ای */
    public function render(int $pageNo, int $tenantId = 1, string $lang = 'fa'): ?array
    {
        $row = $this->page($pageNo, $tenantId, $lang);
        if (!$row) return null;

        return [
            'page_no' => (int)$row['page_no'],
            'lang'    => (string)$row['lang'],
            'title'   => (string)($row['title'] ?? ''),
            'color'   => (string)($row['color'] ?? ''),
            'lines'   => explode("\n", (string)$row['body']),
        ];
    }

    private function hex(mixed $v): ?string
    {
        $s = trim((string)$v);
        return preg_match('/^#[0-9a-f]{6}$/i', $s) ? $s : null;
    }
}
