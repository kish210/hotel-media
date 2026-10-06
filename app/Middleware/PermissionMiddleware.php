<?php declare(strict_types=1);
namespace App\Middleware;

use App\Core\{Auth, Request, Response};

/**
 * اعمال دسترسی نقش‌ها بر اساس مسیر.
 *
 * چرا middleware و نه بررسی در هر کنترلر: ۷۷ کنترلر وجود دارد و
 * بررسی پراکنده یعنی یکی فراموش می‌شود — همان‌طور که تا فاز ۹
 * اعلان اضطراری فراموش شده بود و هر حساب `viewer` می‌توانست روی
 * تلویزیون هر اتاق دستور تخلیه بفرستد. یک نقشه در یک فایل، قابل
 * خواندن و قابل بازبینی است.
 *
 * ── اصل طراحی: پیش‌فرض باز، استثناها بسته ───────────────────────
 * عمدا «هر چه در نقشه نیست ممنوع» نیست. این برنامه ۲۷ سرویس و
 * ده‌ها مسیر دارد و بستن همه‌چیز به‌صورت پیش‌فرض یعنی اولین نقشی
 * که تست نشده، از کارِ روزمره‌اش بیرون می‌افتد — و اپراتور هتل
 * ساعت ۲ بامداد نمی‌فهمد چرا. پس فقط مسیرهایی که دسترسی‌شان
 * واقعا اهمیت دارد اینجا فهرست می‌شوند، و بقیه مثل قبل برای هر
 * کاربر وارد‌شده باز است.
 *
 * ‏super_admin همیشه رد می‌شود (`*` در Auth::$permissions).
 */
class PermissionMiddleware
{
    /**
     * نقشهٔ مسیر → مجوز لازم.
     *
     * کلید، **پیشوند** مسیر است و طولانی‌ترین تطبیق برنده می‌شود، تا
     * بتوان یک زیرمسیر را سخت‌تر از والدش بست (مثل خواندن فهرست
     * صفحه‌ها در برابر فرستادن فرمان به یک صفحه).
     *
     * روش نوشتن: `حوزه.عمل`. نقشهٔ نقش‌ها در `Auth::$permissions`
     * است و `can()` الگوی `حوزه.*` را هم تطبیق می‌دهد.
     */
    private const RULES = [
        // ── پخش اضطراری ──────────────────────────────────────────
        // روی تلویزیون هر اتاق تمام‌صفحه می‌رود و میهمان نمی‌تواند
        // ببنددش. کار تولیدکنندهٔ محتوا نیست.
        '/api/v1/broadcast'          => 'emergency.send',

        // ── کاربران و تنظیمات ───────────────────────────────────
        '/admin/users'               => 'users.view',
        '/api/v1/users'              => 'users.view',
        '/admin/settings'            => 'settings.view',
        '/api/v1/settings'           => 'settings.view',

        // ── زیرساخت فنی ─────────────────────────────────────────
        // ترنسکدر، هدِند، درایور و به‌روزرسانی سیستم: اشتباه اینجا
        // پخش کل هتل را می‌خواباند.
        //
        // پیشوندها با مسیرهای واقعی `routes/web.php` تطبیق داده شدند.
        // پیش‌نویس اول `/admin/driver`، `/admin/system-update`،
        // `/admin/diagnostics`، `/admin/logs` و `/admin/tvheadend`
        // داشت که هیچ‌کدام مسیر واقعی نیستند — قاعده‌ای که با هیچ
        // مسیری جور نشود بی‌صدا هیچ‌چیز را محافظت نمی‌کند، و بدتر از
        // نبودنش است چون آدم خیال می‌کند بسته است.
        '/admin/transcoder'          => 'settings.view',
        '/admin/iptv/tvheadend'      => 'iptv.view',
        '/admin/system'              => 'settings.view',

        // ── کانال و فیلم ────────────────────────────────────────
        '/admin/iptv'                => 'iptv.view',
        '/api/v1/iptv'               => 'iptv.view',
        '/admin/vod'                 => 'vod.view',
        '/api/v1/vod'                => 'vod.view',

        // ── محتوا ───────────────────────────────────────────────
        '/admin/zones'               => 'zones.view',
        '/api/v1/venues'             => 'zones.view',
        '/admin/schedules'           => 'schedules.view',
        '/api/v1/schedules'          => 'schedules.view',
        '/admin/messages'            => 'messages.view',
    ];

    /**
     * قاعده‌هایی که فقط روی **نوشتن** اعمال می‌شوند (POST/PUT/PATCH/DELETE).
     *
     * چرا جدا: نقشهٔ بالا بر اساس مسیر است و متد را نمی‌بیند. با همان
     * به‌تنهایی، حسابی که اجازهٔ *دیدن* کتابخانهٔ رسانه را دارد از
     * مسیر API اجازهٔ *حذف* هم داشت — سیستم دسترسی‌ای که خواندن را از
     * پاک‌کردن جدا نکند چیز زیادی نیست.
     *
     * خواندن باز می‌ماند (همان اصل «پیش‌فرض باز») و فقط تغییر داده
     * مجوز می‌خواهد.
     */
    private const WRITE_RULES = [
        '/api/v1/media'      => 'media.edit',
        '/admin/media'       => 'media.edit',
        '/api/v1/playlists'  => 'playlists.edit',
        '/admin/playlists'   => 'playlists.edit',
        '/api/v1/screens'    => 'screens.edit',
        '/admin/screens'     => 'screens.edit',
    ];

    public function handle(Request $request, callable $next): void
    {
        $path = $request->path();
        $need = '';

        /* قاعده‌های نوشتن فقط برای متدهای تغییردهنده. GET و HEAD از
           اینجا رد می‌شوند و فقط به نقشهٔ عمومی پایین می‌رسند. */
        $isWrite = in_array(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                            ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        /* طولانی‌ترین پیشوند برنده است: `/api/v1/vod/subtitles` باید
           قاعدهٔ دقیق‌تر خودش را بگیرد نه قاعدهٔ `/api/v1/vod`. */
        $pick = static function (array $rules) use ($path): string {
            $need = ''; $best = -1;
            foreach ($rules as $prefix => $perm) {
                if (str_starts_with($path, $prefix) && strlen($prefix) > $best) {
                    $best = strlen($prefix);
                    $need = $perm;
                }
            }
            return $need;
        };

        /* قاعدهٔ نوشتن اگر بخورد، مقدم است — و نه با «طولانی‌ترین
           پیشوند» با نقشهٔ عمومی رقابت می‌کند. وگرنه یک پیشوند عمومیِ
           بلندتر می‌توانست بی‌صدا یک عملیات نوشتن را سست کند. */
        if ($isWrite) $need = $pick(self::WRITE_RULES);
        if ($need === '') $need = $pick(self::RULES);

        if ($need === '' || Auth::can($need)) { $next(); return; }

        /* پاسخ به شکل درخواست: پلیر تلویزیون و fetch پنل، HTML
           نمی‌فهمند و یک هدایت را به‌عنوان موفقیت می‌خوانند. */
        if ($request->isJson() || $request->isAjax() || str_starts_with($path, '/api/')) {
            Response::error('برای این کار دسترسی ندارید', 403);
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_flash']['error'] = 'برای این بخش دسترسی ندارید';
        }
        Response::redirect('/admin');
    }
}
