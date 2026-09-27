-- ═══════════════════════════════════════════════════════════════════
-- 037 — افزودن پلتفرم orsay به ستون screens.platform
-- ═══════════════════════════════════════════════════════════════════
-- چرا لازم شد: تشخیص پلتفرم در DeviceService از نسخه‌ی قبل نسل Orsay
-- (سامسونگ ۲۰۱۳، با UA حاوی Maple/SmartHub) را جدا از Tizen برمی‌گرداند،
-- ولی ستون یک ENUM بود که این مقدار را نداشت. نتیجه: ثبت هر تلویزیون
-- Orsay با خطای ۵۰۰ رد می‌شد — دقیقا همان تلویزیونی که پروفایل Orsay
-- برایش نوشته شده بود.
--
-- ‏UA واقعی که در سایت دیده شد:
--   Mozilla/5.0 (SmartHub; SMART-TV; U; Linux/SmartTV+2015; Maple2012)
--   AppleWebKit/537.42+ (KHTML, like Gecko) SmartTV Safari/537.42+
--
-- روادار است: اجرای دوباره فقط همان تعریف را می‌نویسد.
-- ═══════════════════════════════════════════════════════════════════

ALTER TABLE `screens`
  MODIFY COLUMN `platform`
  ENUM('android','webos','tizen','orsay','windows','browser','unknown')
  NOT NULL DEFAULT 'unknown';
