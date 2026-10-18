<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * تیزر شروع: چند عکس/ویدیو که تلویزیون هنگام روشن‌شدن یک‌بار پخش می‌کند و
 * بعد سراغ محتوای عادی می‌رود.
 *
 * چرا جدول جدا و نه پلی‌لیست: پلی‌لیست حلقه‌ای و زمان‌بندی‌شده است و روی
 * تلویزیون اتاق اصلا پخش نمی‌شود (پروفایل اتاق پلی‌لیست ندارد). تیزر باید
 * برای هر دو بخش کار کند و فقط یک‌بار در شروع.
 *
 * «یک‌بار» سمت پلیر نگه داشته می‌شود (sessionStorage): سرور نمی‌داند
 * تلویزیون خاموش و روشن شده یا فقط صفحه refresh شده، و اگر سرور بشمارد،
 * یک refresh ساده تیزر را برای مهمان بعدی می‌سوزاند.
 */
final class TvIntroService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * تیزرهای یک صفحه، به ترتیب. آیتم با رسانهٔ آماده‌نشده (در حال تبدیل)
     * حذف می‌شود — همان قاعدهٔ پلی‌لیست، وگرنه تلویزیون روی فایل ناقص می‌ماند.
     *
     * @return array<int,array{id:int,name:string,url:string,type:string,duration:int}>
     */
    public function forScreen(int $tenantId, array $screen): array
    {
        $isIptv  = ($screen['screen_type'] ?? 'signage') === 'iptv';
        $screenId = (int)($screen['id'] ?? 0);
        $groupId  = (int)($screen['group_id'] ?? 0);

        try {
            $rows = $this->db->rows(
                "SELECT i.*, m.file_path, m.type AS media_type, m.status AS media_status
                   FROM tv_intro_items i
              LEFT JOIN media m ON m.id = i.media_id AND m.deleted_at IS NULL
                  WHERE i.tenant_id = ? AND i.is_active = 1
                    AND i.scope IN ('both', ?)
                  ORDER BY i.sort_order, i.id",
                [$tenantId, $isIptv ? 'iptv' : 'signage']
            ) ?: [];
        } catch (\Throwable) {
            return [];   // جدول نیست (مهاجرت اجرا نشده): تیزر نداریم، پلیر عادی کار کند
        }

        $out = [];
        foreach ($rows as $r) {
            if (!$this->targets($r, $screenId, $groupId)) continue;

            $url  = trim((string)($r['url'] ?? ''));
            $type = 'image';
            if ($url === '' && !empty($r['media_id'])) {
                $st = $r['media_status'] ?? null;
                if ($st !== null && $st !== 'ready') continue;      // هنوز تبدیل می‌شود
                $url  = (string)($r['file_path'] ?? '');
                $type = (string)($r['media_type'] ?? 'image');
            } elseif ($url !== '') {
                $type = preg_match('/\.(mp4|webm|mkv|mov|m3u8|ts)(\?|$)/i', $url) ? 'video' : 'image';
            }
            if ($url === '') continue;

            $out[] = [
                'id'       => (int)$r['id'],
                'name'     => (string)$r['name'],
                'url'      => $url,
                'type'     => $type === 'video' ? 'video' : 'image',
                'duration' => max(1, (int)$r['duration']),
            ];
        }
        return $out;
    }

    /** همان قرارداد هدف‌گیری screen_messages: all | screen | group */
    private function targets(array $row, int $screenId, int $groupId): bool
    {
        $t = (string)($row['target'] ?? 'all');
        if ($t === 'all') return true;
        $ids = json_decode((string)($row['target_ids'] ?? '[]'), true) ?: [];
        $ids = array_map('intval', (array)$ids);
        if ($t === 'screen') return in_array($screenId, $ids, true);
        if ($t === 'group')  return $groupId > 0 && in_array($groupId, $ids, true);
        return false;
    }
}
