<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Response, Request, Auth};

class BroadcastController extends Controller
{
    /**
     * POST /api/v1/screens/{id}/broadcast
     * ارسال محتوای فوری به صفحه نمایش
     */
    public function send(Request $req, array $params): void
    {
        $screenId = (int)$params['id'];
        $tid      = Auth::tenantId();

        $screen = $this->db->row(
            "SELECT * FROM screens WHERE id=? AND tenant_id=?",
            [$screenId, $tid]
        );
        if (!$screen) Response::notFound('صفحه یافت نشد');

        $type     = $req->input('type', 'image'); // image | video | url | text
        $content  = $req->input('content', '');   // URL یا متن
        $duration = (int)$req->input('duration', 30);
        $mediaId  = (int)$req->input('media_id', 0);

        // اگه media_id داده شده، URL رو از DB بگیر
        if ($mediaId) {
            $media = $this->db->row(
                "SELECT * FROM media WHERE id=? AND tenant_id=? AND deleted_at IS NULL",
                [$mediaId, $tid]
            );
            if (!$media) Response::notFound('رسانه یافت نشد');
            $content = $media['file_path'] ?? $media['url'] ?? '';
            $type    = $media['type'];
        }

        if (!$content) Response::error('محتوا الزامی است', 400);

        // ذخیره در DB (پلیر در heartbeat دریافت می‌کنه)
        $payload = json_encode([
            'type'       => $type,
            'content'    => $content,
            'duration'   => $duration,
            'sent_at'    => time(),
            /* مدت صفر یعنی «تا توقف دستی»، پس انقضا ندارد. با فرمول
               قبلی همان گزینه بعد از ۱۰ ثانیه منقضی می‌شد و اعلانی که
               باید تا دستور بعدی می‌ماند، به صفحه‌های بعدی نمی‌رسید. */
            'expires_at' => $duration > 0 ? time() + $duration + 10 : null,
        ]);

        $this->db->update('screens',
            ['emergency_broadcast' => $payload],
            ['id' => $screenId]
        );

        // ارسال از طریق WebSocket (اگه متصل بود)
        $this->sendViaWebSocket($screen['code'], $type, $content, $duration);

        // لاگ
        $this->log('broadcast.send', 'Screen', $screenId, [], [
            'type' => $type, 'duration' => $duration
        ]);

        Response::success([
            'screen_code' => $screen['code'],
            'type'        => $type,
            'duration'    => $duration,
        ], 'محتوا با موفقیت ارسال شد');
    }

    /**
     * POST /api/v1/screens/{id}/broadcast/clear
     * پاک کردن محتوای فوری
     */
    public function clear(Request $req, array $params): void
    {
        $this->db->update('screens',
            ['emergency_broadcast' => null],
            ['id' => (int)$params['id'], 'tenant_id' => Auth::tenantId()]
        );

        $screen = $this->db->row("SELECT code FROM screens WHERE id=?", [(int)$params['id']]);
        if ($screen) $this->sendViaWebSocket($screen['code'], 'clear', '', 0);

        /* توقف باید از صف فرمان هم برود، نه فقط WebSocket.
           ‏null کردن `emergency_broadcast` تنها تحویلِ بعدی را قطع
           می‌کند؛ صفحه‌ای که همین حالا اعلان را روی خودش دارد با تایمر
           خودش جلو می‌رود. و روی شبکهٔ هتل (NAT و فایروال) دقیقا
           WebSocket همان چیزی است که وصل نمی‌شود — پس تکیه بر آن یعنی
           دکمهٔ «توقف» روی همان صفحه‌هایی کار نکند که بیشترین احتمال
           را دارند. */
        (new \App\Models\Screen())->sendCommand((int)$params['id'], 'clear');

        Response::success(null, 'پخش فوری متوقف شد');
    }

    /**
     * POST /api/v1/broadcast/all
     * ارسال به همه صفحات tenant
     */
    public function sendAll(Request $req): void
    {
        $tid      = Auth::tenantId();
        $type     = $req->input('type', 'text');
        $content  = $req->input('content', '');
        $duration = (int)$req->input('duration', 30);
        $mediaId  = (int)$req->input('media_id', 0);

        /* توقف سراسری. تا پیش از این فقط توقف per-screen وجود داشت،
           پس پایان‌دادن به یک اعلان هتل‌گستر یعنی باز کردن صفحهٔ هر
           تلویزیون یکی‌یکی — دقیقا در لحظه‌ای که وقت ندارید. */
        if ($req->input('clear')) {
            $all = $this->db->rows("SELECT id, code FROM screens WHERE tenant_id=?", [$tid]) ?: [];
            $sm  = new \App\Models\Screen();
            foreach ($all as $s) {
                $this->db->update('screens', ['emergency_broadcast' => null], ['id' => $s['id']]);
                $this->sendViaWebSocket($s['code'], 'clear', '', 0);
                $sm->sendCommand((int)$s['id'], 'clear');
            }
            Response::success(['screens_count' => count($all)], 'توقف روی ' . count($all) . ' صفحه');
            return;
        }

        if ($mediaId) {
            $media = $this->db->row("SELECT * FROM media WHERE id=? AND tenant_id=?", [$mediaId, $tid]);
            if ($media) { $content = $media['file_path'] ?? ''; $type = $media['type']; }
        }

        if (!$content) Response::error('محتوا الزامی است', 400);

        $screens = $this->db->rows("SELECT id,code FROM screens WHERE tenant_id=? AND status='active'", [$tid]);

        $payload = json_encode([
            'type' => $type, 'content' => $content,
            'duration' => $duration, 'sent_at' => time(),
            /* مدت صفر یعنی «تا توقف دستی»، پس انقضا ندارد. با فرمول
               قبلی همان گزینه بعد از ۱۰ ثانیه منقضی می‌شد و اعلانی که
               باید تا دستور بعدی می‌ماند، به صفحه‌های بعدی نمی‌رسید. */
            'expires_at' => $duration > 0 ? time() + $duration + 10 : null,
        ]);

        foreach ($screens as $s) {
            $this->db->update('screens', ['emergency_broadcast' => $payload], ['id' => $s['id']]);
            $this->sendViaWebSocket($s['code'], $type, $content, $duration);
        }

        Response::success(['screens_count' => count($screens)], 'ارسال به ' . count($screens) . ' صفحه');
    }

    private function sendViaWebSocket(string $code, string $type, string $content, int $duration): void
    {
        try {
            $host = env('WS_HOST', '127.0.0.1');
            $port = (int)env('WS_PORT', 8080);
            $msg  = json_encode([
                'type'    => 'broadcast',
                'channel' => "screen_$code",
                'data'    => ['type' => $type, 'content' => $content, 'duration' => $duration],
            ]);
            $sock = @fsockopen($host, $port, $errno, $errstr, 1);
            if ($sock) {
                $len = strlen($msg);
                $header = chr(0x81) . ($len < 126 ? chr($len) : chr(126) . pack('n', $len));
                fwrite($sock, $header . $msg);
                fclose($sock);
            }
        } catch (\Throwable) {}
    }
}
