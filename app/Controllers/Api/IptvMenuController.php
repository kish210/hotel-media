<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Response, Request, Auth};

/**
 * IPTV Menu Controller
 * مدیریت منوهای IPTV (پخش زنده، VOD، اخبار، ...)
 */
class IptvMenuController extends Controller
{
    // ── List menus ──────────────────────────────────────────────────
    public function index(Request $req): void
    {
        $tid     = Auth::tenantId();
        $groupId = $req->get('group_id');

        $sql = "SELECT m.*,
                       g.name AS group_name,
                       COUNT(i.id) AS item_count
                FROM iptv_menus m
                LEFT JOIN screen_groups g  ON g.id = m.group_id
                LEFT JOIN iptv_menu_items i ON i.menu_id = m.id AND i.is_active = 1
                WHERE m.tenant_id = ?";
        $params = [$tid];

        if ($groupId) {
            $sql    .= ' AND m.group_id = ?';
            $params[] = (int)$groupId;
        }

        $sql .= ' GROUP BY m.id ORDER BY m.sort_order, m.name';

        $menus = $this->db->rows($sql, $params) ?: [];
        Response::success($menus);
    }

    // ── Show single menu with items ─────────────────────────────────
    public function show(Request $req, array $params): void
    {
        $menu = $this->getMenu((int)$params['id']);
        if (!$menu) Response::notFound('منو یافت نشد');

        $menu['items'] = $this->db->rows(
            "SELECT * FROM iptv_menu_items WHERE menu_id=? AND is_active=1 ORDER BY sort_order, id",
            [$menu['id']]
        ) ?: [];

        Response::success($menu);
    }

    // ── Create menu ─────────────────────────────────────────────────
    public function store(Request $req): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: $req->post();

        $name = trim($data['name'] ?? '');
        if (!$name) Response::error('نام منو الزامی است', 422);

        $id = $this->db->insert('iptv_menus', [
            'tenant_id'   => $tid,
            'group_id'    => isset($data['group_id']) && $data['group_id'] !== '' ? (int)$data['group_id'] : null,
            'name'        => $name,
            'description' => $data['description'] ?? null,
            'sort_order'  => (int)($data['sort_order'] ?? 0),
            'is_active'   => 1,
        ]);

        // اگر آیتم‌های پیش‌فرض ارسال شدن (از قالب‌ها)
        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $ord => $item) {
                $this->insertItem((int)$id, $item, $ord);
            }
        }

        $this->log('iptv_menu.create', 'IptvMenu', (int)$id);
        $menu          = $this->getMenu((int)$id);
        $menu['items'] = $this->db->rows(
            "SELECT * FROM iptv_menu_items WHERE menu_id=? ORDER BY sort_order", [(int)$id]
        ) ?: [];

        Response::success($menu, 'منو ایجاد شد', 201);
    }

    // ── Update menu ─────────────────────────────────────────────────
    public function update(Request $req, array $params): void
    {
        $menu = $this->getMenu((int)$params['id']);
        if (!$menu) Response::notFound();

        $data    = $req->json() ?: $req->input();
        $allowed = [
            'name', 'description', 'group_id', 'is_active', 'sort_order',
            // appearance
            'accent_color', 'bg_dim', 'bg_blur',
            'welcome_title', 'welcome_sub',
            'ticker_text', 'ticker_color', 'ticker_bg', 'ticker_speed',
        ];
        $upd = array_intersect_key($data, array_flip($allowed));

        if (isset($upd['group_id']) && $upd['group_id'] === '') $upd['group_id'] = null;
        if (isset($upd['accent_color']) && !preg_match('/^#[0-9a-f]{6}$/i', $upd['accent_color']))
            unset($upd['accent_color']);
        if (isset($upd['ticker_color']) && !preg_match('/^#[0-9a-f]{6}$/i', $upd['ticker_color']))
            unset($upd['ticker_color']);
        if (isset($upd['ticker_bg']) && !preg_match('/^#[0-9a-f]{6}$/i', $upd['ticker_bg']))
            unset($upd['ticker_bg']);
        if (isset($upd['bg_dim']))      $upd['bg_dim']      = round(max(0, min(1, (float)$upd['bg_dim'])), 2);
        if (isset($upd['bg_blur']))     $upd['bg_blur']     = max(0, min(20, (int)$upd['bg_blur']));
        if (isset($upd['ticker_speed'])) $upd['ticker_speed'] = max(5, min(200, (int)$upd['ticker_speed']));

        if (!empty($upd)) {
            $this->db->update('iptv_menus', $upd, ['id' => $menu['id']]);
        }

        Response::success($this->getMenu($menu['id']), 'منو بروز شد');
    }

    // ── Upload image (bg or logo) ────────────────────────────────
    public function uploadImage(Request $req, array $params): void
    {
        $menu = $this->getMenu((int)$params['id']);
        if (!$menu) { Response::notFound('منو یافت نشد'); return; }

        $type = in_array($req->post('type'), ['bg', 'logo']) ? $req->post('type') : 'bg';
        $file = $_FILES['file'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Response::error('فایلی انتخاب نشده یا خطا در آپلود', 422); return;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            Response::error('حجم تصویر نباید بیشتر از ۵ مگابایت باشد', 422); return;
        }

        $mime    = mime_content_type($file['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed)) {
            Response::error('فرمت نامعتبر — فقط JPG، PNG، WebP، GIF', 422); return;
        }

        $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        $ext    = $extMap[$mime] ?? 'jpg';
        $dir    = PUBLIC_PATH . '/uploads/iptv/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $name = 'iptv_' . $type . '_' . $menu['id'] . '_' . time() . '.' . $ext;
        $dest = $dir . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Response::error('خطا در ذخیره تصویر روی سرور', 500); return;
        }

        // حذف فایل قدیمی
        $col    = $type === 'bg' ? 'bg_image' : 'logo_url';
        $oldUrl = $menu[$col] ?? '';
        if ($oldUrl && str_contains($oldUrl, '/uploads/iptv/')) {
            $oldPath = PUBLIC_PATH . parse_url($oldUrl, PHP_URL_PATH);
            if (file_exists($oldPath)) @unlink($oldPath);
        }

        $url = '/uploads/iptv/' . $name;
        $this->db->update('iptv_menus', [$col => $url], ['id' => $menu['id']]);
        $this->log('iptv_menu.upload_' . $type, 'IptvMenu', $menu['id']);
        Response::success(['url' => $url], 'تصویر آپلود شد');
    }

    // ── Remove image ─────────────────────────────────────────────
    public function removeImage(Request $req, array $params): void
    {
        $menu = $this->getMenu((int)$params['id']);
        if (!$menu) { Response::notFound(); return; }

        $body = $req->json() ?: [];
        $type = in_array($body['type'] ?? 'bg', ['bg', 'logo']) ? ($body['type'] ?? 'bg') : 'bg';
        $col  = $type === 'bg' ? 'bg_image' : 'logo_url';

        $oldUrl = $menu[$col] ?? '';
        if ($oldUrl && str_contains($oldUrl, '/uploads/iptv/')) {
            $oldPath = PUBLIC_PATH . parse_url($oldUrl, PHP_URL_PATH);
            if (file_exists($oldPath)) @unlink($oldPath);
        }

        $this->db->update('iptv_menus', [$col => null], ['id' => $menu['id']]);
        $this->log('iptv_menu.remove_' . $type, 'IptvMenu', $menu['id']);
        Response::success(null, 'تصویر حذف شد');
    }

    // ── Delete menu ─────────────────────────────────────────────────
    public function destroy(Request $req, array $params): void
    {
        $menu = $this->getMenu((int)$params['id']);
        if (!$menu) Response::notFound();

        $this->db->delete('iptv_menu_items', ['menu_id' => $menu['id']]);
        $this->db->delete('iptv_menus',      ['id'      => $menu['id']]);

        Response::success(null, 'منو حذف شد');
    }

    // ── Add item ────────────────────────────────────────────────────
    public function storeItem(Request $req, array $params): void
    {
        $menu = $this->getMenu((int)$params['id']);
        if (!$menu) Response::notFound();

        $data  = $req->json() ?: $req->post();
        $label = trim($data['label'] ?? '');
        if (!$label) Response::error('عنوان آیتم الزامی است', 422);

        $maxOrd = (int)($this->db->query(
            "SELECT COALESCE(MAX(sort_order),0) FROM iptv_menu_items WHERE menu_id=?",
            [$menu['id']]
        )->fetchColumn() ?? 0);

        $id   = $this->insertItem($menu['id'], $data, $maxOrd + 1);
        $item = $this->db->row("SELECT * FROM iptv_menu_items WHERE id=?", [$id]);

        Response::success($item, 'آیتم اضافه شد', 201);
    }

    // ── Update item ─────────────────────────────────────────────────
    public function updateItem(Request $req, array $params): void
    {
        $menu = $this->getMenu((int)$params['id']);
        if (!$menu) Response::notFound();

        $item = $this->db->row(
            "SELECT * FROM iptv_menu_items WHERE id=? AND menu_id=?",
            [(int)$params['itemId'], $menu['id']]
        );
        if (!$item) Response::notFound();

        $data    = $req->json() ?: $req->input();
        $allowed = ['type', 'label', 'icon', 'color', 'target_url', 'config', 'sort_order', 'is_active'];
        $upd     = array_intersect_key($data, array_flip($allowed));

        if (isset($upd['config']) && is_array($upd['config'])) {
            $upd['config'] = json_encode($upd['config'], JSON_UNESCAPED_UNICODE);
        }

        if (!empty($upd)) {
            $this->db->update('iptv_menu_items', $upd, ['id' => $item['id']]);
        }

        Response::success(
            $this->db->row("SELECT * FROM iptv_menu_items WHERE id=?", [$item['id']]),
            'آیتم بروز شد'
        );
    }

    // ── Delete item ─────────────────────────────────────────────────
    public function destroyItem(Request $req, array $params): void
    {
        $menu = $this->getMenu((int)$params['id']);
        if (!$menu) Response::notFound();

        $this->db->delete('iptv_menu_items', [
            'id'      => (int)$params['itemId'],
            'menu_id' => $menu['id'],
        ]);

        Response::success(null, 'آیتم حذف شد');
    }

    // ── Reorder items ───────────────────────────────────────────────
    public function sortItems(Request $req, array $params): void
    {
        $menu = $this->getMenu((int)$params['id']);
        if (!$menu) Response::notFound();

        $body = $req->json() ?: $req->post();
        $ids  = $body['ids'] ?? [];
        if (!is_array($ids)) Response::error('ids باید آرایه باشد', 422);

        foreach ($ids as $ord => $itemId) {
            $this->db->update('iptv_menu_items',
                ['sort_order' => (int)$ord],
                ['id' => (int)$itemId, 'menu_id' => $menu['id']]
            );
        }

        Response::success(null, 'ترتیب بروز شد');
    }

    // ── Public endpoint (for screen player — no auth) ───────────────
    public function playerMenu(Request $req, array $params): void
    {
        $id   = (int)$params['id'];
        $menu = $this->db->row("SELECT * FROM iptv_menus WHERE id=? AND is_active=1", [$id]);
        if (!$menu) Response::notFound();

        $menu['items'] = $this->db->rows(
            "SELECT * FROM iptv_menu_items WHERE menu_id=? AND is_active=1 ORDER BY sort_order",
            [$id]
        ) ?: [];

        Response::success($menu);
    }

    // ── Helpers ─────────────────────────────────────────────────────
    private function getMenu(int $id): ?array
    {
        $tid = Auth::tenantId();
        return $this->db->row(
            "SELECT * FROM iptv_menus WHERE id=? AND tenant_id=?",
            [$id, $tid]
        ) ?: null;
    }

    private function insertItem(int $menuId, array $data, int $order = 0): int|string
    {
        $validTypes = ['live','vod','news','info','weather','fids','hotel','corporate','retail','url','custom'];
        $type       = in_array($data['type'] ?? '', $validTypes) ? $data['type'] : 'live';

        $config = $data['config'] ?? null;
        if (is_array($config)) $config = json_encode($config, JSON_UNESCAPED_UNICODE);

        return $this->db->insert('iptv_menu_items', [
            'menu_id'    => $menuId,
            'type'       => $type,
            'label'      => trim($data['label'] ?? $type),
            'icon'       => $data['icon'] ?? $this->defaultIcon($type),
            'color'      => $data['color'] ?? $this->defaultColor($type),
            'target_url' => $data['target_url'] ?? null,
            'config'     => $config,
            'sort_order' => $order,
            'is_active'  => 1,
        ]);
    }

    private function defaultIcon(string $type): string
    {
        return match($type) {
            'live'      => 'fas fa-satellite-dish',
            'vod'       => 'fas fa-film',
            'news'      => 'fas fa-newspaper',
            'info'      => 'fas fa-circle-info',
            'weather'   => 'fas fa-cloud-sun',
            'fids'      => 'fas fa-plane',
            'hotel'     => 'fas fa-hotel',
            'corporate' => 'fas fa-building-columns',
            'retail'    => 'fas fa-store',
            'url'       => 'fas fa-link',
            default     => 'fas fa-grip-dots',
        };
    }

    private function defaultColor(string $type): string
    {
        return match($type) {
            'live'      => '#ef4444',
            'vod'       => '#ec4899',
            'news'      => '#3b82f6',
            'info'      => '#8b5cf6',
            'weather'   => '#06b6d4',
            'fids'      => '#0ea5e9',
            'hotel'     => '#f59e0b',
            'corporate' => '#6366f1',
            'retail'    => '#10b981',
            'url'       => '#64748b',
            default     => '#f97316',
        };
    }
}
