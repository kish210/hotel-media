<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Response, Request, Auth};

class HotelController extends Controller
{
    private function tid(): int { return Auth::check() ? Auth::tenantId() : 1; }

    public function info(Request $req): void
    {
        $info = $this->db->row("SELECT * FROM hotel_info WHERE tenant_id=? LIMIT 1", [$this->tid()]);
        Response::success($info);
    }

    public function saveInfo(Request $req): void
    {
        $tid  = $this->tid();
        $data = array_merge($req->input(), ['tenant_id' => $tid]);
        unset($data['_token'], $data['id']);
        $exists = $this->db->value("SELECT id FROM hotel_info WHERE tenant_id=?", [$tid]);
        if ($exists) $this->db->update('hotel_info', $data, ['tenant_id' => $tid]);
        else         $this->db->insert('hotel_info', $data);
        Response::success(null, 'اطلاعات هتل ذخیره شد');
    }

    public function events(Request $req): void
    {
        $rows = $this->db->rows(
            "SELECT * FROM hotel_events WHERE tenant_id=? AND is_active=1
             AND (end_at IS NULL OR end_at >= NOW())
             AND start_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
             ORDER BY start_at ASC LIMIT 20",
            [$this->tid()]
        );
        Response::success($rows);
    }

    public function storeEvent(Request $req): void
    {
        $errors = $req->validate(['title' => 'required', 'start_at' => 'required']);
        if ($errors) Response::error('داده‌ها نامعتبر', 422, $errors);
        $data = array_merge($req->input(), ['tenant_id' => $this->tid()]);
        unset($data['_token']);
        $id = $this->db->insert('hotel_events', $data);
        Response::success($this->db->row("SELECT * FROM hotel_events WHERE id=?", [$id]), 'رویداد ثبت شد', 201);
    }

    public function updateEvent(Request $req, array $params): void
    {
        $data = $req->input(); unset($data['_token']);
        $this->db->update('hotel_events', $data, ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'رویداد به‌روز شد');
    }

    public function deleteEvent(Request $req, array $params): void
    {
        $this->db->update('hotel_events', ['is_active' => 0], ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'رویداد حذف شد');
    }

    public function amenities(Request $req): void
    {
        Response::success($this->db->rows(
            "SELECT * FROM hotel_amenities WHERE tenant_id=? AND is_active=1 ORDER BY sort_order,id",
            [$this->tid()]
        ));
    }

    public function storeAmenity(Request $req): void
    {
        $errors = $req->validate(['name' => 'required']);
        if ($errors) Response::error('داده‌ها نامعتبر', 422, $errors);
        $data = array_merge($req->input(), ['tenant_id' => $this->tid()]); unset($data['_token']);
        $id = $this->db->insert('hotel_amenities', $data);
        Response::success($this->db->row("SELECT * FROM hotel_amenities WHERE id=?", [$id]), 'امکانات ثبت شد', 201);
    }

    public function updateAmenity(Request $req, array $params): void
    {
        $data = $req->input(); unset($data['_token']);
        $this->db->update('hotel_amenities', $data, ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'به‌روز شد');
    }

    public function deleteAmenity(Request $req, array $params): void
    {
        $this->db->update('hotel_amenities', ['is_active' => 0], ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'حذف شد');
    }

    public function roomService(Request $req): void
    {
        $cat  = $req->get('category', '');
        $sql  = "SELECT * FROM hotel_room_service WHERE tenant_id=? AND is_active=1 AND is_available=1";
        $p    = [$this->tid()];
        if ($cat) { $sql .= " AND category=?"; $p[] = $cat; }
        $sql .= " ORDER BY sort_order,id";
        Response::success($this->db->rows($sql, $p));
    }

    public function storeRoomService(Request $req): void
    {
        $errors = $req->validate(['name' => 'required', 'price' => 'required|numeric', 'category' => 'required']);
        if ($errors) Response::error('داده‌ها نامعتبر', 422, $errors);
        $data = array_merge($req->input(), ['tenant_id' => $this->tid()]); unset($data['_token']);
        $id = $this->db->insert('hotel_room_service', $data);
        Response::success($this->db->row("SELECT * FROM hotel_room_service WHERE id=?", [$id]), 'آیتم ثبت شد', 201);
    }

    public function attractions(Request $req): void
    {
        Response::success($this->db->rows(
            "SELECT * FROM hotel_attractions WHERE tenant_id=? AND is_active=1 ORDER BY sort_order,id",
            [$this->tid()]
        ));
    }

    public function storeAttraction(Request $req): void
    {
        $errors = $req->validate(['name' => 'required']);
        if ($errors) Response::error('داده‌ها نامعتبر', 422, $errors);
        $data = array_merge($req->input(), ['tenant_id' => $this->tid()]); unset($data['_token']);
        $id = $this->db->insert('hotel_attractions', $data);
        Response::success($this->db->row("SELECT * FROM hotel_attractions WHERE id=?", [$id]), 'جاذبه ثبت شد', 201);
    }

    public function weather(Request $req): void
    {
        $key  = env('WEATHER_API_KEY', '');
        $city = env('WEATHER_DEFAULT_CITY', 'Tehran');
        if (!$key) { Response::success(['city' => $city, 'temp' => 25, 'description' => 'آفتابی']); return; }
        try {
            $url  = "https://api.openweathermap.org/data/2.5/weather?q={$city}&appid={$key}&units=metric&lang=fa";
            $data = json_decode(file_get_contents($url), true);
            Response::success([
                'city'        => $data['name'] ?? $city,
                'temp'        => $data['main']['temp'] ?? 0,
                'feels_like'  => $data['main']['feels_like'] ?? 0,
                'humidity'    => $data['main']['humidity'] ?? 0,
                'description' => $data['weather'][0]['description'] ?? '',
                'icon'        => $data['weather'][0]['icon'] ?? '',
            ]);
        } catch (\Throwable) {
            Response::success(['city' => $city, 'temp' => 25, 'description' => 'نامشخص']);
        }
    }
}
