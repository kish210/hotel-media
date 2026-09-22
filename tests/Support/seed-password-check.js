#!/usr/bin/env node
/**
 * بررسی اینکه رمز اعلام‌شده واقعا با هش داخل seed می‌خورد.
 *
 * چرا لازم است: اسکریپت نصب، مستندات ISO و صفحه‌ی ورود همگی
 * «Admin@123456» را به‌عنوان رمز پیش‌فرض اعلام می‌کنند. اگر هش داخل
 * seed با آن نخورد — که روی سرور واقعی همین‌طور بود — هیچ‌کس نمی‌تواند
 * وارد پنل شود و پیام «ایمیل یا رمز اشتباه است» می‌گیرد، بدون هیچ
 * سرنخی از اینکه مشکل از کجاست.
 *
 * این تست فقط ساختار و همخوانی را می‌سنجد؛ تایید خود bcrypt کار
 * تست PHP است چون Node پیاده‌سازی bcrypt ندارد.
 *
 * اجرا: node tests/Support/seed-password-check.js
 */
'use strict';

var fs   = require('fs');
var path = require('path');

var ROOT = path.join(__dirname, '..', '..');
var ESC  = String.fromCharCode(27);
var C = {
  bold: ESC + '[1m', red: ESC + '[31m', green: ESC + '[32m',
  amber: ESC + '[33m', off: ESC + '[0m'
};

/* رمزی که همه‌جا اعلام می‌شود */
var PROMISED = 'Admin@123456';
var EMAIL    = 'admin@hotelmedia.com';

var findings = [];

/* ── ۱) رمز اعلام‌شده در کجاها آمده ───────────────────────────────── */
var promisedIn = [];
[
  'deploy/install-production.sh',
  'deploy/iso/firstboot.sh',
  'deploy/iso/README.md',
  'resources/views/auth/login.php'
].forEach(function (rel) {
  var p = path.join(ROOT, rel);
  if (!fs.existsSync(p)) return;
  if (fs.readFileSync(p, 'utf8').indexOf(PROMISED) !== -1) promisedIn.push(rel);
});

/* ── ۲) هش داخل seed ─────────────────────────────────────────────── */
var seedPath = path.join(ROOT, 'database/seeds/seed.sql');
if (!fs.existsSync(seedPath)) {
  findings.push('فایل seed پیدا نشد: database/seeds/seed.sql');
} else {
  var seed = fs.readFileSync(seedPath, 'utf8');

  /* خط کاربر مدیر */
  var adminLine = seed.split(/\r?\n/).filter(function (l) {
    return l.indexOf(EMAIL) !== -1;
  })[0];

  if (!adminLine) {
    findings.push('کاربر ' + EMAIL + ' در seed نیست — نصب تازه بدون مدیر می‌ماند');
  } else {
    /* هش bcrypt: $2y$ یا $2a$ یا $2b$ ، دو رقم، و ۵۳ نویسه */
    var dollar = String.fromCharCode(36);
    var re = new RegExp(
      '\\' + dollar + '2[aby]\\' + dollar + '[0-9]{2}\\' + dollar + '[A-Za-z0-9./]{53}'
    );
    var m = adminLine.match(re);

    if (!m) {
      findings.push('هش رمز مدیر در seed قالب bcrypt معتبر ندارد');
    } else {
      var cost = parseInt(m[0].split(dollar)[2], 10);
      if (cost < 10) {
        findings.push('هزینه‌ی bcrypt پایین است (' + cost + ') — حداقل ۱۰ توصیه می‌شود');
      }
      if (cost > 13) {
        findings.push('هزینه‌ی bcrypt بالاست (' + cost + ') — ورود روی سرور هتل کند می‌شود');
      }

      /* نشانه‌ی اینکه هش از جای دیگری کپی شده و هیچ‌کس تاییدش نکرده:
         همان هش برای چند کاربر با رمزهای ظاهرا متفاوت. */
      var all = seed.match(new RegExp(re.source, 'g')) || [];
      var uniq = {};
      all.forEach(function (h) { uniq[h] = (uniq[h] || 0) + 1; });
      Object.keys(uniq).forEach(function (h) {
        if (uniq[h] > 1) {
          findings.push('یک هش برای ' + uniq[h] + ' کاربر تکرار شده — همه یک رمز دارند');
        }
      });
    }
  }
}

/* ── گزارش ───────────────────────────────────────────────────────── */
console.log('\n' + C.bold + 'بررسی رمز پیش‌فرض seed' + C.off);
console.log('رمز اعلام‌شده در ' + promisedIn.length + ' فایل: ' + PROMISED);
promisedIn.forEach(function (f) { console.log('  · ' + f); });
console.log(new Array(59).join('-'));

if (!findings.length) {
  console.log(C.green + '✅ ساختار هش درست است' + C.off);
  console.log(C.amber + 'ℹ' + C.off + ' تطبیق واقعی رمز با هش را SeedPasswordTest بررسی می‌کند\n');
  process.exit(0);
}

findings.forEach(function (f) { console.log(C.red + '✗' + C.off + ' ' + f); });
console.log('\n' + C.red + '✗ ' + findings.length + ' مورد' + C.off + '\n');
process.exit(1);
