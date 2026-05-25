<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth, Response};

class AppController extends Controller
{
    public function index(Request $req): void
    {
        $versions = $this->db->rows("SELECT * FROM apk_versions ORDER BY version_code DESC LIMIT 20");
        $this->view('admin.app.index', compact('versions') + ['title' => 'مدیریت اپ Android']);
    }

    public function upload(Request $req): void
    {
        if (empty($_FILES['apk_file']) || $_FILES['apk_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'فایل APK انتخاب نشده'); $this->redirect('/admin/app'); return;
        }

        $file = $_FILES['apk_file'];
        if (!str_ends_with(strtolower($file['name']), '.apk')) {
            $this->flash('error', 'فقط فایل APK مجاز است'); $this->redirect('/admin/app'); return;
        }

        $vCode   = (int)$req->post('version_code', 1);
        $vName   = trim($req->post('version_name', '1.0.0'));
        $changelog = trim($req->post('changelog', ''));
        $force   = $req->post('force_update') ? 1 : 0;
        $filename = 'signagecms-v' . $vName . '-' . $vCode . '.apk';
        $dest     = PUBLIC_PATH . '/apk/' . $filename;

        if (!is_dir(PUBLIC_PATH . '/apk')) mkdir(PUBLIC_PATH . '/apk', 0755, true);

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->flash('error', 'آپلود ناموفق بود'); $this->redirect('/admin/app'); return;
        }

        // غیرفعال کردن نسخه‌های قبلی
        $this->db->query("UPDATE apk_versions SET is_active=0");

        $this->db->insert('apk_versions', [
            'version_code' => $vCode,
            'version_name' => $vName,
            'apk_filename' => $filename,
            'file_size'    => filesize($dest),
            'changelog'    => $changelog,
            'force_update' => $force,
            'is_active'    => 1,
        ]);

        $this->flash('success', "نسخه $vName آپلود شد — دستگاه‌ها خودکار بروزرسانی می‌شوند");
        $this->redirect('/admin/app');
    }

    public function delete(Request $req, array $params): void
    {
        $v = $this->db->row("SELECT * FROM apk_versions WHERE id=?", [(int)$params['id']]);
        if ($v) {
            $path = PUBLIC_PATH . '/apk/' . $v['apk_filename'];
            if (file_exists($path)) unlink($path);
            $this->db->delete('apk_versions', ['id' => $v['id']]);
        }
        $this->flash('success', 'نسخه حذف شد');
        $this->redirect('/admin/app');
    }
}
