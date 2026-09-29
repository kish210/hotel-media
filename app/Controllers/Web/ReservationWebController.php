<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};

/**
 * پنل کارکنان — رزرو رستوران و امکانات (TODO ۲.۱۳ و ۲.۱۴)
 *
 * صفحه فقط اسکلت و فهرست محل‌ها را می‌دهد؛ رزروهای روز انتخابی با
 * /api/v1/reservations خوانده می‌شوند تا عوض کردن تاریخ صفحه را
 * دوباره بار نکند — پذیرش در ساعت شلوغ بین امروز و فردا جابه‌جا می‌شود.
 */
class ReservationWebController extends Controller
{
    public function index(Request $req): void
    {
        $venues = $this->db->rows(
            "SELECT id, kind, name, capacity, open_from, open_to, is_active,
                    bookable, slot_minutes, max_party, booking_price, days_ahead
               FROM venues
              WHERE tenant_id = ? AND kind IN ('restaurant','cafe','pool','spa','gym','hall','other')
              ORDER BY bookable DESC, sort_order, name",
            [Auth::tenantId()]
        ) ?: [];

        $this->view('admin.iptv.reservations', [
            'title'  => 'رزرو رستوران و امکانات',
            'venues' => $venues,
            'today'  => date('Y-m-d'),
        ]);
    }
}
