<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\TvheadendTunerService;

/**
 * «تلویزیون کار می‌کند؟» — یک وضعیت واحد برای داشبورد: تیونر، فرکانس، کانال‌ها
 * و چک‌لیست راه‌اندازی، هر مشکل با علت ساده و لینک رفع.
 *
 * فقط خواندنی است و رمز منبع TVHeadend را برنمی‌گرداند. نتیجه ۸ ثانیه در نشست
 * کش می‌شود تا هر بار باز کردن داشبورد TVHeadend را نزند.
 */
class TvHealthController extends Controller
{
    /** GET /admin/tv/health */
    public function summary(Request $req): void
    {
        if (!in_array(Auth::user()['role'] ?? '', ['super_admin', 'admin'], true)) {
            Response::error('اجازه ندارید', 403);
            return;
        }
        $tid = (int)Auth::tenantId();

        $c = $_SESSION['_tvh_health'] ?? null;
        if (is_array($c) && ($c['tid'] ?? 0) === $tid && (time() - (int)($c['at'] ?? 0)) < 8) {
            Response::success($c['data']);
            return;
        }

        $svc = new TvheadendTunerService();
        $src = $svc->source($tid);
        $problems = [];
        $tuner = null;
        $verdict = 'ok';

        $add = static function (string $level, string $text, string $label = '', string $url = '') use (&$problems, &$verdict): void {
            $problems[] = ['level' => $level, 'text' => $text, 'label' => $label, 'url' => $url];
            if ($level === 'down') { $verdict = 'down'; }
            elseif ($level === 'warn' && $verdict !== 'down') { $verdict = 'warn'; }
        };

        $st = null;
        if (!$src) {
            $verdict = 'none';
            $add('warn', 'منبع پخش زنده (TVHeadend) هنوز اضافه نشده.', 'افزودن منبع', '/admin/iptv/tvheadend');
        } else {
            $st = $svc->status($src);
            if (!$st['reachable']) {
                $add('down', 'TVHeadend جواب نمی‌دهد (' . ($st['error'] ?: 'قطع') . '). سرویس خاموش است یا رمزش عوض شده.', 'بررسی منبع', '/admin/iptv/tvheadend');
            } elseif (!$st['inputs']) {
                $add('down', 'تیونر دیده نمی‌شود. USB را وصل کنید (روی ماشین مجازی از منوی Removable Devices)، بعد صفحهٔ تیونر را باز کنید.', 'تیونر و اسکن', '/admin/system/tuner');
            } else {
                $in = $st['inputs'][0];
                $tuner = ['name' => $in['name'], 'viewers' => (int)$in['subs'], 'on' => $in['stream']];
                $net = $st['network'];
                if (!$net || $net['mux'] === 0) {
                    $add('warn', 'فرکانسی برای اسکن تنظیم نشده.', 'راه‌اندازی', '/admin/system/tuner');
                } elseif ($net['svc'] === 0) {
                    $add('warn', 'اسکن هیچ سرویسی پیدا نکرد: آنتن، کابل یا فرکانس‌ها را بررسی کنید.', 'اسکن دوباره', '/admin/system/tuner');
                } elseif ($net['chn'] === 0) {
                    $add('warn', 'سرویس پیدا شده ولی کانالی ساخته نشده.', 'ورود کانال‌ها', '/admin/system/tuner');
                }
                if (($in['snr_db'] ?? null) !== null && $in['snr_db'] < 15 && $in['subs'] > 0) {
                    $add('warn', 'کیفیت سیگنال پایین است (SNR ' . $in['snr_db'] . ' dB؛ زیر ۱۵ تصویر نمی‌آید یا می‌پرد). آنتن، کابل و جهت آنتن را بررسی کنید.', 'جزئیات', '/admin/system/tuner');
                }
                if ($in['stream'] !== '' && $in['subs'] > 0) {
                    $add('info', 'تیونر الان روی «' . $in['stream'] . '» است. کانال‌های فرکانس‌های دیگر تا آزاد شدن تیونر پخش نمی‌شوند (هر تیونر یک فرکانس).', '', '');
                }
            }
        }

        // چک‌لیست راه‌اندازی (شمارش‌های ارزان؛ هر کدام fail-soft)
        $count = function (string $sql) use ($tid): int {
            try { return (int)$this->db->value($sql, [$tid]); } catch (\Throwable) { return 0; }
        };
        $channels = $count("SELECT COUNT(*) FROM iptv_channels WHERE tenant_id = ? AND source_type = 'tvheadend'");
        $checks = [
            ['key' => 'source',   'label' => 'منبع TVHeadend',      'ok' => $src !== null,                      'url' => '/admin/iptv/tvheadend'],
            ['key' => 'tuner',    'label' => 'تیونر دیده می‌شود',    'ok' => (bool)($st['inputs'] ?? []),        'url' => '/admin/system/tuner'],
            ['key' => 'channels', 'label' => 'کانال وارد شده',       'ok' => $channels > 0,                      'url' => '/admin/system/tuner'],
            ['key' => 'menu',     'label' => 'منوی IPTV',           'ok' => $count('SELECT COUNT(*) FROM iptv_menus WHERE tenant_id = ?') > 0, 'url' => '/admin/iptv/menus'],
            ['key' => 'rooms',    'label' => 'اتاق تعریف شده',      'ok' => $count('SELECT COUNT(*) FROM iptv_rooms WHERE tenant_id = ?') > 0,  'url' => '/admin/iptv/rooms'],
            ['key' => 'tvs',      'label' => 'تلویزیون جفت شده',    'ok' => $count("SELECT COUNT(*) FROM screens WHERE tenant_id = ? AND screen_type = 'iptv'") > 0, 'url' => '/admin/screens'],
        ];

        $data = ['verdict' => $verdict, 'tuner' => $tuner, 'problems' => $problems,
                 'channels' => $channels, 'checks' => $checks];
        $_SESSION['_tvh_health'] = ['tid' => $tid, 'at' => time(), 'data' => $data];
        Response::success($data);
    }
}
