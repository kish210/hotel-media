<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\{Database, Auth};

class Media
{
    private Database $db;
    private int $tenantId;

    // ─── MIME → extension map ──────────────────────────────
    private const EXT_MAP = [
        'image/jpeg'      => 'jpg',
        'image/jpg'       => 'jpg',
        'image/png'       => 'png',
        'image/gif'       => 'gif',
        'image/webp'      => 'webp',
        'video/mp4'       => 'mp4',
        'video/webm'      => 'webm',
        'video/ogg'       => 'ogv',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
        'video/x-ms-wmv'  => 'wmv',
        'video/mpeg'      => 'mpeg',
    ];

    // ─── MIME types مجاز ─────────────────────────────────
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
        'video/x-msvideo', 'video/x-ms-wmv', 'video/mpeg',
    ];

    public function __construct()
    {
        $this->db       = Database::getInstance();
        $this->tenantId = Auth::tenantId();
    }

    // ── Upload ───────────────────────────────────────────────
    public function upload(array $file, array $extra = []): array
    {
        // بررسی خطای آپلود PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE   => 'فایل از حد upload_max_filesize بزرگتر است',
                UPLOAD_ERR_FORM_SIZE  => 'فایل از حد MAX_FILE_SIZE بزرگتر است',
                UPLOAD_ERR_PARTIAL    => 'آپلود ناقص انجام شد',
                UPLOAD_ERR_NO_FILE    => 'فایلی ارسال نشد',
                UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت وجود ندارد',
                UPLOAD_ERR_CANT_WRITE => 'دسترسی نوشتن وجود ندارد',
            ];
            throw new \RuntimeException($errors[$file['error']] ?? "خطای آپلود: {$file['error']}");
        }

        // تشخیص MIME از محتوای فایل (قابل اعتماد‌تر از browser MIME)
        $mime = mime_content_type($file['tmp_name']) ?: $file['type'];

        // اگه browser video/quicktime ارسال کرده ولی mime_content_type چیز دیگه‌ای گفت
        if (!in_array($mime, self::ALLOWED_MIMES)) {
            // یه بار با MIME مرورگر امتحان کن
            if (in_array($file['type'], self::ALLOWED_MIMES)) {
                $mime = $file['type'];
            } else {
                throw new \InvalidArgumentException(
                    "فرمت «{$mime}» پشتیبانی نمی‌شود. فرمت‌های مجاز: JPG, PNG, GIF, WEBP, MP4, WEBM"
                );
            }
        }

        // حجم
        $maxSize = (int)env('MAX_UPLOAD_SIZE', 524288000); // 500MB
        if ($file['size'] > $maxSize) {
            throw new \InvalidArgumentException('حجم فایل بیش از حد مجاز (' . round($maxSize/1048576) . ' MB) است');
        }

        // نوع
        $type = str_starts_with($mime, 'image/') ? 'image' : 'video';

        // extension
        $ext      = self::EXT_MAP[$mime] ?? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin');
        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        // مسیر: /public/uploads/media/{tenant_id}/filename.ext
        $uploadDir = PUBLIC_PATH . '/uploads/media/' . $this->tenantId;
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new \RuntimeException('پوشه آپلود قابل ساخت نیست: ' . $uploadDir);
            }
        }

        $absPath = $uploadDir . '/' . $filename;
        $relPath = '/uploads/media/' . $this->tenantId . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new \RuntimeException('ذخیره فایل ناموفق — مسیر: ' . $absPath);
        }

        // thumbnail برای تصاویر
        $thumbPath = null;
        if ($type === 'image') {
            $thumbDir = PUBLIC_PATH . '/uploads/thumbnails/' . $this->tenantId;
            if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);
            $thumbFile = $thumbDir . '/th_' . $filename;
            if ($this->makeThumbnail($absPath, $thumbFile, $mime)) {
                $thumbPath = '/uploads/thumbnails/' . $this->tenantId . '/th_' . $filename;
            }
        }

        $id = $this->db->insert('media', [
            'tenant_id'      => $this->tenantId,
            'uploaded_by'    => Auth::id() ?? 1,
            'name'           => $extra['name'] ?: pathinfo($file['name'], PATHINFO_FILENAME),
            'original_name'  => $file['name'],
            'type'           => $type,
            'mime_type'      => $mime,
            'file_path'      => $relPath,
            'thumbnail_path' => $thumbPath ?? ($type === 'image' ? $relPath : null),
            'file_size'      => filesize($absPath),
        ]);

        return $this->find((int)$id) ?? [];
    }

    // ── Thumbnail ────────────────────────────────────────────
    private function makeThumbnail(string $src, string $dest, string $mime): bool
    {
        if (!function_exists('imagecreatefromjpeg')) return false;

        $img = match($mime) {
            'image/jpeg','image/jpg' => @imagecreatefromjpeg($src),
            'image/png'              => @imagecreatefrompng($src),
            'image/gif'              => @imagecreatefromgif($src),
            'image/webp'             => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            default                  => false,
        };
        if (!$img) return false;

        [$tw, $th] = [400, 225];
        $ow = imagesx($img);
        $oh = imagesy($img);
        $ratio = min($tw / $ow, $th / $oh);
        $nw = (int)round($ow * $ratio);
        $nh = (int)round($oh * $ratio);

        $thumb = imagecreatetruecolor($tw, $th);
        $bg    = imagecolorallocate($thumb, 22, 22, 31);
        imagefill($thumb, 0, 0, $bg);
        imagecopyresampled($thumb, $img, (int)(($tw-$nw)/2), (int)(($th-$nh)/2), 0, 0, $nw, $nh, $ow, $oh);

        $ok = imagejpeg($thumb, $dest, 85);
        imagedestroy($img);
        imagedestroy($thumb);
        return $ok;
    }

    // ── CRUD ─────────────────────────────────────────────────
    public function all(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $sql    = "SELECT * FROM media WHERE tenant_id=? AND deleted_at IS NULL";
        $params = [$this->tenantId];

        if (!empty($filters['type']))   { $sql .= " AND type=?";      $params[] = $filters['type']; }
        if (!empty($filters['search'])) { $sql .= " AND name LIKE ?"; $params[] = "%{$filters['search']}%"; }

        $sql .= " ORDER BY created_at DESC";
        return $this->db->paginate($sql, $params, $page, $perPage);
    }

    public function find(int $id): ?array
    {
        return $this->db->row(
            "SELECT * FROM media WHERE id=? AND tenant_id=? AND deleted_at IS NULL",
            [$id, $this->tenantId]
        );
    }

    public function createUrl(string $url, string $name, array $extra = []): int|string
    {
        return $this->db->insert('media', array_merge([
            'tenant_id'    => $this->tenantId,
            'uploaded_by'  => Auth::id() ?? 1,
            'name'         => $name,
            'original_name'=> $url,
            'type'         => 'url',
            'url'          => $url,
            'file_path'    => $url,
            'mime_type'    => 'text/uri-list',
            'file_size'    => 0,
        ], $extra));
    }

    public function delete(int $id): bool
    {
        $m = $this->find($id);
        if (!$m) return false;

        // حذف فایل‌های فیزیکی
        foreach (['file_path', 'thumbnail_path'] as $key) {
            $path = $m[$key] ?? '';
            if ($path && str_starts_with($path, '/uploads/') && !str_starts_with($path, 'http')) {
                $full = PUBLIC_PATH . $path;
                if (file_exists($full)) @unlink($full);
            }
        }

        return $this->db->update(
            'media',
            ['deleted_at' => date('Y-m-d H:i:s')],
            ['id' => $id, 'tenant_id' => $this->tenantId]
        ) > 0;
    }

    public function getStorageUsage(): array
    {
        $row = $this->db->row(
            "SELECT COUNT(*) AS total_files, COALESCE(SUM(file_size),0) AS used_bytes
             FROM media WHERE tenant_id=? AND deleted_at IS NULL",
            [$this->tenantId]
        );
        return [
            'total_files' => (int)($row['total_files'] ?? 0),
            'used_bytes'  => (int)($row['used_bytes'] ?? 0),
            'used_mb'     => round(($row['used_bytes'] ?? 0) / 1048576, 2),
        ];
    }

    public function getForPlayer(int $id): ?array
    {
        return $this->db->row("SELECT * FROM media WHERE id=? AND deleted_at IS NULL", [$id]);
    }
}
