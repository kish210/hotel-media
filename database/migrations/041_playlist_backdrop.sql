-- ════════════════════════════════════════════════════════════════════
-- پس‌زمینه‌ی تابلو — پر کردن جاهای خالیِ ویدیو
-- ════════════════════════════════════════════════════════════════════
--
-- ویدیویی که نسبت تصویرش با صفحه یکی نیست، داخل کادر خودش جا می‌شود و
-- بقیه‌ی صفحه سیاه می‌ماند (نوار بالا-پایین یا چپ-راست). روی یک تابلوی
-- تبلیغاتی این سیاهی مثل خرابی دیده می‌شود، نه مثل طراحی.
--
-- چرا پس‌زمینه و نه «برش تا پر شدن»: برای برش باید نسبت تصویر ویدیو را
-- بدانیم، و videoWidth روی موتور ماپل (نسل ۲۰۱۳ سامسونگ) عدد غلط
-- می‌دهد — روی تلویزیون واقعیِ همین هتل برای یک فایل ۱۶:۹ مقدار
-- 1280x1280 گزارش شد. محاسبه‌ای که بر آن عدد بنشیند روی همان
-- تلویزیون‌هایی خراب می‌شود که این پروفایل برایشان نوشته شده.
-- پس‌زمینه به اندازه‌ی ویدیو کاری ندارد و همیشه درست است.
--
-- ستون video_fit برای پروفایل‌های جدیدتر (تایزن، webOS، اندروید) است که
-- object-fit دارند و می‌توانند ویدیو را تا پر شدن برش بزنند. روی ماپل
-- بی‌اثر است و به همین دلیل هم در فرم پنل نمایش داده نمی‌شود تا
-- اپراتور دکمه‌ای نبیند که روی تلویزیون او کاری نمی‌کند.
--
-- این مهاجرت idempotent است.

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='playlists'
                AND COLUMN_NAME='backdrop_mode');

SET @sql := IF(@has = 0,
  "ALTER TABLE playlists
     ADD COLUMN backdrop_mode ENUM('black','color','logo','image')
         NOT NULL DEFAULT 'black'
         COMMENT 'چه چیزی پشت ویدیو دیده شود',
     ADD COLUMN backdrop_color VARCHAR(9) NOT NULL DEFAULT '#000000',
     ADD COLUMN backdrop_image VARCHAR(500) DEFAULT NULL,
     ADD COLUMN video_fit ENUM('fit','stretch') NOT NULL DEFAULT 'fit'
         COMMENT 'fit نسبت تصویر را نگه می‌دارد، stretch تا پر شدن می‌کشد'",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
