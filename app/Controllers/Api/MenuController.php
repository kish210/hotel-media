<?php declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Response, Request, Auth};

class MenuController extends Controller
{
    public function categories(Request $req): void
    {
        $tid = Auth::check() ? Auth::tenantId() : 1;
        $cats = $this->db->rows("SELECT * FROM menu_categories WHERE tenant_id=? AND is_active=1 ORDER BY sort_order", [$tid]);
        Response::success($cats);
    }

    public function items(Request $req): void
    {
        $tid = Auth::check() ? Auth::tenantId() : 1;
        $sql = "SELECT * FROM menu_items WHERE tenant_id=? AND is_available=1";
        $params = [$tid];
        if ($req->get('category_id')) { $sql .= " AND category_id=?"; $params[] = $req->get('category_id'); }
        $sql .= " ORDER BY sort_order, id";
        Response::success($this->db->rows($sql, $params));
    }
}
