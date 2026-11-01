-- ── 058: شمارش تلاش‌های ناموفق ترنسکدر ──────────────────────────────
-- کار زندهٔ دارای ورودی خراب همیشه دوباره اجرا می‌شود و در پنل «در حال
-- شروع» می‌ماند. با شمارش، پیام بعد از سه شکست صریح می‌شود.
SET @db = DATABASE();
SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'transcoder_jobs' AND COLUMN_NAME = 'fail_count');
SET @q = IF(@has = 0,
    "ALTER TABLE transcoder_jobs ADD COLUMN fail_count INT UNSIGNED NOT NULL DEFAULT 0",
    'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- شمارنده با اجرای موفق صفر می‌شود (در کد، هنگام رسیدن به وضعیت running)
