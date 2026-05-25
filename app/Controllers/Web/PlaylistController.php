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
        $result = $this->playlist->all($req->get(), (int)$req->get('page', 1));
        $this->view('playlists.index', ['title' => 'پلی‌لیست‌ها', 'playlists' => $result]);
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
        $this->db->update('playlist_items', [
            'duration'   => (int)$req->post('duration', 10),
            'start_at'   => $req->post('start_at') ?: null,
            'end_at'     => $req->post('end_at') ?: null,
            'volume'     => (int)$req->post('volume', 100),
        ], ['id' => (int)$params['iid'], 'playlist_id' => (int)$params['id']]);
        $this->flash('success', 'آیتم ویرایش شد');
        $this->redirect('/admin/playlists/' . $params['id']);
    }

}