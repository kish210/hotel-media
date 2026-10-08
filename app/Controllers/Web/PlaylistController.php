<?php declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};
use App\Models\Playlist;

class PlaylistController extends Controller
{
    private Playlist $playlist;
    public function __construct() { parent::__construct(); $this->playlist = new Playlist(); }

    public function index(Request $req): void
    {
        // فیلتر دنیا: تابلو یا تلویزیون اتاق. خالی = هر دو
        $world   = in_array($req->get('type'), ['signage', 'iptv'], true) ? $req->get('type') : '';
        $filters = $req->get();
        if ($world !== '') $filters['screen_type'] = $world;

        $result = $this->playlist->all($filters, (int)$req->get('page', 1));
        $this->view('playlists.index', [
            'title'     => 'پلی‌لیست‌ها',
            'playlists' => $result,
            'world'     => $world,
        ]);
    }

    public function create(Request $req): void
    {
        $layouts = $this->db->rows("SELECT id,name FROM layouts WHERE tenant_id=? AND is_active=1 ORDER BY name", [Auth::tenantId()]);
        $media   = $this->db->rows("SELECT id,name,type,thumbnail_path,file_path,url FROM media WHERE tenant_id=? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 200", [Auth::tenantId()]);
        $this->view('playlists.create', ['title' => 'پلی‌لیست جدید', 'layouts' => $layouts, 'media' => $media]);
    }

    public function store(Request $req): void
    {
        $name = trim($req->post('name',''));
        if (!$name) { $this->flash('error', 'نام پلی‌لیست الزامی است'); $this->redirect('/admin/playlists/create'); return; }
        $data = [
            'name'             => $name,
            'description'      => $req->post('description') ?: null,
            'screen_type'      => in_array($req->post('screen_type'), ['signage','iptv','any'], true)
                                    ? $req->post('screen_type') : 'signage',
            'layout_id'        => $req->post('layout_id') ?: null,
            'transition'       => $req->post('transition','fade'),
            'default_duration' => (int)$req->post('default_duration', 10),
            'shuffle'          => $req->post('shuffle') ? 1 : 0,
            'is_active'        => 1,
        ];
        $id = $this->playlist->create($data);
        $this->flash('success', 'پلی‌لیست ایجاد شد');
        $this->log('playlist.create', 'Playlist', (int)$id);
        $this->redirect('/admin/playlists/' . $id);
    }

    public function show(Request $req, array $params): void
    {
        $pl = $this->playlist->find((int)$params['id']);
        if (!$pl) { $this->redirect('/admin/playlists'); return; }

        $tid = Auth::tenantId();
        $items = $this->db->rows(
            "SELECT pi.*, m.name AS media_name, m.type, m.thumbnail_path, m.file_path, m.url
             FROM playlist_items pi
             LEFT JOIN media m ON m.id = pi.media_id
             WHERE pi.playlist_id=? AND pi.is_active=1 ORDER BY pi.sort_order ASC",
            [(int)$params['id']]
        );
        $media = $this->db->rows(
            "SELECT id,name,type,thumbnail_path,file_path,url FROM media WHERE tenant_id=? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 200",
            [$tid]
        );
        $screens = $this->db->rows(
            "SELECT s.id,s.name,s.is_online FROM screens s
             JOIN schedules sc ON sc.playlist_id=? AND (sc.screen_id=s.id OR sc.screen_id IS NULL)
             WHERE s.tenant_id=? AND s.status != 'inactive' LIMIT 10",
            [(int)$params['id'], $tid]
        );
        $this->view('playlists.show', compact('pl','items','media','screens') + ['title' => $pl['name'], 'playlist' => $pl]);
    }

    public function edit(Request $req, array $params): void
    {
        $pl      = $this->playlist->find((int)$params['id']);
        if (!$pl) { $this->redirect('/admin/playlists'); return; }
        $layouts = $this->db->rows("SELECT id,name FROM layouts WHERE tenant_id=? AND is_active=1 ORDER BY name", [Auth::tenantId()]);
        $media   = $this->db->rows("SELECT id,name,type,thumbnail_path,file_path,url,duration FROM media WHERE tenant_id=? AND deleted_at IS NULL ORDER BY created_at DESC", [Auth::tenantId()]);
        $this->view('playlists.edit', ['title' => 'ویرایش: ' . $pl['name'], 'playlist' => $pl, 'layouts' => $layouts, 'media' => $media]);
    }

    public function update(Request $req, array $params): void
    {
        $this->playlist->update((int)$params['id'], $req->post());
        $this->flash('success', 'پلی‌لیست به‌روز شد');
        $this->log('playlist.update', 'Playlist', (int)$params['id']);
        $this->redirect('/admin/playlists/' . $params['id'] . '/edit');
    }

    /**
     * جایگزینی لوگوی همیشگیِ گوشه‌ی تابلو.
     *
     * لوگو روی پلی‌لیست است نه روی صفحه: هتل ممکن است تابلوی لابی را با
     * لوگوی هتل و تابلوی سالن را با لوگوی همایش بزند، و اگر عمومی بود
     * تعویض یکی همه را عوض می‌کرد.
     */
    public function uploadLogo(Request $req, array $params): void
    {
        $this->storeImage($req, (int)$params['id'], 'logo_path', 'logo', 'لوگو');
    }

    /**
     * تصویر پس‌زمینه‌ی پشت ویدیو — همان مسیر آپلود، ستون دیگر.
     */
    public function uploadBackdrop(Request $req, array $params): void
    {
        $this->storeImage($req, (int)$params['id'], 'backdrop_image', 'backdrop', 'تصویر پس‌زمینه');
    }

    /**
     * آپلود مشترکِ تصویرهای برندینگ پلی‌لیست.
     *
     * یکی شدنشان عمدی است: دو نسخه‌ی جدا یعنی دو جا برای فراموش کردنِ
     * بررسی نوع فایل، و همان یک جا سوراخ امنیتی می‌شود.
     */
    private function storeImage(
        Request $req, int $id, string $column, string $field, string $label
    ): void {
        $pl = $this->playlist->find($id);
        if (!$pl) { $this->redirect('/admin/playlists'); return; }

        $back = '/admin/playlists/' . $id . '/edit';
        $file = $_FILES[$field] ?? null;

        /* حذف — خالی یعنی برگرد به حالت پیش‌فرض */
        if ($req->post('remove') === '1') {
            $this->deleteOldImage((string)($pl[$column] ?? ''));
            $this->playlist->update($id, [$column => null]);
            $this->flash('success', $label . ' حذف شد');
            $this->redirect($back);
            return;
        }

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash('error', 'فایلی انتخاب نشده یا آپلود ناقص ماند');
            $this->redirect($back);
            return;
        }
        if ($file['size'] > 3 * 1024 * 1024) {
            $this->flash('error', 'حجم ' . $label . ' نباید بیشتر از ۳ مگابایت باشد');
            $this->redirect($back);
            return;
        }

        /* پسوند فایل قابل اعتماد نیست — نوع واقعی خوانده می‌شود.
           SVG عمدا پذیرفته نمی‌شود: می‌تواند اسکریپت داشته باشد و روی
           صفحه‌ی پنل اجرا شود. */
        $mime    = (string)@mime_content_type($file['tmp_name']);
        $extMap  = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extMap[$mime])) {
            $this->flash('error', 'فرمت نامعتبر — فقط PNG، JPG یا WebP');
            $this->redirect($back);
            return;
        }

        $dir = PUBLIC_PATH . '/uploads/branding/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->flash('error', 'پوشه‌ی آپلود ساخته نشد');
            $this->redirect($back);
            return;
        }

        /* WebP روی موتور مرورگرِ تلویزیون‌های نسل ۲۰۱۳ (ماپل/اورسی)
           پشتیبانی نمی‌شود. هیچ خطایی هم نمی‌دهد — تصویر فقط بار
           نمی‌شود و گوشه‌ی تابلو خالی می‌ماند، که از پشت پنل دقیقا
           شبیه «آپلود نشده» دیده می‌شود. همین یک بار در همین هتل
           اتفاق افتاد، پس به‌جای رد کردن فایل، تبدیلش می‌کنیم. */
        $ext = $extMap[$mime];
        if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $img = @imagecreatefromwebp($file['tmp_name']);
            if ($img !== false) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                $tmpPng = $file['tmp_name'] . '.png';
                if (@imagepng($img, $tmpPng)) {
                    imagedestroy($img);
                    $name = $field . '_pl' . $id . '_' . time() . '.png';
                    if (@rename($tmpPng, $dir . $name)) {
                        @chmod($dir . $name, 0644);
                        $this->finishImage($id, $pl, $column, $field, $label, $name, $back);
                        return;
                    }
                    @unlink($tmpPng);
                } else {
                    imagedestroy($img);
                }
            }
            /* اگر تبدیل نشد، فایل اصلی ذخیره می‌شود — روی تلویزیون‌های
               جدیدتر webp کار می‌کند و نباید آپلود را کلا رد کنیم. */
        }

        $name = $field . '_pl' . $id . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
            $this->flash('error', 'ذخیره‌ی ' . $label . ' روی سرور ناموفق بود');
            $this->redirect($back);
            return;
        }

        $this->finishImage($id, $pl, $column, $field, $label, $name, $back);
    }

    /** ثبت مسیر تصویر تازه و پاک‌کردن قبلی — مشترک بین دو مسیر ذخیره */
    private function finishImage(
        int $id, array $pl, string $column, string $field,
        string $label, string $name, string $back
    ): void {
        $this->deleteOldImage((string)($pl[$column] ?? ''));
        $this->playlist->update($id, [$column => '/uploads/branding/' . $name]);
        $this->log('playlist.' . $field, 'Playlist', $id);
        $this->flash('success', $label . ' جایگزین شد');
        $this->redirect($back);
    }

    /** فایل قبلی را پاک می‌کند، ولی فقط اگر واقعا داخل پوشه‌ی برندینگ باشد */
    private function deleteOldImage(string $path): void
    {
        if ($path === '' || !str_starts_with($path, '/uploads/branding/')) return;
        $abs = PUBLIC_PATH . $path;
        if (is_file($abs)) @unlink($abs);
    }

    public function destroy(Request $req, array $params): void
    {
        $this->playlist->delete((int)$params['id']);
        $this->flash('success', 'پلی‌لیست حذف شد');
        $this->redirect('/admin/playlists');
    }

    public function addItem(Request $req, array $params): void
    {
        $playlistId   = (int)$params['id'];
        $mediaId      = (int)$req->post('media_id', 0);
        $duration     = (int)$req->post('duration', 10);
        $contentType  = $req->post('content_type', 'media');
        $streamUrl    = $req->post('stream_url', '');
        $xmlUrl       = $req->post('xml_url', '');
        $webpageUrl   = $req->post('webpage_url', '');
        $startAt      = $req->post('start_at') ?: null;
        $endAt        = $req->post('end_at') ?: null;

        // اگه module → یه media record با type=module بساز
        if (!$mediaId && $contentType === 'module') {
            $moduleType     = $req->post('module_type', '');
            $moduleSettings = $req->post('module_settings', '{}');
            if ($moduleType) {
                $moduleUrl = '/player/module/' . $moduleType . '?settings=' . urlencode($moduleSettings);
                $mediaId   = $this->db->insert('media', [
                    'tenant_id'     => \App\Core\Auth::tenantId(),
                    'uploaded_by'   => \App\Core\Auth::id() ?? 1,
                    'name'          => 'ماژول: ' . $moduleType,
                    'original_name' => $moduleType,
                    'type'          => 'url',
                    'url'           => $moduleUrl,
                    'file_path'     => $moduleUrl,
                    'mime_type'     => 'application/x-signage-module',
                    'file_size'     => 0,
                    'meta'          => json_encode(['module_type'=>$moduleType,'module_settings'=>json_decode($moduleSettings,true)]),
                ]);
            }
        }

        // اگه stream/xml/url → یه media record بساز
        if (!$mediaId && in_array($contentType, ['stream', 'xml', 'url'])) {
            $url   = $streamUrl ?: $xmlUrl ?: $webpageUrl;
            $type  = $contentType === 'stream' ? (str_starts_with($url,'rtsp://')?'video':'url') : 'url';
            $name  = $req->post('stream_name') ?: ($contentType === 'xml' ? 'محتوای XML' : 'صفحه وب');
            if ($url) {
                $mediaId = $this->db->insert('media', [
                    'tenant_id'     => \App\Core\Auth::tenantId(),
                    'uploaded_by'   => \App\Core\Auth::id() ?? 1,
                    'name'          => $name,
                    'original_name' => $url,
                    'type'          => $type,
                    'url'           => $url,
                    'file_path'     => $url,
                    'mime_type'     => (str_contains($url,'.m3u8') ? 'application/x-mpegURL' :
                                       (str_contains($url,'.xml') ? 'application/xml' : 'text/uri-list')),
                    'file_size'     => 0,
                ]);
            }
        }

        if (!$mediaId) {
            $this->flash('error', 'رسانه انتخاب نشده یا آدرس معتبر نیست');
            $this->redirect('/admin/playlists/' . $playlistId);
            return;
        }

        // بررسی دسترسی
        $pl = $this->db->row(
            "SELECT id FROM playlists WHERE id=? AND tenant_id=?",
            [$playlistId, Auth::tenantId()]
        );
        if (!$pl) { $this->redirect('/admin/playlists'); return; }

        // آخرین ترتیب
        $maxOrder = (int)$this->db->value(
            "SELECT COALESCE(MAX(sort_order),0) FROM playlist_items WHERE playlist_id=?",
            [$playlistId]
        );

        $this->db->insert('playlist_items', [
            'playlist_id' => $playlistId,
            'media_id'    => $mediaId,
            'duration'    => $duration,
            'start_at'    => $startAt,
            'end_at'      => $endAt,
            'sort_order'  => $maxOrder + 1,
            'is_active'   => 1,
        ]);


        $this->flash('success', 'رسانه به پلی‌لیست اضافه شد');
        $this->redirect('/admin/playlists/' . $playlistId);
    }

    /**
     * افزودن گروهی رسانه — چند عکس/ویدیو با یک درخواست.
     * body: media_ids (آرایه یا JSON)، duration، start_at، end_at
     * پاسخ JSON می‌دهد چون از مودال با fetch صدا زده می‌شود.
     */
    public function addItemsBulk(Request $req, array $params): void
    {
        $playlistId = (int)$params['id'];

        $pl = $this->db->row(
            "SELECT id FROM playlists WHERE id=? AND tenant_id=?",
            [$playlistId, Auth::tenantId()]
        );
        if (!$pl) { \App\Core\Response::error('پلی‌لیست یافت نشد', 404); return; }

        $ids = $req->post('media_ids', []);
        if (is_string($ids)) $ids = json_decode($ids, true) ?: [];
        // یکتا و مرتب: ترتیب انتخاب کاربر حفظ شود ولی تکراری‌ها حذف
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array)$ids),
            static fn($v) => $v > 0
        )));
        if (!$ids) { \App\Core\Response::error('رسانه‌ای انتخاب نشده', 422); return; }

        $duration = max(1, (int)$req->post('duration', 10));
        $startAt  = $req->post('start_at') ?: null;
        $endAt    = $req->post('end_at') ?: null;
        $tid      = Auth::tenantId();

        $maxOrder = (int)$this->db->value(
            "SELECT COALESCE(MAX(sort_order),0) FROM playlist_items WHERE playlist_id=?",
            [$playlistId]
        );

        $added = 0;
        foreach ($ids as $mid) {
            // فقط رسانه‌ی همین tenant — جلوی افزودن id دستکاری‌شده
            $owns = $this->db->value(
                "SELECT id FROM media WHERE id=? AND tenant_id=? AND deleted_at IS NULL",
                [$mid, $tid]
            );
            if (!$owns) continue;

            $this->db->insert('playlist_items', [
                'playlist_id' => $playlistId,
                'media_id'    => $mid,
                'duration'    => $duration,
                'start_at'    => $startAt,
                'end_at'      => $endAt,
                'sort_order'  => ++$maxOrder,
                'is_active'   => 1,
            ]);
            $added++;
        }

        if (!$added) { \App\Core\Response::error('هیچ رسانه‌ی معتبری اضافه نشد', 422); return; }

        $this->log('playlist.items.bulk', 'Playlist', $playlistId);
        \App\Core\Response::success(['added' => $added], "$added رسانه به پلی‌لیست اضافه شد");
    }

    public function removeItem(Request $req, array $params): void
    {
        $this->db->update(
            'playlist_items',
            ['is_active' => 0],
            ['id' => (int)$params['iid'], 'playlist_id' => (int)$params['id']]
        );
        $this->flash('success', 'آیتم حذف شد');
        $this->redirect('/admin/playlists/' . $params['id']);
    }

    public function reorderItems(Request $req, array $params): void
    {
        $order = $req->post('order', []);
        if (is_string($order)) $order = json_decode($order, true) ?: [];
        foreach ($order as $idx => $itemId) {
            $this->db->update('playlist_items', ['sort_order' => $idx + 1], ['id' => (int)$itemId]);
        }
        \App\Core\Response::success(null, 'ترتیب ذخیره شد');
    }


    public function editItem(Request $req, array $params): void
    {
        /* fit_mode از فهرست بسته می‌آید؛ هر چیز دیگری به contain
           برمی‌گردد تا مقدار دست‌ساز در ENUM خطا ندهد. */
        $fit = $req->post('fit_mode') === 'cover' ? 'cover' : 'contain';

        /* چک‌باکسِ تیک‌نخورده POST نمی‌شود، پس یک hidden با مقدار ۱
           جلوتر از آن است و «بی‌صدا» پیش‌فرض می‌ماند. */
        $muted = $req->post('muted') === '0' ? 0 : 1;

        $this->db->update('playlist_items', [
            'duration'   => (int)$req->post('duration', 10),
            'start_at'   => $req->post('start_at') ?: null,
            'end_at'     => $req->post('end_at') ?: null,
            'volume'     => (int)$req->post('volume', 100),
            'fit_mode'   => $fit,
            'muted'      => $muted,
        ], ['id' => (int)$params['iid'], 'playlist_id' => (int)$params['id']]);
        $this->flash('success', 'آیتم ویرایش شد');
        $this->redirect('/admin/playlists/' . $params['id']);
    }

}