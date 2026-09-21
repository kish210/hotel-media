<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Device Service
 * ثبت خودکار تلویزیون‌ها و صف فرمان زنده.
 *
 * واقعیت پلتفرم‌ها — مهم برای اینکه انتظار غلط ایجاد نشود:
 *  • Android TV  — اپ APK نصب می‌شود؛ کامل‌ترین کنترل (ریبوت، صدا، نصب به‌روزرسانی)
 *  • LG webOS    — تلویزیون هتلی با Pro:Centric یک آدرس HTML5 را باز می‌کند.
 *                  ریبوت و خاموش/روشن از راه دور کار سرور Pro:Centric است، نه ما؛
 *                  ما فقط فرمان‌های داخل صفحه (refresh، پیام، کانال) را داریم.
 *  • Samsung Tizen — تلویزیون هتلی با LYNK REACH / URL Launcher همان کار را می‌کند.
 *                  محدودیت‌ها مشابه LG است.
 * به همین دلیل هر فرمان با پلتفرم دستگاه اعتبارسنجی می‌شود و فرمان
 * پشتیبانی‌نشده اصلا در صف قرار نمی‌گیرد.
 *
 * فاز ۴ نقشه‌راه — docs/TODO.md (۵.۴)
 */
class DeviceService
{
    public const PLATFORMS = ['android', 'webos', 'tizen', 'windows', 'browser', 'unknown'];

    /** فرمان‌هایی که هر پلتفرم واقعا می‌تواند اجرا کند */
    public const CAPABILITIES = [
        // اپ بومی: کنترل کامل
        'android' => ['refresh', 'reboot', 'volume', 'brightness', 'power', 'channel',
                      'message', 'update_app', 'open_url', 'clear_cache', 'screenshot'],
        // اپ ویندوزی پلیر
        'windows' => ['refresh', 'reboot', 'volume', 'message', 'update_app', 'open_url',
                      'clear_cache', 'screenshot'],
        // ‏LG Pro:Centric — صفحه HTML5؛ ریبوت/خاموشی کار سرور Pro:Centric است
        'webos'   => ['refresh', 'volume', 'channel', 'message', 'open_url', 'clear_cache'],
        // ‏Samsung LYNK REACH / URL Launcher — همان محدودیت
        'tizen'   => ['refresh', 'volume', 'channel', 'message', 'open_url', 'clear_cache'],
        // مرورگر معمولی (تست یا کیوسک)
        'browser' => ['refresh', 'message', 'open_url', 'clear_cache'],
        'unknown' => ['refresh', 'message'],
    ];

    /** فرمان بعد از این مدت منقضی می‌شود تا تلویزیونی که یک هفته خاموش بوده
     *  ناگهان فرمان قدیمی را اجرا نکند */
    private const COMMAND_TTL = 3600;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // ══════════════════════════════════════════════════════════════
    //  ثبت خودکار
    // ══════════════════════════════════════════════════════════════

    /**
     * دستگاه با توکن ثبت، خودش را معرفی می‌کند.
     * اگر همان MAC قبلا ثبت شده باشد، همان صفحه برگردانده می‌شود —
     * یعنی reset کارخانه‌ی تلویزیون، رکورد تکراری نمی‌سازد.
     *
     * @param array<string,mixed> $info
     * @return array{ok:bool,screen:?array,message:string,status:string}
     */
    public function enroll(string $token, array $info): array
    {
        $tok = $this->db->row(
            'SELECT * FROM enrollment_tokens WHERE token = ? AND is_active = 1',
            [trim($token)]
        );

        if (!$tok) {
            return ['ok' => false, 'screen' => null, 'status' => 'invalid', 'message' => 'توکن ثبت نامعتبر است'];
        }
        if (!empty($tok['expires_at']) && strtotime((string)$tok['expires_at']) < time()) {
            return ['ok' => false, 'screen' => null, 'status' => 'expired', 'message' => 'توکن ثبت منقضی شده است'];
        }

        $tid = (int)$tok['tenant_id'];
        $mac = $this->normalizeMac($info['mac'] ?? '');

        // دستگاه تکراری؟
        if ($mac !== null) {
            $existing = $this->db->row(
                'SELECT * FROM screens WHERE tenant_id = ? AND mac_address = ?',
                [$tid, $mac]
            );
            if ($existing) {
                $this->updateDeviceInfo((int)$existing['id'], $info);
                return [
                    'ok'      => true,
                    'screen'  => $this->db->row('SELECT * FROM screens WHERE id = ?', [(int)$existing['id']]),
                    'status'  => $existing['status'] === 'active' ? 'approved' : 'pending',
                    'message' => 'این دستگاه از قبل ثبت شده است',
                ];
            }
        }

        if ($tok['max_devices'] !== null && (int)$tok['used_count'] >= (int)$tok['max_devices']) {
            return ['ok' => false, 'screen' => null, 'status' => 'full', 'message' => 'سقف ثبت این توکن پر شده است'];
        }

        $platform = $this->detectPlatform($info);
        $approve  = (int)$tok['auto_approve'] === 1;
        $code     = $this->uniqueCode($tid);

        $name = trim((string)($info['name'] ?? ''))
             ?: ('TV ' . ($info['model'] ?? strtoupper($platform)) . ' ' . $code);

        $screenId = (int)$this->db->insert('screens', [
            'tenant_id'     => $tid,
            'name'          => mb_substr($name, 0, 255),
            'code'          => $code,
            'screen_type'   => $tok['screen_type'] ?: 'iptv',
            'group_id'      => $tok['group_id'] ?: null,
            'iptv_menu_id'  => $tok['menu_id'] ?: null,
            'platform'      => $platform,
            'mac_address'   => $mac,
            'serial_number' => $this->clean($info['serial'] ?? null, 60),
            'model'         => $this->clean($info['model'] ?? null, 80),
            'firmware'      => $this->clean($info['firmware'] ?? null, 60),
            'app_version'   => $this->clean($info['app_version'] ?? null, 30),
            'resolution'    => $this->clean($info['resolution'] ?? null, 20) ?: '1920x1080',
            // تا وقتی IT تایید نکرده، دستگاه pending می‌ماند
            'status'        => $approve ? 'active' : 'pending',
            'enrolled_at'   => date('Y-m-d H:i:s'),
            'device_info'   => json_encode($info, JSON_UNESCAPED_UNICODE),
        ]);

        $this->db->query(
            'UPDATE enrollment_tokens SET used_count = used_count + 1 WHERE id = ?',
            [(int)$tok['id']]
        );

        $this->logEvent($tid, $screenId, 'enrolled', "پلتفرم $platform" . ($mac ? " — $mac" : ''));

        return [
            'ok'      => true,
            'screen'  => $this->db->row('SELECT * FROM screens WHERE id = ?', [$screenId]),
            'status'  => $approve ? 'approved' : 'pending',
            'message' => $approve ? 'دستگاه ثبت و فعال شد' : 'دستگاه ثبت شد — منتظر تایید مدیر',
        ];
    }

    /** اطلاعات سخت‌افزاری را در هر heartbeat تازه نگه می‌دارد */
    public function updateDeviceInfo(int $screenId, array $info): void
    {
        $fields = [];

        foreach ([
            'model'       => 80,
            'firmware'    => 60,
            'app_version' => 30,
            'resolution'  => 20,
        ] as $key => $len) {
            $v = $this->clean($info[$key] ?? null, $len);
            if ($v !== null) $fields[$key] = $v;
        }

        if ($serial = $this->clean($info['serial'] ?? null, 60)) $fields['serial_number'] = $serial;
        if ($mac = $this->normalizeMac($info['mac'] ?? ''))      $fields['mac_address']   = $mac;

        $platform = $this->detectPlatform($info);
        if ($platform !== 'unknown') $fields['platform'] = $platform;

        if ($fields) $this->db->update('screens', $fields, ['id' => $screenId]);
    }

    // ══════════════════════════════════════════════════════════════
    //  صف فرمان
    // ══════════════════════════════════════════════════════════════

    /**
     * فرمان را در صف می‌گذارد، بعد از بررسی اینکه پلتفرم دستگاه آن را
     * واقعا پشتیبانی می‌کند.
     *
     * @param array<string,mixed> $payload
     * @return array{ok:bool,id:?int,message:string}
     */
    public function queue(array $screen, string $command, array $payload = [], ?int $userId = null): array
    {
        $platform = (string)($screen['platform'] ?? 'unknown');
        $allowed  = self::CAPABILITIES[$platform] ?? self::CAPABILITIES['unknown'];

        if (!in_array($command, $allowed, true)) {
            return [
                'ok'      => false,
                'id'      => null,
                'message' => "فرمان «$command» روی پلتفرم " . $this->platformLabel($platform) . " پشتیبانی نمی‌شود",
            ];
        }

        $err = $this->validatePayload($command, $payload);
        if ($err !== null) return ['ok' => false, 'id' => null, 'message' => $err];

        // فرمان تکراریِ هنوز اجرانشده دوباره صف نمی‌شود
        $dupe = $this->db->row(
            "SELECT id FROM screen_commands
              WHERE screen_id = ? AND command = ? AND status IN ('pending','sent')",
            [(int)$screen['id'], $command]
        );
        if ($dupe) {
            return ['ok' => true, 'id' => (int)$dupe['id'], 'message' => 'همین فرمان از قبل در صف است'];
        }

        $id = (int)$this->db->insert('screen_commands', [
            'tenant_id'  => (int)$screen['tenant_id'],
            'screen_id'  => (int)$screen['id'],
            'command'    => $command,
            'payload'    => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'issued_by'  => $userId,
            'expires_at' => date('Y-m-d H:i:s', time() + self::COMMAND_TTL),
        ]);

        return ['ok' => true, 'id' => $id, 'message' => 'فرمان در صف قرار گرفت'];
    }

    /**
     * فرمان‌های منتظر یک دستگاه را برمی‌گرداند و آن‌ها را sent علامت می‌زند.
     * @return list<array<string,mixed>>
     */
    public function pullCommands(int $screenId): array
    {
        $this->expireOld();

        $rows = $this->db->rows(
            "SELECT id, command, payload FROM screen_commands
              WHERE screen_id = ? AND status = 'pending'
              ORDER BY id LIMIT 20",
            [$screenId]
        );
        if (!$rows) return [];

        $ids = array_map(static fn($r) => (int)$r['id'], $rows);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $this->db->query(
            "UPDATE screen_commands SET status='sent', sent_at=NOW() WHERE id IN ($ph)",
            $ids
        );

        return array_map(static function (array $r): array {
            $payload = $r['payload'] ? json_decode((string)$r['payload'], true) : null;
            return [
                'id'      => (int)$r['id'],
                'cmd'     => $r['command'],
                'payload' => $payload,
            ];
        }, $rows);
    }

    /** دستگاه نتیجه‌ی اجرا را گزارش می‌دهد */
    public function ack(int $screenId, int $commandId, bool $ok, string $result = ''): bool
    {
        $n = $this->db->query(
            "UPDATE screen_commands
                SET status = ?, result = ?, acked_at = NOW()
              WHERE id = ? AND screen_id = ? AND status IN ('sent','pending')",
            [$ok ? 'done' : 'failed', mb_substr($result, 0, 400) ?: null, $commandId, $screenId]
        )->rowCount();

        return $n > 0;
    }

    private function expireOld(): void
    {
        $this->db->query(
            "UPDATE screen_commands SET status='expired'
              WHERE status IN ('pending','sent') AND expires_at IS NOT NULL AND expires_at < NOW()"
        );
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    public function logEvent(int $tenantId, int $screenId, string $event, ?string $detail = null): void
    {
        try {
            $this->db->insert('screen_events', [
                'tenant_id' => $tenantId,
                'screen_id' => $screenId,
                'event'     => mb_substr($event, 0, 30),
                'detail'    => $detail !== null ? mb_substr($detail, 0, 300) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[DEVICE EVENT] ' . $e->getMessage());
        }
    }

    /** پلتفرم را از اطلاعات ارسالی یا User-Agent تشخیص می‌دهد */
    public function detectPlatform(array $info): string
    {
        $claimed = mb_strtolower(trim((string)($info['platform'] ?? '')));
        if (in_array($claimed, self::PLATFORMS, true) && $claimed !== 'unknown') return $claimed;

        $ua = mb_strtolower((string)($info['user_agent'] ?? ''));
        if ($ua === '') return 'unknown';

        // ترتیب مهم است: تلویزیون سامسونگ هم رشته‌ی Linux دارد
        return match (true) {
            str_contains($ua, 'webos') || str_contains($ua, 'web0s')      => 'webos',
            str_contains($ua, 'tizen') || str_contains($ua, 'smart-tv')   => 'tizen',
            str_contains($ua, 'android')                                  => 'android',
            str_contains($ua, 'electron') || str_contains($ua, 'windows') => 'windows',
            str_contains($ua, 'mozilla')                                  => 'browser',
            default                                                       => 'unknown',
        };
    }

    public function platformLabel(string $p): string
    {
        return match ($p) {
            'android' => 'Android TV',
            'webos'   => 'LG webOS',
            'tizen'   => 'Samsung Tizen',
            'windows' => 'Windows',
            'browser' => 'مرورگر',
            default   => 'نامشخص',
        };
    }

    private function validatePayload(string $command, array $payload): ?string
    {
        return match ($command) {
            'volume', 'brightness' => (function () use ($payload, $command): ?string {
                $v = $payload['value'] ?? null;
                if (!is_numeric($v) || (int)$v < 0 || (int)$v > 100) {
                    return "مقدار $command باید بین ۰ تا ۱۰۰ باشد";
                }
                return null;
            })(),
            'channel' => isset($payload['channel_id']) && (int)$payload['channel_id'] > 0
                ? null : 'شناسه کانال لازم است',
            'message' => trim((string)($payload['text'] ?? '')) !== ''
                ? null : 'متن پیام لازم است',
            'open_url' => preg_match('#^https?://#i', (string)($payload['url'] ?? ''))
                ? null : 'آدرس باید با http یا https شروع شود',
            'power' => in_array($payload['state'] ?? '', ['on', 'off'], true)
                ? null : 'وضعیت باید on یا off باشد',
            default => null,
        };
    }

    private function normalizeMac(mixed $mac): ?string
    {
        $mac = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string)$mac) ?? '');
        if (strlen($mac) !== 12) return null;

        return implode(':', str_split($mac, 2));
    }

    private function clean(mixed $v, int $len): ?string
    {
        $v = trim((string)($v ?? ''));
        return $v === '' ? null : mb_substr($v, 0, $len);
    }

    /** کد یکتای صفحه — حلقه محدود است تا در بدترین حالت قفل نشود */
    private function uniqueCode(int $tenantId): string
    {
        for ($i = 0; $i < 20; $i++) {
            $code = 'TV' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
            if (!$this->db->exists('screens', ['code' => $code])) return $code;
        }

        throw new \RuntimeException('تولید کد یکتا برای دستگاه ناموفق بود');
    }
}
