<!DOCTYPE html>
<html lang="fa" dir="rtl" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ورود به SignageCMS</title>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Vazirmatn',sans-serif;background:#09090f;color:#e2e8f0;direction:rtl;min-height:100vh;display:flex;align-items:center;justify-content:center;}
  .bg-glow{position:fixed;top:0;left:0;right:0;bottom:0;background:radial-gradient(ellipse at 30% 40%,rgba(249,115,22,0.08),transparent 60%),radial-gradient(ellipse at 70% 70%,rgba(59,130,246,0.04),transparent 60%);pointer-events:none;}
  .card{position:relative;background:#111118;border:1px solid rgba(255,255,255,0.08);border-radius:24px;padding:40px;width:100%;max-width:420px;}
  .logo{width:56px;height:56px;background:linear-gradient(135deg,#f97316,#c2570b);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;}
  h1{text-align:center;font-size:22px;font-weight:800;color:#fff;margin-bottom:4px;}
  .sub{text-align:center;font-size:13px;color:#64748b;margin-bottom:32px;}
  label{display:block;font-size:12px;font-weight:600;color:#94a3b8;margin-bottom:6px;}
  input{width:100%;background:#0d0d14;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:11px 14px;font-size:14px;color:#fff;outline:none;transition:border-color .2s;font-family:'Vazirmatn',sans-serif;}
  input:focus{border-color:#f97316;}
  input::placeholder{color:#475569;}
  .field{margin-bottom:18px;}
  .btn{width:100%;background:linear-gradient(135deg,#f97316,#c2570b);color:#fff;padding:13px;border-radius:12px;font-size:15px;font-weight:700;border:none;cursor:pointer;transition:opacity .2s;margin-top:8px;font-family:'Vazirmatn',sans-serif;}
  .btn:hover{opacity:.9;}
  .btn:active{transform:scale(.99);}
  .error{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:11px 14px;font-size:13px;color:#f87171;margin-bottom:20px;display:flex;align-items:center;gap:8px;}
  .hint{background:rgba(59,130,246,.06);border:1px solid rgba(59,130,246,.2);border-radius:12px;padding:11px 14px;font-size:12px;color:#94a3b8;margin-top:20px;text-align:center;line-height:1.6;}
  .hint strong{color:#60a5fa;}
</style>
</head>
<body>
<div class="bg-glow"></div>
<div class="card">
  <div class="logo"><i class="fas fa-tv" style="font-size:24px;color:#fff;"></i></div>
  <h1>SignageCMS</h1>
  <p class="sub">سیستم مدیریت تابلو دیجیتال</p>

  <?php if (!empty($error)): ?>
  <div class="error"><i class="fas fa-circle-xmark"></i><?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="/login">
    <?= csrf_field() ?>
    <div class="field">
      <label>ایمیل</label>
      <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>"
        placeholder="admin@signagecms.com" required autofocus>
    </div>
    <div class="field">
      <label>رمز عبور</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn"><i class="fas fa-right-to-bracket" style="margin-left:8px;"></i>ورود به سیستم</button>
  </form>

  <div class="hint">
    <strong>اطلاعات پیش‌فرض:</strong><br>
    admin@signagecms.com | Admin@123456
  </div>
</div>
</body>
</html>
