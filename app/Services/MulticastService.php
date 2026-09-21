<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Multicast Service
 * تخصیص آدرس گروه multicast به کانال‌ها و ساخت فهرست کانال برای تلویزیون‌ها.
 *
 * چرا multicast:
 *   با HTTP/HLS هر تلویزیون یک جریان جدا می‌گیرد. ۲۰۰ اتاق روی یک کانال
 *   یعنی ۲۰۰ × ۸Mbps = ۱.۶ گیگابیت. با multicast همان کانال یک بار روی
 *   شبکه می‌رود و سوییچ با IGMP snooping آن را فقط به پورت‌های درخواست‌کننده
 *   می‌دهد: ۸Mbps ثابت، مستقل از تعداد اتاق.
 *
 * محدودیتی که باید صریح باشد:
 *   تگ <video> در HTML5 نمی‌تواند UDP multicast بخواند. پورتال ما HTML5 است،
 *   پس مسیر درست این است که **خود تلویزیون** کانال multicast را با تیونر IP
 *   بگیرد (از فهرست کانالی که اینجا ساخته می‌شود) و پورتال فقط منو، EPG و
 *   خدمات را نشان دهد. برای کلاینت‌های HTML5 مسیر udpxy هست که multicast را
 *   به HTTP تبدیل می‌کند — ولی آن دوباره unicast است و باید کم استفاده شود.
 */
class MulticastService
{
    /** پهنای باند تخمینی هر کانال برای محاسبه‌ی بار شبکه (Mbps) */
    private const BITRATE_MBPS = [
        'sd' => 3.0,
        'hd' => 8.0,
        'uhd' => 25.0,
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** @return array<string,mixed> */
    public function config(int $tenantId): array
    {
        $row = $this->db->row('SELECT * FROM multicast_config WHERE tenant_id = ?', [$tenantId]);
        if ($row) return $row;

        return [
            'tenant_id'    => $tenantId,
            'base_group'   => '239.1.1.1',
            'base_port'    => 5000,
            'port_step'    => 2,
            'ttl'          => 4,
            'udpxy_url'    => null,
            'interface_ip' => null,
            'is_active'    => 0,
        ];
    }

    /** @param array<string,mixed> $data */
    public function saveConfig(int $tenantId, array $data): array
    {
        $group = trim((string)($data['base_group'] ?? '239.1.1.1'));
        if (!$this->isMulticastIp($group)) {
            return ['ok' => false, 'message' => 'آدرس گروه باید در بازه‌ی 224.0.0.0 تا 239.255.255.255 باشد'];
        }
        // بازه‌ی 239.x محلی است و از روتر هتل بیرون نمی‌رود — امن‌ترین انتخاب
        if (!str_starts_with($group, '239.')) {
            return ['ok' => false, 'message' => 'برای شبکه‌ی داخلی هتل از بازه‌ی 239.x.x.x استفاده کنید'];
        }

        $port = (int)($data['base_port'] ?? 5000);
        if ($port < 1024 || $port > 65000) {
            return ['ok' => false, 'message' => 'پورت باید بین ۱۰۲۴ تا ۶۵۰۰۰ باشد'];
        }

        $udpxy = trim((string)($data['udpxy_url'] ?? ''));
        if ($udpxy !== '' && !preg_match('#^https?://#i', $udpxy)) {
            return ['ok' => false, 'message' => 'آدرس udpxy باید با http شروع شود'];
        }

        $fields = [
            'tenant_id'    => $tenantId,
            'base_group'   => $group,
            'base_port'    => $port,
            // RTP پورت زوج می‌خواهد و پورت فرد را برای RTCP نگه می‌دارد
            'port_step'    => max(1, min(10, (int)($data['port_step'] ?? 2))),
            'ttl'          => max(1, min(64, (int)($data['ttl'] ?? 4))),
            'udpxy_url'    => $udpxy !== '' ? rtrim($udpxy, '/') : null,
            'interface_ip' => trim((string)($data['interface_ip'] ?? '')) ?: null,
            'is_active'    => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 0,
        ];

        $exists = $this->db->row('SELECT id FROM multicast_config WHERE tenant_id = ?', [$tenantId]);
        if ($exists) {
            unset($fields['tenant_id']);
            $this->db->update('multicast_config', $fields, ['tenant_id' => $tenantId]);
        } else {
            $this->db->insert('multicast_config', $fields);
        }

        return ['ok' => true, 'message' => 'تنظیمات multicast ذخیره شد'];
    }

    /**
     * به کانال‌هایی که آدرس multicast ندارند، آدرس تخصیص می‌دهد.
     * آدرس‌های موجود دست‌نخورده می‌مانند تا فهرست کانال تلویزیون‌ها
     * بعد از هر بار اجرا به‌هم نریزد.
     *
     * @return array{ok:bool,assigned:int,message:string}
     */
    public function assignAddresses(int $tenantId, bool $reassignAll = false): array
    {
        $cfg = $this->config($tenantId);

        $channels = $this->db->rows(
            'SELECT id, name, multicast_url FROM iptv_channels
              WHERE tenant_id = ? AND is_active = 1
              ORDER BY channel_no IS NULL, channel_no, sort_order, id',
            [$tenantId]
        );
        if (!$channels) return ['ok' => false, 'assigned' => 0, 'message' => 'کانال فعالی وجود ندارد'];

        // آدرس‌هایی که از قبل گرفته شده‌اند، دوباره تخصیص نمی‌شوند
        $taken = [];
        if (!$reassignAll) {
            foreach ($channels as $c) {
                if (!empty($c['multicast_url'])) $taken[$c['multicast_url']] = true;
            }
        }

        [$g1, $g2, $g3, $g4] = array_map('intval', explode('.', (string)$cfg['base_group']));
        $port = (int)$cfg['base_port'];
        $step = (int)$cfg['port_step'];

        $assigned = 0;
        $index    = 0;

        foreach ($channels as $c) {
            if (!$reassignAll && !empty($c['multicast_url'])) continue;

            // آدرس بعدی آزاد
            do {
                $octet = $g4 + $index;
                $carry = intdiv($octet, 256);
                $addr  = sprintf('%d.%d.%d.%d', $g1, $g2, $g3 + $carry, $octet % 256);
                $url   = "udp://@$addr:" . ($port + $index * $step);
                $index++;
            } while (isset($taken[$url]) && $index < 1000);

            if ($index >= 1000) break;   // بازه تمام شد

            $this->db->update('iptv_channels',
                ['multicast_url' => $url, 'delivery' => 'multicast'],
                ['id' => (int)$c['id'], 'tenant_id' => $tenantId]
            );
            $taken[$url] = true;
            $assigned++;
        }

        return [
            'ok'       => true,
            'assigned' => $assigned,
            'message'  => $assigned ? "$assigned کانال آدرس multicast گرفت" : 'همه کانال‌ها از قبل آدرس داشتند',
        ];
    }

    /**
     * فهرست کانال M3U برای وارد کردن در تلویزیون هتلی.
     * تلویزیون‌های هتلی LG و Samsung فهرست کانال IP را از M3U می‌خوانند.
     *
     * @param 'multicast'|'unicast'|'udpxy' $mode
     */
    public function buildM3u(int $tenantId, string $mode = 'multicast'): string
    {
        $cfg   = $this->config($tenantId);
        $udpxy = (string)($cfg['udpxy_url'] ?? '');

        $channels = $this->db->rows(
            'SELECT name, name_en, logo_url, category, epg_id, channel_no,
                    stream_url, multicast_url, delivery
               FROM iptv_channels
              WHERE tenant_id = ? AND is_active = 1
              ORDER BY channel_no IS NULL, channel_no, sort_order, id',
            [$tenantId]
        );

        $out = "#EXTM3U\n";
        $no  = 0;

        foreach ($channels as $c) {
            $url = match ($mode) {
                'multicast' => (string)($c['multicast_url'] ?? ''),
                'udpxy'     => $this->udpxyUrl($udpxy, (string)($c['multicast_url'] ?? '')),
                default     => (string)$c['stream_url'],
            };
            if ($url === '') continue;

            $no++;
            $number = $c['channel_no'] !== null ? (int)$c['channel_no'] : $no;

            $out .= sprintf(
                '#EXTINF:-1 tvg-chno="%d" tvg-id="%s" tvg-name="%s" tvg-logo="%s" group-title="%s",%s' . "\n",
                $number,
                $this->esc((string)($c['epg_id'] ?? '')),
                $this->esc((string)($c['name_en'] ?: $c['name'])),
                $this->esc((string)($c['logo_url'] ?? '')),
                $this->esc((string)($c['category'] ?? 'general')),
                (string)$c['name']
            );
            $out .= $url . "\n";
        }

        return $out;
    }

    /**
     * محاسبه‌ی بار شبکه — همان عددی که تصمیم multicast را توجیه می‌کند.
     * @return array<string,mixed>
     */
    public function bandwidthEstimate(int $tenantId, int $rooms = 300, string $quality = 'hd'): array
    {
        $mbps = self::BITRATE_MBPS[$quality] ?? self::BITRATE_MBPS['hd'];

        $channels = (int)$this->db->value(
            'SELECT COUNT(*) FROM iptv_channels WHERE tenant_id = ? AND is_active = 1',
            [$tenantId]
        );
        $multicast = (int)$this->db->value(
            "SELECT COUNT(*) FROM iptv_channels
              WHERE tenant_id = ? AND is_active = 1
                AND delivery IN ('multicast','both') AND multicast_url IS NOT NULL",
            [$tenantId]
        );

        // بدترین حالت unicast: همه‌ی اتاق‌ها همزمان تلویزیون زنده می‌بینند
        $unicastPeak = $rooms * $mbps;
        // multicast: هر کانال یک بار، مستقل از تعداد بیننده
        $multicastPeak = $channels * $mbps;

        return [
            'rooms'              => $rooms,
            'quality'            => $quality,
            'per_stream_mbps'    => $mbps,
            'channels'           => $channels,
            'multicast_channels' => $multicast,
            'unicast_peak_mbps'  => round($unicastPeak, 1),
            'multicast_peak_mbps'=> round($multicastPeak, 1),
            'saving_percent'     => $unicastPeak > 0
                ? (int)round((1 - $multicastPeak / $unicastPeak) * 100)
                : 0,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /** udp://@239.1.1.5:5000 → http://10.0.0.5:4022/udp/239.1.1.5:5000 */
    private function udpxyUrl(string $base, string $multicast): string
    {
        if ($base === '' || $multicast === '') return '';

        if (!preg_match('#^(udp|rtp)://@?([\d.]+):(\d+)#', $multicast, $m)) return '';

        $proto = $m[1] === 'rtp' ? 'rtp' : 'udp';
        return rtrim($base, '/') . "/$proto/{$m[2]}:{$m[3]}";
    }

    private function isMulticastIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;

        $first = (int)explode('.', $ip)[0];
        return $first >= 224 && $first <= 239;
    }

    private function esc(string $v): string
    {
        return str_replace('"', "'", trim($v));
    }
}
