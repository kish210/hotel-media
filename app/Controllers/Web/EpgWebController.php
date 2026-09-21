<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};

/**
 * پنل EPG — منابع، وضعیت تطبیق کانال‌ها و جدول پخش
 * فاز ۲ نقشه‌راه — docs/TODO.md (۱.۲)
 */
class EpgWebController extends Controller
{
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();

        $sources = $this->db->rows(
            'SELECT s.*, t.name AS tvh_name,
                    (SELECT COUNT(*) FROM epg_programs p
                      WHERE p.source_id = s.id AND p.ends_at >= NOW()) AS upcoming
               FROM epg_sources s
               LEFT JOIN tvheadend_sources t ON t.id = s.tvh_source_id
              WHERE s.tenant_id = ?
              ORDER BY s.id',
            [$tid]
        ) ?: [];

        $tvhSources = $this->db->rows(
            'SELECT id, name FROM tvheadend_sources WHERE tenant_id = ? AND is_active = 1 ORDER BY name',
            [$tid]
        ) ?: [];

        // کانال‌ها به‌همراه تعداد برنامه — تا اپراتور ببیند کدام کانال EPG ندارد
        $channels = $this->db->rows(
            'SELECT c.id, c.name, c.logo_url, c.epg_id, c.sort_order,
                    (SELECT COUNT(*) FROM epg_programs p
                      WHERE p.channel_id = c.id AND p.ends_at >= NOW()) AS program_count
               FROM iptv_channels c
              WHERE c.tenant_id = ? AND c.is_active = 1
              ORDER BY c.sort_order, c.name',
            [$tid]
        ) ?: [];

        // کلیدهایی که به هیچ کانالی وصل نشدند — رایج‌ترین مشکل راه‌اندازی EPG
        $unmatched = $this->db->rows(
            'SELECT channel_key, COUNT(*) AS n, MIN(starts_at) AS first_at
               FROM epg_programs
              WHERE tenant_id = ? AND channel_id IS NULL
              GROUP BY channel_key
              ORDER BY n DESC
              LIMIT 50',
            [$tid]
        ) ?: [];

        $stats = [
            'programs'  => (int)$this->db->value('SELECT COUNT(*) FROM epg_programs WHERE tenant_id=? AND ends_at >= NOW()', [$tid]),
            'matched'   => (int)$this->db->value('SELECT COUNT(DISTINCT channel_id) FROM epg_programs WHERE tenant_id=? AND channel_id IS NOT NULL', [$tid]),
            'channels'  => count($channels),
            'unmatched' => count($unmatched),
            'until'     => $this->db->value('SELECT MAX(ends_at) FROM epg_programs WHERE tenant_id=?', [$tid]),
        ];

        $this->view('admin.iptv.epg', [
            'title'      => 'راهنمای برنامه‌ها (EPG)',
            'sources'    => $sources,
            'tvhSources' => $tvhSources,
            'channels'   => $channels,
            'unmatched'  => $unmatched,
            'stats'      => $stats,
        ]);
    }

    /** POST /admin/epg/map — نگاشت دستی یک کلید به کانال */
    public function mapChannel(Request $req): void
    {
        $tid = Auth::tenantId();
        $key = trim((string)$req->post('channel_key', ''));
        $cid = (int)$req->post('channel_id', 0);

        if ($key === '' || !$cid) {
            $this->flash('error', 'کلید و کانال هر دو لازم است');
            $this->redirect('/admin/epg');
            return;
        }

        if (!$this->db->exists('iptv_channels', ['id' => $cid, 'tenant_id' => $tid])) {
            $this->flash('error', 'کانال انتخابی معتبر نیست');
            $this->redirect('/admin/epg');
            return;
        }

        $this->db->query(
            'INSERT INTO epg_channel_map (tenant_id, channel_key, channel_id) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE channel_id = VALUES(channel_id)',
            [$tid, mb_substr($key, 0, 120), $cid]
        );

        // نگاشت تازه را فوراً روی برنامه‌های موجود اعمال کن
        $n = $this->db->query(
            'UPDATE epg_programs SET channel_id = ? WHERE tenant_id = ? AND channel_key = ?',
            [$cid, $tid, $key]
        )->rowCount();

        $this->log('epg.map', 'epg_channel_map', $cid, [], ['key' => $key, 'programs' => $n]);
        $this->flash('success', "«$key» به کانال وصل شد — $n برنامه به‌روز شد");
        $this->redirect('/admin/epg');
    }
}
