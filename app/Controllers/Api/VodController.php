<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Response, Request, Auth};

class VodController extends Controller
{
    private int $tid;

    public function __construct()
    {
        parent::__construct();
        $this->tid = Auth::check() ? (int)Auth::tenantId() : 1;
    }

    // ══════════════════════════════════════════════════════
    // CATEGORIES
    // ══════════════════════════════════════════════════════

    /** GET /api/v1/vod/categories */
    public function categories(Request $req): void
    {
        $rows = $this->db->rows(
            "SELECT c.*, COUNT(v.id) AS video_count
             FROM vod_categories c
             LEFT JOIN vod_videos v ON v.category_id=c.id AND v.is_active=1 AND v.tenant_id=c.tenant_id
             WHERE c.tenant_id=? AND c.is_active=1
             GROUP BY c.id ORDER BY c.sort_order, c.name",
            [$this->tid]
        );
        Response::success($rows);
    }

    /** POST /api/v1/vod/categories */
    public function storeCategory(Request $req): void
    {
        $name = trim($req->post('name', ''));
        if (!$name) Response::error('نام دسته‌بندی الزامی است', 422);

        $slug = $this->makeSlug($name, 'vod_categories');
        $id   = $this->db->insert('vod_categories', [
            'tenant_id'  => $this->tid,
            'parent_id'  => $req->post('parent_id') ?: null,
            'name'       => $name,
            'slug'       => $slug,
            'description'=> $req->post('description') ?: null,
            'color'      => $req->post('color', '#7c3aed'),
            'sort_order' => (int)$req->post('sort_order', 0),
        ]);
        Response::success($this->db->row("SELECT * FROM vod_categories WHERE id=?", [$id]), 'دسته‌بندی ساخته شد', 201);
    }

    /** PUT /api/v1/vod/categories/{id} */
    public function updateCategory(Request $req, array $p): void
    {
        $data = array_filter([
            'name'        => $req->post('name'),
            'description' => $req->post('description'),
            'color'       => $req->post('color'),
            'sort_order'  => $req->post('sort_order') !== null ? (int)$req->post('sort_order') : null,
            'is_active'   => $req->post('is_active') !== null ? (int)$req->post('is_active') : null,
        ], fn($v) => $v !== null);
        $this->db->update('vod_categories', $data, ['id' => (int)$p['id'], 'tenant_id' => $this->tid]);
        Response::success(null, 'به‌روز شد');
    }

    /** DELETE /api/v1/vod/categories/{id} */
    public function deleteCategory(Request $req, array $p): void
    {
        $id = (int)$p['id'];
        // انتقال ویدیوها به بدون دسته‌بندی
        $this->db->query("UPDATE vod_videos SET category_id=NULL WHERE category_id=? AND tenant_id=?", [$id, $this->tid]);
        $this->db->delete('vod_categories', ['id' => $id, 'tenant_id' => $this->tid]);
        Response::success(null, 'دسته‌بندی حذف شد');
    }

    // ══════════════════════════════════════════════════════
    // VIDEOS — LIST & DETAIL
    // ══════════════════════════════════════════════════════

    /** GET /api/v1/vod/videos */
    public function videos(Request $req): void
    {
        $catId    = $req->get('category_id');
        $search   = trim($req->get('search', ''));
        $type     = $req->get('type');
        $featured = $req->get('featured');
        $sort     = $req->get('sort', 'newest'); // newest|oldest|name|size|views
        $page     = max(1, (int)$req->get('page', 1));
        $perPage  = min(100, max(10, (int)$req->get('per_page', 24)));

        $where  = ['v.tenant_id = ?'];
        $params = [$this->tid];

        $where[] = 'v.is_active = 1';
        if ($catId)    { $where[] = 'v.category_id = ?'; $params[] = (int)$catId; }
        if ($search)   { $where[] = '(v.title LIKE ? OR v.description LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($type)     { $where[] = 'v.type = ?'; $params[] = $type; }
        if ($featured) { $where[] = 'v.is_featured = 1'; }

        $orderMap = [
            'newest'  => 'v.created_at DESC',
            'oldest'  => 'v.created_at ASC',
            'name'    => 'v.title ASC',
            'size'    => 'v.file_size DESC',
            'views'   => 'v.views DESC',
        ];
        $order = $orderMap[$sort] ?? 'v.created_at DESC';

        $wSql   = 'WHERE ' . implode(' AND ', $where);
        $total  = (int)$this->db->query("SELECT COUNT(*) FROM vod_videos v $wSql", $params)->fetchColumn();
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->rows(
            "SELECT v.*, c.name AS category_name, c.color AS category_color
             FROM vod_videos v
             LEFT JOIN vod_categories c ON c.id = v.category_id
             $wSql ORDER BY $order LIMIT $perPage OFFSET $offset",
            $params
        );

        Response::json([
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => ceil($total / $perPage),
            ],
        ]);
    }

    /** GET /api/v1/vod/videos/{id} */
    public function showVideo(Request $req, array $p): void
    {
        $v = $this->db->row(
            "SELECT v.*, c.name AS category_name FROM vod_videos v
             LEFT JOIN vod_categories c ON c.id=v.category_id
             WHERE v.id=? AND v.tenant_id=?",
            [(int)$p['id'], $this->tid]
        );
        if (!$v) Response::notFound('ویدیو پیدا نشد');
        // افزایش بازدید
        $this->db->query("UPDATE vod_videos SET views=views+1 WHERE id=?", [(int)$p['id']]);

        /* زیرنویس و باند صوتی همراه خود ویدیو برمی‌گردد، نه با درخواست
           جدا — پلیر تلویزیون روی شبکه‌ی کند هتل نباید برای شروع پخش
           منتظر دو رفت‌وبرگشت بماند.

           زبان اتاق (اگر پذیرش ثبت کرده) بر پیش‌فرض اپراتور مقدم است:
           مهمانی که فارسی نمی‌داند نباید در منوی ناآشنا دنبال تنظیمات
           زیرنویس بگردد. */
        $lang = null;
        $code = trim((string)$req->get('screen', ''));
        if ($code !== '') {
            /* ارتباط از سمت صفحه است (screens.iptv_room_id)، نه از سمت
               اتاق. و زبان مهمان از قبل در guest_lang بود — ستون جدید
               لازم نبود. */
            $lang = $this->db->value(
                'SELECT r.guest_lang
                   FROM screens s
                   JOIN iptv_rooms r ON r.id = s.iptv_room_id
                  WHERE s.code = ? AND s.tenant_id = ?',
                [$code, $this->tid]
            );
        }

        $tracks = (new \App\Services\SubtitleService($this->db))
            ->forPlayer((int)$p['id'], $lang ? (string)$lang : null);

        $v['subtitles'] = $tracks['subtitles'];
        $v['audio']     = $tracks['audio'];

        Response::success($v);
    }

    // ══════════════════════════════════════════════════════
    // UPLOAD
    // ══════════════════════════════════════════════════════

    /** POST /api/v1/vod/upload */
    public function upload(Request $req): void
    {
        // بدون محدودیت زمانی — برای تبدیل فایل‌های بزرگ
        set_time_limit(0);

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Response::error('فایل معتبر نیست: ' . ($file['error'] ?? 'no file'), 400);
        }

        $allowedMimes = [
            'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
            'video/x-msvideo', 'video/x-matroska', 'video/mpeg',
            'video/3gpp', 'video/x-flv', 'video/mp2t',
        ];
        $mime = mime_content_type($file['tmp_name']) ?: $file['type'];
        if (!in_array($mime, $allowedMimes, true)) {
            Response::error("نوع فایل مجاز نیست: $mime", 422);
        }

        // محدودیت ۵ گیگابایت
        $maxSize = 5 * 1024 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            Response::error('حجم فایل بیش از ۵ گیگابایت مجاز است', 413);
        }

        // آماده‌سازی مسیر
        $uploadDir = PUBLIC_PATH . '/uploads/vod/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $safeName = preg_replace('/[^a-z0-9_-]/i', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $safeName = substr($safeName, 0, 80);
        $uid      = uniqid();
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'mp4';
        $fileName = $safeName . '_' . $uid . '.' . $ext;
        $destPath = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Response::error('خطا در ذخیره فایل', 500);
        }

        /* ── پردازش دیگر اینجا انجام نمی‌شود ─────────────────────────
           قبلا همین‌جا ffmpeg اجرا می‌شد: یک‌بار با `-c copy -f mpegts`
           و در صورت شکست با libx264. سه ایراد داشت:

             • داخل درخواست HTTP بود. فیلم چندگیگابایتی از
               request_terminate_timeout رد می‌شد؛ کاربر «ناموفق»
               می‌دید، ردیفی ثبت نمی‌شد و ffmpeg یتیم ادامه می‌داد.
             • `-c copy` کدک را نگاه نمی‌کرد. H.265 بی‌اعتراض داخل TS
               کپی می‌شد، «موفق» شمرده می‌شد، فایل اصلی حذف می‌شد و
               وضعیت `ready` ثبت می‌شد — فیلمی که هیچ تلویزیونی پخشش
               نمی‌کند و اصلش هم رفته.
             • خروجی MPEG-TS بود، در حالی که پلیر این ردیف‌ها یک تگ
               <video> است و .ts را پخش نمی‌کند.

           حالا ردیف با وضعیت `queued` ثبت می‌شود و
           ‏VodPipelineService در پس‌زمینه probe/تبدیل/HLS/پوستر را
           انجام می‌دهد. متادیتا را هم همان مرحله می‌نویسد، چون تا
           تبدیل تمام نشود ابعاد و کدک نهایی معلوم نیست. */

        $catId = $req->post('category_id') ? (int)$req->post('category_id') : null;
        $title = trim($req->post('title', '')) ?: pathinfo($file['name'], PATHINFO_FILENAME);
        $title = preg_replace('/[_\-]+/', ' ', $title);

        $id = $this->db->insert('vod_videos', [
            'tenant_id'      => $this->tid,
            'category_id'    => $catId,
            'title'          => $title,
            'type'           => 'upload',
            'file_path'      => '/uploads/vod/' . $fileName,
            'source_path'    => '/uploads/vod/' . $fileName,
            'file_name'      => $file['name'],
            'file_size'      => filesize($destPath) ?: $file['size'],
            'mime_type'      => $mime,
            'status'         => 'queued',
            'uploaded_by'    => Auth::id() ?? null,
        ]);

        $queued = (new \App\Services\VodPipelineService($this->db))->enqueue((int)$id);

        $video = $this->db->row("SELECT * FROM vod_videos WHERE id=?", [$id]);
        $this->log('vod.upload', 'VodVideo', (int)$id);
        Response::success($video, $queued['message'], 201);
    }

    /** POST /api/v1/vod/videos — اضافه کردن URL */
    public function storeUrl(Request $req): void
    {
        $url = trim($req->post('stream_url', ''));
        if (!$url) Response::error('آدرس URL الزامی است', 422);

        $title = trim($req->post('title', '')) ?: 'ویدیوی جدید';
        $type  = $this->detectUrlType($url);

        $id = $this->db->insert('vod_videos', [
            'tenant_id'   => $this->tid,
            'category_id' => $req->post('category_id') ? (int)$req->post('category_id') : null,
            'title'       => $title,
            'description' => $req->post('description') ?: null,
            'type'        => $type,
            'stream_url'  => $url,
            'thumbnail'   => $req->post('thumbnail') ?: null,
            'status'      => 'ready',
            'uploaded_by' => Auth::id() ?? null,
        ]);

        Response::success($this->db->row("SELECT * FROM vod_videos WHERE id=?", [$id]), 'ویدیو اضافه شد', 201);
    }

    // ══════════════════════════════════════════════════════
    // UPDATE & DELETE
    // ══════════════════════════════════════════════════════

    /** PUT /api/v1/vod/videos/{id} */
    public function updateVideo(Request $req, array $p): void
    {
        $allowed = ['title','title_en','description','category_id','tags','year','language',
                    'is_featured','is_active','thumbnail','sort_order'];
        $data = [];
        foreach ($allowed as $k) {
            $v = $req->post($k);
            if ($v !== null) $data[$k] = $k === 'tags' ? json_encode(explode(',', $v)) : $v;
        }
        if (empty($data)) Response::error('داده‌ای ارسال نشد', 400);
        $this->db->update('vod_videos', $data, ['id' => (int)$p['id'], 'tenant_id' => $this->tid]);
        Response::success(null, 'ویدیو به‌روز شد');
    }

    /** DELETE /api/v1/vod/videos/{id} */
    public function deleteVideo(Request $req, array $p): void
    {
        $v = $this->db->row("SELECT * FROM vod_videos WHERE id=? AND tenant_id=?", [(int)$p['id'], $this->tid]);
        if (!$v) Response::notFound();

        // حذف فایل فیزیکی
        if ($v['file_path'] && file_exists(PUBLIC_PATH . $v['file_path'])) {
            @unlink(PUBLIC_PATH . $v['file_path']);
        }
        if ($v['thumbnail'] && $v['thumbnail_auto'] && file_exists(PUBLIC_PATH . $v['thumbnail'])) {
            @unlink(PUBLIC_PATH . $v['thumbnail']);
        }
        /* فایل اصلی و قطعه‌های HLS هم باید بروند، وگرنه هر آپلود
           دوبرابرِ حجم فیلم روی دیسک جا می‌گذارد و دیسک سرور هتل
           بی‌صدا پر می‌شود. */
        if (!empty($v['source_path']) && $v['source_path'] !== $v['file_path']
            && file_exists(PUBLIC_PATH . $v['source_path'])) {
            @unlink(PUBLIC_PATH . $v['source_path']);
        }
        $this->purgeHls((int)$p['id']);

        $this->db->delete('vod_videos', ['id' => (int)$p['id'], 'tenant_id' => $this->tid]);
        $this->log('vod.delete', 'VodVideo', (int)$p['id']);
        Response::success(null, 'ویدیو حذف شد');
    }

    private function purgeHls(int $id): void
    {
        $dir = PUBLIC_PATH . '/uploads/vod/hls/' . $id;
        if (!is_dir($dir)) return;
        foreach ((array)glob($dir . '/*') as $f) @unlink($f);
        @rmdir($dir);
    }

    // ══════════════════════════════════════════════════════
    // وضعیت پردازش
    // ══════════════════════════════════════════════════════

    /**
     * GET /api/v1/vod/videos/{id}/status
     * فهرست هر چند ثانیه این را می‌پرسد؛ عمدا سبک است و تمام ردیف را
     * برنمی‌گرداند.
     */
    public function processStatus(Request $req, array $p): void
    {
        $v = $this->db->row(
            'SELECT status, conv_progress, conv_action, conv_note, hls_path, file_path,
                    duration_fmt, width, height, codec, audio_codec, thumbnail
               FROM vod_videos WHERE id = ? AND tenant_id = ?',
            [(int)$p['id'], $this->tid]
        );
        if (!$v) Response::notFound('ویدیو پیدا نشد');
        Response::success($v);
    }

    /** POST /api/v1/vod/videos/{id}/reprocess */
    public function reprocess(Request $req, array $p): void
    {
        $id = (int)$p['id'];
        if (!$this->db->value('SELECT id FROM vod_videos WHERE id=? AND tenant_id=?', [$id, $this->tid])) {
            Response::notFound('ویدیو پیدا نشد');
        }
        $r = (new \App\Services\VodPipelineService($this->db))->reprocess($id);
        if (!$r['ok']) Response::error($r['message'], 422);
        $this->log('vod.reprocess', 'VodVideo', $id);
        Response::success(null, $r['message']);
    }

    /** POST /api/v1/vod/videos/{id}/cancel */
    public function cancelProcess(Request $req, array $p): void
    {
        $id = (int)$p['id'];
        if (!$this->db->value('SELECT id FROM vod_videos WHERE id=? AND tenant_id=?', [$id, $this->tid])) {
            Response::notFound('ویدیو پیدا نشد');
        }
        $r = (new \App\Services\VodPipelineService($this->db))->cancel($id);
        if (!$r['ok']) Response::error($r['message'], 422);
        $this->log('vod.cancel', 'VodVideo', $id);
        Response::success(null, $r['message']);
    }

    /** DELETE /api/v1/vod/videos/bulk — حذف گروهی */
    public function bulkDelete(Request $req): void
    {
        $ids = array_map('intval', (array)$req->post('ids', []));
        if (empty($ids)) Response::error('آیدی انتخاب نشده', 400);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $videos = $this->db->rows(
            "SELECT * FROM vod_videos WHERE id IN ($placeholders) AND tenant_id=?",
            array_merge($ids, [$this->tid])
        );

        foreach ($videos as $v) {
            if ($v['file_path'] && file_exists(PUBLIC_PATH . $v['file_path'])) @unlink(PUBLIC_PATH . $v['file_path']);
            if ($v['thumbnail'] && $v['thumbnail_auto'] && file_exists(PUBLIC_PATH . $v['thumbnail'])) @unlink(PUBLIC_PATH . $v['thumbnail']);
        }

        $this->db->query(
            "DELETE FROM vod_videos WHERE id IN ($placeholders) AND tenant_id=?",
            array_merge($ids, [$this->tid])
        );
        Response::success(null, count($videos) . ' ویدیو حذف شد');
    }

    /** POST /api/v1/vod/videos/{id}/thumbnail — آپلود تامبنیل دستی */
    public function uploadThumbnail(Request $req, array $p): void
    {
        $file = $_FILES['thumbnail'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) Response::error('فایل نامعتبر', 400);

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true)) {
            Response::error('فقط تصویر مجاز است', 422);
        }

        $dir  = PUBLIC_PATH . '/uploads/vod/thumbs/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
        $name = 'thumb_' . $p['id'] . '_' . time() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $dir . $name);

        $thumbUrl = '/uploads/vod/thumbs/' . $name;
        $this->db->update('vod_videos', ['thumbnail' => $thumbUrl, 'thumbnail_auto' => 0], ['id' => (int)$p['id'], 'tenant_id' => $this->tid]);
        Response::success(['thumbnail' => $thumbUrl]);
    }

    /** GET /api/v1/vod/stats */
    public function stats(Request $req): void
    {
        $s = $this->db->row(
            "SELECT
               COUNT(*)                              AS total,
               SUM(type='upload')                    AS uploads,
               SUM(type IN ('url','youtube','vimeo')) AS urls,
               SUM(is_featured=1)                    AS featured,
               SUM(file_size)                        AS total_size,
               SUM(views)                            AS total_views
             FROM vod_videos WHERE tenant_id=? AND is_active=1",
            [$this->tid]
        );
        $cats = (int)$this->db->query("SELECT COUNT(*) FROM vod_categories WHERE tenant_id=? AND is_active=1", [$this->tid])->fetchColumn();
        $s['categories'] = $cats;
        $s['total_size_fmt'] = $this->formatSize((int)($s['total_size'] ?? 0));
        Response::success($s);
    }

    // ══════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════


    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024) . ' KB';
        return $bytes . ' B';
    }

    private function detectUrlType(string $url): string
    {
        if (preg_match('/youtube\.com|youtu\.be/', $url)) return 'youtube';
        if (str_contains($url, 'vimeo.com'))              return 'vimeo';
        return 'url';
    }

    private function makeSlug(string $name, string $table): string
    {
        $slug = preg_replace('/\s+/', '-', mb_strtolower(trim($name)));
        $slug = preg_replace('/[^a-z0-9\-\x{0600}-\x{06FF}]/u', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug) ?: 'item';
        $base = substr($slug, 0, 80);
        $s = $base; $i = 1;
        while ($this->db->query("SELECT COUNT(*) FROM $table WHERE slug=? AND tenant_id=?", [$s, $this->tid])->fetchColumn()) {
            $s = $base . '-' . $i++;
        }
        return $s;
    }
}
