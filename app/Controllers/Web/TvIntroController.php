<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};

/**
 * تیزر شروع: عکس/ویدیوهایی که تلویزیون هنگام روشن‌شدن یک‌بار پخش می‌کند.
 * هدف‌گیری مثل پیام‌ها (همه / صفحهٔ مشخص / گروه) و دامنه (تابلو، اتاق، هر دو).
 */
class TvIntroController extends Controller
{
    private const SCOPES  = ['both', 'signage', 'iptv'];
    private const TARGETS = ['all', 'screen', 'group'];

    private function back(string $kind, string $msg): void
    {
        $this->flash($kind, $msg);
        $this->redirect('/admin/intro');
    }

    /** GET /admin/intro */
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();
        $items = $screens = $groups = $media = [];
        try {
            $items = $this->db->rows(
                "SELECT i.*, m.name AS media_name, m.type AS media_type, m.file_path, m.status AS media_status
                   FROM tv_intro_items i
              LEFT JOIN media m ON m.id = i.media_id
                  WHERE i.tenant_id = ?
                  ORDER BY i.sort_order, i.id",
                [$tid]
            ) ?: [];
        } catch (\Throwable) {
            $this->flash('error', 'جدول تیزر ساخته نشده — یک بار «به‌روزرسانی سیستم» را اجرا کنید.');
        }
        try {
            $screens = $this->db->rows("SELECT id, name, code FROM screens WHERE tenant_id=? ORDER BY name", [$tid]) ?: [];
            $groups  = $this->db->rows("SELECT id, name, type FROM screen_groups WHERE tenant_id=? ORDER BY name", [$tid]) ?: [];
            $media   = $this->db->rows(
                "SELECT id, name, type FROM media
                  WHERE tenant_id = ? AND deleted_at IS NULL AND type IN ('image','video')
                    AND (status IS NULL OR status = 'ready')
                  ORDER BY created_at DESC LIMIT 200",
                [$tid]
            ) ?: [];
        } catch (\Throwable) {}

        $this->view('admin.intro.index', [
            'title'   => 'تیزر شروع',
            'items'   => $items,
            'screens' => $screens,
            'groups'  => $groups,
            'media'   => $media,
        ]);
    }

    /** POST /admin/intro */
    public function store(Request $req): void
    {
        $name = trim((string)$req->post('name', ''));
        $url  = trim((string)$req->post('url', ''));
        $mid  = $req->post('media_id') ? (int)$req->post('media_id') : null;
        if ($name === '')             { $this->back('error', 'نام تیزر را بنویسید'); return; }
        if (!$mid && $url === '')     { $this->back('error', 'یک رسانه انتخاب کنید یا آدرس بدهید'); return; }
        if ($url !== '' && !preg_match('#^(https?:)?/#i', $url)) {
            $this->back('error', 'آدرس باید با http یا / شروع شود'); return;
        }

        $target = (string)$req->post('target', 'all');
        if (!in_array($target, self::TARGETS, true)) $target = 'all';
        $ids = array_values(array_filter(array_map('intval', (array)$req->post('target_ids', []))));
        if ($target !== 'all' && !$ids) { $this->back('error', 'برای این هدف، دست‌کم یک مورد انتخاب کنید'); return; }

        $scope = (string)$req->post('scope', 'both');
        if (!in_array($scope, self::SCOPES, true)) $scope = 'both';

        try {
            $this->db->insert('tv_intro_items', [
                'tenant_id'  => Auth::tenantId(),
                'name'       => mb_substr($name, 0, 200),
                'media_id'   => $mid,
                'url'        => $url !== '' ? mb_substr($url, 0, 1000) : null,
                'duration'   => max(1, min(600, (int)$req->post('duration', 8))),
                'target'     => $target,
                'target_ids' => $target === 'all' ? null : json_encode($ids),
                'scope'      => $scope,
                'sort_order' => (int)$req->post('sort_order', 0),
                'is_active'  => 1,
            ]);
        } catch (\Throwable $e) {
            $this->back('error', 'ذخیره نشد: ' . $e->getMessage()); return;
        }
        $this->back('success', 'تیزر اضافه شد');
    }

    /** POST /admin/intro/{id}/toggle */
    public function toggle(Request $req, array $p): void
    {
        $row = $this->db->row('SELECT id, is_active FROM tv_intro_items WHERE id=? AND tenant_id=?',
                              [(int)$p['id'], Auth::tenantId()]);
        if (!$row) { $this->back('error', 'تیزر پیدا نشد'); return; }
        $this->db->update('tv_intro_items', ['is_active' => $row['is_active'] ? 0 : 1], ['id' => (int)$row['id']]);
        $this->back('success', $row['is_active'] ? 'تیزر خاموش شد' : 'تیزر روشن شد');
    }

    /** POST /admin/intro/{id}/delete */
    public function delete(Request $req, array $p): void
    {
        $this->db->delete('tv_intro_items', ['id' => (int)$p['id'], 'tenant_id' => Auth::tenantId()]);
        $this->back('success', 'تیزر حذف شد');
    }
}
