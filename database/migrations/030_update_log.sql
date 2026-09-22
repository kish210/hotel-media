-- ════════════════════════════════════════════════════════════════════
-- تاریخچه‌ی به‌روزرسانی سرور
-- ════════════════════════════════════════════════════════════════════
--
-- زنجیره‌ی به‌روزرسانی در هتل:
--   گیت‌هاب (انتشار پایدار) → سرور هتل → ۳۰۰ تلویزیون
--
-- بدون تاریخچه، وقتی هتلی می‌گوید «از دیروز کار نمی‌کند» هیچ راهی
-- نیست بفهمیم چه نسخه‌ای و کِی نصب شده. این جدول کوچک است و هیچ‌وقت
-- بزرگ نمی‌شود (چند سطر در سال).
--
-- ستون tenant_id ندارد: به‌روزرسانی مال کل سرور است، نه یک مستاجر.
--
-- این مهاجرت idempotent است.

CREATE TABLE IF NOT EXISTS update_log (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  from_version  VARCHAR(20)  NOT NULL,
  to_version    VARCHAR(20)  NOT NULL,
  success       TINYINT(1)   NOT NULL DEFAULT 0,
  note          VARCHAR(500) DEFAULT NULL COMMENT 'پیام نتیجه یا دلیل شکست',
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── جدول نسخه‌های APK ────────────────────────────────────────────────
-- ‏AppUpdateController از این می‌خواند ولی هیچ مهاجرتی نمی‌ساختش، پس
-- روی نصب تازه بررسی نسخه‌ی اپ اندروید با خطای «جدول وجود ندارد»
-- می‌افتاد. سرور بعد از به‌روزرسانی، APK را از همان انتشار گیت‌هاب
-- برمی‌دارد و اینجا ثبت می‌کند تا تلویزیون‌های اتاق — که اینترنت
-- ندارند — از خود سرور بگیرند.
CREATE TABLE IF NOT EXISTS apk_versions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  version_code  INT UNSIGNED NOT NULL COMMENT 'عدد صعودی؛ اپ با این مقایسه می‌کند',
  version_name  VARCHAR(20)  NOT NULL,
  apk_filename  VARCHAR(190) NOT NULL COMMENT 'داخل public/apk',
  file_size     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  changelog     TEXT         DEFAULT NULL,
  force_update  TINYINT(1)   NOT NULL DEFAULT 0,
  min_version   INT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_code (version_code),
  KEY idx_active (is_active, version_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
