<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};

/**
 * IPTV Room Controller
 * مدیریت اتاق‌های هتل/واحدها، پیام‌رسانی، یکپارچه‌سازی PMS
 */
class IptvRoomController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    //  CRUD اتاق‌ها
    // ══════════════════════════════════════════════════════════════

    public function index(Request $req): void
    {
        $tid    = Auth::tenantId();
        $floor  = $req->get('floor');
        $type   = $req->get('type');
        $status = $req->get('status');
        $q      = $req->get('q');

        $sql = "SELECT r.*,
                       g.name  AS group_name,
                       s.name  AS screen_name,
                       s.code  AS screen_code,
                       (SELECT COUNT(*) FROM iptv_room_messages m
                        WHERE (m.room_id=r.id OR m.room_id IS NULL)
                          AND m.tenant_id=r.tenant_id AND m.is_active=1
                          AND (m.expires_at IS NULL OR m.expires_at > NOW())) AS active_msgs
                FROM iptv_rooms r
                LEFT JOIN screen_groups g ON g.id = r.group_id
                LEFT JOIN screens       s ON s.iptv_room_id = r.id AND s.tenant_id = r.tenant_id
                WHERE r.tenant_id = ?";
        $params = [$tid];

        if ($floor  !== null && $floor  !== '') { $sql .= ' AND r.floor = ?';     $params[] = (int)$floor; }
        if ($type)                               { $sql .= ' AND r.room_type = ?'; $params[] = $type; }
        if ($status)                             { $sql .= ' AND r.status = ?';    $params[] = $status; }
        if ($q) {
            $sql .= ' AND (r.room_number LIKE ? OR r.room_name LIKE ? OR r.guest_name LIKE ?)';
            $like = "%$q%";
            $params = array_merge($params, [$like, $like, $like]);
        }

        $sql .= ' ORDER BY r.floor ASC, r.room_number ASC';
        Response::success($this->db->rows($sql, $params) ?: []);
    }

    public function store(Request $req): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: [];

        $roomNum = trim($data['room_number'] ?? '');
        if (!$roomNum) { Response::error('شماره اتاق الزامی است', 422); return; }

        if ($this->db->row('SELECT id FROM iptv_rooms WHERE tenant_id=? AND room_number=?', [$tid, $roomNum])) {
            Response::error('این شماره اتاق قبلاً ثبت شده', 422); return;
        }

        $id = $this->db->insert('iptv_rooms', [
            'tenant_id'   => $tid,
            'group_id'    => isset($data['group_id']) && $data['group_id'] !== '' ? (int)$data['group_id'] : null,
            'room_number' => $roomNum,
            'room_name'   => trim($data['room_name'] ?? '') ?: null,
            'floor'       => isset($data['floor']) && $data['floor'] !== '' ? (int)$data['floor'] : null,
            'room_type'   => $data['room_type']   ?? null,
            'pms_room_id' => trim($data['pms_room_id'] ?? '') ?: null,
            'notes'       => trim($data['notes']       ?? '') ?: null,
        ]);

        $this->log('iptv_room.create', 'IptvRoom', (int)$id);
        Response::success($this->getRoom((int)$id), 'اتاق ایجاد شد', 201);
    }

    public function show(Request $req, array $params): void
    {
        $room = $this->getRoom((int)$params['id']);
        if (!$room) { Response::notFound('اتاق یافت نشد'); return; }
        $room['messages'] = $this->getActiveMessages((int)$room['id']);
        Response::success($room);
    }

    public function update(Request $req, array $params): void
    {
        $room = $this->getRoom((int)$params['id']);
        if (!$room) { Response::notFound(); return; }

        $data    = $req->json() ?: [];
        $allowed = ['room_number','room_name','floor','room_type','status',
                    'guest_name','guest_lang','check_in_at','check_out_at',
                    'pms_room_id','notes','group_id'];
        $upd = array_filter(array_intersect_key($data, array_flip($allowed)), fn($v) => $v !== null);
        if (isset($data['group_id'])   && $data['group_id']   === '') $upd['group_id']   = null;
        if (isset($data['guest_name']) && $data['guest_name'] === '') $upd['guest_name'] = null;
        if (isset($data['pms_room_id'])&& $data['pms_room_id']=== '') $upd['pms_room_id']= null;

        if (!empty($upd)) $this->db->update('iptv_rooms', $upd, ['id' => $room['id']]);
        Response::success($this->getRoom($room['id']), 'اتاق بروز شد');
    }

    public function destroy(Request $req, array $params): void
    {
        $room = $this->getRoom((int)$params['id']);
        if (!$room) { Response::notFound(); return; }
        $this->db->delete('iptv_room_messages', ['room_id' => $room['id']]);
        $this->db->delete('iptv_rooms', ['id' => $room['id']]);
        Response::success(null, 'اتاق حذف شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  ورود / خروج مهمان
    // ══════════════════════════════════════════════════════════════

    public function checkin(Request $req, array $params): void
    {
        $room = $this->getRoom((int)$params['id']);
        if (!$room) { Response::notFound(); return; }

        $data = $req->json() ?: [];
        $this->db->update('iptv_rooms', [
            'status'       => 'occupied',
            'guest_name'   => trim($data['guest_name'] ?? '') ?: null,
            'guest_lang'   => $data['guest_lang']  ?? 'fa',
            'check_in_at'  => $data['check_in_at'] ?? date('Y-m-d H:i:s'),
            'check_out_at' => $data['check_out_at'] ?? null,
        ], ['id' => $room['id']]);

        // پیام خوش‌آمدگویی خودکار
        if (!empty($data['guest_name']) && ($data['send_welcome'] ?? true)) {
            $guestName = trim($data['guest_name']);
            $this->insertMessage((int)$room['id'], [
                'title'        => 'خوش آمدید',
                'body'         => "عزیز {$guestName}، به اقامتگاه ما خوش آمدید",
                'msg_type'     => 'welcome',
                'display_mode' => 'popup',
                'priority'     => 10,
                'expires_at'   => date('Y-m-d H:i:s', strtotime('+4 hours')),
            ]);
        }

        $this->log('iptv_room.checkin', 'IptvRoom', $room['id']);
        Response::success($this->getRoom($room['id']), 'ورود مهمان ثبت شد');
    }

    public function checkout(Request $req, array $params): void
    {
        $room = $this->getRoom((int)$params['id']);
        if (!$room) { Response::notFound(); return; }

        $this->db->update('iptv_rooms', [
            'status'       => 'available',
            'guest_name'   => null,
            'guest_lang'   => 'fa',
            'check_in_at'  => null,
            'check_out_at' => null,
        ], ['id' => $room['id']]);
        $this->db->query('UPDATE iptv_room_messages SET is_active=0 WHERE room_id=?', [$room['id']]);

        $this->log('iptv_room.checkout', 'IptvRoom', $room['id']);
        Response::success(null, 'خروج مهمان ثبت شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  پیام‌رسانی
    // ══════════════════════════════════════════════════════════════

    public function sendMessage(Request $req, array $params): void
    {
        $room = $this->getRoom((int)$params['id']);
        if (!$room) { Response::notFound(); return; }

        $data = $req->json() ?: [];
        if (empty($data['body'])) { Response::error('متن پیام الزامی است', 422); return; }

        $id = $this->insertMessage((int)$room['id'], $data);
        Response::success(['id' => $id], 'پیام ارسال شد', 201);
    }

    public function broadcastMessage(Request $req): void
    {
        $data = $req->json() ?: [];
        if (empty($data['body'])) { Response::error('متن پیام الزامی است', 422); return; }
        $id = $this->insertMessage(null, $data);
        Response::success(['id' => $id], 'پیام برای همه اتاق‌ها ارسال شد', 201);
    }

    public function roomMessages(Request $req, array $params): void
    {
        $room = $this->getRoom((int)$params['id']);
        if (!$room) { Response::notFound(); return; }
        $msgs = $this->db->rows(
            'SELECT * FROM iptv_room_messages WHERE (room_id=? OR room_id IS NULL) AND tenant_id=? ORDER BY created_at DESC LIMIT 50',
            [$room['id'], Auth::tenantId()]
        ) ?: [];
        Response::success($msgs);
    }

    public function deleteMessage(Request $req, array $params): void
    {
        $msg = $this->db->row(
            'SELECT * FROM iptv_room_messages WHERE id=? AND tenant_id=?',
            [(int)$params['msgId'], Auth::tenantId()]
        );
        if (!$msg) { Response::notFound(); return; }
        $this->db->delete('iptv_room_messages', ['id' => $msg['id']]);
        Response::success(null, 'پیام حذف شد');
    }

    public function deactivateMessage(Request $req, array $params): void
    {
        $msg = $this->db->row(
            'SELECT * FROM iptv_room_messages WHERE id=? AND tenant_id=?',
            [(int)$params['msgId'], Auth::tenantId()]
        );
        if (!$msg) { Response::notFound(); return; }
        $this->db->update('iptv_room_messages', ['is_active' => 0], ['id' => $msg['id']]);
        Response::success(null, 'پیام غیرفعال شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  PMS API (با api_key auth)
    // ══════════════════════════════════════════════════════════════

    public function pmsCheckin(Request $req): void
    {
        $tid  = $this->resolvePmsTenant($req);
        if (!$tid) { Response::error('کلید API نامعتبر است', 401); return; }

        $data    = $req->json() ?: [];
        $roomNum = trim($data['room_number'] ?? $data['room'] ?? '');
        if (!$roomNum) { Response::error('room_number الزامی است', 422); return; }

        $room = $this->db->row(
            'SELECT * FROM iptv_rooms WHERE tenant_id=? AND (room_number=? OR pms_room_id=?)',
            [$tid, $roomNum, $roomNum]
        );
        if (!$room) { Response::notFound("اتاق {$roomNum} یافت نشد"); return; }

        $this->db->update('iptv_rooms', [
            'status'       => 'occupied',
            'guest_name'   => trim($data['guest_name'] ?? $data['name'] ?? '') ?: null,
            'guest_lang'   => $data['guest_lang']   ?? $data['lang'] ?? 'fa',
            'check_in_at'  => $data['check_in']     ?? $data['check_in_at'] ?? date('Y-m-d H:i:s'),
            'check_out_at' => $data['check_out']     ?? $data['check_out_at'] ?? null,
        ], ['id' => $room['id']]);

        if (!empty($data['guest_name']) && ($data['send_welcome'] ?? true)) {
            $this->insertMessage((int)$room['id'], [
                'title' => 'خوش آمدید', 'body' => "عزیز {$data['guest_name']}، خوش آمدید",
                'msg_type' => 'welcome', 'display_mode' => 'popup',
                'priority' => 10, 'expires_at' => date('Y-m-d H:i:s', strtotime('+4 hours')),
            ], $tid);
        }
        $this->updatePmsLastUsed($req);
        Response::success(['room' => $roomNum, 'status' => 'occupied'], 'ورود ثبت شد');
    }

    public function pmsCheckout(Request $req): void
    {
        $tid = $this->resolvePmsTenant($req);
        if (!$tid) { Response::error('کلید API نامعتبر است', 401); return; }

        $data    = $req->json() ?: [];
        $roomNum = trim($data['room_number'] ?? $data['room'] ?? '');
        if (!$roomNum) { Response::error('room_number الزامی است', 422); return; }

        $room = $this->db->row(
            'SELECT * FROM iptv_rooms WHERE tenant_id=? AND (room_number=? OR pms_room_id=?)',
            [$tid, $roomNum, $roomNum]
        );
        if (!$room) { Response::notFound("اتاق {$roomNum} یافت نشد"); return; }

        $this->db->update('iptv_rooms', [
            'status' => 'available', 'guest_name' => null,
            'guest_lang' => 'fa', 'check_in_at' => null, 'check_out_at' => null,
        ], ['id' => $room['id']]);
        $this->db->query('UPDATE iptv_room_messages SET is_active=0 WHERE room_id=?', [$room['id']]);

        $this->updatePmsLastUsed($req);
        Response::success(['room' => $roomNum, 'status' => 'available'], 'خروج ثبت شد');
    }

    public function pmsSendMessage(Request $req): void
    {
        $tid = $this->resolvePmsTenant($req);
        if (!$tid) { Response::error('کلید API نامعتبر است', 401); return; }

        $data    = $req->json() ?: [];
        $body    = trim($data['body'] ?? $data['message'] ?? $data['text'] ?? '');
        if (!$body) { Response::error('متن پیام الزامی است', 422); return; }

        $roomNum = trim($data['room_number'] ?? $data['room'] ?? '');
        if ($roomNum) {
            $room = $this->db->row(
                'SELECT * FROM iptv_rooms WHERE tenant_id=? AND (room_number=? OR pms_room_id=?)',
                [$tid, $roomNum, $roomNum]
            );
            if (!$room) { Response::notFound("اتاق {$roomNum} یافت نشد"); return; }
            $this->insertMessage((int)$room['id'], ['body' => $body] + $data, $tid);
        } else {
            $this->insertMessage(null, ['body' => $body] + $data, $tid);
        }

        $this->updatePmsLastUsed($req);
        Response::success(null, 'پیام ارسال شد');
    }

    // ── PMS key management ───────────────────────────────────────
    public function getPmsIntegrations(Request $req): void
    {
        $rows = $this->db->rows(
            'SELECT id, name, api_key, pms_type, is_active, last_used_at, created_at FROM pms_integrations WHERE tenant_id=? ORDER BY created_at DESC',
            [Auth::tenantId()]
        ) ?: [];
        Response::success($rows);
    }

    public function createPmsIntegration(Request $req): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: [];
        $name = trim($data['name'] ?? '');
        if (!$name) { Response::error('نام سیستم الزامی است', 422); return; }

        $key = bin2hex(random_bytes(24)); // 48 char hex key
        $id  = $this->db->insert('pms_integrations', [
            'tenant_id' => $tid,
            'name'      => $name,
            'api_key'   => $key,
            'pms_type'  => $data['pms_type'] ?? 'custom',
            'is_active' => 1,
        ]);
        Response::success(['id' => $id, 'api_key' => $key], 'اتصال PMS ایجاد شد', 201);
    }

    public function deletePmsIntegration(Request $req, array $params): void
    {
        $row = $this->db->row('SELECT * FROM pms_integrations WHERE id=? AND tenant_id=?', [(int)$params['pmsId'], Auth::tenantId()]);
        if (!$row) { Response::notFound(); return; }
        $this->db->delete('pms_integrations', ['id' => $row['id']]);
        Response::success(null, 'اتصال حذف شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  پلیر — اطلاعات اتاق + پیام‌های فعال
    // ══════════════════════════════════════════════════════════════

    public function playerRoomInfo(Request $req, array $params): void
    {
        $screen = $this->db->row(
            'SELECT s.iptv_room_id, r.room_number, r.room_name, r.status, r.guest_name, r.guest_lang
             FROM screens s
             LEFT JOIN iptv_rooms r ON r.id = s.iptv_room_id
             WHERE s.code = ?',
            [$params['code']]
        );
        if (!$screen) { Response::notFound(); return; }

        $roomId   = (int)($screen['iptv_room_id'] ?? 0);
        $messages = $roomId ? $this->getActiveMessages($roomId, (int)($screen['tenant_id'] ?? 0)) : [];

        Response::success([
            'room_number' => $screen['room_number'] ?? null,
            'room_name'   => $screen['room_name']   ?? null,
            'status'      => $screen['status']      ?? null,
            'guest_name'  => $screen['guest_name']  ?? null,
            'guest_lang'  => $screen['guest_lang']  ?? 'fa',
            'messages'    => $messages,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    private function getRoom(int $id): ?array
    {
        return $this->db->row(
            'SELECT r.*, g.name AS group_name, s.name AS screen_name, s.code AS screen_code
             FROM iptv_rooms r
             LEFT JOIN screen_groups g ON g.id = r.group_id
             LEFT JOIN screens s       ON s.iptv_room_id = r.id AND s.tenant_id = r.tenant_id
             WHERE r.id=? AND r.tenant_id=?',
            [$id, Auth::tenantId()]
        );
    }

    private function getActiveMessages(int $roomId, int $tid = 0): array
    {
        if (!$tid) $tid = Auth::tenantId();
        return $this->db->rows(
            'SELECT * FROM iptv_room_messages
             WHERE (room_id=? OR room_id IS NULL) AND tenant_id=? AND is_active=1
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY priority DESC, created_at DESC
             LIMIT 20',
            [$roomId, $tid]
        ) ?: [];
    }

    private function insertMessage(?int $roomId, array $data, int $tid = 0): int
    {
        if (!$tid) $tid = Auth::tenantId();
        $types = ['info','welcome','urgent','promo','custom'];
        $modes = ['banner','popup','ticker'];
        return (int)$this->db->insert('iptv_room_messages', [
            'tenant_id'    => $tid,
            'room_id'      => $roomId,
            'title'        => trim($data['title'] ?? '') ?: null,
            'body'         => $data['body'],
            'msg_type'     => in_array($data['msg_type'] ?? $data['type'] ?? '', $types) ? ($data['msg_type'] ?? $data['type']) : 'info',
            'display_mode' => in_array($data['display_mode'] ?? 'banner', $modes) ? ($data['display_mode'] ?? 'banner') : 'banner',
            'priority'     => max(1, min(10, (int)($data['priority'] ?? 5))),
            'expires_at'   => $data['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+24 hours')),
            'is_active'    => 1,
        ]);
    }

    private function resolvePmsTenant(Request $req): ?int
    {
        $key = $req->header('X-PMS-Key')
            ?? $req->header('Authorization') // Bearer <key>
            ?? $req->get('api_key')
            ?? '';
        $key = str_replace('Bearer ', '', trim($key));
        if (!$key) return null;
        $row = $this->db->row('SELECT tenant_id FROM pms_integrations WHERE api_key=? AND is_active=1', [$key]);
        return $row ? (int)$row['tenant_id'] : null;
    }

    private function updatePmsLastUsed(Request $req): void
    {
        $key = str_replace('Bearer ', '', trim(
            $req->header('X-PMS-Key') ?? $req->header('Authorization') ?? $req->get('api_key') ?? ''
        ));
        if ($key) $this->db->query('UPDATE pms_integrations SET last_used_at=NOW() WHERE api_key=?', [$key]);
    }
}
