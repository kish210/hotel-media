<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\ChannelAccessService;

/**
 * Content Controller
 * محتوای جانبی (اخبار، قرآن، کتاب، دفترچه تلفن) + قفل والدین.
 *
 * نقشه‌راه: ۱.۹، ۱.۱۵، ۱.۱۶، ۲.۱۵، ۳.۴
 */
class ContentController extends Controller
{
    public const KINDS = ['news', 'quran', 'book', 'directory', 'info', 'prayer_audio'];

    private ChannelAccessService $access;

    public function __construct()
    {
        parent::__construct();
        $this->access = new ChannelAccessService($this->db);
    }

    // ══════════════════════════════════════════════════════════════
    //  سمت مهمان
    // ══════════════════════════════════════════════════════════════

    /** GET /api/v1/guest/{code}/content/{kind} */
    public function guestContent(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یافت نشد'); return; }

        $kind = (string)($params['kind'] ?? '');
        if (!in_array($kind, self::KINDS, true)) { Response::error('نوع محتوا نامعتبر است', 422); return; }

        $sql = 'SELECT id, kind, category, title, title_en, subtitle, image_url,
                       audio_url, file_url, extra, lang, published_at
                  FROM content_items
                 WHERE tenant_id = ? AND kind = ? AND is_active = 1';
        $par = [(int)$ctx['tenant_id'], $kind];

        if ($cat = trim((string)$req->get('category', ''))) {
            $sql .= ' AND category = ?'; $par[] = $cat;
        }

        // خبر بر اساس تازگی، بقیه بر اساس ترتیب دستی
        $sql .= $kind === 'news'
            ? ' ORDER BY published_at DESC, id DESC'
            : ' ORDER BY sort_order, id';
        $sql .= ' LIMIT ' . max(1, min(300, (int)$req->get('limit', 100)));

        Response::success($this->db->rows($sql, $par));
    }

    /** GET /api/v1/guest/{code}/content/{kind}/{id} — متن کامل */
    public function guestContentItem(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یافت نشد'); return; }

        $row = $this->db->row(
            'SELECT * FROM content_items WHERE id = ? AND tenant_id = ? AND is_active = 1',
            [(int)($params['id'] ?? 0), (int)$ctx['tenant_id']]
        );
        if (!$row) { Response::notFound('محتوا یافت نشد'); return; }

        Response::success($row);
    }

    /** GET /api/v1/guest/{code}/channels — با اعمال سطح دسترسی و قفل */
    public function guestChannels(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''), true);
        if (!$ctx) { Response::notFound('صفحه‌نمایش یافت نشد'); return; }

        $room     = $ctx['room_id'] ? $ctx : null;
        $channels = $this->access->visibleChannels((int)$ctx['tenant_id'], $room);

        // توکن باز شدن قفل از هدر می‌آید — پلیر آن را نگه می‌دارد
        $token    = $req->header('X-Parental-Token');
        $unlocked = $token !== '' && $ctx['room_id']
            && $this->access->verifyToken((int)$ctx['tenant_id'], (int)$ctx['room_id'], $token);

        foreach ($channels as &$c) {
            if ($unlocked) $c['locked'] = false;

            // آدرس کانال قفل‌شده اصلا فرستاده نمی‌شود — وگرنه قفل فقط
            // ظاهری است و با خواندن پاسخ API دور زده می‌شود
            if (!empty($c['locked'])) {
                $c['stream_url'] = null;
                $c['multicast_url'] = null;
            }
        }
        unset($c);

        Response::success([
            'channels'        => $channels,
            'parental_active' => (int)($ctx['parental_enabled'] ?? 0) === 1,
            'unlocked'        => $unlocked,
        ]);
    }

    /** POST /api/v1/guest/{code}/parental/unlock — body: { pin } */
    public function unlock(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''), true);
        if (!$ctx || !$ctx['room_id']) { Response::notFound('اتاق یافت نشد'); return; }

        $pin = trim((string)(($req->json() ?: [])['pin'] ?? ''));
        $res = $this->access->unlock((int)$ctx['tenant_id'], (int)$ctx['room_id'], $pin);

        if (!$res['ok']) { Response::error($res['message'], 403); return; }

        Response::success(
            ['token' => $res['token'], 'expires_in' => $res['expires_in']],
            $res['message']
        );
    }

    /** POST /api/v1/guest/{code}/parental/pin — body: { pin, current_pin } */
    public function setPin(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''), true);
        if (!$ctx || !$ctx['room_id']) { Response::notFound('اتاق یافت نشد'); return; }

        $data = $req->json() ?: [];
        $res  = $this->access->setPin(
            (int)$ctx['tenant_id'], (int)$ctx['room_id'],
            trim((string)($data['pin'] ?? '')),
            trim((string)($data['current_pin'] ?? ''))
        );

        if (!$res['ok']) { Response::error($res['message'], 422); return; }
        Response::success(null, $res['message']);
    }

    /** DELETE /api/v1/guest/{code}/parental/pin — body: { pin } */
    public function disablePin(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''), true);
        if (!$ctx || !$ctx['room_id']) { Response::notFound('اتاق یافت نشد'); return; }

        $pin = trim((string)(($req->json() ?: [])['pin'] ?? ''));
        $res = $this->access->disablePin((int)$ctx['tenant_id'], (int)$ctx['room_id'], $pin);

        if (!$res['ok']) { Response::error($res['message'], 403); return; }
        Response::success(null, $res['message']);
    }

    /**
     * GET /api/v1/guest/{code}/wakeups — بیدارباش‌هایی که باید الان نشان داده شوند
     * پلیر این را هر دقیقه می‌پرسد.
     */
    public function dueWakeups(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''), true);
        if (!$ctx || !$ctx['room_id']) { Response::success([]); return; }

        // پنجره‌ی ۵ دقیقه‌ای: اگر تلویزیون چند دقیقه خاموش یا آفلاین بوده،
        // بیدارباش گم نشود — ولی یک ساعت بعد هم ناگهان ظاهر نشود.
        $rows = $this->db->rows(
            "SELECT id, scheduled_at, note FROM guest_requests
              WHERE tenant_id = ? AND room_id = ? AND category = 'wakeup'
                AND status IN ('pending','accepted','in_progress')
                AND acknowledged_at IS NULL
                AND scheduled_at <= NOW()
                AND scheduled_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
              ORDER BY scheduled_at",
            [(int)$ctx['tenant_id'], (int)$ctx['room_id']]
        );

        // اولین تحویل را ثبت می‌کنیم تا در پنل معلوم باشد واقعا نشان داده شد
        foreach ($rows as $r) {
            $this->db->query(
                'UPDATE guest_requests SET delivered_at = NOW()
                  WHERE id = ? AND delivered_at IS NULL',
                [(int)$r['id']]
            );
        }

        Response::success($rows);
    }

    /** POST /api/v1/guest/{code}/wakeups/{id}/ack — مهمان بیدارباش را بست */
    public function ackWakeup(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''), true);
        if (!$ctx || !$ctx['room_id']) { Response::notFound('اتاق یافت نشد'); return; }

        $n = $this->db->query(
            "UPDATE guest_requests
                SET acknowledged_at = NOW(), status = 'done', done_at = NOW()
              WHERE id = ? AND tenant_id = ? AND room_id = ? AND category = 'wakeup'
                AND acknowledged_at IS NULL",
            [(int)($params['id'] ?? 0), (int)$ctx['tenant_id'], (int)$ctx['room_id']]
        )->rowCount();

        if (!$n) { Response::error('بیدارباش یافت نشد یا قبلا بسته شده', 404); return; }
        Response::success(null, 'ثبت شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  سمت پنل
    // ══════════════════════════════════════════════════════════════

    public function index(Request $req): void
    {
        $tid  = Auth::tenantId();
        $kind = (string)$req->get('kind', '');

        $sql = 'SELECT * FROM content_items WHERE tenant_id = ?';
        $par = [$tid];

        if ($kind !== '') {
            if (!in_array($kind, self::KINDS, true)) { Response::error('نوع محتوا نامعتبر است', 422); return; }
            $sql .= ' AND kind = ?'; $par[] = $kind;
        }

        $sql .= ' ORDER BY kind, sort_order, id';
        Response::success($this->db->rows($sql, $par));
    }

    public function store(Request $req): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: [];

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') { Response::error('عنوان الزامی است', 422); return; }

        $kind = $data['kind'] ?? 'info';
        if (!in_array($kind, self::KINDS, true)) { Response::error('نوع محتوا نامعتبر است', 422); return; }

        $id = $this->db->insert('content_items', [
            'tenant_id'    => $tid,
            'kind'         => $kind,
            'category'     => trim((string)($data['category'] ?? '')) ?: null,
            'title'        => mb_substr($title, 0, 250),
            'title_en'     => trim((string)($data['title_en'] ?? '')) ?: null,
            'subtitle'     => trim((string)($data['subtitle'] ?? '')) ?: null,
            'body'         => $data['body'] ?? null,
            'image_url'    => $this->safeUrl($data['image_url'] ?? null),
            'audio_url'    => $this->safeUrl($data['audio_url'] ?? null),
            'file_url'     => $this->safeUrl($data['file_url'] ?? null),
            'extra'        => trim((string)($data['extra'] ?? '')) ?: null,
            'lang'         => mb_substr((string)($data['lang'] ?? 'fa'), 0, 10),
            'sort_order'   => (int)($data['sort_order'] ?? 0),
            'is_active'    => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
            'published_at' => $kind === 'news' ? date('Y-m-d H:i:s') : null,
        ]);

        $this->log('content.create', 'content_item', (int)$id, [], ['kind' => $kind]);
        Response::success(['id' => (int)$id], 'محتوا ثبت شد', 201);
    }

    public function update(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        if (!$this->db->exists('content_items', ['id' => $id, 'tenant_id' => $tid])) {
            Response::notFound('محتوا یافت نشد'); return;
        }

        $data   = $req->json() ?: [];
        $fields = [];

        if (array_key_exists('title', $data)) {
            $t = trim((string)$data['title']);
            if ($t === '') { Response::error('عنوان نمی‌تواند خالی باشد', 422); return; }
            $fields['title'] = mb_substr($t, 0, 250);
        }
        foreach (['title_en', 'subtitle', 'category', 'extra'] as $f) {
            if (array_key_exists($f, $data)) $fields[$f] = trim((string)$data[$f]) ?: null;
        }
        foreach (['image_url', 'audio_url', 'file_url'] as $f) {
            if (array_key_exists($f, $data)) $fields[$f] = $this->safeUrl($data[$f]);
        }
        if (array_key_exists('body', $data))   $fields['body']       = $data['body'] ?: null;
        if (isset($data['sort_order']))        $fields['sort_order'] = (int)$data['sort_order'];
        if (isset($data['is_active']))         $fields['is_active']  = (int)(bool)$data['is_active'];
        if (isset($data['lang']))              $fields['lang']       = mb_substr((string)$data['lang'], 0, 10);

        if (!$fields) { Response::error('چیزی برای به‌روزرسانی ارسال نشده', 422); return; }

        $this->db->update('content_items', $fields, ['id' => $id, 'tenant_id' => $tid]);
        Response::success(null, 'به‌روزرسانی شد');
    }

    public function destroy(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        if (!$this->db->exists('content_items', ['id' => $id, 'tenant_id' => $tid])) {
            Response::notFound('محتوا یافت نشد'); return;
        }

        $this->db->delete('content_items', ['id' => $id, 'tenant_id' => $tid]);
        $this->log('content.delete', 'content_item', $id);
        Response::success(null, 'حذف شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /**
     * فقط آدرس داخلی یا http(s) — جلوی javascript: و data: را می‌گیرد
     * که مستقیم روی تلویزیون اجرا می‌شدند.
     */
    private function safeUrl(mixed $v): ?string
    {
        $v = trim((string)($v ?? ''));
        if ($v === '') return null;

        if (str_starts_with($v, '/'))            return mb_substr($v, 0, 500);
        if (preg_match('#^https?://#i', $v))     return mb_substr($v, 0, 500);

        return null;
    }

    /** @return array<string,mixed>|null */
    private function resolveRoom(string $code, bool $needRoom = false): ?array
    {
        if ($code === '') return null;

        $row = $this->db->row(
            'SELECT s.tenant_id, s.code,
                    r.id AS room_id, r.access_level, r.parental_enabled, r.parental_pin
               FROM screens s
               LEFT JOIN iptv_rooms r ON r.id = s.iptv_room_id
              WHERE s.code = ?',
            [$code]
        );
        if (!$row) return null;
        if ($needRoom && empty($row['room_id'])) return null;

        return $row;
    }
}
