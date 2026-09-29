<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth, Branch};

/** انتخاب شعبه برای دیدن — فقط برای کاربر همه‌ی شعبه‌ها (TODO ۵.۱۴) */
class BranchController extends Controller
{
    /** POST /admin/branch  body: location_id (۰ = همه) */
    public function switch(Request $req): void
    {
        if (Branch::locked() === null) {
            $l = (int)$req->post('location_id', 0);
            if ($l > 0 && $this->db->exists('locations', ['id' => $l, 'tenant_id' => Auth::tenantId()])) {
                $_SESSION['branch_view'] = $l;
            } else {
                unset($_SESSION['branch_view']);
            }
        }

        /* فقط مسیر داخلی پنل؛ Referer دستکاری‌شده به بیرون نمی‌برد */
        $path = (string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
        $this->redirect(str_starts_with($path, '/admin') ? $path : '/admin/dashboard');
    }
}
