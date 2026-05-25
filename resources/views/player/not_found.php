<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>صفحه‌نمایش یافت نشد</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
html, body { width:100%; height:100%; background:#0a0a14; color:#e2e8f0;
             font-family:'Segoe UI',Tahoma,sans-serif; display:flex;
             align-items:center; justify-content:center; }
.card { text-align:center; }
.code { font-size:80px; font-weight:900; color:#1e293b; line-height:1; margin-bottom:12px; }
.msg  { font-size:18px; color:#475569; margin-bottom:8px; }
.sub  { font-size:13px; color:#1e293b; }
.scr  { font-family:monospace; color:#38bdf8; font-size:16px; margin:8px 0; }
.back { display:inline-block; margin-top:24px; padding:10px 24px;
        background:rgba(56,189,248,.1); border:1px solid rgba(56,189,248,.25);
        color:#38bdf8; border-radius:10px; text-decoration:none; font-size:13px; }
</style>
</head>
<body>
<div class="card">
  <div class="code">404</div>
  <div class="msg">صفحه‌نمایش یافت نشد</div>
  <div class="scr"><?= isset($notFoundCode) ? htmlspecialchars($notFoundCode) : '' ?></div>
  <div class="sub">این کد در سیستم ثبت نشده یا حذف شده است.</div>
  <a href="/player" class="back">← بازگشت</a>
</div>
</body>
</html>
