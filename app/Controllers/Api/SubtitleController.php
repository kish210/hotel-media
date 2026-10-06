<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Response, Request, Auth};
use App\Services\SubtitleService;

/**
 * آپلود و حذف زیرنویس — هم برای رسانهٔ تابلو و هم برای فیلم VOD.
 *
 * چرا این کنترلر لازم شد: `SubtitleService` از ابتدا کامل بود
 * (خواندن SRT و ASS، تبدیل به VTT، زبان، SDH، پیش‌فرض) و
 * ‏`forPlayer()` هم در `VodController::showVideo` استفاده می‌شد — ولی
 * ‏`store()` و `delete()` را **هیچ‌کس صدا نمی‌زد** و هیچ مسیری برایشان
 * ثبت نشده بود. یعنی تحویل زیرنویس کار می‌کرد و راهی برای گذاشتن
 * زیرنویس وجود نداشت؛ فهرست TODO آن قابلیت را «انجام‌شده» ثبت کرده
 * بود.
 *
 * ‏`media_subtitles` هم در migration 039 ساخته شده بود و تنها
 * ارجاعش در تمام مخزن، همان CREATE TABLE بود.
 */
class SubtitleController extends Controller
{
    private function svc(): SubtitleService
    {
        return new SubtitleService($this->db);
    }

    /* ── رسانهٔ تابلو ───────────────────────────────────────────── */

    /** GET /api/v1/media/{id}/subtitles */
    public function listForMedia(Request $req, array $p): void
    {
        if (!$this->authorize('media.view')) return;
        Response::success($this->svc()->forMedia((int)$p['id']));
    }

    /** POST /api/v1/media/{id}/subtitles  (multipart: file, lang, label, is_default) */
    public function storeForMedia(Request $req, array $p): void
    {
        if (!$this->authorize('media.edit')) return;

        $id  = (int)$p['id'];
        $tid = Auth::tenantId();

        /* بررسی مالکیت پیش از هر کاری: بی این، یک tenant می‌توانست
           روی رسانهٔ tenant دیگر زیرنویس بگذارد. */
        $media = $this->db->row(
            'SELECT id, type FROM media WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$id, $tid]
        );
        if (!$media) Response::notFound('رسانه پیدا نشد');

        /* زیرنویس روی عکس معنا ندارد و اگر اجازه بدهیم، اپراتور بعدا
           دنبال این می‌گردد که چرا دیده نمی‌شود. */
        if (($media['type'] ?? '') !== 'video') {
            Response::error('زیرنویس فقط برای ویدیو معنا دارد', 422);
        }

        $r = $this->svc()->storeForMedia($tid, $id, $_FILES['file'] ?? [], [
            'lang'       => $req->post('lang', ''),
            'label'      => $req->post('label', ''),
            'is_default' => $req->post('is_default'),
        ]);

        if (!$r['ok']) Response::error($r['message'], 422);

        $this->log('media.subtitle.add', 'Media', $id);
        Response::success(['id' => $r['id']], $r['message'], 201);
    }

    /** DELETE /api/v1/media/subtitles/{id} */
    public function deleteForMedia(Request $req, array $p): void
    {
        if (!$this->authorize('media.edit')) return;

        $id  = (int)$p['id'];
        $own = $this->db->value(
            'SELECT s.id FROM media_subtitles s
               JOIN media m ON m.id = s.media_id
              WHERE s.id = ? AND m.tenant_id = ?',
            [$id, Auth::tenantId()]
        );
        if (!$own) Response::notFound('زیرنویس پیدا نشد');

        $this->svc()->deleteForMedia($id);
        $this->log('media.subtitle.delete', 'Media', $id);
        Response::success(null, 'زیرنویس حذف شد');
    }

    /* ── فیلم VOD ───────────────────────────────────────────────── */

    /** POST /api/v1/vod/videos/{id}/subtitles */
    public function storeForVod(Request $req, array $p): void
    {
        if (!$this->authorize('vod.edit')) return;

        $id  = (int)$p['id'];
        $tid = Auth::tenantId();

        if (!$this->db->value('SELECT id FROM vod_videos WHERE id = ? AND tenant_id = ?', [$id, $tid])) {
            Response::notFound('فیلم پیدا نشد');
        }

        $r = $this->svc()->store($tid, $id, $_FILES['file'] ?? [], [
            'lang'       => $req->post('lang', ''),
            'label'      => $req->post('label', ''),
            'is_sdh'     => $req->post('is_sdh'),
            'is_default' => $req->post('is_default'),
        ]);

        if (!$r['ok']) Response::error($r['message'], 422);

        $this->log('vod.subtitle.add', 'VodVideo', $id);
        Response::success(['id' => $r['id']], $r['message'], 201);
    }

    /** DELETE /api/v1/vod/subtitles/{id} */
    public function deleteForVod(Request $req, array $p): void
    {
        if (!$this->authorize('vod.edit')) return;

        $id  = (int)$p['id'];
        $own = $this->db->value(
            'SELECT s.id FROM vod_subtitles s
               JOIN vod_videos v ON v.id = s.vod_id
              WHERE s.id = ? AND v.tenant_id = ?',
            [$id, Auth::tenantId()]
        );
        if (!$own) Response::notFound('زیرنویس پیدا نشد');

        $this->svc()->delete($id);
        $this->log('vod.subtitle.delete', 'VodVideo', $id);
        Response::success(null, 'زیرنویس حذف شد');
    }
}
