-- ════════════════════════════════════════════════════════════════════
-- مدل تایم‌لاین چندلایه برای استودیوی پلی‌لیست
-- ════════════════════════════════════════════════════════════════════
--
-- پلی‌لیست تا امروز یک فهرست تک‌لایه بود: playlist_items با sort_order،
-- پشت سر هم پخش می‌شد. استودیوی تازه چند لایه‌ی هم‌زمان می‌خواهد
-- (ویدیو + لوگو + زیرنویس + تبلیغ روی هم).
--
-- ── چرا یک ستون JSON، نه جدول‌های track/clip ───────────────────────
-- بک‌اند و پلیرهای فعلی روی playlist_items کار می‌کنند و نباید بشکنند.
-- مدل تایم‌لاین در یک ستون JSON روی خود پلی‌لیست می‌نشیند و هنگام
-- انتشار به همان playlist_items تختِ موجود «کامپایل» می‌شود:
--
--   تراک ویدیو/تصویر → آیتم‌های پشت‌سرهم (لایه‌ی پایه، مثل امروز)
--   تراک لوگو/متن/overlay → دادهٔ برند و overlayهای پنجره‌دار که پلیر
--                            از قبل می‌فهمد (brand.logo، ticker، instant)
--
-- پس تایم‌لاین حالت ویرایش است و playlist_items خروجی اجراست. این یعنی
-- هیچ پلیری لازم نیست برای دیدن نتیجه عوض شود، و اگر استودیو هیچ‌وقت
-- استفاده نشود، پلی‌لیست دقیقا مثل قبل کار می‌کند.
--
-- ── draft و published ──────────────────────────────────────────────
-- ویرایش نباید بلافاصله پخش را عوض کند (بند ۲۰ درخواست). تایم‌لاین
-- پیش‌نویس جدا از نسخه‌ی منتشرشده نگه داشته می‌شود؛ انتشار، پیش‌نویس
-- را به playlist_items کامپایل می‌کند.
--
-- این مهاجرت idempotent است.

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='playlists'
                AND COLUMN_NAME='timeline');

SET @sql := IF(@has = 0,
  "ALTER TABLE playlists
     ADD COLUMN timeline LONGTEXT DEFAULT NULL
         COMMENT 'مدل تایم‌لاین چندلایه (JSON) — حالت ویرایش استودیو',
     ADD COLUMN timeline_published LONGTEXT DEFAULT NULL
         COMMENT 'آخرین تایم‌لاینی که منتشر و به playlist_items کامپایل شد',
     ADD COLUMN timeline_saved_at TIMESTAMP NULL DEFAULT NULL
         COMMENT 'زمان آخرین ذخیره‌ی پیش‌نویس',
     ADD COLUMN timeline_published_at TIMESTAMP NULL DEFAULT NULL
         COMMENT 'زمان آخرین انتشار'",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
