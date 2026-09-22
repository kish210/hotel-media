-- ════════════════════════════════════════════════════════════════════
-- کدام پلتفرم‌ها خودشان UDP multicast می‌خوانند
-- ════════════════════════════════════════════════════════════════════
--
-- تا اینجا در PortalController سیم‌کشیِ ثابت بود: فقط اپ بومی اندروید و
-- ویندوز آدرس multicast می‌گرفتند و webOS و Tizen از udpxy رد می‌شدند.
--
-- تست میدانی روی تلویزیون‌های هتلی LG (webOS) و Samsung (Tizen) نشان داد
-- خودِ تلویزیون udp:// را مستقیم می‌خواند. با فرض قبلی، در هتل ۳۰۰ اتاقی
-- سرور باید ۳۰۰ جریان unicast رله می‌کرد — دقیقا همان باری که multicast
-- برای حذفش اضافه شد.
--
-- ولی این به مدل و فرم‌ور تلویزیون بستگی دارد و در هر هتل یکسان نیست،
-- پس به‌جای عوض‌کردن یک ثابت، تصمیم به تنظیمات منتقل می‌شود. پیش‌فرض
-- همان چیزی است که در میدان تایید شد؛ اگر هتلی تلویزیون قدیمی‌تر داشت
-- اپراتور پلتفرم را از فهرست برمی‌دارد و آن دستگاه‌ها به udpxy برمی‌گردند.
--
-- این مهاجرت idempotent است.

-- ── فهرست پلتفرم‌هایی که آدرس multicast مستقیم می‌گیرند ──────────────
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'multicast_config'
    AND COLUMN_NAME  = 'native_platforms'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE multicast_config
     ADD COLUMN native_platforms VARCHAR(120) NOT NULL
       DEFAULT "android,windows,webos,tizen"
       COMMENT "پلتفرم‌هایی که udp:// را مستقیم می‌خوانند؛ بقیه از udpxy"
       AFTER udpxy_url',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ردیف‌هایی که پیش از این مهاجرت ساخته شده‌اند مقدار پیش‌فرض ستون را
-- نمی‌گیرند اگر ستون تازه اضافه شده باشد، پس صریح پر می‌شود.
UPDATE multicast_config
   SET native_platforms = 'android,windows,webos,tizen'
 WHERE native_platforms IS NULL OR native_platforms = '';

-- ── یادداشت پیکربندی سوییچ ────────────────────────────────────────────
-- روی سوییچ سیسکو برای VLAN مربوط به IPTV فقط همین لازم است:
--
--   ip igmp snooping                       ← سراسری، معمولا از پیش روشن است
--   ip igmp snooping vlan <IPTV_VLAN>
--   ip igmp snooping querier               ← سراسری
--   ip igmp snooping vlan <IPTV_VLAN> querier address <IP سوییچ>
--
-- نکته‌ای که اگر رعایت نشود پخش بی‌دلیل قطع‌وصل می‌شود: در کل شبکه باید
-- دقیقا یک querier فعال باشد. اگر روی دو سوییچ روشن شود، آن با آدرس
-- کوچک‌تر برنده می‌شود و دیگری ساکت — ولی در گذارِ انتخاب، جدول عضویت
-- خالی می‌شود و تصویر همه‌ی اتاق‌ها چند ثانیه می‌پرد.
SET @note := 'switch: single IGMP querier per IPTV VLAN';
