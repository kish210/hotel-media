-- ════════════════════════════════════════════════════════════════════
-- فقط ماژول‌های هتل
-- ════════════════════════════════════════════════════════════════════
--
-- ماژول‌های صنف‌های دیگر (فرودگاه، حمل‌ونقل، فروشگاه، سازمانی،
-- داخل‌پرواز) به شاخه‌ی non-hotel-verticals منتقل شدند، ولی سطرهایشان
-- در جدول modules ماند. نتیجه: صفحه‌ی «ماژول‌ها» ماژول‌هایی را نشان
-- می‌داد که کلاسشان دیگر وجود ندارد و روشن‌کردنشان خطا می‌داد.
--
-- مشکل دوم: iptv و vod هیچ‌وقت در این جدول ثبت نشده بودند، پس
-- modOn('iptv') همیشه false برمی‌گرداند و کل بخش «تلویزیون اتاق» در
-- نوار کناری پنهان می‌ماند — روی سیستمی که تمام کارش همان است.
--
-- این مهاجرت idempotent است.

-- ── حذف ماژول‌های صنف‌های دیگر ───────────────────────────────────────
DELETE FROM modules
 WHERE id IN ('fids', 'transport', 'retail', 'corporate', 'inflight');

-- ── ثبت ماژول‌های هتل ────────────────────────────────────────────────
-- هر مستاجری که از قبل ردیف دارد، ماژول‌های جاافتاده‌اش اضافه می‌شود.
INSERT INTO modules (id, tenant_id, name, version, is_active)
SELECT 'iptv', t.id, 'تلویزیون اتاق (IPTV)', '1.0.0', 1
  FROM tenants t
 WHERE NOT EXISTS (
   SELECT 1 FROM modules m WHERE m.id = 'iptv' AND m.tenant_id = t.id
 );

INSERT INTO modules (id, tenant_id, name, version, is_active)
SELECT 'vod', t.id, 'فیلم و سریال (VOD)', '1.0.0', 1
  FROM tenants t
 WHERE NOT EXISTS (
   SELECT 1 FROM modules m WHERE m.id = 'vod' AND m.tenant_id = t.id
 );

INSERT INTO modules (id, tenant_id, name, version, is_active)
SELECT 'hotel', t.id, 'اطلاع‌رسانی هتل', '1.1.0', 1
  FROM tenants t
 WHERE NOT EXISTS (
   SELECT 1 FROM modules m WHERE m.id = 'hotel' AND m.tenant_id = t.id
 );

INSERT INTO modules (id, tenant_id, name, version, is_active)
SELECT 'menu', t.id, 'منوی رستوران', '2.0.0', 1
  FROM tenants t
 WHERE NOT EXISTS (
   SELECT 1 FROM modules m WHERE m.id = 'menu' AND m.tenant_id = t.id
 );
