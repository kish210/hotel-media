<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ورود به Hotel Media</title>
<?php // همه محلی — سرور هتل روی VLAN بدون اینترنت است. ?>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css">
<link rel="stylesheet" href="/assets/vendor/inter/inter.css">
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="/assets/css/design-system.css">
<style>
  body {
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: var(--s5);
  }
  /* درخشش پس‌زمینه با رنگ برند، نه نارنجی قبلی */
  .bg-glow {
    position: fixed; inset: 0; pointer-events: none;
    background:
      radial-gradient(ellipse at 28% 38%, color-mix(in srgb, var(--brand-500) 12%, transparent), transparent 58%),
      radial-gradient(ellipse at 72% 72%, color-mix(in srgb, var(--brand-700) 10%, transparent), transparent 58%);
  }
  .login-card {
    position: relative;
    background: var(--surface-2);
    border: 1px solid var(--line-1);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-4);
    padding: 40px 36px 32px;
    width: 100%; max-width: 420px;
    animation: sheet var(--t-slow) var(--ease-out) both;
  }
  .login-mark {
    height: 56px; width: auto; display: block; margin: 0 auto 18px;
    border-radius: var(--r-sm);
  }
  /* در تم روشن نشان روی کاشی تیره می‌نشیند تا بخش‌های سفیدش گم نشود */
  :root[data-theme="light"] .login-mark {
    background: var(--brand-900); padding: 6px 9px; box-sizing: content-box;
  }
  .login-card h1 {
    text-align: center; font-size: 21px; font-weight: 700;
    color: var(--text-1); letter-spacing: -.02em; margin: 0 0 4px;
  }
  .login-sub { text-align: center; font-size: 13px; color: var(--text-3); margin: 0 0 28px; }
  .field { margin-bottom: 16px; }
  .btn-login {
    width: 100%; justify-content: center; margin-top: 8px;
    padding: 12px; font-size: 15px; font-weight: 700;
  }
  .login-foot {
    margin-top: 22px; padding-top: 18px;
    border-top: 1px solid var(--line-1);
    text-align: center; font-size: 11.5px; color: var(--text-4);
  }
  .login-foot a { color: var(--text-3); }
  .login-foot a:hover { color: var(--brand-400); }
  @media (max-width: 480px) { .login-card { padding: 32px 22px 26px; } }
</style>
</head>
<body>
<div class="bg-glow"></div>

<main class="login-card">
  <img class="login-mark" src="/assets/img/sama-logo.svg" alt="سماع رایانه کیش">
  <h1>Hotel Media</h1>
  <p class="login-sub">سامانه تلویزیون و تابلو دیجیتال هتل</p>

  <?php if (!empty($error)): ?>
  <div class="alert alert-error"><i class="fas fa-circle-xmark"></i><span><?= e($error) ?></span></div>
  <?php endif; ?>

  <form method="POST" action="/login">
    <?= csrf_field() ?>
    <div class="field">
      <label class="form-label" for="email">ایمیل</label>
      <input class="form-input" id="email" type="email" name="email"
        value="<?= e($old['email'] ?? '') ?>" placeholder="admin@hotelmedia.com"
        autocomplete="username" required autofocus>
    </div>
    <div class="field">
      <label class="form-label" for="password">رمز عبور</label>
      <input class="form-input" id="password" type="password" name="password"
        placeholder="••••••••" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn btn-primary btn-login">
      <i class="fas fa-right-to-bracket"></i> ورود به سیستم
    </button>
  </form>

  <?php
  // اطلاعات ورود پیش‌فرض قبلا همیشه روی صفحه ورود چاپ می‌شد — روی سرور
  // هتل یعنی هر کسی که صفحه را باز کند رمز مدیر را می‌بیند. حالا فقط در
  // حالت اشکال‌زدایی نشان داده می‌شود.
  if (defined('APP_DEBUG') && APP_DEBUG):
  ?>
  <div class="alert alert-info" style="margin-top:20px;font-size:12px;">
    <i class="fas fa-circle-info"></i>
    <span><strong>ورود پیش‌فرض:</strong> admin@hotelmedia.com / Admin@123456</span>
  </div>
  <?php endif; ?>

  <div class="login-foot">
    <a href="https://kishwifi.com" target="_blank" rel="noopener">سماع رایانه کیش</a>
  </div>
</main>
</body>
</html>
