#!/usr/bin/env node
/**
 * بازرس سازگاری مرورگر تلویزیون
 *
 * صفحه‌هایی که روی تلویزیون هتل اجرا می‌شوند (پورتال مهمان و تابلوهای
 * محیط عمومی) در مرورگر خود تلویزیون باز می‌شوند، نه کروم دسکتاپ. این
 * مرورگرها خیلی عقب‌ترند:
 *
 *   Samsung Tizen 2.3 → Chromium 34      LG webOS 3 → Chromium 38
 *   Samsung Tizen 4   → Chromium 56      LG webOS 4 → Chromium 53
 *   Samsung Tizen 5   → Chromium 63      LG webOS 5 → Chromium 68
 *   Samsung Tizen 6   → Chromium 76      LG webOS 6 → Chromium 79
 *
 * پس سقف امن حتی برای جدیدترین تلویزیون هتلی Chromium 53 است و برای
 * پشتیبانی webOS 3 / Tizen 2.3 باید ES5 خالص بماند.
 *
 * وقتی یکی از این‌ها استفاده شود خطا در کنسول نمی‌آید — صفحه فقط
 * خالی یا بی‌ریخت بالا می‌آید و کسی متوجه نمی‌شود تا وقتی مهمان
 * شکایت کند. برای همین اینجا تست شده‌اند.
 *
 * اجرا:  node tests/Support/tv-compat-lint.js
 */
'use strict';

var fs   = require('fs');
var path = require('path');

var ROOT = path.join(__dirname, '..', '..');

/* صفحه‌هایی که واقعا روی تلویزیون باز می‌شوند. صفحه‌های پنل مدیریت
   اینجا نیستند — آن‌ها در مرورگر دسکتاپِ اپراتور باز می‌شوند. */
var TV_FILES = [
  'resources/views/player/profiles/iptv.php',
  'resources/views/player/profiles/lg_tv.php',
  'resources/views/player/profiles/samsung_tv.php',
  'resources/views/player/profiles/android_tv.php',
  'resources/views/player/profiles/modern.php',
  'resources/views/player/profiles/legacy.php',
  'resources/views/player/profiles/kiosk.php',
  'resources/views/player/profiles/minimal.php',
  'resources/views/player/index.php',
  'resources/views/player/bootstrap.php',
  'resources/views/player/pair.php',
  'resources/views/player/not_found.php',
  'public/assets/css/tv-base.css',
  'public/assets/js/tv-base.js'
];

/* هر قانون: چه چیزی، از کدام Chromium، و جایگزینش چیست.
   `re` باید global باشد تا شماره خط همه‌ی موارد پیدا شود. */
var RULES = [
  { id: 'css-inset',   min: 87, re: /(^|[;{\s])inset\s*:/g,
    what: 'inset: کوتاه‌نویس',
    fix:  'top/right/bottom/left را جدا بنویسید' },

  { id: 'css-gap',     min: 84, re: /(^|[;{\s])(gap|row-gap|column-gap)\s*:/g,
    what: 'gap در flexbox',
    fix:  'margin روی فرزندها (در grid از Chromium 57 کار می‌کند، در flex نه)' },

  { id: 'css-var',     min: 49, re: /var\(\s*--/g,
    what: 'متغیر CSS',
    fix:  'مقدار ثابت بنویسید — روی webOS 3 و Tizen 2.3 پشتیبانی نمی‌شود' },

  { id: 'css-colormix', min: 111, re: /color-mix\s*\(/g,
    what: 'color-mix()',
    fix:  'رنگ نهایی را مستقیم بنویسید' },

  { id: 'css-backdrop', min: 76, re: /backdrop-filter\s*:/g,
    what: 'backdrop-filter',
    fix:  'پس‌زمینه نیمه‌شفاف ساده — بدون بلور' },

  { id: 'css-aspect',  min: 88, re: /aspect-ratio\s*:/g,
    what: 'aspect-ratio',
    fix:  'ترفند padding-top درصدی' },

  /* clamp/min/max از همه خطرناک‌ترند: کل اندازه‌گذاری واکنش‌گرا با آن‌ها
     نوشته شده و وقتی پشتیبانی نشود مرورگر کل اعلان را دور می‌اندازد،
     پس font-size و padding بی‌مقدار می‌مانند و صفحه به‌هم می‌ریزد. */
  /* `[^\w.-]` نه `[^\w-]` — وگرنه Math.max و Math.min هم گرفته می‌شوند
     که تابع JS‌اند و هیچ ربطی به CSS ندارند. */
  { id: 'css-clamp',   min: 79, re: /(^|[^\w.-])(clamp|min|max)\s*\([^)]*[\d%]/g,
    what: 'clamp() / min() / max()',
    fix:  'اندازه ثابت px — تلویزیون رزولوشن ثابت دارد؛ مقیاس با tvScale() در tv-base.js' },

  { id: 'css-numeric', min: 52, re: /font-variant-numeric\s*:/g,
    what: 'font-variant-numeric',
    fix:  'حذف کنید — با فونت tabular جایگزین شود' },

  { id: 'js-arrow',    min: 45, re: /=>/g,
    what: 'تابع arrow',
    fix:  'function () {} بنویسید' },

  { id: 'js-template', min: 41, re: /`/g,
    what: 'رشته template',
    fix:  'با + بچسبانید' },

  { id: 'js-letconst', min: 49, re: /(^|[^\w.$])(let|const)\s+[A-Za-z_$]/g,
    what: 'let / const',
    fix:  'var بنویسید' },

  { id: 'js-fetch',    min: 42, re: /(^|[^\w.$])fetch\s*\(/g,
    what: 'fetch()',
    fix:  'XMLHttpRequest — در tv-base.js کمک‌تابع tvGet/tvPost هست' },

  { id: 'js-promise',  min: 32, re: /(^|[^\w.$])Promise\s*[.(]/g,
    what: 'Promise',
    fix:  'callback بنویسید' },

  { id: 'js-spread',   min: 46, re: /\.\.\.[A-Za-z_$[{]/g,
    what: 'spread / rest',
    fix:  'apply() یا حلقه' },

  { id: 'js-classkw',  min: 49, re: /(^|[^\w.$])class\s+[A-Z]/g,
    what: 'class',
    fix:  'تابع سازنده با prototype' },

  { id: 'js-forof',    min: 38, re: /for\s*\([^)]*\sof\s/g,
    what: 'for...of',
    fix:  'حلقه شمارشی ساده' },

  { id: 'js-objassign', min: 45, re: /Object\.assign\s*\(/g,
    what: 'Object.assign',
    fix:  'انتساب دستی کلیدها' },

  { id: 'js-includes', min: 41, re: /\.includes\s*\(/g,
    what: 'Array/String.includes',
    fix:  'indexOf(x) !== -1' },

  { id: 'js-optchain', min: 80, re: /\?\./g,
    what: 'زنجیره اختیاری ?.',
    fix:  'بررسی صریح null' },

  { id: 'js-nullish',  min: 80, re: /\?\?/g,
    what: 'عملگر ??',
    fix:  'بررسی صریح null یا ||' },

  /* تله‌ی خاص تلویزیون: play() تا Chromium 50 چیزی برنمی‌گرداند، پس
     .then/.catch روی آن TypeError می‌دهد و پخش روی همان تلویزیون‌های
     قدیمی که این پروفایل‌ها برایشان نوشته شده‌اند می‌خوابد. */
  { id: 'js-playpromise', min: 50, re: /\.play\s*\(\s*\)\s*\.\s*(then|catch)/g,
    what: 'play().then / play().catch',
    fix:  'var pr = el.play(); if (pr && pr.catch) pr.catch(function(){});' }
];

/* سقف مجاز. عدد کوچک‌تر یعنی سخت‌گیرانه‌تر.
   ۳۴ = Tizen 2.3، قدیمی‌ترین تلویزیونی که پشتیبانی می‌کنیم. */
var TARGET = parseInt(process.env.TV_CHROMIUM || '34', 10);

/* بخش‌هایی که نباید بررسی شوند: کامنت‌ها و کد PHP سمت سرور.
   PHP روی سرور اجرا می‌شود، نه تلویزیون — `=>` در آرایه‌ی PHP بی‌ضرر است. */
function strip(src) {
  return src
    // بلوک‌های PHP — سمت سرور اجرا می‌شوند
    .replace(/<\?php[\s\S]*?\?>/g, function (m) { return blank(m); })
    .replace(/<\?=[\s\S]*?\?>/g,   function (m) { return blank(m); })
    // کامنت‌های CSS و JS
    .replace(/\/\*[\s\S]*?\*\//g,  function (m) { return blank(m); })
    // کامنت تک‌خطی — نه داخل رشته یا //: در URL
    .replace(/(^|[^:"'`\\])\/\/[^\n]*/g, function (m, p1) { return p1 + blank(m.slice(p1.length)); })
    // کامنت HTML
    .replace(/<!--[\s\S]*?-->/g,   function (m) { return blank(m); });
}

/* جایگزینی با فاصله اما با حفظ خطوط جدید، تا شماره خط درست بماند */
function blank(s) {
  return s.replace(/[^\n]/g, ' ');
}

function lineOf(src, index) {
  var n = 1;
  for (var i = 0; i < index; i++) if (src.charCodeAt(i) === 10) n++;
  return n;
}

var findings = [];
var scanned  = 0;
var skipped  = [];

TV_FILES.forEach(function (rel) {
  var abs = path.join(ROOT, rel);
  if (!fs.existsSync(abs)) { skipped.push(rel); return; }
  scanned++;

  var raw   = fs.readFileSync(abs, 'utf8');
  var clean = strip(raw);

  RULES.forEach(function (rule) {
    if (rule.min <= TARGET) return;   // روی این هدف مجاز است
    rule.re.lastIndex = 0;
    var m;
    while ((m = rule.re.exec(clean)) !== null) {
      findings.push({
        file: rel, line: lineOf(clean, m.index),
        id: rule.id, what: rule.what, min: rule.min, fix: rule.fix
      });
      if (m.index === rule.re.lastIndex) rule.re.lastIndex++;
    }
  });
});

/* ── گزارش ──────────────────────────────────────────────────────── */
console.log('\n[1mبازرس سازگاری مرورگر تلویزیون[0m');
console.log('هدف: Chromium ' + TARGET + '  ·  ' + scanned + ' فایل بررسی شد');
if (skipped.length) console.log('موجود نبود: ' + skipped.join(', '));
console.log('─'.repeat(64));

if (!findings.length) {
  console.log('[32m✅ هیچ ویژگی ناسازگاری پیدا نشد[0m\n');
  process.exit(0);
}

/* گروه‌بندی بر اساس فایل، بعد بر اساس قانون — خروجی کوتاه بماند */
var byFile = {};
findings.forEach(function (f) {
  (byFile[f.file] = byFile[f.file] || []).push(f);
});

Object.keys(byFile).sort().forEach(function (file) {
  console.log('\n[1m' + file + '[0m');
  var byRule = {};
  byFile[file].forEach(function (f) {
    (byRule[f.id] = byRule[f.id] || []).push(f);
  });
  Object.keys(byRule).forEach(function (id) {
    var g = byRule[id], first = g[0];
    var lines = g.map(function (x) { return x.line; });
    var show  = lines.slice(0, 8).join(', ') + (lines.length > 8 ? ' …' : '');
    console.log('  [31m✗[0m ' + first.what +
                '  (نیاز به Chromium ' + first.min + ')  ×' + g.length);
    console.log('    خط: ' + show);
    console.log('    [33m→[0m ' + first.fix);
  });
});

console.log('\n' + '─'.repeat(64));
console.log('[31m✗ ' + findings.length + ' مورد در ' +
            Object.keys(byFile).length + ' فایل[0m');
console.log('این‌ها خطای کنسول نمی‌دهند — صفحه روی تلویزیون فقط خالی');
console.log('یا بی‌ریخت بالا می‌آید.\n');
process.exit(1);
