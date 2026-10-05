<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};

/**
 * TVHeadend Integration Controller
 * مدیریت سرورهای TVHeadend و ایمپورت کانال‌های پخش زنده
 */
class TvheadendController extends Controller
{
    // ─── صفحه اصلی تنظیمات ──────────────────────────────────────
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();
        $sources = [];
        try {
            $this->ensureTable();
            $sources = $this->db->rows(
                "SELECT * FROM tvheadend_sources WHERE tenant_id=? ORDER BY id ASC",
                [$tid]
            );
        } catch (\Throwable $e) {}

        $this->view('admin.iptv.tvheadend', compact('sources') + ['title' => 'TVHeadend — پخش زنده']);
    }

    // ─── افزودن سرور جدید ───────────────────────────────────────
    public function store(Request $req): void
    {
        $this->ensureTable();
        $tid = Auth::tenantId();
        $url = rtrim(trim($req->post('server_url', '')), '/');

        if (!$url) {
            $this->flash('error', 'آدرس سرور الزامی است');
            $this->redirect('/admin/iptv/tvheadend');
            return;
        }

        $this->db->insert('tvheadend_sources', [
            'tenant_id'      => $tid,
            'name'           => trim($req->post('name', 'TVHeadend Server')),
            'server_url'     => $url,
            'username'       => $req->post('username') ?: null,
            'password'       => $req->post('password') ?: null,
            'stream_profile' => $req->post('stream_profile', 'pass'),
            'is_active'      => 1,
        ]);
        $this->flash('success', 'سرور TVHeadend اضافه شد');
        $this->redirect('/admin/iptv/tvheadend');
    }

    // ─── حذف سرور ───────────────────────────────────────────────
    public function delete(Request $req, array $p): void
    {
        $this->db->delete('tvheadend_sources',
            ['id' => (int)$p['id'], 'tenant_id' => Auth::tenantId()]
        );
        $this->flash('success', 'سرور حذف شد');
        $this->redirect('/admin/iptv/tvheadend');
    }

    // ─── تست اتصال (AJAX) ───────────────────────────────────────
    public function testConnection(Request $req, array $p): void
    {
        header('Content-Type: application/json');
        $src = $this->db->row(
            "SELECT * FROM tvheadend_sources WHERE id=? AND tenant_id=?",
            [(int)$p['id'], Auth::tenantId()]
        );
        if (!$src) { echo json_encode(['ok'=>false,'msg'=>'سرور یافت نشد']); exit; }

        $result = $this->tvhRequest($src, '/api/serverinfo');
        if ($result['ok']) {
            $info = json_decode($result['body'], true) ?? [];
            echo json_encode([
                'ok'  => true,
                'msg' => 'اتصال موفق',
                'version' => $info['sw_version'] ?? '?',
                'name'    => $info['name'] ?? 'TVHeadend',
            ]);
        } else {
            echo json_encode(['ok'=>false, 'msg' => $result['error']]);
        }
        exit;
    }

    // ─── سینک کانال‌ها از TVHeadend ─────────────────────────────
    public function syncChannels(Request $req, array $p): void
    {
        header('Content-Type: application/json');
        $tid = Auth::tenantId();

        $src = $this->db->row(
            "SELECT * FROM tvheadend_sources WHERE id=? AND tenant_id=?",
            [(int)$p['id'], $tid]
        );
        if (!$src) { echo json_encode(['ok'=>false,'msg'=>'سرور یافت نشد']); exit; }

        // دریافت لیست کانال‌ها
        $result = $this->tvhRequest($src, '/api/channel/list?limit=2000');
        if (!$result['ok']) {
            echo json_encode(['ok'=>false,'msg'=>'خطا در دریافت کانال‌ها: '.$result['error']]);
            exit;
        }

        $data = json_decode($result['body'], true);
        $entries = $data['entries'] ?? [];
        if (empty($entries)) {
            echo json_encode(['ok'=>false,'msg'=>'هیچ کانالی یافت نشد']);
            exit;
        }

        $baseUrl = rtrim($src['server_url'], '/');
        $profile = $src['stream_profile'] ?: 'pass';
        $imported = 0;
        $updated  = 0;

        foreach ($entries as $ch) {
            $uuid   = $ch['uuid']   ?? '';
            $name   = $ch['name']   ?? 'بدون نام';
            $icon   = $ch['icon']   ?? '';
            $number = (int)($ch['number'] ?? 0);

            if (!$uuid) continue;

            // آدرس استریم
            $streamUrl = $baseUrl . '/stream/channel/' . $uuid . '?profile=' . $profile;

            // لوگو
            $logoUrl = null;
            if ($icon && !str_starts_with($icon, 'imagecache')) {
                $logoUrl = str_starts_with($icon, 'http') ? $icon : $baseUrl . '/' . ltrim($icon, '/');
            }

            // بررسی وجود کانال
            $existing = $this->db->row(
                "SELECT id FROM iptv_channels WHERE tenant_id=? AND tvh_uuid=?",
                [$tid, $uuid]
            );

            if ($existing) {
                $this->db->update('iptv_channels', [
                    'name'        => $name,
                    'stream_url'  => $streamUrl,
                    'logo_url'    => $logoUrl,
                    'sort_order'  => $number,
                    'is_active'   => 1,
                    'source_type' => 'tvheadend',
                    'source_id'   => (int)$src['id'],
                ], ['id' => $existing['id']]);
                $updated++;
            } else {
                $this->db->insert('iptv_channels', [
                    'tenant_id'   => $tid,
                    'name'        => $name,
                    'stream_url'  => $streamUrl,
                    'logo_url'    => $logoUrl,
                    'category'    => 'livetv',
                    'group_id'    => (new \App\Services\ChannelGroupService($this->db))->resolve($tid, 'livetv'),
                    'protocol'    => 'http',
                    'sort_order'  => $number,
                    'is_active'   => 1,
                    'source_type' => 'tvheadend',
                    'source_id'   => (int)$src['id'],
                    'tvh_uuid'    => $uuid,
                ]);
                $imported++;
            }
        }

        // آپدیت آمار sync
        $this->db->update('tvheadend_sources', [
            'last_sync'  => date('Y-m-d H:i:s'),
            'sync_count' => (int)$src['sync_count'] + $imported + $updated,
        ], ['id' => $src['id']]);

        echo json_encode([
            'ok'       => true,
            'imported' => $imported,
            'updated'  => $updated,
            'total'    => count($entries),
            'msg'      => "$imported کانال جدید + $updated آپدیت از " . count($entries) . " کانال",
        ]);
        exit;
    }

    // ─── ایمپورت از M3U خود TVHeadend ───────────────────────────
    public function importM3u(Request $req, array $p): void
    {
        header('Content-Type: application/json');
        $tid = Auth::tenantId();

        $src = $this->db->row(
            "SELECT * FROM tvheadend_sources WHERE id=? AND tenant_id=?",
            [(int)$p['id'], $tid]
        );
        if (!$src) { echo json_encode(['ok'=>false,'msg'=>'سرور یافت نشد']); exit; }

        $result = $this->tvhRequest($src, '/playlist?profile=' . ($src['stream_profile'] ?: 'pass'));
        if (!$result['ok']) {
            echo json_encode(['ok'=>false,'msg'=>'خطا در دریافت M3U: '.$result['error']]);
            exit;
        }

        // ارسال به IPTVController برای parse
        $channels = $this->parseM3U($result['body']);
        $baseUrl  = rtrim($src['server_url'], '/');
        $imported = 0;

        foreach ($channels as $ch) {
            if (!$ch['url']) continue;
            $existing = $this->db->row(
                "SELECT id FROM iptv_channels WHERE tenant_id=? AND stream_url=?",
                [$tid, $ch['url']]
            );
            if (!$existing) {
                $this->db->insert('iptv_channels', [
                    'tenant_id'   => $tid,
                    'name'        => $ch['name'],
                    'stream_url'  => $ch['url'],
                    'logo_url'    => $ch['logo'] ?: null,
                    'category'    => $ch['group'] ?: 'livetv',
                    'group_id'    => (new \App\Services\ChannelGroupService($this->db))
                                         ->resolve($tid, $ch['group'] ?: 'livetv'),
                    'protocol'    => 'http',
                    'is_active'   => 1,
                    'source_type' => 'tvheadend',
                    'source_id'   => (int)$src['id'],
                ]);
                $imported++;
            }
        }

        $this->db->update('tvheadend_sources', [
            'last_sync'  => date('Y-m-d H:i:s'),
        ], ['id' => $src['id']]);

        echo json_encode(['ok'=>true,'imported'=>$imported,'msg'=>"$imported کانال از M3U ایمپورت شد"]);
        exit;
    }

    // ─── helpers ────────────────────────────────────────────────
    private function tvhRequest(array $src, string $path): array
    {
        $url = rtrim($src['server_url'], '/') . $path;
        $ctx = stream_context_create(['http' => [
            'timeout' => 10,
            'header'  => $this->basicAuthHeader($src),
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return ['ok'=>false, 'body'=>'', 'error'=>'اتصال به سرور برقرار نشد'];
        }
        $code = 0;
        if (isset($http_response_header)) {
            preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
            $code = (int)($m[1] ?? 0);
        }
        if ($code === 401) return ['ok'=>false,'body'=>$body,'error'=>'نام کاربری یا رمز اشتباه است (401)'];
        if ($code >= 400) return ['ok'=>false,'body'=>$body,'error'=>"خطای HTTP $code"];
        return ['ok'=>true,'body'=>$body,'error'=>''];
    }

    private function basicAuthHeader(array $src): string
    {
        if (!$src['username']) return '';
        $cred = base64_encode($src['username'] . ':' . ($src['password'] ?? ''));
        return "Authorization: Basic $cred\r\n";
    }

    private function parseM3U(string $content): array
    {
        $lines = explode("\n", str_replace("\r", "", $content));
        $channels = []; $cur = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#EXTINF:')) {
                $cur = ['name'=>'', 'url'=>'', 'logo'=>'', 'group'=>''];
                if (preg_match('/,(.+)$/', $line, $m)) $cur['name'] = trim($m[1]);
                if (preg_match('/tvg-logo="([^"]+)"/', $line, $m)) $cur['logo'] = $m[1];
                if (preg_match('/group-title="([^"]+)"/', $line, $m)) $cur['group'] = $m[1];
            } elseif ($line && !str_starts_with($line, '#') && $cur) {
                $cur['url'] = $line;
                $channels[] = $cur;
                $cur = [];
            }
        }
        return $channels;
    }

    private function ensureTable(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `tvheadend_sources` (
            `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tenant_id`     INT UNSIGNED NOT NULL DEFAULT 1,
            `name`          VARCHAR(255) NOT NULL DEFAULT 'TVHeadend Server',
            `server_url`    VARCHAR(500) NOT NULL,
            `username`      VARCHAR(255) DEFAULT NULL,
            `password`      VARCHAR(255) DEFAULT NULL,
            `stream_profile` VARCHAR(100) DEFAULT 'pass',
            `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
            `last_sync`     TIMESTAMP NULL DEFAULT NULL,
            `sync_count`    INT UNSIGNED DEFAULT 0,
            `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_tenant` (`tenant_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // اضافه کردن ستون‌های جدید به iptv_channels اگه نباشن
        try {
            $this->db->query("ALTER TABLE `iptv_channels`
                ADD COLUMN IF NOT EXISTS `source_type` VARCHAR(20) DEFAULT 'manual' AFTER `is_active`,
                ADD COLUMN IF NOT EXISTS `source_id`   INT UNSIGNED DEFAULT NULL    AFTER `source_type`,
                ADD COLUMN IF NOT EXISTS `tvh_uuid`    VARCHAR(100) DEFAULT NULL    AFTER `source_id`");
        } catch (\Throwable) {}
    }
}
