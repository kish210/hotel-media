<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};

class MediaController extends Controller
{
    public function index(Request $req): void
    {
        $tid   = Auth::tenantId();
        $type  = $req->get('type', '');
        $search= $req->get('q', '');

        $sql    = "SELECT * FROM media WHERE tenant_id=? AND deleted_at IS NULL";
        $params = [$tid];

        if ($type)   { $sql .= " AND type=?";      $params[] = $type; }
        if ($search) { $sql .= " AND name LIKE ?";  $params[] = "%$search%"; }
        $sql .= " ORDER BY created_at DESC LIMIT 200";

        $media = $this->db->rows($sql, $params);
        $stats = $this->db->row(
            "SELECT COUNT(*) AS total, COALESCE(SUM(file_size),0) AS used
             FROM media WHERE tenant_id=? AND deleted_at IS NULL",
            [$tid]
        );

        $this->view('media.index', [
            'title'        => 'کتابخانه رسانه',
            'media'        => $media,
            'total'        => (int)($stats['total'] ?? 0),
            'storage_used' => (int)($stats['used'] ?? 0),
        ]);
    }

    public function upload(Request $req): void
    {
        $tid  = Auth::tenantId();
        $file = $req->file('file');

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            \App\Core\Response::error('فایلی ارسال نشده یا خطا در آپلود: ' . ($file['error'] ?? '?'), 400);
            return;
        }

        try {
            $allowed = ['image/jpeg','image/png','image/gif','image/webp','video/mp4','video/webm','video/ogg'];
            $mime    = mime_content_type($file['tmp_name']);

            if (!in_array($mime, $allowed)) {
                \App\Core\Response::error("فرمت «$mime» مجاز نیست", 415);
                return;
            }

            $type = str_starts_with($mime, 'image/') ? 'image' : 'video';
            $ext  = match($mime) {
                'image/jpeg'  => 'jpg',
                'image/png'   => 'png',
                'image/gif'   => 'gif',
                'image/webp'  => 'webp',
                'video/mp4'   => 'mp4',
                'video/webm'  => 'webm',
                default       => pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin',
            };

            // مسیر: /public/uploads/media/{tenant_id}/
            $subDir  = "uploads/media/{$tid}";
            $fullDir = PUBLIC_PATH . '/' . $subDir;
            if (!is_dir($fullDir)) {
                mkdir($fullDir, 0755, true);
            }

            $filename = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = $fullDir . '/' . $filename;
            $urlPath  = '/' . $subDir . '/' . $filename; // URL path

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                \App\Core\Response::error('خطا در ذخیره فایل — دسترسی به پوشه را بررسی کنید', 500);
                return;
            }

            // ساخت thumbnail برای تصاویر
            $thumbPath = null;
            if ($type === 'image') {
                $thumbDir  = PUBLIC_PATH . "/uploads/thumbnails/{$tid}";
                if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

                $thumbFile = $thumbDir . '/th_' . $filename;
                if ($this->createThumbnail($destPath, $thumbFile, $mime)) {
                    $thumbPath = "/uploads/thumbnails/{$tid}/th_{$filename}";
                }
            }

            $mediaId = $this->db->insert('media', [
                'tenant_id'      => $tid,
                'uploaded_by'    => Auth::id() ?? 1,
                'name'           => pathinfo($file['name'], PATHINFO_FILENAME),
                'original_name'  => $file['name'],
                'file_path'      => $urlPath,
                'thumbnail_path' => $thumbPath ?? $urlPath,
                'mime_type'      => $mime,
                'file_size'      => filesize($destPath),
                'type'           => $type,
            ]);

            \App\Core\Response::success([
                'id'             => $mediaId,
                'name'           => pathinfo($file['name'], PATHINFO_FILENAME),
                'file_path'      => $urlPath,
                'thumbnail_path' => $thumbPath ?? $urlPath,
                'type'           => $type,
                'file_size'      => filesize($destPath),
                'url'            => env('APP_URL') . $urlPath,
            ], 'فایل آپلود شد', 201);

        } catch (\Throwable $e) {
            error_log('[UPLOAD ERROR] ' . $e->getMessage());
            \App\Core\Response::error('خطا: ' . $e->getMessage(), 500);
        }
    }

    public function addUrl(Request $req): void
    {
        $url  = trim($req->post('url', ''));
        $name = trim($req->post('name', '')) ?: parse_url($url, PHP_URL_HOST) ?: $url;
        $dur  = (int)$req->post('duration', 30);

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->flash('error', 'آدرس URL معتبر نیست');
            $this->redirect('/admin/media');
            return;
        }

        $this->db->insert('media', [
            'tenant_id'      => Auth::tenantId(),
            'uploaded_by'    => Auth::id() ?? 1,
            'name'           => $name,
            'original_name'  => $url,
            'file_path'      => $url,
            'url'            => $url,
            'thumbnail_path' => null,
            'mime_type'      => 'text/uri-list',
            'file_size'      => 0,
            'type'           => 'url',
            'duration'       => $dur,
        ]);

        $this->flash('success', 'لینک اضافه شد');
        $this->redirect('/admin/media');
    }

    public function destroy(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $m   = $this->db->row(
            "SELECT * FROM media WHERE id=? AND tenant_id=?",
            [(int)$params['id'], $tid]
        );

        if ($m) {
            // حذف فایل فیزیکی
            foreach ([$m['file_path'], $m['thumbnail_path']] as $path) {
                if ($path && !str_starts_with($path, 'http') && str_starts_with($path, '/uploads/')) {
                    $full = PUBLIC_PATH . $path;
                    if (file_exists($full)) @unlink($full);
                }
            }
            $this->db->update('media',
                ['deleted_at' => date('Y-m-d H:i:s')],
                ['id' => (int)$params['id'], 'tenant_id' => $tid]
            );
        }

        if ($req->isAjax() || $req->isJson()) {
            \App\Core\Response::success(null, 'رسانه حذف شد');
        } else {
            $this->flash('success', 'رسانه حذف شد');
            $this->redirect('/admin/media');
        }
    }

    private function createThumbnail(string $src, string $dest, string $mime): bool
    {
        if (!function_exists('imagecreatefromjpeg')) return false;

        $img = match($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($src),
            'image/png'  => @imagecreatefrompng($src),
            'image/gif'  => @imagecreatefromgif($src),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            default      => false,
        };
        if (!$img) return false;

        $tw = 400; $th = 225;
        $ow = imagesx($img); $oh = imagesy($img);
        $ratio = min($tw/$ow, $th/$oh);
        $nw = (int)round($ow * $ratio);
        $nh = (int)round($oh * $ratio);

        $thumb = imagecreatetruecolor($tw, $th);
        $bg    = imagecolorallocate($thumb, 22, 22, 31);
        imagefill($thumb, 0, 0, $bg);
        imagecopyresampled(
            $thumb, $img,
            (int)(($tw - $nw) / 2), (int)(($th - $nh) / 2),
            0, 0, $nw, $nh, $ow, $oh
        );

        $result = imagejpeg($thumb, $dest, 85);
        imagedestroy($img);
        imagedestroy($thumb);
        return $result;
    }
}
