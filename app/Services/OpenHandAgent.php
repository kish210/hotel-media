<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

/**
 * OpenHand — عامل اجرایی سامانهٔ هتل‌مدیا.
 *
 * برخلاف `AiAssistantService` که فقط «تشخیص و پیشنهاد» می‌دهد، این کلاس
 * کار را انجام می‌دهد: دستور فارسی اپراتور را می‌گیرد، در چند گام با مدل
 * زبانی تصمیم می‌گیرد کدام ابزار لازم است، و آن ابزار را اجرا می‌کند.
 *
 * ── چرا JSON و نه function-calling ─────────────────────────────
 * دروازهٔ مدلی که این سرور استفاده می‌کند همهٔ مدل‌هایش function-calling
 * ندارند و پاسخ بزرگ را وسط راه می‌برد. پس پروتکل ساده و متنی است:
 * مدل یا یک «اقدام» برمی‌گرداند یا «پاسخ نهایی». همان الگویی که در
 * دستیار موجود روی همین سرور جواب داده است.
 *
 * ── سه مرز ─────────────────────────────────────────────────────
 *  ۱. فقط ابزارهای فهرست‌شده اینجا اجرا می‌شوند؛ نام ناشناس رد می‌شود.
 *  ۲. هر ابزار نوشتنی از `OpenHandGuard` می‌گذرد: پشتیبان، بررسی سلامت،
 *     و بازگشت خودکار.
 *  ۳. هر ابزار در محدودهٔ tenant همان نشست کار می‌کند.
 */
final class OpenHandAgent
{
    /** حداکثر گام تصمیم در یک نوبت — بیشتر یعنی حلقه و هزینهٔ بی‌جا. */
    private const MAX_STEPS = 6;

    /** سقف توکن پاسخ؛ روی این شبکه پاسخ بلند نیمه‌راه گیر می‌کند. */
    private const MAX_TOKENS = 1100;

    private const HISTORY_KEY = 'openhand_history';
    private const HISTORY_MAX = 8;

    private Database $db;
    private AiAssistantService $ai;
    private OpenHandGuard $guard;

    public function __construct(?Database $db = null)
    {
        $this->db    = $db ?? Database::getInstance();
        $this->ai    = new AiAssistantService($this->db);
        $this->guard = new OpenHandGuard($this->db);
    }

    public function enabled(): bool { return $this->ai->enabled(); }

    public function guard(): OpenHandGuard { return $this->guard; }

    /** متن قانون از فایل؛ اگر نبود، متن پیش‌فرض کوتاه. */
    public function rules(): string
    {
        $f = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/resources/agent/openhand-rules.md';
        $txt = is_readable($f) ? (string)@file_get_contents($f) : '';
        if (trim($txt) === '') {
            $txt = "۱. فقط دستور اپراتور را انجام بده. ۲. هیچ فرمان شل یا SQL اجرا نکن. "
                 . "۳. هر تغییر با پشتیبان و بازگشت خودکار. ۴. کوتاه و فارسی جواب بده.";
        }
        return mb_substr($txt, 0, 4000);
    }

    public function clearHistory(): void
    {
        unset($_SESSION[self::HISTORY_KEY]);
    }

    // ══════════════════════════════════════════════════════════════
    //  اجرا
    // ══════════════════════════════════════════════════════════════

    /**
     * @return array{ok:bool,answer:string,steps:array,model:string,message:string}
     */
    public function run(int $tenantId, ?int $userId, string $message): array
    {
        $fail = static fn(string $m): array =>
            ['ok' => false, 'answer' => '', 'steps' => [], 'model' => '', 'message' => $m];

        if (!$this->enabled()) {
            return $fail('عامل تنظیم نشده — در .env باید AI_ENABLED=true و AI_BASE_URL و AI_API_KEY باشند.');
        }

        $model = (string)($this->ai->modelChain()[0] ?? '');
        if ($model === '') return $fail('مدلی انتخاب نشده؛ از صفحهٔ دستیار هوشمند یکی را انتخاب کنید.');

        $sys = $this->systemPrompt();

        $history = $_SESSION[self::HISTORY_KEY] ?? [];
        if (!is_array($history)) $history = [];

        $turn = [['role' => 'user', 'content' => $message]];
        $steps = [];
        $answer = '';

        for ($i = 1; $i <= self::MAX_STEPS; $i++) {
            $payload = [
                'model'       => $model,
                'messages'    => array_merge(
                    [['role' => 'system', 'content' => $sys]],
                    $history,
                    $turn
                ),
                'temperature' => 0.1,
                'max_tokens'  => self::MAX_TOKENS,
            ];

            $r = $this->ai->http('POST', '/chat/completions', $payload);
            if (!$r['ok']) {
                $msg = $r['message'];
                /* اگر مدل اصلی جواب نداد، یک‌بار مدل بعدی زنجیره امتحان می‌شود. */
                $alt = (string)($this->ai->modelChain()[1] ?? '');
                if ($alt !== '' && !str_contains($msg, 'کلید پذیرفته نشد')) {
                    $model = $alt;
                    $r = $this->ai->http('POST', '/chat/completions', array_merge($payload, ['model' => $model]));
                }
                if (!$r['ok']) return $fail('سرویس مدل جواب نداد: ' . $msg);
            }

            $j      = json_decode($r['body'], true);
            $msgArr = (array)($j['choices'][0]['message'] ?? []);
            $text   = trim((string)($msgArr['content'] ?? ''));
            if ($text === '') $text = trim((string)($msgArr['reasoning'] ?? ''));
            if ($text === '') return $fail('مدل پاسخ خالی داد؛ دوباره امتحان کنید یا مدل سبک‌تری انتخاب کنید.');

            $obj = $this->parseJson($text);
            if ($obj === null) return $fail('پاسخ مدل قابل‌فهم نبود. دوباره بپرسید.');

            $act = (string)($obj['action'] ?? '');

            if ($act === 'final') {
                $answer = trim((string)($obj['answer'] ?? '')) ?: $text;
                break;
            }

            $tool = (string)($obj['tool'] ?? '');
            $args = is_array($obj['args'] ?? null) ? $obj['args'] : [];

            $res = $this->exec($tool, $args, $tenantId, $userId);
            $steps[] = [
                'tool'      => $tool,
                'args'      => $args,
                'ok'        => $res['ok'],
                'message'   => $res['message'],
                'change_id' => $res['change_id'] ?? '',
            ];

            $turn[] = ['role' => 'assistant', 'content' => $text];
            $turn[] = ['role' => 'user', 'content' => 'نتیجهٔ ابزار ' . $tool . "\n" . json_encode($res, JSON_UNESCAPED_UNICODE)];

            /* تغییر انجام شد و مدل باید تمام کند؛ ادامه بده یعنی ریسک
               تغییر دوم بی‌دلیل. */
            if (($res['change_id'] ?? '') !== '' && !$res['ok']) {
                $answer = 'تغییر انجام نشد و سامانه سالم است. ' . $res['message'];
                break;
            }
        }

        if ($answer === '') {
            $answer = 'به سقف گام‌های تصمیم رسیدم و کار را نیمه‌کاره رها نکردم. اگر لازم است دوباره و دقیق‌تر بگویید.';
        }

        $history[] = ['role' => 'user', 'content' => mb_substr($message, 0, 500)];
        $history[] = ['role' => 'assistant', 'content' => mb_substr($answer, 0, 900)];
        $_SESSION[self::HISTORY_KEY] = array_slice($history, -self::HISTORY_MAX);

        return ['ok' => true, 'answer' => $answer, 'steps' => $steps, 'model' => $model, 'message' => ''];
    }

    /** اولین شیء JSON داخل متن مدل را بیرون می‌کشد. */
    private function parseJson(string $text): ?array
    {
        $t = trim($text);
        $t = (string)preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $t);
        if (preg_match('/\{[\s\S]*\}/', $t, $m)) {
            $j = json_decode($m[0], true);
            if (is_array($j)) return $j;
        }
        return null;
    }

    // ══════════════════════════════════════════════════════════════
    //  پرامپت
    // ══════════════════════════════════════════════════════════════

    private function systemPrompt(): string
    {
        $tools = <<<'TXT'
- overview  {}                                  وضعیت کلی سامانه و مشکل‌های شناخته‌شده
- list      {table, q?}                          خواندن فهرست؛ table یکی از: screens, playlists, media, iptv_channels, screen_messages, screen_groups, iptv_menus
- health    {}                                  بررسی سلامت (دیتابیس، دیسک، پاسخ پنل)
- logs      {}                                  چند خط آخر لاگ خطا (رمزها پاک شده)
- update_setting  {key, row_id, value}          تغییر یکی از تنظیمات مجاز
- screen_command  {screen_id, command, value?}  command: refresh|screenshot|mute|unmute|brightness|volume|clear_cache|reboot
- create_playlist {name, duration?}             ساخت پلی‌لیست تازه
- playlist_add    {playlist_id, media_id, seconds?}  افزودن رسانه به پلی‌لیست
- create_message  {title, body, duration?, screen_ids?}  پیام روی صفحه‌ها
- rollback        {change_id}                   بازگرداندن یکی از تغییرهای قبلی
TXT;

        $settings = [];
        foreach (AiAssistantService::SETTABLE as $k => $meta) {
            $settings[] = '  ' . $k . ' — ' . $meta['label'];
        }

        return "تو «OpenHand» هستی: عامل اجرایی سرور Hotel Media (مدیریت تلویزیون و تابلوی دیجیتال هتل).\n"
            . "کاربر مدیر ارشد هتل است، فارسی می‌نویسد و دانش فنی ندارد. تو فقط و فقط دستور او را انجام می‌دهی.\n\n"
            . "── قوانین (نقض‌کردنی نیست) ──\n" . $this->rules() . "\n\n"
            . "── نقشهٔ سامانه ──\n"
            . "screens: صفحه/تلویزیون. playlists + playlist_items: محتوا. media: کتابخانهٔ رسانه.\n"
            . "iptv_channels: کانال زنده. iptv_menus: منوی اتاق. screen_groups: گروه صفحه.\n"
            . "screen_messages: پیام روی صفحه. screen_commands: فرمان به دستگاه (refresh, reboot, …).\n\n"
            . "── تنظیماتی که update_setting می‌پذیرد ──\n" . implode("\n", $settings) . "\n\n"
            . "── ابزارها ──\n" . $tools . "\n\n"
            . "── پروتکل پاسخ ──\n"
            . "در هر گام دقیقا یک شیء JSON بده و هیچ متن دیگری ننویس.\n"
            . "برای انجام کار: {\"action\":\"tool\",\"tool\":\"نام\",\"args\":{...}}\n"
            . "برای پایان یا پاسخ: {\"action\":\"final\",\"answer\":\"پاسخ فارسی کوتاه\"}\n"
            . "اگر ابزاری لازم نیست و فقط باید توضیح بدهی، همان final را بده.\n"
            . "اگر نتیجهٔ ابزار خطا داد، دنبال راه دور زدن نرو؛ به اپراتور بگو.\n"
            . "در پاسخ نهایی اگر تغییری انجام شد شناسهٔ تغییر (change_id) را بیاور تا بتواند برگرداند.";
    }

    // ══════════════════════════════════════════════════════════════
    //  ابزارها
    // ══════════════════════════════════════════════════════════════

    /**
     * @return array{ok:bool,message:string,data?:mixed,change_id?:string}
     */
    private function exec(string $tool, array $a, int $tenantId, ?int $userId): array
    {
        try {
            return match ($tool) {
                'overview'       => $this->toolOverview($tenantId),
                'list'           => $this->toolList($a, $tenantId),
                'health'         => $this->toolHealth(),
                'logs'           => $this->toolLogs(),
                'update_setting' => $this->toolUpdateSetting($a, $tenantId),
                'screen_command' => $this->toolScreenCommand($a, $tenantId, $userId),
                'create_playlist'=> $this->toolCreatePlaylist($a, $tenantId, $userId),
                'playlist_add'   => $this->toolPlaylistAdd($a, $tenantId),
                'create_message' => $this->toolCreateMessage($a, $tenantId, $userId),
                'rollback'       => $this->toolRollback($a),
                default          => ['ok' => false, 'message' => 'ابزار ناشناس: ' . mb_substr($tool, 0, 40)],
            };
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'خطای ابزار: ' . mb_substr($e->getMessage(), 0, 200)];
        }
    }

    private function toolOverview(int $tenantId): array
    {
        $n = fn(string $sql, array $p = []) => (int)$this->db->value($sql, $p);
        $data = [
            'screens'  => [
                'total'  => $n('SELECT COUNT(*) FROM screens WHERE tenant_id=?', [$tenantId]),
                'online' => $n('SELECT COUNT(*) FROM screens WHERE tenant_id=? AND is_online=1', [$tenantId]),
            ],
            'playlists'      => $n('SELECT COUNT(*) FROM playlists WHERE tenant_id=?', [$tenantId]),
            'media'          => $n('SELECT COUNT(*) FROM media WHERE tenant_id=? AND deleted_at IS NULL', [$tenantId]),
            'iptv_channels'  => $n('SELECT COUNT(*) FROM iptv_channels WHERE tenant_id=?', [$tenantId]),
            'screen_messages'=> $n('SELECT COUNT(*) FROM screen_messages WHERE tenant_id=?', [$tenantId]),
            'problems'       => $this->ai->problems($tenantId),
            'health'         => $this->guard->health()['ok'] ? 'سالم' : 'مشکل دارد',
        ];
        return ['ok' => true, 'message' => 'وضعیت خوانده شد', 'data' => $data];
    }

    /** جدول‌های فقط‌خواندنی و ستون‌هایی که به مدل نشان داده می‌شود. */
    private const READABLE = [
        'screens'        => ['id', 'name', 'status', 'is_online', 'last_seen_at', 'current_playlist_id'],
        'playlists'      => ['id', 'name', 'default_duration', 'is_active'],
        'media'          => ['id', 'name', 'type', 'duration', 'file_path'],
        'iptv_channels'  => ['id', 'name', 'category', 'is_active'],
        'screen_messages'=> ['id', 'title', 'is_active', 'start_at', 'end_at'],
        'screen_groups'  => ['id', 'name'],
        'iptv_menus'     => ['id', 'name'],
    ];

    private function toolList(array $a, int $tenantId): array
    {
        $table = (string)($a['table'] ?? '');
        if (!isset(self::READABLE[$table])) {
            return ['ok' => false, 'message' => 'این جدول خواندنی نیست: ' . mb_substr($table, 0, 40)];
        }

        $cols = self::READABLE[$table];
        $sql  = "SELECT * FROM `$table` WHERE tenant_id = ?";
        $par  = [$tenantId];

        $q = trim((string)($a['q'] ?? ''));
        if ($q !== '' && in_array('name', $cols, true)) {
            $sql .= ' AND name LIKE ?';
            $par[] = '%' . mb_substr($q, 0, 60) . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT 30';

        $rows = $this->db->rows($sql, $par) ?: [];
        $out  = [];
        foreach ($rows as $r) {
            $item = [];
            foreach ($cols as $c) if (array_key_exists($c, $r)) $item[$c] = $r[$c];
            $out[] = $item;
        }

        return ['ok' => true, 'message' => count($out) . ' ردیف', 'data' => ['table' => $table, 'rows' => $out]];
    }

    private function toolHealth(): array
    {
        $h = $this->guard->health();
        $bad = array_values(array_filter($h['checks'], fn($c) => !$c['ok']));
        return [
            'ok'      => true,
            'message' => $h['ok'] ? 'همه چیز سالم است' : ('مشکل: ' . $h['reason']),
            'data'    => ['ok' => $h['ok'], 'failed' => $bad],
        ];
    }

    /** چند خط آخر لاگ‌ها با پاک‌سازی رمز و کلید. */
    private function toolLogs(): array
    {
        $files = [
            (defined('STORAGE_PATH') ? STORAGE_PATH : ROOT_PATH . '/storage') . '/logs/cron.log',
            (defined('STORAGE_PATH') ? STORAGE_PATH : ROOT_PATH . '/storage') . '/logs/php-errors.log',
        ];

        $lines = [];
        foreach ($files as $f) {
            if (!is_readable($f)) continue;
            $all = @file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach (array_slice($all, -20) as $l) {
                $lines[] = mb_substr($this->ai->redact((string)$l), 0, 240);
            }
        }

        return ['ok' => true, 'message' => count($lines) . ' خط', 'data' => array_slice($lines, -25)];
    }

    private function toolUpdateSetting(array $a, int $tenantId): array
    {
        $sug = [
            'key'    => (string)($a['key'] ?? ''),
            'row_id' => (int)($a['row_id'] ?? 0),
            'value'  => $a['value'] ?? null,
            'why'    => (string)($a['why'] ?? ''),
        ];

        $v = $this->ai->validateSuggestion($sug);
        if ($v === null) return ['ok' => false, 'message' => 'این کلید یا مقدار در فهرست مجاز نیست'];

        [$table, $col] = explode('.', $v['key'], 2);

        $r = $this->guard->guarded(
            'تغییر ' . $v['label'],
            [['table' => $table, 'id' => (int)$v['row_id']]],
            function () use ($tenantId, $table, $col, $v) {
                $owner = $table === 'playlist_items'
                    ? (int)$this->db->value(
                        'SELECT COUNT(*) FROM playlist_items p JOIN playlists pl ON pl.id=p.playlist_id
                          WHERE p.id=? AND pl.tenant_id=?', [$v['row_id'], $tenantId])
                    : (int)$this->db->value("SELECT COUNT(*) FROM `$table` WHERE id=? AND tenant_id=?", [$v['row_id'], $tenantId]);
                if ($owner < 1) throw new \RuntimeException('ردیف پیدا نشد');

                $this->db->update($table, [$col => $v['value']], ['id' => (int)$v['row_id']]);
                return true;
            }
        );

        if (!$r['ok']) return ['ok' => false, 'message' => $r['message'], 'change_id' => $r['change_id']];

        return [
            'ok'        => true,
            'message'   => $v['label'] . ' روی ردیف ' . $v['row_id'] . ' به «' . $v['value'] . '» تغییر کرد',
            'change_id' => $r['change_id'],
        ];
    }

    private function toolScreenCommand(array $a, int $tenantId, ?int $userId): array
    {
        $screenId = (int)($a['screen_id'] ?? 0);
        $cmd      = (string)($a['command'] ?? '');
        $allowed  = ['refresh', 'screenshot', 'mute', 'unmute', 'brightness', 'volume', 'clear_cache', 'reboot'];
        if (!in_array($cmd, $allowed, true)) return ['ok' => false, 'message' => 'این فرمان مجاز نیست'];

        $screen = $this->db->row('SELECT id, name FROM screens WHERE id=? AND tenant_id=?', [$screenId, $tenantId]);
        if ($screen === null) return ['ok' => false, 'message' => 'صفحه پیدا نشد'];

        $payload = null;
        if (in_array($cmd, ['brightness', 'volume'], true)) {
            $val = (int)($a['value'] ?? 0);
            if ($val < 0 || $val > 100) return ['ok' => false, 'message' => 'مقدار باید بین ۰ تا ۱۰۰ باشد'];
            $payload = ['value' => $val];
        }

        $r = $this->guard->guarded(
            'فرمان ' . $cmd . ' برای صفحهٔ ' . $screen['name'],
            [],
            function (array &$ctx) use ($screenId, $cmd, $payload, $userId) {
                $id = $this->db->insert('screen_commands', [
                    'screen_id' => $screenId,
                    'issued_by' => $userId,
                    'command'   => $cmd,
                    'payload'   => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
                ]);
                $ctx['inserted'][] = ['table' => 'screen_commands', 'id' => (int)$id];
                return (int)$id;
            }
        );

        if (!$r['ok']) return ['ok' => false, 'message' => $r['message'], 'change_id' => $r['change_id']];
        return ['ok' => true, 'message' => 'فرمان برای «' . $screen['name'] . '» ثبت شد', 'change_id' => $r['change_id']];
    }

    private function toolCreatePlaylist(array $a, int $tenantId, ?int $userId): array
    {
        $name = trim((string)($a['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 200) return ['ok' => false, 'message' => 'نام پلی‌لیست لازم است'];

        $dur = (int)($a['duration'] ?? 10);
        $dur = max(3, min(600, $dur));

        $r = $this->guard->guarded(
            'ساخت پلی‌لیست «' . $name . '»',
            [],
            function (array &$ctx) use ($tenantId, $userId, $name, $dur) {
                $id = $this->db->insert('playlists', [
                    'tenant_id'        => $tenantId,
                    'created_by'       => (int)($userId ?: 1),
                    'name'             => $name,
                    'default_duration' => $dur,
                    'is_active'        => 1,
                ]);
                $ctx['inserted'][] = ['table' => 'playlists', 'id' => (int)$id];
                return (int)$id;
            }
        );

        if (!$r['ok']) return ['ok' => false, 'message' => $r['message'], 'change_id' => $r['change_id']];
        return ['ok' => true, 'message' => 'پلی‌لیست ساخته شد (شناسه ' . $r['result'] . ')', 'change_id' => $r['change_id']];
    }

    private function toolPlaylistAdd(array $a, int $tenantId): array
    {
        $pid = (int)($a['playlist_id'] ?? 0);
        $mid = (int)($a['media_id'] ?? 0);

        $pl = $this->db->row('SELECT id, name FROM playlists WHERE id=? AND tenant_id=?', [$pid, $tenantId]);
        if ($pl === null) return ['ok' => false, 'message' => 'پلی‌لیست پیدا نشد'];

        $m = $this->db->row('SELECT id, name FROM media WHERE id=? AND tenant_id=? AND deleted_at IS NULL', [$mid, $tenantId]);
        if ($m === null) return ['ok' => false, 'message' => 'رسانه پیدا نشد'];

        $sec = (int)($a['seconds'] ?? 10);
        $sec = max(3, min(600, $sec));

        $r = $this->guard->guarded(
            'افزودن «' . $m['name'] . '» به «' . $pl['name'] . '»',
            [],
            function (array &$ctx) use ($pid, $mid, $sec) {
                $order = (int)$this->db->value('SELECT COALESCE(MAX(sort_order),0)+1 FROM playlist_items WHERE playlist_id=?', [$pid]);
                $id = $this->db->insert('playlist_items', [
                    'playlist_id' => $pid,
                    'media_id'    => $mid,
                    'sort_order'  => $order,
                    'duration'    => $sec,
                    'is_active'   => 1,
                ]);
                $ctx['inserted'][] = ['table' => 'playlist_items', 'id' => (int)$id];
                return (int)$id;
            }
        );

        if (!$r['ok']) return ['ok' => false, 'message' => $r['message'], 'change_id' => $r['change_id']];
        return ['ok' => true, 'message' => 'به پلی‌لیست اضافه شد', 'change_id' => $r['change_id']];
    }

    private function toolCreateMessage(array $a, int $tenantId, ?int $userId): array
    {
        $title = trim((string)($a['title'] ?? ''));
        $body  = trim((string)($a['body'] ?? ''));
        if ($title === '' || $body === '') return ['ok' => false, 'message' => 'عنوان و متن پیام لازم است'];

        $dur = (int)($a['duration'] ?? 15);
        $dur = max(5, min(300, $dur));

        $ids = array_values(array_filter(array_map('intval', (array)($a['screen_ids'] ?? []))));
        $own = [];
        foreach ($ids as $sid) {
            if ($this->db->row('SELECT id FROM screens WHERE id=? AND tenant_id=?', [$sid, $tenantId])) $own[] = $sid;
        }

        $r = $this->guard->guarded(
            'پیام صفحه «' . $title . '»',
            [],
            function (array &$ctx) use ($tenantId, $userId, $title, $body, $dur, $own) {
                $id = $this->db->insert('screen_messages', [
                    'tenant_id'  => $tenantId,
                    'title'      => mb_substr($title, 0, 200),
                    'body'       => $body,
                    'type'       => 'announcement',
                    'style'      => 'overlay',
                    'target'     => $own ? 'screen' : 'all',
                    'target_ids' => $own ? json_encode($own) : null,
                    'start_at'   => date('Y-m-d H:i:s'),
                    'duration'   => $dur,
                    'is_active'  => 1,
                    'created_by' => $userId,
                ]);
                $ctx['inserted'][] = ['table' => 'screen_messages', 'id' => (int)$id];
                return (int)$id;
            }
        );

        if (!$r['ok']) return ['ok' => false, 'message' => $r['message'], 'change_id' => $r['change_id']];
        return ['ok' => true, 'message' => 'پیام ثبت شد و روی صفحه‌ها می‌رود', 'change_id' => $r['change_id']];
    }

    private function toolRollback(array $a): array
    {
        $id = (string)($a['change_id'] ?? '');
        if ($id === '') return ['ok' => false, 'message' => 'شناسهٔ تغییر لازم است'];

        $r = $this->guard->restore($id);
        return ['ok' => $r['ok'], 'message' => $r['message']];
    }
}
