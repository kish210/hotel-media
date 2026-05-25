<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth, Response};

/**
 * RTSP/RTMP → HLS Transcoder Manager
 * مدیریت تبدیل استریم‌های زنده به HLS برای مرورگر
 */
class TranscoderController extends Controller
{
    private string $hlsDir    = '/tmp/signage_hls';
    private string $ffmpegBin = '';

    public function __construct()
    {
        parent::__construct();
        // پیدا کردن FFmpeg
        foreach (['/usr/bin/ffmpeg','/usr/local/bin/ffmpeg'] as $p) {
            if (is_executable($p)) { $this->ffmpegBin = $p; break; }
        }
        if (!$this->ffmpegBin) {
            $found = shell_exec('which ffmpeg 2>/dev/null');
            $this->ffmpegBin = trim($found ?: '');
        }
        if (!is_dir($this->hlsDir)) mkdir($this->hlsDir, 0777, true);
    }

    /** GET /admin/transcoder */
    public function index(Request $req): void
    {
        $ffmpegVersion = '';
        $ffmpegOk      = !empty($this->ffmpegBin);

        if ($ffmpegOk) {
            $out = shell_exec($this->ffmpegBin . ' -version 2>&1');
            preg_match('/ffmpeg version ([^\s]+)/', $out ?? '', $m);
            $ffmpegVersion = $m[1] ?? 'نامشخص';
        }

        $sessions  = $this->getActiveSessions();
        $tid       = Auth::tenantId();
        $channels  = [];
        try {
            $channels = $this->db->rows(
                "SELECT * FROM iptv_channels WHERE tenant_id=? AND is_active=1 AND protocol IN ('rtsp','rtmp','rtp','udp') ORDER BY name ASC",
                [$tid]
            );
        } catch (\Throwable $e) {}

        $this->view('admin.transcoder.index', [
            'title'          => 'Transcoder — RTSP/RTMP به HLS',
            'ffmpegOk'       => $ffmpegOk,
            'ffmpegVersion'  => $ffmpegVersion,
            'ffmpegBin'      => $this->ffmpegBin,
            'sessions'       => $sessions,
            'channels'       => $channels,
            'hlsDir'         => $this->hlsDir,
        ]);
    }

    /** POST /admin/transcoder/start */
    public function start(Request $req): void
    {
        if (!$this->ffmpegBin) {
            $this->flash('error', 'FFmpeg نصب نیست — docker compose up -d --build را اجرا کنید');
            $this->redirect('/admin/transcoder');
            return;
        }

        $inputUrl   = trim($req->post('input_url', ''));
        $streamName = preg_replace('/[^a-z0-9_\-]/', '', strtolower($req->post('stream_name', 'stream' . time())));
        $quality    = $req->post('quality', 'medium');
        $audioMode  = $req->post('audio', 'include');

        if (!$inputUrl) {
            $this->flash('error', 'آدرس استریم الزامی است');
            $this->redirect('/admin/transcoder');
            return;
        }

        // تنظیمات کیفیت
        $qualityMap = [
            'low'    => ['-vf','scale=854:480','-b:v','800k','-maxrate','1000k','-bufsize','2000k'],
            'medium' => ['-vf','scale=1280:720','-b:v','2500k','-maxrate','3000k','-bufsize','6000k'],
            'high'   => ['-vf','scale=1920:1080','-b:v','5000k','-maxrate','6000k','-bufsize','12000k'],
            'copy'   => ['-c:v','copy'],
        ];
        $qArgs = $qualityMap[$quality] ?? $qualityMap['medium'];

        $outDir = $this->hlsDir . '/' . $streamName;
        if (!is_dir($outDir)) mkdir($outDir, 0777, true);
        $m3u8 = $outDir . '/index.m3u8';

        // build FFmpeg command
        $audioArgs = $audioMode === 'mute' ? ['-an'] : ['-c:a', 'aac', '-b:a', '128k', '-ar', '44100'];

        $cmd = array_merge(
            [$this->ffmpegBin,
             '-loglevel', 'error',
             '-rtsp_transport', 'tcp',
             '-i', $inputUrl,
             '-c:v', 'libx264',
             '-preset', 'ultrafast',
             '-tune', 'zerolatency',
             '-g', '30',
             '-sc_threshold', '0'],
            $qArgs,
            $audioArgs,
            ['-f', 'hls',
             '-hls_time', '2',
             '-hls_list_size', '10',
             '-hls_flags', 'delete_segments+append_list',
             '-hls_segment_filename', $outDir . '/seg%04d.ts',
             $m3u8]
        );

        // اجرا در background
        $logFile = $outDir . '/ffmpeg.log';
        $pidFile = $outDir . '/ffmpeg.pid';
        $cmdStr  = implode(' ', array_map('escapeshellarg', $cmd));
        $fullCmd = "nohup $cmdStr > " . escapeshellarg($logFile) . " 2>&1 & echo \$!";
        $pid     = (int)shell_exec($fullCmd);

        if ($pid > 0) {
            file_put_contents($pidFile, $pid);
            // ذخیره session
            $sessions = $this->getActiveSessions();
            $sessions[$streamName] = [
                'name'       => $streamName,
                'input'      => $inputUrl,
                'quality'    => $quality,
                'pid'        => $pid,
                'started_at' => date('Y-m-d H:i:s'),
                'm3u8'       => '/hls/' . $streamName . '/index.m3u8',
                'log'        => $logFile,
            ];
            $this->saveSessions($sessions);
            $this->flash('success', "استریم «$streamName» شروع شد — PID: $pid");
        } else {
            $this->flash('error', 'FFmpeg اجرا نشد — لاگ را بررسی کنید');
        }

        $this->redirect('/admin/transcoder');
    }

    /** POST /admin/transcoder/stop/{name} */
    public function stop(Request $req, array $params): void
    {
        $name     = preg_replace('/[^a-z0-9_\-]/', '', $params['name'] ?? '');
        $sessions = $this->getActiveSessions();

        if (isset($sessions[$name])) {
            $pid = (int)$sessions[$name]['pid'];
            if ($pid > 0) shell_exec("kill $pid 2>/dev/null");
            // پاک کردن فایل‌ها
            $dir = $this->hlsDir . '/' . $name;
            if (is_dir($dir)) shell_exec("rm -rf " . escapeshellarg($dir));
            unset($sessions[$name]);
            $this->saveSessions($sessions);
            $this->flash('success', "استریم «$name» متوقف شد");
        }
        $this->redirect('/admin/transcoder');
    }

    /** GET /admin/transcoder/log/{name} */
    public function streamLog(Request $req, array $params): void
    {
        $name = preg_replace('/[^a-z0-9_\-]/', '', $params['name'] ?? '');
        $log  = $this->hlsDir . '/' . $name . '/ffmpeg.log';
        header('Content-Type: text/plain; charset=utf-8');
        echo file_exists($log) ? file_get_contents($log) : 'لاگی وجود ندارد';
        exit;
    }

    /** GET /hls/{name}/index.m3u8 — serve HLS */
    public function serveHls(Request $req, array $params): void
    {
        $name = preg_replace('/[^a-z0-9_\-]/', '', $params['name'] ?? '');
        $file = $params['file'] ?? 'index.m3u8';
        $file = basename($file);

        $path = $this->hlsDir . '/' . $name . '/' . $file;
        if (!file_exists($path)) { http_response_code(404); echo 'Not found'; exit; }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mime = $ext === 'm3u8' ? 'application/vnd.apple.mpegurl' : 'video/MP2T';
        header("Content-Type: $mime");
        header("Cache-Control: no-cache");
        header("Access-Control-Allow-Origin: *");
        readfile($path);
        exit;
    }

    // ─── Session helpers ────────────────────────────────────────
    private function getActiveSessions(): array
    {
        $file = $this->hlsDir . '/sessions.json';
        if (!file_exists($file)) return [];
        $sessions = json_decode(file_get_contents($file), true) ?: [];
        // بررسی اینکه PIDs هنوز در حال اجرا هستند
        foreach ($sessions as $name => &$session) {
            $pid = (int)($session['pid'] ?? 0);
            if ($pid > 0 && !file_exists("/proc/$pid")) {
                unset($sessions[$name]);
            }
        }
        return $sessions;
    }

    private function saveSessions(array $sessions): void
    {
        file_put_contents(
            $this->hlsDir . '/sessions.json',
            json_encode($sessions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
