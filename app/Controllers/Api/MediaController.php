<?php declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Response, Request};
use App\Models\Media;

class MediaController extends Controller
{
    private Media $media;
    public function __construct() { parent::__construct(); $this->media = new Media(); }

    public function index(Request $req): void
    {
        Response::paginated($this->media->all($req->get(), (int)$req->get('page', 1)));
    }

    public function show(Request $req, array $params): void
    {
        $m = $this->media->find((int)$params['id']);
        if (!$m) Response::notFound(); Response::success($m);
    }

    public function upload(Request $req): void
    {
        $file = $req->file('file');
        if (!$file) Response::error('فایلی ارسال نشده', 400);
        try {
            $media = $this->media->upload($file, ['name' => $req->post('name', ''), 'folder' => $req->post('folder', '')]);
            $this->log('media.upload', 'Media', $media['id']);
            Response::success($media, 'آپلود موفق', 201);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 422);
        }
    }

    public function addUrl(Request $req): void
    {
        $errors = $req->validate(['url' => 'required', 'name' => 'required']);
        if ($errors) Response::error('داده‌ها نامعتبر', 422, $errors);
        $id = $this->media->createUrl($req->post('url'), $req->post('name'));
        Response::success($this->media->find((int)$id), 'URL اضافه شد', 201);
    }

    public function destroy(Request $req, array $params): void
    {
        $this->media->delete((int)$params['id']);
        $this->log('media.delete', 'Media', (int)$params['id']);
        Response::success(null, 'رسانه حذف شد');
    }

    public function storageInfo(Request $req): void
    {
        Response::success($this->media->getStorageUsage());
    }
}
