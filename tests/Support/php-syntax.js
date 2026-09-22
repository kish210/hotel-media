#!/usr/bin/env node
/**
 * بازرس الگوهای نحوی PHP.
 *
 * ‏php -l تک‌تک فایل‌ها را می‌گیرد، ولی روی سرور هتل همیشه نصب نیست و
 * پیامش هم محل واقعی مشکل را نشان نمی‌دهد. این بازرس الگوهایی را
 * می‌گیرد که در عمل پیش آمدند و تشخیصشان از پیام php سخت است.
 *
 * الگوی اول که واقعا رخ داد: نوشتن یک خط cron داخل کامنت بلوکی. در
 * «هر پنج دقیقه» یک ستاره و اسلش پشت سر هم می‌آید و همان کامنت را
 * زودتر می‌بندد؛ بقیه‌ی متنِ کامنت به‌عنوان کد خوانده می‌شود و خطایی
 * چند خط پایین‌تر گزارش می‌شود که هیچ ربطی به محل واقعی ندارد.
 *
 * اجرا:  node tests/Support/php-syntax.js
 */
'use strict';

var fs   = require('fs');
var path = require('path');

var ROOT = path.join(__dirname, '..', '..');
var SCAN = ['app', 'config', 'database', 'routes', 'resources', 'tests', 'public'];

var ESC = String.fromCharCode(27);
var C = {
  bold:  ESC + '[1m',
  red:   ESC + '[31m',
  green: ESC + '[32m',
  amber: ESC + '[33m',
  off:   ESC + '[0m'
};

function walk(dir, out) {
  var full = path.join(ROOT, dir);
  if (!fs.existsSync(full)) return out;
  fs.readdirSync(full).forEach(function (f) {
    var rel = path.join(dir, f);
    var abs = path.join(ROOT, rel);
    var st;
    try { st = fs.statSync(abs); } catch (e) { return; }
    if (st.isDirectory()) { walk(rel, out); return; }
    if (/\.php$/.test(f)) out.push(rel.replace(/\\/g, '/'));
  });
  return out;
}

var files = [];
SCAN.forEach(function (d) { walk(d, files); });
if (fs.existsSync(path.join(ROOT, 'artisan'))) files.push('artisan');

var findings = [];

files.forEach(function (rel) {
  var src   = fs.readFileSync(path.join(ROOT, rel), 'utf8');

  /* رشته‌ها را خالی می‌کنیم پیش از هر تحلیلی.
     بدون این، هر regex داخل یک رشته که ستاره و اسلش دارد — مثل
     preg_replace با الگوی کامنت SQL — به‌عنوان بستنِ کامنت خوانده
     می‌شود و بازرس روی کد سالم هشدار می‌دهد. طول خط حفظ می‌شود تا
     شماره‌ی ستون به‌هم نریزد. */
  var scan = src.replace(/'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"/g, function (m) {
    return m.charAt(0) + new Array(m.length - 1).join(' ') + m.charAt(0);
  });

  var lines = scan.split(/\r?\n/);
  /* متن اصلی برای نمایش در گزارش — نسخه‌ی پاک‌شده فقط برای تحلیل است */
  var rawLines = src.split(/\r?\n/);

  /* ── کامنت بلوکی که زودتر بسته می‌شود ────────────────────────────
     دنبال خطی می‌گردیم که هنوز داخل کامنت است، دنباله‌ی بستن دارد، و
     چیزی که آن دنباله را ساخته یک الگوی زمانی است نه پایان عمدی. */
  var inBlock = false;

  lines.forEach(function (line, i) {
    if (inBlock) {
      var closesHere = line.indexOf('*/');
      if (closesHere !== -1) {
        /* متنِ *بعد* از دنباله‌ی بستن مهم است، نه قبلش.
           در «ستاره‌اسلش‌پنج ستاره ستاره ستاره» خودِ ستاره‌اسلش کامنت را
           می‌بندد و بقیه‌ی خط — که هنوز متنِ کامنت است — کد حساب
           می‌شود. پس اگر بعد از بستن چیزی مانده که شبیه کد PHP نیست،
           این یک بستنِ ناخواسته است. */
        var after = line.slice(closesHere + 2).trim();

        var looksLikeCron = /^\d+\s+[*\d,\-\/]+\s+[*\d,\-\/]+/.test(after)
                         || /^[*\d,\-\/]+(\s+[*\d,\-\/]+){3,}/.test(after);

        /* بعد از پایان کامنت معمولا یا چیزی نیست یا کد PHP می‌آید.
           متن فارسی یا دستور پوسته آنجا یعنی کامنت زودتر بسته شده.

           ‏?> استثناست و خیلی رایج: در نماها یک کامنت توضیحی تمام
           می‌شود و بلافاصله از PHP بیرون می‌آییم. */
        var looksLikeProse = after !== ''
                          && !/^\?>/.test(after)
                          && !/^[$\w\\(){};\[\]]/.test(after)
                          && !/^\/[\/*]/.test(after);

        if (looksLikeCron || looksLikeProse) {
          findings.push({
            file: rel, line: i + 1,
            what: 'کامنت بلوکی زودتر بسته می‌شود؛ بقیه‌ی خط کد حساب می‌شود',
            fix:  'الگوی زمانی cron را با کلمه بنویسید، یا دنباله‌ی بستن را از متن بردارید',
            text: (rawLines[i] || line).trim().slice(0, 70)
          });
        }
      }
    }

    var opens  = (line.match(/\/\*/g) || []).length;
    var closes = (line.match(/\*\//g) || []).length;
    if (opens > closes)      inBlock = true;
    else if (closes > 0)     inBlock = false;
  });

  /* ── فایل باید با تگ PHP یا HTML شروع شود ───────────────────────
     خط shebang استثناست: artisan یک اسکریپت اجرایی است و #! باید
     پیش از <?php بیاید، وگرنه پوسته نمی‌داند با چه چیزی اجرایش کند. */
  var head = src.replace(/^#![^\n]*\r?\n/, '');
  if (!/^\s*(<\?php|<\?=|<)/.test(head) && head.trim() !== '') {
    findings.push({
      file: rel, line: 1,
      what: 'فایل با تگ PHP یا HTML شروع نمی‌شود',
      fix:  'اولین خط باید <?php باشد',
      text: src.slice(0, 40).replace(/\r?\n/g, ' ')
    });
  }

  /* ── فاصله‌ی خالی بعد از تگ پایانی ────────────────────────────────
     در فایلی که فقط PHP است، هر کاراکتر بعد از ?> مستقیم به خروجی
     می‌رود و اولین header() یا setcookie بعدی را می‌شکند — با پیامی
     که فایل مقصر را نشان نمی‌دهد. */
  if (/\?>\s*\r?\n\s*\r?\n\s*$/.test(src)) {
    findings.push({
      file: rel, line: lines.length,
      what: 'فاصله‌ی خالی بعد از تگ پایانی به خروجی می‌رود',
      fix:  'در فایل‌های تماما PHP تگ پایانی را حذف کنید',
      text: ''
    });
  }
});

console.log('\n' + C.bold + 'بازرس الگوهای نحوی PHP' + C.off);
console.log(files.length + ' فایل بررسی شد');
console.log(new Array(59).join('-'));

if (!findings.length) {
  console.log(C.green + '✅ الگوی مشکل‌سازی پیدا نشد' + C.off + '\n');
  process.exit(0);
}

findings.forEach(function (f) {
  console.log(C.red + '✗' + C.off + ' ' + f.file + ':' + f.line);
  console.log('   ' + f.what);
  if (f.text) console.log('   › ' + f.text);
  console.log('   ' + C.amber + '→' + C.off + ' ' + f.fix);
});

console.log('\n' + C.red + '✗ ' + findings.length + ' مورد' + C.off + '\n');
process.exit(1);
