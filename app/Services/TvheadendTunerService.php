<?php
declare(strict_types=1);
namespace App\Services;

/**
 * راه‌اندازی و اسکن تیونر DVB-T از داخل پنل، بدون SSH و بدون نصب مجدد.
 *
 * هر کاری که این کلاس می‌کند فقط از API رسمی TVHeadend است و به هیچ فایل یا
 * تنظیم داخل خود TVHeadend دست نمی‌زند. به همین دلیل با به‌روزرسانی TVHeadend
 * نمی‌شکند. به‌طور خاص:
 *
 *   • فایل اسکن (dvb-scan) لازم نیست. TVHeadend فهرست آن را فقط هنگام شروع
 *     می‌خواند و برای فایل تازه ریستارت می‌خواست. به‌جایش شبکه را بدون
 *     scanfile می‌سازیم و فرکانس‌ها را با `mux_create` خودمان می‌گذاریم؛
 *     نه ریستارت لازم است نه دسترسی root.
 *   • پارامترها AUTO هستند: مدولاسیون/نرخ کد ثابت (مثل QAM64 و 2/3) فرستندهٔ
 *     دیگر را قفل نمی‌کند و همین اسکن اول را صفر کرد.
 *   • تیونر وصل‌شده بعدا هم فقط با دکمهٔ «راه‌اندازی» به شبکه وصل می‌شود.
 */
final class TvheadendTunerService
{
    public const NETWORK_NAME = 'Hotel Media — DVB-T';

    private TvheadendService $tvh;

    public function __construct(?TvheadendService $tvh = null)
    {
        $this->tvh = $tvh ?? new TvheadendService();
    }

    /**
     * فرکانس‌های آماده (مگاهرتز). `t2` یعنی فقط با تیونر T2 معنا دارد.
     * Kish: از اندازه‌گیری واقعی با تیونر AF9033 (۵۷۸ و ۶۸۲ قفل کامل).
     *
     * @return array<string,array{label:string,mhz:int[],t2?:int[]}>
     */
    public static function presets(): array
    {
        $uhf = [];
        for ($f = 474; $f <= 858; $f += 8) { $uhf[] = $f; }
        return [
            'kish' => [
                'label' => 'جزیره کیش (۵۷۸، ۶۸۲، ۷۳۰ + T2: ۷۵۴)',
                'mhz'   => [578, 682, 730],
                't2'    => [754],
            ],
            'iran_uhf' => [
                'label' => 'کل باند UHF ایران (۴۷۴ تا ۸۵۸، پله ۸ مگاهرتز — کندتر)',
                'mhz'   => $uhf,
            ],
        ];
    }

    /** منبع فعال TVHeadend این tenant (یا null). */
    public function source(int $tenantId): ?array
    {
        $db = \App\Core\Database::getInstance();
        $r = $db->row('SELECT * FROM tvheadend_sources WHERE tenant_id = ? AND is_active = 1 ORDER BY id LIMIT 1', [$tenantId]);
        return $r ?: null;
    }

    /**
     * وضعیت زندهٔ تیونر، شبکه و اسکن.
     * @return array<string,mixed>
     */
    public function status(array $src): array
    {
        $out = ['reachable' => false, 'error' => '', 'inputs' => [], 'network' => null,
                'frontends' => [], 'has_t2' => false];

        $in = $this->tvh->call($src, 'status/inputs');
        if (!$in['ok']) { $out['error'] = $in['error']; return $out; }
        $out['reachable'] = true;
        foreach (($in['data']['entries'] ?? []) as $e) {
            $name = (string)($e['input'] ?? '');
            $out['inputs'][] = [
                'name'   => $name,
                'subs'   => (int)($e['subs'] ?? 0),
                'signal' => (int)($e['signal'] ?? 0),
                'snr'    => (int)($e['snr'] ?? 0),
                'ber'    => (int)($e['ber'] ?? 0),
                'unc'    => (int)($e['unc'] ?? 0),
            ];
            if (stripos($name, 'T2') !== false) { $out['has_t2'] = true; }
        }

        $net = $this->network($src);
        if ($net) {
            $out['network'] = [
                'uuid'   => (string)$net['uuid'],
                'name'   => (string)($net['networkname'] ?? ''),
                'mux'    => (int)($net['num_mux'] ?? 0),
                'svc'    => (int)($net['num_svc'] ?? 0),
                'chn'    => (int)($net['num_chn'] ?? 0),
                'queue'  => (int)($net['scanq_length'] ?? 0),
            ];
        }
        $out['frontends'] = $this->frontends($src);
        return $out;
    }

    /** شبکهٔ DVB-T ما (یا هر شبکهٔ Hotel Media قدیمی‌تر). */
    private function network(array $src): ?array
    {
        $r = $this->tvh->call($src, 'mpegts/network/grid', ['limit' => 100]);
        if (!$r['ok']) return null;
        $first = null;
        foreach (($r['data']['entries'] ?? []) as $n) {
            $name = (string)($n['networkname'] ?? '');
            if ($name === self::NETWORK_NAME) return $n;
            // شبکه‌ای که نسخهٔ قبلی با scanfile ساخته (Hotel Media — ir-Kish)
            if ($first === null && str_starts_with($name, 'Hotel Media')) $first = $n;
        }
        return $first;
    }

    /**
     * فرانت‌اندهای تیونر از درخت سخت‌افزار.
     * @return array<int,array{uuid:string,name:string,enabled:bool,linked:bool}>
     */
    public function frontends(array $src, ?string $netUuid = null): array
    {
        $found = [];
        $root = $this->tvh->call($src, 'hardware/tree', ['uuid' => 'root']);
        if (!$root['ok']) return $found;
        foreach ((array)$root['data'] as $adapter) {
            $au = (string)($adapter['uuid'] ?? '');
            if ($au === '') continue;
            $kids = $this->tvh->call($src, 'hardware/tree', ['uuid' => $au]);
            foreach ((array)($kids['data'] ?? []) as $fe) {
                $fu = (string)($fe['uuid'] ?? '');
                if ($fu === '') continue;
                $p = $this->params($src, $fu);
                if ($p === null || !array_key_exists('networks', $p)) continue;   // فرانت‌اند نیست
                $found[] = [
                    'uuid'    => $fu,
                    'name'    => (string)($fe['text'] ?? $fu),
                    'enabled' => ($p['enabled'] ?? false) === true,
                    'linked'  => $netUuid ? in_array($netUuid, (array)($p['networks'] ?? []), true)
                                          : !empty($p['networks']),
                ];
            }
        }
        return $found;
    }

    /** پارامترهای یک idnode به‌صورت id→value */
    private function params(array $src, string $uuid): ?array
    {
        $r = $this->tvh->call($src, 'idnode/load', ['uuid' => $uuid]);
        if (!$r['ok']) return null;
        $params = $r['data']['entries'][0]['params'] ?? null;
        if (!is_array($params)) return null;
        $d = [];
        foreach ($params as $p) { $d[(string)($p['id'] ?? '')] = $p['value'] ?? null; }
        return $d;
    }

    /**
     * شبکه + فرکانس‌ها + اتصال تیونر. چندبار اجرا شود مشکلی نیست (idempotent):
     * فرکانس موجود دوباره ساخته نمی‌شود.
     *
     * @param int[] $mhz
     * @param int[] $t2mhz فقط اگر تیونر T2 داشته باشد اضافه می‌شود
     * @return array{ok:bool,message:string,added:int,total:int}
     */
    public function setup(array $src, array $mhz, array $t2mhz = []): array
    {
        $fail = static fn(string $m): array => ['ok' => false, 'message' => $m, 'added' => 0, 'total' => 0];

        $mhz = array_values(array_unique(array_filter(array_map('intval', $mhz), static fn($f) => $f >= 40 && $f <= 900)));
        if (!$mhz) return $fail('فرکانسی داده نشد');

        $st = $this->status($src);
        if (!$st['reachable']) return $fail('TVHeadend در دسترس نیست: ' . $st['error']);
        if (!$st['inputs']) return $fail('تیونری دیده نشد. دستگاه را وصل کنید و درایور را از «درایورها» نصب کنید.');

        $net = $this->network($src);
        if ($net) {
            $netUuid = (string)$net['uuid'];
        } else {
            $c = $this->tvh->call($src, 'mpegts/network/create', [
                'class' => 'dvb_network_dvbt',
                'conf'  => ['networkname' => self::NETWORK_NAME, 'autodiscovery' => 0,
                            'skipinitscan' => true, 'idlescan' => false],
            ]);
            $netUuid = (string)($c['data']['uuid'] ?? '');
            if (!$c['ok'] || $netUuid === '') return $fail('ساخت شبکه ناموفق بود: ' . $c['error']);
        }

        // فرکانس‌هایی که از قبل هست
        $have = [];
        $mg = $this->tvh->call($src, 'mpegts/mux/grid', ['limit' => 2000]);
        foreach (($mg['data']['entries'] ?? []) as $m) {
            if (($m['network_uuid'] ?? '') === $netUuid) { $have[(int)($m['frequency'] ?? 0)] = true; }
        }

        $added = 0;
        $todo = array_map(static fn($f) => [$f, 'DVB-T'], $mhz);
        if ($st['has_t2']) {
            foreach ($t2mhz as $f) { $todo[] = [(int)$f, 'DVB-T2']; }
        }
        foreach ($todo as [$f, $delsys]) {
            $hz = $f * 1_000_000;
            if (isset($have[$hz])) continue;
            $conf = ['enabled' => 1, 'delsys' => $delsys, 'frequency' => $hz, 'bandwidth' => '8MHz',
                     'constellation' => 'AUTO', 'transmission_mode' => 'AUTO', 'guard_interval' => 'AUTO',
                     'hierarchy' => 'NONE', 'fec_hi' => 'AUTO', 'fec_lo' => 'AUTO'];
            if ($delsys === 'DVB-T2') { $conf['plp_id'] = -1; }
            $r = $this->tvh->call($src, 'mpegts/network/mux_create', ['uuid' => $netUuid, 'conf' => $conf]);
            if ($r['ok'] && !empty($r['data']['uuid'])) { $added++; }
        }

        // اتصال همهٔ فرانت‌اندها (روشن + شبکه). شبکه‌های قبلی فرانت‌اند حفظ می‌شود.
        $linked = 0;
        foreach ($this->frontends($src) as $fe) {
            $p = $this->params($src, $fe['uuid']) ?? [];
            $nets = array_values(array_unique(array_merge((array)($p['networks'] ?? []), [$netUuid])));
            $this->tvh->call($src, 'idnode/save', ['node' => ['uuid' => $fe['uuid'], 'enabled' => true, 'networks' => $nets]]);
            $linked++;
        }
        // به جای اعلام موفقیت، واقعا بخوانیم
        $verify = 0;
        foreach ($this->frontends($src, $netUuid) as $fe) { if ($fe['enabled'] && $fe['linked']) $verify++; }
        if ($linked === 0) return $fail('فرانت‌اند تیونر پیدا نشد (تیونر وصل است؟)');
        if ($verify === 0) return $fail('اتصال تیونر به شبکه تأیید نشد');

        $after = $this->network($src);
        $total = (int)($after['num_mux'] ?? 0);
        if ($total === 0) return $fail('شبکه ساخته شد ولی هیچ فرکانسی ندارد');

        return ['ok' => true, 'added' => $added, 'total' => $total,
                'message' => "$added فرکانس تازه، $total فرکانس در شبکه، $verify تیونر وصل شد"];
    }

    /** شروع اسکن همهٔ فرکانس‌های شبکه. */
    public function scan(array $src): array
    {
        $net = $this->network($src);
        if (!$net) return ['ok' => false, 'message' => 'اول «راه‌اندازی» را بزنید'];
        $r = $this->tvh->call($src, 'mpegts/network/scan', ['uuid' => (string)$net['uuid']]);
        return $r['ok'] ? ['ok' => true, 'message' => 'اسکن شروع شد'] : ['ok' => false, 'message' => $r['error']];
    }

    /**
     * سرویس‌های پیداشده را کانال کن (TV و رادیو با برچسب).
     * @return array{ok:bool,message:string,services:int}
     */
    public function mapServices(array $src): array
    {
        $g = $this->tvh->call($src, 'mpegts/service/grid', ['limit' => 5000]);
        if (!$g['ok']) return ['ok' => false, 'message' => $g['error'], 'services' => 0];
        $uuids = [];
        foreach (($g['data']['entries'] ?? []) as $s) {
            if (!empty($s['enabled']) && empty($s['channel'])) { $uuids[] = (string)$s['uuid']; }
        }
        if (!$uuids) return ['ok' => true, 'message' => 'سرویس بی‌کانال نیست', 'services' => 0];

        $r = $this->tvh->call($src, 'service/mapper/save', ['node' => [
            'services' => $uuids, 'encrypted' => false, 'merge_same_name' => false,
            'check_availability' => false, 'type_tags' => true, 'provider_tags' => false,
            'network_tags' => false,
        ]]);
        return $r['ok']
            ? ['ok' => true, 'message' => count($uuids) . ' سرویس به کانال تبدیل شد', 'services' => count($uuids)]
            : ['ok' => false, 'message' => $r['error'], 'services' => 0];
    }
}
