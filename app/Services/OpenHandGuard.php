<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

/**
 * نگهبان تغییرها — عکس‌برداری، سنجش سلامت، و بازگشت خودکار.
 *
 * ── چرا این کلاس وجود دارد ─────────────────────────────────────────
 * عامل (OpenHand) برخلاف دستیار قبلی واقعا می‌نویسد. این برنامه روی
 * تلویزیون اتاق مهمان اجرا می‌شود، پس یک ردیف اشتباه یعنی چند صد اتاق
 * بی‌تصویر. سه ضمانت اینجا گذاشته شده:
 *
 *  ۱. پیش از هر تغییر، ردیف‌هایی که تغییر می‌کنند *قبل از* نوشتن خوانده
 *     و در دیسک ذخیره می‌شوند. پس بازگشت حتی وقتی دیتابیس نیمه‌کاره
 *     مانده باشد هم ممکن است.
 *  ۲. بعد از هر تغییر چند بررسی ساده اجرا می‌شود (اتصال دیتابیس،
 *     جدول‌های اصلی، نوشتن روی دیسک، و پاسخ‌دادن پنل روی خود سرور).
 *  ۳. اگر نوشتن استثنا داد یا بررسی سلامت شکست خورد، همان لحظه و بدون
 *     دخالت اپراتور برگشت انجام می‌شود و علت گزارش می‌شود.
 *
 * دفتر تغییرها در `storage/agent/changes` است و فقط ردیف‌های درگیر را
 * نگه می‌دارد (نه کل دیتابیس) تا هر تغییر ارزان و دقیق باشد.
 */
final class OpenHandGuard
{
    /** تنها جدول‌هایی که عامل اجازهٔ نوشتن در آن‌ها را دارد. */
    public const WRITABLE = [
        'playlists', 'playlist_items', 'screen_messages', 'screen_commands',
        'iptv_menus', 'screen_groups', 'tv_intro_items',
    ];

    /** جدول‌هایی که عکس‌برداری از آن‌ها مجاز است (خواندن برای بازگشت). */
    public const SNAPSHOTTABLE = [
        'playlists', 'playlist_items', 'screen_messages', 'screen_commands',
        'iptv_menus', 'iptv_menu_items', 'screen_groups', 'tv_intro_items',
        'screens', 'media', 'iptv_channels', 'screen_playlists',
    ];

    /** چند تغییر در دیسک نگه داشته شود. */
    private const KEEP = 40;

    private Database $db;
    private string $dir;

    public function __construct(?Database $db = null, ?string $dir = null)
    {
        $this->db  = $db ?? Database::getInstance();
        $base      = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/storage';
        $this->dir = $dir ?? ($base . '/agent');
    }

    // ══════════════════════════════════════════════════════════════
    //  تغییر محافظت‌شده
    // ══════════════════════════════════════════════════════════════

    /**
     * یک تغییر را با عکس‌برداری، بررسی سلامت و بازگشت خودکار اجرا می‌کند.
     *
     * @param string   $label   توضیح فارسی برای اپراتور
     * @param array    $preKeys ردیف‌هایی که تغییر می‌کنند: [['table'=>..,'id'=>..]]
     * @param callable $mutate  تابع تغییر؛ آرایهٔ $ctx را با مرجع می‌گیرد و
     *                          ردیف‌های تازه‌ساخته را در $ctx['inserted'] می‌گذارد.
     *
     * @return array{ok:bool,change_id:string,result:mixed,message:string,rolled_back:bool}
     */
    public function guarded(string $label, array $preKeys, callable $mutate): array
    {
        $id  = $this->newId();
        $rec = [
            'id'       => $id,
            'label'    => $label,
            'at'       => date('c'),
            'status'   => 'running',
            'pre'      => [],
            'inserted' => [],
            'health'   => [],
            'message'  => '',
        ];

        foreach ($preKeys as $k) {
            $table = (string)($k['table'] ?? '');
            $rowId = (int)($k['id'] ?? 0);
            if ($rowId < 1 || !$this->tableOk($table)) continue;
            $rec['pre'][] = ['table' => $table, 'id' => $rowId, 'row' => $this->captureRow($table, $rowId)];
        }

        $this->saveRecord($rec);

        $ctx    = ['inserted' => []];
        $result = null;
        $error  = '';

        try {
            $result = $mutate($ctx);
        } catch (Throwable $e) {
            $error = $this->cleanError($e->getMessage());
        }

        $rec['inserted'] = $this->cleanInserted($ctx['inserted'] ?? []);

        if ($error === '') {
            $h = $this->health();
            $rec['health'] = $h['checks'];
            if (!$h['ok']) $error = 'بررسی سلامت پس از تغییر شکست خورد — ' . $h['reason'];
        }

        if ($error !== '') {
            $rec['status']  = 'failed';
            $rec['message'] = $error;
            $undoErrors     = $this->undo($rec);
            $rec['status']  = $undoErrors ? 'failed_rollback_incomplete' : 'rolled_back';
            if ($undoErrors) $rec['message'] .= ' | بازگشت ناقص: ' . implode('؛ ', $undoErrors);
            $this->saveRecord($rec);

            return [
                'ok'          => false,
                'change_id'   => $id,
                'result'      => null,
                'message'     => $rec['message'],
                'rolled_back' => !$undoErrors,
            ];
        }

        $rec['status'] = 'applied';
        $this->saveRecord($rec);
        $this->prune();

        return ['ok' => true, 'change_id' => $id, 'result' => $result, 'message' => '', 'rolled_back' => false];
    }

    /**
     * بازگردانی یک تغییر از دفتر (دستی، با دکمهٔ اپراتور).
     *
     * @return array{ok:bool,message:string}
     */
    public function restore(string $id): array
    {
        $rec = $this->readRecord($id);
        if ($rec === null) return ['ok' => false, 'message' => 'این تغییر پیدا نشد'];
        if (($rec['status'] ?? '') === 'rolled_back') return ['ok' => false, 'message' => 'این تغییر قبلا برگردانده شده'];

        $errors = $this->undo($rec);
        $rec['status'] = $errors ? 'failed_rollback_incomplete' : 'rolled_back';
        $rec['message'] = $errors ? implode('؛ ', $errors) : 'بازگردانی شد';
        $this->saveRecord($rec);

        return ['ok' => !$errors, 'message' => $rec['message']];
    }

    /** فهرست آخرین تغییرها، تازه‌ترین اول. */
    public function changes(int $limit = 20): array
    {
        $out = [];
        foreach ($this->records() as $rec) {
            $out[] = [
                'id'      => (string)($rec['id'] ?? ''),
                'label'   => (string)($rec['label'] ?? ''),
                'at'      => (string)($rec['at'] ?? ''),
                'status'  => (string)($rec['status'] ?? ''),
                'message' => (string)($rec['message'] ?? ''),
                'rows'    => count($rec['pre'] ?? []) + count($rec['inserted'] ?? []),
            ];
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /** آیا تغییر دادنی برگردانده‌شدنی هست؟ */
    public function undoable(string $id): bool
    {
        $rec = $this->readRecord($id);
        return $rec !== null && in_array(($rec['status'] ?? ''), ['applied', 'failed_rollback_incomplete'], true);
    }

    // ══════════════════════════════════════════════════════════════
    //  سلامت
    // ══════════════════════════════════════════════════════════════

    /**
     * بررسی سلامت سامانه بعد از تغییر.
     *
     * @return array{ok:bool,reason:string,checks:array<int,array{name:string,ok:bool,detail:string}>}
     */
    public function health(): array
    {
        $checks = [];

        try {
            $this->db->value('SELECT 1');
            $checks[] = ['name' => 'اتصال دیتابیس', 'ok' => true, 'detail' => ''];
        } catch (Throwable $e) {
            $checks[] = ['name' => 'اتصال دیتابیس', 'ok' => false, 'detail' => $this->cleanError($e->getMessage())];
        }

        foreach (['screens', 'playlists', 'media', 'iptv_channels'] as $t) {
            try {
                $this->db->value("SELECT COUNT(*) FROM `$t`");
                $checks[] = ['name' => 'جدول ' . $t, 'ok' => true, 'detail' => ''];
            } catch (Throwable $e) {
                $checks[] = ['name' => 'جدول ' . $t, 'ok' => false, 'detail' => $this->cleanError($e->getMessage())];
            }
        }

        $storage = defined('STORAGE_PATH') ? STORAGE_PATH : ROOT_PATH . '/storage';
        $checks[] = ['name' => 'نوشتن روی دیسک', 'ok' => is_writable($storage), 'detail' => $storage];

        $free = @disk_free_space(defined('ROOT_PATH') ? ROOT_PATH : '.');
        $checks[] = [
            'name'   => 'فضای دیسک',
            'ok'     => $free === false || $free > 200 * 1024 * 1024,
            'detail' => $free === false ? 'نامعلوم' : round((float)$free / 1048576) . ' مگابایت آزاد',
        ];

        $http = $this->httpCheck();
        if ($http !== null) $checks[] = $http;

        $reason = '';
        foreach ($checks as $c) {
            if (!$c['ok']) { $reason = $c['name'] . ($c['detail'] !== '' ? ' (' . $c['detail'] . ')' : ''); break; }
        }

        return ['ok' => $reason === '', 'reason' => $reason, 'checks' => $checks];
    }

    /** @return array{name:string,ok:bool,detail:string}|null */
    private function httpCheck(): ?array
    {
        if (!function_exists('curl_init')) return null;

        $appUrl = (string)env('APP_URL', '');
        $host   = parse_url($appUrl, PHP_URL_HOST) ?: '127.0.0.1';
        $port   = (int)(parse_url($appUrl, PHP_URL_PORT) ?: 80);

        $ch = curl_init("http://127.0.0.1:{$port}/admin/login");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER     => ['Host: ' . $host],
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        return [
            'name'   => 'پاسخ‌دهی پنل',
            'ok'     => $code >= 200 && $code < 400,
            'detail' => $err !== '' ? $this->cleanError($err) : ('کد ' . $code),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  عکس‌برداری و بازگشت
    // ══════════════════════════════════════════════════════════════

    private function captureRow(string $table, int $id): ?array
    {
        try {
            return $this->db->row("SELECT * FROM `$table` WHERE id = ? LIMIT 1", [$id]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * بازگشت: اول ردیف‌های تازه حذف، بعد ردیف‌های قبلی بازنویسی.
     *
     * @return array<int,string> خطاها؛ خالی یعنی موفق
     */
    private function undo(array $rec): array
    {
        $errors = [];

        foreach (array_reverse($rec['inserted'] ?? []) as $ins) {
            $t  = (string)($ins['table'] ?? '');
            $id = (int)($ins['id'] ?? 0);
            if ($id < 1 || !$this->tableOk($t)) continue;
            try {
                $this->db->delete($t, ['id' => $id]);
            } catch (Throwable $e) {
                $errors[] = $t . ' #' . $id . ': ' . $this->cleanError($e->getMessage());
            }
        }

        foreach ($rec['pre'] ?? [] as $p) {
            $t   = (string)($p['table'] ?? '');
            $id  = (int)($p['id'] ?? 0);
            $row = $p['row'] ?? null;
            if ($id < 1 || !$this->tableOk($t) || !is_array($row)) continue;

            try {
                $exists = (int)$this->db->value("SELECT COUNT(*) FROM `$t` WHERE id = ?", [$id]);
                if ($exists > 0) {
                    $data = $row;
                    unset($data['id']);
                    if ($data) $this->db->update($t, $data, ['id' => $id]);
                } else {
                    $this->db->insert($t, $row);
                }
            } catch (Throwable $e) {
                $errors[] = $t . ' #' . $id . ': ' . $this->cleanError($e->getMessage());
            }
        }

        return $errors;
    }

    /** @param array<int,mixed> $inserted */
    private function cleanInserted(array $inserted): array
    {
        $out = [];
        foreach ($inserted as $ins) {
            $t  = (string)($ins['table'] ?? '');
            $id = (int)($ins['id'] ?? 0);
            if ($id > 0 && $this->tableOk($t)) $out[] = ['table' => $t, 'id' => $id];
        }
        return $out;
    }

    private function tableOk(string $table): bool
    {
        return (bool)preg_match('/^[a-z_]{2,40}$/', $table)
            && (in_array($table, self::SNAPSHOTTABLE, true) || in_array($table, self::WRITABLE, true));
    }

    /** پیام خطای دیتابیس ممکن است رشتهٔ اتصال یا رمز را لو بدهد. */
    private function cleanError(string $m): string
    {
        $m = (string)preg_replace('/(password|passwd|pwd|secret|api[_-]?key|token)\s*[:=]\s*\S+/i', '$1=***', $m);
        $m = (string)preg_replace('/\b[A-Za-z0-9._%+\-]+:[^@\s\/]{3,}@/', '***:***@', $m);
        return mb_substr($m, 0, 300);
    }

    // ══════════════════════════════════════════════════════════════
    //  دفتر تغییرها
    // ══════════════════════════════════════════════════════════════

    private function newId(): string
    {
        return date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }

    private function ensureDir(): bool
    {
        $dir = $this->dir . '/changes';
        if (is_dir($dir)) return true;
        return @mkdir($dir, 0775, true) || is_dir($dir);
    }

    private function fileFor(string $id): string
    {
        $safe = (string)preg_replace('/[^A-Za-z0-9\-]/', '', $id);
        return $this->dir . '/changes/' . $safe . '.json';
    }

    private function saveRecord(array $rec): void
    {
        if (!$this->ensureDir()) return;
        $json = json_encode($rec, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) return;
        @file_put_contents($this->fileFor((string)$rec['id']), $json, LOCK_EX);
    }

    private function readRecord(string $id): ?array
    {
        $f = $this->fileFor($id);
        if (!is_file($f)) return null;
        $j = json_decode((string)@file_get_contents($f), true);
        return is_array($j) ? $j : null;
    }

    /** @return array<int,array> تازه‌ترین اول */
    private function records(): array
    {
        $files = glob($this->dir . '/changes/*.json') ?: [];
        rsort($files);
        $out = [];
        foreach ($files as $f) {
            $j = json_decode((string)@file_get_contents($f), true);
            if (is_array($j)) $out[] = $j;
        }
        return $out;
    }

    private function prune(): void
    {
        $files = glob($this->dir . '/changes/*.json') ?: [];
        rsort($files);
        foreach (array_slice($files, self::KEEP) as $old) @unlink($old);
    }
}
