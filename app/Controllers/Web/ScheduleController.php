<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};

class ScheduleController extends Controller
{
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();
        /* محل و گروه هم هدف برنامه‌اند، نه فقط صفحه.
           تا پیش از این فقط `screen_id` خوانده می‌شد، پس برنامه‌ای که
           روی لابی منتشر شده بود در این فهرست «همه صفحات» دیده می‌شد —
           یعنی اپراتور فکر می‌کرد تمام هتل را عوض کرده. */
        $schedules = $this->db->rows(
            "SELECT sc.*, p.name AS playlist_name,
                    s.name AS screen_name,
                    v.name AS venue_name,
                    g.name AS group_name,
                    (SELECT COUNT(*) FROM screens x WHERE x.venue_id = sc.venue_id) AS venue_screens,
                    (SELECT COUNT(*) FROM screens x WHERE x.group_id = sc.group_id) AS group_screens
             FROM schedules sc
             JOIN playlists p ON p.id = sc.playlist_id
             LEFT JOIN screens s       ON s.id = sc.screen_id
             LEFT JOIN venues v        ON v.id = sc.venue_id
             LEFT JOIN screen_groups g ON g.id = sc.group_id
             WHERE sc.tenant_id=? ORDER BY sc.priority DESC, sc.created_at DESC",
            [$tid]
        );
        $playlists = $this->db->rows("SELECT id,name FROM playlists WHERE tenant_id=? AND is_active=1 ORDER BY name", [$tid]);
        $screens   = $this->db->rows("SELECT id,name,code FROM screens WHERE tenant_id=? AND status != 'inactive' ORDER BY name", [$tid]);
        $venues    = $this->db->rows("SELECT id,name FROM venues WHERE tenant_id=? AND is_active=1 ORDER BY sort_order,name", [$tid]) ?: [];
        $groups    = $this->db->rows("SELECT id,name FROM screen_groups WHERE tenant_id=? ORDER BY name", [$tid]) ?: [];


        /* ── تداخل‌های موجود ─────────────────────────────────────────
           دو زمان‌بندی فعال روی یک هدف با اولویت یکسان بی‌صدا با هم
           رقابت می‌کنند و هیچ‌کس نمی‌فهمد کدام برنده است. روی سرور
           تولید ۹ زمان‌بندی در اصل ۳ مجموعهٔ تکراری بودند و هیچ‌جا
           دیده نمی‌شد. اینجا فهرستشان بالای صفحه می‌آید تا اپراتور
           یکی را خاموش کند یا اولویتش را عوض کند. */
        $clashes = [];
        try {
            $clashes = $this->db->rows(
                "SELECT COUNT(*) AS n,
                        GROUP_CONCAT(sc.name SEPARATOR ' ، ') AS names,
                        sc.priority,
                        /* نام هدف بدون متن ثابت فارسی داخل SQL: مخلوط‌کردن یک
                           رشتهٔ ثابت با ستون‌هایی که collation خودشان را دارند،
                           روی MariaDB خطای «Illegal mix of collations» می‌دهد.
                           برچسب «همه‌ی صفحه‌ها» پایین‌تر در PHP گذاشته می‌شود. */
                        MAX(s.name) AS screen_name,
                        MAX(v.name) AS venue_name,
                        MAX(g.name) AS group_name
                   FROM schedules sc
              LEFT JOIN screens s       ON s.id = sc.screen_id
              LEFT JOIN venues v        ON v.id = sc.venue_id
              LEFT JOIN screen_groups g ON g.id = sc.group_id
                  WHERE sc.tenant_id = ? AND sc.is_active = 1
                  GROUP BY IFNULL(sc.screen_id,0), IFNULL(sc.venue_id,0),
                           IFNULL(sc.group_id,0), sc.priority
                 HAVING n > 1",
                [$tid]
            ) ?: [];
            foreach ($clashes as &$c) {
                $c['target'] = $c['screen_name'] ?: ($c['venue_name'] ?: ($c['group_name'] ?: 'همه‌ی صفحه‌ها'));
            }
            unset($c);
        } catch (\Throwable) { $clashes = []; }

        $this->view('schedules.index', compact('schedules','playlists','screens','venues','groups','clashes') + ['title' => 'زمان‌بندی محتوا']);
    }

    public function store(Request $req): void
    {
        $errors = $req->validate(['playlist_id' => 'required', 'name' => 'required']);
        if ($errors) { $this->flash('error', 'خطا در اطلاعات'); $this->redirect('/admin/schedules'); return; }

        $weekdays = $req->post('weekdays') ? json_encode($req->post('weekdays')) : null;

        /* یک هدف و فقط یک هدف.
           فرم با یک select هدف را می‌گیرد («screen:7»، «venue:1»،
           «group:2» یا خالی برای همگانی) چون سه فیلد موازی یعنی
           اپراتور می‌تواند هم صفحه و هم محل را پر کند و بعد هیچ‌کس
           نداند کدام برنده است. `Screen::getCurrentPlaylist()` هم بر
           همین فرض ساخته شده. */
        [$tKind, $tId] = array_pad(explode(':', (string)$req->post('target', ''), 2), 2, '');
        $tId = (int)$tId;

        $newId = (int)$this->db->insert('schedules', [
            'tenant_id'   => Auth::tenantId(),
            'playlist_id' => (int)$req->post('playlist_id'),
            'screen_id'   => $tKind === 'screen' && $tId ? $tId : null,
            'venue_id'    => $tKind === 'venue'  && $tId ? $tId : null,
            'group_id'    => $tKind === 'group'  && $tId ? $tId : null,
            'name'        => $req->post('name'),
            'type'        => $req->post('type', 'always'),
            'start_date'  => $req->post('start_date') ?: null,
            'end_date'    => $req->post('end_date') ?: null,
            'start_time'  => $req->post('start_time') ?: null,
            'end_time'    => $req->post('end_time') ?: null,
            'weekdays'    => $weekdays,
            'priority'    => (int)$req->post('priority', 5),
            'is_active'   => 1,
        ]);
        /* ── هشدار تداخل، نه جلوگیری ─────────────────────────────────
           دو زمان‌بندی روی یک هدف با اولویت یکسان بی‌صدا با هم رقابت
           می‌کنند و اپراتور نمی‌فهمد چرا تابلو آن چیزی را که انتظار
           داشت پخش نمی‌کند. جلوی ساختش را نمی‌گیریم (گاهی عمدی است)
           ولی لااقل می‌گوییم. */
        $clash = 0;
        try {
            $clash = (int)$this->db->value(
                'SELECT COUNT(*) FROM schedules
                  WHERE tenant_id = ? AND is_active = 1 AND id <> ?
                    AND priority = ?
                    AND IFNULL(screen_id,0) = ? AND IFNULL(venue_id,0) = ? AND IFNULL(group_id,0) = ?',
                [Auth::tenantId(), $newId, (int)$req->post('priority', 5),
                 $tKind === 'screen' && $tId ? $tId : 0,
                 $tKind === 'venue'  && $tId ? $tId : 0,
                 $tKind === 'group'  && $tId ? $tId : 0]
            );
        } catch (\Throwable) {}

        if ($clash > 0) {
            $this->flash('error', 'ساخته شد، ولی ' . $clash . ' زمان‌بندی دیگر با همین هدف و همین اولویت هست — '
                . 'معلوم نیست کدام برنده می‌شود. اولویت یکی را عوض کنید.');
            $this->log('schedule.create', 'Schedule');
            $this->redirect('/admin/schedules');
            return;
        }

        $this->flash('success', 'زمان‌بندی ایجاد شد');
        $this->log('schedule.create', 'Schedule');
        $this->redirect('/admin/schedules');
    }

    /**
     * POST /admin/schedules/{id}/duplicate
     *
     * چرا لازم است: تا امروز تنها کار ممکن روی یک زمان‌بندی «حذف» بود.
     * برای عوض‌کردن یک ساعت، اپراتور باید ردیف را پاک می‌کرد و هر نُه
     * فیلد را از نو پر می‌کرد — و تا وقتی ردیف تازه ساخته نشده بود،
     * تابلو بی‌برنامه می‌ماند. کپی، همان ردیف را با نام تازه می‌سازد
     * و خاموش می‌گذارد تا اپراتور بی‌خطر ویرایشش کند.
     */
    public function duplicate(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $row = $this->db->row('SELECT * FROM schedules WHERE id=? AND tenant_id=?',
                              [(int)$params['id'], $tid]);
        if (!$row) { $this->flash('error', 'زمان‌بندی پیدا نشد'); $this->redirect('/admin/schedules'); return; }

        unset($row['id'], $row['created_at'], $row['updated_at']);
        $row['name']      = mb_substr('رونوشت ' . $row['name'], 0, 120);
        $row['is_active'] = 0;      // خاموش، تا با نسخهٔ اصلی تداخل نسازد
        $this->db->insert('schedules', $row);

        $this->flash('success', 'رونوشت ساخته شد و خاموش است — ویرایشش کنید و بعد روشن');
        $this->log('schedule.duplicate', 'Schedule', (int)$params['id']);
        $this->redirect('/admin/schedules');
    }

    /** POST /admin/schedules/{id}/toggle */
    public function toggle(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $row = $this->db->row('SELECT id, is_active FROM schedules WHERE id=? AND tenant_id=?',
                              [(int)$params['id'], $tid]);
        if (!$row) { $this->flash('error', 'زمان‌بندی پیدا نشد'); $this->redirect('/admin/schedules'); return; }

        $this->db->update('schedules', ['is_active' => $row['is_active'] ? 0 : 1], ['id' => (int)$row['id']]);
        $this->flash('success', $row['is_active'] ? 'خاموش شد' : 'روشن شد');
        $this->redirect('/admin/schedules');
    }

    public function destroy(Request $req, array $params): void
    {
        $this->db->delete('schedules', ['id' => (int)$params['id'], 'tenant_id' => Auth::tenantId()]);
        $this->flash('success', 'زمان‌بندی حذف شد');
        $this->redirect('/admin/schedules');
    }
}
