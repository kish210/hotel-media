<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\OpenHandAgent;

/**
 * OpenHand — عامل اجرایی.
 *
 * فقط مدیر ارشد. هر تغییر از سرویس با پشتیبان و بازگشت خودکار انجام
 * می‌شود؛ این کنترلر خودش هیچ نوشتنی در دیتابیس ندارد جز لاگ فعالیت.
 * کلید سرویس مدل هیچ‌جا در پاسخ این مسیرها نمی‌آید.
 */
class OpenHandController extends Controller
{
    private function guard(bool $json = true): bool
    {
        if ((Auth::user()['role'] ?? '') === 'super_admin') return true;
        if ($json) Response::error('این بخش فقط برای مدیر ارشد است', 403);
        else { $this->flash('error', 'این بخش فقط برای مدیر ارشد است'); $this->redirect('/admin/dashboard'); }
        return false;
    }

    /** GET /admin/system/agent */
    public function index(Request $req): void
    {
        if (!$this->guard(false)) return;

        $agent = new OpenHandAgent($this->db);

        $this->view('admin.system.openhand', [
            'title'    => 'OpenHand — عامل اجرایی',
            'enabled'  => $agent->enabled(),
            'changes'  => $agent->guard()->changes(15),
            'health'   => $agent->guard()->health(),
        ]);
    }

    /** POST /admin/system/agent/chat */
    public function chat(Request $req): void
    {
        if (!$this->guard()) return;

        $msg = trim((string)$req->post('message', ''));
        if ($msg === '') { Response::error('دستور خالی است', 422); return; }
        if (mb_strlen($msg) > 1500) { Response::error('دستور طولانی است؛ کوتاه‌تر بنویسید', 422); return; }

        $agent = new OpenHandAgent($this->db);
        $r = $agent->run((int)Auth::tenantId(), (int)Auth::id(), $msg);

        if (!$r['ok']) { Response::error($r['message'], 502); return; }

        $this->log('agent.openhand', 'system', null);
        Response::success($r);
    }

    /** POST /admin/system/agent/rollback */
    public function rollback(Request $req): void
    {
        if (!$this->guard()) return;

        $id = trim((string)$req->post('change_id', ''));
        if ($id === '') { Response::error('شناسهٔ تغییر لازم است', 422); return; }

        $r = (new OpenHandAgent($this->db))->guard()->restore($id);
        if (!$r['ok']) { Response::error($r['message'], 400); return; }

        $this->log('agent.rollback', 'system', null);
        Response::success(['change_id' => $id], $r['message']);
    }

    /** POST /admin/system/agent/reset */
    public function reset(Request $req): void
    {
        if (!$this->guard()) return;

        (new OpenHandAgent($this->db))->clearHistory();
        Response::success(null, 'گفتگو پاک شد');
    }

    /** GET /admin/system/agent/changes */
    public function changes(Request $req): void
    {
        if (!$this->guard()) return;

        Response::success((new OpenHandAgent($this->db))->guard()->changes(30));
    }
}
