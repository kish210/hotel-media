-- ── 056: کانال‌های تیونر unicast‌اند ───────────────────────────────────
-- «تخصیص آدرس multicast» به کانال‌های تیونر هم آدرس 239.1.1.x می‌داد، در
-- حالی که هیچ فرستنده‌ای روی آن‌ها نیست. تلویزیون بومی (Tizen/webOS) اول سراغ
-- همان منبع مرده می‌رفت و ۱۲ ثانیه سیاه می‌ماند. اینجا برمی‌گردند به unicast.
UPDATE iptv_channels
   SET delivery = 'unicast', multicast_url = NULL
 WHERE tvh_uuid IS NOT NULL AND delivery <> 'unicast';
