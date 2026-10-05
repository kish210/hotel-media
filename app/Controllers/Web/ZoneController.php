<?php

namespace App\Controllers\Web;

use App\Core\{Controller, Auth};

/**
 * محل‌های هتل به‌عنوان Zone — لابی، رستوران، آسانسور، راهرو…
 *
 * هیچ جدول تازه‌ای اینجا نیست: `venues` از قبل همین مفهوم را داشت
 * (`kind`, `floor`, ظرفیت، ساعت کار) و `Api\VenueController` هم CRUD
 * کاملش را با مسیرهای فعال داشت. چیزی که نبود، رویهٔ کاربری بود — پس
 * فقط همان backend به UI وصل می‌شود، نه نسخهٔ دومی از آن.
 */
class ZoneController extends Controller
{
    /**
     * برچسب فارسی انواع محل.
     *
     * کلیدها عینا همان ENUM ستون `venues.kind` است و با
     * ‏`Api\VenueController::KINDS` اعتبارسنجی می‌شود — پس اگر روزی
     * نوعی به اسکیما اضافه شد و اینجا نیامد، کارت محل همان کلید
     * انگلیسی را نشان می‌دهد و خطا نمی‌دهد.
     */
    public const KIND_LABELS = [
        'lobby'      => 'لابی',
        'restaurant' => 'رستوران',
        'cafe'       => 'کافه',
        'hall'       => 'سالن',
        'pool'       => 'استخر',
        'spa'        => 'اسپا',
        'gym'        => 'باشگاه',
        'elevator'   => 'آسانسور',
        'corridor'   => 'راهرو',
        'reception'  => 'پذیرش',
        'shop'       => 'فروشگاه',
        'other'      => 'سایر',
    ];

    public function index(): void
    {
        $tid = Auth::tenantId();

        /* شمارش صفحه‌ها و وضعیت زنده‌بودنشان در همین کوئری: اپراتور
           پیش از انتشار پلی‌لیست روی یک محل باید بداند چند صفحه آنجاست
           و چندتایشان واقعا روشن است. */
        $zones = $this->db->rows(
            "SELECT v.*,
                    (SELECT COUNT(*) FROM screens s WHERE s.venue_id = v.id) AS screen_count,
                    (SELECT COUNT(*) FROM screens s
                      WHERE s.venue_id = v.id AND s.last_seen_at > DATE_SUB(NOW(), INTERVAL 3 MINUTE)) AS online_count,
                    (SELECT COUNT(*) FROM schedules sc
                      WHERE sc.venue_id = v.id AND sc.is_active = 1) AS schedule_count
               FROM venues v
              WHERE v.tenant_id = ?
              ORDER BY v.sort_order, v.name",
            [$tid]
        ) ?: [];

        $unassigned = $this->db->rows(
            "SELECT id, name, code, screen_type FROM screens
              WHERE tenant_id = ? AND (venue_id IS NULL OR venue_id = 0)
              ORDER BY name",
            [$tid]
        ) ?: [];

        $playlists = $this->db->rows(
            "SELECT id, name FROM playlists WHERE tenant_id = ? AND is_active = 1 ORDER BY name",
            [$tid]
        ) ?: [];

        $this->view('admin.zones.index', [
            'title'      => 'محل‌ها و Zone',
            'zones'      => $zones,
            'unassigned' => $unassigned,
            'playlists'  => $playlists,
            'kinds'      => self::KIND_LABELS,
        ]);
    }
}
