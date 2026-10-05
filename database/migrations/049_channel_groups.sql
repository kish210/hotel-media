-- ═══════════════════════════════════════════════════════════════════
-- 049 — گروه کانال واقعی (فاز ۴ بازسازی)
-- ═══════════════════════════════════════════════════════════════════
-- وضعیت پیش از این تغییر، از روی خودِ کد و اسکیما:
--
--   • گروه‌بندی کانال یک ستون **متن آزاد** بود: `iptv_channels.category`
--     ‏`varchar(100)`. فرم پنل آن را با placeholder «news» می‌گرفت و
--     import از TVHeadend مقدار `$ch['group']` یا رشتهٔ «imported» را
--     می‌نوشت. نتیجهٔ قطعی: «News» و «news» و «اخبار» سه گروه متفاوت
--     می‌شدند و هیچ‌وقت کنار هم نمی‌نشستند.
--
--   • `ChannelAccessService::forRoom()` ستون `category` را به تلویزیون
--     می‌فرستاد، ولی `player/guest/live.php` هیچ ارجاعی به آن نداشت —
--     یعنی مهمان یک فهرست صافِ همهٔ کانال‌ها می‌دید. در هتلی با ۸۰
--     کانال، همین «گروه» تمام فایدهٔ فهرست است.
--
-- پس حلقهٔ «گروه کانال» در زنجیرهٔ منبع→کانال→گروه→EPG→پخش، تنها
-- حلقه‌ای بود که واقعا وجود نداشت؛ بقیه هست و کار می‌کند.
--
-- ستون `category` حذف نمی‌شود: مقدار خامِ آمده از هدِند است و برای
-- ردگیری «این کانال در TVHeadend زیر چه گروهی بود» ارزش دارد. ولی از
-- این پس مرجعِ گروه‌بندی `group_id` است.
--
-- روادار است: اجرای دوباره گروه تکراری نمی‌سازد (slug یکتا) و
-- ‏group_id های تعیین‌شده را دست‌کاری نمی‌کند.
-- ═══════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `iptv_channel_groups` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `name_en`     VARCHAR(120) DEFAULT NULL,
  -- slug از نام ساخته می‌شود و یکتاست؛ همین چیزی است که import با آن
  -- گروه موجود را پیدا می‌کند تا هر بار گروه تازه نسازد
  `slug`        VARCHAR(140) NOT NULL,
  `icon`        VARCHAR(40)  DEFAULT NULL,
  `color`       VARCHAR(7)   DEFAULT NULL,
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  -- گروه بزرگسال را می‌شود یک‌جا پشت قفل والدین گذاشت، به‌جای
  -- علامت‌زدن تک‌تک کانال‌ها
  `is_adult`    TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chgroup_slug` (`tenant_id`, `slug`),
  KEY `idx_chgroup_sort` (`tenant_id`, `is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `iptv_channels`
  ADD COLUMN IF NOT EXISTS `group_id` INT UNSIGNED DEFAULT NULL AFTER `category`;

-- فهرست کانال مهمان با همین ترتیب خوانده می‌شود
ALTER TABLE `iptv_channels`
  ADD INDEX IF NOT EXISTS `idx_ch_group` (`tenant_id`, `group_id`, `sort_order`);

-- ── انتقال داده: هر مقدار متنی موجود یک گروه می‌شود ────────────────
-- ‏slug از نام نرمال‌شده ساخته می‌شود تا «News» و «news» یکی شوند.
-- فاصله‌ها به خط تیره، و حروف کوچک.
INSERT IGNORE INTO `iptv_channel_groups` (`tenant_id`, `name`, `slug`, `sort_order`)
SELECT c.tenant_id,
       MIN(c.category),
       LOWER(REPLACE(TRIM(c.category), ' ', '-')),
       0
  FROM `iptv_channels` c
 WHERE c.category IS NOT NULL AND TRIM(c.category) <> ''
 GROUP BY c.tenant_id, LOWER(REPLACE(TRIM(c.category), ' ', '-'));

-- ‏COLLATE صریح: این دیتابیس مختلط است — `iptv_channels` با
-- ‏utf8mb4_general_ci ساخته شده و جدول تازه با پیش‌فرض دیتابیس
-- (utf8mb4_unicode_ci). مقایسهٔ رشته‌ای بین این دو با خطای
-- «Illegal mix of collations» رد می‌شود.
--
-- عمدا collation جدول را عوض نکردیم: این برخورد فقط در همین کوئریِ
-- یک‌بارهٔ انتقال داده وجود دارد. از این پس اتصال دو جدول با
-- ‏`c.group_id = g.id` است، یعنی عدد، و هیچ مقایسهٔ رشته‌ای بین
-- آن‌ها باقی نمی‌ماند.
UPDATE `iptv_channels` c
  JOIN `iptv_channel_groups` g
    ON g.tenant_id = c.tenant_id
   AND g.slug COLLATE utf8mb4_general_ci = LOWER(REPLACE(TRIM(c.category), ' ', '-'))
   SET c.group_id = g.id
 WHERE c.group_id IS NULL
   AND c.category IS NOT NULL AND TRIM(c.category) <> '';
