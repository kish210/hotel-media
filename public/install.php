<?php
declare(strict_types=1);
/**
 * SignageCMS Installer v5
 * ========================
 * • اگر دیتابیس وجود نداشت → می‌سازه
 * • اگر جداول نبودن → می‌سازه
 * • اگر connection fail شد → فرم تنظیمات نشون میده
 *
 * دسترسی:   http://localhost/install.php
 * CLI:        php public/install.php
 *
 * بعد از نصب حذف کنید:
 *   docker exec signage_php rm /var/www/html/public/install.php
 */

define('INSTALLER_VER', '5.0');
define('ROOT', dirname(__DIR__));

// ── CLI ──────────────────────────────────────────────────────────────────────
if (php_sapi_name() === 'cli') { runCli(); exit(0); }

// ── Web ──────────────────────────────────────────────────────────────────────
session_start();

$cfg    = readEnv();
$errors = [];
$log    = [];

// POST: مرحله ۱ — ذخیره config و تست
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_cfg') {
    foreach (['DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD'] as $k) {
        if (isset($_POST[$k])) $cfg[$k] = trim($_POST[$k]);
    }
    $_SESSION['install_cfg'] = $cfg;

    $r = connectMysql($cfg);
    if ($r['ok']) {
        // ذخیره در .env و نصب فوری
        updateEnvFile($cfg);
        $log = doInstall($r['pdo'], $cfg);
        renderResult($cfg, $log);
        exit;
    } else {
        $errors[] = $r['error'];
    }
}

// GET بدون POST: اول تلاش خودکار با تنظیمات موجود
if (empty($_SESSION['install_cfg'])) {
    $r = connectMysql($cfg);
    if ($r['ok']) {
        $log = doInstall($r['pdo'], $cfg);
        renderResult($cfg, $log);
        exit;
    }
    // اتصال fail شد → نمایش فرم
    $errors[] = 'اتصال خودکار ناموفق: ' . $r['error'];
}

if (isset($_SESSION['install_cfg'])) $cfg = $_SESSION['install_cfg'];

renderForm($cfg, $errors);
exit;

// ════════════════════════════════════════════════════════════════════════════
// CONNECT — بدون dbname تا DB بتونه وجود نداشته باشه
// ════════════════════════════════════════════════════════════════════════════
function connectMysql(array $cfg): array
{
    try {
        // اتصال بدون نام دیتابیس
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4',
            $cfg['DB_HOST'], $cfg['DB_PORT'] ?? '3306');
        $pdo = new PDO($dsn, $cfg['DB_USERNAME'], $cfg['DB_PASSWORD'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]);
        return ['ok' => true, 'pdo' => $pdo];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ════════════════════════════════════════════════════════════════════════════
// MAIN INSTALL
// ════════════════════════════════════════════════════════════════════════════
function doInstall(PDO $pdo, array $cfg): array
{
    $log = [];
    $db  = $cfg['DB_DATABASE'];

    // ── 1. ایجاد دیتابیس ─────────────────────────────────────────────────
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}`
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$db}`");
        addLog($log, 'ok', "دیتابیس «{$db}» آماده است");
    } catch (PDOException $e) {
        addLog($log, 'err', 'خطا در ساخت دیتابیس: ' . $e->getMessage());
        return $log;
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS=0; SET sql_mode='';");

    // ── 2. اجرای فایل‌های migration ──────────────────────────────────────
    $migrations = [
        '001_complete_schema.sql',
        '003_module_tables_only.sql',
        '004_iptv_channels.sql',
        '005_apk_versions.sql',
        '006_screen_groups.sql',
        '007_vod_tables.sql',
        '008_iptv_menus.sql',
        '009_iptv_menu_appearance.sql',
        '010_iptv_rooms.sql',
        '011_inflight.sql',
        '012_inflight_rpi.sql',
        '013_screen_type_inflight.sql',
    ];

    foreach ($migrations as $mf) {
        $path = ROOT . '/database/migrations/' . $mf;
        if (!file_exists($path)) {
            addLog($log, 'skip', "فایل migration نیست: {$mf}");
            continue;
        }
        runSqlFile($pdo, $path, $mf, $log);
    }

    // ── 3. ستون‌های اضافی (upgrade safe) ─────────────────────────────────
    $extraCols = [
        ['screens', 'screen_type',         "ENUM('signage','iptv') NOT NULL DEFAULT 'signage'"],
        ['screens', 'group_id',             "INT UNSIGNED DEFAULT NULL"],
        ['screens', 'iptv_channel_id',      "INT UNSIGNED DEFAULT NULL"],
        ['screens', 'iptv_settings',        "JSON DEFAULT NULL"],
        ['screens', 'pending_commands',     "JSON DEFAULT NULL"],
        ['screens', 'emergency_broadcast',  "TEXT DEFAULT NULL"],
        ['screens', 'iptv_menu_id',         "INT UNSIGNED DEFAULT NULL"],
        ['playlists','default_duration',    "SMALLINT UNSIGNED NOT NULL DEFAULT 10"],
        ['playlists','transition',          "VARCHAR(20) DEFAULT 'fade'"],
    ];
    foreach ($extraCols as [$tbl, $col, $def]) {
        addColIfMissing($pdo, $tbl, $col, $def, $log);
    }

    // ── 4. INDEX‌های اضافی ──────────────────────────────────────────────
    addIndexIfMissing($pdo, 'screens',   'idx_screen_type', 'screen_type', $log);
    addIndexIfMissing($pdo, 'screens',   'idx_group_id',    'group_id',    $log);
    addIndexIfMissing($pdo, 'fids_flights','idx_fids_gate', 'gate',        $log);

    // ── 5. Tenant پیش‌فرض ───────────────────────────────────────────────
    try {
        $pdo->exec("INSERT IGNORE INTO `tenants`
            (id,slug,name,plan,storage_limit,screen_limit,is_active)
            VALUES (1,'main','SignageCMS','pro',53687091200,50,1)");
        addLog($log, 'ok', 'Tenant پیش‌فرض آماده است');
    } catch (PDOException $e) {
        addLog($log, 'warn', 'Tenant: ' . $e->getMessage());
    }

    // ── 6. Admin user ────────────────────────────────────────────────────
    try {
        $exists = $pdo->query(
            "SELECT COUNT(*) FROM `users` WHERE email='admin@signagecms.com'"
        )->fetchColumn();
        if (!$exists) {
            $hash = password_hash('Admin@123456', PASSWORD_BCRYPT, ['cost' => 12]);
            $st   = $pdo->prepare(
                "INSERT INTO `users`
                 (tenant_id,name,email,password,role,language,is_active)
                 VALUES (1,'مدیر سیستم','admin@signagecms.com',?,'super_admin','fa',1)"
            );
            $st->execute([$hash]);
            addLog($log, 'ok', 'Admin user ساخته شد  (Admin@123456)');
        } else {
            addLog($log, 'skip', 'Admin user از قبل وجود دارد');
        }
    } catch (PDOException $e) {
        addLog($log, 'warn', 'Admin user: ' . $e->getMessage());
    }

    // ── 7. Module records ────────────────────────────────────────────────
    try {
        $pdo->exec("INSERT IGNORE INTO `modules`
            (id,tenant_id,name,version,is_active) VALUES
            ('fids',      1,'سامانه اطلاع‌رسانی پرواز (FIDS)','1.2.0',1),
            ('hotel',     1,'اطلاع‌رسانی هتل','1.1.0',1),
            ('menu',      1,'منوی رستوران','2.0.0',1),
            ('transport', 1,'حمل‌ونقل عمومی','1.0.0',0),
            ('retail',    1,'فروشگاه و خرده‌فروشی','1.0.0',0),
            ('corporate', 1,'اطلاع‌رسانی سازمانی','1.0.0',0)");
        addLog($log, 'ok', 'ماژول‌های پیش‌فرض ثبت شدند');
    } catch (PDOException $e) {
        addLog($log, 'warn', 'Modules: ' . $e->getMessage());
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    addLog($log, 'ok', '─────── نصب کامل شد ───────');
    return $log;
}

// ── SQL file runner ──────────────────────────────────────────────────────────
function runSqlFile(PDO $pdo, string $path, string $label, array &$log): void
{
    $sql = file_get_contents($path);
    // حذف کامنت‌ها
    $sql = preg_replace('/--[^\n]*/', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    // تبدیل به آرایه statement
    $buf = ''; $stmts = [];
    foreach (explode("\n", $sql) as $line) {
        $line = trim($line);
        if (!$line) continue;
        $buf .= ' ' . $line;
        if (str_ends_with(rtrim($buf), ';')) {
            $s = trim(rtrim($buf, '; '));
            if (strlen($s) > 4) $stmts[] = $s;
            $buf = '';
        }
    }
    if (trim($buf) && strlen(trim($buf)) > 4) $stmts[] = trim($buf, '; ');

    $ok = $skip = $fail = 0;
    foreach ($stmts as $stmt) {
        try {
            $st = $pdo->prepare($stmt);
            $st->execute();
            try { do { $st->fetchAll(); } while ($st->nextRowset()); } catch (\Throwable $e) {}
            $st->closeCursor();
            $ok++;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            $ignored = ['already exists', 'Duplicate column', 'Duplicate entry',
                        "Can't DROP", 'Multiple primary', '1060', '1061', '1062', '1091'];
            $isIgnored = false;
            foreach ($ignored as $ig) {
                if (stripos($msg, $ig) !== false) { $isIgnored = true; break; }
            }
            $isIgnored ? $skip++ : $fail++;
            if (!$isIgnored && $fail <= 3) {
                addLog($log, 'warn', "SQL warn in {$label}: " . substr($stmt, 0, 60) . ' → ' . substr($msg, 0, 80));
            }
        }
    }
    $icon = $fail > 0 ? 'warn' : 'ok';
    addLog($log, $icon,
        "{$label} — {$ok} اجرا" .
        ($skip ? " · {$skip} موجود" : '') .
        ($fail ? " · ❌ {$fail} خطا" : ''));
}

// ── Column helpers ───────────────────────────────────────────────────────────
function addColIfMissing(PDO $pdo, string $tbl, string $col, string $def, array &$log): void
{
    try {
        $n = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$tbl}' AND COLUMN_NAME='{$col}'")->fetchColumn();
        if ($n) return; // already exists — silent
        $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `{$col}` {$def}");
        addLog($log, 'ok', "ستون {$tbl}.{$col} اضافه شد");
    } catch (\Throwable $e) {
        if (!str_contains($e->getMessage(), 'Duplicate column')) {
            addLog($log, 'warn', "ستون {$tbl}.{$col}: " . $e->getMessage());
        }
    }
}

function addIndexIfMissing(PDO $pdo, string $tbl, string $idx, string $col, array &$log): void
{
    try {
        $n = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$tbl}' AND INDEX_NAME='{$idx}'")->fetchColumn();
        if ($n) return;
        $pdo->exec("ALTER TABLE `{$tbl}` ADD INDEX `{$idx}` (`{$col}`)");
    } catch (\Throwable $e) {}
}

// ── Log helper ───────────────────────────────────────────────────────────────
function addLog(array &$log, string $type, string $msg): void
{
    $log[] = [$type, $msg];
}

// ── .env helpers ─────────────────────────────────────────────────────────────
function readEnv(): array
{
    $cfg = [
        'DB_HOST'     => 'mysql',
        'DB_PORT'     => '3306',
        'DB_DATABASE' => 'signage_cms',
        'DB_USERNAME' => 'signage_user',
        'DB_PASSWORD' => 'StrongPassword123!',
    ];
    $envFile = ROOT . '/.env';
    if (!file_exists($envFile)) return $cfg;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $cfg[trim($k)] = trim($v, '"\'');
    }
    return $cfg;
}

function updateEnvFile(array $cfg): void
{
    $envFile = ROOT . '/.env';
    if (!file_exists($envFile)) return;
    $content = file_get_contents($envFile);
    foreach (['DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD'] as $k) {
        if (!isset($cfg[$k])) continue;
        $content = preg_replace("/^{$k}=.*/m", "{$k}={$cfg[$k]}", $content);
    }
    file_put_contents($envFile, $content);
}

// ════════════════════════════════════════════════════════════════════════════
// CLI MODE
// ════════════════════════════════════════════════════════════════════════════
function runCli(): void
{
    $cfg = readEnv();
    echo "\n╔══════════════════════════════════════╗\n";
    echo "║  SignageCMS Installer v" . INSTALLER_VER . "          ║\n";
    echo "╚══════════════════════════════════════╝\n\n";
    echo "  DB_HOST     = {$cfg['DB_HOST']}\n";
    echo "  DB_DATABASE = {$cfg['DB_DATABASE']}\n";
    echo "  DB_USERNAME = {$cfg['DB_USERNAME']}\n\n";

    $r = connectMysql($cfg);
    if (!$r['ok']) {
        echo "❌  اتصال ناموفق: {$r['error']}\n\n";
        echo "  تنظیمات DB_HOST / DB_USERNAME / DB_PASSWORD رو در .env چک کنید\n\n";
        exit(1);
    }
    echo "✅  اتصال MySQL برقرار شد\n\n";

    $log = doInstall($r['pdo'], $cfg);
    updateEnvFile($cfg);

    foreach ($log as [$type, $msg]) {
        $icon = match($type) { 'ok'=>'✅', 'warn'=>'⚠ ', 'skip'=>'⏭ ', 'err'=>'❌', default=>'  ' };
        echo "  $icon  $msg\n";
    }

    $hasError = !empty(array_filter($log, fn($l) => $l[0] === 'err'));
    echo "\n";
    if ($hasError) {
        echo "❌  نصب با خطا مواجه شد.\n\n"; exit(1);
    }
    echo "✅  نصب کامل!\n";
    echo "   ایمیل: admin@signagecms.com\n";
    echo "   رمز:   Admin@123456\n\n";
    echo "⚠  فایل public/install.php را حذف کنید\n\n";
}

// ════════════════════════════════════════════════════════════════════════════
// HTML VIEWS
// ════════════════════════════════════════════════════════════════════════════
function renderForm(array $cfg, array $errors): void
{
    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SignageCMS — نصب</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Tahoma,sans-serif;background:#09090f;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px}
.card{background:#16161f;border:1px solid rgba(255,255,255,.08);border-radius:16px;width:480px;max-width:100%;overflow:hidden;box-shadow:0 30px 60px rgba(0,0,0,.6)}
.hd{background:linear-gradient(135deg,#1d4ed8,#7c3aed);padding:28px 32px}
.hd h1{font-size:1.4rem;font-weight:800}.hd p{opacity:.75;font-size:.82rem;margin-top:4px}
.bd{padding:28px 32px}
h2{font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:20px}
.fld{margin-bottom:14px}
label{display:block;font-size:.78rem;color:#94a3b8;margin-bottom:5px}
input{width:100%;padding:10px 14px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:.88rem;outline:none;font-family:inherit;direction:ltr}
input:focus{border-color:#3b82f6}
.row2{display:grid;grid-template-columns:2fr 1fr;gap:10px}
.btn{width:100%;padding:12px;background:linear-gradient(135deg,#f97316,#c2570b);color:#fff;border:none;border-radius:8px;font-size:.95rem;cursor:pointer;font-family:inherit;font-weight:700;margin-top:20px}
.btn:hover{opacity:.9}
.errs{background:#450a0a;border:1px solid #991b1b;border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:.82rem}
.errs li{list-style:none;padding:2px 0;color:#fca5a5}
.note{font-size:.75rem;color:#475569;margin-top:16px;text-align:center;line-height:1.8}
code{background:#0f172a;padding:2px 6px;border-radius:4px;font-family:monospace;color:#f97316;font-size:.75rem}
</style>
</head>
<body>
<div class="card">
<div class="hd"><h1>🖥️ SignageCMS Installer</h1><p>v<?= INSTALLER_VER ?> — راه‌اندازی اولیه سیستم</p></div>
<div class="bd">
<h2>🔌 تنظیمات اتصال MySQL</h2>
<?php if (!empty($errors)): ?>
<ul class="errs"><?php foreach ($errors as $e) echo "<li>⚠ " . htmlspecialchars($e) . "</li>"; ?></ul>
<?php endif ?>
<form method="POST">
<input type="hidden" name="action" value="save_cfg">
<div class="row2">
  <div class="fld"><label>MySQL Host</label>
    <input name="DB_HOST" value="<?= htmlspecialchars($cfg['DB_HOST']) ?>" placeholder="mysql یا 127.0.0.1">
  </div>
  <div class="fld"><label>Port</label>
    <input name="DB_PORT" value="<?= htmlspecialchars($cfg['DB_PORT'] ?? '3306') ?>">
  </div>
</div>
<div class="fld"><label>نام دیتابیس</label>
  <input name="DB_DATABASE" value="<?= htmlspecialchars($cfg['DB_DATABASE']) ?>" placeholder="signage_cms">
</div>
<div class="fld"><label>نام کاربری MySQL</label>
  <input name="DB_USERNAME" value="<?= htmlspecialchars($cfg['DB_USERNAME']) ?>">
</div>
<div class="fld"><label>رمز عبور MySQL</label>
  <input type="password" name="DB_PASSWORD" placeholder="رمز عبور MySQL">
</div>
<button class="btn" type="submit">▶ اتصال و نصب</button>
</form>
<p class="note">
  مقادیر از فایل <code>.env</code> بارگذاری شدند<br>
  کاربر پیش‌فرض بعد از نصب: <code>admin@signagecms.com</code> / <code>Admin@123456</code>
</p>
</div>
</div>
</body></html>
<?php
}

function renderResult(array $cfg, array $log): void
{
    header('Content-Type: text/html; charset=utf-8');
    $hasError = !empty(array_filter($log, fn($l) => $l[0] === 'err'));
    $icon     = $hasError ? '❌' : '✅';
    $title    = $hasError ? 'خطا در نصب' : 'نصب موفق!';
    ?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SignageCMS — <?= $title ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Tahoma,sans-serif;background:#09090f;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px}
.card{background:#16161f;border:1px solid rgba(255,255,255,.08);border-radius:16px;width:560px;max-width:100%;overflow:hidden;box-shadow:0 30px 60px rgba(0,0,0,.6)}
.hd{padding:28px 32px;background:<?= $hasError ? 'linear-gradient(135deg,#7f1d1d,#991b1b)' : 'linear-gradient(135deg,#14532d,#15803d)' ?>}
.hd h1{font-size:1.4rem;font-weight:800}.hd p{opacity:.8;font-size:.82rem;margin-top:4px}
.bd{padding:24px 32px}
.log{background:#0f172a;border-radius:10px;padding:14px 16px;font-size:.8rem;line-height:2;max-height:300px;overflow-y:auto}
.row{display:flex;gap:8px;align-items:flex-start}
.ok{color:#86efac}.warn{color:#fcd34d}.err{color:#fca5a5}.skip{color:#475569}
.box{border-radius:10px;padding:16px;margin-top:18px;font-size:.85rem;line-height:1.9}
.box-ok{background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.2);color:#86efac}
.box-err{background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);color:#fca5a5}
.btn{display:block;padding:12px 28px;background:linear-gradient(135deg,#f97316,#c2570b);color:#fff;border:none;border-radius:10px;text-decoration:none;font-weight:700;font-size:.95rem;margin-top:18px;cursor:pointer;text-align:center}
code{background:#111;padding:2px 6px;border-radius:4px;font-family:monospace;color:#f97316;font-size:.78rem}
.del{border-radius:10px;padding:12px;margin-top:14px;font-size:.75rem;color:#f87171;text-align:center;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.18);line-height:2.2}
</style>
</head>
<body>
<div class="card">
<div class="hd">
  <h1><?= $icon ?> <?= $title ?></h1>
  <p>دیتابیس: <?= htmlspecialchars($cfg['DB_DATABASE']) ?> @ <?= htmlspecialchars($cfg['DB_HOST']) ?></p>
</div>
<div class="bd">
  <div class="log">
  <?php foreach ($log as [$type, $msg]): ?>
  <div class="row <?= $type ?>">
    <span><?= $type==='ok'?'✅':($type==='skip'?'⏭':($type==='warn'?'⚠':'❌')) ?></span>
    <span><?= htmlspecialchars($msg) ?></span>
  </div>
  <?php endforeach ?>
  </div>

  <?php if (!$hasError): ?>
  <div class="box box-ok">
    ✅ <strong>سیستم آماده است</strong><br>
    📧 ایمیل: <code>admin@signagecms.com</code><br>
    🔑 رمز: <code>Admin@123456</code><br>
    ⚠ بعد از ورود، رمز را تغییر دهید
  </div>
  <a href="/" class="btn">ورود به داشبورد ←</a>
  <?php else: ?>
  <div class="box box-err">
    ❌ نصب با خطا مواجه شد — پیام‌های بالا را بررسی کنید
  </div>
  <a href="/install.php" class="btn" style="background:linear-gradient(135deg,#1d4ed8,#7c3aed)">← تلاش مجدد</a>
  <?php endif ?>

  <div class="del">
    ⚠ بعد از ورود موفق این فایل را حذف کنید:<br>
    <code>docker exec signage_php rm /var/www/html/public/install.php</code>
  </div>
</div>
</div>
</body></html>
<?php
}
