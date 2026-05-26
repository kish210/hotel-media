<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Response};
use App\Models\{Screen, Playlist};

class PlayerController extends Controller
{
    /** Cookie که کد صفحه‌نمایش رو نگه می‌داره */
    private const COOKIE_NAME   = 'signage_scr';
    private const COOKIE_TTL    = 31536000; // 1 year

    // ── /player (no code) ───────────────────────────────────────────────────
    /**
     * اگر کوکی وجود داشت → پلیر رو لود می‌کنه
     * اگر نه → صفحه pairing نشون می‌ده
     */
    public function index(Request $req): void
    {
        $code = self::readCookie();

        if ($code) {
            $screenModel = new Screen();
            $screen = $screenModel->findByCode($code);
            if ($screen) {
                // کوکی معتبره — refresh کن و پلیر رو لود کن
                self::writeCookie($code);
                $this->loadPlayer($screen);
                return;
            }
            // کوکی داریم ولی screen حذف شده — پاک کن
            self::clearCookie();
        }

        // هیچ کوکی معتبری نیست → صفحه pairing
        include VIEWS_PATH . '/player/pair.php';
    }

    // ── /player/{code} ──────────────────────────────────────────────────────
    public function show(Request $req, array $params): void
    {
        $code = strtoupper(trim($params['code'] ?? ''));
        $screenModel = new Screen();
        $screen = $screenModel->findByCode($code);

        if (!$screen) {
            http_response_code(404);
            $notFoundCode = htmlspecialchars($code);
            include VIEWS_PATH . '/player/not_found.php';
            return;
        }

        // ── Bind this device via cookie ──────────────────────────────────
        $prevCode   = self::readCookie();
        $justBound  = ($prevCode !== $code);   // کد جدید یا اولین بار
        self::writeCookie($code);

        // اگه اولین بار بایند شد → صفحه pairing کوتاه نشون بده که بعد
        // خودش به /player/{code} ریدایرکت می‌کنه
        if ($justBound && !$req->get('_bound')) {
            // pair.php یه صفحه کوتاه نشون می‌ده و ریدایرکت می‌کنه
            $pairingScreen = $screen;
            $pairingRedirect = '/player/' . urlencode($code) . '?_bound=1';
            include VIEWS_PATH . '/player/pair.php';
            return;
        }

        $this->loadPlayer($screen);
    }

    // ── Shared: load playlist + render profile ───────────────────────────────
    private function loadPlayer(array $screen): void
    {
        $screenModel = new Screen();
        $playlist    = null;

        if ($screen['status'] === 'active') {
            $p = $screenModel->getCurrentPlaylist($screen['id']);
            if ($p) {
                $playlistModel = new Playlist();
                $playlist = $playlistModel->getForPlayer((int)$p['id']);
            }
        }

        // انتخاب profile پلیر
        $settings = json_decode($screen['settings'] ?? '{}', true) ?: [];
        if (($screen['screen_type'] ?? 'signage') === 'iptv') {
            $profile = 'iptv';
        } elseif (($screen['screen_type'] ?? 'signage') === 'inflight') {
            $profile = 'inflight';
        } elseif (($screen['screen_type'] ?? 'signage') === 'monitor_3d') {
            $profile = 'monitor_3d';
            // بارگذاری تنظیمات 3D از دیتابیس
            try {
                $cfg3d = $this->db->row(
                    "SELECT * FROM monitor_3d_configs WHERE screen_id=?",
                    [$screen['id']]
                );
                $screen['cfg_3d'] = $cfg3d ?: [];
            } catch (\Throwable $e) {
                $screen['cfg_3d'] = [];
            }
        } else {
            $profile  = $settings['player_profile'] ?? 'modern';
            $profiles = ['modern', 'android_tv', 'lg_tv', 'samsung_tv', 'legacy', 'minimal', 'kiosk'];
            if (!in_array($profile, $profiles)) $profile = 'modern';
        }

        $profileView = VIEWS_PATH . '/player/profiles/' . $profile . '.php';
        if (!file_exists($profileView)) $profileView = VIEWS_PATH . '/player/index.php';

        include $profileView;
    }

    // ── Cookie helpers ───────────────────────────────────────────────────────
    private static function writeCookie(string $code): void
    {
        setcookie(self::COOKIE_NAME, $code, [
            'expires'  => time() + self::COOKIE_TTL,
            'path'     => '/',
            'samesite' => 'Lax',
            'httponly' => false,   // JS باید بتونه بخونه (heartbeat)
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
    }

    private static function clearCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', ['expires' => time() - 3600, 'path' => '/']);
    }

    private static function readCookie(): string
    {
        $v = trim($_COOKIE[self::COOKIE_NAME] ?? '');
        // validate: باید فقط حروف بزرگ + عدد باشه
        return preg_match('/^[A-Z0-9]{4,20}$/', $v) ? $v : '';
    }

    // ── /player/activate ────────────────────────────────────────────────────
    public function activate(Request $req): void
    {
        // خواندن از JSON body یا form data
        $body = $req->json() ?: [];
        $activationCode = $body['activation_code']
            ?? $req->post('activation_code')
            ?? $req->get('activation_code')
            ?? '';
        $screenCode = $body['screen_code']
            ?? $req->post('screen_code')
            ?? $req->get('screen_code')
            ?? '';

        $activationCode = strtoupper(trim($activationCode));
        $screenCode     = strtoupper(trim($screenCode));

        if (!$activationCode) {
            Response::error('کد فعال‌سازی الزامی است', 400);
            return;
        }

        $model  = new Screen();
        // اگه screenCode خالی بود، فقط با activationCode پیدا کن
        $screen = $screenCode
            ? $model->activate($activationCode, $screenCode)
            : $model->activateByCode($activationCode);

        if ($screen) {
            Response::success([
                'screen_code' => $screen['code'],
                'screen_name' => $screen['name'],
            ], 'صفحه با موفقیت فعال شد');
        } else {
            Response::error('کد نامعتبر است یا منقضی شده — از پنل مدیریت کد جدید بگیرید', 422);
        }
    }
}
