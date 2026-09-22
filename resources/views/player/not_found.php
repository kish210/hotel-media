<?php
/**
 * کد صفحه‌نمایش در سیستم نیست.
 *
 * این صفحه روی مرورگر تلویزیون باز می‌شود، پس مثل بقیه‌ی صفحه‌های
 * تلویزیون از tv-base.css استفاده می‌کند و ES5 است.
 */
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>صفحه‌نمایش یافت نشد</title>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css">
<link rel="stylesheet" href="/assets/css/tv-base.css">
<style>
body {
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center;
  text-align: center;
}
.nf-logo { height: 3rem; width: auto; margin: 0 auto 1.6rem; display: block; }
.nf-code { font-size: 4rem; font-weight: 800; color: #4a5567; line-height: 1; }
.nf-msg  { font-size: 1.5rem; font-weight: 700; margin-top: .9rem; }
/* رنگ قبلی #1e293b روی زمینه‌ی تیره عملا نامرئی بود — تکنسینی که
   جلوی تلویزیون ایستاده باید کد را از چند قدمی بخواند. */
.nf-scr  {
  font-family: monospace;
  font-size: 1.5rem;
  letter-spacing: .2em;
  color: #7bb8e8;
  margin: 1rem 0;
}
.nf-sub  { font-size: 1rem; font-weight: 400; color: #8c99ad; line-height: 1.8; }
.nf-back { margin-top: 1.8rem; }
</style>
</head>
<body>
<div>
  <img class="nf-logo" src="/assets/img/sama-logo.svg" alt="">
  <div class="nf-code">۴۰۴</div>
  <div class="nf-msg">این صفحه‌نمایش شناخته نشد</div>
  <div class="nf-scr"><?= e($notFoundCode ?? '') ?></div>
  <div class="nf-sub">این کد در سیستم ثبت نشده یا حذف شده است.<br>
    از پنل مدیریت دوباره ثبتش کنید.</div>
  <a class="tv-btn nf-back" href="/player">بازگشت به راه‌اندازی</a>
</div>
</body>
</html>
