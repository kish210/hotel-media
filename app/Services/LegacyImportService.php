<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Legacy Import Service — ورود دادهٔ سیستم قدیمی (VINAK / sms) به هتل مدیا.
 *
 * منبع داده، پوشهٔ پشتیبان همان سرور است:
 *
 *     conf/channels.json   ۳۹ کانال DVB-T + خروجی‌ها
 *     conf/users.json      ۳۲۸ اتاق/کاربر با IP و زبان
 *     conf/configs.json    شبکه، multicast، PMS، منطقهٔ زمانی
 *     assets/chan_logo     لوگوهای ۵۰×۵۰
 *     conf/user_data_*.sqlite3  کاتالوگ VOD و نظرسنجی
 *
 * ── سه تصمیم که عمدا این‌طور گرفته شد ────────────────────────────
 *
 *  ۱. **هیچ‌چیز پاک نمی‌شود.** حالت پیش‌فرض `merge` است: ردیف موجود
 *     با همان `legacy_ref` به‌روز می‌شود، نه حذف و درج دوباره. اجرای
 *     دوبارهٔ مهاجرت باید بی‌خطر باشد، وگرنه اپراتور دستی تنظیمات
 *     کانال را از دست می‌دهد.
 *
 *  ۲. **فایل ویدیو وارد نمی‌شود، فقط فهرست.** ردیف‌های VOD دیتابیس
 *     قدیمی به فایل‌هایی روی دیسک آن سرور اشاره می‌کردند که همراه
 *     پشتیبان نیستند. واردکردنشان یعنی هزاران کارت «فیلم» که هیچ‌کدام
 *     پخش نمی‌شود. در عوض کاتالوگ به CSV صادر می‌شود.
 *
 *  ۳. **stream_url از multicast ساخته می‌شود.** سیستم قدیمی هر کانال
 *     را یک‌بار روی `239.2.1.x:3200` می‌فرستاد و ۳۰۰ تلویزیون همان را
 *     می‌گرفتند. این آدرس‌ها در `outputs` نبودند؛ از `net_multicast` و
 *     ترتیب کانال ساخته می‌شدند — همان قاعده اینجا.
 */
final class LegacyImportService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // ══════════════════════════════════════════════════════════════
    //  کشف منبع
    // ══════════════════════════════════════════════════════════════

    /**
     * پوشهٔ conf را پیدا می‌کند — چه مسیر خودش داده شود، چه ریشهٔ
     * پشتیبان‌ها. جدیدترین `channels.json` برنده است: پشتیبان‌های
     * قدیمی هم در همان پوشه‌اند و ورود دادهٔ کهنه یعنی از دست دادن
     * کانال‌هایی که بعدا اضافه شده‌اند.
     *
     * @return array{ok:bool,conf:string,root:string,message:string,files:array<string,bool>}
     */
    public function discover(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $out  = ['ok' => false, 'conf' => '', 'root' => $root, 'message' => '', 'files' => []];

        if ($root === '' || !is_dir($root)) {
            $out['message'] = 'مسیر پیدا نشد: ' . $root;
            return $out;
        }

        $candidates = [];
        foreach ([$root, $root . '/conf', $root . '/www/conf'] as $dir) {
            if (is_file($dir . '/channels.json')) $candidates[] = $dir;
        }
        if (!$candidates) $candidates = $this->scanForChannels($root, 0);

        if (!$candidates) {
            $out['message'] = 'در این مسیر channels.json پیدا نشد. مسیر پوشهٔ conf سیستم قدیمی را بدهید.';
            return $out;
        }

        usort($candidates, static fn($a, $b) => filemtime($b . '/channels.json') <=> filemtime($a . '/channels.json'));
        $conf = $candidates[0];

        $out['ok']   = true;
        $out['conf'] = $conf;
        foreach (['channels.json', 'users.json', 'configs.json'] as $f) {
            $out['files'][$f] = is_file($conf . '/' . $f);
        }
        return $out;
    }

    /** @return list<string> */
    private function scanForChannels(string $dir, int $depth): array
    {
        if ($depth > 6) return [];
        $found = [];
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $sub) {
            if (is_file($sub . '/channels.json')) { $found[] = $sub; continue; }
            foreach ($this->scanForChannels($sub, $depth + 1) as $f) $found[] = $f;
        }
        return $found;
    }

    // ══════════════════════════════════════════════════════════════
    //  اجرا
    // ══════════════════════════════════════════════════════════════

    /**
     * @param array{mode?:string,tenant_id?:int,logos?:bool,teletext?:bool,settings?:bool} $opt
     * @return array{ok:bool,message:string,report:array<string,mixed>}
     */
    public function run(string $root, array $opt = []): array
    {
        $mode     = in_array($opt['mode'] ?? 'merge', ['merge', 'replace', 'dry'], true) ? (string)$opt['mode'] : 'merge';
        $tenantId = (int)($opt['tenant_id'] ?? 1);
        $dry      = $mode === 'dry';

        $src = $this->discover($root);
        if (!$src['ok']) return ['ok' => false, 'message' => $src['message'], 'report' => []];

        $conf         = $src['conf'];
        $channelsJson = $this->json($conf . '/channels.json');
        $usersJson    = $this->jsonArr($conf . '/users.json');
        $configsJson  = $this->json($conf . '/configs.json');

        if ($channelsJson === null && $usersJson === [] && $configsJson === null) {
            return ['ok' => false, 'message' => 'هیچ فایل داده‌ای خوانده نشد در ' . $conf, 'report' => []];
        }

        $report = [
            'source' => $conf, 'mode' => $mode,
            'channels' => 0, 'rooms' => 0, 'logos' => 0, 'settings' => 0,
            'teletext' => 0, 'vod' => 0, 'warnings' => [], 'at' => date('c'),
        ];

        try {
            if ($opt['settings'] ?? true) {
                $report['settings'] = $dry ? 0 : $this->importSettings($configsJson, $tenantId);
            }
            if ($channelsJson !== null) {
                $report['channels'] = $this->importChannels($channelsJson, $configsJson, $tenantId, $dry);
            }
            if ($usersJson) {
                $report['rooms'] = $this->importRooms($usersJson, $tenantId, $dry);
            }
            if ($opt['teletext'] ?? false) {
                foreach ([$conf, dirname($conf) . '/www/conf', $src['root'] . '/app/opt/sms/www/conf'] as $tDir) {
                    if (!is_dir($tDir) || !glob($tDir . '/teletext*.conf')) continue;
                    $report['teletext'] = $dry ? 0 : ((new TeletextService($this->db))->importDir($tDir, $tenantId)['pages'] ?? 0);
                    break;
                }
            }
            if ($opt['logos'] ?? false) {
                $report['logos'] = $this->importLogos($src['root'], $dry);
            }
            $report['vod'] = $this->exportVodCatalog($src['root'], $dry);

            if ($dry) $report['warnings'][] = 'حالت آزمایشی: هیچ تغییری در دیتابیس نوشته نشد';
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'مهاجرت نیمه‌کاره ماند: ' . $e->getMessage(), 'report' => $report];
        }

        if (!$dry) {
            try {
                $this->db->insert('legacy_import_runs', [
                    'tenant_id' => $tenantId,
                    'source'    => mb_substr($conf, 0, 300),
                    'mode'      => $mode,
                    'channels'  => $report['channels'],
                    'rooms'     => $report['rooms'],
                    'logos'     => $report['logos'],
                    'teletext'  => $report['teletext'],
                    'vod'       => $report['vod'],
                    'summary'   => json_encode($report, JSON_UNESCAPED_UNICODE),
                ]);
            } catch (\Throwable) { /* گزارش نباید مهاجرت را خراب کند */ }
        }

        $msg = sprintf(
            'مهاجرت انجام شد: %d کانال، %d اتاق، %d لوگو، %d تنظیم',
            $report['channels'], $report['rooms'], $report['logos'], $report['settings']
        );

        return ['ok' => true, 'message' => $msg, 'report' => $report];
    }

    public function history(int $tenantId = 1, int $limit = 10): array
    {
        try {
            return $this->db->rows(
                'SELECT * FROM legacy_import_runs WHERE tenant_id = ? ORDER BY id DESC LIMIT ' . max(1, min(50, $limit)),
                [$tenantId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  کانال‌ها
    // ══════════════════════════════════════════════════════════════

    /** @param array<string,mixed> $channelsJson @param array<string,mixed>|null $configs */
    private function importChannels(array $channelsJson, ?array $configs, int $tenantId, bool $dry): int
    {
        $dvb = is_array($channelsJson['dvb'] ?? null) ? $channelsJson['dvb'] : [];
        if (!$dvb) return 0;

        $multicast = (string)($configs['network']['net_multicast'] ?? '239.2.1.1');
        $port      = 3200;
        $count     = 0;

        foreach ($dvb as $i => $ch) {
            $name = trim((string)($ch['name'] ?? ''));
            if ($name === '') continue;

            $ref = 'DVBT' . (int)($ch['dvb_id'] ?? 0) . ':' . (int)($ch['sid'] ?? 0);

            $row = [
                'tenant_id'  => $tenantId,
                'name'       => mb_substr($name, 0, 255),
                'stream_url' => $this->multicastUrl($multicast, $i + 1, $port),
                'logo_url'   => $this->logoFor($name),
                'category'   => 'legacy',
                'protocol'   => 'udp',
                'epg_id'     => null,
                'sort_order' => (int)$i,
                'is_active'  => 1,
                'legacy_ref' => $ref,
            ];

            if ($dry) { $count++; continue; }

            $existing = $this->db->row(
                'SELECT id FROM iptv_channels WHERE tenant_id = ? AND legacy_ref = ? LIMIT 1',
                [$tenantId, $ref]
            );
            if (!$existing) {
                /* کانال همنام از TVHeadend ممکن است از قبل باشد؛ دوباره نساز */
                $existing = $this->db->row(
                    'SELECT id FROM iptv_channels WHERE tenant_id = ? AND name = ? LIMIT 1',
                    [$tenantId, $row['name']]
                );
            }

            if ($existing) {
                $this->db->update('iptv_channels', $row, ['id' => (int)$existing['id']]);
            } else {
                $this->db->insert('iptv_channels', $row);
            }
            $count++;
        }

        return $count;
    }

    /** ‏239.2.1.1 + ۳ → 239.2.1.4 (آخرین اکتت، بدون سرریز) */
    private function multicastUrl(string $base, int $offset, int $port): string
    {
        $parts = explode('.', $base);
        if (count($parts) !== 4) return 'udp://@' . $base . ':' . $port;

        $parts[3] = (string)(((int)end($parts) + $offset) % 250);
        return 'udp://@' . implode('.', $parts) . ':' . $port;
    }

    private function logoFor(string $name): ?string
    {
        $found = ChannelLogoService::find($name);
        if ($found) return $found;

        $slug = ChannelLogoService::slug($name);
        $file = PUBLIC_PATH . '/assets/channel-logos/' . $slug . '.png';
        return is_file($file) ? ChannelLogoService::URL_BASE . $slug . '.png' : null;
    }

    // ══════════════════════════════════════════════════════════════
    //  اتاق‌ها
    // ══════════════════════════════════════════════════════════════

    /** @param list<array<string,mixed>> $users */
    private function importRooms(array $users, int $tenantId, bool $dry): int
    {
        $langs = ['farsi' => 'fa', 'persian' => 'fa', 'english' => 'en', 'arabic' => 'ar'];
        $count = 0;

        foreach ($users as $u) {
            $number = trim((string)($u['id'] ?? ''));
            if ($number === '') continue;

            $lang = strtolower(trim((string)($u['language'] ?? 'fa')));
            $lang = $langs[$lang] ?? (in_array($lang, ['fa', 'en', 'ar'], true) ? $lang : 'fa');

            $row = [
                'tenant_id'   => $tenantId,
                'room_number' => mb_substr($number, 0, 20),
                'room_name'   => mb_substr(trim((string)($u['name'] ?? '')), 0, 100) ?: null,
                'guest_lang'  => $lang,
                'status'      => 'available',
                'legacy_ref'  => mb_substr('user:' . $number, 0, 64),
                'legacy_ip'   => mb_substr(trim((string)($u['ipaddress'] ?? '')), 0, 45) ?: null,
            ];

            if ($dry) { $count++; continue; }

            $existing = $this->db->row(
                'SELECT id FROM iptv_rooms WHERE tenant_id = ? AND room_number = ? LIMIT 1',
                [$tenantId, $row['room_number']]
            );

            if ($existing) {
                /* وضعیت مهمان دست نمی‌خورد: ممکن است همین الان کسی
                   داخل اتاق باشد و مهاجرت او را «آزاد» نکند. */
                unset($row['status']);
                $this->db->update('iptv_rooms', $row, ['id' => (int)$existing['id']]);
            } else {
                $this->db->insert('iptv_rooms', $row);
            }
            $count++;
        }

        return $count;
    }

    // ══════════════════════════════════════════════════════════════
    //  تنظیمات
    // ══════════════════════════════════════════════════════════════

    /** @param array<string,mixed>|null $configs */
    private function importSettings(?array $configs, int $tenantId): int
    {
        if ($configs === null) return 0;

        $count = 0;
        foreach ($configs as $section => $values) {
            if (!is_array($values)) continue;
            /* هش رمز ادمین قدیمی منتقل نمی‌شود: الگوریتمش با bcrypt
               هتل مدیا یکی نیست و نگه‌داشتنش فقط سوءاستفاده می‌کند. */
            if ($section === 'login') continue;

            foreach ($values as $key => $value) {
                if (is_array($value))      $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                elseif (is_bool($value))   $value = $value ? '1' : '0';

                $this->db->query(
                    'INSERT INTO legacy_settings (tenant_id, skey, sval, source)
                     VALUES (?, ?, ?, "legacy")
                     ON DUPLICATE KEY UPDATE sval = VALUES(sval), updated_at = NOW()',
                    [$tenantId, $section . '.' . $key, (string)$value]
                );
                $count++;
            }
        }

        /* اتصال PMS قدیمی به pms_integrations هم ترجمه می‌شود، وگرنه
           اپراتور باید آدرس و کاربری را دستی دوباره وارد کند. */
        $pms = $configs['middleware']['pms'] ?? null;
        if (is_array($pms) && !empty($pms['ip'])) {
            $name   = (string)($pms['name'] ?? 'legacy');
            $exists = $this->db->row(
                'SELECT id FROM pms_integrations WHERE tenant_id = ? AND name = ? LIMIT 1',
                [$tenantId, mb_substr($name, 0, 100)]
            );
            $data = [
                'tenant_id'    => $tenantId,
                'name'         => mb_substr($name, 0, 100),
                'pms_type'     => stripos($name, 'asa') !== false ? 'asa' : 'custom',
                'is_active'    => 1,
                'base_url'     => 'http://' . $pms['ip'],
                'auth_type'    => 'basic',
                'auth_secret'  => isset($pms['user'], $pms['pass']) ? $pms['user'] . ':' . $pms['pass'] : null,
            ];
            if ($exists) {
                $this->db->update('pms_integrations', $data, ['id' => (int)$exists['id']]);
            } else {
                $data['api_key'] = bin2hex(random_bytes(16));
                $this->db->insert('pms_integrations', $data);
            }
        }

        return $count;
    }

    // ══════════════════════════════════════════════════════════════
    //  لوگوها
    // ══════════════════════════════════════════════════════════════

    /** لوگوها فقط اگر موجود نباشند کپی می‌شوند. */
    private function importLogos(string $root, bool $dry): int
    {
        $dest = PUBLIC_PATH . '/assets/channel-logos';
        if (!is_dir($dest) && !$dry && !@mkdir($dest, 0775, true)) return 0;

        $sources = [
            $root . '/assets/chan_logo',
            $root . '/app/opt/sms/www/conf/logo',
            dirname($root) . '/chan_logo',
        ];

        $count = 0;
        foreach ($sources as $dir) {
            if (!is_dir($dir)) continue;
            foreach (glob($dir . '/*.{png,jpg,jpeg}', GLOB_BRACE) ?: [] as $file) {
                $slug = ChannelLogoService::slug(strtolower(pathinfo($file, PATHINFO_FILENAME)));
                if ($slug === '') continue;

                $ext    = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $target = $dest . '/' . $slug . ($ext === 'png' ? '.png' : '.jpg');
                if (is_file($dest . '/' . $slug . '.png') || is_file($dest . '/' . $slug . '.jpg')) continue;

                $count++;
                if (!$dry) @copy($file, $target);
            }
        }

        return $count;
    }

    // ══════════════════════════════════════════════════════════════
    //  کاتالوگ VOD
    // ══════════════════════════════════════════════════════════════

    /** فهرست فیلم‌های قدیمی را به CSV می‌نویسد (دلیلش بالای کلاس). */
    private function exportVodCatalog(string $root, bool $dry): int
    {
        $files = array_merge(
            glob($root . '/conf/user_data_*.sqlite3') ?: [],
            glob($root . '/*/user_data_*.sqlite3') ?: []
        );
        $files = array_values(array_unique($files));
        if (!$files) return 0;

        usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        $rows = [];

        foreach ($files as $file) {
            try {
                $pdo = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                if (!in_array('media', $tables, true)) continue;

                $rows = $pdo->query('SELECT * FROM media')->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if ($rows) break;
            } catch (\Throwable) {
                continue;
            }
        }

        if (!$rows) return 0;
        if ($dry) return count($rows);

        $dir = STORAGE_PATH . '/legacy';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $fh = @fopen($dir . '/vod-catalog.csv', 'wb');
        if (!$fh) return 0;

        fwrite($fh, "\xEF\xBB\xBF"); // BOM تا Excel فارسی را درست باز کند
        fputcsv($fh, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($fh, array_values($r));
        fclose($fh);

        return count($rows);
    }

    // ══════════════════════════════════════════════════════════════
    //  ابزار
    // ══════════════════════════════════════════════════════════════

    /** @return array<string,mixed>|null */
    private function json(string $file): ?array
    {
        if (!is_file($file)) return null;
        $raw = @file_get_contents($file);
        if ($raw === false) return null;
        $d = json_decode($raw, true);
        return is_array($d) ? $d : null;
    }

    /** @return list<array<string,mixed>> */
    private function jsonArr(string $file): array
    {
        $d = $this->json($file);
        return $d !== null && array_is_list($d) ? $d : [];
    }
}
