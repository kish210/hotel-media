<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};
use App\Models\Screen;

class ScreenController extends Controller
{
    private Screen $screen;

    public function __construct()
    {
        parent::__construct();
        $this->screen = new Screen();
    }

    public function index(Request $req): void
    {
        $tid       = Auth::tenantId();
        // فقط filter های معتبر
        $filters = array_filter([
            'status'      => $req->get('status'),
            'location_id' => $req->get('location_id'),
        ]);
        $screens   = $this->screen->all($filters, (int)$req->get('page', 1));
        $locations = $this->db->rows("SELECT * FROM locations WHERE tenant_id=? AND is_active=1 ORDER BY name", [$tid]);
        $groups    = [];
        try {
            $groups = $this->db->rows(
                "SELECT * FROM screen_groups WHERE tenant_id=? ORDER BY type, sort_order, name",
                [$tid]
            );
        } catch (\Throwable $e) {}

        $this->view('screens.index', [
            'title'     => 'صفحات نمایش',
            'screens'   => is_array($screens) ? $screens : [],
            'locations' => $locations,
            'groups'    => $groups,
        ]);
    }

    public function create(Request $req): void
    {
        $tid       = Auth::tenantId();
        $locations = $this->db->rows("SELECT * FROM locations WHERE tenant_id=? AND is_active=1", [$tid]);
        $groups    = [];
        try { $groups = $this->db->rows("SELECT * FROM screen_groups WHERE tenant_id=? ORDER BY type, name", [$tid]) ?: []; } catch (\Throwable $e) {}
        $iptvMenus = $this->loadIptvMenus($tid);

        $this->view('screens.create', [
            'title'      => 'صفحه جدید',
            'locations'  => $locations,
            'groups'     => $groups,
            'iptvMenus'  => $iptvMenus,
        ]);
    }

    public function store(Request $req): void
    {
        $errors = $req->validate(['name' => 'required|max:255']);
        if ($errors) {
            $this->flash('error', 'نام صفحه الزامی است');
            $this->redirect('/admin/screens/create');
            return;
        }
        $data = $req->post();
        // screen_type
        $data['screen_type'] = in_array($req->post('screen_type'), ['signage','iptv','inflight','monitor_3d'])
            ? $req->post('screen_type') : 'signage';
        // empty string → null برای فیلدهای integer
        foreach (['location_id', 'layout_id', 'current_playlist_id', 'group_id', 'iptv_menu_id', 'iptv_room_id', 'inflight_flight_id'] as $field) {
            if (isset($data[$field]) && $data[$field] === '') $data[$field] = null;
        }
        // فیلد JSON — رشته خالی → null
        if (isset($data['tags']) && trim((string)$data['tags']) === '') {
            $data['tags'] = null;
        }
        $id = $this->screen->create($data);
        $actCode = $this->screen->generateActivationCode((int)$id);
        $this->flash('success', "صفحه ایجاد شد — کد فعال‌سازی: $actCode");
        $this->log('screen.create', 'Screen', (int)$id);
        $this->redirect('/admin/screens/' . $id);
    }

    public function show(Request $req, array $params): void
    {
        $screen = $this->screen->find((int)$params['id']);
        if (!$screen) { $this->redirect('/admin/screens'); return; }

        $tid        = Auth::tenantId();
        $heartbeats = $this->db->rows(
            "SELECT * FROM heartbeats WHERE screen_id=? ORDER BY created_at DESC LIMIT 10",
            [$params['id']]
        );
        $playlists  = $this->db->rows("SELECT id,name FROM playlists WHERE tenant_id=? AND is_active=1 ORDER BY name", [$tid]);
        $locations  = $this->db->rows("SELECT * FROM locations WHERE tenant_id=? AND is_active=1 ORDER BY name", [$tid]);
        $allGroups  = $this->db->rows("SELECT * FROM screen_groups WHERE tenant_id=? ORDER BY name", [$tid]) ?? [];
        $iptvMenus  = $this->loadIptvMenus($tid);
        $iptvRooms      = $this->db->rows("SELECT id, room_number, room_name, floor FROM iptv_rooms WHERE tenant_id=? ORDER BY floor, room_number", [$tid]) ?: [];
        $inflightFlights = [];
        try {
            $inflightFlights = $this->db->rows(
                "SELECT id, flight_number, airline_name, origin_iata, dest_iata FROM inflight_flights WHERE tenant_id=? AND is_active=1 ORDER BY flight_number",
                [$tid]
            ) ?: [];
        } catch (\Throwable $e) {}

        $this->view('screens.show', compact('screen','heartbeats','playlists','locations','allGroups','iptvMenus','iptvRooms','inflightFlights') + ['title' => $screen['name']]);
    }

    public function update(Request $req, array $params): void
    {
        $id     = (int)$params['id'];
        $action = $req->post('_action', 'update');

        if ($action === 'regenerate_code') {
            $code = $this->screen->generateActivationCode($id);
            $this->flash('success', "کد جدید: $code");
            $this->redirect('/admin/screens/' . $id);
            return;
        }

        if ($action === 'command') {
            $cmd = $req->post('command', '');
            if ($cmd) $this->screen->sendCommand($id, $cmd);
            $this->flash('success', "دستور '$cmd' ارسال شد");
            $this->redirect('/admin/screens/' . $id);
            return;
        }

        // Normal update — only allow safe fields
        $section = $req->post('section', 'update');
        $allowed = ['name', 'description', 'orientation', 'resolution', 'location_id', 'brightness', 'volume', 'tags', 'screen_type', 'group_id', 'iptv_menu_id', 'iptv_room_id', 'inflight_flight_id'];
        if (isset($data['screen_type'])) {
            $data['screen_type'] = in_array($data['screen_type'], ['signage','iptv','inflight','monitor_3d'])
                ? $data['screen_type'] : 'signage';
        }
        $data    = array_intersect_key($req->post(), array_flip($allowed));

        // empty string → null برای فیلدهای integer
        foreach (['location_id', 'layout_id', 'current_playlist_id', 'group_id', 'iptv_menu_id', 'iptv_room_id', 'inflight_flight_id'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') $data[$field] = null;
        }

        // فیلدهای JSON — رشته خالی یا invalid JSON → null
        foreach (['tags'] as $jsonField) {
            if (array_key_exists($jsonField, $data)) {
                $v = trim((string)$data[$jsonField]);
                if ($v === '') {
                    $data[$jsonField] = null;
                } elseif ($v[0] !== '[' && $v[0] !== '{' && $v[0] !== '"') {
                    // ورودی متنی (مثلاً tag1,tag2) → تبدیل به آرایه JSON
                    $data[$jsonField] = json_encode(
                        array_values(array_filter(array_map('trim', explode(',', $v)))),
                        JSON_UNESCAPED_UNICODE
                    );
                }
                // اگر از قبل JSON معتبر باشه همان‌طور می‌مونه
            }
        }

        // ذخیره settings (overlay, logo, ticker, clock)
        if ($section === 'overlay' || isset($_POST['settings'])) {
            $settings = $req->post('settings', []);
            if (is_array($settings)) {
                // دریافت settings قبلی و ادغام
                $existing = json_decode($this->db->value("SELECT settings FROM screens WHERE id=?", [$id]) ?? '{}', true) ?: [];
                $merged   = array_merge($existing, $settings);
                $data['settings'] = json_encode($merged, JSON_UNESCAPED_UNICODE);
            }
        }

        if (!empty($data)) {
            $this->screen->update($id, $data);
            $this->log('screen.update', 'Screen', $id);
        }

        $this->flash('success', 'صفحه به‌روز شد');
        $this->redirect('/admin/screens/' . $id);
    }

    public function destroy(Request $req, array $params): void
    {
        $this->screen->delete((int)$params['id']);
        $this->flash('success', 'صفحه حذف شد');
        $this->log('screen.delete', 'Screen', (int)$params['id']);
        $this->redirect('/admin/screens');
    }

    public function monitor(Request $req): void
    {
        $tid = Auth::tenantId();

        $screens = $this->db->rows(
            "SELECT s.*,
                    l.name AS location_name,
                    p.name AS playlist_name,
                    hb.current_item,
                    hb.cpu_usage,
                    hb.memory_usage,
                    hb.player_version,
                    hb.created_at AS last_heartbeat_at,
                    TIMESTAMPDIFF(SECOND, s.last_seen_at, NOW()) AS seconds_ago
             FROM screens s
             LEFT JOIN locations l ON l.id = s.location_id
             LEFT JOIN playlists p ON p.id = s.current_playlist_id
             LEFT JOIN heartbeats hb ON hb.id = (
                 SELECT MAX(id) FROM heartbeats WHERE screen_id = s.id
             )
             WHERE s.tenant_id=? AND s.status != 'inactive'
             ORDER BY s.is_online DESC, s.name ASC",
            [$tid]
        );

        // آمار کلی
        $stats = [
            'total'   => count($screens),
            'online'  => count(array_filter($screens, fn($s) => $s['is_online'])),
            'offline' => count(array_filter($screens, fn($s) => !$s['is_online'] && $s['status']==='active')),
            'pending' => count(array_filter($screens, fn($s) => $s['status']==='pending')),
        ];

        $this->view('screens.monitor', [
            'title'   => 'مانیتورینگ صفحات',
            'screens' => $screens,
            'stats'   => $stats,
        ]);
    }


    public function storeGroup(Request $req): void
    {
        $tid = Auth::tenantId();
        $this->db->insert('screen_groups', [
            'tenant_id' => $tid,
            'name'      => trim($req->post('name', 'گروه جدید')),
            'type'      => in_array($req->post('type'), ['signage','iptv']) ? $req->post('type') : 'signage',
            'color'     => preg_match('/^#[0-9a-f]{6}$/i', $req->post('color','#f97316')) ? $req->post('color') : '#f97316',
            'is_active' => 1,
            'sort_order'=> 0,
        ]);
        $this->flash('success', 'گروه اضافه شد');
        $this->redirect('/admin/screens?tab=' . $req->post('type', 'signage'));
    }

    public function deleteGroup(Request $req, array $params): void
    {
        $this->db->delete('screen_groups', ['id' => (int)$params['id'], 'tenant_id' => Auth::tenantId()]);
        $this->flash('success', 'گروه حذف شد');
        $this->redirect('/admin/screens');
    }

    // ── بارگذاری منوهای IPTV + آیتم‌هاشون ────────────────────────
    private function loadIptvMenus(int $tid): array
    {
        try {
            $menus = $this->db->rows(
                "SELECT m.*, g.name AS group_name,
                        COUNT(i.id) AS item_count
                 FROM iptv_menus m
                 LEFT JOIN screen_groups g  ON g.id = m.group_id
                 LEFT JOIN iptv_menu_items i ON i.menu_id = m.id AND i.is_active = 1
                 WHERE m.tenant_id = ? AND m.is_active = 1
                 GROUP BY m.id
                 ORDER BY m.sort_order, m.name",
                [$tid]
            ) ?: [];

            // بارگذاری آیتم‌های هر منو
            foreach ($menus as &$menu) {
                $menu['items'] = $this->db->rows(
                    "SELECT * FROM iptv_menu_items WHERE menu_id=? AND is_active=1 ORDER BY sort_order",
                    [$menu['id']]
                ) ?: [];
            }
            return $menus;
        } catch (\Throwable $e) {
            return [];
        }
    }
}