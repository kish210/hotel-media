<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Camera Service — دوربین مداربسته روی تلویزیون.
 *
 * دوربین RTSP می‌دهد و تلویزیون هتلی RTSP نمی‌خواند، پس ffmpeg رله
 * می‌کند به HLS. همان قرارداد ماژول ترنسکد استفاده می‌شود تا دو مسیر
 * موازی و ناهمگون نداشته باشیم: خروجی در /tmp/signage_hls/{stream}
 * و سرو از مسیر /hls/{stream}/index.m3u8.
 *
 * چرا ffmpeg با nohup و نه یک daemon: رله باید بعد از پایان درخواست
 * PHP زنده بماند. pid در فایل نگه داشته می‌شود تا stop و status بدانند
 * با چه چیزی طرف‌اند.
 *
 * فاز ۱۰ نقشه‌راه — docs/TODO.md (۵.۷)
 */
class CameraService
{
    /** اگر m3u8 از این قدیمی‌تر باشد، رله عملا مرده است */
    private const STALE_SECONDS = 30;

    private Database $db;
    private string $hlsDir = '/tmp/signage_hls';
    private string $ffmpeg = '';

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();

        /* is_executable اینجا کار نمی‌کند: pool مقدار open_basedir دارد و
           هر بررسی فایل‌سیستمی بیرون از آن از سمت وب false می‌دهد، هرچند
           باینری با shell اجرا می‌شود. تشخیص مشترک در MediaConvertService. */
        $this->ffmpeg = MediaConvertService::locate('ffmpeg');
        if (!is_dir($this->hlsDir)) @mkdir($this->hlsDir, 0777, true);
    }

    public function ffmpegAvailable(): bool { return $this->ffmpeg !== ''; }

    /** اسلاگ امن برای مسیر HLS — هرچه غیر از این باشد مسیر را می‌شکند */
    public static function slug(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9_\-]/', '', $s) ?? '';
        return $s !== '' ? mb_substr($s, 0, 60) : ('cam' . time());
    }

    // ── CRUD ─────────────────────────────────────────────────────────

    public function all(int $tenantId): array
    {
        return $this->db->rows(
            "SELECT * FROM cameras WHERE tenant_id=? ORDER BY sort_order, name",
            [$tenantId]
        );
    }

    public function find(int $tenantId, int $id): ?array
    {
        return $this->db->row("SELECT * FROM cameras WHERE id=? AND tenant_id=?", [$id, $tenantId]);
    }

    /** @return array{ok:bool,message:string,id?:int} */
    public function create(int $tenantId, array $d): array
    {
        $name = trim((string)($d['name'] ?? ''));
        $rtsp = trim((string)($d['rtsp_url'] ?? ''));
        if ($name === '') return ['ok' => false, 'message' => 'نام دوربین لازم است'];
        if (!preg_match('#^rtsp(s)?://#i', $rtsp) && !preg_match('#^https?://#i', $rtsp)) {
            return ['ok' => false, 'message' => 'آدرس باید با rtsp:// یا http:// شروع شود'];
        }

        $slug = self::slug((string)($d['stream_name'] ?? $name));
        // یکتا بودن اسلاگ در همین tenant
        $i = 0; $base = $slug;
        while ($this->db->exists('cameras', ['tenant_id' => $tenantId, 'stream_name' => $slug])) {
            $slug = $base . '-' . (++$i);
        }

        $id = $this->db->insert('cameras', [
            'tenant_id'     => $tenantId,
            'name'          => mb_substr($name, 0, 120),
            'location'      => $this->clean($d['location'] ?? null, 120),
            'rtsp_url'      => mb_substr($rtsp, 0, 500),
            'stream_name'   => $slug,
            'quality'       => in_array($d['quality'] ?? '', ['low','medium','high','copy'], true) ? $d['quality'] : 'medium',
            'audio'         => ($d['audio'] ?? 'mute') === 'include' ? 'include' : 'mute',
            'guest_visible' => !empty($d['guest_visible']) ? 1 : 0,
            'access_level'  => max(0, (int)($d['access_level'] ?? 0)),
            'is_active'     => 1,
            'sort_order'    => max(0, (int)($d['sort_order'] ?? 0)),
        ]);

        return ['ok' => true, 'message' => 'دوربین اضافه شد', 'id' => (int)$id];
    }

    public function update(int $tenantId, int $id, array $d): array
    {
        $cam = $this->find($tenantId, $id);
        if (!$cam) return ['ok' => false, 'message' => 'دوربین یافت نشد'];

        $set = [];
        if (isset($d['name']) && trim((string)$d['name']) !== '') $set['name'] = mb_substr(trim((string)$d['name']), 0, 120);
        if (isset($d['location']))     $set['location'] = $this->clean($d['location'], 120);
        if (isset($d['rtsp_url']) && trim((string)$d['rtsp_url']) !== '') {
            $rtsp = trim((string)$d['rtsp_url']);
            if (!preg_match('#^rtsp(s)?://#i', $rtsp) && !preg_match('#^https?://#i', $rtsp)) {
                return ['ok' => false, 'message' => 'آدرس باید با rtsp:// یا http:// شروع شود'];
            }
            $set['rtsp_url'] = mb_substr($rtsp, 0, 500);
        }
        if (isset($d['quality']) && in_array($d['quality'], ['low','medium','high','copy'], true)) $set['quality'] = $d['quality'];
        if (isset($d['audio']))         $set['audio'] = $d['audio'] === 'include' ? 'include' : 'mute';
        if (isset($d['guest_visible'])) $set['guest_visible'] = !empty($d['guest_visible']) ? 1 : 0;
        if (isset($d['access_level']))  $set['access_level'] = max(0, (int)$d['access_level']);
        if (isset($d['is_active']))     $set['is_active'] = !empty($d['is_active']) ? 1 : 0;
        if (isset($d['sort_order']))    $set['sort_order'] = max(0, (int)$d['sort_order']);

        if ($set) $this->db->update('cameras', $set, ['id' => $id, 'tenant_id' => $tenantId]);
        return ['ok' => true, 'message' => 'ذخیره شد'];
    }

    public function delete(int $tenantId, int $id): array
    {
        $cam = $this->find($tenantId, $id);
        if (!$cam) return ['ok' => false, 'message' => 'دوربین یافت نشد'];
        $this->stop($cam);
        $this->db->delete('cameras', ['id' => $id, 'tenant_id' => $tenantId]);
        return ['ok' => true, 'message' => 'دوربین حذف شد'];
    }

    // ── رله ──────────────────────────────────────────────────────────

    public function hlsUrl(array $cam): string
    {
        return '/hls/' . $cam['stream_name'] . '/index.m3u8';
    }

    private function dir(array $cam): string  { return $this->hlsDir . '/' . $cam['stream_name']; }
    private function pidFile(array $cam): string { return $this->dir($cam) . '/ffmpeg.pid'; }

    /**
     * وضعیت رله.
     * @return array{running:bool,pid:int,fresh:bool,age:int}
     */
    public function status(array $cam): array
    {
        $pid  = 0;
        $pf   = $this->pidFile($cam);
        if (is_file($pf)) $pid = (int)trim((string)@file_get_contents($pf));

        /* نه is_dir('/proc/…'): در production، open_basedir پول PHP-FPM
           دسترسی به /proc نمی‌دهد و آن بررسی همیشه «مرده» می‌گفت — هر
           «شروع» رله‌ی سالم را می‌کشت و از نو می‌ساخت. ps محدودیتی ندارد و
           خط فرمان را هم می‌دهد تا pid بازیافته با رله اشتباه نشود. */
        $alive = $pid > 0 && str_contains(
            (string)shell_exec('ps -o args= -p ' . $pid . ' 2>/dev/null'),
            '/' . $cam['stream_name'] . '/'
        );

        $m3u8 = $this->dir($cam) . '/index.m3u8';
        $age  = is_file($m3u8) ? (time() - (int)filemtime($m3u8)) : -1;
        $fresh = $age >= 0 && $age <= self::STALE_SECONDS;

        return ['running' => $alive, 'pid' => $pid, 'fresh' => $fresh, 'age' => $age];
    }

    /** @return array{ok:bool,message:string,pid?:int} */
    public function start(array $cam): array
    {
        if (!$this->ffmpegAvailable()) {
            return ['ok' => false, 'message' => 'ffmpeg روی سرور نصب نیست'];
        }

        $st = $this->status($cam);
        if ($st['running'] && $st['fresh']) {
            return ['ok' => true, 'message' => 'از قبل در حال پخش است', 'pid' => $st['pid']];
        }
        // فرایند مرده یا خروجی کهنه — اول تمیز کن
        $this->stop($cam);

        $outDir = $this->dir($cam);
        if (!is_dir($outDir)) @mkdir($outDir, 0777, true);

        $qMap = [
            'low'    => ['-vf','scale=854:480',  '-b:v','800k', '-maxrate','1000k','-bufsize','2000k'],
            'medium' => ['-vf','scale=1280:720', '-b:v','2500k','-maxrate','3000k','-bufsize','6000k'],
            'high'   => ['-vf','scale=1920:1080','-b:v','5000k','-maxrate','6000k','-bufsize','12000k'],
            'copy'   => ['-c:v','copy'],
        ];
        $qArgs = $qMap[$cam['quality']] ?? $qMap['medium'];
        $aArgs = ($cam['audio'] ?? 'mute') === 'include'
            ? ['-c:a','aac','-b:a','128k','-ar','44100']
            : ['-an'];

        $pre     = ['-loglevel','error'];
        $isRtsp  = (bool)preg_match('#^rtsp(s)?://#i', (string)$cam['rtsp_url']);
        if ($isRtsp) {
            $pre[] = '-rtsp_transport'; $pre[] = 'tcp';
            /* دوربین گاهی جواب نمی‌دهد؛ بدون timeout ffmpeg تا ابد منتظر
               می‌ماند و مهمان صفحه‌ی خالی می‌بیند. نام گزینه بین نسخه‌ها
               عوض شده (ffmpeg ۶ گزینه‌ی stimeout را حذف کرد)، پس از خودِ
               ffmpeg می‌پرسیم — وگرنه با «Unrecognized option» فوری می‌میرد. */
            $opt = $this->rtspTimeoutOption();
            if ($opt !== '') { $pre[] = $opt; $pre[] = '5000000'; }
        }

        $m3u8 = $outDir . '/index.m3u8';
        $cmd  = array_merge(
            [$this->ffmpeg], $pre,
            ['-i', (string)$cam['rtsp_url']],
            ($cam['quality'] === 'copy' ? [] : ['-c:v','libx264','-preset','ultrafast','-tune','zerolatency','-g','30','-sc_threshold','0']),
            $qArgs, $aArgs,
            ['-f','hls',
             '-hls_time','2',
             '-hls_list_size','10',
             '-hls_flags','delete_segments+append_list',
             '-hls_segment_filename', $outDir . '/seg%04d.ts',
             $m3u8]
        );

        $log     = $outDir . '/ffmpeg.log';
        $cmdStr  = implode(' ', array_map('escapeshellarg', $cmd));
        $pid     = (int)shell_exec('nohup ' . $cmdStr . ' > ' . escapeshellarg($log) . ' 2>&1 & echo $!');

        if ($pid <= 0) return ['ok' => false, 'message' => 'ffmpeg اجرا نشد — لاگ را ببینید'];

        @file_put_contents($this->pidFile($cam), (string)$pid);
        $this->db->update('cameras', ['last_started_at' => date('Y-m-d H:i:s')], ['id' => (int)$cam['id']]);

        return ['ok' => true, 'message' => 'پخش دوربین شروع شد', 'pid' => $pid];
    }

    public function stop(array $cam): array
    {
        $pf = $this->pidFile($cam);
        if (is_file($pf)) {
            $pid = (int)trim((string)@file_get_contents($pf));
            if ($pid > 0) @shell_exec('kill ' . $pid . ' 2>/dev/null');
            @unlink($pf);
        }
        $dir = $this->dir($cam);
        // فقط قطعه‌ها و پلی‌لیست پاک می‌شوند، نه لاگ — برای عیب‌یابی لازم است
        foreach ((array)glob($dir . '/*.ts') as $f) @unlink((string)$f);
        @unlink($dir . '/index.m3u8');

        return ['ok' => true, 'message' => 'پخش دوربین متوقف شد'];
    }

    /**
     * نام گزینه‌ی timeout سوکت برای demuxer rtsp در همین نسخه‌ی ffmpeg.
     * ffmpeg ۴ آن را stimeout می‌نامید؛ از ۵ به بعد timeout است و
     * stimeout حذف شده. اگر هیچ‌کدام نبود رشته‌ی خالی برمی‌گردد و
     * گزینه‌ای اضافه نمی‌شود.
     */
    private function rtspTimeoutOption(): string
    {
        static $opt = null;
        if ($opt !== null) return $opt;

        $opt = '';
        if ($this->ffmpeg === '') return $opt;

        $help = (string)shell_exec(
            escapeshellarg($this->ffmpeg) . ' -hide_banner -h demuxer=rtsp 2>&1'
        );
        if (preg_match('/^\s*-stimeout\s/m', $help))     $opt = '-stimeout';
        elseif (preg_match('/^\s*-timeout\s/m', $help))  $opt = '-timeout';

        return $opt;
    }

    /** آخرین خطوط لاگ ffmpeg — برای عیب‌یابی در پنل */
    public function log(array $cam, int $lines = 40): string
    {
        $f = $this->dir($cam) . '/ffmpeg.log';
        if (!is_file($f)) return '';
        $all = preg_split('/\r?\n/', (string)@file_get_contents($f)) ?: [];
        $all = array_values(array_filter($all, static fn($l) => trim($l) !== ''));
        return implode("\n", array_slice($all, -$lines));
    }

    // ── سمت مهمان ────────────────────────────────────────────────────

    /**
     * دوربین‌هایی که این اتاق اجازه‌ی دیدنشان را دارد.
     * فقط نام و آدرس HLS بیرون می‌رود — rtsp_url هرگز.
     */
    public function forGuest(int $tenantId, int $roomAccessLevel = 0): array
    {
        $rows = $this->db->rows(
            "SELECT id, name, location, stream_name, access_level
               FROM cameras
              WHERE tenant_id=? AND is_active=1 AND guest_visible=1 AND access_level <= ?
              ORDER BY sort_order, name",
            [$tenantId, $roomAccessLevel]
        );

        $out = [];
        foreach ($rows as $r) {
            $st = $this->status($r);
            $out[] = [
                'id'       => (int)$r['id'],
                'name'     => (string)$r['name'],
                'location' => (string)($r['location'] ?? ''),
                'hls'      => $this->hlsUrl($r),
                'live'     => $st['running'] && $st['fresh'],
            ];
        }
        return $out;
    }

    private function clean(mixed $v, int $len): ?string
    {
        $v = trim((string)($v ?? ''));
        return $v === '' ? null : mb_substr($v, 0, $len);
    }
}
