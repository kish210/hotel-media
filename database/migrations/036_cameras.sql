-- ═══════════════════════════════════════════════════════════════════
-- 036 — دوربین مداربسته روی تلویزیون اتاق
-- ═══════════════════════════════════════════════════════════════════
-- کاربرد واقعی در هتل: مهمان از اتاق زمین بازی کودکان، لابی یا پارکینگ
-- را ببیند. دوربین RTSP می‌دهد و تلویزیون RTSP نمی‌خواند، پس ffmpeg آن
-- را به HLS تبدیل می‌کند (همان مسیر ماژول ترنسکد: /hls/{stream_name}).
--
-- نکته‌ی امنیتی: rtsp_url معمولا رمز دوربین را در خود دارد و هرگز به
-- سمت مهمان فرستاده نمی‌شود — فقط آدرس HLS بیرون می‌رود.
--
-- محدودیت: HLS روی Tizen و webOS و اندروید کار می‌کند ولی روی سامسونگ
-- Orsay ۲۰۱۳ نه (docs/TV-COMPAT-SIGNAGE.md).
-- ═══════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `cameras` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT UNSIGNED NOT NULL DEFAULT 1,
  `name`          VARCHAR(120) NOT NULL COMMENT 'مثلا «زمین بازی کودکان»',
  `location`      VARCHAR(120) DEFAULT NULL COMMENT 'محل نصب برای اپراتور',
  `rtsp_url`      VARCHAR(500) NOT NULL COMMENT 'آدرس RTSP دوربین — هرگز به مهمان داده نمی‌شود',
  `stream_name`   VARCHAR(60)  NOT NULL COMMENT 'اسلاگ مسیر HLS',
  `quality`       ENUM('low','medium','high','copy') NOT NULL DEFAULT 'medium',
  `audio`         ENUM('mute','include') NOT NULL DEFAULT 'mute' COMMENT 'صدای دوربین معمولا لازم نیست',
  `guest_visible` TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'در پورتال مهمان دیده شود',
  `access_level`  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'حداقل سطح اتاق برای دیدن',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `last_started_at` DATETIME   DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_stream` (`tenant_id`, `stream_name`),
  KEY `idx_cam_tenant` (`tenant_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
