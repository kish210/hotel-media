-- ═══════════════════════════════════════════════════════════════════
-- 034 — ضبط (PVR/NPVR)، Catch-up، و افزونگی کانال
-- ═══════════════════════════════════════════════════════════════════
-- زیرساخت پخش‌کننده‌اش (TVHeadend DVR/timeshift) از قبل در
-- TvheadendApiService پوشش داده شده. این migration فقط لایه‌ی داده‌ی
-- «چه کسی چه چیزی را ضبط کرد و چقدر سهمیه دارد» و تنظیمات catch-up و
-- آدرس پشتیبان کانال (افزونگی، از netup) را اضافه می‌کند.
-- روادار است: دوباره‌اجرا بی‌ضرر (ستون تکراری در ALTER نادیده گرفته می‌شود).
-- ═══════════════════════════════════════════════════════════════════

-- ── ستون‌های تازه‌ی کانال ─────────────────────────────────────────
-- نگاشت مستقیم به کانال TVHeadend (uuid) برای ضبط دقیق؛ پنجره‌ی
-- catch-up؛ آدرس پشتیبان و SRT برای افزونگی وقتی منبع اصلی قطع شد.
ALTER TABLE iptv_channels ADD COLUMN tvh_uuid            VARCHAR(64)  DEFAULT NULL COMMENT 'uuid کانال در TVHeadend برای DVR';
ALTER TABLE iptv_channels ADD COLUMN catchup_enabled     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'بازگشت به عقب فعال است';
ALTER TABLE iptv_channels ADD COLUMN catchup_window_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24 COMMENT 'تا چند ساعت عقب';
ALTER TABLE iptv_channels ADD COLUMN catchup_autorec_uuid VARCHAR(64) DEFAULT NULL COMMENT 'uuid قانون autorec در TVHeadend';
ALTER TABLE iptv_channels ADD COLUMN backup_stream_url   TEXT         DEFAULT NULL COMMENT 'منبع پشتیبان (افزونگی) وقتی اصلی قطع شد';
ALTER TABLE iptv_channels ADD COLUMN srt_url             TEXT         DEFAULT NULL COMMENT 'آدرس SRT اختیاری';

-- ── ضبط‌ها ────────────────────────────────────────────────────────
-- هر ردیف یک ضبط است: PVR (زمان‌بندی‌شده توسط IT)، NPVR (مهمان خودش
-- ضبط کرد)، یا catchup (ماده‌ی موقتِ برنامه‌ی گذشته). uuid همان entry
-- تی‌وی‌هدند است تا وضعیت/حذف با آن هماهنگ بماند.
CREATE TABLE IF NOT EXISTS `dvr_recordings` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`    INT UNSIGNED    NOT NULL DEFAULT 1,
  `kind`         ENUM('pvr','npvr','catchup') NOT NULL DEFAULT 'pvr',
  `channel_id`   INT UNSIGNED    DEFAULT NULL COMMENT 'iptv_channels.id',
  `room_id`      INT UNSIGNED    DEFAULT NULL COMMENT 'صاحب ضبط برای NPVR — iptv_rooms.id',
  `screen_code`  VARCHAR(32)     DEFAULT NULL COMMENT 'صفحه‌ای که درخواست داد',
  `tvh_uuid`     VARCHAR(64)     DEFAULT NULL COMMENT 'uuid ورودی DVR در TVHeadend',
  `event_id`     INT UNSIGNED    DEFAULT NULL COMMENT 'شناسه رویداد EPG (اگر از روی برنامه ضبط شد)',
  `title`        VARCHAR(300)    NOT NULL,
  `starts_at`    DATETIME        NOT NULL,
  `stops_at`     DATETIME        NOT NULL,
  `status`       ENUM('scheduled','recording','finished','failed','removed') NOT NULL DEFAULT 'scheduled',
  `file_url`     VARCHAR(500)    DEFAULT NULL COMMENT 'آدرس پخش فایل ضبط‌شده',
  `size_bytes`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `error`        VARCHAR(300)    DEFAULT NULL,
  `created_by`   INT UNSIGNED    DEFAULT NULL COMMENT 'کاربر پنل، یا NULL برای مهمان',
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dvr_tenant`  (`tenant_id`, `status`),
  KEY `idx_dvr_room`    (`room_id`, `status`),
  KEY `idx_dvr_channel` (`channel_id`),
  KEY `idx_dvr_uuid`    (`tvh_uuid`),
  KEY `idx_dvr_time`    (`starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── سهمیه‌ی NPVR ───────────────────────────────────────────────────
-- سقف ضبط شخصیِ هر اتاق. اگر ردیفی نباشد، از پیش‌فرض tenant استفاده
-- می‌شود (در تنظیمات نگه‌داری می‌شود، نه اینجا).
CREATE TABLE IF NOT EXISTS `dvr_quotas` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `tenant_id`      INT UNSIGNED   NOT NULL DEFAULT 1,
  `room_id`        INT UNSIGNED   NOT NULL COMMENT 'iptv_rooms.id',
  `max_recordings` SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  `max_minutes`    INT UNSIGNED   NOT NULL DEFAULT 1200 COMMENT 'سقف مجموع دقیقه',
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_room` (`tenant_id`, `room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تنظیمات سراسری DVR (فعال بودن NPVR و پیش‌فرض سهمیه) به‌صورت ثابت در
-- DvrService نگه‌داری می‌شود؛ جدول کلید-مقدار عمومی در این استک نداریم.
