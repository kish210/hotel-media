<?php
/**
 * HotelMedia — first-run welcome page (Persian).
 * Shown after the native installer finishes. Displays the default login so the
 * user never has to see credentials inside the installer itself.
 *
 * Served directly by the PHP built-in server (it's a real file under public/).
 */
declare(strict_types=1);

// ── read a few values from .env (best-effort) ────────────────────────────────
$appName = 'هتل مدیا';
$appUrl  = 'http://localhost';
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v, " \t\"'");
        if ($k === 'APP_NAME' && $v !== '') $appName = $v;
        if ($k === 'APP_URL'  && $v !== '') $appUrl  = $v;
        if ($k === 'ADMIN_EMAIL'    && $v !== '') $adminEmail = $v;
        if ($k === 'ADMIN_PASSWORD' && $v !== '') $adminPass  = $v;
    }
}
$adminEmail = $adminEmail ?? 'admin@hotelmedia.com';
$adminPass  = $adminPass  ?? 'Admin@123456';
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>خوش آمدید — <?= $e($appName) ?></title>
<style>
  :root { --brand:#1e88e5; --brand2:#0d47a1; --ok:#2e7d32; --bg:#0f172a; --card:#fff; --ink:#1f2937; --muted:#6b7280; }
  * { box-sizing: border-box; }
  body {
    margin:0; min-height:100vh; font-family:"Segoe UI",Tahoma,"Iranian Sans",sans-serif;
    background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%);
    display:flex; align-items:center; justify-content:center; padding:24px; color:var(--ink);
  }
  .card {
    background:var(--card); width:100%; max-width:560px; border-radius:20px;
    box-shadow:0 24px 60px rgba(0,0,0,.35); overflow:hidden;
  }
  .head { background:linear-gradient(135deg,var(--brand),var(--brand2)); color:#fff; padding:34px 28px; text-align:center; }
  .head .logo { width:72px;height:72px;border-radius:18px;background:rgba(255,255,255,.15);
    display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:38px; }
  .head h1 { margin:0; font-size:26px; }
  .head p { margin:8px 0 0; opacity:.9; font-size:15px; }
  .body { padding:28px; }
  .ok { display:flex; align-items:center; gap:10px; color:var(--ok); font-weight:700; font-size:18px; margin-bottom:18px; }
  .ok .dot { width:12px;height:12px;border-radius:50%;background:var(--ok); box-shadow:0 0 0 4px rgba(46,125,50,.15); }
  .creds { background:#f1f5f9; border:1px solid #e2e8f0; border-radius:14px; padding:18px; }
  .creds h3 { margin:0 0 12px; font-size:15px; color:var(--muted); font-weight:600; }
  .row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px dashed #d8dee9; }
  .row:last-child { border-bottom:0; }
  .row .lbl { color:var(--muted); font-size:14px; }
  .row .val { font-family:Consolas,monospace; font-weight:700; font-size:16px; direction:ltr; }
  .copy { border:0;background:var(--brand);color:#fff;border-radius:8px;padding:4px 10px;cursor:pointer;font-size:12px;margin-inline-start:8px; }
  .btn { display:block; text-align:center; text-decoration:none; margin-top:22px;
    background:linear-gradient(135deg,var(--brand),var(--brand2)); color:#fff; padding:16px;
    border-radius:14px; font-size:18px; font-weight:700; }
  .note { margin-top:16px; font-size:13px; color:var(--muted); line-height:1.9; }
  .foot { text-align:center; color:#94a3b8; font-size:12px; padding:14px; }
</style>
</head>
<body>
  <div class="card">
    <div class="head">
      <div class="logo">🏨</div>
      <h1><?= $e($appName) ?></h1>
      <p>نصب با موفقیت انجام شد</p>
    </div>
    <div class="body">
      <div class="ok"><span class="dot"></span> برنامه آماده‌ی استفاده است</div>

      <div class="creds">
        <h3>اطلاعات ورود به پنل مدیریت</h3>
        <div class="row">
          <span class="lbl">نام کاربری</span>
          <span><span class="val" id="u"><?= $e($adminEmail) ?></span>
            <button class="copy" onclick="cp('u')">کپی</button></span>
        </div>
        <div class="row">
          <span class="lbl">رمز عبور</span>
          <span><span class="val" id="p"><?= $e($adminPass) ?></span>
            <button class="copy" onclick="cp('p')">کپی</button></span>
        </div>
      </div>

      <a class="btn" href="<?= $e($appUrl) ?>/admin">ورود به پنل مدیریت ←</a>

      <div class="note">
        🔒 لطفاً پس از اولین ورود، رمز عبور را از بخش تنظیمات تغییر دهید.<br>
        💡 این برنامه با روشن شدن ویندوز به‌صورت خودکار اجرا می‌شود؛ برای باز کردن دوباره،
        از آیکون «هتل مدیا» روی دسکتاپ یا کنار ساعت ویندوز استفاده کنید.
      </div>
    </div>
    <div class="foot">سماع رایانه کیش | kishwifi.com</div>
  </div>
<script>
  function cp(id){
    var t=document.getElementById(id).innerText;
    navigator.clipboard && navigator.clipboard.writeText(t);
    event.target.innerText='کپی شد';
    setTimeout(function(){event.target.innerText='کپی';},1200);
  }
</script>
</body>
</html>
