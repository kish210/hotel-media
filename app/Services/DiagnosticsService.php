<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * عیب‌یابی سرور هتل — سرویس‌ها، شبکه، پخش زنده.
 *
 * چرا لازم شد: وقتی چیزی روی تلویزیون دیده نمی‌شود، علتش می‌تواند در
 * هفت جای مختلف باشد — سرویسِ خوابیده، مهاجرتِ اجرانشده، پوشه‌ی
 * بدون دسترسی نوشتن، cron خاموش، TVHeadend قطع، IGMP بدون پرس‌وجوگر،
 * یا تلویزیونی که اصلا ضربان نمی‌زند. تا امروز پیدا کردن اینکه کدام
 * یکی است فقط با SSH ممکن بود، و آدمی که پای دستگاه است معمولا SSH
 * ندارد.
 *
 * دو قاعده‌ی این کلاس:
 *
 *   ۱) هیچ بررسی‌ای نباید بقیه را از کار بیندازد. هرکدام در try خودش
 *      است و اگر ابزارش نبود، «قابل بررسی نیست» برمی‌گرداند نه خطا.
 *      صفحه‌ای که وسطش می‌شکند بدتر از نبودنش است، چون همان بخشی که
 *      نشان نداده شاید همان خرابی باشد.
 *
 *   ۲) فقط می‌خواند. هیچ چیزی را روشن، خاموش یا عوض نمی‌کند.
 */
class DiagnosticsService
{
    /** سرویس‌هایی که بدون آن‌ها سیستم کار نمی‌کند */
    private const SERVICES = [
        'nginx'          => 'وب‌سرور',
        'php8.3-fpm'     => 'مفسر PHP',
        'mariadb'        => 'پایگاه داده',
        'cron'           => 'کارهای زمان‌بندی‌شده',
        'hotel-media-ws' => 'سرور WebSocket',
        'tvheadend'      => 'پخش تلویزیون زنده',
    ];

    /** وضعیت‌ها */
    public const OK    = 'ok';
    public const WARN  = 'warn';
    public const FAIL  = 'fail';
    public const UNK   = 'unknown';

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * همه‌ی بررسی‌ها، گروه‌بندی‌شده.
     *
     * @return array<string,array<string,mixed>>
     */
    public function all(): array
    {
        return [
            'services' => $this->section('سرویس‌ها',        fn() => $this->services()),
            'boot'     => $this->section('راه‌اندازی خودکار', fn() => $this->autostart()),
            'app'      => $this->section('برنامه',           fn() => $this->app()),
            'network'  => $this->section('شبکه',             fn() => $this->network()),
            'iptv'     => $this->section('پخش زنده',         fn() => $this->iptv()),
            'screens'  => $this->section('تلویزیون‌ها',       fn() => $this->screens()),
        ];
    }

    /**
     * یک بخش را اجرا می‌کند و شکستش را مهار می‌کند.
     *
     * @param callable():array<int,array<string,mixed>> $fn
     * @return array<string,mixed>
     */
    private function section(string $title, callable $fn): array
    {
        try {
            $checks = $fn();
        } catch (\Throwable $e) {
            $checks = [$this->check('اجرای بررسی', self::UNK, $e->getMessage())];
        }

        /* وضعیت بخش = بدترین وضعیتِ داخلش. اپراتور باید از روی سرفصل
           بفهمد کجا را باز کند، بدون باز کردن همه. */
        $worst = self::OK;
        foreach ($checks as $c) {
            if ($c['status'] === self::FAIL) { $worst = self::FAIL; break; }
            if ($c['status'] === self::WARN) $worst = self::WARN;
            if ($c['status'] === self::UNK && $worst === self::OK) $worst = self::UNK;
        }

        return ['title' => $title, 'status' => $worst, 'checks' => $checks];
    }

    /** @return array<string,mixed> */
    private function check(string $name, string $status, string $detail = '', string $fix = ''): array
    {
        return ['name' => $name, 'status' => $status, 'detail' => $detail, 'fix' => $fix];
    }

    // ══════════════════════════════════════════════════════════════
    //  سرویس‌ها
    // ══════════════════════════════════════════════════════════════

    /** @return array<int,array<string,mixed>> */
    private function services(): array
    {
        $out = [];

        foreach (self::SERVICES as $unit => $label) {
            $active = $this->systemctl('is-active', $unit);

            if ($active === null) {
                $out[] = $this->check("$label ($unit)", self::UNK,
                    'وضعیت خوانده نشد — systemctl در دسترس نیست');
                continue;
            }

            if ($active === 'active') {
                $out[] = $this->check("$label ($unit)", self::OK, 'در حال اجرا');
            } elseif ($active === 'inactive' && $unit === 'tvheadend') {
                /* تنها سرویسی که نبودنش کل سیستم را نمی‌خواباند —
                   هتلی که تلویزیون زنده ندارد هم کار می‌کند. */
                $out[] = $this->check("$label ($unit)", self::WARN, 'خاموش است',
                    "sudo systemctl start $unit");
            } else {
                $out[] = $this->check("$label ($unit)", self::FAIL, "وضعیت: $active",
                    "sudo systemctl start $unit");
            }
        }

        return $out;
    }

    // ══════════════════════════════════════════════════════════════
    //  راه‌اندازی خودکار — همان چیزی که بعد از قطع برق معلوم می‌شود
    // ══════════════════════════════════════════════════════════════

    /** @return array<int,array<string,mixed>> */
    private function autostart(): array
    {
        $out = [];

        foreach (self::SERVICES as $unit => $label) {
            $enabled = $this->systemctl('is-enabled', $unit);

            if ($enabled === null) {
                $out[] = $this->check($label, self::UNK, 'خوانده نشد');
                continue;
            }

            /* «enabled» و «enabled-runtime» و «alias» و «static» همگی
               یعنی بعد از ریستارت بالا می‌آید. فقط disabled و masked
               مشکل‌اند. */
            if (in_array($enabled, ['enabled', 'enabled-runtime', 'alias', 'static', 'indirect'], true)) {
                $out[] = $this->check($label, self::OK, "پس از ریستارت بالا می‌آید ($enabled)");
            } elseif ($enabled === 'masked') {
                $out[] = $this->check($label, self::FAIL, 'مسدود شده (masked)',
                    "sudo systemctl unmask $unit && sudo systemctl enable --now $unit");
            } else {
                $out[] = $this->check($label, self::FAIL,
                    "پس از ریستارت بالا نمی‌آید ($enabled)",
                    "sudo systemctl enable --now $unit");
            }
        }

        return $out;
    }

    // ══════════════════════════════════════════════════════════════
    //  برنامه
    // ══════════════════════════════════════════════════════════════

    /** @return array<int,array<string,mixed>> */
    private function app(): array
    {
        $out = [];

        // ── پایگاه داده ──
        try {
            $this->db->value('SELECT 1');
            $out[] = $this->check('اتصال پایگاه داده', self::OK, 'برقرار');
        } catch (\Throwable $e) {
            $out[] = $this->check('اتصال پایگاه داده', self::FAIL, $e->getMessage());
        }

        // ── مهاجرت‌های اجرانشده ──
        try {
            $applied = [];
            foreach ($this->db->rows('SELECT filename FROM schema_migrations') ?: [] as $r) {
                $applied[(string)$r['filename']] = true;
            }

            $files = glob(ROOT_PATH . '/database/migrations/*.sql') ?: [];
            $pending = [];
            foreach ($files as $f) {
                $name = basename($f);
                /* همان فایلی که db:migrate هم عمدا رد می‌کند: نسخه‌ی
                   قدیمیِ 001_complete_schema است. بدون این استثنا، این
                   بررسی روی هر نصبِ سالمی قرمز می‌ماند — و هشداری که
                   همیشه قرمز است، آموزش می‌دهد که نادیده گرفته شود. */
                if ($name === '001_initial_schema.sql') continue;
                if (!isset($applied[$name])) $pending[] = $name;
            }

            if (!$pending) {
                $out[] = $this->check('مهاجرت‌های پایگاه داده', self::OK,
                    count($files) . ' مهاجرت، همه اعمال شده');
            } else {
                /* این دقیقا همان حالتی است که ستون‌های تازه وجود ندارند
                   و ویژگی‌ها بی‌صدا کار نمی‌کنند. */
                $out[] = $this->check('مهاجرت‌های پایگاه داده', self::FAIL,
                    count($pending) . ' اجرا نشده: ' . implode('، ', array_slice($pending, 0, 4))
                    . (count($pending) > 4 ? ' …' : ''),
                    'php artisan db:migrate');
            }
        } catch (\Throwable $e) {
            $out[] = $this->check('مهاجرت‌های پایگاه داده', self::UNK, $e->getMessage());
        }

        // ── پوشه‌های نوشتنی ──
        foreach ([
            'storage/logs'            => 'گزارش‌ها',
            'storage/cache'           => 'حافظه‌ی موقت',
            'storage/sessions'        => 'نشست‌ها',
            'public/uploads/media'    => 'رسانه',
            'public/uploads/branding' => 'لوگو و پس‌زمینه',
        ] as $rel => $label) {
            $abs = ROOT_PATH . '/' . $rel;
            if (!is_dir($abs)) {
                $out[] = $this->check("پوشه‌ی $label", self::FAIL, "وجود ندارد: $rel",
                    "sudo -u www-data mkdir -p $rel");
            } elseif (!is_writable($abs)) {
                $out[] = $this->check("پوشه‌ی $label", self::FAIL, "قابل نوشتن نیست: $rel",
                    "sudo chown -R www-data:www-data $rel");
            } else {
                $out[] = $this->check("پوشه‌ی $label", self::OK, 'قابل نوشتن');
            }
        }

        // ── دارایی‌های محلی ──
        // هتل بدون اینترنت اگر این‌ها نباشند پنل را بی‌استایل می‌بیند
        $vendor = PUBLIC_PATH . '/assets/vendor';
        $count  = is_dir($vendor) ? count(glob($vendor . '/*') ?: []) : 0;
        $out[] = $count > 0
            ? $this->check('دارایی‌های محلی پنل', self::OK, "$count پوشه")
            : $this->check('دارایی‌های محلی پنل', self::FAIL,
                'public/assets/vendor خالی است — پنل بدون استایل بالا می‌آید');

        // ── فضای دیسک ──
        try {
            $free  = @disk_free_space(ROOT_PATH);
            $total = @disk_total_space(ROOT_PATH);
            if ($free && $total) {
                $pct = (int)round($free / $total * 100);
                /* آکولاد لازم است: «٪» بایتِ بالای 0x80 دارد و PHP آن را
                   حرفِ معتبرِ نام متغیر می‌شمارد، پس "$pct٪" به متغیرِ
                   ناموجودِ «pct٪» تبدیل می‌شود و درصد خالی چاپ می‌شد. */
                $txt = $this->bytes((int)$free) . ' آزاد از ' . $this->bytes((int)$total) . " ({$pct}٪)";
                /* ویدیوی تبلیغاتی حجیم است و پر شدن دیسک اول از همه
                   آپلود را می‌شکند، بعد گزارش‌ها را. */
                $out[] = $this->check('فضای دیسک',
                    $pct < 5 ? self::FAIL : ($pct < 15 ? self::WARN : self::OK), $txt);
            }
        } catch (\Throwable) {}

        // ── کارهای زمان‌بندی‌شده ──
        $cron = $this->run('crontab -l 2>/dev/null; cat /etc/cron.d/hotel-media 2>/dev/null');
        if ($cron === null) {
            $out[] = $this->check('کارهای زمان‌بندی‌شده', self::UNK, 'خوانده نشد');
        } else {
            $need = ['monitor:screens', 'flights:sync', 'epg:sync', 'news:sync'];
            $miss = [];
            foreach ($need as $t) if (!str_contains($cron, $t)) $miss[] = $t;

            $out[] = $miss
                ? $this->check('کارهای زمان‌بندی‌شده', self::WARN,
                    'ثبت نشده: ' . implode('، ', $miss),
                    'بخش cron در deploy/install-production.sh')
                : $this->check('کارهای زمان‌بندی‌شده', self::OK, 'همه ثبت شده‌اند');
        }

        // ── نسخه‌ی PHP ──
        $out[] = version_compare(PHP_VERSION, '8.1', '>=')
            ? $this->check('نسخه‌ی PHP', self::OK, PHP_VERSION)
            : $this->check('نسخه‌ی PHP', self::FAIL, PHP_VERSION . ' — حداقل ۸.۱ لازم است');

        return $out;
    }

    // ══════════════════════════════════════════════════════════════
    //  شبکه
    // ══════════════════════════════════════════════════════════════

    /** @return array<int,array<string,mixed>> */
    private function network(): array
    {
        $out = [];

        // ── نشانی‌های سرور ──
        $ips = $this->run("ip -4 -o addr show scope global 2>/dev/null | awk '{print \$2\": \"\$4}'");
        $out[] = $ips !== null && trim($ips) !== ''
            ? $this->check('نشانی IP سرور', self::OK, str_replace("\n", '  ·  ', trim($ips)))
            : $this->check('نشانی IP سرور', self::UNK, 'خوانده نشد');

        // ── دروازه ──
        $gw = $this->run("ip route show default 2>/dev/null | head -1");
        $out[] = $gw !== null && trim($gw) !== ''
            ? $this->check('دروازه‌ی پیش‌فرض', self::OK, trim($gw))
            : $this->check('دروازه‌ی پیش‌فرض', self::WARN, 'تعریف نشده');

        // ── DNS ──
        $dns = $this->run("resolvectl status 2>/dev/null | grep -m3 'DNS Servers' || grep -E '^nameserver' /etc/resolv.conf 2>/dev/null | head -3");
        $out[] = $dns !== null && trim($dns) !== ''
            ? $this->check('DNS', self::OK, str_replace("\n", '  ·  ', trim($dns)))
            : $this->check('DNS', self::WARN, 'تعریف نشده');

        // ── پورت‌های شنونده ──
        $ports = [80 => 'وب', 3306 => 'پایگاه داده', 8080 => 'WebSocket', 9981 => 'TVHeadend'];
        foreach ($ports as $p => $label) {
            $open = $this->portOpen('127.0.0.1', $p);
            $out[] = $open
                ? $this->check("پورت $p ($label)", self::OK, 'باز')
                : $this->check("پورت $p ($label)", $p === 9981 ? self::WARN : self::FAIL, 'بسته');
        }

        // ── اینترنت ──
        // برای به‌روزرسانی از گیت‌هاب و دریافت پرواز لازم است، ولی
        // نبودنش پخش داخل هتل را نمی‌خواباند.
        $out[] = $this->portOpen('github.com', 443, 4)
            ? $this->check('دسترسی اینترنت', self::OK, 'github.com در دسترس')
            : $this->check('دسترسی اینترنت', self::WARN,
                'در دسترس نیست — به‌روزرسانی و دریافت پرواز کار نمی‌کند');

        // ── پرس‌وجوگر IGMP ──
        /* مولتی‌کست بدون دقیقا یک querier در VLAN کار نمی‌کند. این
           بررسی فقط سمت سرور را می‌بیند؛ تنظیم واقعی روی سوییچ است. */
        $snoop = $this->run("cat /sys/class/net/*/bridge/multicast_querier 2>/dev/null | head -1");
        $out[] = $snoop !== null && trim($snoop) !== ''
            ? $this->check('IGMP روی سرور', self::OK, 'multicast_querier = ' . trim($snoop))
            : $this->check('IGMP روی سرور', self::UNK,
                'پل شبکه‌ای ندارد — querier باید روی سوییچ تنظیم باشد (VLAN 80)');

        return $out;
    }

    // ══════════════════════════════════════════════════════════════
    //  پخش زنده
    // ══════════════════════════════════════════════════════════════

    /** @return array<int,array<string,mixed>> */
    private function iptv(): array
    {
        $out = [];

        $out[] = $this->portOpen('127.0.0.1', 9981)
            ? $this->check('رابط TVHeadend', self::OK, 'پورت ۹۹۸۱ پاسخ می‌دهد')
            : $this->check('رابط TVHeadend', self::WARN, 'پاسخ نمی‌دهد',
                'sudo systemctl start tvheadend');

        try {
            $n = (int)$this->db->value('SELECT COUNT(*) FROM iptv_channels WHERE is_active=1');
            $out[] = $n > 0
                ? $this->check('کانال‌های فعال', self::OK, "$n کانال")
                : $this->check('کانال‌های فعال', self::WARN, 'هیچ کانالی تعریف نشده');
        } catch (\Throwable $e) {
            $out[] = $this->check('کانال‌های فعال', self::UNK, $e->getMessage());
        }

        return $out;
    }

    // ══════════════════════════════════════════════════════════════
    //  تلویزیون‌ها
    // ══════════════════════════════════════════════════════════════

    /** @return array<int,array<string,mixed>> */
    private function screens(): array
    {
        $out = [];

        try {
            $r = $this->db->row(
                "SELECT COUNT(*) AS همه,
                        SUM(CASE WHEN last_seen_at > NOW() - INTERVAL 3 MINUTE THEN 1 ELSE 0 END) AS زنده
                   FROM screens WHERE status != 'inactive'"
            ) ?: [];

            $total = (int)($r['همه'] ?? 0);
            $live  = (int)($r['زنده'] ?? 0);

            if ($total === 0) {
                $out[] = $this->check('تلویزیون‌ها', self::WARN, 'هیچ صفحه‌ای ثبت نشده');
            } elseif ($live === $total) {
                $out[] = $this->check('تلویزیون‌ها', self::OK, "$live از $total آنلاین");
            } else {
                $out[] = $this->check('تلویزیون‌ها',
                    $live === 0 ? self::FAIL : self::WARN,
                    "$live از $total آنلاین — بقیه در ۳ دقیقه‌ی گذشته ضربان نزده‌اند");
            }

            /* فرمانِ مانده در صف یعنی تلویزیون ضربان نمی‌زند. این
               همان حالتی است که «پنل کار کرد ولی تلویزیون عوض نشد». */
            $stuck = (int)$this->db->value(
                "SELECT COUNT(*) FROM screens
                  WHERE (reboot_requested=1 OR refresh_requested=1)
                    AND (last_seen_at IS NULL OR last_seen_at < NOW() - INTERVAL 3 MINUTE)"
            );
            if ($stuck > 0) {
                $out[] = $this->check('فرمان‌های معلق', self::WARN,
                    "$stuck صفحه فرمانی در صف دارد ولی ضربان نمی‌زند");
            }
        } catch (\Throwable $e) {
            $out[] = $this->check('تلویزیون‌ها', self::UNK, $e->getMessage());
        }

        return $out;
    }

    // ══════════════════════════════════════════════════════════════
    //  ابزارها
    // ══════════════════════════════════════════════════════════════

    /**
     * systemctl فقط برای خواندن. کاربر بدون دسترسی ریشه هم می‌تواند
     * وضعیت را بپرسد، پس sudo لازم نیست.
     */
    private function systemctl(string $verb, string $unit): ?string
    {
        /* نام یونیت از ثابتِ داخل همین کلاس می‌آید، ولی escape می‌شود
           تا اگر روزی از ورودی آمد، همین‌جا امن باشد. */
        $o = $this->run('systemctl ' . $verb . ' ' . escapeshellarg($unit) . ' 2>&1');
        if ($o === null) return null;

        $o = trim($o);
        return $o === '' ? null : explode("\n", $o)[0];
    }

    /** اجرای امنِ فرمان — اگر shell_exec نباشد null می‌دهد، نه خطا */
    private function run(string $cmd): ?string
    {
        if (!function_exists('shell_exec')) return null;

        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        if (in_array('shell_exec', $disabled, true)) return null;

        try {
            $o = @shell_exec($cmd);
        } catch (\Throwable) {
            return null;
        }

        return $o === null || $o === false ? null : (string)$o;
    }

    private function portOpen(string $host, int $port, int $timeout = 2): bool
    {
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($fp === false) return false;
        fclose($fp);
        return true;
    }

    private function bytes(int $n): string
    {
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($n >= 1024 && $i < count($u) - 1) { $n = (int)($n / 1024); $i++; }
        return $n . ' ' . $u[$i];
    }
}
