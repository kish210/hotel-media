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
            $channels = $this->db->rows(
                "SELECT * FROM iptv_channels WHERE tenant_id=? ORDER BY sort_order ASC, name ASC",
                [$tid]
            );
        } catch (\Throwable $e) { /* جدول هنوز نساخته شده */ }
        $this->view('admin.iptv.index', compact('channels') + ['title' => 'مدیریت IPTV']);
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
