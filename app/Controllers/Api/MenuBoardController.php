<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};

/**
 * Menu Board Controller
 * منوی تصویری: IT هتل عکس منوی چاپی رستوران، روم‌سرویس یا خشک‌شویی را
 * بارگذاری می‌کند و همان روی تلویزیون اتاق نمایش داده می‌شود.
 *
 * چرا این روش: اکثر هتل‌ها منوی طراحی‌شده و چاپی دارند و حاضر نیستند
 * تک‌تک اقلام را دوباره در سیستم وارد کنند. با این، راه‌اندازی از چند روز
 * به چند دقیقه می‌رسد. اقلام قابل سفارش (guest_services) کنار این باقی
 * می‌مانند برای هتلی که سفارش تعاملی می‌خواهد.
 *
 * فاز ۴ نقشه‌راه — docs/TODO.md
 */
class MenuBoardController extends Controller
{
    public const CATEGORIES = [
        'restaurant', 'room_service', 'laundry', 'minibar', 'breakfast', 'spa', 'other',
    ];

    /** فرمت‌های تصویر مجاز — PDF عمدا نیست: تلویزیون نمی‌تواند رندرش کند */
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** سقف هر صفحه — عکس موبایل معمولا ۳ تا ۸ مگابایت است */
    private const MAX_BYTES = 15 * 1024 * 1024;

    /** بیشتر از این عرض روی تلویزیون فایده ندارد و فقط بوت را کند می‌کند */
    private const MAX_WIDTH = 2560;

    // ══════════════════════════════════════════════════════════════
    //  تخته‌ها
    // ══════════════════════════════════════════════════════════════

    public function index(Request $req): void
    {
        $tid = Auth::tenantId();

        $boards = $this->db->rows(
            'SELECT b.*, (SELECT COUNT(*) FROM menu_board_pages p WHERE p.board_id = b.id) AS page_count
               FROM menu_boards b WHERE b.tenant_id = ?
              ORDER BY b.category, b.sort_order, b.id',
            [$tid]
        );

        foreach ($boards as &$b) {
            $b['pages'] = $this->db->rows(
                'SELECT id, image_url, caption, sort_order, width, height, file_size
                   FROM menu_board_pages WHERE board_id = ? ORDER BY sort_order, id',
                [(int)$b['id']]
            );
        }
        unset($b);

        Response::success($boards);
    }

    public function store(Request $req): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: $req->post() ?: [];

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') { Response::error('عنوان منو الزامی است', 422); return; }

        $cat = $data['category'] ?? 'restaurant';
        if (!in_array($cat, self::CATEGORIES, true)) { Response::error('دسته نامعتبر است', 422); return; }

        $id = $this->db->insert('menu_boards', [
            'tenant_id'   => $tid,
            'category'    => $cat,
            'title'       => mb_substr($title, 0, 160),
            'title_en'    => trim((string)($data['title_en'] ?? '')) ?: null,
            'description' => trim((string)($data['description'] ?? '')) ?: null,
            'sort_order'  => (int)($data['sort_order'] ?? 0),
            'is_active'   => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
        ]);

        $this->log('menu_board.create', 'menu_board', (int)$id, [], ['title' => $title]);
        Response::success(['id' => (int)$id], 'منو ساخته شد', 201);
    }

    public function update(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        if (!$this->db->exists('menu_boards', ['id' => $id, 'tenant_id' => $tid])) {
            Response::notFound('منو یافت نشد'); return;
        }

        $data   = $req->json() ?: [];
        $fields = [];

        if (array_key_exists('title', $data)) {
            $t = trim((string)$data['title']);
            if ($t === '') { Response::error('عنوان نمی‌تواند خالی باشد', 422); return; }
            $fields['title'] = mb_substr($t, 0, 160);
        }
        if (array_key_exists('title_en', $data))    $fields['title_en']    = trim((string)$data['title_en']) ?: null;
        if (array_key_exists('description', $data)) $fields['description'] = trim((string)$data['description']) ?: null;
        if (isset($data['category'])) {
            if (!in_array($data['category'], self::CATEGORIES, true)) { Response::error('دسته نامعتبر است', 422); return; }
            $fields['category'] = $data['category'];
        }
        if (isset($data['sort_order'])) $fields['sort_order'] = (int)$data['sort_order'];
        if (isset($data['is_active']))  $fields['is_active']  = (int)(bool)$data['is_active'];

        if (!$fields) { Response::error('چیزی برای به‌روزرسانی ارسال نشده', 422); return; }

        $this->db->update('menu_boards', $fields, ['id' => $id, 'tenant_id' => $tid]);
        Response::success(null, 'به‌روزرسانی شد');
    }

    public function destroy(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        if (!$this->db->exists('menu_boards', ['id' => $id, 'tenant_id' => $tid])) {
            Response::notFound('منو یافت نشد'); return;
        }

        // فایل‌های تصویر هم پاک می‌شوند، وگرنه دیسک سرور پر می‌شود
        foreach ($this->db->rows('SELECT image_url FROM menu_board_pages WHERE board_id = ?', [$id]) as $p) {
            $this->deleteImage((string)$p['image_url']);
        }

        $this->db->query('DELETE FROM menu_board_pages WHERE board_id = ?', [$id]);
        $this->db->delete('menu_boards', ['id' => $id, 'tenant_id' => $tid]);

        $this->log('menu_board.delete', 'menu_board', $id);
        Response::success(null, 'منو و تصویرهایش حذف شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  صفحه‌ها (تصویرها)
    // ══════════════════════════════════════════════════════════════

    /**
     * POST /menu-boards/{id}/pages — بارگذاری عکس منو
     * multipart با فیلد image، یا چند فایل با image[]
     */
    public function uploadPage(Request $req, array $params): void
    {
        $tid     = Auth::tenantId();
        $boardId = (int)($params['id'] ?? 0);

        if (!$this->db->exists('menu_boards', ['id' => $boardId, 'tenant_id' => $tid])) {
            Response::notFound('منو یافت نشد'); return;
        }

        $files = $this->normalizeFiles($_FILES['image'] ?? null);
        if (!$files) { Response::error('تصویری ارسال نشده', 422); return; }

        $dir = PUBLIC_PATH . "/uploads/menus/{$tid}";
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            Response::error('ساخت پوشه بارگذاری ممکن نشد', 500); return;
        }

        $nextOrder = (int)$this->db->value(
            'SELECT COALESCE(MAX(sort_order), 0) FROM menu_board_pages WHERE board_id = ?',
            [$boardId]
        );

        $saved  = [];
        $errors = [];

        foreach ($files as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = $this->uploadErrorText((int)($file['error'] ?? 0), (string)($file['name'] ?? ''));
                continue;
            }
            if ((int)$file['size'] > self::MAX_BYTES) {
                $errors[] = ($file['name'] ?? 'فایل') . ' — بیش از ۱۵ مگابایت';
                continue;
            }

            $mime = mime_content_type($file['tmp_name']) ?: '';
            if (!isset(self::ALLOWED_MIME[$mime])) {
                $errors[] = ($file['name'] ?? 'فایل') . " — فرمت «$mime» پشتیبانی نمی‌شود (JPG، PNG یا WebP)";
                continue;
            }

            // ابعاد واقعی، نه چیزی که نام فایل ادعا می‌کند
            $dims = @getimagesize($file['tmp_name']);
            if (!$dims) { $errors[] = ($file['name'] ?? 'فایل') . ' — تصویر معتبر نیست'; continue; }

            $name = 'menu_' . $boardId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4))
                  . '.' . self::ALLOWED_MIME[$mime];
            $dest = $dir . '/' . $name;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $errors[] = ($file['name'] ?? 'فایل') . ' — ذخیره روی سرور ناموفق بود';
                continue;
            }

            [$w, $h] = $this->shrinkIfNeeded($dest, $mime, (int)$dims[0], (int)$dims[1]);

            $url = "/uploads/menus/{$tid}/{$name}";
            $id  = $this->db->insert('menu_board_pages', [
                'board_id'   => $boardId,
                'image_url'  => $url,
                'sort_order' => ++$nextOrder,
                'width'      => min(65535, $w),
                'height'     => min(65535, $h),
                'file_size'  => filesize($dest) ?: null,
            ]);

            $saved[] = ['id' => (int)$id, 'image_url' => $url, 'width' => $w, 'height' => $h];
        }

        if (!$saved) {
            Response::error($errors ? implode(' · ', $errors) : 'هیچ تصویری ذخیره نشد', 422);
            return;
        }

        $this->log('menu_board.upload', 'menu_board', $boardId, [], ['pages' => count($saved)]);

        Response::success(
            ['pages' => $saved, 'errors' => $errors],
            count($saved) . ' صفحه بارگذاری شد' . ($errors ? '، ' . count($errors) . ' مورد رد شد' : ''),
            201
        );
    }

    public function destroyPage(Request $req, array $params): void
    {
        $tid    = Auth::tenantId();
        $pageId = (int)($params['pageId'] ?? 0);

        $page = $this->db->row(
            'SELECT p.id, p.image_url FROM menu_board_pages p
               JOIN menu_boards b ON b.id = p.board_id
              WHERE p.id = ? AND b.tenant_id = ?',
            [$pageId, $tid]
        );
        if (!$page) { Response::notFound('صفحه یافت نشد'); return; }

        $this->deleteImage((string)$page['image_url']);
        $this->db->query('DELETE FROM menu_board_pages WHERE id = ?', [$pageId]);

        Response::success(null, 'صفحه حذف شد');
    }

    /** POST /menu-boards/{id}/pages/sort — body: { order: [pageId, …] } */
    public function sortPages(Request $req, array $params): void
    {
        $tid     = Auth::tenantId();
        $boardId = (int)($params['id'] ?? 0);

        if (!$this->db->exists('menu_boards', ['id' => $boardId, 'tenant_id' => $tid])) {
            Response::notFound('منو یافت نشد'); return;
        }

        $order = $req->json()['order'] ?? null;
        if (!is_array($order) || !$order) { Response::error('ترتیب صفحه‌ها ارسال نشده', 422); return; }

        $i = 0;
        foreach ($order as $pageId) {
            $this->db->query(
                'UPDATE menu_board_pages SET sort_order = ? WHERE id = ? AND board_id = ?',
                [++$i, (int)$pageId, $boardId]
            );
        }

        Response::success(null, 'ترتیب ذخیره شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /**
     * ‏$_FILES برای یک فایل و چند فایل ساختار متفاوتی دارد؛ یکسانش می‌کنیم.
     * @return list<array<string,mixed>>
     */
    private function normalizeFiles(mixed $input): array
    {
        if (!is_array($input) || !isset($input['tmp_name'])) return [];

        if (!is_array($input['tmp_name'])) return [$input];

        $out = [];
        foreach (array_keys($input['tmp_name']) as $i) {
            $out[] = [
                'name'     => $input['name'][$i]     ?? '',
                'type'     => $input['type'][$i]     ?? '',
                'tmp_name' => $input['tmp_name'][$i] ?? '',
                'error'    => $input['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
                'size'     => $input['size'][$i]     ?? 0,
            ];
        }

        return array_slice($out, 0, 30);
    }

    /**
     * عکس موبایل معمولا ۴۰۰۰ پیکسل عرض دارد. روی تلویزیون بیشتر از
     * ۲۵۶۰ دیده نمی‌شود و فقط بوت را کند می‌کند.
     * @return array{0:int,1:int} ابعاد نهایی
     */
    private function shrinkIfNeeded(string $path, string $mime, int $w, int $h): array
    {
        if ($w <= self::MAX_WIDTH || !function_exists('imagecreatetruecolor')) return [$w, $h];

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default      => false,
        };
        if (!$src) return [$w, $h];

        $nw = self::MAX_WIDTH;
        $nh = (int)round($h * ($nw / $w));

        $dst = imagecreatetruecolor($nw, $nh);
        // شفافیت PNG نباید سیاه شود
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        match ($mime) {
            'image/jpeg' => imagejpeg($dst, $path, 85),
            'image/png'  => imagepng($dst, $path, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($dst, $path, 85) : null,
            default      => null,
        };

        imagedestroy($src);
        imagedestroy($dst);

        return [$nw, $nh];
    }

    /** حذف امن فایل — فقط از داخل پوشه‌ی بارگذاری منوها */
    private function deleteImage(string $url): void
    {
        if (!str_starts_with($url, '/uploads/menus/')) return;

        $path = realpath(PUBLIC_PATH . $url);
        $base = realpath(PUBLIC_PATH . '/uploads/menus');

        if ($path && $base && str_starts_with($path, $base) && is_file($path)) {
            @unlink($path);
        }
    }

    private function uploadErrorText(int $code, string $name): string
    {
        $label = $name !== '' ? $name : 'فایل';

        return $label . ' — ' . match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'حجم بیش از حد مجاز سرور',
            UPLOAD_ERR_PARTIAL    => 'بارگذاری ناقص ماند',
            UPLOAD_ERR_NO_FILE    => 'فایلی انتخاب نشده',
            UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت سرور تعریف نشده',
            UPLOAD_ERR_CANT_WRITE => 'نوشتن روی دیسک سرور ممکن نشد',
            default               => 'خطای بارگذاری',
        };
    }
}
