<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Response};

/**
 * تلویزیون آزمایشی IPTV: آدرس بدهید، همان‌جا پخش کنید و مشخصات استریم را
 * ببینید (کدک، رزولوشن)، بعد اگر خوب بود کانالش کنید.
 *
 * بخش مرورگر پخش را امتحان می‌کند؛ بخش سرور با ffprobe می‌گوید استریم اصلا
 * باز می‌شود و چه کدکی دارد (مثلا H.265 که مرورگر/بعضی تلویزیون‌ها نمی‌پخشند
 * ولی سرور درست می‌بیند). چون مرورگر UDP/RTSP نمی‌پخشد، آزمایش سرور تنها
 * راه امتحان آن‌هاست.
 */
class IptvTestController extends Controller
{
    private const SCHEMES = ['http', 'https', 'rtsp', 'rtmp', 'udp', 'rtp'];

    /** GET /admin/iptv/test */
    public function index(Request $req): void
    {
        $this->view('admin.iptv.test', ['title' => 'تلویزیون آزمایشی']);
    }

    /** POST /admin/iptv/test/probe  url=... */
    public function probe(Request $req): void
    {
        // ffprobe آدرس دلخواه را از روی سرور باز می‌کند؛ فقط نقش فنی
        if (!in_array(\App\Core\Auth::user()['role'] ?? '', ['super_admin', 'admin'], true)) {
            Response::error('اجازه ندارید', 403);
            return;
        }
        $url = trim((string)$req->input('url', ''));
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if ($url === '' || !in_array($scheme, self::SCHEMES, true)) {
            Response::error('آدرس باید با ' . implode('، ', self::SCHEMES) . ' شروع شود', 422);
            return;
        }

        $bin = $this->ffprobe();
        if ($bin === '') {
            Response::success(['available' => false], 'ffprobe روی سرور نیست؛ فقط آزمایش مرورگر ممکن است');
            return;
        }

        $cmd = [$bin, '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams'];
        if (in_array($scheme, ['http', 'https', 'rtsp'], true)) { $cmd[] = '-rw_timeout'; $cmd[] = '8000000'; }
        if ($scheme === 'udp' || $scheme === 'rtp') { $cmd[] = '-timeout'; $cmd[] = '8000000'; }
        $cmd[] = $url;

        $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) { Response::error('اجرای ffprobe ممکن نشد', 500); return; }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $out = $err = '';
        $deadline = microtime(true) + 18;
        while (microtime(true) < $deadline) {
            $out .= (string)stream_get_contents($pipes[1]);
            $err .= (string)stream_get_contents($pipes[2]);
            if (!(proc_get_status($proc)['running'] ?? false)) break;
            usleep(100_000);
        }
        $running = proc_get_status($proc)['running'] ?? false;
        if ($running) { proc_terminate($proc, 9); }
        $out .= (string)stream_get_contents($pipes[1]);
        $err .= (string)stream_get_contents($pipes[2]);
        foreach ($pipes as $p) { fclose($p); }
        proc_close($proc);

        if ($running) { Response::error('زمان آزمایش تمام شد — استریم جواب نداد', 504); return; }
        $j = json_decode($out, true);
        if (!is_array($j) || empty($j['streams'])) {
            $why = trim(substr($err, 0, 300));
            Response::error('استریم باز نشد: ' . ($why !== '' ? $why : 'بدون جواب'), 422);
            return;
        }

        $streams = [];
        foreach ($j['streams'] as $s) {
            $streams[] = [
                'type'    => (string)($s['codec_type'] ?? ''),
                'codec'   => (string)($s['codec_name'] ?? ''),
                'width'   => (int)($s['width'] ?? 0),
                'height'  => (int)($s['height'] ?? 0),
                'fps'     => (string)($s['r_frame_rate'] ?? ''),
                'bitrate' => (int)($s['bit_rate'] ?? 0),
            ];
        }
        Response::success([
            'available' => true,
            'format'    => (string)($j['format']['format_name'] ?? ''),
            'streams'   => $streams,
        ], 'استریم باز شد');
    }

    private function ffprobe(): string
    {
        foreach (['/usr/bin/ffprobe', '/usr/local/bin/ffprobe'] as $p) {
            if (is_executable($p)) return $p;
        }
        return '';
    }
}
