<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Response, Request, Auth};
use App\Services\AirportIrFetcher;

class FIDSController extends Controller
{
    private function tid(): int { return Auth::check() ? Auth::tenantId() : 1; }

    // ── Live data from fids.airport.ir ───────────────────────────────────────

    /** GET /api/v1/fids/live?airport_id=2&type=arrival&route=domestic&limit=20 */
    public function live(Request $req): void
    {
        $airportId = (int)$req->get('airport_id', 2);
        $type      = $req->get('type',      'all');   // arrival | departure | all
        $route     = $req->get('route',     'all');   // domestic | international | all
        $limit     = min(50, max(1, (int)$req->get('limit', 20)));

        // Validate
        if (!array_key_exists($airportId, AirportIrFetcher::AIRPORTS)) {
            Response::error('فرودگاه نامعتبر است', 400);
            return;
        }
        if (!in_array($type,  ['arrival','departure','all'], true)) $type  = 'all';
        if (!in_array($route, ['domestic','international','all'], true)) $route = 'all';

        try {
            $flights = AirportIrFetcher::fetch($airportId, $type, $route, $limit);
            $airport = AirportIrFetcher::AIRPORTS[$airportId];

            Response::json([
                'success'    => true,
                'airport_id' => $airportId,
                'airport'    => $airport['name'],
                'type'       => $type,
                'route'      => $route,
                'count'      => count($flights),
                'cached_at'  => date('H:i:s'),
                'data'       => $flights,
            ]);
        } catch (\Throwable $e) {
            error_log('[FIDS Live] ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'خطا در دریافت اطلاعات پرواز', 'data' => []]);
        }
    }

    /** GET /api/v1/fids/airports — list all available airports */
    public function airportList(Request $req): void
    {
        $list = [];
        foreach (AirportIrFetcher::AIRPORTS as $id => $info) {
            $list[] = ['id' => $id, 'name' => $info['name']];
        }
        Response::json(['success' => true, 'data' => $list]);
    }

    /** POST /api/v1/fids/live/bust?airport_id=2 — clear cache */
    public function bustCache(Request $req): void
    {
        $airportId = (int)$req->get('airport_id', 0);
        AirportIrFetcher::bust($airportId);
        Response::json(['success' => true, 'message' => 'کش پاک شد']);
    }

    /** GET /api/v1/fids/flights */
    public function flights(Request $req): void
    {
        $type   = $req->get('type', 'departure');
        $limit  = min(50, (int)$req->get('limit', 15));
        $gate   = $req->get('gate', '');
        $tid    = $this->tid();

        $sql    = "SELECT f.*, a.logo_url AS airline_logo FROM fids_flights f
                   LEFT JOIN fids_airlines a ON a.code = f.airline_code
                   WHERE f.tenant_id = ? AND f.is_active = 1";
        $params = [$tid];

        if ($type !== 'all') { $sql .= " AND f.type = ?"; $params[] = $type; }
        if ($gate)           { $sql .= " AND f.gate = ?"; $params[] = $gate; }

        // Show today's + next 4h flights
        $sql .= " AND f.scheduled_time BETWEEN DATE_SUB(NOW(), INTERVAL 30 MINUTE) AND DATE_ADD(NOW(), INTERVAL 8 HOUR)";
        $sql .= " ORDER BY f.scheduled_time ASC LIMIT $limit";

        Response::success($this->db->rows($sql, $params));
    }

    /** POST /api/v1/fids/flights — create flight */
    public function storeFlight(Request $req): void
    {
        $errors = $req->validate([
            'flight_number'  => 'required|max:20',
            'airline_code'   => 'required',
            'airline_name'   => 'required',
            'scheduled_time' => 'required',
            'type'           => 'required|in:departure,arrival',
        ]);
        if ($errors) Response::error('داده‌ها نامعتبر', 422, $errors);

        $data = array_merge($req->input(), [
            'tenant_id'  => $this->tid(),
            'status_fa'  => $this->statusFa($req->input('status', 'scheduled')),
        ]);
        unset($data['_token']);

        $id = $this->db->insert('fids_flights', $data);
        Response::success(
            $this->db->row("SELECT * FROM fids_flights WHERE id=?", [$id]),
            'پرواز ثبت شد', 201
        );
    }

    /** PUT /api/v1/fids/flights/{id} */
    public function updateFlight(Request $req, array $params): void
    {
        $data = $req->input();
        unset($data['_token']);
        if (isset($data['status'])) $data['status_fa'] = $this->statusFa($data['status']);
        $this->db->update('fids_flights', $data, ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'پرواز به‌روز شد');
    }

    /** DELETE /api/v1/fids/flights/{id} */
    public function deleteFlight(Request $req, array $params): void
    {
        $this->db->update('fids_flights', ['is_active' => 0], ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'پرواز حذف شد');
    }

    /** GET /api/v1/fids/airlines */
    public function airlines(Request $req): void
    {
        Response::success($this->db->rows("SELECT * FROM fids_airlines ORDER BY name_fa"));
    }

    /** POST /api/v1/fids/flights/{id}/status — quick status update */
    public function updateStatus(Request $req, array $params): void
    {
        $status = $req->input('status');
        $validStatuses = ['scheduled','boarding','departed','arrived','delayed','cancelled','diverted','gate_change'];
        if (!in_array($status, $validStatuses)) Response::error('وضعیت نامعتبر', 400);

        $update = [
            'status'    => $status,
            'status_fa' => $this->statusFa($status),
        ];
        if ($req->input('gate'))          $update['gate']          = $req->input('gate');
        if ($req->input('delay_minutes')) $update['delay_minutes'] = (int)$req->input('delay_minutes');
        if ($req->input('estimated_time'))$update['estimated_time']= $req->input('estimated_time');

        $this->db->update('fids_flights', $update, ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'وضعیت پرواز به‌روز شد');
    }

    /** GET /api/v1/fids/stats */
    public function stats(Request $req): void
    {
        $tid   = $this->tid();
        $today = date('Y-m-d');
        $stats = $this->db->row(
            "SELECT COUNT(*) AS total,
             SUM(type='departure') AS departures,
             SUM(type='arrival')   AS arrivals,
             SUM(status='delayed') AS delayed_count,
             SUM(status='cancelled') AS cancelled_count,
             SUM(status='boarding')  AS boarding,
             SUM(status='departed' OR status='arrived') AS completed_count
             FROM fids_flights WHERE tenant_id=? AND DATE(scheduled_time)=? AND is_active=1",
            [$tid, $today]
        );
        Response::success($stats);
    }

    // ── Sync: fetch from airport.ir and save to DB ──────────────────────────

    /**
     * GET /api/v1/fids/ping — test connectivity to fids.airport.ir (no auth required)
     */
    public function ping(Request $req): void
    {
        $airportId = (int)$req->get('airport_id', 2);
        $result    = AirportIrFetcher::testConnection($airportId);
        Response::json(array_merge($result, ['success' => $result['reachable']]));
    }

    /**
     * POST /api/v1/fids/sync-live
     * Fetch flights from fids.airport.ir and upsert into fids_flights.
     * Reads settings from modules table. Returns count of saved flights.
     */
    public function syncLive(Request $req): void
    {
        $tid      = $this->tid();
        $settings = $this->loadFidsSettings($tid);

        $airportId = (int)($req->input('airport_id') ?? $settings['airport_id'] ?? 2);
        $direction = $req->input('direction') ?? $settings['direction'] ?? 'all';
        $route     = $req->input('route')     ?? $settings['route']     ?? 'all';
        $limit     = (int)($req->input('limit') ?? $settings['limit']   ?? 50);
        $clearOld  = filter_var($req->input('clear_old') ?? $settings['clear_old'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (!array_key_exists($airportId, AirportIrFetcher::AIRPORTS)) {
            Response::error('فرودگاه نامعتبر', 400);
            return;
        }

        // bust cache so we always get fresh data
        AirportIrFetcher::bust($airportId);

        try {
            $flights = AirportIrFetcher::fetch($airportId, $direction, $route, $limit);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            error_log('[FIDS syncLive] ' . $msg);
            Response::json([
                'success' => false,
                'message' => $msg,
                'hint'    => str_contains($msg, 'قابل دسترس نیست') || str_contains($msg, 'timeout')
                    ? 'برای بررسی اتصال، آدرس GET /api/v1/fids/ping را باز کنید. اگر سرور خارج از ایران است، متغیر FIDS_HTTP_PROXY را در فایل .env تنظیم کنید.'
                    : null,
            ], 502);
            return;
        }

        if (empty($flights)) {
            $conn = AirportIrFetcher::testConnection($airportId);
            Response::json([
                'success'    => false,
                'message'    => $conn['reachable']
                    ? 'سایت fids.airport.ir در دسترس است اما پروازی پارس نشد — ساختار HTML تغییر کرده'
                    : 'سایت fids.airport.ir قابل دسترس نیست: ' . ($conn['error'] ?: 'timeout'),
                'connection' => $conn,
            ], 502);
            return;
        }

        $today = date('Y-m-d');

        // حذف پروازهای auto-fetched امروز برای این فرودگاه
        if ($clearOld) {
            $this->db->query(
                "DELETE FROM fids_flights
                 WHERE tenant_id=? AND source='auto' AND DATE(scheduled_time)=?",
                [$tid, $today]
            );
        }

        $saved   = 0;
        $airport = AirportIrFetcher::AIRPORTS[$airportId]['name'];

        foreach ($flights as $f) {
            try {
                $this->db->insert('fids_flights', [
                    'tenant_id'        => $tid,
                    'flight_number'    => strtoupper($f['flight_number']),
                    'airline_code'     => $f['airline_code']  ?? '',
                    'airline_name'     => $f['airline_name']  ?? '',
                    'airline_name_en'  => '',
                    'type'             => $f['type'],
                    'origin'           => $f['origin']       ?? null,
                    'destination'      => $f['destination']  ?? null,
                    'destination_code' => '',
                    'scheduled_time'   => $f['scheduled_time'],
                    'actual_time'      => $f['actual_time']  ?? null,
                    'status'           => $f['status']       ?? 'scheduled',
                    'status_fa'        => $f['status_fa']    ?? '',
                    'gate'             => $f['gate']         ?? null,
                    'terminal'         => $f['terminal']     ?? null,
                    'belt'             => $f['belt']         ?? null,
                    'counter'          => $f['counter']      ?? null,
                    'aircraft_type'    => $f['aircraft_type'] ?? null,
                    'delay_minutes'    => $f['delay_minutes'] ?? 0,
                    'source'           => 'auto',
                    'airport_id'       => $airportId,
                    'is_active'        => 1,
                ]);
                $saved++;
            } catch (\Throwable $e) {
                // skip duplicate / schema mismatch silently
            }
        }

        // ذخیره زمان آخرین sync در تنظیمات ماژول
        $this->saveFidsLastSync($tid, $airportId, $saved);

        Response::success([
            'airport'    => $airport,
            'airport_id' => $airportId,
            'fetched'    => count($flights),
            'saved'      => $saved,
            'synced_at'  => date('Y-m-d H:i:s'),
        ], "✓ {$saved} پرواز از «{$airport}» ذخیره شد");
    }

    /**
     * GET /api/v1/fids/cron-sync?token=TOKEN
     * Cron-safe endpoint (no JWT) — uses a stored token.
     */
    public function cronSync(Request $req): void
    {
        $token = $req->get('token', '');
        if (!$token) { Response::error('توکن الزامی است', 401); return; }

        // find tenant by cron token stored in modules settings
        $row = $this->db->row(
            "SELECT tenant_id, settings FROM modules WHERE id='fids' AND is_active=1
             AND JSON_UNQUOTE(JSON_EXTRACT(settings,'$.cron_token')) = ?
             LIMIT 1",
            [$token]
        );
        if (!$row) { Response::error('توکن نامعتبر', 401); return; }

        $tid      = (int)$row['tenant_id'];
        $settings = json_decode($row['settings'] ?? '{}', true) ?: [];

        $airportId = (int)($settings['airport_id'] ?? 2);
        $direction = $settings['direction'] ?? 'all';
        $route     = $settings['route']     ?? 'all';
        $limit     = (int)($settings['limit']    ?? 50);

        if (!array_key_exists($airportId, AirportIrFetcher::AIRPORTS)) {
            Response::error('فرودگاه نامعتبر در تنظیمات', 400); return;
        }

        AirportIrFetcher::bust($airportId);

        try {
            $flights = AirportIrFetcher::fetch($airportId, $direction, $route, $limit);
        } catch (\Throwable $e) {
            Response::error('خطا در دریافت: ' . $e->getMessage(), 500); return;
        }

        $today = date('Y-m-d');
        $this->db->query(
            "DELETE FROM fids_flights WHERE tenant_id=? AND source='auto' AND DATE(scheduled_time)=?",
            [$tid, $today]
        );

        $saved = 0;
        foreach ($flights as $f) {
            try {
                $this->db->insert('fids_flights', [
                    'tenant_id'      => $tid,
                    'flight_number'  => strtoupper($f['flight_number']),
                    'airline_code'   => $f['airline_code']  ?? '',
                    'airline_name'   => $f['airline_name']  ?? '',
                    'airline_name_en'=> '',
                    'type'           => $f['type'],
                    'origin'         => $f['origin']       ?? null,
                    'destination'    => $f['destination']  ?? null,
                    'destination_code'=> '',
                    'scheduled_time' => $f['scheduled_time'],
                    'actual_time'    => $f['actual_time']  ?? null,
                    'status'         => $f['status']       ?? 'scheduled',
                    'status_fa'      => $f['status_fa']    ?? '',
                    'delay_minutes'  => $f['delay_minutes'] ?? 0,
                    'source'         => 'auto',
                    'airport_id'     => $airportId,
                    'is_active'      => 1,
                ]);
                $saved++;
            } catch (\Throwable $e) {}
        }

        $this->saveFidsLastSync($tid, $airportId, $saved);
        Response::success(['saved' => $saved, 'synced_at' => date('Y-m-d H:i:s')]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function loadFidsSettings(int $tid): array
    {
        $row = $this->db->row("SELECT settings FROM modules WHERE id='fids' AND tenant_id=?", [$tid]);
        return $row ? (json_decode($row['settings'] ?? '{}', true) ?: []) : [];
    }

    private function saveFidsLastSync(int $tid, int $airportId, int $saved): void
    {
        try {
            $row  = $this->db->row("SELECT settings FROM modules WHERE id='fids' AND tenant_id=?", [$tid]);
            $curr = json_decode($row['settings'] ?? '{}', true) ?: [];
            $curr['last_sync_at']      = date('Y-m-d H:i:s');
            $curr['last_sync_airport'] = $airportId;
            $curr['last_sync_count']   = $saved;
            $this->db->query(
                "UPDATE modules SET settings=? WHERE id='fids' AND tenant_id=?",
                [json_encode($curr, JSON_UNESCAPED_UNICODE), $tid]
            );
        } catch (\Throwable $e) {}
    }

    private function statusFa(string $status): string
    {
        return match($status) {
            'scheduled'   => 'زمان‌بندی‌شده',
            'boarding'    => 'سوارشوید',
            'departed'    => 'پرواز کرد',
            'arrived'     => 'فرود آمد',
            'delayed'     => 'تأخیر',
            'cancelled'   => 'لغو شد',
            'diverted'    => 'مسیر تغییر کرد',
            'gate_change' => 'تغییر دروازه',
            default       => $status,
        };
    }
}
