<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SignageCMS — راه‌اندازی</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
html, body {
  width:100%; height:100%;
  background:#0a0a14;
  font-family:'Segoe UI',Tahoma,sans-serif;
  color:#e2e8f0;
  overflow:hidden;
}
.bg-grid {
  position:fixed; inset:0; z-index:0;
  background-image:
    linear-gradient(rgba(56,189,248,.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(56,189,248,.03) 1px, transparent 1px);
  background-size:60px 60px;
  animation:gridMove 25s linear infinite;
}
@keyframes gridMove { to { background-position:60px 60px; } }

.wrap {
  position:relative; z-index:1;
  display:flex; flex-direction:column;
  align-items:center; justify-content:center;
  min-height:100vh; padding:24px;
}

/* ── Binding confirmation card ── */
.bind-card {
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  border-radius:24px;
  padding:48px 44px;
  max-width:420px; width:100%;
  text-align:center;
  backdrop-filter:blur(14px);
  box-shadow:0 28px 90px rgba(0,0,0,.55);
}

/* ── Pairing confirmed (ring animation) ── */
.ring-wrap {
  position:relative;
  width:90px; height:90px;
  margin:0 auto 22px;
}
.ring-wrap svg { width:90px; height:90px; transform:rotate(-90deg); }
.ring-wrap circle { fill:none; stroke-width:4; }
.bg-circle  { stroke:rgba(56,189,248,.12); }
.prog-circle {
  stroke:#38bdf8; stroke-linecap:round;
  stroke-dasharray:245; stroke-dashoffset:245;
  animation:ringFill 2.4s ease-out forwards;
}
@keyframes ringFill { to { stroke-dashoffset:0; } }
.check-icon {
  position:absolute; inset:0;
  display:flex; align-items:center; justify-content:center;
  font-size:30px;
}
.bind-name { font-size:22px; font-weight:800; color:#fff; margin-bottom:6px; }
.bind-code { font-size:12px; color:#38bdf8; font-family:monospace; letter-spacing:3px; margin-bottom:18px; }
.bind-msg  { font-size:13px; color:#64748b; }

/* ── Enter code card ── */
.enter-card {
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  border-radius:24px;
  padding:44px 40px;
  max-width:440px; width:100%;
  text-align:center;
  backdrop-filter:blur(14px);
  box-shadow:0 28px 90px rgba(0,0,0,.55);
}
.icon-ring {
  width:76px; height:76px;
  border-radius:50%;
  background:rgba(56,189,248,.08);
  border:1.5px solid rgba(56,189,248,.2);
  display:flex; align-items:center; justify-content:center;
  margin:0 auto 22px;
  font-size:30px;
}
.ec-title { font-size:20px; font-weight:800; color:#fff; margin-bottom:6px; }
.ec-sub   { font-size:13px; color:#64748b; line-height:1.7; margin-bottom:28px; }

/* ── Code input ── */
.code-input {
  background:rgba(255,255,255,.05);
  border:1.5px solid rgba(255,255,255,.1);
  border-radius:14px;
  padding:16px 20px;
  font-size:26px; letter-spacing:6px;
  color:#fff; width:100%;
  text-align:center;
  font-family:'Courier New',monospace;
  text-transform:uppercase;
  outline:none;
  transition:border-color .2s, background .2s;
  caret-color:#38bdf8;
}
.code-input:focus {
  border-color:rgba(56,189,248,.5);
  background:rgba(56,189,248,.04);
}
.code-input::placeholder { color:#334155; letter-spacing:3px; font-size:16px; }

/* ── Go button ── */
.btn-go {
  width:100%; margin-top:14px;
  padding:15px;
  background:linear-gradient(135deg,#38bdf8,#0ea5e9);
  border:none; border-radius:14px;
  color:#fff; font-size:15px; font-weight:700;
  cursor:pointer; font-family:inherit;
  transition:opacity .2s, transform .1s;
  display:flex; align-items:center; justify-content:center; gap:8px;
}
.btn-go:hover  { opacity:.88; }
.btn-go:active { transform:scale(.98); }
.btn-go:disabled { opacity:.4; cursor:not-allowed; }

/* ── Error / status ── */
.err {
  font-size:12px; color:#f87171;
  margin-top:12px; min-height:18px;
  display:flex; align-items:center; justify-content:center; gap:6px;
}
.loading { color:#38bdf8 !important; }

/* ── Help hint ── */
.help {
  margin-top:20px;
  font-size:11px; color:#334155;
  line-height:1.8;
  border-top:1px solid rgba(255,255,255,.05);
  padding-top:16px;
}
.help code {
  color:#38bdf8;
  background:rgba(56,189,248,.08);
  padding:1px 6px; border-radius:4px;
}

.brand { margin-top:28px; font-size:11px; color:#1e293b; letter-spacing:1px; }
</style>
</head>
<body>
<div class="bg-grid"></div>
<div class="wrap">

<?php if (isset($pairingScreen)): ?>
  <!-- ══ حالت: دستگاه تازه bind شد ══ -->
  <div class="bind-card">
    <div class="ring-wrap">
      <svg viewBox="0 0 90 90">
        <circle class="bg-circle"   cx="45" cy="45" r="39"/>
        <circle class="prog-circle" cx="45" cy="45" r="39"/>
      </svg>
      <div class="check-icon">✓</div>
    </div>
    <div class="bind-name"><?= htmlspecialchars($pairingScreen['name'] ?? 'صفحه‌نمایش') ?></div>
    <div class="bind-code"><?= htmlspecialchars($pairingScreen['code'] ?? '') ?></div>
    <div class="bind-msg">این دستگاه متصل شد — در حال بارگذاری پلیر...</div>
  </div>
  <script>
  (function(){
    const code = <?= json_encode($pairingScreen['code'] ?? '') ?>;
    const name = <?= json_encode($pairingScreen['name'] ?? '') ?>;
    if (code) {
      try { localStorage.setItem('signage_scr',  code); } catch(e){}
      try { localStorage.setItem('signage_name', name); } catch(e){}
    }
    setTimeout(function(){ window.location.href = <?= json_encode($pairingRedirect ?? '/player/') ?>; }, 2400);
  })();
  </script>

<?php else: ?>
  <!-- ══ حالت: بدون cookie — ورود کد ══ -->
  <div class="enter-card">
    <div class="icon-ring">📺</div>
    <div class="ec-title">راه‌اندازی صفحه‌نمایش</div>
    <div class="ec-sub">کد فعال‌سازی را از پنل مدیریت دریافت و وارد کنید</div>

    <input id="codeInput" class="code-input"
           type="text" autocomplete="off" autocorrect="off"
           spellcheck="false" maxlength="20"
           placeholder="کد فعال‌سازی">

    <button class="btn-go" id="goBtn" onclick="goPair()">
      <i class="fas fa-link"></i> اتصال به صفحه‌نمایش
    </button>
    <div class="err" id="errMsg"></div>

    <div class="help">
      <strong style="color:#475569;">کد فعال‌سازی</strong> از بخش
      <code>مدیریت ← صفحات ← فعال‌سازی</code>
      دریافت کنید.<br>
      آدرس این صفحه برای <strong>همه</strong> صفحات‌نمایش یکسان است:<br>
      <code id="thisUrl"></code>
    </div>
  </div>
  <script>
  // نشون دادن URL جاری
  (function(){
    const u = window.location.origin + '/player/';
    const el = document.getElementById('thisUrl');
    if (el) el.textContent = u;
  })();

  const inp = document.getElementById('codeInput');
  const btn = document.getElementById('goBtn');
  const err = document.getElementById('errMsg');

  inp.focus();
  inp.addEventListener('keydown', function(e){ if(e.key==='Enter') goPair(); });

  async function goPair() {
    const raw = inp.value.trim().toUpperCase().replace(/\s+/g,'');
    err.textContent = '';
    if (!raw) { showErr('کد نمی‌تواند خالی باشد'); return; }
    if (!/^[A-Z0-9]{4,20}$/.test(raw)) { showErr('فرمت کد نامعتبر است'); return; }

    // اگه SCR... بود → مستقیم برو به /player/{code}
    if (/^SCR[A-Z0-9]+$/.test(raw)) {
      window.location.href = '/player/' + encodeURIComponent(raw);
      return;
    }

    // وگرنه: کد فعال‌سازی (activation code) است → از API بگیر
    setLoading(true);
    try {
      const res = await fetch('/player/activate', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ activation_code: raw })
      });
      const d = await res.json();
      if (d.success && d.data && d.data.screen_code) {
        // داریم screen code رو → برو به /player/{code} تا cookie ست بشه
        window.location.href = '/player/' + encodeURIComponent(d.data.screen_code);
      } else {
        showErr(d.message || 'کد نامعتبر است یا منقضی شده');
        setLoading(false);
      }
    } catch(e) {
      showErr('خطا در اتصال به سرور');
      setLoading(false);
    }
  }

  function showErr(msg) {
    err.className = 'err';
    err.innerHTML = '<i class="fas fa-circle-exclamation"></i> ' + msg;
  }
  function setLoading(on) {
    btn.disabled = on;
    if (on) {
      btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> در حال اتصال...';
      err.className = 'err loading';
      err.textContent = '';
    } else {
      btn.innerHTML = '<i class="fas fa-link"></i> اتصال به صفحه‌نمایش';
    }
  }
  </script>
<?php endif; ?>

  <div class="brand">SignageCMS Player</div>
</div>

<!-- Font Awesome CDN (lightweight) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
      crossorigin="anonymous" referrerpolicy="no-referrer">
</body>
</html>
