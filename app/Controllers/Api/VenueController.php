<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};

/**
 * Venue Controller
 * محل‌های هتل (لابی، رستوران، سالن کنفرانس) و رویدادهایشان.
 *
 * بدون مفهوم «محل»، تابلوی جلوی هر سالن نیاز به یک پلی‌لیست جدا داشت.
 * با آن، یک پلی‌لیست «تابلوی رویداد» روی همه‌ی درها کار می‌کند و هر صفحه
 * برنامه‌ی سالن خودش را نشان می‌دهد.
 */
class VenueController extends Controller
{
    public const KINDS = [
        'lobby', 'restaurant', 'cafe', 'hall', 'pool', 'spa',
        'gym', 'elevator', 'corridor', 'reception', 'shop', 'other',
    ];

    private const EVENT_TYPES = ['conference', 'wedding', 'seminar', 'party', 'exhibition', 'other'];
    private const EVENT_STATUS = ['scheduled', 'ongoing', 'ended', 'cancelled'];

    // ══════════════════════════════════════════════════════════════
    //  محل‌ها
    // ══════════════════════════════════════════════════════════════

    public function index(Request $req): void
    {
        $tid = Auth::tenantId();

        $sql = 'SELECT v.*, m.title AS menu_board_title,
                       (SELECT COUNT(*) FROM screens s WHERE s.venue_id = v.id) AS screen_count,
                       (SELECT COUNT(*) FROM hotel_events e
                         WHERE e.venue_id = v.id AND e.is_active = 1
                           AND COALESCE(e.end_at, e.start_at) >= NOW()) AS upcoming_events
                  FROM venues v
                  LEFT JOIN menu_boards m ON m.id = v.menu_board_id
                 WHERE v.tenant_id = ?';
        $par = [$tid];

        if ($kind = $req->get('kind')) {
            if (!in_array($kind, self::KINDS, true)) { Response::error('نوع محل نامعتبر است', 422); return; }
            $sql .= ' AND v.kind = ?'; $par[] = $kind;
        }

        $sql .= ' ORDER BY v.sort_order, v.name';
        Response::success($this->db->rows($sql, $par));
    }

    public function store(Request $req): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: [];

        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') { Response::error('نام محل الزامی است', 422); return; }

        $kind = $data['kind'] ?? 'other';
        if (!in_array($kind, self::KINDS, true)) { Response::error('نوع محل نامعتبر است', 422); return; }

        $boardId = $this->validMenuBoard($tid, $data['menu_board_id'] ?? null);
        if ($boardId === false) { Response::error('منوی انتخابی معتبر نیست', 422); return; }

        $id = $this->db->insert('venues', [
            'tenant_id'     => $tid,
            'kind'          => $kind,
            'name'          => mb_substr($name, 0, 120),
            'name_en'       => trim((string)($data['name_en'] ?? '')) ?: null,
            'floor'         => trim((string)($data['floor'] ?? '')) ?: null,
            'description'   => trim((string)($data['description'] ?? '')) ?: null,
            'image_url'     => $this->safeUrl($data['image_url'] ?? null),
            'capacity'      => (int)($data['capacity'] ?? 0) ?: null,
            'open_from'     => $this->timeOrNull($data['open_from'] ?? null),
            'open_to'       => $this->timeOrNull($data['open_to'] ?? null),
            'menu_board_id' => $boardId,
            'sort_order'    => (int)($data['sort_order'] ?? 0),
            'is_active'     => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
        ]);

        $this->log('venue.create', 'venue', (int)$id, [], ['name' => $name]);
        Response::success(['id' => (int)$id], 'محل ثبت شد', 201);
    }

    public function update(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        if (!$this->db->exists('venues', ['id' => $id, 'tenant_id' => $tid])) {
            Response::notFound('محل یافت نشد'); return;
        }

        $data   = $req->json() ?: [];
        $fields = [];

        if (array_key_exists('name', $data)) {
            $n = trim((string)$data['name']);
            if ($n === '') { Response::error('نام نمی‌تواند خالی باشد', 422); return; }
            $fields['name'] = mb_substr($n, 0, 120);
        }
        foreach (['name_en', 'floor', 'description'] as $f) {
            if (array_key_exists($f, $data)) $fields[$f] = trim((string)$data[$f]) ?: null;
        }
        if (isset($data['kind'])) {
            if (!in_array($data['kind'], self::KINDS, true)) { Response::error('نوع محل نامعتبر است', 422); return; }
            $fields['kind'] = $data['kind'];
        }
        if (array_key_exists('image_url', $data))  $fields['image_url'] = $this->safeUrl($data['image_url']);
        if (array_key_exists('capacity', $data))   $fields['capacity']  = (int)$data['capacity'] ?: null;
        if (array_key_exists('open_from', $data))  $fields['open_from'] = $this->timeOrNull($data['open_from']);
        if (array_key_exists('open_to', $data))    $fields['open_to']   = $this->timeOrNull($data['open_to']);
        if (isset($data['sort_order']))            $fields['sort_order']= (int)$data['sort_order'];
        if (isset($data['is_active']))             $fields['is_active'] = (int)(bool)$data['is_active'];

        if (array_key_exists('menu_board_id', $data)) {
            $b = $this->validMenuBoard($tid, $data['menu_board_id']);
            if ($b === false) { Response::error('منوی انتخابی معتبر نیست', 422); return; }
            $fields['menu_board_id'] = $b;
        }

        if (!$fields) { Response::error('چیزی برای به‌روزرسانی ارسال نشده', 422); return; }

        $this->db->update('venues', $fields, ['id' => $id, 'tenant_id' => $tid]);
        Response::success(null, 'به‌روزرسانی شد');
    }

    public function destroy(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        if (!$this->db->exists('venues', ['id' => $id, 'tenant_id' => $tid])) {
            Response::notFound('محل یافت نشد'); return;
        }

        // صفحه‌ها و رویدادها حذف نمی‌شوند — فقط ارجاعشان خالی می‌شود،
        // وگرنه حذف یک محل، تابلوی چند صفحه را بی‌صدا از کار می‌انداخت
        $this->db->update('screens',      ['venue_id' => null], ['venue_id' => $id]);
        $this->db->update('hotel_events', ['venue_id' => null], ['venue_id' => $id]);
        $this->db->delete('venues', ['id' => $id, 'tenant_id' => $tid]);

        $this->log('venue.delete', 'venue', $id);
        Response::success(null, 'محل حذف شد — صفحه‌ها و رویدادها باقی ماندند');
    }

    /** POST /venues/{id}/assign-screen — body: { screen_id } */
    public function assignScreen(Request $req, array $params): void
    {
        $tid     = Auth::tenantId();
        $venueId = (int)($params['id'] ?? 0);

        if (!$this->db->exists('venues', ['id' => $venueId, 'tenant_id' => $tid])) {
            Response::notFound('محل یافت نشد'); return;
        }

        $screenId = (int)(($req->json() ?: [])['screen_id'] ?? 0);
        if (!$this->db->exists('screens', ['id' => $screenId, 'tenant_id' => $tid])) {
            Response::error('صفحه‌نمایش معتبر نیست', 422); return;
        }

        $this->db->update('screens', ['venue_id' => $venueId], ['id' => $screenId, 'tenant_id' => $tid]);
        $this->log('venue.assign_screen', 'venue', $venueId, [], ['screen_id' => $screenId]);

        Response::success(null, 'صفحه‌نمایش به این محل وصل شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  رویدادها
    // ══════════════════════════════════════════════════════════════

    /** GET /events — برنامه‌ی سالن‌ها */
    public function events(Request $req): void
    {
        $tid = Auth::tenantId();

        $sql = 'SELECT e.*, v.name AS venue_name, v.floor AS venue_floor
                  FROM hotel_events e
                  LEFT JOIN venues v ON v.id = e.venue_id
                 WHERE e.tenant_id = ?';
        $par = [$tid];

        if ($vid = (int)$req->get('venue_id', 0)) { $sql .= ' AND e.venue_id = ?'; $par[] = $vid; }
        if ($from = $req->get('from'))            { $sql .= ' AND e.start_at >= ?'; $par[] = $from . ' 00:00:00'; }
        if ($to   = $req->get('to'))              { $sql .= ' AND e.start_at <= ?'; $par[] = $to . ' 23:59:59'; }

        if ($req->get('upcoming')) {
            $sql .= " AND e.status <> 'ended' AND COALESCE(e.end_at, e.start_at) >= NOW()";
        }

        $sql .= ' ORDER BY e.start_at LIMIT 300';
        Response::success($this->db->rows($sql, $par));
    }

    public function storeEvent(Request $req): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: [];

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') { Response::error('عنوان رویداد الزامی است', 422); return; }

        $start = $this->dateOrNull($data['start_at'] ?? null);
        if ($start === null) { Response::error('زمان شروع الزامی و باید معتبر باشد', 422); return; }

        $end = $this->dateOrNull($data['end_at'] ?? null);
        if ($end !== null && strtotime($end) <= strtotime($start)) {
            Response::error('زمان پایان باید بعد از شروع باشد', 422); return;
        }

        $venueId = (int)($data['venue_id'] ?? 0) ?: null;
        if ($venueId && !$this->db->exists('venues', ['id' => $venueId, 'tenant_id' => $tid])) {
            Response::error('محل انتخابی معتبر نیست', 422); return;
        }

        // تداخل رزرو سالن — دو رویداد همزمان در یک سالن یعنی اشتباه رزرو
        if ($venueId) {
            $clash = $this->overlap($tid, $venueId, $start, $end, 0);
            if ($clash) {
                Response::error(
                    "این سالن در آن بازه رزرو است: «{$clash['title']}» از {$clash['start_at']}",
                    409
                );
                return;
            }
        }

        $type = $data['type'] ?? 'conference';
        if (!in_array($type, self::EVENT_TYPES, true)) $type = 'other';

        $id = $this->db->insert('hotel_events', [
            'tenant_id'   => $tid,
            'venue_id'    => $venueId,
            'title'       => mb_substr($title, 0, 255),
            'title_en'    => trim((string)($data['title_en'] ?? '')) ?: null,
            'description' => $data['description'] ?? null,
            'location'    => trim((string)($data['location'] ?? '')) ?: null,
            'hall_name'   => trim((string)($data['hall_name'] ?? '')) ?: null,
            'floor'       => trim((string)($data['floor'] ?? '')) ?: null,
            'start_at'    => $start,
            'end_at'      => $end,
            'organizer'   => trim((string)($data['organizer'] ?? '')) ?: null,
            'capacity'    => (int)($data['capacity'] ?? 0) ?: null,
            'type'        => $type,
            'image'       => $this->safeUrl($data['image'] ?? null),
            'color'       => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($data['color'] ?? ''))
                              ? $data['color'] : '#d4af37',
            'status'      => 'scheduled',
            'is_active'   => 1,
        ]);

        $this->log('event.create', 'hotel_event', (int)$id, [], ['title' => $title]);
        Response::success(['id' => (int)$id], 'رویداد ثبت شد', 201);
    }

    public function updateEvent(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        $event = $this->db->row('SELECT * FROM hotel_events WHERE id = ? AND tenant_id = ?', [$id, $tid]);
        if (!$event) { Response::notFound('رویداد یافت نشد'); return; }

        $data   = $req->json() ?: [];
        $fields = [];

        if (array_key_exists('title', $data)) {
            $t = trim((string)$data['title']);
            if ($t === '') { Response::error('عنوان نمی‌تواند خالی باشد', 422); return; }
            $fields['title'] = mb_substr($t, 0, 255);
        }
        foreach (['title_en', 'location', 'hall_name', 'floor', 'organizer'] as $f) {
            if (array_key_exists($f, $data)) $fields[$f] = trim((string)$data[$f]) ?: null;
        }
        if (array_key_exists('description', $data)) $fields['description'] = $data['description'] ?: null;
        if (array_key_exists('capacity', $data))    $fields['capacity']    = (int)$data['capacity'] ?: null;
        if (array_key_exists('image', $data))       $fields['image']       = $this->safeUrl($data['image']);

        if (isset($data['status'])) {
            if (!in_array($data['status'], self::EVENT_STATUS, true)) {
                Response::error('وضعیت نامعتبر است', 422); return;
            }
            $fields['status'] = $data['status'];
        }
        if (isset($data['type']) && in_array($data['type'], self::EVENT_TYPES, true)) {
            $fields['type'] = $data['type'];
        }

        $start = array_key_exists('start_at', $data) ? $this->dateOrNull($data['start_at']) : $event['start_at'];
        $end   = array_key_exists('end_at', $data)   ? $this->dateOrNull($data['end_at'])   : $event['end_at'];

        if (array_key_exists('start_at', $data)) {
            if ($start === null) { Response::error('زمان شروع معتبر نیست', 422); return; }
            $fields['start_at'] = $start;
        }
        if (array_key_exists('end_at', $data)) $fields['end_at'] = $end;

        if ($end !== null && $start !== null && strtotime((string)$end) <= strtotime((string)$start)) {
            Response::error('زمان پایان باید بعد از شروع باشد', 422); return;
        }

        $venueId = array_key_exists('venue_id', $data)
            ? ((int)$data['venue_id'] ?: null)
            : ($event['venue_id'] !== null ? (int)$event['venue_id'] : null);

        if (array_key_exists('venue_id', $data)) {
            if ($venueId && !$this->db->exists('venues', ['id' => $venueId, 'tenant_id' => $tid])) {
                Response::error('محل انتخابی معتبر نیست', 422); return;
            }
            $fields['venue_id'] = $venueId;
        }

        // تداخل فقط وقتی بررسی می‌شود که زمان یا سالن عوض شده باشد
        $timingChanged = array_key_exists('start_at', $data)
                      || array_key_exists('end_at', $data)
                      || array_key_exists('venue_id', $data);

        if ($timingChanged && $venueId && ($fields['status'] ?? $event['status']) !== 'cancelled') {
            $clash = $this->overlap($tid, $venueId, (string)$start, $end, $id);
            if ($clash) {
                Response::error("این سالن در آن بازه رزرو است: «{$clash['title']}»", 409);
                return;
            }
        }

        if (!$fields) { Response::error('چیزی برای به‌روزرسانی ارسال نشده', 422); return; }

        $this->db->update('hotel_events', $fields, ['id' => $id, 'tenant_id' => $tid]);
        $this->log('event.update', 'hotel_event', $id, $event, $fields);

        Response::success(null, 'به‌روزرسانی شد');
    }

    public function destroyEvent(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        if (!$this->db->exists('hotel_events', ['id' => $id, 'tenant_id' => $tid])) {
            Response::notFound('رویداد یافت نشد'); return;
        }

        $this->db->delete('hotel_events', ['id' => $id, 'tenant_id' => $tid]);
        $this->log('event.delete', 'hotel_event', $id);

        Response::success(null, 'رویداد حذف شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /**
     * رویداد متداخل در همان سالن، یا null.
     * @return array<string,mixed>|null
     */
    private function overlap(int $tenantId, int $venueId, string $start, ?string $end, int $excludeId): ?array
    {
        // رویداد بدون پایان، دو ساعته فرض می‌شود — همان فرضی که تابلو دارد
        $endAt = $end ?: date('Y-m-d H:i:s', strtotime($start) + 7200);

        return $this->db->row(
            "SELECT id, title, start_at FROM hotel_events
              WHERE tenant_id = ? AND venue_id = ? AND id <> ?
                AND is_active = 1 AND status NOT IN ('cancelled','ended')
                AND start_at < ?
                AND COALESCE(end_at, DATE_ADD(start_at, INTERVAL 2 HOUR)) > ?
              LIMIT 1",
            [$tenantId, $venueId, $excludeId, $endAt, $start]
        ) ?: null;
    }

    /** @return int|null|false  false یعنی نامعتبر */
    private function validMenuBoard(int $tenantId, mixed $v): int|null|false
    {
        $id = (int)($v ?? 0);
        if ($id <= 0) return null;

        return $this->db->exists('menu_boards', ['id' => $id, 'tenant_id' => $tenantId]) ? $id : false;
    }

    private function timeOrNull(mixed $v): ?string
    {
        $v = trim((string)($v ?? ''));
        return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $v) ? $v : null;
    }

    private function dateOrNull(mixed $v): ?string
    {
        $v = trim((string)($v ?? ''));
        if ($v === '') return null;

        $ts = strtotime($v);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function safeUrl(mixed $v): ?string
    {
        $v = trim((string)($v ?? ''));
        if ($v === '') return null;

        if (str_starts_with($v, '/'))        return mb_substr($v, 0, 500);
        if (preg_match('#^https?://#i', $v)) return mb_substr($v, 0, 500);

        return null;
    }
}
