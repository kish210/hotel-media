<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response};

/**
 * IPTV Transcoder/Proxy Controller
 * تبدیل RTSP/UDP/RTMP به HLS برای نمایش در مرورگر
 */
class IPTVController extends Controller
{
    private array $ALLOWED_PROTOCOLS = ['rtsp','rtmp','rtp','udp','http','https'];

    /** GET /api/v1/iptv/proxy - بررسی وضعیت یا redirect */
    public function proxy(Request $req): void
    {
        $url    = $req->get('url', '');
        $format = $req->get('format', 'hls');

        if (!$url) { Response::error('URL الزامی است', 400); return; }

        // بررسی protocol مجاز
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        if (!in_array($scheme, $this->ALLOWED_PROTOCOLS)) {
            Response::error('پروتکل غیرمجاز', 403); return;
        }

        // بررسی FFmpeg
        $ffmpegPath = $this->findFFmpeg();

        if ($scheme === 'https' || ($scheme === 'http' && str_contains($url, '.m3u8'))) {
            // HLS مستقیم - redirect
            header('Location: ' . $url, true, 302); exit;
        }

        if (!$ffmpegPath) {
            // FFmpeg نیست - info بده
            Response::json([
                'success' => false,
                'message' => 'FFmpeg در دسترس نیست. برای RTSP نیاز به FFmpeg دارید.',
                'install' => 'در docker-compose: image: jrottenberg/ffmpeg',
                'stream_url' => $url,
                'workaround' => 'از VLC یا ffserver استفاده کنید تا RTSP رو به HLS تبدیل کنید.',
            ]);
            return;
        }

        // راه‌اندازی FFmpeg transcoder
        $cacheKey = md5($url);
        $hlsDir   = '/tmp/iptv/' . $cacheKey;
        $hlsPath  = $hlsDir . '/stream.m3u8';

        if (!is_dir($hlsDir)) mkdir($hlsDir, 0777, true);

        // بررسی آیا stream در حال اجراست
        if (file_exists($hlsPath) && filemtime($hlsPath) > time() - 10) {
            header('Content-Type: application/vnd.apple.mpegurl');
            readfile($hlsPath); exit;
        }

        // شروع FFmpeg در background
        $cmd = sprintf(
            '%s -i %s -c:v libx264 -preset ultrafast -tune zerolatency ' .
            '-c:a aac -f hls -hls_time 2 -hls_list_size 5 -hls_flags delete_segments ' .
            '-hls_segment_filename %s/seg%%03d.ts %s > /dev/null 2>&1 &',
            escapeshellarg($ffmpegPath),
            escapeshellarg($url),
            escapeshellarg($hlsDir),
            escapeshellarg($hlsPath)
        );
        exec($cmd);

        // صبر تا اولین segment آماده شه
        $waited = 0;
        while (!file_exists($hlsPath) && $waited < 8) {
            sleep(1); $waited++;
        }

        if (file_exists($hlsPath)) {
            header('Content-Type: application/vnd.apple.mpegurl');
            readfile($hlsPath);
        } else {
            Response::error('Transcoder timeout - stream ممکن است در دسترس نباشد', 503);
        }
        exit;
    }

    /** GET /api/v1/iptv/channels - لیست کانال‌ها */
    public function channels(Request $req): void
    {
        $tid = \App\Core\Auth::tenantId();
        $channels = $this->db->rows(
            "SELECT * FROM iptv_channels WHERE tenant_id=? ORDER BY sort_order, name ASC",
            [$tid]
        );
        Response::success($channels);
    }

    /** POST /api/v1/iptv/channels - افزودن کانال */
    public function storeChannel(Request $req): void
    {
        $tid  = \App\Core\Auth::tenantId();
        $data = $req->json() ?: $req->post();
        $id = $this->db->insert('iptv_channels', [
            'tenant_id'    => $tid,
            'name'         => $data['name'] ?? 'کانال جدید',
            'stream_url'   => $data['stream_url'] ?? '',
            'logo_url'     => $data['logo_url'] ?? null,
            'category'     => $data['category'] ?? 'general',
            'protocol'     => strtolower(parse_url($data['stream_url'] ?? '', PHP_URL_SCHEME) ?? 'hls'),
            'epg_id'       => $data['epg_id'] ?? null,
            'sort_order'   => (int)($data['sort_order'] ?? 0),
            'is_active'    => 1,
        ]);
        Response::success(['id' => $id], 'کانال اضافه شد');
    }

    /** POST /api/v1/iptv/import-m3u - ایمپورت M3U playlist ───
     *  body: { url: "http://..." } یا multipart file
     */
    public function importM3U(Request $req): void
    {
        $tid = \App\Core\Auth::tenantId();
        $m3uUrl = ($req->json()['url'] ?? $req->post('url', ''));

        if (!$m3uUrl && isset($_FILES['file'])) {
            $content = file_get_contents($_FILES['file']['tmp_name']);
        } elseif ($m3uUrl) {
            $content = @file_get_contents($m3uUrl);
        } else {
            Response::error('URL یا فایل M3U الزامی است', 400); return;
        }

        if (!$content) { Response::error('M3U فایل خوانده نشد', 422); return; }

        $channels = $this->parseM3U($content);
        $imported = 0;

        foreach ($channels as $ch) {
            $this->db->insert('iptv_channels', [
                'tenant_id'  => $tid,
                'name'       => $ch['name'],
                'stream_url' => $ch['url'],
                'logo_url'   => $ch['logo'] ?? null,
                'category'   => $ch['group'] ?? 'imported',
                'protocol'   => strtolower(parse_url($ch['url'], PHP_URL_SCHEME) ?? 'hls'),
                'is_active'  => 1,
                'sort_order' => $imported,
            ]);
            $imported++;
            if ($imported >= 500) break; // max
        }

        Response::success(['imported' => $imported], "$imported کانال ایمپورت شد");
    }

    // ─── M3U Parser ─────────────────────────────────────────────
    private function parseM3U(string $content): array
    {
        $lines    = explode("\n", str_replace("\r", "", $content));
        $channels = [];
        $current  = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#EXTINF:')) {
                $current = ['name'=>'', 'url'=>'', 'logo'=>'', 'group'=>''];
                // name
                if (preg_match('/,(.+)$/', $line, $m)) $current['name'] = trim($m[1]);
                // logo
                if (preg_match('/tvg-logo="([^"]+)"/', $line, $m)) $current['logo'] = $m[1];
                // group
                if (preg_match('/group-title="([^"]+)"/', $line, $m)) $current['group'] = $m[1];
            } elseif ($line && !str_starts_with($line, '#') && $current) {
                $current['url'] = $line;
                $channels[] = $current;
                $current = [];
            }
        }
        return $channels;
    }

    private function findFFmpeg(): ?string
    {
        foreach (['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'ffmpeg'] as $p) {
            if (is_executable($p) || (shell_exec("which $p 2>/dev/null"))) return $p;
        }
        return null;
    }
}
