-- ═══════════════════════════════════════════════════════════════════
-- 047 — خط لولهٔ پردازش VOD (فاز ۵ بازسازی)
-- ═══════════════════════════════════════════════════════════════════
-- مشکل واقعی که در کد دیده شد، نه حدس:
--
-- ‏`VodController::upload()` فایل را می‌گرفت و بی‌درنگ `status='ready'`
-- می‌گذاشت، ولی پیش از آن ffmpeg را **داخل همان درخواست HTTP** صدا
-- می‌زد. سه پیامد:
--
--  ۱) مسدودکننده: یک فیلم چند گیگابایتی از `request_terminate_timeout`
--     رد می‌شد. اتصال می‌مرد، هیچ ردیفی در دیتابیس ثبت نمی‌شد، و
--     ffmpeg در پس‌زمینه ادامه می‌داد. کاربر فقط «ناموفق» می‌دید —
--     همان گزارشی که برای فایل .mov داده شد.
--
--  ۲) کدک‌کور: مسیر سریع `-c copy` بود. این دستور H.265 را بی‌اعتراض
--     داخل MPEG-TS کپی می‌کند و چون فایل خروجی > ۱ کیلوبایت می‌شد،
--     «موفق» شمرده می‌شد، فایل اصلی **حذف** می‌شد و وضعیت `ready`
--     ثبت می‌شد. نتیجه: فیلمی که هیچ تلویزیون هتلی پخشش نمی‌کند، در
--     فهرست «آماده» می‌نشست و اصلش هم دیگر وجود نداشت.
--
--  ۳) خروجی MPEG-TS بی‌قید: تنها پلیر موجود این ردیف‌ها
--     (`resources/views/admin/vod/index.php`) یک تگ <video> است که
--     `file_path` را می‌خواند؛ هیچ مرورگری .ts را پخش نمی‌کند. پس
--     تبدیل، سازگاری را کم می‌کرد نه زیاد.
--
-- چرا مدل وضعیت اینجاست و نه در `transcoder_jobs`:
-- آن جدول از قبل `mode ENUM('live','vod')` دارد و وسوسه‌کننده بود، ولی
-- ‏`TranscoderService::superviseOnce()` هر ردیفی را که
-- ‏`status IN ('starting','running')` باشد برمی‌دارد و اگر فرایندش را
-- زنده نبیند دوباره راه می‌اندازد. اگر خط لولهٔ آپلود وضعیتش را آنجا
-- می‌نوشت، دو ناظر روی یک ffmpeg می‌شدند — کار تمام‌شده دوباره شروع
-- می‌شد یا وسط کار kill. پس وضعیت همان جایی می‌ماند که صاحب داده است
-- و `job_id` فقط برای وقتی است که اپراتور دستی کار ترنسکد می‌سازد.
--
-- مقدار قدیمی `error` به `failed` نقشه می‌خورد. روادار است.
-- ═══════════════════════════════════════════════════════════════════

-- وضعیت‌های مشخصات: QUEUED / PROCESSING / READY / FAILED / CANCELLED
ALTER TABLE `vod_videos`
  MODIFY COLUMN `status`
  ENUM('queued','processing','ready','failed','cancelled','error')
  NOT NULL DEFAULT 'queued';

UPDATE `vod_videos` SET `status` = 'failed' WHERE `status` = 'error';

-- حالا که داده پاک شد، مقدار منسوخ از ENUM بیرون می‌رود
ALTER TABLE `vod_videos`
  MODIFY COLUMN `status`
  ENUM('queued','processing','ready','failed','cancelled')
  NOT NULL DEFAULT 'queued';

ALTER TABLE `vod_videos`
  -- چرا متن خطا در دیتابیس: خط آخر stderr ffmpeg تنها چیزی است که
  -- می‌گوید «کدک پشتیبانی نمی‌شود» یا «فضای دیسک تمام شد». قبلاً با
  -- ‏2>/dev/null دور ریخته می‌شد و اپراتور هیچ سرنخی نداشت.
  ADD COLUMN IF NOT EXISTS `conv_note`     VARCHAR(500) DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `conv_progress` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `conv_note`,
  -- passthrough | remux | transcode — تصمیمی که probe گرفته، برای
  -- اینکه اپراتور بداند چرا یکی ۲ ثانیه و دیگری ۴۰ دقیقه طول کشید
  ADD COLUMN IF NOT EXISTS `conv_action`   VARCHAR(16)  DEFAULT NULL AFTER `conv_progress`,
  ADD COLUMN IF NOT EXISTS `job_id`        INT UNSIGNED DEFAULT NULL AFTER `conv_action`,
  -- فایل اصلی دیگر حذف نمی‌شود تا پردازش دوباره ممکن باشد
  ADD COLUMN IF NOT EXISTS `source_path`   VARCHAR(255) DEFAULT NULL AFTER `file_path`,
  -- خروجی HLS برای تلویزیون‌هایی که MP4 پیشرو را دوست ندارند
  ADD COLUMN IF NOT EXISTS `hls_path`      VARCHAR(255) DEFAULT NULL AFTER `source_path`,
  ADD COLUMN IF NOT EXISTS `audio_codec`   VARCHAR(32)  DEFAULT NULL AFTER `codec`;

-- صف: کار بعدی باید با یک ایندکس پیدا شود، نه با پیمایش کل جدول
ALTER TABLE `vod_videos` ADD INDEX IF NOT EXISTS `idx_vod_status` (`tenant_id`, `status`);

-- ردیف‌های موجود واقعاً آماده‌اند (فایلشان هست) — صف نباید آن‌ها را بردارد
UPDATE `vod_videos` SET `conv_action` = 'legacy' WHERE `status` = 'ready' AND `conv_action` IS NULL;
