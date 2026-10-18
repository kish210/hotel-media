<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};

/**
 * مدیریت «گروه IPTV» (جدول screen_groups با type=iptv): گروهی که به صفحه‌ها،
 * اتاق‌ها و منوهای IPTV داده می‌شود. جدا از «گروه کانال» (iptv_channel_groups).
 *
 * تا اینجا فقط یک مسیر ساخت داخل تب صفحه‌ها بود و در فرم‌های ساخت صفحه/اتاق/منو
 * هیچ راهی برای ساخت یا ویرایش گروه نبود؛ لیست «گروه IPTV» خالی می‌ماند.
 */
class IptvScreenGroupController extends Controller
{
    private function back(string $kind, string $msg): void
    {
        $this->flash($kind, $msg);
        $this->redirect('/admin/iptv/screen-groups');
    }

    /** GET /admin/iptv/screen-groups */
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();
        $groups = $this->db->rows(
            "SELECT g.*,
                    (SELECT COUNT(*) FROM screens s WHERE s.group_id = g.id) AS screens_count,
                    (SELECT COUNT(*) FROM iptv_menus m WHERE m.group_id = g.id) AS menus_count
               FROM screen_groups g
              WHERE g.tenant_id = ? AND g.type = 'iptv'
              ORDER BY g.sort_order, g.name",
            [$tid]
        ) ?: [];
        $this->view('admin.iptv.screen_groups', ['title' => 'گروه‌های IPTV', 'groups' => $groups]);
    }

    /** POST /admin/iptv/screen-groups */
    public function store(Request $req): void
    {
        $name = trim((string)$req->post('name', ''));
        if ($name === '') { $this->back('error', 'نام گروه را بنویسید'); return; }
        $tid = Auth::tenantId();
        if ($this->db->row("SELECT id FROM screen_groups WHERE tenant_id=? AND type='iptv' AND name=?", [$tid, $name])) {
            $this->back('error', 'گروهی با این نام هست'); return;
        }
        $this->db->insert('screen_groups', [
            'tenant_id'   => $tid,
            'name'        => mb_substr($name, 0, 100),
            'description' => mb_substr(trim((string)$req->post('description', '')), 0, 255) ?: null,
            'type'        => 'iptv',
            'color'       => $this->color((string)$req->post('color', '#ef4444')),
            'is_active'   => 1,
            'sort_order'  => 0,
        ]);
        $this->back('success', 'گروه ساخته شد');
    }

    /** POST /admin/iptv/screen-groups/{id} */
    public function update(Request $req, array $p): void
    {
        $tid  = Auth::tenantId();
        $name = trim((string)$req->post('name', ''));
        if ($name === '') { $this->back('error', 'نام گروه را بنویسید'); return; }
        $this->db->update('screen_groups', [
            'name'        => mb_substr($name, 0, 100),
            'description' => mb_substr(trim((string)$req->post('description', '')), 0, 255) ?: null,
            'color'       => $this->color((string)$req->post('color', '#ef4444')),
        ], ['id' => (int)$p['id'], 'tenant_id' => $tid, 'type' => 'iptv']);
        $this->back('success', 'گروه ذخیره شد');
    }

    /** POST /admin/iptv/screen-groups/{id}/delete — صفحه‌ها و اتاق‌ها بی‌گروه می‌شوند، حذف نمی‌شوند */
    public function delete(Request $req, array $p): void
    {
        $tid = Auth::tenantId();
        $id  = (int)$p['id'];
        if (!$this->db->row("SELECT id FROM screen_groups WHERE id=? AND tenant_id=? AND type='iptv'", [$id, $tid])) {
            $this->back('error', 'گروه پیدا نشد'); return;
        }
        // کلید خارجی ممکن است نباشد؛ خودمان از گروه جدا می‌کنیم تا ردیف یتیم نماند
        foreach (['screens', 'iptv_menus', 'iptv_rooms'] as $table) {
            try { $this->db->query("UPDATE `$table` SET group_id = NULL WHERE group_id = ?", [$id]); } catch (\Throwable) {}
        }
        $this->db->delete('screen_groups', ['id' => $id, 'tenant_id' => $tid]);
        $this->back('success', 'گروه حذف شد');
    }

    private function color(string $c): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : '#ef4444';
    }
}
