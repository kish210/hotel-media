const fs = require('fs'), path = require('path');
const dir = 'database/migrations';
const tables = {};
let ord = 0;
const files = fs.readdirSync(dir).filter(f => f.endsWith('.sql')).sort();

const COMMENT_RE = new RegExp("COMMENT\\s+'((?:[^'\\\\]|\\\\.)*)'", "i");
function pullComment(def) {
  let cm = '';
  const m = def.match(COMMENT_RE);
  if (m) { cm = m[1].replace(/\\'/g, "'"); def = def.replace(m[0], '').trim(); }
  return [def.replace(/,$/, '').trim(), cm];
}

for (const f of files) {
  const mig = f.replace('.sql', '');
  const sql = fs.readFileSync(path.join(dir, f), 'utf8');

  const re = /CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z_0-9]+)`?\s*\(([\s\S]*?)\n\s*\)\s*ENGINE/gi;
  let m;
  while ((m = re.exec(sql))) {
    const t = m[1];
    if (!tables[t]) tables[t] = { cols: {}, from: mig, order: ord++ };
    for (let line of m[2].split('\n')) {
      line = line.trim().replace(/,$/, '').trim();
      if (!line || /^(PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT|FOREIGN|FULLTEXT)\b/i.test(line)) continue;
      const c = line.match(/^`?([A-Za-z_0-9]+)`?\s+(.+)$/);
      if (!c) continue;
      const [type, cm] = pullComment(c[2]);
      tables[t].cols[c[1]] = { type, comment: cm, from: mig };
    }
  }

  const ar = /ALTER\s+TABLE\s+`?([A-Za-z_0-9]+)`?\s+ADD\s+COLUMN\s+`?([A-Za-z_0-9]+)`?\s+([^"']*)/gi;
  while ((m = ar.exec(sql))) {
    const t = m[1], col = m[2];
    const [type, cm] = pullComment(m[3].trim());
    if (!tables[t]) tables[t] = { cols: {}, from: mig, order: ord++ };
    if (!tables[t].cols[col]) tables[t].cols[col] = { type, comment: cm, from: mig };
  }
}


const T = tables;

// دسته‌بندی دستی: نام جدول به‌تنهایی نمی‌گوید به کدام محصول تعلق دارد
const GROUPS = [
  ['پایه و دسترسی', 'حساب‌ها، مستأجر، نقش، توکن و لاگ.', [
    'tenants', 'users', 'user_tokens', 'refresh_tokens', 'api_keys', 'activity_logs',
    'notifications', 'modules', 'rate_limits', 'ip_whitelist', 'update_log', 'ws_connections']],
  ['صفحه‌ها و دستگاه‌ها (مشترک)', 'هر تلویزیون یا تابلو یک «صفحه» است؛ `screen_type` تعیین می‌کند کدام پروفایل پلیر اجرا شود.', [
    'screens', 'screen_groups', 'screen_group_members', 'screen_commands', 'screen_events',
    'screen_heartbeats', 'heartbeats', 'enrollment_tokens', 'apk_versions', 'locations', 'venues',
    'monitor_3d_configs', 'widgets']],
  ['محتوا و تابلوی دیجیتال', 'رسانه، پلی‌لیست، زمان‌بندی و قالب. تلویزیون اتاق پلی‌لیست پخش نمی‌کند.', [
    'media', 'media_subtitles', 'playlists', 'playlist_items', 'screen_playlists', 'schedules',
    'layouts', 'content_items', 'campaigns', 'screen_messages', 'tv_intro_items', 'news_feeds',
    'menu_boards', 'menu_board_pages']],
  ['IPTV و پخش زنده', 'کانال، منوی اتاق، EPG، ضبط و تیونر.', [
    'iptv_channels', 'iptv_channel_groups', 'iptv_menus', 'iptv_menu_items', 'iptv_menu_backgrounds',
    'iptv_rooms', 'iptv_room_messages', 'tvheadend_sources', 'epg_sources', 'epg_programs',
    'epg_channel_map', 'dvr_recordings', 'dvr_quotas', 'multicast_config', 'transcoder_jobs',
    'cameras', 'portal_live_cache', 'portal_translations']],
  ['ویدیو درخواستی (VOD)', '', [
    'vod_videos', 'vod_watch_history', 'vod_categories', 'vod_subtitles', 'vod_audio_tracks', 'ppv_purchases']],
  ['هتل و مهمان', 'رزرو، صورتحساب، سفارش و خدمات اتاق.', [
    'reservations', 'room_charges', 'checkout_requests', 'guest_services', 'guest_requests',
    'guest_request_items', 'guest_request_log', 'minibar_items', 'minibar_checks',
    'menu_categories', 'menu_items', 'retail_products', 'retail_queue',
    'hotel_info', 'hotel_amenities', 'hotel_attractions', 'hotel_events', 'hotel_room_service',
    'local_offers', 'offer_claims', 'transport_schedules',
    'pms_integrations', 'pms_push_log']],
  ['پرواز و شرکتی', 'تابلوی پرواز فرودگاه و صفحه‌های اداری.', [
    'flights', 'fids_flights', 'fids_airlines', 'inflight_flights',
    'corp_departments', 'corp_kpi', 'corp_news']],
];

const seen = new Set();
GROUPS.forEach(g => g[2].forEach(t => seen.add(t)));
const rest = Object.keys(T).filter(t => !seen.has(t)).sort();
if (rest.length) GROUPS.push(['دسته‌بندی‌نشده', '', rest]);

function esc(s) { return String(s || '').replace(/\|/g, '\\|').replace(/\n/g, ' '); }
function shortType(t) {
  return esc(String(t).replace(/\s+/g, ' ')
    .replace(/ COLLATE [a-z0-9_]+/ig, '')
    .replace(/ CHARACTER SET [a-z0-9_]+/ig, '')
    .trim()).slice(0, 110);
}

let out = [];
out.push('# اسکیمای دیتابیس — Hotel Media');
out.push('');
out.push('> این فایل **تولیدشده** است: `node tools/gen-schema-doc.js` آن را از روی `database/migrations/*.sql` می‌سازد.');
out.push('> دستی ویرایشش نکنید؛ ستون تازه را در یک مهاجرت تازه اضافه کنید و دوباره تولید کنید.');
out.push('');
out.push('منبع حقیقت، خود مهاجرت‌هاست. هر نصب با اجرای ترتیبی `database/migrations/*.sql` ساخته می‌شود');
out.push('(`php artisan db:migrate`). مهاجرت‌ها **قابل اجرای دوباره** نوشته شده‌اند: ستونی که هست دوباره اضافه نمی‌شود.');
out.push('');
const totalCols = Object.values(T).reduce((a, t) => a + Object.keys(t.cols).length, 0);
out.push(`جمع: **${Object.keys(T).length} جدول**، **${totalCols} ستون**، از ${new Set(Object.values(T).map(t => t.from)).size} فایل مهاجرت.`);
out.push('');
out.push('## فهرست');
out.push('');
GROUPS.forEach(([title, , list]) => {
  const have = list.filter(t => T[t]);
  if (have.length) out.push(`- [${title}](#${title.replace(/[^؀-ۿ\w]+/g, '-')}) — ${have.length} جدول`);
});
out.push('');

GROUPS.forEach(([title, desc, list]) => {
  const have = list.filter(t => T[t]);
  if (!have.length) return;
  out.push('## ' + title);
  out.push('');
  if (desc) { out.push(desc); out.push(''); }
  have.forEach(name => {
    const t = T[name];
    out.push('### `' + name + '`');
    out.push('');
    out.push(`<sub>از مهاجرت ${t.from} · ${Object.keys(t.cols).length} ستون</sub>`);
    out.push('');
    out.push('| ستون | نوع | توضیح |');
    out.push('|---|---|---|');
    for (const [col, c] of Object.entries(t.cols)) {
      let note = c.comment || '';
      if (c.from.slice(0,3) !== t.from.slice(0,3)) note = (note ? note + ' ' : '') + `_(افزوده در ${c.from})_`;
      out.push(`| \`${col}\` | ${shortType(c.type)} | ${esc(note)} |`);
    }
    out.push('');
  });
});

fs.writeFileSync('docs/DATABASE-SCHEMA.md', out.join('\n'), 'utf8');
console.log('docs/DATABASE-SCHEMA.md written, ' + out.length + ' lines');
