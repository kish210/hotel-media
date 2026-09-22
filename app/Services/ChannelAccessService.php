<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Channel Access Service
 * دو لایه‌ی مستقل کنترل دسترسی روی کانال‌ها:
 *
 *  ۱) سطح اتاق — کانال VIP فقط برای سوئیت. هتل تصمیم می‌گیرد.
 *  ۲) قفل والدین — کانال بزرگسال با رمز. مهمان تصمیم می‌گیرد.
 *
 * این دو با هم قاطی نمی‌شوند: کانالی که سطحش بالاتر از اتاق است اصلا در
 * فهرست نمی‌آید (مهمان نباید بداند وجود دارد)، ولی کانال قفل‌شده دیده
 * می‌شود و با رمز باز می‌شود.
 *
 * نقشه‌راه: ۱.۱۷ و ۳.۴
 */
class ChannelAccessService
{
    /** توکن باز شدن قفل تا این مدت معتبر است (ثانیه) */
    private const UNLOCK_TTL = 7200;

    /** بعد از این تعداد رمز اشتباه، ورود رمز برای اتاق قفل می‌شود */
    private const MAX_PIN_TRIES = 5;
    private const PIN_LOCK_SEC  = 300;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // ══════════════════════════════════════════════════════════════
    //  فهرست کانال قابل‌دسترس
    // ══════════════════════════════════════════════════════════════

    /**
     * کانال‌هایی که این اتاق حق دیدنشان را دارد.
     * کانال بالاتر از سطح اتاق اصلا برنمی‌گردد.
     *
     * @param array<string,mixed>|null $room ردیف iptv_rooms یا null برای صفحه بدون اتاق
     * @return list<array<string,mixed>>
     */
    public function visibleChannels(int $tenantId, ?array $room): array
    {
        $level = (int)($room['access_level'] ?? 0);

        $rows = $this->db->rows(
            'SELECT id, name, name_en, logo_url, category, channel_no, sort_order,
                    stream_url, multicast_url, delivery, protocol,
                    access_level, is_adult, is_radio
               FROM iptv_channels
              WHERE tenant_id = ? AND is_active = 1 AND access_level <= ?
              ORDER BY is_radio, channel_no IS NULL, channel_no, sort_order, id',
            [$tenantId, $level]
        );

        $parentalOn = (int)($room['parental_enabled'] ?? 0) === 1
                   && !empty($room['parental_pin']);

        foreach ($rows as &$r) {
            // قفل فقط وقتی معنا دارد که مهمان رمز گذاشته باشد
            $r['locked'] = $parentalOn && (int)$r['is_adult'] === 1;
            $r['is_adult'] = (int)$r['is_adult'] === 1;
            $r['is_radio'] = (int)$r['is_radio'] === 1;
        }
        unset($r);

        return $rows;
    }

    /** آیا این اتاق حق دیدن این کانال را دارد؟ */
    public function canView(int $tenantId, ?array $room, int $channelId): bool
    {
        $ch = $this->db->row(
            'SELECT access_level FROM iptv_channels WHERE id = ? AND tenant_id = ? AND is_active = 1',
            [$channelId, $tenantId]
        );
        if (!$ch) return false;

        return (int)$ch['access_level'] <= (int)($room['access_level'] ?? 0);
    }

    // ══════════════════════════════════════════════════════════════
    //  قفل والدین
    // ══════════════════════════════════════════════════════════════

    /**
     * مهمان رمز والدین را تنظیم یا عوض می‌کند.
     * @return array{ok:bool,message:string}
     */
    public function setPin(int $tenantId, int $roomId, string $pin, string $currentPin = ''): array
    {
        $room = $this->db->row(
            'SELECT parental_pin, parental_enabled FROM iptv_rooms WHERE id = ? AND tenant_id = ?',
            [$roomId, $tenantId]
        );
        if (!$room) return ['ok' => false, 'message' => 'اتاق یافت نشد'];

        // اگر رمزی هست، تغییرش نیاز به رمز فعلی دارد
        if (!empty($room['parental_pin'])) {
            if (!password_verify($currentPin, (string)$room['parental_pin'])) {
                return ['ok' => false, 'message' => 'رمز فعلی اشتباه است'];
            }
        }

        if (!preg_match('/^\d{4}$/', $pin)) {
            return ['ok' => false, 'message' => 'رمز باید دقیقا ۴ رقم باشد'];
        }
        // رمزهایی که هر بچه‌ای اول امتحان می‌کند
        if (in_array($pin, ['0000', '1111', '1234', '9999'], true)) {
            return ['ok' => false, 'message' => 'این رمز خیلی ساده است — رمز دیگری انتخاب کنید'];
        }

        $this->db->update('iptv_rooms', [
            'parental_pin'     => password_hash($pin, PASSWORD_BCRYPT),
            'parental_enabled' => 1,
        ], ['id' => $roomId, 'tenant_id' => $tenantId]);

        return ['ok' => true, 'message' => 'قفل والدین فعال شد'];
    }

    /**
     * خاموش کردن قفل — نیاز به رمز فعلی دارد.
     */
    public function disablePin(int $tenantId, int $roomId, string $pin): array
    {
        $room = $this->db->row(
            'SELECT parental_pin FROM iptv_rooms WHERE id = ? AND tenant_id = ?',
            [$roomId, $tenantId]
        );
        if (!$room)                       return ['ok' => false, 'message' => 'اتاق یافت نشد'];
        if (empty($room['parental_pin'])) return ['ok' => true,  'message' => 'قفل والدین فعال نبود'];

        if (!password_verify($pin, (string)$room['parental_pin'])) {
            return ['ok' => false, 'message' => 'رمز اشتباه است'];
        }

        $this->db->update('iptv_rooms',
            ['parental_pin' => null, 'parental_enabled' => 0],
            ['id' => $roomId, 'tenant_id' => $tenantId]
        );

        return ['ok' => true, 'message' => 'قفل والدین خاموش شد'];
    }

    /**
     * بررسی رمز و صدور توکن باز شدن.
     * توکن در نشست پلیر می‌ماند و تا انقضا کانال‌های قفل‌شده باز است —
     * وگرنه مهمان برای هر تعویض کانال باید رمز بزند.
     *
     * @return array{ok:bool,token:?string,expires_in:int,message:string}
     */
    public function unlock(int $tenantId, int $roomId, string $pin): array
    {
        if ($this->isPinLocked($roomId)) {
            return ['ok' => false, 'token' => null, 'expires_in' => 0,
                    'message' => 'به‌دلیل تلاش‌های ناموفق، ورود رمز چند دقیقه غیرفعال است'];
        }

        $room = $this->db->row(
            'SELECT parental_pin, parental_enabled FROM iptv_rooms WHERE id = ? AND tenant_id = ?',
            [$roomId, $tenantId]
        );
        if (!$room) return ['ok' => false, 'token' => null, 'expires_in' => 0, 'message' => 'اتاق یافت نشد'];

        if (empty($room['parental_pin'])) {
            return ['ok' => true, 'token' => null, 'expires_in' => 0, 'message' => 'قفل والدین فعال نیست'];
        }

        if (!password_verify($pin, (string)$room['parental_pin'])) {
            $this->recordFailedPin($roomId);
            return ['ok' => false, 'token' => null, 'expires_in' => 0, 'message' => 'رمز اشتباه است'];
        }

        $this->clearFailedPin($roomId);

        return [
            'ok'         => true,
            'token'      => $this->issueToken($tenantId, $roomId),
            'expires_in' => self::UNLOCK_TTL,
            'message'    => 'قفل باز شد',
        ];
    }

    /** آیا توکن باز شدن معتبر است؟ */
    public function verifyToken(int $tenantId, int $roomId, string $token): bool
    {
        if ($token === '') return false;

        $parts = explode('.', $token);
        if (count($parts) !== 2) return false;

        [$payload, $sig] = $parts;
        $expected = $this->sign($payload);
        if (!hash_equals($expected, $sig)) return false;

        $data = json_decode((string)base64_decode(strtr($payload, '-_', '+/')), true);
        if (!is_array($data)) return false;

        return (int)($data['t'] ?? 0) === $tenantId
            && (int)($data['r'] ?? 0) === $roomId
            && (int)($data['e'] ?? 0) > time();
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    private function issueToken(int $tenantId, int $roomId): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode([
            't' => $tenantId,
            'r' => $roomId,
            'e' => time() + self::UNLOCK_TTL,
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $payload . '.' . $this->sign($payload);
    }

    private function sign(string $payload): string
    {
        // از همان راز برنامه استفاده می‌کنیم؛ اگر تعریف نشده باشد، امضا
        // بی‌معنا می‌شود، پس صریح خطا می‌دهیم نه اینکه بی‌صدا ناامن شویم.
        $secret = (string)env('APP_KEY', '');
        if ($secret === '') {
            throw new \RuntimeException('APP_KEY تعریف نشده — توکن قفل والدین قابل امضا نیست');
        }

        return hash_hmac('sha256', $payload, $secret);
    }

    private function isPinLocked(int $roomId): bool
    {
        // ‏rate_limits ستون key یکتا ندارد و reset_at را نگه می‌دارد،
        // پس ردیف منقضی را می‌خوانیم و کنار می‌گذاریم.
        $row = $this->db->row(
            "SELECT attempts FROM rate_limits
              WHERE `key` = ? AND reset_at > NOW()
              ORDER BY id DESC LIMIT 1",
            [$this->pinKey($roomId)]
        );

        return $row !== null && (int)$row['attempts'] >= self::MAX_PIN_TRIES;
    }

    private function recordFailedPin(int $roomId): void
    {
        $key   = $this->pinKey($roomId);
        $reset = date('Y-m-d H:i:s', time() + self::PIN_LOCK_SEC);

        try {
            $existing = $this->db->row(
                "SELECT id FROM rate_limits WHERE `key` = ? AND reset_at > NOW() ORDER BY id DESC LIMIT 1",
                [$key]
            );

            if ($existing) {
                $this->db->query(
                    'UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?',
                    [(int)$existing['id']]
                );
            } else {
                $this->db->insert('rate_limits', [
                    'key'      => $key,
                    'attempts' => 1,
                    'reset_at' => $reset,
                ]);
            }
        } catch (\Throwable $e) {
            // محدودیت نرخ نباید باعث شود قفل والدین از کار بیفتد
            error_log('[PARENTAL] ' . $e->getMessage());
        }
    }

    private function clearFailedPin(int $roomId): void
    {
        try {
            $this->db->query('DELETE FROM rate_limits WHERE `key` = ?', [$this->pinKey($roomId)]);
        } catch (\Throwable $e) {
            error_log('[PARENTAL] ' . $e->getMessage());
        }
    }

    private function pinKey(int $roomId): string
    {
        return 'parental:' . $roomId;
    }
}
