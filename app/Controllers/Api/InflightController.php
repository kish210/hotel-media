<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};

class InflightController extends Controller
{
    // ── Admin: list flights ───────────────────────────────────────────────────
    public function index(Request $req): void
    {
        $tid   = Auth::tenantId();
        $rows  = $this->db->rows(
            "SELECT * FROM inflight_flights WHERE tenant_id=? ORDER BY updated_at DESC",
            [$tid]
        ) ?: [];
        Response::success($rows);
    }

    // ── Admin: create flight ──────────────────────────────────────────────────
    public function store(Request $req): void
    {
        $tid  = Auth::tenantId();
        $body = $req->json() ?: $req->post();
        $data = $this->sanitize($body);
        $data['tenant_id'] = $tid;

        $id = $this->db->insert('inflight_flights', $data);
        $row = $this->db->row("SELECT * FROM inflight_flights WHERE id=?", [$id]);
        Response::success($row, 'پرواز ایجاد شد', 201);
    }

    // ── Admin: show single ────────────────────────────────────────────────────
    public function show(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $row = $this->db->row(
            "SELECT * FROM inflight_flights WHERE id=? AND tenant_id=?",
            [(int)$params['id'], $tid]
        );
        if (!$row) { Response::error('پرواز یافت نشد', 404); return; }
        Response::success($row);
    }

    // ── Admin: update flight (info + telemetry) ───────────────────────────────
    public function update(Request $req, array $params): void
    {
        $tid  = Auth::tenantId();
        $id   = (int)$params['id'];
        $body = $req->json() ?: $req->post();

        $existing = $this->db->row(
            "SELECT id FROM inflight_flights WHERE id=? AND tenant_id=?",
            [$id, $tid]
        );
        if (!$existing) { Response::error('پرواز یافت نشد', 404); return; }

        $data = $this->sanitize($body);
        unset($data['tenant_id']);

        if (!empty($data)) {
            $this->db->update('inflight_flights', $data, ['id' => $id, 'tenant_id' => $tid]);
        }
        $row = $this->db->row("SELECT * FROM inflight_flights WHERE id=?", [$id]);
        Response::success($row, 'پرواز به‌روز شد');
    }

    // ── Admin: update live telemetry only ─────────────────────────────────────
    public function updateLive(Request $req, array $params): void
    {
        $tid  = Auth::tenantId();
        $id   = (int)$params['id'];
        $body = $req->json() ?: $req->post();

        $allowed = ['phase','progress_pct','altitude_ft','speed_kmh','heading_deg'];
        $data = array_intersect_key($body, array_flip($allowed));

        if (isset($data['phase'])) {
            $phases = ['preflight','taxi','takeoff','climb','cruise','descent','approach','landing','landed'];
            if (!in_array($data['phase'], $phases)) unset($data['phase']);
        }
        foreach (['progress_pct','altitude_ft','speed_kmh','heading_deg'] as $f) {
            if (isset($data[$f])) $data[$f] = max(0, (int)$data[$f]);
        }
        if (isset($data['progress_pct'])) $data['progress_pct'] = min(100, $data['progress_pct']);

        if (!empty($data)) {
            $this->db->update('inflight_flights', $data, ['id' => $id, 'tenant_id' => $tid]);
        }
        Response::success(null, 'تله‌متری به‌روز شد');
    }

    // ── Admin: delete ─────────────────────────────────────────────────────────
    public function destroy(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $this->db->delete('inflight_flights', ['id' => (int)$params['id'], 'tenant_id' => $tid]);
        Response::success(null, 'پرواز حذف شد');
    }

    // ── Player: public endpoint — no auth needed ──────────────────────────────
    public function playerFlight(Request $req, array $params): void
    {
        $id  = (int)$params['id'];
        $row = $this->db->row(
            "SELECT * FROM inflight_flights WHERE id=? AND is_active=1",
            [$id]
        );
        if (!$row) { Response::error('پرواز یافت نشد', 404); return; }

        // Calculate distance and ETA
        $distKm  = null;
        $etaMins = null;
        if ($row['origin_lat'] && $row['origin_lng'] && $row['dest_lat'] && $row['dest_lng']) {
            $distKm = $this->haversine(
                (float)$row['origin_lat'], (float)$row['origin_lng'],
                (float)$row['dest_lat'],   (float)$row['dest_lng']
            );
        }
        if ($distKm && $row['speed_kmh'] > 0 && $row['progress_pct'] < 100) {
            $remaining = $distKm * (1 - $row['progress_pct'] / 100);
            $etaMins   = round($remaining / $row['speed_kmh'] * 60);
        }

        $row['dist_km']  = $distKm ? round($distKm) : null;
        $row['eta_mins'] = $etaMins;
        $row['server_time_utc'] = gmdate('c');

        Response::success($row);
    }

    // ── RPi: save connection settings ─────────────────────────────────────────
    public function rpiSave(Request $req, array $params): void
    {
        $tid  = Auth::tenantId();
        $id   = (int)$params['id'];
        $body = $req->json() ?: $req->post();

        $ip   = trim($body['rpi_ip'] ?? '');
        $port = max(1, min(65535, (int)($body['rpi_port'] ?? 5055)));

        // Validate IP (v4 or v6)
        if ($ip && !filter_var($ip, FILTER_VALIDATE_IP)) {
            Response::error('آدرس IP نامعتبر است', 422);
            return;
        }

        $existing = $this->db->row(
            "SELECT id FROM inflight_flights WHERE id=? AND tenant_id=?",
            [$id, $tid]
        );
        if (!$existing) { Response::error('پرواز یافت نشد', 404); return; }

        $this->db->update('inflight_flights',
            ['rpi_ip' => $ip ?: null, 'rpi_port' => $port],
            ['id' => $id, 'tenant_id' => $tid]
        );
        Response::success(['rpi_ip' => $ip ?: null, 'rpi_port' => $port], 'تنظیمات RPi ذخیره شد');
    }

    // ── RPi: proxy status check (server-side fetch to avoid CORS) ─────────────
    public function rpiStatus(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)$params['id'];
        $row = $this->db->row(
            "SELECT rpi_ip, rpi_port FROM inflight_flights WHERE id=? AND tenant_id=?",
            [$id, $tid]
        );
        if (!$row || !$row['rpi_ip']) {
            Response::error('IP برای RPi تنظیم نشده', 422);
            return;
        }

        $data = $this->rpiGet($row['rpi_ip'], (int)($row['rpi_port'] ?: 5055), '/api/status');
        if ($data === null) {
            Response::error('اتصال به Raspberry Pi ممکن نشد', 503);
            return;
        }
        Response::success($data);
    }

    // ── RPi: sync GPS → update flight telemetry ───────────────────────────────
    public function rpiSync(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)$params['id'];

        $row = $this->db->row(
            "SELECT rpi_ip, rpi_port, origin_lat, origin_lng, dest_lat, dest_lng
             FROM inflight_flights WHERE id=? AND tenant_id=?",
            [$id, $tid]
        );
        if (!$row || !$row['rpi_ip']) {
            Response::error('IP برای RPi تنظیم نشده', 422);
            return;
        }

        $data = $this->rpiGet($row['rpi_ip'], (int)($row['rpi_port'] ?: 5055), '/api/gps');
        if ($data === null) {
            Response::error('اتصال به Raspberry Pi ممکن نشد', 503);
            return;
        }
        if (empty($data['fix'])) {
            Response::error('GPS هنوز فیکس ندارد — منتظر آنتن بمانید', 422);
            return;
        }

        $altFt  = (int)($data['alt_ft']    ?? 0);
        $spdKmh = (int)($data['speed_kmh'] ?? 0);
        $hdg    = (int)($data['heading']    ?? 0);
        $phase  = $this->detectPhaseFromTelemetry($altFt, $spdKmh);

        // Auto-calculate progress_pct from GPS position on great-circle
        $pct = null;
        $lat = isset($data['lat']) ? (float)$data['lat'] : null;
        $lng = isset($data['lng']) ? (float)$data['lng'] : null;

        if ($lat && $lng && $row['origin_lat'] && $row['dest_lat']) {
            $total  = $this->haversine(
                (float)$row['origin_lat'], (float)$row['origin_lng'],
                (float)$row['dest_lat'],   (float)$row['dest_lng']
            );
            $flown  = $this->haversine(
                (float)$row['origin_lat'], (float)$row['origin_lng'],
                $lat, $lng
            );
            if ($total > 0) {
                $pct = min(100, max(0, (int)round($flown / $total * 100)));
            }
        }

        $update = [
            'altitude_ft'  => $altFt,
            'speed_kmh'    => $spdKmh,
            'heading_deg'  => $hdg,
            'phase'        => $phase,
        ];
        if ($pct !== null) $update['progress_pct'] = $pct;

        $this->db->update('inflight_flights', $update, ['id' => $id, 'tenant_id' => $tid]);

        Response::success(array_merge($update, [
            'gps'          => $data,
            'progress_pct' => $pct,
        ]), 'تله‌متری از GPS به‌روز شد');
    }

    // ── RPi: push SignageCMS config → RPi ─────────────────────────────────────
    public function rpiPushConfig(Request $req, array $params): void
    {
        $tid  = Auth::tenantId();
        $id   = (int)$params['id'];
        $body = $req->json() ?: $req->post();

        $row = $this->db->row(
            "SELECT rpi_ip, rpi_port FROM inflight_flights WHERE id=? AND tenant_id=?",
            [$id, $tid]
        );
        if (!$row || !$row['rpi_ip']) {
            Response::error('IP برای RPi تنظیم نشده', 422);
            return;
        }

        $cfg = [
            'signagecms_url'  => rtrim($body['cms_url']       ?? '', '/'),
            'flight_id'       => $id,
            'api_token'       => $body['api_token']        ?? '',
            'push_enabled'    => !empty($body['push_enabled']),
            'push_interval'   => max(5, min(60, (int)($body['push_interval'] ?? 10))),
        ];

        $ip   = $row['rpi_ip'];
        $port = (int)($row['rpi_port'] ?: 5055);
        $payload = json_encode($cfg);
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents("http://{$ip}:{$port}/api/config", false, $ctx);
        if ($raw === false) {
            Response::error('ارسال config به RPi ممکن نشد', 503);
            return;
        }
        $res = json_decode($raw, true);
        Response::success($res, 'تنظیمات به RPi ارسال شد');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private function rpiGet(string $ip, int $port, string $path): ?array
    {
        $ctx = stream_context_create([
            'http' => ['timeout' => 5, 'ignore_errors' => true],
        ]);
        $raw = @file_get_contents("http://{$ip}:{$port}{$path}", false, $ctx);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function detectPhaseFromTelemetry(int $altFt, int $spdKmh): string
    {
        if ($altFt < 50   && $spdKmh < 20)   return 'preflight';
        if ($altFt < 200  && $spdKmh < 120)  return 'taxi';
        if ($altFt < 2000)                    return 'takeoff';
        if ($altFt < 8000)                    return 'climb';
        if ($altFt >= 25000 && $spdKmh > 600) return 'cruise';
        if ($altFt >= 8000)                   return 'descent';
        return 'approach';
    }

    private function sanitize(array $body): array
    {
        $allowed = [
            'flight_number','airline_name','airline_logo',
            'origin_iata','origin_city','origin_country','origin_lat','origin_lng','origin_timezone',
            'dest_iata','dest_city','dest_country','dest_lat','dest_lng','dest_timezone',
            'departure_at','arrival_at',
            'phase','progress_pct','altitude_ft','speed_kmh','heading_deg',
            'accent_color','bg_style','welcome_msg','is_active',
        ];
        $data = array_intersect_key($body, array_flip($allowed));

        // Type coercions
        foreach (['origin_lat','origin_lng','dest_lat','dest_lng'] as $f) {
            if (isset($data[$f]) && $data[$f] !== '') $data[$f] = (float)$data[$f];
            elseif (isset($data[$f])) $data[$f] = null;
        }
        foreach (['progress_pct','altitude_ft','speed_kmh','heading_deg','is_active'] as $f) {
            if (isset($data[$f])) $data[$f] = (int)$data[$f];
        }
        if (isset($data['progress_pct'])) $data['progress_pct'] = min(100, max(0, $data['progress_pct']));

        // Validate phase
        if (isset($data['phase'])) {
            $phases = ['preflight','taxi','takeoff','climb','cruise','descent','approach','landing','landed'];
            if (!in_array($data['phase'], $phases)) $data['phase'] = 'preflight';
        }
        // Validate bg_style
        if (isset($data['bg_style'])) {
            if (!in_array($data['bg_style'], ['space','clouds','ocean','dusk'])) $data['bg_style'] = 'space';
        }
        // Accent color
        if (isset($data['accent_color']) && !preg_match('/^#[0-9a-f]{6}$/i', $data['accent_color'])) {
            $data['accent_color'] = '#00b4d8';
        }
        // Empty strings → null for optional fields
        foreach (['departure_at','arrival_at','airline_logo','welcome_msg'] as $f) {
            if (isset($data[$f]) && trim((string)$data[$f]) === '') $data[$f] = null;
        }
        return $data;
    }

    /** Haversine distance in km */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
        return $R * 2 * asin(sqrt($a));
    }
}
