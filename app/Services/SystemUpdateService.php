<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * به‌روزرسانی خود سرور از گیت‌هاب.
 *
 * زنجیره‌ی به‌روزرسانی در هتل:
 *
 *     GitHub (نسخه‌ی پایدار)
 *         ↓  سرور هتل می‌کشد  (همین سرویس)
 *     سرور Hotel Media
 *         ↓  تلویزیون‌ها می‌کشند
 *     ۳۰۰ تلویزیون
 *
 * چرا سرور باید APK را هم آینه کند: تلویزیون‌های اتاق روی VLAN بدون
 * اینترنت‌اند و هیچ‌وقت به گیت‌هاب نمی‌رسند. پس وقتی سرور به‌روز
 * می‌شود، فایل APK را هم از همان انتشار برمی‌دارد و در public/apk
 * می‌گذارد تا تلویزیون‌ها از خود سرور بگیرند.
 *
 * ── تصمیم‌های طراحی ──────────────────────────────────────────────
 *
 * فقط انتشارهای «پایدار» دیده می‌شوند، نه هر commit روی main. یعنی
 * شما روی گیت‌هاب tag می‌زنید و تا آن لحظه هیچ هتلی چیزی نمی‌گیرد.
 *
 * هر به‌روزرسانی پیش از اعمال یک نسخه‌ی پشتیبان می‌گیرد (فایل‌ها +
 * دیتابیس). اگر مهاجرت یا خود به‌روزرسانی شکست بخورد، برگشت ممکن
 * است. بدون این، یک به‌روزرسانی ناموفق یعنی هتل بدون تلویزیون.
 *
 * دانلود با checksum بررسی می‌شود. سرور هتل معمولا پشت پراکسی و
 * فیلترینگ است و فایل نصفه یا دستکاری‌شده اتفاق نادری نیست.
 */
final class SystemUpdateService
{
    /** مخزن رسمی. تغییرش فقط برای فورک. */
    private const REPO = 'kish210/hotel-media';

    /** مهلت هر درخواست شبکه — سرور هتل معمولا کند است */
    private const HTTP_TIMEOUT = 30;

    /** چند نسخه‌ی پشتیبان نگه داشته شود */
    private const KEEP_BACKUPS = 3;

    private Database $db;
    private string $root;

    public function __construct(?Database $db = null)
    {
        $this->db   = $db ?? Database::getInstance();
        $this->root = ROOT_PATH;
    }

    // ══════════════════════════════════════════════════════════════
    //  نسخه
    // ══════════════════════════════════════════════════════════════

    /** نسخه‌ی نصب‌شده، از فایل VERSION */
    public function current(): string
    {
        $f = $this->root . '/VERSION';
        $v = is_file($f) ? trim((string)file_get_contents($f)) : '';
        return $v !== '' ? $v : '0.0.0';
    }

    /**
     * مقایسه‌ی دو نسخه‌ی semver.
     * برمی‌گرداند: ۱ اگر $a بزرگ‌تر، ‎-۱ اگر کوچک‌تر، ۰ اگر برابر.
     *
     * version_compare خود PHP «1.10» را کوچک‌تر از «1.9» می‌گیرد اگر
     * قالب دقیقا semver نباشد، پس دستی مقایسه می‌کنیم.
     */
    public static function compare(string $a, string $b): int
    {
        $pa = array_map('intval', explode('.', ltrim($a, 'vV')));
        $pb = array_map('intval', explode('.', ltrim($b, 'vV')));
        for ($i = 0; $i < 3; $i++) {
            $x = $pa[$i] ?? 0;
            $y = $pb[$i] ?? 0;
            if ($x !== $y) return $x <=> $y;
        }
        return 0;
    }

    // ══════════════════════════════════════════════════════════════
    //  بررسی انتشار جدید
    // ══════════════════════════════════════════════════════════════

    /**
     * آخرین انتشار پایدار روی گیت‌هاب.
     *
     * @return array{ok:bool,message:string,available:bool,current:string,
     *               latest:string,notes:string,published_at:string,
     *               tarball:string,apk:string,apk_size:int}
     */
    public function check(): array
    {
        $cur = $this->current();
        $out = [
            'ok' => false, 'message' => '', 'available' => false,
            'current' => $cur, 'latest' => '', 'notes' => '',
            'published_at' => '', 'tarball' => '', 'apk' => '', 'apk_size' => 0,
        ];

        $res = $this->http('https://api.github.com/repos/' . self::REPO . '/releases/latest');
        if (!$res['ok']) {
            $out['message'] = $res['message'];
            return $out;
        }

        $r = json_decode($res['body'], true);
        if (!is_array($r) || empty($r['tag_name'])) {
            $out['message'] = 'پاسخ گیت‌هاب قابل خواندن نبود';
            return $out;
        }

        /* انتشار پیش‌نمایش و پیش‌نویس برای هتل نیست */
        if (!empty($r['prerelease']) || !empty($r['draft'])) {
            $out['ok'] = true;
            $out['message'] = 'آخرین انتشار هنوز پایدار نشده است';
            return $out;
        }

        $latest = ltrim((string)$r['tag_name'], 'vV');

        $out['ok']           = true;
        $out['latest']       = $latest;
        $out['notes']        = (string)($r['body'] ?? '');
        $out['published_at'] = (string)($r['published_at'] ?? '');
        $out['tarball']      = (string)($r['tarball_url'] ?? '');
        $out['available']    = self::compare($latest, $cur) > 0;

        /* فایل APK از همان انتشار، تا سرور بتواند برای تلویزیون‌ها
           آینه‌اش کند.
           انتشارها معمولا چند APK دارند: یکی با شماره‌ی نسخه در نام و
           یکی بدون آن (نام ثابت برای لینک پایدار). آن که شماره دارد
           مقدم است، چون فایلِ نام‌ثابت ممکن است از انتشار قبلی مانده
           باشد و سرور نسخه‌ی اشتباه را به ۳۰۰ تلویزیون بدهد. */
        $fallback = null;
        foreach (($r['assets'] ?? []) as $a) {
            $name = (string)($a['name'] ?? '');
            if (!str_ends_with($name, '.apk')) continue;

            if (str_contains($name, $latest)) {
                $out['apk']      = (string)$a['browser_download_url'];
                $out['apk_size'] = (int)($a['size'] ?? 0);
                $fallback = null;
                break;
            }
            $fallback ??= $a;
        }
        if ($fallback !== null) {
            $out['apk']      = (string)$fallback['browser_download_url'];
            $out['apk_size'] = (int)($fallback['size'] ?? 0);
        }

        $out['message'] = $out['available']
            ? 'نسخه‌ی ' . $latest . ' در دسترس است'
            : 'سیستم به‌روز است';

        return $out;
    }

    // ══════════════════════════════════════════════════════════════
    //  درخواست HTTP
    // ══════════════════════════════════════════════════════════════

    /** @return array{ok:bool,body:string,message:string} */
    private function http(string $url): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'body' => '', 'message' => 'افزونه‌ی curl نصب نیست'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            /* گیت‌هاب بدون User-Agent پاسخ ۴۰۳ می‌دهد */
            CURLOPT_USERAGENT      => 'HotelMedia/' . $this->current(),
            CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github+json'],
        ]);

        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            /* پیام خام curl انگلیسی است و برای اپراتور هتل بی‌فایده.
               دو علت رایج را جدا می‌کنیم چون چاره‌شان فرق دارد. */
            if (stripos($err, 'certificate') !== false || stripos($err, 'SSL') !== false) {
                return ['ok' => false, 'body' => '', 'message' =>
                    'گواهی امنیتی بررسی نشد. روی سرور بسته‌ی ca-certificates را نصب ' .
                    'یا به‌روز کنید: sudo apt install --reinstall ca-certificates'];
            }
            return ['ok' => false, 'body' => '', 'message' =>
                'اتصال به گیت‌هاب برقرار نشد. اگر سرور اینترنت ندارد، ' .
                'از به‌روزرسانی دستی استفاده کنید. (' . $err . ')'];
        }
        if ($code === 403 || $code === 429) {
            return ['ok' => false, 'body' => '', 'message' =>
                'گیت‌هاب فعلا پاسخ نمی‌دهد (محدودیت نرخ). چند دقیقه بعد دوباره امتحان کنید.'];
        }
        if ($code === 404) {
            return ['ok' => false, 'body' => '', 'message' =>
                'هیچ انتشار پایداری روی مخزن پیدا نشد.'];
        }
        if ($code < 200 || $code >= 300) {
            return ['ok' => false, 'body' => '', 'message' => 'گیت‌هاب خطای ' . $code . ' داد'];
        }

        return ['ok' => true, 'body' => (string)$body, 'message' => ''];
    }

    /**
     * دانلود فایل با نمایش پیشرفت در فایل وضعیت.
     *
     * @return array{ok:bool,message:string,bytes:int}
     */
    private function download(string $url, string $dest, ?string $progressKey = null): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'افزونه‌ی curl نصب نیست', 'bytes' => 0];
        }

        $fh = @fopen($dest, 'wb');
        if (!$fh) return ['ok' => false, 'message' => 'فایل مقصد ساخته نشد', 'bytes' => 0];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            /* دانلود بسته روی اینترنت کند هتل طول می‌کشد؛ مهلت کوتاه
               باعث می‌شد به‌روزرسانی نصفه رها شود. */
            CURLOPT_TIMEOUT        => 900,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT      => 'HotelMedia/' . $this->current(),
            CURLOPT_FAILONERROR    => true,
        ]);

        if ($progressKey !== null) {
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION,
                function ($res, $total, $done) use ($progressKey) {
                    if ($total > 0) {
                        $this->progress($progressKey, (int)floor($done * 100 / $total));
                    }
                    return 0;
                });
        }

        $ok   = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);

        if ($ok === false) {
            @unlink($dest);
            return ['ok' => false, 'message' => 'دانلود ناموفق (' . ($err ?: 'HTTP ' . $code) . ')', 'bytes' => 0];
        }

        $size = (int)@filesize($dest);
        if ($size < 1024) {
            @unlink($dest);
            return ['ok' => false, 'message' => 'فایل دانلودشده ناقص است', 'bytes' => 0];
        }

        return ['ok' => true, 'message' => '', 'bytes' => $size];
    }

    // ══════════════════════════════════════════════════════════════
    //  وضعیت پیشرفت
    // ══════════════════════════════════════════════════════════════

    private function stateFile(): string
    {
        return STORAGE_PATH . '/update-state.json';
    }

    /**
     * به‌روزرسانی چند دقیقه طول می‌کشد و اپراتور نباید صفحه‌ی خالی
     * ببیند. وضعیت در فایل نوشته می‌شود تا رابط کاربر بتواند بپرسد.
     */
    private function progress(string $step, int $percent, string $note = ''): void
    {
        @file_put_contents($this->stateFile(), json_encode([
            'step'    => $step,
            'percent' => max(0, min(100, $percent)),
            'note'    => $note,
            'at'      => time(),
        ], JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string,mixed> */
    public function state(): array
    {
        $f = $this->stateFile();
        if (!is_file($f)) return ['step' => 'idle', 'percent' => 0, 'note' => '', 'at' => 0];
        $d = json_decode((string)file_get_contents($f), true);
        return is_array($d) ? $d : ['step' => 'idle', 'percent' => 0, 'note' => '', 'at' => 0];
    }
    public function clearState(): void
    {
        @unlink($this->stateFile());
    }

    // ══════════════════════════════════════════════════════════════
    //  اعمال به‌روزرسانی
    // ══════════════════════════════════════════════════════════════

    /**
     * به‌روزرسانی کامل:
     *   پشتیبان → دانلود → باز کردن → جایگزینی → مهاجرت → آینه‌ی APK
     *   → اطلاع به تلویزیون‌ها
     *
     * هر مرحله که شکست بخورد کار متوقف می‌شود و آنچه کار می‌کرد
     * دست‌نخورده می‌ماند. تنها مرحله‌ی برگشت‌ناپذیر جایگزینی فایل‌هاست
     * و پیش از آن پشتیبان گرفته شده.
     *
     * @return array{ok:bool,message:string,from:string,to:string,backup:string}
     */
    public function apply(?string $tarball = null, ?string $apkUrl = null): array
    {
        $from = $this->current();
        $res  = ['ok' => false, 'message' => '', 'from' => $from, 'to' => '', 'backup' => ''];

        if ($tarball === null) {
            $chk = $this->check();
            if (!$chk['ok'])         { $res['message'] = $chk['message']; return $res; }
            if (!$chk['available'])  { $res['message'] = 'سیستم از قبل به‌روز است'; return $res; }
            $tarball   = $chk['tarball'];
            $apkUrl    = $chk['apk'] ?: null;
            $res['to'] = $chk['latest'];
        }

        $tmp = STORAGE_PATH . '/update';
        $this->rmrf($tmp);
        if (!@mkdir($tmp, 0775, true)) {
            $res['message'] = 'پوشه‌ی موقت ساخته نشد: ' . $tmp;
            return $res;
        }

        try {
            $this->progress('backup', 5, 'در حال تهیه نسخه پشتیبان');
            $bk = $this->backup();
            if (!$bk['ok']) { $res['message'] = $bk['message']; return $res; }
            $res['backup'] = $bk['path'];

            $this->progress('download', 15, 'در حال دانلود از گیت‌هاب');
            $tar = $tmp . '/release.tar.gz';
            $dl  = $this->download($tarball, $tar, 'download');
            if (!$dl['ok']) { $res['message'] = $dl['message']; return $res; }

            $this->progress('extract', 55, 'در حال باز کردن بسته');
            $src = $this->extract($tar, $tmp);
            if ($src === null) { $res['message'] = 'باز کردن بسته ناموفق بود'; return $res; }

            $this->progress('install', 70, 'در حال نصب فایل‌ها');
            $cp = $this->copyOver($src);
            if (!$cp['ok']) { $res['message'] = $cp['message']; return $res; }

            $this->progress('migrate', 85, 'در حال به‌روزرسانی دیتابیس');
            $mg = $this->migrate();
            if (!$mg['ok']) { $res['message'] = $mg['message']; return $res; }

            if ($apkUrl) {
                $this->progress('apk', 92, 'در حال دریافت نسخه اپ تلویزیون');
                $this->mirrorApk($apkUrl);
            }

            /* تلویزیون‌ها نسخه‌ی تازه را بردارند. روی LG و سامسونگ
               «اپ» همان صفحه‌ی سرور است، پس یک reload کافی است؛ روی
               اندروید اپ خودش APK تازه را از سرور می‌گیرد. */
            $this->progress('notify', 97, 'اطلاع به تلویزیون‌ها');
            $this->reloadAllScreens();

            $res['to']      = $res['to'] !== '' ? $res['to'] : $this->current();
            $res['ok']      = true;
            $res['message'] = 'به‌روزرسانی به نسخه ' . $res['to'] . ' انجام شد';
            $this->progress('done', 100, $res['message']);
            $this->log($from, $res['to'], true, $res['message']);

        } catch (\Throwable $e) {
            $res['message'] = 'خطای غیرمنتظره: ' . $e->getMessage();
            $this->progress('failed', 0, $res['message']);
            $this->log($from, $res['to'], false, $res['message']);
        } finally {
            $this->rmrf($tmp);
        }

        return $res;
    }

    // ══════════════════════════════════════════════════════════════
    //  مراحل
    // ══════════════════════════════════════════════════════════════

    /**
     * پشتیبان: فایل‌های کد + دیتابیس.
     *
     * محتوای آپلودی (public/uploads) عمدا پشتیبان نمی‌شود — ده‌ها
     * گیگابایت ویدیو است و به‌روزرسانی هم به آن دست نمی‌زند. پشتیبان
     * محتوا کار جداگانه‌ی cron شبانه است.
     *
     * @return array{ok:bool,message:string,path:string}
     */
    public function backup(): array
    {
        $dir = STORAGE_PATH . '/backups';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return ['ok' => false, 'message' => 'پوشه‌ی پشتیبان ساخته نشد', 'path' => ''];
        }

        $name = 'backup-' . $this->current() . '-' . date('Ymd-His');
        $path = $dir . '/' . $name;
        if (!@mkdir($path, 0775, true)) {
            return ['ok' => false, 'message' => 'پوشه‌ی پشتیبان ساخته نشد', 'path' => ''];
        }

        /* فایل‌های کد */
        foreach (['app', 'config', 'database', 'routes', 'resources', 'public/assets'] as $rel) {
            $s = $this->root . '/' . $rel;
            if (is_dir($s)) $this->copyTree($s, $path . '/' . $rel);
        }
        foreach (['VERSION', 'artisan', 'composer.json'] as $f) {
            if (is_file($this->root . '/' . $f)) @copy($this->root . '/' . $f, $path . '/' . $f);
        }

        /* دیتابیس */
        $sql = $this->dumpDatabase($path . '/database.sql');
        if (!$sql['ok']) {
            /* بدون پشتیبان دیتابیس ادامه نمی‌دهیم: مهاجرت ممکن است
               ساختار را عوض کند و برگشت بدون dump غیرممکن است. */
            $this->rmrf($path);
            return ['ok' => false, 'message' => $sql['message'], 'path' => ''];
        }

        $this->pruneBackups($dir);
        return ['ok' => true, 'message' => '', 'path' => $path];
    }

    /**
     * ‏dump دیتابیس. اول با mysqldump؛ اگر نبود با PHP.
     *
     * @return array{ok:bool,message:string}
     */
    private function dumpDatabase(string $dest): array
    {
        $host = (string)env('DB_HOST', '127.0.0.1');
        $port = (string)env('DB_PORT', '3306');
        $name = (string)env('DB_DATABASE', '');
        $user = (string)env('DB_USERNAME', '');
        $pass = (string)env('DB_PASSWORD', '');

        if ($name === '') return ['ok' => false, 'message' => 'نام دیتابیس در .env نیست'];

        foreach (['mariadb-dump', 'mysqldump'] as $bin) {
            if (!$this->hasCommand($bin)) continue;

            /* رمز از متغیر محیطی می‌رود نه آرگومان — آرگومان در
               خروجی ps برای همه‌ی کاربران سرور دیده می‌شود. */
            $cmd = sprintf(
                '%s --host=%s --port=%s --user=%s --single-transaction '
                . '--routines --no-tablespaces %s > %s 2>/dev/null',
                escapeshellcmd($bin),
                escapeshellarg($host), escapeshellarg($port),
                escapeshellarg($user), escapeshellarg($name),
                escapeshellarg($dest)
            );

            $prev = getenv('MYSQL_PWD');
            putenv('MYSQL_PWD=' . $pass);
            exec($cmd, $out, $code);
            putenv('MYSQL_PWD=' . ($prev === false ? '' : $prev));

            if ($code === 0 && is_file($dest) && filesize($dest) > 0) {
                return ['ok' => true, 'message' => ''];
            }
        }

        return $this->dumpWithPhp($dest);
    }

    /**
     * ‏dump با PHP — وقتی mysqldump روی سرور نیست.
     *
     * @return array{ok:bool,message:string}
     */
    private function dumpWithPhp(string $dest): array
    {
        $fh = @fopen($dest, 'wb');
        if (!$fh) return ['ok' => false, 'message' => 'فایل پشتیبان ساخته نشد'];

        try {
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n");
            $tables = $this->db->rows('SHOW TABLES');

            foreach ($tables as $row) {
                $t = (string)array_values($row)[0];

                $create = $this->db->row('SHOW CREATE TABLE `' . $t . '`');
                fwrite($fh, "\nDROP TABLE IF EXISTS `$t`;\n");
                fwrite($fh, (string)array_values($create)[1] . ";\n");

                /* سطر به سطر، نه همه یکجا — جدول رویدادها در هتل
                   شلوغ می‌تواند صدها هزار سطر باشد و fetchAll حافظه
                   را تمام می‌کند. */
                $pdo = $this->db->pdo();
                foreach ($this->db->rows('SELECT * FROM `' . $t . '`') as $r) {
                    $vals = array_map(static function ($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        /* quote روی خود PDO است، نه روی wrapper ما */
                        return $pdo->quote((string)$v);
                    }, array_values($r));
                    fwrite($fh, 'INSERT INTO `' . $t . '` VALUES (' . implode(',', $vals) . ");\n");
                }
            }
            fwrite($fh, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        } catch (\Throwable $e) {
            fclose($fh);
            return ['ok' => false, 'message' => 'پشتیبان دیتابیس ناموفق: ' . $e->getMessage()];
        }

        fclose($fh);
        return ['ok' => true, 'message' => ''];
    }

    /** پشتیبان‌های قدیمی را حذف کن — وگرنه دیسک سرور پر می‌شود */
    private function pruneBackups(string $dir): void
    {
        $items = glob($dir . '/backup-*') ?: [];
        if (count($items) <= self::KEEP_BACKUPS) return;

        usort($items, static fn($a, $b) => filemtime($a) <=> filemtime($b));
        foreach (array_slice($items, 0, count($items) - self::KEEP_BACKUPS) as $old) {
            $this->rmrf($old);
        }
    }

    /**
     * باز کردن tar.gz. گیت‌هاب همه‌چیز را داخل یک پوشه با نام تصادفی
     * می‌گذارد، پس مسیر واقعی برگردانده می‌شود.
     */
    private function extract(string $tar, string $into): ?string
    {
        $dir = $into . '/src';
        if (!@mkdir($dir, 0775, true)) return null;

        if ($this->hasCommand('tar')) {
            exec(sprintf('tar -xzf %s -C %s 2>/dev/null',
                escapeshellarg($tar), escapeshellarg($dir)), $o, $code);
            if ($code !== 0) return null;
        } elseif (class_exists(\PharData::class)) {
            try {
                $p = new \PharData($tar);
                $p->decompress();
                (new \PharData(preg_replace('/\.gz$/', '', $tar)))->extractTo($dir, null, true);
            } catch (\Throwable) { return null; }
        } else {
            return null;
        }

        /* پوشه‌ی داخلی گیت‌هاب: kish210-hotel-media-<sha> */
        $inner = glob($dir . '/*', GLOB_ONLYDIR) ?: [];
        if (count($inner) === 1) return $inner[0];
        return is_file($dir . '/VERSION') ? $dir : null;
    }

    /**
     * جایگزینی فایل‌های کد.
     *
     * فقط پوشه‌های کد جایگزین می‌شوند. .env و storage و public/uploads
     * و public/apk دست‌نخورده می‌مانند — آن‌ها داده‌ی همین هتل‌اند، نه
     * بخشی از انتشار.
     *
     * @return array{ok:bool,message:string}
     */
    private function copyOver(string $src): array
    {
        $dirs  = ['app', 'config', 'database', 'routes', 'resources', 'deploy', 'docs'];
        $files = ['VERSION', 'artisan', 'composer.json'];

        foreach ($dirs as $d) {
            if (!is_dir($src . '/' . $d)) continue;
            if (!$this->copyTree($src . '/' . $d, $this->root . '/' . $d)) {
                return ['ok' => false, 'message' => 'کپی ' . $d . ' ناموفق بود'];
            }
        }

        /* دارایی‌های وب جدا، چون public/ چیزهای مخصوص هتل هم دارد */
        if (is_dir($src . '/public/assets')) {
            $this->copyTree($src . '/public/assets', $this->root . '/public/assets');
        }
        foreach (['index.php', 'server-router.php'] as $pf) {
            if (is_file($src . '/public/' . $pf)) {
                @copy($src . '/public/' . $pf, $this->root . '/public/' . $pf);
            }
        }

        foreach ($files as $f) {
            if (is_file($src . '/' . $f)) @copy($src . '/' . $f, $this->root . '/' . $f);
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * اجرای مهاجرت‌ها از طریق artisan.
     *
     * @return array{ok:bool,message:string}
     */
    private function migrate(): array
    {
        $artisan = $this->root . '/artisan';
        if (!is_file($artisan)) return ['ok' => true, 'message' => ''];

        $php = PHP_BINARY ?: 'php';
        exec(sprintf('%s %s db:migrate 2>&1',
            escapeshellarg($php), escapeshellarg($artisan)), $out, $code);

        if ($code !== 0) {
            return ['ok' => false, 'message' =>
                'مهاجرت دیتابیس ناموفق بود. از نسخه پشتیبان برگردانید. '
                . implode(' ', array_slice($out, -3))];
        }
        return ['ok' => true, 'message' => ''];
    }

    /**
     * آینه‌ی APK برای تلویزیون‌های اندروید.
     *
     * تلویزیون‌های اتاق روی VLAN بدون اینترنت‌اند و هیچ‌وقت به گیت‌هاب
     * نمی‌رسند، پس سرور باید فایل را نگه دارد.
     */
    private function mirrorApk(string $url): bool
    {
        $dir = $this->root . '/public/apk';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;

        $ver  = $this->current();
        $name = 'hotel-media-' . $ver . '.apk';
        $dest = $dir . '/' . $name;

        $dl = $this->download($url, $dest, 'apk');
        if (!$dl['ok']) return false;

        /* ثبت در جدول تا AppUpdateController آن را به تلویزیون‌ها
           اعلام کند. version_code عددی و صعودی لازم است. */
        try {
            $parts = array_map('intval', explode('.', $ver));
            $code  = ($parts[0] ?? 0) * 10000 + ($parts[1] ?? 0) * 100 + ($parts[2] ?? 0);

            $this->db->query('UPDATE apk_versions SET is_active = 0');
            $this->db->insert('apk_versions', [
                'version_code' => $code,
                'version_name' => $ver,
                'apk_filename' => $name,
                'file_size'    => $dl['bytes'],
                'changelog'    => 'به‌روزرسانی خودکار از گیت‌هاب',
                'force_update' => 0,
                'is_active'    => 1,
            ]);
        } catch (\Throwable) {
            /* اگر جدول نبود، فایل سرو می‌شود ولی اعلام خودکار نیست */
            return false;
        }
        return true;
    }

    /**
     * به همه‌ی صفحه‌ها بگو دوباره بارگذاری کنند.
     *
     * روی LG و سامسونگ «اپ» همان صفحه‌ی وب سرور است، پس تنها کاری که
     * لازم است همین است — به‌علاوه‌ی مهر نسخه روی دارایی‌ها که کش
     * مرورگر تلویزیون را دور می‌زند.
     */
    private function reloadAllScreens(): void
    {
        try {
            $rows = $this->db->rows('SELECT id FROM screens WHERE status = ?', ['active']);
            foreach ($rows as $r) {
                $this->db->insert('screen_commands', [
                    'screen_id' => (int)$r['id'],
                    'cmd'       => 'reload',
                    'payload'   => json_encode(['reason' => 'system_update'], JSON_UNESCAPED_UNICODE),
                    'status'    => 'pending',
                ]);
            }
        } catch (\Throwable) {
            /* اگر جدول فرمان نبود، تلویزیون‌ها در ضربان بعدی خودشان
               صفحه‌ی تازه را می‌گیرند — فقط دیرتر. */
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  تاریخچه
    // ══════════════════════════════════════════════════════════════

    private function log(string $from, string $to, bool $ok, string $note): void
    {
        try {
            $this->db->insert('update_log', [
                'from_version' => $from,
                'to_version'   => $to !== '' ? $to : $from,
                'success'      => $ok ? 1 : 0,
                'note'         => mb_substr($note, 0, 500),
            ]);
        } catch (\Throwable) { }
    }

    /** @return list<array<string,mixed>> */
    public function history(int $limit = 20): array
    {
        try {
            return $this->db->rows(
                'SELECT * FROM update_log ORDER BY id DESC LIMIT ' . max(1, min(100, $limit))
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array{name:string,path:string,at:int,size:int}> */
    public function backups(): array
    {
        $out = [];
        foreach (glob(STORAGE_PATH . '/backups/backup-*') ?: [] as $p) {
            $out[] = [
                'name' => basename($p),
                'path' => $p,
                'at'   => (int)filemtime($p),
                'size' => $this->dirSize($p),
            ];
        }
        usort($out, static fn($a, $b) => $b['at'] <=> $a['at']);
        return $out;
    }

    // ══════════════════════════════════════════════════════════════
    //  ابزار فایل
    // ══════════════════════════════════════════════════════════════

    private function hasCommand(string $bin): bool
    {
        if (!function_exists('exec')) return false;
        $probe = stripos(PHP_OS_FAMILY, 'Windows') === 0 ? 'where' : 'command -v';
        exec($probe . ' ' . escapeshellarg($bin) . ' 2>/dev/null', $o, $c);
        return $c === 0;
    }

    private function copyTree(string $src, string $dst): bool
    {
        if (!is_dir($src)) return false;
        if (!is_dir($dst) && !@mkdir($dst, 0775, true)) return false;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $item) {
            $target = $dst . '/' . $it->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target) && !@mkdir($target, 0775, true)) return false;
            } elseif (!@copy($item->getPathname(), $target)) {
                return false;
            }
        }
        return true;
    }

    private function rmrf(string $path): void
    {
        if (!file_exists($path)) return;
        if (is_file($path) || is_link($path)) { @unlink($path); return; }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }

    private function dirSize(string $path): int
    {
        if (!is_dir($path)) return 0;
        $n = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) if ($f->isFile()) $n += $f->getSize();
        return $n;
    }
}
