<?php
/**
 * صفحه‌ی تأیید کد برای کسب‌وکار طرف قرارداد — روی گوشی صندوق‌دار.
 * @var array|null $offer
 * @var array|null $result
 */
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$claim = $result['claim'] ?? null;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex">
<title>تأیید کد تخفیف</title>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css">
<style>
  :root { --bg:#f1f5f9; --card:#fff; --ink:#0f172a; --mut:#64748b; --ok:#15803d; --bad:#b91c1c; --acc:#0d9488; }
  @media (prefers-color-scheme: dark) { :root { --bg:#0b1220; --card:#111a2e; --ink:#e2e8f0; --mut:#94a3b8; --ok:#4ade80; --bad:#f87171; --acc:#2dd4bf; } }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--ink); font-family: Vazirmatn, Tahoma, sans-serif; }
  .wrap { max-width: 440px; margin: 0 auto; padding: 24px 16px; }
  .card { background: var(--card); border-radius: 16px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  h1 { font-size: 18px; margin: 0 0 4px; }
  .sub { color: var(--mut); font-size: 13px; margin-bottom: 18px; }
  label { font-size: 13px; color: var(--mut); display: block; margin-bottom: 6px; }
  input { width: 100%; font: inherit; font-size: 26px; letter-spacing: 4px; text-align: center; direction: ltr;
          padding: 12px; border-radius: 12px; border: 2px solid #cbd5e1; background: transparent; color: var(--ink); text-transform: uppercase; }
  input:focus { outline: none; border-color: var(--acc); }
  button { width: 100%; margin-top: 12px; font: inherit; font-size: 16px; font-weight: 700; padding: 12px; border: 0;
           border-radius: 12px; background: var(--acc); color: #fff; cursor: pointer; }
  .res { margin-top: 16px; padding: 14px; border-radius: 12px; font-weight: 700; }
  .res.ok { background: rgba(21,128,61,.12); color: var(--ok); }
  .res.bad { background: rgba(185,28,28,.1); color: var(--bad); }
  .res small { display: block; font-weight: 400; color: var(--mut); margin-top: 4px; }
  .foot { text-align: center; color: var(--mut); font-size: 12px; margin-top: 16px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
<?php if (!$offer): ?>
    <h1>لینک نامعتبر است</h1>
    <div class="sub">این لینک وجود ندارد یا هتل آن را عوض کرده است. لینک تازه را از هتل بگیرید.</div>
<?php else: ?>
    <h1><?= $e($offer['business_name']) ?></h1>
    <div class="sub"><?= $e($offer['title']) ?><?= !(int)$offer['is_active'] ? ' — غیرفعال' : '' ?></div>
    <form method="post" autocomplete="off">
      <label for="code">کد مهمان را وارد کنید</label>
      <input id="code" name="code" maxlength="12" placeholder="ABCD-2345" required autofocus>
      <button type="submit">تأیید و ثبت استفاده</button>
    </form>
  <?php if ($result): ?>
    <div class="res <?= $result['ok'] ? 'ok' : 'bad' ?>">
      <?= $result['ok'] ? '✓ ' : '✗ ' ?><?= $e($result['message']) ?>
      <?php if ($claim): ?>
        <small>کد <?= $e($claim['code']) ?> · صادرشده <?= $e(substr((string)$claim['created_at'], 0, 16)) ?>
        <?= $claim['redeemed_at'] ? ' · استفاده ' . $e(substr((string)$claim['redeemed_at'], 0, 16)) : '' ?></small>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
  </div>
  <div class="foot">هر کد فقط یک بار پذیرفته می‌شود.</div>
</div>
</body>
</html>
