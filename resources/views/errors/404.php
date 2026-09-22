<?php /* صفحه‌ی ۴۰۴ — مستقل از layout چون ممکن است خطا پیش از
         احراز هویت یا در مسیر پلیر رخ دهد و layout به Auth نیاز دارد. */ ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>۴۰۴ — صفحه پیدا نشد</title>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css">
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="/assets/css/design-system.css">
<style>
  body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: var(--s6); }
  .err-box { text-align: center; max-width: 26rem; }
  .err-mark { height: 44px; width: auto; margin: 0 auto var(--s6); display: block; }
  :root[data-theme="light"] .err-mark { background: var(--brand-900); padding: 5px 7px; border-radius: var(--r-sm); box-sizing: content-box; }
  .err-code { font-size: 64px; font-weight: 700; color: var(--brand-500); line-height: 1; letter-spacing: -.03em; }
  .err-title { font-size: 18px; font-weight: 600; color: var(--text-1); margin-top: var(--s4); }
  .err-text { font-size: 14px; color: var(--text-3); margin-top: var(--s2); line-height: 1.8; }
  .err-actions { margin-top: var(--s6); display: flex; gap: var(--s3); justify-content: center; }
</style>
</head>
<body>
<div class="err-box">
  <img class="err-mark" src="/assets/img/sama-logo.svg" alt="">
  <div class="err-code">۴۰۴</div>
  <div class="err-title">این صفحه پیدا نشد</div>
  <div class="err-text">ممکن است نشانی اشتباه باشد یا این بخش جابه‌جا شده باشد.</div>
  <div class="err-actions">
    <a class="btn btn-primary" href="/admin/dashboard">
      <i class="fas fa-gauge"></i> داشبورد
    </a>
    <a class="btn btn-ghost" href="javascript:history.back()">
      <i class="fas fa-arrow-right"></i> بازگشت
    </a>
  </div>
</div>
</body>
</html>
