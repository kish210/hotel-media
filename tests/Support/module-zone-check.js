#!/usr/bin/env node
/**
 * بازرس شناسه‌های «نوع نمایش» ماژول.
 *
 * صفحهٔ پلی‌لیست یک فهرست کشویی از انواع نمایش هر ماژول می‌سازد و
 * مقدارش را در تنظیمات آیتم ذخیره می‌کند. ماژول همان مقدار را در یک
 * `match` می‌گیرد و اگر نشناسد به شاخهٔ default می‌افتد.
 *
 * چرا این بازرس لازم شد: این دو فهرست از هم جدا افتاده بودند —
 * صفحه `full_menu` می‌داد و ماژول `menu_full` می‌خواست، و برای هتل
 * `events` در برابر `hotel_events`. نتیجه این بود که هر آیتم ماژولی
 * که از صفحهٔ پلی‌لیست ساخته می‌شد روی تابلو فقط «نامعتبر» نشان
 * می‌داد. هیچ خطایی هم ثبت نمی‌شد: نه در لاگ PHP، نه در کنسول
 * تلویزیون. فقط یک اسلاید سفید بین تبلیغات.
 *
 * پس تطبیق این دو، تنها چیزی است که جلوی تکرارش را می‌گیرد.
 *
 * اجرا:  node tests/Support/module-zone-check.js
 */
'use strict';

var fs   = require('fs');
var path = require('path');

var ROOT = path.join(__dirname, '..', '..');
var VIEW = path.join(ROOT, 'resources/views/playlists/show.php');

/* ماژول‌هایی که در این شاخه زنده‌اند. صنف‌های غیرهتلی به شاخهٔ
   non-hotel-verticals رفته‌اند و فایلشان اینجا نیست. */
var MODULES = {
  menu:  'app/Modules/Menu/MenuModule.php',
  hotel: 'app/Modules/Hotel/HotelModule.php'
};

function read(p) {
  var abs = path.join(ROOT, p);
  return fs.existsSync(abs) ? fs.readFileSync(abs, 'utf8') : null;
}

/* شناسه‌هایی که ماژول واقعا در renderPlayerWidget می‌پذیرد */
function moduleZones(src) {
  var body = src.split('function renderPlayerWidget')[1] || '';
  body = body.split('private function')[0];
  var out = [], m;
  var re = /'([a-z0-9_]+)'\s*=>\s*\$this->/g;
  while ((m = re.exec(body)) !== null) out.push(m[1]);
  return out;
}

/* شناسه‌هایی که فهرست کشویی صفحه پیشنهاد می‌دهد */
function viewZones(src, moduleKey) {
  var at = src.indexOf('\n  ' + moduleKey + ': {');
  if (at === -1) return null;
  var block = src.slice(at, at + 1600);
  var line  = (block.match(/\{key:'zone_type'[^\n]*/) || [])[0];
  if (!line) return null;
  var opts = (line.match(/options:\{([^}]*)\}/) || [])[1] || '';
  var out = [], m;
  var re = /'?([A-Za-z0-9_]+)'?\s*:/g;
  while ((m = re.exec(opts)) !== null) out.push(m[1]);
  return out;
}

var view = read('resources/views/playlists/show.php');
var bad  = [];
var seen = 0;

console.log('\n[1mبازرس شناسه‌های نوع نمایش ماژول[0m');

if (view === null) {
  console.log('[31m❌ فایل صفحهٔ پلی‌لیست پیدا نشد[0m\n');
  process.exit(1);
}

Object.keys(MODULES).forEach(function (key) {
  var src = read(MODULES[key]);
  if (src === null) { console.log('⏭ ' + key + ' — فایل ماژول نیست'); return; }

  var accepted = moduleZones(src);
  var offered  = viewZones(view, key);

  if (!accepted.length) { console.log('⏭ ' + key + ' — شناسه‌ای در ماژول پیدا نشد'); return; }
  if (offered === null) { console.log('⏭ ' + key + ' — فهرستی در صفحه نیست'); return; }

  seen++;
  var missing = offered.filter(function (z) { return accepted.indexOf(z) === -1; });

  if (missing.length) {
    bad.push(key);
    console.log('[31m❌ ' + key + '[0m — صفحه می‌دهد ولی ماژول نمی‌شناسد: ' + missing.join('، '));
    console.log('     ماژول این‌ها را می‌پذیرد: ' + accepted.join('، '));
  } else {
    console.log('[32m✅[0m ' + key + ' — هر ' + offered.length + ' گزینه را ماژول می‌شناسد');
  }
});

console.log('');
if (bad.length) {
  console.log('[31m' + bad.length + ' ماژول ناهماهنگ — آیتمشان روی تابلو «نامعتبر» نشان می‌دهد[0m\n');
  process.exit(1);
}
console.log('[32m✅ هر ' + seen + ' ماژول هماهنگ‌اند[0m\n');
process.exit(0);
