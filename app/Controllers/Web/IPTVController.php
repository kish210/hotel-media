<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};

class IPTVController extends Controller
{
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();
        $channels = [];
        try {
            /* نام گروه از جدول می‌آید. ستون متنی `category` فقط مقدار
               خامِ آمده از هدِند است و دیگر مرجع گروه‌بندی نیست. */
            $channels = $this->db->rows(
                "SELECT c.*, g.name AS group_name, g.color AS group_color
                   FROM iptv_channels c
              LEFT JOIN iptv_channel_groups g ON g.id = c.group_id
                  WHERE c.tenant_id=?
                  ORDER BY g.sort_order, g.name, c.sort_order ASC, c.name ASC",
                [$tid]
            );
        } catch (\Throwable $e) { /* جدول هنوز نساخته شده */ }

        $groups = [];
        try { $groups = (new \App\Services\ChannelGroupService($this->db))->all($tid); }
        catch (\Throwable $e) { /* پیش از migration 049 */ }

        $this->view('admin.iptv.index', compact('channels', 'groups') + ['title' => 'مدیریت IPTV']);
    }

    public function store(Request $req): void
    {
        $tid = Auth::tenantId();
        $url = trim($req->post('stream_url', ''));
        if (!$url) { $this->flash('error', 'آدرس استریم الزامی است'); $this->redirect('/admin/iptv'); return; }

        $this->db->insert('iptv_channels', [
            'tenant_id'  => $tid,
            'name'       => trim($req->post('name', 'کانال جدید')),
            'stream_url' => $url,
            'logo_url'   => $req->post('logo_url') ?: null,
            'category'   => $req->post('category', 'general'),
            /* گروه از همان سرویسی حل می‌شود که import هم استفاده می‌کند،
               وگرنه گروه ساخته‌شده از فرم و گروه آمده از هدِند دو ردیف
               جدا می‌شدند — همان اتفاقی که با ستون متنی می‌افتاد. */
            'group_id'   => $req->post('group_id')
                ? (int)$req->post('group_id')
                : (new \App\Services\ChannelGroupService($this->db))
                    ->resolve($tid, $req->post('category', 'general')),
            'protocol'   => $req->post('protocol', $this->guessProtocol($url)),
            'sort_order' => 0,
            'is_active'  => 1,
        ]);
        $this->flash('success', 'کانال اضافه شد');
        $this->redirect('/admin/iptv');
    }

    public function delete(Request $req, array $params): void
    {
        $this->db->delete('iptv_channels', ['id' => (int)$params['id'], 'tenant_id' => Auth::tenantId()]);
        $this->flash('success', 'کانال حذف شد');
        $this->redirect('/admin/iptv');
    }

    // ══════════════════════════════════════════════════════════════
    //  گروه کانال
    // ══════════════════════════════════════════════════════════════

    public function storeGroup(Request $req): void
    {
        $id = $req->post('id') ? (int)$req->post('id') : null;
        $r  = (new \App\Services\ChannelGroupService($this->db))
                ->save(Auth::tenantId(), $req->post(), $id);

        $this->flash($r['ok'] ? 'success' : 'error', $r['message']);
        if ($r['ok']) $this->log($id ? 'channel_group.update' : 'channel_group.create', 'ChannelGroup', $r['id'] ?? null);
        $this->redirect('/admin/iptv');
    }

    public function deleteGroup(Request $req, array $params): void
    {
        $r = (new \App\Services\ChannelGroupService($this->db))
                ->delete(Auth::tenantId(), (int)$params['id']);

        $this->flash($r['ok'] ? 'success' : 'error', $r['message']);
        $this->redirect('/admin/iptv');
    }

    /** انتقال گروهی کانال‌ها به یک گروه — بعد از import لازم می‌شود */
    public function assignGroup(Request $req): void
    {
        $tid = Auth::tenantId();
        $ids = array_filter(array_map('intval', (array)$req->post('ids', [])));
        $gid = $req->post('group_id') ? (int)$req->post('group_id') : null;

        if (!$ids) { $this->flash('error', 'کانالی انتخاب نشده'); $this->redirect('/admin/iptv'); return; }
        if ($gid && !$this->db->value('SELECT id FROM iptv_channel_groups WHERE id=? AND tenant_id=?', [$gid, $tid])) {
            $this->flash('error', 'گروه معتبر نیست'); $this->redirect('/admin/iptv'); return;
        }

        /* ‏IN با placeholder ساخته می‌شود، نه با چسباندن رشته —
           شناسه‌ها از فرم می‌آیند. */
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $this->db->query(
            "UPDATE iptv_channels SET group_id = ? WHERE tenant_id = ? AND id IN ($ph)",
            array_merge([$gid, $tid], $ids)
        );

        $this->flash('success', count($ids) . ' کانال جابه‌جا شد');
        $this->redirect('/admin/iptv');
    }

    public function import(Request $req): void
    {
        $tid = Auth::tenantId();
        $content = '';

        if (!empty($_FILES['m3u_file']['tmp_name'])) {
            $content = file_get_contents($_FILES['m3u_file']['tmp_name']) ?: '';
        } elseif ($url = $req->post('m3u_url', '')) {
            $content = @file_get_contents($url) ?: '';
        }

        if (!$content) { $this->flash('error', 'فایل M3U خوانده نشد'); $this->redirect('/admin/iptv'); return; }

        $channels = $this->parseM3U($content);
        $imported = 0;
        foreach ($channels as $ch) {
            try {
                $this->db->insert('iptv_channels', [
                    'tenant_id'  => $tid,
                    'name'       => $ch['name'],
                    'stream_url' => $ch['url'],
                    'logo_url'   => $ch['logo'] ?: null,
                    'category'   => $ch['group'] ?: 'imported',
                    'group_id'   => (new \App\Services\ChannelGroupService($this->db))
                                        ->resolve($tid, $ch['group'] ?: 'imported'),
                    'protocol'   => $this->guessProtocol($ch['url']),
                    'is_active'  => 1,
                    'sort_order' => $imported,
                ]);
                $imported++;
            } catch (\Throwable $e) {}
            if ($imported >= 500) break;
        }
        $this->flash('success', "$imported کانال ایمپورت شد");
        $this->redirect('/admin/iptv');
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

    private function guessProtocol(string $url): string
    {
        $s = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        if ($s === 'rtsp') return 'rtsp';
        if ($s === 'rtmp') return 'rtmp';
        if (str_contains($url, '.m3u8')) return 'hls';
        return 'http';
    }
}
