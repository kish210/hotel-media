<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\{Controller, Request, Response, Auth};

/**
 * تعریف ساختار هتل — شعبه، گروه، اتاق.
 *
 * ── چرا فقط مدیر ارشد ──────────────────────────────────────────────
 * این‌ها ساختار پایه‌ی سیستم‌اند و یک‌بار موقع راه‌اندازی تعریف
 * می‌شوند. کارمند پذیرش هر روز با اتاق کار می‌کند — ورود و خروج مهمان
 * — ولی نباید بتواند اتاق را حذف کند یا شماره‌اش را عوض کند. حذف یک
 * اتاق یعنی قطع‌شدن تلویزیون آن اتاق و ازدست‌رفتن صورتحسابش.
 *
 * ── چرا سه صفحه و نه یکی ───────────────────────────────────────────
 * سلسله‌مراتب واقعی هتل سه لایه دارد و هر کدام عمر متفاوتی دارند:
 *
 *   شعبه  یک‌بار در عمر سیستم تعریف می‌شود (هتل زنجیره‌ای)
 *   گروه  گاهی عوض می‌شود — «طبقه ۳»، «سوئیت‌ها»، «لابی»
 *   اتاق  زیاد است و مدام وضعیتش عوض می‌شود
 *
 * ── الگوی کارت اتاق ────────────────────────────────────────────────
 * فهرست جدولی برای ۳۰۰ اتاق بی‌فایده است؛ کارمند پذیرش باید در یک
 * نگاه ببیند کدام اتاق پر است. پس هر اتاق یک کارت است با رنگ وضعیت —
 * همان چیزی که میدلورهای استاندارد هتلی (NetUP و مشابه) می‌کنند.
 */
class PropertyController extends Controller
{
    /** @return bool true اگر اجازه دارد */
    private function guard(): bool
    {
        if ((Auth::user()['role'] ?? '') !== 'super_admin') {
            Response::error('این بخش فقط برای مدیر ارشد است', 403);
            return false;
        }
        return true;
    }

    // ══════════════════════════════════════════════════════════════
    //  اتاق‌ها
    // ══════════════════════════════════════════════════════════════

    /** GET /admin/property/rooms */
    public function rooms(Request $req): void
    {
        if (!$this->guard()) return;

        $tid = Auth::tenantId();

        /* صفحه‌ی متصل به هر اتاق هم لازم است: اپراتور باید ببیند کدام
           اتاق تلویزیون دارد و کدام هنوز وصل نشده. */
        $rooms = $this->db->rows(
            'SELECT r.*,
                    g.name AS group_name,
                    l.name AS location_name,
                    s.code AS screen_code,
                    s.name AS screen_name,
                    s.is_online
               FROM iptv_rooms r
          LEFT JOIN screen_groups g ON g.id = r.group_id
          LEFT JOIN locations     l ON l.id = r.location_id
          LEFT JOIN screens       s ON s.iptv_room_id = r.id
              WHERE r.tenant_id = ?
           ORDER BY r.building, r.floor, r.room_number',
            [$tid]
        );

        $groups    = $this->groupList($tid);
        $locations = $this->locationList($tid);

        /* صفحه‌هایی که هنوز به اتاقی وصل نیستند — برای منوی انتخاب */
        $freeScreens = $this->db->rows(
            "SELECT id, code, name FROM screens
              WHERE tenant_id = ? AND screen_type = 'iptv'
                AND (iptv_room_id IS NULL OR iptv_room_id = 0)
           ORDER BY code",
            [$tid]
        );

        $stats = [
            'total'       => count($rooms),
            'occupied'    => 0,
            'available'   => 0,
            'maintenance' => 0,
            'no_screen'   => 0,
        ];
        foreach ($rooms as $r) {
            $stats[$r['status']] = ($stats[$r['status']] ?? 0) + 1;
            if (empty($r['screen_code'])) $stats['no_screen']++;
        }

        $title = 'تعریف اتاق‌ها';
        include VIEWS_PATH . '/admin/property/rooms.php';
    }

    /** POST /admin/property/rooms */
    public function storeRoom(Request $req): void
    {
        if (!$this->guard()) return;

        $tid    = Auth::tenantId();
        $number = trim((string)$req->post('room_number', ''));

        if ($number === '') {
            $this->flash('error', 'شماره اتاق الزامی است');
            $this->redirect('/admin/property/rooms');
            return;
        }

        /* شماره‌ی تکراری در همان هتل — جدول unique دارد ولی پیام
           دیتابیس برای اپراتور بی‌معنی است. */
        if ($this->db->exists('iptv_rooms', ['tenant_id' => $tid, 'room_number' => $number])) {
            $this->flash('error', 'اتاق ' . $number . ' از قبل تعریف شده است');
            $this->redirect('/admin/property/rooms');
            return;
        }

        $id = (int)$this->db->insert('iptv_rooms', [
            'tenant_id'    => $tid,
            'location_id'  => (int)$req->post('location_id', 0) ?: null,
            'group_id'     => (int)$req->post('group_id', 0) ?: null,
            'room_number'  => $number,
            'room_name'    => trim((string)$req->post('room_name', '')) ?: null,
            'building'     => trim((string)$req->post('building', '')) ?: null,
            'wing'         => trim((string)$req->post('wing', '')) ?: null,
            'floor'        => $req->post('floor', '') !== '' ? (int)$req->post('floor') : null,
            'room_type'    => trim((string)$req->post('room_type', '')) ?: null,
            'access_level' => max(0, min(9, (int)$req->post('access_level', 0))),
            'status'       => 'available',
            'guest_lang'   => 'fa',
        ]);

        $this->linkScreen($id, (int)$req->post('screen_id', 0), $tid);

        $this->flash('success', 'اتاق ' . $number . ' اضافه شد');
        $this->redirect('/admin/property/rooms');
    }

    /** POST /admin/property/rooms/{id} */
    public function updateRoom(Request $req, array $p): void
    {
        if (!$this->guard()) return;

        $tid = Auth::tenantId();
        $id  = (int)($p['id'] ?? 0);

        $room = $this->db->row(
            'SELECT id FROM iptv_rooms WHERE id = ? AND tenant_id = ?', [$id, $tid]
        );
        if (!$room) { Response::error('اتاق پیدا نشد', 404); return; }

        $this->db->update('iptv_rooms', [
            'location_id'  => (int)$req->post('location_id', 0) ?: null,
            'group_id'     => (int)$req->post('group_id', 0) ?: null,
            'room_name'    => trim((string)$req->post('room_name', '')) ?: null,
            'building'     => trim((string)$req->post('building', '')) ?: null,
            'wing'         => trim((string)$req->post('wing', '')) ?: null,
            'floor'        => $req->post('floor', '') !== '' ? (int)$req->post('floor') : null,
            'room_type'    => trim((string)$req->post('room_type', '')) ?: null,
            'access_level' => max(0, min(9, (int)$req->post('access_level', 0))),
        ], ['id' => $id]);

        $this->linkScreen($id, (int)$req->post('screen_id', 0), $tid);

        $this->flash('success', 'اتاق به‌روز شد');
        $this->redirect('/admin/property/rooms');
    }

    /** POST /admin/property/rooms/{id}/delete */
    public function deleteRoom(Request $req, array $p): void
    {
        if (!$this->guard()) return;

        $tid = Auth::tenantId();
        $id  = (int)($p['id'] ?? 0);

        $room = $this->db->row(
            'SELECT room_number, status FROM iptv_rooms WHERE id = ? AND tenant_id = ?',
            [$id, $tid]
        );
        if (!$room) { Response::error('اتاق پیدا نشد', 404); return; }

        /* اتاقی که مهمان داخلش است حذف نمی‌شود: تلویزیونش وسط اقامت
           قطع می‌شود و صورتحسابش بی‌صاحب می‌ماند. */
        if ($room['status'] === 'occupied') {
            $this->flash('error',
                'اتاق ' . $room['room_number'] . ' مهمان دارد. اول خروج بزنید.');
            $this->redirect('/admin/property/rooms');
            return;
        }

        /* صفحه‌ی متصل آزاد شود، وگرنه به اتاقی اشاره می‌کند که نیست */
        $this->db->query(
            'UPDATE screens SET iptv_room_id = NULL WHERE iptv_room_id = ? AND tenant_id = ?',
            [$id, $tid]
        );
        $this->db->delete('iptv_rooms', ['id' => $id]);

        $this->flash('success', 'اتاق ' . $room['room_number'] . ' حذف شد');
        $this->redirect('/admin/property/rooms');
    }

    /**
     * ساخت گروهی اتاق — «۳۰۱ تا ۳۲۰».
     *
     * بدون این، تعریف ۳۰۰ اتاق یعنی ۳۰۰ بار پر کردن فرم. این تنها
     * دلیلی است که راه‌اندازی یک هتل کامل در چند دقیقه ممکن می‌شود.
     */
    public function bulkRooms(Request $req): void
    {
        if (!$this->guard()) return;

        $tid   = Auth::tenantId();
        $from  = (int)$req->post('from', 0);
        $to    = (int)$req->post('to', 0);
        $pre   = trim((string)$req->post('prefix', ''));

        if ($from <= 0 || $to < $from) {
            $this->flash('error', 'بازه‌ی شماره اتاق درست نیست');
            $this->redirect('/admin/property/rooms');
            return;
        }
        /* سقف تا یک اشتباه تایپی ۱۰۰ هزار ردیف نسازد */
        if ($to - $from > 499) {
            $this->flash('error', 'حداکثر ۵۰۰ اتاق در هر بار');
            $this->redirect('/admin/property/rooms');
            return;
        }

        $common = [
            'tenant_id'    => $tid,
            'location_id'  => (int)$req->post('location_id', 0) ?: null,
            'group_id'     => (int)$req->post('group_id', 0) ?: null,
            'building'     => trim((string)$req->post('building', '')) ?: null,
            'wing'         => trim((string)$req->post('wing', '')) ?: null,
            'floor'        => $req->post('floor', '') !== '' ? (int)$req->post('floor') : null,
            'room_type'    => trim((string)$req->post('room_type', '')) ?: null,
            'access_level' => max(0, min(9, (int)$req->post('access_level', 0))),
            'status'       => 'available',
            'guest_lang'   => 'fa',
        ];

        $added = 0;
        $skipped = 0;

        for ($n = $from; $n <= $to; $n++) {
            $number = $pre . $n;
            /* اتاق‌های موجود رد می‌شوند نه اینکه کل کار شکست بخورد —
               اپراتور معمولا بازه را دوباره می‌زند تا جاافتاده‌ها را
               اضافه کند. */
            if ($this->db->exists('iptv_rooms', ['tenant_id' => $tid, 'room_number' => $number])) {
                $skipped++;
                continue;
            }
            $this->db->insert('iptv_rooms', $common + ['room_number' => $number]);
            $added++;
        }

        $msg = $added . ' اتاق اضافه شد';
        if ($skipped) $msg .= ' · ' . $skipped . ' اتاق از قبل بود';
        $this->flash('success', $msg);
        $this->redirect('/admin/property/rooms');
    }

    // ══════════════════════════════════════════════════════════════
    //  گروه‌ها
    // ══════════════════════════════════════════════════════════════

    /** GET /admin/property/groups */
    public function groups(Request $req): void
    {
        if (!$this->guard()) return;

        $tid = Auth::tenantId();

        /* تعداد عضو هر گروه — گروه خالی یعنی اپراتور یادش رفته
           دستگاه‌ها را داخلش بگذارد. */
        $groups = $this->db->rows(
            "SELECT g.*,
                    l.name AS location_name,
                    (SELECT COUNT(*) FROM iptv_rooms r WHERE r.group_id = g.id) AS room_count,
                    (SELECT COUNT(*) FROM screen_group_members m WHERE m.group_id = g.id) AS screen_count
               FROM screen_groups g
          LEFT JOIN locations l ON l.id = g.location_id
              WHERE g.tenant_id = ?
           ORDER BY g.type, g.sort_order, g.name",
            [$tid]
        );

        $locations = $this->locationList($tid);

        $title = 'تعریف گروه‌ها';
        include VIEWS_PATH . '/admin/property/groups.php';
    }

    /** POST /admin/property/groups */
    public function storeGroup(Request $req): void
    {
        if (!$this->guard()) return;

        $name = trim((string)$req->post('name', ''));
        if ($name === '') {
            $this->flash('error', 'نام گروه الزامی است');
            $this->redirect('/admin/property/groups');
            return;
        }

        $type = $req->post('type') === 'iptv' ? 'iptv' : 'signage';

        $this->db->insert('screen_groups', [
            'tenant_id'   => Auth::tenantId(),
            'location_id' => (int)$req->post('location_id', 0) ?: null,
            'name'        => $name,
            'description' => trim((string)$req->post('description', '')) ?: null,
            'type'        => $type,
            'color'       => $this->safeColor((string)$req->post('color', '#1a7ac4')),
            'sort_order'  => (int)$req->post('sort_order', 0),
            'is_active'   => 1,
        ]);

        $this->flash('success', 'گروه «' . $name . '» ساخته شد');
        $this->redirect('/admin/property/groups');
    }

    /** POST /admin/property/groups/{id} */
    public function updateGroup(Request $req, array $p): void
    {
        if (!$this->guard()) return;

        $tid = Auth::tenantId();
        $id  = (int)($p['id'] ?? 0);

        if (!$this->db->exists('screen_groups', ['id' => $id, 'tenant_id' => $tid])) {
            Response::error('گروه پیدا نشد', 404); return;
        }

        $this->db->update('screen_groups', [
            'location_id' => (int)$req->post('location_id', 0) ?: null,
            'name'        => trim((string)$req->post('name', '')) ?: 'بدون نام',
            'description' => trim((string)$req->post('description', '')) ?: null,
            'color'       => $this->safeColor((string)$req->post('color', '#1a7ac4')),
            'sort_order'  => (int)$req->post('sort_order', 0),
            'is_active'   => $req->post('is_active') ? 1 : 0,
        ], ['id' => $id]);

        $this->flash('success', 'گروه به‌روز شد');
        $this->redirect('/admin/property/groups');
    }

    /** POST /admin/property/groups/{id}/delete */
    public function deleteGroup(Request $req, array $p): void
    {
        if (!$this->guard()) return;

        $tid = Auth::tenantId();
        $id  = (int)($p['id'] ?? 0);

        if (!$this->db->exists('screen_groups', ['id' => $id, 'tenant_id' => $tid])) {
            Response::error('گروه پیدا نشد', 404); return;
        }

        /* اتاق‌ها و دستگاه‌های داخل گروه حذف نمی‌شوند — فقط از گروه
           بیرون می‌آیند. حذف گروه نباید اتاق را از بین ببرد. */
        $this->db->query('UPDATE iptv_rooms SET group_id = NULL WHERE group_id = ?', [$id]);
        $this->db->delete('screen_group_members', ['group_id' => $id]);
        $this->db->delete('screen_groups', ['id' => $id]);

        $this->flash('success', 'گروه حذف شد — اتاق‌ها و دستگاه‌ها دست‌نخورده ماندند');
        $this->redirect('/admin/property/groups');
    }

    // ══════════════════════════════════════════════════════════════
    //  شعبه‌ها
    // ══════════════════════════════════════════════════════════════

    /** GET /admin/property/locations */
    public function locations(Request $req): void
    {
        if (!$this->guard()) return;

        $tid = Auth::tenantId();

        $locations = $this->db->rows(
            'SELECT l.*,
                    (SELECT COUNT(*) FROM iptv_rooms r WHERE r.location_id = l.id) AS room_count,
                    (SELECT COUNT(*) FROM screens    s WHERE s.location_id = l.id) AS screen_count
               FROM locations l
              WHERE l.tenant_id = ?
           ORDER BY l.name',
            [$tid]
        );

        $title = 'تعریف شعبه‌ها';
        include VIEWS_PATH . '/admin/property/locations.php';
    }

    /** POST /admin/property/locations */
    public function storeLocation(Request $req): void
    {
        if (!$this->guard()) return;

        $name = trim((string)$req->post('name', ''));
        if ($name === '') {
            $this->flash('error', 'نام شعبه الزامی است');
            $this->redirect('/admin/property/locations');
            return;
        }

        $this->db->insert('locations', [
            'tenant_id' => Auth::tenantId(),
            'name'      => $name,
            'city'      => trim((string)$req->post('city', '')) ?: null,
            'address'   => trim((string)$req->post('address', '')) ?: null,
            'phone'     => trim((string)$req->post('phone', '')) ?: null,
            'timezone'  => trim((string)$req->post('timezone', '')) ?: 'Asia/Tehran',
            'is_active' => 1,
        ]);

        $this->flash('success', 'شعبه «' . $name . '» ساخته شد');
        $this->redirect('/admin/property/locations');
    }

    /** POST /admin/property/locations/{id} */
    public function updateLocation(Request $req, array $p): void
    {
        if (!$this->guard()) return;

        $tid = Auth::tenantId();
        $id  = (int)($p['id'] ?? 0);

        if (!$this->db->exists('locations', ['id' => $id, 'tenant_id' => $tid])) {
            Response::error('شعبه پیدا نشد', 404); return;
        }

        $this->db->update('locations', [
            'name'      => trim((string)$req->post('name', '')) ?: 'بدون نام',
            'city'      => trim((string)$req->post('city', '')) ?: null,
            'address'   => trim((string)$req->post('address', '')) ?: null,
            'phone'     => trim((string)$req->post('phone', '')) ?: null,
            'timezone'  => trim((string)$req->post('timezone', '')) ?: 'Asia/Tehran',
            'is_active' => $req->post('is_active') ? 1 : 0,
        ], ['id' => $id]);

        $this->flash('success', 'شعبه به‌روز شد');
        $this->redirect('/admin/property/locations');
    }

    /** POST /admin/property/locations/{id}/delete */
    public function deleteLocation(Request $req, array $p): void
    {
        if (!$this->guard()) return;

        $tid = Auth::tenantId();
        $id  = (int)($p['id'] ?? 0);

        if (!$this->db->exists('locations', ['id' => $id, 'tenant_id' => $tid])) {
            Response::error('شعبه پیدا نشد', 404); return;
        }

        /* شعبه‌ای که اتاق دارد حذف نمی‌شود: آن اتاق‌ها بی‌شعبه می‌مانند
           و اپراتور دیگر نمی‌فهمد کجا هستند. */
        $rooms = (int)$this->db->value(
            'SELECT COUNT(*) FROM iptv_rooms WHERE location_id = ?', [$id]
        );
        if ($rooms > 0) {
            $this->flash('error',
                'این شعبه ' . $rooms . ' اتاق دارد. اول اتاق‌ها را به شعبه‌ی دیگری ببرید.');
            $this->redirect('/admin/property/locations');
            return;
        }

        $this->db->query('UPDATE screens SET location_id = NULL WHERE location_id = ?', [$id]);
        $this->db->query('UPDATE screen_groups SET location_id = NULL WHERE location_id = ?', [$id]);
        $this->db->delete('locations', ['id' => $id]);

        $this->flash('success', 'شعبه حذف شد');
        $this->redirect('/admin/property/locations');
    }

    // ══════════════════════════════════════════════════════════════
    //  ابزار
    // ══════════════════════════════════════════════════════════════

    /** @return list<array<string,mixed>> */
    private function groupList(int $tid): array
    {
        return $this->db->rows(
            "SELECT id, name, color FROM screen_groups
              WHERE tenant_id = ? AND is_active = 1 AND type = 'iptv'
           ORDER BY sort_order, name",
            [$tid]
        );
    }

    /** @return list<array<string,mixed>> */
    private function locationList(int $tid): array
    {
        return $this->db->rows(
            'SELECT id, name, city FROM locations
              WHERE tenant_id = ? AND is_active = 1 ORDER BY name',
            [$tid]
        );
    }

    /**
     * اتصال یک صفحه‌نمایش به اتاق.
     *
     * یک صفحه فقط به یک اتاق وصل می‌شود، پس اتصال قبلی‌اش باز می‌شود.
     * بدون این، دو اتاق به یک تلویزیون اشاره می‌کنند و پورتال مهمان
     * نام اشتباه نشان می‌دهد.
     */
    private function linkScreen(int $roomId, int $screenId, int $tid): void
    {
        /* اتصال فعلی این اتاق را باز کن */
        $this->db->query(
            'UPDATE screens SET iptv_room_id = NULL WHERE iptv_room_id = ? AND tenant_id = ?',
            [$roomId, $tid]
        );

        if ($screenId <= 0) return;

        $this->db->query(
            'UPDATE screens SET iptv_room_id = ? WHERE id = ? AND tenant_id = ?',
            [$roomId, $screenId, $tid]
        );
    }

    /** رنگ فقط اگر واقعا hex باشد — وگرنه مستقیم داخل style می‌رود */
    private function safeColor(string $c): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#1a7ac4';
    }
}
