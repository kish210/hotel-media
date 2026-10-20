-- ============================================================
--  ۰۵۸ — تاریخچه‌ی تماشای فیلم (TODO ۳.۳)
--
--  «ادامه‌ی تماشا» و پیشنهاد بر اساس علاقه. هر ردیف به یک اقامت بسته
--  است (stay_started_at = check_in_at اتاق)، پس مهمان بعدی همان اتاق
--  فیلم‌های مهمان قبلی را نمی‌بیند؛ ولی آمار «پربیننده‌ترین» برای هتل
--  می‌ماند.
-- ============================================================
CREATE TABLE IF NOT EXISTS `vod_watch_history` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`       INT UNSIGNED NOT NULL DEFAULT 1,
  `room_id`         INT UNSIGNED NOT NULL,
  `stay_started_at` DATETIME     NOT NULL,
  `video_id`        INT UNSIGNED NOT NULL,
  `position`        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ثانیه',
  `duration`        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ثانیه، از خود پخش‌کننده',
  `completed`       TINYINT(1)   NOT NULL DEFAULT 0,
  `hidden`          TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'مهمان از «ادامه‌ی تماشا» برداشت',
  `started_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_stay_video` (`room_id`, `stay_started_at`, `video_id`),
  KEY `idx_hist_video` (`tenant_id`, `video_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
