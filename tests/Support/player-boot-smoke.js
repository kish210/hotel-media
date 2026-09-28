/**
 * اجرای خشکِ اسکریپتِ صفحه‌ی پخش‌کننده روی یک DOM ساختگی.
 *
 * چرا لازم شد: لینتِ سازگاری فقط دنبال ویژگی‌های نوین می‌گردد و
 * بررسی نحو هم فقط پارس می‌کند. هیچ‌کدام خطای زمانِ بارگذاری را
 * نمی‌گیرند — مثلا صدا زدن تابعی که در شاخه‌ی PHP چاپ نشده، یا
 * خواندن عنصری که هنوز در DOM نیست. روی تلویزیون این خطا صفحه را
 * می‌خواباند و چون کنسولی در دسترس نیست، فقط یک نمایشگر سیاه
 * دیده می‌شود بدون هیچ سرنخی.
 *
 * استفاده:  node tests/Support/player-boot-smoke.js <فایل-html>
 */
var fs = require('fs');
var vm = require('vm');

var file = process.argv[2];
if (!file) {
  console.error('استفاده: node player-boot-smoke.js <فایل-html>');
  process.exit(2);
}

var html = fs.readFileSync(file, 'utf8');

/* ── DOM ساختگی ────────────────────────────────────────────────
   عمدا کمینه است: هدف اجرا کردن مسیرِ بارگذاری است، نه شبیه‌سازی
   کامل مرورگر. هر عنصری که کد بخواهد ساخته می‌شود تا شکستِ واقعی
   فقط از خودِ کد بیاید، نه از ناقص بودن این ساختگی. */
function makeEl(id) {
  var el = {
    id: id,
    style: {},
    className: '',
    innerHTML: '',
    textContent: '',
    offsetWidth: 1280,
    offsetHeight: 720,
    children: [],
    parentNode: null,
    appendChild: function (c) { c.parentNode = el; el.children.push(c); return c; },
    removeChild: function (c) { return c; },
    setAttribute: function () {},
    getAttribute: function () { return null; },
    getElementsByTagName: function () { return []; },
    addEventListener: function () {},
    focus: function () {},
    play: function () {},
    load: function () {}
  };
  return el;
}

var els = {};
function elById(id) {
  if (!els[id]) els[id] = makeEl(id);
  return els[id];
}

var timers = 0;
var thrown = [];

var sandbox = {
  console: console,
  screen: { width: 1280, height: 720, availWidth: 1280, availHeight: 720 },
  navigator: { userAgent: 'Maple smoke test' },
  document: {
    documentElement: makeEl('html'),
    body: makeEl('body'),
    getElementById: elById,
    createElement: function (t) { return makeEl('<' + t + '>'); },
    getElementsByTagName: function () { return []; },
    addEventListener: function () {}
  },
  /* تایمرها فقط شمرده می‌شوند؛ اجرای حلقه‌ها اینجا هدف نیست و
     اجرایشان تست را در حلقه‌ی بی‌پایان می‌انداخت. */
  setInterval: function () { timers++; return timers; },
  setTimeout: function () { timers++; return timers; },
  clearInterval: function () {},
  clearTimeout: function () {},
  XMLHttpRequest: function () {
    return {
      open: function () {}, send: function () {},
      setRequestHeader: function () {},
      onload: null, onerror: null, ontimeout: null,
      responseText: '{}', timeout: 0
    };
  },
  Image: function () { return { onload: null, onerror: null, src: '' }; },
  location: { origin: 'http://x', pathname: '/player/X', href: '', protocol: 'http:', host: 'x' },
  Date: Date, Math: Math, JSON: JSON, parseInt: parseInt, parseFloat: parseFloat,
  isNaN: isNaN, String: String, Number: Number, Array: Array, Object: Object
};
sandbox.window = sandbox;
sandbox.self = sandbox;

var re = /<script\b[^>]*>([\s\S]*?)<\/script>/gi;
var m, n = 0;
var ctx = vm.createContext(sandbox);

while ((m = re.exec(html)) !== null) {
  n++;
  var src = m[1];
  if (!src.trim()) continue;
  try {
    new vm.Script(src, { filename: 'script#' + n }).runInContext(ctx, { timeout: 5000 });
  } catch (e) {
    thrown.push('اسکریپت #' + n + ': ' + e.message);
  }
}

console.log('\n[1mاجرای خشکِ صفحه‌ی پخش‌کننده[0m');
console.log('فایل: ' + file);
console.log('اسکریپت‌ها: ' + n + '  ·  تایمرهای ثبت‌شده: ' + timers);

if (thrown.length) {
  thrown.forEach(function (t) { console.log('[31m❌[0m ' + t); });
  console.log('');
  process.exit(1);
}

console.log('[32m✅ بارگذاری بدون خطا[0m\n');
process.exit(0);
