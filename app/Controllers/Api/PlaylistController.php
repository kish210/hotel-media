<?php declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Response, Request};
use App\Models\Playlist;

class PlaylistController extends Controller
{
    private Playlist $playlist;
    public function __construct() { parent::__construct(); $this->playlist = new Playlist(); }

    public function index(Request $req): void
    {
        Response::paginated($this->playlist->all($req->get(), (int)$req->get('page',1)));
    }

    public function show(Request $req, array $params): void
    {
        $p = $this->playlist->find((int)$params['id']);
        if (!$p) Response::notFound();
        Response::success($p);
    }

    public function store(Request $req): void
    {
        $errors = $req->validate(['name'=>'required|max:255']);
        if ($errors) Response::error('داده‌ها نامعتبر',422,$errors);
        $id = $this->playlist->create($req->input());
        Response::success($this->playlist->find((int)$id),'پلی‌لیست ایجاد شد',201);
    }

    public function update(Request $req, array $params): void
    {
        $this->playlist->update((int)$params['id'], $req->input());
        Response::success($this->playlist->find((int)$params['id']),'به‌روز شد');
    }

    public function destroy(Request $req, array $params): void
    {
        $this->playlist->delete((int)$params['id']);
        Response::success(null,'حذف شد');
    }
}
