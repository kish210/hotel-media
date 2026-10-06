<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * ترنسکدر — کارها، اجرا و نظارت. ساخت خط فرمان در TranscoderCommand.
 *
 * ── چرا این شکل ────────────────────────────────────────────────────
 *
 * هر کار یک فرایند ffmpeg جدا است (با setsid، مستقل از PHP-FPM). یک
 * کانال خراب کانال دیگر را نمی‌اندازد — همان «مصونیت از تداخل بین
 * استریم‌ها» در مشخصات رامند.
 *
 * وضعیتِ خواسته‌شده (desired) در دیتابیس است، نه در فایل موقت. ناظر
 * (php artisan transcoder:supervise، هر دقیقه از cron، هر ۵ ثانیه
 * بررسی) هر کانالی را که باید روشن باشد و نیست برمی‌گرداند؛ بعد از
 * قطع برق هتل هم. کانالی که ffmpeg اش زنده است ولی دیگر چیزی نمی‌نویسد
 * (ورودی ماهواره قطع شده و ffmpeg منتظر مانده) «گیر کرده» حساب می‌شود
 * و از نو راه می‌افتد — بدون این، مهمان تصویر ثابت می‌بیند و پنل
 * «در حال اجرا» نشان می‌دهد.
 *
 * زنده بودن فرایند با `kill -0` و `ps` سنجیده می‌شود نه با /proc: در
 * production، مقدار open_basedir پول PHP-FPM دسترسی به /proc را
 * نمی‌دهد و هر بررسی آنجا «مرده» می‌گفت. ps خط فرمان را هم برمی‌گرداند
 * تا pid بازیافته‌ی یک فرایند دیگر با ffmpeg ما اشتباه نشود.
 */
final class TranscoderService
{
    /** بعد از این مدت بی‌خبری از ffmpeg، کانال گیرکرده است */
    public const STALL_SECONDS = 30;

    /** پوشه‌ی کاری زنده — همان که /hls/{name}/{file} سرو می‌کند */
    public const LIVE_ROOT = '/tmp/signage_hls';

    private Database $db;
    private string $ffmpeg;
    private string $ffprobe;

    public function __construct(?Database $db = null)
    {
        $this->db      = $db ?? Database::getInstance();
        $this->ffmpeg  = MediaConvertService::locate('ffmpeg');
        $this->ffprobe = MediaConvertService::locate('ffprobe');
    }

    public function available(): bool { return $this->ffmpeg !== ''; }

    // ══════════════════════════════════════════════════════════════
    //  مسیرها
    // ══════════════════════════════════════════════════════════════

    public static function slug(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9_\-]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        return $s !== '' ? substr($s, 0, 50) : 'job' . time();
    }

    /** پوشه‌ی لاگ و پیشرفت — همیشه خصوصی */
    public function workDir(array $job): string { return self::LIVE_ROOT . '/tc-' . $job['slug']; }

    /** پوشه‌ی خروجی HLS */
    public function outDir(array $job): string
    {
        return $job['mode'] === 'vod'
            ? PUBLIC_PATH . '/uploads/transcoded/' . $job['slug']
            : $this->workDir($job);
    }

    /** آدرس پخش HLS برای تلویزیون و پنل */
    public function hlsUrl(array $job): string
    {
        return $job['mode'] === 'vod'
            ? '/uploads/transcoded/' . $job['slug'] . '/index.m3u8'
            : '/hls/tc-' . $job['slug'] . '/index.m3u8';
    }

    // ══════════════════════════════════════════════════════════════
    //  CRUD
    // ══════════════════════════════════════════════════════════════

    public function all(int $tenantId): array
    {
        $rows = $this->db->rows('SELECT * FROM transcoder_jobs WHERE tenant_id = ? ORDER BY mode, name', [$tenantId]);
        foreach ($rows as &$r) $r['settings'] = json_decode((string)$r['settings'], true) ?: [];
        return $rows;
    }

    public function find(int $tenantId, int $id): ?array
    {
        $r = $this->db->row('SELECT * FROM transcoder_jobs WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
        if (!$r) return null;
        $r['settings'] = json_decode((string)$r['settings'], true) ?: [];
        return $r;
    }

    /**
     * اعتبارسنجی و ذخیره. با $id به‌روزرسانی است.
     * @return array{ok:bool,message:string,id?:int,restart?:bool}
     */
    public function save(int $tenantId, array $d, ?int $id = null): array
    {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') return ['ok' => false, 'message' => 'نام کار الزامی است'];

        $mode = ($d['mode'] ?? 'live') === 'vod' ? 'vod' : 'live';
        $kind = in_array($d['input_kind'] ?? '', ['url', 'file', 'v4l2', 'decklink'], true) ? $d['input_kind'] : 'url';
        $url  = trim((string)($d['input_url'] ?? ''));

        $in = $this->validateInput($kind, $url, $mode);
        if ($in !== null) return ['ok' => false, 'message' => $in];

        $norm = TranscoderCommand::normalize((array)($d['settings'] ?? []), $mode, $kind);
        if (!$norm['ok']) return ['ok' => false, 'message' => $norm['message']];

        $slug = self::slug((string)($d['slug'] ?? '') ?: $name);
        $dup  = $this->db->value(
            'SELECT id FROM transcoder_jobs WHERE tenant_id = ? AND slug = ?' . ($id ? ' AND id <> ?' : ''),
            $id ? [$tenantId, $slug, $id] : [$tenantId, $slug]
        );
        if ($dup) return ['ok' => false, 'message' => "نام مسیر «{$slug}» تکراری است"];

        $row = [
            'name'       => mb_substr($name, 0, 120),
            'slug'       => $slug,
            'mode'       => $mode,
            'input_kind' => $kind,
            'input_url'  => $url,
            'settings'   => json_encode($norm['settings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        if ($id) {
            $old = $this->find($tenantId, $id);
            if (!$old) return ['ok' => false, 'message' => 'کار یافت نشد'];
            if ($old['slug'] !== $slug && $this->alive($old)) {
                return ['ok' => false, 'message' => 'برای تغییر نام مسیر، اول کار را متوقف کنید'];
            }
            $this->db->update('transcoder_jobs', $row, ['id' => $id, 'tenant_id' => $tenantId]);
            /* تنظیمات تازه فقط با اجرای دوباره‌ی ffmpeg اعمال می‌شود */
            $restart = $old['desired'] === 'running' && $this->alive($old);
            if ($restart) $this->spawn($this->find($tenantId, $id));
            return ['ok' => true, 'message' => $restart ? 'ذخیره شد و کار با تنظیمات تازه دوباره شروع شد' : 'ذخیره شد',
                    'id' => $id, 'restart' => $restart];
        }

        $row['tenant_id'] = $tenantId;
        $newId = (int)$this->db->insert('transcoder_jobs', $row);
        return ['ok' => true, 'message' => 'کار ساخته شد', 'id' => $newId];
    }

    public function delete(int $tenantId, int $id): array
    {
        $job = $this->find($tenantId, $id);
        if (!$job) return ['ok' => false, 'message' => 'کار یافت نشد'];
        $this->kill($job);
        $this->clean($job, true);
        $this->db->query('DELETE FROM transcoder_jobs WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
        return ['ok' => true, 'message' => 'کار حذف شد'];
    }

    /** ورودی‌ای که ffmpeg را به فایل دلخواه یا حلقه‌ی بی‌پایان ببرد رد می‌شود */
    private function validateInput(string $kind, string $url, string $mode): ?string
    {
        if ($url === '') return 'ورودی الزامی است';
        if (strlen($url) > 1000) return 'آدرس ورودی بیش از حد بلند است';

        switch ($kind) {
            case 'url':
                if ($mode === 'vod') return 'کار فایل (VOD) ورودی فایل می‌خواهد';
                if (!preg_match('#^(udp|rtp|rtsps?|rtmps?|https?|srt)://[^\s\'"]+$#i', $url)) {
                    return 'پروتکل ورودی پشتیبانی نمی‌شود — udp، rtp، rtsp، rtmp، http(s) (شامل HLS و DASH) یا srt';
                }
                /* خروجی خود ترنسکدر به‌عنوان ورودی = حلقه‌ای که CPU را می‌سوزاند */
                if (preg_match('#/hls/tc-|/uploads/transcoded/#', $url)) return 'ورودی نمی‌تواند خروجی خود ترنسکدر باشد';
                return null;
            case 'file':
                if (!preg_match('#^/uploads/[A-Za-z0-9_./\-]+$#', $url) || str_contains($url, '..')) {
                    return 'فایل باید از کتابخانه‌ی رسانه‌ی سامانه باشد (/uploads/…)';
                }
                if (str_starts_with($url, '/uploads/transcoded/')) return 'ورودی نمی‌تواند خروجی خود ترنسکدر باشد';
                if (!is_file(PUBLIC_PATH . $url)) return 'فایل روی سرور پیدا نشد';
                return null;
            case 'v4l2':
                if ($mode === 'vod') return 'کارت کپچر ورودی زنده است';
                return preg_match('#^/dev/video\d{1,2}$#', $url) ? null : 'دستگاه کپچر باید به شکل /dev/video0 باشد';
            case 'decklink':
                if ($mode === 'vod') return 'کارت کپچر ورودی زنده است';
                return preg_match('/^[A-Za-z0-9 ()\-]{2,60}$/', $url) ? null : 'نام دستگاه DeckLink نامعتبر است';
        }
        return 'نوع ورودی نامعتبر است';
    }

    // ══════════════════════════════════════════════════════════════
    //  اجرا
    // ══════════════════════════════════════════════════════════════

    /** شروع دستی: شمارنده‌ی راه‌اندازی مجدد صفر می‌شود */
    public function start(int $tenantId, int $id): array
    {
        $job = $this->find($tenantId, $id);
        if (!$job) return ['ok' => false, 'message' => 'کار یافت نشد'];
        if (!$this->available()) return ['ok' => false, 'message' => 'ffmpeg روی سرور نصب نیست'];

        $this->db->update('transcoder_jobs', [
            'desired' => 'running', 'restarts' => 0, 'last_error' => null, 'progress' => null,
        ], ['id' => $id]);
        return $this->spawn($this->find($tenantId, $id));
    }

    public function stop(int $tenantId, int $id): array
    {
        $job = $this->find($tenantId, $id);
        if (!$job) return ['ok' => false, 'message' => 'کار یافت نشد'];
        $this->kill($job);
        if ($job['mode'] === 'live') $this->clean($job, false);
        $this->db->update('transcoder_jobs', ['desired' => 'stopped', 'status' => 'stopped', 'pid' => null], ['id' => $id]);
        return ['ok' => true, 'message' => 'کار متوقف شد'];
    }

    /** اجرای ffmpeg برای یک کار — از شروع دستی و ناظر */
    private function spawn(array $job): array
    {
        $this->kill($job);
        $this->clean($job, $job['mode'] === 'vod');

        $work = $this->workDir($job);
        $out  = $this->outDir($job);
        foreach ([$work, $out] as $d) if (!is_dir($d)) @mkdir($d, 0775, true);

        $logo = '';
        $img  = (string)($job['settings']['overlay']['image'] ?? '');
        if ($img !== '' && is_file(PUBLIC_PATH . $img)) $logo = PUBLIC_PATH . $img;

        $input = $job;
        if ($job['input_kind'] === 'file') {
            $input['input_url'] = PUBLIC_PATH . $job['input_url'];
            if ($job['mode'] === 'vod') {
                /* طول فایل برای درصد پیشرفت */
                @file_put_contents($work . '/duration.txt', (string)$this->duration($input['input_url']));
            }
        }

        $cmd = TranscoderCommand::build($input, [
            'ffmpeg'           => $this->ffmpeg,
            'out_dir'          => $out,
            'work_dir'         => $work,
            'logo_path'        => $logo,
            'rtsp_timeout_opt' => $this->rtspTimeoutOption(),
        ]);
        @file_put_contents($work . '/cmd.txt', implode(' ', array_map('escapeshellarg', $cmd)));

        $sh  = 'nohup setsid ' . implode(' ', array_map('escapeshellarg', $cmd))
             . ' > ' . escapeshellarg($work . '/ffmpeg.log') . ' 2>&1 & echo $!';
        $pid = (int)trim((string)shell_exec($sh));

        if ($pid <= 0) {
            $this->db->update('transcoder_jobs', ['status' => 'error', 'last_error' => 'ffmpeg اجرا نشد'], ['id' => (int)$job['id']]);
            return ['ok' => false, 'message' => 'ffmpeg اجرا نشد — لاگ را ببینید'];
        }

        $this->db->update('transcoder_jobs', [
            'pid' => $pid, 'status' => 'starting', 'started_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int)$job['id']]);

        return ['ok' => true, 'message' => $job['mode'] === 'vod' ? 'تبدیل فایل شروع شد' : 'کانال شروع شد', 'pid' => $pid];
    }

    /** فرایند این کار زنده است؟ */
    public function alive(array $job): bool
    {
        $pid = (int)($job['pid'] ?? 0);
        if ($pid <= 0) return false;
        $args = (string)shell_exec('ps -o args= -p ' . $pid . ' 2>/dev/null');
        /* pid بازیافته‌ی یک فرایند دیگر نباید با کار ما اشتباه شود */
        return str_contains($args, 'ffmpeg') && str_contains($args, '/' . ($job['mode'] === 'vod' ? '' : 'tc-') . $job['slug']);
    }

    private function kill(array $job): void
    {
        if (!$this->alive($job)) return;
        $pid = (int)$job['pid'];
        /* TERM تا ffmpeg پلی‌لیست را ببندد؛ اگر نبست، KILL */
        @shell_exec('kill -TERM ' . $pid . ' 2>/dev/null');
        for ($i = 0; $i < 20; $i++) {
            usleep(100000);
            if (!$this->alive($job)) return;
        }
        @shell_exec('kill -KILL ' . $pid . ' 2>/dev/null');
    }

    /** پاک‌سازی قطعه‌ها. $all برای VOD و حذف: کل پوشه‌ی خروجی */
    private function clean(array $job, bool $all): void
    {
        $out = $this->outDir($job);
        foreach ((array)glob($out . '/*.{ts,m3u8,tmp}', GLOB_BRACE) as $f) @unlink((string)$f);
        @unlink($this->workDir($job) . '/progress.txt');
        if ($all && $job['mode'] === 'vod') @rmdir($out);
    }

    // ══════════════════════════════════════════════════════════════
    //  ناظر
    // ══════════════════════════════════════════════════════════════

    /**
     * یک دور نظارت روی همه‌ی کارها. خروجی برای لاگ cron.
     * @return list<string>
     */
    public function superviseOnce(?int $now = null): array
    {
        $now  = $now ?? time();
        $log  = [];
        $rows = $this->db->rows(
            "SELECT * FROM transcoder_jobs WHERE desired = 'running' OR status IN ('starting','running')"
        );

        foreach ($rows as $job) {
            $job['settings'] = json_decode((string)$job['settings'], true) ?: [];
            $id    = (int)$job['id'];
            $alive = $this->alive($job);
            $st    = $this->stats($job);
            /* سن بر حسب همان «اکنون» ناظر، تا تست بتواند زمان را جلو ببرد */
            if ($st['age'] >= 0) $st['age'] += $now - time();

            if ($job['desired'] === 'stopped') {
                if ($alive) { $this->kill($job); $log[] = "#$id stopped (not desired)"; }
                $this->db->update('transcoder_jobs', ['status' => 'stopped', 'pid' => null], ['id' => $id]);
                continue;
            }

            // ── فایل: یک بار اجرا، بعد تمام ─────────────────────────
            if ($job['mode'] === 'vod') {
                if ($alive) {
                    $this->db->update('transcoder_jobs', ['status' => 'running', 'progress' => $st['percent']], ['id' => $id]);
                    continue;
                }
                $done = $st['state'] === 'end';
                $this->db->update('transcoder_jobs', [
                    'status'     => $done ? 'finished' : 'error',
                    'desired'    => 'stopped',
                    'pid'        => null,
                    'progress'   => $done ? 100 : $st['percent'],
                    'last_error' => $done ? null : ($this->lastLogLine($job) ?: 'تبدیل ناتمام ماند'),
                ], ['id' => $id]);
                $log[] = "#$id vod " . ($done ? 'finished' : 'failed');
                continue;
            }

            // ── زنده: همیشه روشن ────────────────────────────────────
            $started = strtotime((string)$job['started_at']) ?: 0;
            if ($alive) {
                $stalled = $now - $started > self::STALL_SECONDS
                        && ($st['age'] < 0 || $st['age'] > self::STALL_SECONDS);
                if (!$stalled) {
                    if ($job['status'] !== 'running' && $st['age'] >= 0 && $st['age'] <= self::STALL_SECONDS) {
                        $this->db->update('transcoder_jobs', ['status' => 'running'], ['id' => $id]);
                    }
                    continue;
                }
                $this->kill($job);
                $this->db->update('transcoder_jobs', ['status' => 'error',
                    'last_error' => 'ورودی جواب نمی‌دهد — ' . self::STALL_SECONDS . ' ثانیه بدون تصویر'], ['id' => $id]);
                $job['last_error'] = 'stalled';
                $log[] = "#$id stalled, killed";
            } else {
                $err = $this->lastLogLine($job);
                if ($job['status'] !== 'error' || $err) {
                    $this->db->update('transcoder_jobs', ['status' => 'error', 'last_error' => $err ?: 'ffmpeg متوقف شد'], ['id' => $id]);
                }
            }

            /* فاصله‌ی دوباره‌سازی بزرگ می‌شود (۵، ۱۰، ۲۰، ۴۰، ۶۰ ثانیه) تا
               ورودیِ قطع‌شده سرور را با صدها اجرای بی‌حاصل پر نکند — ولی
               هیچ‌وقت تسلیم نمی‌شود: ماهواره‌ای که برگشت، کانال هم برمی‌گردد. */
            $wait = min(60, 5 * (2 ** min((int)$job['restarts'], 4)));
            $last = strtotime((string)($job['last_restart_at'] ?: $job['started_at'])) ?: 0;
            if ($now - $last < $wait) continue;

            $this->db->update('transcoder_jobs', [
                'restarts' => (int)$job['restarts'] + 1, 'last_restart_at' => date('Y-m-d H:i:s', $now),
            ], ['id' => $id]);
            $res = $this->spawn($job);
            $log[] = "#$id restarted (" . ((int)$job['restarts'] + 1) . '): ' . $res['message'];
        }
        return $log;
    }

    // ══════════════════════════════════════════════════════════════
    //  آمار و عیب‌یابی
    // ══════════════════════════════════════════════════════════════

    /**
     * آخرین گزارش ffmpeg از -progress.
     * @return array{fps:float,bitrate:string,speed:string,out_time:string,drop:int,dup:int,age:int,state:string,percent:?int}
     */
    public function stats(array $job): array
    {
        $out = ['fps' => 0.0, 'bitrate' => '', 'speed' => '', 'out_time' => '', 'drop' => 0, 'dup' => 0,
                'age' => -1, 'state' => '', 'percent' => null];
        $f = $this->workDir($job) . '/progress.txt';
        if (!is_file($f)) return $out;

        clearstatcache(true, $f);
        /* ffmpeg فایل را همان اول خالی می‌سازد، پیش از رسیدن حتی یک فریم.
           فایل خالی یعنی «هنوز خبری نیست»، نه «سالم» — وگرنه ورودیِ
           قطع‌شده از همان لحظه‌ی اول running نشان داده می‌شد. */
        $size = (int)filesize($f);
        if ($size === 0) return $out;
        $out['age'] = time() - (int)filemtime($f);

        $fh   = @fopen($f, 'rb');
        if (!$fh) return $out;
        if ($size > 4096) fseek($fh, -4096, SEEK_END);
        $tail = (string)stream_get_contents($fh);
        fclose($fh);

        $blocks = preg_split('/^progress=(continue|end)\s*$/m', $tail, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        /* [.., body, state, ""] — آخرین بلوک کامل */
        $n = count($blocks);
        if ($n < 3) return $out;
        $body = $blocks[$n - 3];
        $out['state'] = $blocks[$n - 2];

        $kv = [];
        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            if (str_contains($line, '=')) { [$k, $v] = explode('=', $line, 2); $kv[trim($k)] = trim($v); }
        }
        $out['fps']      = (float)($kv['fps'] ?? 0);
        $out['bitrate']  = (string)($kv['bitrate'] ?? '');
        $out['speed']    = (string)($kv['speed'] ?? '');
        $out['out_time'] = substr((string)($kv['out_time'] ?? ''), 0, 8);
        $out['drop']     = (int)($kv['drop_frames'] ?? 0);
        $out['dup']      = (int)($kv['dup_frames'] ?? 0);

        if ($job['mode'] === 'vod') {
            $dur = (float)@file_get_contents($this->workDir($job) . '/duration.txt');
            $us  = (float)($kv['out_time_us'] ?? $kv['out_time_ms'] ?? 0);
            if ($dur > 0 && $us > 0) $out['percent'] = (int)max(0, min(100, floor($us / 1e6 / $dur * 100)));
            if ($out['state'] === 'end') $out['percent'] = 100;
        }
        return $out;
    }

    public function log(array $job, int $lines = 60): string
    {
        $f = $this->workDir($job) . '/ffmpeg.log';
        if (!is_file($f)) return '';
        $all = preg_split('/\r?\n/', (string)@file_get_contents($f)) ?: [];
        $all = array_values(array_filter($all, static fn($l) => trim($l) !== ''));
        return implode("\n", array_slice($all, -$lines));
    }

    private function lastLogLine(array $job): string
    {
        $l = $this->log($job, 3);
        $parts = explode("\n", $l);
        return mb_substr(trim((string)end($parts)), 0, 480);
    }

    /**
     * ورودی را با ffprobe می‌خواند — قبل از ساخت کار، اپراتور می‌بیند
     * چه تصویر، صدا و زیرنویسی (با چه زبانی) در ورودی هست.
     * @return array{ok:bool,message:string,streams?:array,format?:string}
     */
    public function probe(string $kind, string $url, string $mode = 'live'): array
    {
        if ($this->ffprobe === '') return ['ok' => false, 'message' => 'ffprobe روی سرور نصب نیست'];
        if (in_array($kind, ['v4l2', 'decklink'], true)) {
            return ['ok' => false, 'message' => 'بررسی کارت کپچر فقط با شروع کار ممکن است'];
        }
        $err = $this->validateInput($kind, $url, $mode);
        if ($err !== null) return ['ok' => false, 'message' => $err];

        $path = $kind === 'file' ? PUBLIC_PATH . $url : $url;
        $pre  = '';
        if (preg_match('#^rtsps?://#i', $path)) $pre = '-rtsp_transport tcp ';
        $cmd  = 'timeout 15 ' . escapeshellarg($this->ffprobe) . ' -v error ' . $pre
              . '-show_streams -show_format -of json ' . escapeshellarg($path) . ' 2>&1';
        $raw  = (string)shell_exec($cmd);
        $json = json_decode($raw, true);
        if (!is_array($json) || empty($json['streams'])) {
            return ['ok' => false, 'message' => 'ورودی خوانده نشد: ' . mb_substr(trim($raw) ?: 'پاسخی در ۱۵ ثانیه نرسید', 0, 300)];
        }

        $streams = [];
        foreach ($json['streams'] as $s) {
            $fr = (string)($s['avg_frame_rate'] ?? '');
            $fps = 0.0;
            if (preg_match('#^(\d+)/(\d+)$#', $fr, $m) && (int)$m[2] > 0) $fps = round((int)$m[1] / (int)$m[2], 2);
            $streams[] = [
                'type'     => (string)($s['codec_type'] ?? ''),
                'codec'    => (string)($s['codec_name'] ?? ''),
                'width'    => (int)($s['width'] ?? 0),
                'height'   => (int)($s['height'] ?? 0),
                'fps'      => $fps,
                'field'    => (string)($s['field_order'] ?? ''),
                'channels' => (int)($s['channels'] ?? 0),
                'language' => (string)($s['tags']['language'] ?? ''),
            ];
        }
        return ['ok' => true, 'message' => '', 'streams' => $streams,
                'format' => (string)($json['format']['format_long_name'] ?? '')];
    }

    /**
     * قابلیت‌های همین سرور — نسخه‌ی ffmpeg، انکودرهای سخت‌افزاری، کارت
     * کپچر. «ساخته‌شده با NVENC» یعنی ffmpeg پشتیبانی دارد، نه اینکه
     * کارت گرافیک هست؛ هر دو جدا گزارش می‌شوند.
     */
    public function capabilities(): array
    {
        $cache = STORAGE_PATH . '/cache/transcoder_caps.json';
        if (is_file($cache) && time() - (int)filemtime($cache) < 3600) {
            $c = json_decode((string)file_get_contents($cache), true);
            if (is_array($c)) return $c;
        }

        $caps = ['ffmpeg' => $this->ffmpeg !== '', 'version' => '', 'encoders' => [], 'devices' => [],
                 'nvidia' => false, 'dri' => false, 'video_devices' => []];
        if ($this->ffmpeg === '') return $caps;

        $bin = escapeshellarg($this->ffmpeg);
        if (preg_match('/ffmpeg version (\S+)/', (string)shell_exec("$bin -version 2>&1"), $m)) $caps['version'] = $m[1];

        $enc = (string)shell_exec("$bin -hide_banner -encoders 2>/dev/null");
        foreach (['libx264', 'libx265', 'mpeg2video', 'h264_nvenc', 'hevc_nvenc', 'h264_qsv', 'hevc_qsv',
                  'h264_vaapi', 'hevc_vaapi', 'aac', 'libmp3lame', 'mp2'] as $e) {
            if (preg_match('/^\s*\S+\s+' . preg_quote($e, '/') . '\s/m', $enc)) $caps['encoders'][] = $e;
        }
        $dev = (string)shell_exec("$bin -hide_banner -devices 2>/dev/null");
        foreach (['v4l2', 'decklink', 'alsa'] as $d) {
            if (preg_match('/^\s*D\S*\s+[^\n]*\b' . $d . '\b/m', $dev)) $caps['devices'][] = $d;
        }
        $caps['nvidia'] = trim((string)shell_exec('command -v nvidia-smi >/dev/null 2>&1 && nvidia-smi -L 2>/dev/null | head -1')) !== '';
        $caps['dri']    = trim((string)shell_exec('test -e /dev/dri/renderD128 && echo 1')) === '1';
        $vd = trim((string)shell_exec('ls /dev/video* 2>/dev/null'));
        $caps['video_devices'] = $vd !== '' ? preg_split('/\s+/', $vd) : [];

        @file_put_contents($cache, json_encode($caps));
        return $caps;
    }

    /** انتشار روی کانال IPTV: تلویزیون‌ها از این به بعد خروجی ترنسکدر را پخش می‌کنند */
    public function publish(int $tenantId, int $jobId, int $channelId): array
    {
        $job = $this->find($tenantId, $jobId);
        if (!$job) return ['ok' => false, 'message' => 'کار یافت نشد'];
        $ch = $this->db->row('SELECT id FROM iptv_channels WHERE id = ? AND tenant_id = ?', [$channelId, $tenantId]);
        if (!$ch) return ['ok' => false, 'message' => 'کانال یافت نشد'];

        $fields = [];
        $types  = array_column($job['settings']['outputs'] ?? [], 'type');
        if (in_array('hls', $types, true)) $fields['stream_url'] = $this->hlsUrl($job);
        foreach ($job['settings']['outputs'] as $o) {
            if ($o['type'] === 'udp') { $fields['multicast_url'] = $o['url']; break; }
        }
        if (!$fields) return ['ok' => false, 'message' => 'این کار خروجی HLS یا UDP ندارد که کانال پخشش کند'];

        $this->db->update('iptv_channels', $fields, ['id' => $channelId, 'tenant_id' => $tenantId]);
        $this->db->update('transcoder_jobs', ['channel_id' => $channelId], ['id' => $jobId]);
        return ['ok' => true, 'message' => 'کانال به خروجی ترنسکدر وصل شد'];
    }

    // ══════════════════════════════════════════════════════════════

    private function duration(string $path): float
    {
        if ($this->ffprobe === '') return 0.0;
        return (float)trim((string)shell_exec(
            escapeshellarg($this->ffprobe) . ' -v error -show_entries format=duration -of csv=p=0 '
            . escapeshellarg($path) . ' 2>/dev/null'
        ));
    }

    /** نام گزینهٔ timeout سوکت rtsp — یک منبع حقیقت در MediaConvertService */
    private function rtspTimeoutOption(): string
    {
        return MediaConvertService::rtspTimeoutOption($this->ffmpeg);
    }
}
