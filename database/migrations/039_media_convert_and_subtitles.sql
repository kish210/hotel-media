-- ═══════════════════════════════════════════════════════════════════
-- 039 — تبدیل خودکار ویدیو هنگام آپلود + زیرنویس روی رسانه‌ی تابلو
-- ═══════════════════════════════════════════════════════════════════
-- مشکل واقعی که دیده شد: آپلود فایل .mov با خطا رد می‌شد، چون فهرست
-- مجاز فقط mp4/webm/ogg بود و .mov نوع video/quicktime دارد. تلویزیون
-- هتلی هم quicktime نمی‌خواند، پس فقط بازکردن فهرست کافی نیست —
-- فایل باید به MP4 با H.264/AAC تبدیل شود.
--
-- چرا وضعیت لازم است: تبدیل در پس‌زمینه انجام می‌شود، نه داخل همان
-- درخواست. ‏max_execution_time روی pool برابر ۳۰۰ ثانیه است و یک ویدیوی
-- چندصد مگابایتی وسط تبدیل قطع می‌شد. تا وقتی status=processing است،
-- پلیر این آیتم را رد می‌کند تا تابلو صفحه‌ی سیاه نشان ندهد.
-- ═══════════════════════════════════════════════════════════════════

ALTER TABLE `media`
  ADD COLUMN `status` ENUM('ready','processing','failed') NOT NULL DEFAULT 'ready'
  COMMENT 'processing = ffmpeg در حال تبدیل است';

ALTER TABLE `media`
  ADD COLUMN `conv_note` VARCHAR(300) DEFAULT NULL
  COMMENT 'دلیل شکست تبدیل، برای نمایش به اپراتور';

ALTER TABLE `media` ADD INDEX `idx_media_status` (`tenant_id`, `status`);

-- ── زیرنویس روی رسانه‌ی کتابخانه ─────────────────────────────────
-- ‏vod_subtitles به vod_id گره خورده و برای تابلو به‌کار نمی‌آید.
-- ساختار عمدا شبیه آن نگه داشته شده تا SubtitleService::toVtt()
-- بدون تغییر برای هر دو استفاده شود: همیشه WebVTT ذخیره می‌شود،
-- چون تگ <track> فقط همان را می‌خواند.
CREATE TABLE IF NOT EXISTS `media_subtitles` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT UNSIGNED  NOT NULL DEFAULT 1,
  `media_id`      INT UNSIGNED  NOT NULL,
  `lang`          VARCHAR(10)   NOT NULL COMMENT 'fa, en, ar …',
  `label`         VARCHAR(80)   NOT NULL COMMENT 'نامی که روی تلویزیون دیده می‌شود',
  `source_format` ENUM('srt','vtt','ass','sub') NOT NULL DEFAULT 'srt',
  `file_path`     VARCHAR(1000) NOT NULL COMMENT 'مسیر فایل .vtt نسبت به public',
  `file_size`     INT UNSIGNED  NOT NULL DEFAULT 0,
  `is_default`    TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'بدون انتخاب کاربر روشن باشد',
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msub_media` (`media_id`),
  CONSTRAINT `fk_msub_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
