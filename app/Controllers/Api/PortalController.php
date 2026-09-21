<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response};
use App\Services\PortalLiveService;

/**
 * Portal Controller
 * صفحه‌ی اصلی تلویزیون اتاق — در یک درخواست: برندینگ، منو با میانبر عددی،
 * خوش‌آمدگویی با نام و زبان مهمان، و داده‌های زنده‌ی نوار بالا.
 *
 * بدون JWT؛ هویت با کد صفحه‌نمایش. ست‌تاپ‌باکس چیزی جز کد خودش نمی‌داند.
 * فاز ۳ نقشه‌راه — docs/TODO.md (۳.۱، ۳.۲، ۳.۵، ۳.۶، ۳.۷)
 */
class PortalController extends Controller
{
    private const ALLOWED_WIDGETS = ['clock', 'weather', 'currency', 'prayer'];

    /** زبان‌هایی که ترجمه‌ی پایه دارند */
    private const FALLBACK_LANG = 'fa';

    /**
     * GET /api/v1/portal/{code}
     * همه‌چیزِ لازم برای رندر صفحه‌ی اصلی، در یک رفت‌وبرگشت.
     */
    public function home(Request $req, array $params): void
    {
        $ctx = $this->resolveScreen((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یافت نشد'); return; }

        $tid  = (int)$ctx['tenant_id'];
        $menu = $this->resolveMenu($ctx);

        // زبان مهمان بر زبان پیش‌فرض اولویت دارد
        $lang = $this->normalizeLang($req->get('lang') ?: ($ctx['guest_lang'] ?? self::FALLBACK_LANG));

        $widgets = $this->parseWidgets($menu['header_widgets'] ?? 'clock,weather');
        $live    = (new PortalLiveService($this->db))->get($tid, $widgets, $menu['city'] ?? null);

        Response::success([
            'screen' => [
                'code'     => $ctx['code'],
                'name'     => $ctx['name'],
                'platform' => $ctx['platform'] ?? 'unknown',
            ],
            'room'     => $this->roomBlock($ctx, $menu),
            'branding' => $this->brandingBlock($menu),
            'menu'     => $this->menuBlock($menu, $lang),
            'channels' => $this->channelsBlock($tid, (string)($ctx['platform'] ?? 'unknown')),
            'header'   => ['widgets' => $widgets, 'data' => $live],
            'lang'     => $lang,
            'strings'  => $this->translations($tid, $lang),
        ]);
    }

    /**
     * GET /api/v1/portal/{code}/live
     * فقط داده‌های زنده — پلیر این را هر چند دقیقه صدا می‌زند
     * بدون اینکه کل صفحه را دوباره بگیرد.
     */
    public function live(Request $req, array $params): void
    {
        $ctx = $this->resolveScreen((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یافت نشد'); return; }

        $menu    = $this->resolveMenu($ctx);
        $widgets = $this->parseWidgets($menu['header_widgets'] ?? 'clock,weather');

        Response::success(
            (new PortalLiveService($this->db))->get((int)$ctx['tenant_id'], $widgets, $menu['city'] ?? null)
        );
    }

    // ══════════════════════════════════════════════════════════════
    //  بلوک‌های پاسخ
    // ══════════════════════════════════════════════════════════════

    /** @return array<string,mixed> */
    private function roomBlock(array $ctx, ?array $menu): array
    {
        $occupied  = ($ctx['room_status'] ?? '') === 'occupied';
        $showName  = $menu === null || (int)($menu['show_guest_name'] ?? 1) === 1;
        $guestName = ($occupied && $showName) ? ($ctx['guest_name'] ?: null) : null;

        return [
            'room_number' => $ctx['room_number'] ?? null,
            'room_name'   => $ctx['room_name']   ?? null,
            'occupied'    => $occupied,
            'guest_name'  => $guestName,
        ];
    }

    /** @return array<string,mixed> */
    private function brandingBlock(?array $menu): array
    {
        if (!$menu) {
            // صفحه‌ای که به منو وصل نیست، باز هم باید قابل رندر باشد
            return [
                'logo_url'      => null,
                'accent_color'  => '#ef4444',
                'bg_image'      => null,
                'backgrounds'   => [],
                'bg_dim'        => 0.55,
                'bg_blur'       => 0,
                'welcome_title' => null,
                'welcome_sub'   => null,
                'ticker'        => null,
            ];
        }

        // اسلایدشو؛ اگر خالی بود، همان تک‌تصویر قدیمی
        $backgrounds = array_column(
            $this->db->rows(
                'SELECT image_url FROM iptv_menu_backgrounds WHERE menu_id = ? ORDER BY sort_order, id',
                [(int)$menu['id']]
            ),
            'image_url'
        );
        if (!$backgrounds && !empty($menu['bg_image'])) {
            $backgrounds = [$menu['bg_image']];
        }

        $ticker = trim((string)($menu['ticker_text'] ?? ''));

        return [
            'logo_url'      => $menu['logo_url']      ?? null,
            'accent_color'  => $menu['accent_color']  ?? '#ef4444',
            'bg_image'      => $menu['bg_image']      ?? null,
            'backgrounds'   => $backgrounds,
            'bg_dim'        => (float)($menu['bg_dim'] ?? 0.55),
            'bg_blur'       => (int)($menu['bg_blur'] ?? 0),
            'welcome_title' => $menu['welcome_title'] ?? null,
            'welcome_sub'   => $menu['welcome_sub']   ?? null,
            'ticker'        => $ticker === '' ? null : [
                'text'  => $ticker,
                'color' => $menu['ticker_color'] ?? '#ffffff',
                'bg'    => $menu['ticker_bg']    ?? '#000000',
                'speed' => (int)($menu['ticker_speed'] ?? 40),
            ],
        ];
    }

    /**
     * آیتم‌های منو با برچسب زبان درست و میانبر عددی.
     * @return list<array<string,mixed>>
     */
    private function menuBlock(?array $menu, string $lang): array
    {
        if (!$menu) return [];

        $rows = $this->db->rows(
            'SELECT id, type, label, label_en, icon, color, target_url, config, shortcut_key, sort_order
               FROM iptv_menu_items
              WHERE menu_id = ? AND is_active = 1
              ORDER BY sort_order, id',
            [(int)$menu['id']]
        );

        $used = [];
        $out  = [];

        foreach ($rows as $row) {
            // انگلیسی و عربی هر دو به برچسب لاتین می‌افتند وقتی موجود باشد
            $label = ($lang !== 'fa' && !empty($row['label_en']))
                ? $row['label_en']
                : $row['label'];

            // میانبر تکراری نادیده گرفته می‌شود — اولین آیتم مالک آن عدد است
            $key = $row['shortcut_key'];
            if ($key !== null) {
                $key = (int)$key;
                if ($key < 0 || $key > 9 || isset($used[$key])) $key = null;
                else $used[$key] = true;
            }

            $config = $row['config'];
            if (is_string($config) && $config !== '') {
                $config = json_decode($config, true) ?: null;
            }

            $out[] = [
                'id'           => (int)$row['id'],
                'type'         => $row['type'],
                'label'        => $label,
                'icon'         => $row['icon'],
                'color'        => $row['color'],
                'target_url'   => $row['target_url'],
                'config'       => $config,
                'shortcut_key' => $key,
            ];
        }

        return $out;
    }

    /**
     * کانال‌های زنده با آدرسی که **این پلتفرم واقعا می‌تواند پخش کند**.
     *
     * ‏HTML5 و تگ <video> نمی‌توانند UDP multicast بخوانند. پس:
     *  • اپ بومی Android TV و ویندوز → آدرس multicast مستقیم (بار صفر روی سرور)
     *  • پورتال HTML5 روی webOS و Tizen → udpxy اگر تنظیم شده، وگرنه HTTP
     * ‏playable=false یعنی کانال روی این دستگاه فقط از تیونر خود تلویزیون
     * قابل دیدن است، نه از داخل پورتال.
     *
     * @return list<array<string,mixed>>
     */
    private function channelsBlock(int $tenantId, string $platform): array
    {
        $rows = $this->db->rows(
            'SELECT id, name, name_en, logo_url, category, channel_no, sort_order,
                    stream_url, multicast_url, delivery, protocol
               FROM iptv_channels
              WHERE tenant_id = ? AND is_active = 1
              ORDER BY channel_no IS NULL, channel_no, sort_order, id',
            [$tenantId]
        );
        if (!$rows) return [];

        $udpxy = (string)($this->db->value(
            'SELECT udpxy_url FROM multicast_config WHERE tenant_id = ? AND is_active = 1',
            [$tenantId]
        ) ?? '');

        // فقط اپ بومی می‌تواند سوکت UDP باز کند
        $nativeMulticast = in_array($platform, ['android', 'windows'], true);

        $out = [];
        foreach ($rows as $r) {
            $multicast = (string)($r['multicast_url'] ?? '');
            $http      = (string)$r['stream_url'];
            $isMulti   = in_array($r['delivery'], ['multicast', 'both'], true) && $multicast !== '';

            if ($isMulti && $nativeMulticast) {
                $url = $multicast;  $via = 'multicast';
            } elseif ($isMulti && $udpxy !== '') {
                $url = $this->udpxy($udpxy, $multicast);  $via = 'udpxy';
            } elseif ($r['delivery'] === 'multicast') {
                // فقط multicast دارد و این دستگاه نمی‌تواند — تیونر تلویزیون باید بگیرد
                $url = '';  $via = 'tv_tuner';
            } else {
                $url = $http;  $via = 'unicast';
            }

            $out[] = [
                'id'         => (int)$r['id'],
                'name'       => $r['name'],
                'name_en'    => $r['name_en'],
                'logo_url'   => $r['logo_url'],
                'category'   => $r['category'],
                'channel_no' => $r['channel_no'] !== null ? (int)$r['channel_no'] : null,
                'url'        => $url,
                'via'        => $via,
                'playable'   => $url !== '',
            ];
        }

        return $out;
    }

    /** udp://@239.1.1.5:5000 → http://10.0.0.5:4022/udp/239.1.1.5:5000 */
    private function udpxy(string $base, string $multicast): string
    {
        if (!preg_match('#^(udp|rtp)://@?([\d.]+):(\d+)#', $multicast, $m)) return '';

        $proto = $m[1] === 'rtp' ? 'rtp' : 'udp';
        return rtrim($base, '/') . "/$proto/{$m[2]}:{$m[3]}";
    }

    /**
     * متن‌های رابط. کلیدهایی که در زبان مهمان ترجمه ندارند از فارسی پر می‌شوند،
     * تا رابط نیمه‌خالی نماند.
     * @return array<string,string>
     */
    private function translations(int $tenantId, string $lang): array
    {
        $base = $this->langMap($tenantId, self::FALLBACK_LANG);
        if ($lang === self::FALLBACK_LANG) return $base;

        return array_merge($base, $this->langMap($tenantId, $lang));
    }

    /** @return array<string,string> */
    private function langMap(int $tenantId, string $lang): array
    {
        $map = [];
        foreach ($this->db->rows(
            'SELECT trans_key, trans_value FROM portal_translations WHERE tenant_id = ? AND lang = ?',
            [$tenantId, $lang]
        ) as $row) {
            $map[(string)$row['trans_key']] = (string)$row['trans_value'];
        }

        // ‏tenant سفارشی چیزی تعریف نکرده باشد، از متن‌های پایه‌ی tenant 1 استفاده کن
        if (!$map && $tenantId !== 1) return $this->langMap(1, $lang);

        return $map;
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /** @return array<string,mixed>|null */
    private function resolveScreen(string $code): ?array
    {
        if ($code === '') return null;

        return $this->db->row(
            'SELECT s.id, s.code, s.name, s.tenant_id, s.iptv_menu_id, s.group_id, s.platform,
                    r.room_number, r.room_name, r.status AS room_status,
                    r.guest_name, r.guest_lang
               FROM screens s
               LEFT JOIN iptv_rooms r ON r.id = s.iptv_room_id
              WHERE s.code = ?',
            [$code]
        ) ?: null;
    }

    /**
     * منوی صفحه: اول منوی مستقیم، بعد منوی گروه، بعد منوی پیش‌فرض tenant.
     * @return array<string,mixed>|null
     */
    private function resolveMenu(array $ctx): ?array
    {
        $tid = (int)$ctx['tenant_id'];

        if (!empty($ctx['iptv_menu_id'])) {
            $menu = $this->db->row(
                'SELECT * FROM iptv_menus WHERE id = ? AND tenant_id = ? AND is_active = 1',
                [(int)$ctx['iptv_menu_id'], $tid]
            );
            if ($menu) return $menu;
        }

        if (!empty($ctx['group_id'])) {
            $menu = $this->db->row(
                'SELECT * FROM iptv_menus WHERE group_id = ? AND tenant_id = ? AND is_active = 1
                 ORDER BY sort_order, id LIMIT 1',
                [(int)$ctx['group_id'], $tid]
            );
            if ($menu) return $menu;
        }

        return $this->db->row(
            'SELECT * FROM iptv_menus WHERE tenant_id = ? AND is_active = 1
             ORDER BY sort_order, id LIMIT 1',
            [$tid]
        ) ?: null;
    }

    /** @return list<string> */
    private function parseWidgets(string $raw): array
    {
        $list = array_filter(array_map('trim', explode(',', $raw)));
        $out  = [];

        foreach ($list as $w) {
            if (in_array($w, self::ALLOWED_WIDGETS, true) && !in_array($w, $out, true)) {
                $out[] = $w;
            }
        }

        return $out;
    }

    private function normalizeLang(mixed $lang): string
    {
        $lang = mb_strtolower(trim((string)$lang));
        return preg_match('/^[a-z]{2}$/', $lang) ? $lang : self::FALLBACK_LANG;
    }
}
