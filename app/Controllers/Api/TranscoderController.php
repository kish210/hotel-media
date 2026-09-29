<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\TranscoderService;

/**
 * API ترنسکدر برای پنل — کارها، اجرا، لاگ، بررسی ورودی، انتشار.
 *
 * فقط super_admin و admin: هر کار یک فرایند ffmpeg روی سرور هتل است و
 * آدرس ورودی به ffmpeg داده می‌شود. نسخه‌ی قبلی ترنسکدر هیچ بررسی
 * نقشی نداشت و هر کاربر واردشده‌ای، حتی viewer، می‌توانست اجرایش کند.
 */
class TranscoderController extends Controller
{
    private TranscoderService $svc;

    public function __construct()
    {
        parent::__construct();
        $this->svc = new TranscoderService($this->db);
    }

    private function allowed(): bool
    {
        if (in_array(Auth::role(), ['super_admin', 'admin'], true)) return true;
        Response::error('فقط مدیر سامانه به ترنسکدر دسترسی دارد', 403);
        return false;
    }

    /** GET /api/v1/transcoder/jobs — فهرست با وضعیت زنده (برای به‌روزرسانی خودکار پنل) */
    public function index(Request $req): void
    {
        if (!$this->allowed()) return;
        $out = [];
        foreach ($this->svc->all(Auth::tenantId()) as $j) {
            $out[] = $this->present($j);
        }
        Response::success($out);
    }

    public function store(Request $req): void
    {
        if (!$this->allowed()) return;
        $res = $this->svc->save(Auth::tenantId(), $req->json() ?: []);
        if (!$res['ok']) { Response::error($res['message'], 422); return; }
        $this->log('transcoder.create', 'transcoder_job', (int)$res['id']);
        Response::success(['id' => $res['id']], $res['message'], 201);
    }

    public function update(Request $req, array $params): void
    {
        if (!$this->allowed()) return;
        $id  = (int)($params['id'] ?? 0);
        $res = $this->svc->save(Auth::tenantId(), $req->json() ?: [], $id);
        if (!$res['ok']) { Response::error($res['message'], 422); return; }
        $this->log('transcoder.update', 'transcoder_job', $id);
        Response::success(['restart' => $res['restart'] ?? false], $res['message']);
    }

    public function destroy(Request $req, array $params): void
    {
        if (!$this->allowed()) return;
        $id  = (int)($params['id'] ?? 0);
        $res = $this->svc->delete(Auth::tenantId(), $id);
        if (!$res['ok']) { Response::error($res['message'], 404); return; }
        $this->log('transcoder.delete', 'transcoder_job', $id);
        Response::success(null, $res['message']);
    }

    public function start(Request $req, array $params): void
    {
        if (!$this->allowed()) return;
        $id  = (int)($params['id'] ?? 0);
        $res = $this->svc->start(Auth::tenantId(), $id);
        if (!$res['ok']) { Response::error($res['message'], 409); return; }
        $this->log('transcoder.start', 'transcoder_job', $id);
        Response::success(null, $res['message']);
    }

    public function stop(Request $req, array $params): void
    {
        if (!$this->allowed()) return;
        $id  = (int)($params['id'] ?? 0);
        $res = $this->svc->stop(Auth::tenantId(), $id);
        if (!$res['ok']) { Response::error($res['message'], 404); return; }
        $this->log('transcoder.stop', 'transcoder_job', $id);
        Response::success(null, $res['message']);
    }

    /** GET /api/v1/transcoder/jobs/{id}/log */
    public function jobLog(Request $req, array $params): void
    {
        if (!$this->allowed()) return;
        $job = $this->svc->find(Auth::tenantId(), (int)($params['id'] ?? 0));
        if (!$job) { Response::notFound('کار یافت نشد'); return; }
        Response::success(['log' => $this->svc->log($job, 120)]);
    }

    /** POST /api/v1/transcoder/probe  body: {input_kind, input_url, mode} */
    public function probe(Request $req): void
    {
        if (!$this->allowed()) return;
        $d   = $req->json() ?: [];
        $res = $this->svc->probe((string)($d['input_kind'] ?? 'url'), trim((string)($d['input_url'] ?? '')),
                                 (string)($d['mode'] ?? 'live'));
        if (!$res['ok']) { Response::error($res['message'], 422); return; }
        Response::success($res);
    }

    /** POST /api/v1/transcoder/jobs/{id}/publish  body: {channel_id} */
    public function publish(Request $req, array $params): void
    {
        if (!$this->allowed()) return;
        $id  = (int)($params['id'] ?? 0);
        $res = $this->svc->publish(Auth::tenantId(), $id, (int)(($req->json() ?: [])['channel_id'] ?? 0));
        if (!$res['ok']) { Response::error($res['message'], 422); return; }
        $this->log('transcoder.publish', 'transcoder_job', $id);
        Response::success(null, $res['message']);
    }

    /** POST /api/v1/transcoder/capabilities/refresh — بعد از نصب کارت گرافیک یا کپچر */
    public function refreshCaps(Request $req): void
    {
        if (!$this->allowed()) return;
        @unlink(STORAGE_PATH . '/cache/transcoder_caps.json');
        Response::success($this->svc->capabilities());
    }

    private function present(array $j): array
    {
        $alive = $this->svc->alive($j);
        $stats = $alive || $j['status'] === 'finished' ? $this->svc->stats($j) : null;

        /* ناظر هر ۵ ثانیه وضعیت را در دیتابیس می‌نویسد؛ پنل منتظرش
           نمی‌ماند. فرایند زنده با گزارش تازه یعنی در حال اجرا، و
           فرایندی که نیست یعنی پنل نباید «در حال اجرا» نشان دهد. */
        $status = $j['status'];
        if ($alive && $stats && $stats['age'] >= 0 && $stats['age'] <= TranscoderService::STALL_SECONDS) $status = 'running';
        elseif (!$alive && in_array($status, ['running', 'starting'], true)) $status = $j['desired'] === 'running' ? 'error' : 'stopped';

        return [
            'id'          => (int)$j['id'],
            'name'        => $j['name'],
            'slug'        => $j['slug'],
            'mode'        => $j['mode'],
            'input_kind'  => $j['input_kind'],
            'input_url'   => $j['input_url'],
            'settings'    => $j['settings'],
            'channel_id'  => $j['channel_id'] ? (int)$j['channel_id'] : null,
            'desired'     => $j['desired'],
            'status'      => $status,
            'alive'       => $alive,
            'restarts'    => (int)$j['restarts'],
            'started_at'  => $j['started_at'],
            'last_error'  => $j['last_error'],
            'progress'    => $j['progress'] !== null ? (int)$j['progress'] : null,
            'hls_url'     => $this->svc->hlsUrl($j),
            'stats'       => $stats,
        ];
    }
}
