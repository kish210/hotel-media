<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};

class HotelWebController extends Controller
{
    public function index(Request $req): void
    {
        $tid       = Auth::tenantId();
        $events    = $this->db->rows("SELECT * FROM hotel_events WHERE tenant_id=? ORDER BY start_at ASC LIMIT 50", [$tid]);
        $amenities = $this->db->rows("SELECT * FROM hotel_amenities WHERE tenant_id=? ORDER BY sort_order ASC", [$tid]);
        $hotelInfo = $this->db->row("SELECT * FROM hotel_info WHERE tenant_id=? LIMIT 1", [$tid]);
        $this->view('admin.modules.manage_hotel', compact('events', 'amenities', 'hotelInfo') + ['title' => 'مدیریت هتل']);
    }

    public function storeEvent(Request $req): void
    {
        $data = $req->post();
        $this->db->insert('hotel_events', [
            'tenant_id'   => Auth::tenantId(),
            'title'       => $data['title'] ?? '',
            'title_en'    => $data['title_en'] ?? null,
            'description' => $data['description'] ?? null,
            'hall_name'   => $data['hall_name'] ?? null,
            'start_at'    => $data['start_at'] ?? date('Y-m-d H:i:s'),
            'end_at'      => $data['end_at'] ?: null,
            'type'        => $data['type'] ?? 'conference',
            'organizer'   => $data['organizer'] ?? null,
            'color'       => $data['color'] ?? '#d4af37',
            'is_active'   => 1,
        ]);
        $this->flash('success', 'رویداد ثبت شد');
        $this->redirect('/admin/modules/hotel/manage');
    }

    public function deleteEvent(Request $req, array $params): void
    {
        $this->db->delete('hotel_events', ['id' => (int)$params['id'], 'tenant_id' => Auth::tenantId()]);
        $this->flash('success', 'رویداد حذف شد');
        $this->redirect('/admin/modules/hotel/manage');
    }

    public function storeAmenity(Request $req): void
    {
        $data = $req->post();
        $this->db->insert('hotel_amenities', [
            'tenant_id'  => Auth::tenantId(),
            'name'       => $data['name'] ?? '',
            'name_en'    => $data['name_en'] ?? null,
            'icon'       => $data['icon'] ?? 'fas fa-star',
            'floor'      => $data['floor'] ?? null,
            'hours'      => $data['hours'] ?? null,
            'phone'      => $data['phone'] ?? null,
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'is_active'  => 1,
        ]);
        $this->flash('success', 'امکان اضافه شد');
        $this->redirect('/admin/modules/hotel/manage');
    }

    public function deleteAmenity(Request $req, array $params): void
    {
        $this->db->delete('hotel_amenities', ['id' => (int)$params['id'], 'tenant_id' => Auth::tenantId()]);
        $this->flash('success', 'امکان حذف شد');
        $this->redirect('/admin/modules/hotel/manage');
    }

    public function saveInfo(Request $req): void
    {
        $tid  = Auth::tenantId();
        $data = $req->post();
        $existing = $this->db->row("SELECT id FROM hotel_info WHERE tenant_id=?", [$tid]);

        $record = [
            'hotel_name'    => $data['hotel_name'] ?? '',
            'hotel_name_en' => $data['hotel_name_en'] ?? null,
            'checkin_time'  => $data['checkin_time'] ?? '14:00:00',
            'checkout_time' => $data['checkout_time'] ?? '12:00:00',
            'wifi_name'     => $data['wifi_name'] ?? null,
            'wifi_pass'     => $data['wifi_pass'] ?? null,
        ];

        if ($existing) {
            $this->db->update('hotel_info', $record, ['id' => $existing['id']]);
        } else {
            $this->db->insert('hotel_info', array_merge($record, ['tenant_id' => $tid]));
        }

        $this->flash('success', 'اطلاعات هتل ذخیره شد');
        $this->redirect('/admin/modules/hotel/manage');
    }
}
