-- ── مدیریت دستگاه‌ها (تلویزیون هتل) ─────────────────────────────
-- فاز ۴ نقشه‌راه: docs/TODO.md (۵.۴)
-- هدف: IT هتل بتواند ۲۰۰ تلویزیون LG / Samsung / Android TV را زنده ببیند
-- و مدیریت کند — ثبت خودکار، تخصیص اتاق، فرمان زنده، نسخه‌ی اپ.
--
-- ‏screens از قبل code / activation_code / last_seen_at / device_info دارد؛
-- این migration فقط چیزهای نداشته را اضافه می‌کند.

-- ── ۱) شناسه‌ی سخت‌افزاری و پلتفرم روی screens ────────────────────
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='platform')=0,
  "ALTER TABLE screens ADD COLUMN platform ENUM('android','webos','tizen','windows','browser','unknown') NOT NULL DEFAULT 'unknown' COMMENT 'android=Android TV, webos=LG, tizen=Samsung'",
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='mac_address')=0,
  'ALTER TABLE screens ADD COLUMN mac_address VARCHAR(17) DEFAULT NULL COMMENT "شناسه پایدار دستگاه برای ثبت خودکار"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='serial_number')=0,
  'ALTER TABLE screens ADD COLUMN serial_number VARCHAR(60) DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='model')=0,
  'ALTER TABLE screens ADD COLUMN model VARCHAR(80) DEFAULT NULL COMMENT "مثلا 43UT570H یا HG43AU800"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='firmware')=0,
  'ALTER TABLE screens ADD COLUMN firmware VARCHAR(60) DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='app_version')=0,
  'ALTER TABLE screens ADD COLUMN app_version VARCHAR(30) DEFAULT NULL COMMENT "نسخه اپ روی دستگاه"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='enrolled_at')=0,
  'ALTER TABLE screens ADD COLUMN enrolled_at DATETIME DEFAULT NULL COMMENT "زمان ثبت خودکار دستگاه"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND INDEX_NAME='idx_mac')=0,
  'ALTER TABLE screens ADD INDEX idx_mac (mac_address)',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND INDEX_NAME='idx_platform')=0,
  'ALTER TABLE screens ADD INDEX idx_platform (tenant_id, platform)',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── ۲) صف فرمان ─────────────────────────────────────────────────
-- تا امروز فرمان‌ها پرچم بولی روی screens بودند (reboot_requested و …)،
-- پس نه چند فرمان همزمان ممکن بود، نه پارامتر داشت، نه معلوم می‌شد
-- دستگاه واقعا اجرا کرد یا نه. این جدول هر سه را حل می‌کند.
-- پرچم‌های قدیمی دست‌نخورده می‌مانند تا پلیرهای قدیمی نشکنند.
CREATE TABLE IF NOT EXISTS screen_commands (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id    INT UNSIGNED NOT NULL,
  screen_id    INT UNSIGNED NOT NULL,
  command      VARCHAR(40)  NOT NULL COMMENT 'reboot|refresh|volume|brightness|power|channel|message|update_app|open_url|clear_cache|screenshot',
  payload      TEXT         DEFAULT NULL COMMENT 'JSON پارامترها',
  status       ENUM('pending','sent','done','failed','expired') NOT NULL DEFAULT 'pending',
  result       VARCHAR(400) DEFAULT NULL COMMENT 'پاسخ دستگاه',
  issued_by    INT UNSIGNED DEFAULT NULL COMMENT 'users.id',
  sent_at      DATETIME     DEFAULT NULL,
  acked_at     DATETIME     DEFAULT NULL,
  expires_at   DATETIME     DEFAULT NULL COMMENT 'فرمان کهنه نباید بعدا ناگهان اجرا شود',
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pending (screen_id, status, id),
  KEY idx_tenant  (tenant_id, created_at),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ۳) توکن ثبت دسته‌ای ──────────────────────────────────────────
-- ‏IT هتل یک توکن می‌سازد، در تنظیمات همه‌ی تلویزیون‌ها می‌گذارد، و هر
-- دستگاه خودش ثبت می‌شود. بدون این، برای ۲۰۰ اتاق باید ۲۰۰ بار کد وارد شود.
CREATE TABLE IF NOT EXISTS enrollment_tokens (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id    INT UNSIGNED NOT NULL,
  token        VARCHAR(64)  NOT NULL,
  label        VARCHAR(120) DEFAULT NULL COMMENT 'مثلا «طبقه ۳ - نصب مهر»',
  group_id     INT UNSIGNED DEFAULT NULL COMMENT 'دستگاه‌های ثبت‌شده به این گروه می‌روند',
  menu_id      INT UNSIGNED DEFAULT NULL COMMENT 'منوی IPTV پیش‌فرض',
  screen_type  VARCHAR(20)  NOT NULL DEFAULT 'iptv',
  auto_approve TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '۰ = منتظر تایید IT بماند',
  max_devices  SMALLINT UNSIGNED DEFAULT NULL COMMENT 'سقف ثبت با این توکن',
  used_count   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at   DATETIME     DEFAULT NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_token (token),
  KEY idx_tenant (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ۴) تاریخچه‌ی سلامت دستگاه ───────────────────────────────────
-- برای اینکه IT بفهمد کدام تلویزیون مدام قطع می‌شود.
CREATE TABLE IF NOT EXISTS screen_events (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id  INT UNSIGNED NOT NULL,
  screen_id  INT UNSIGNED NOT NULL,
  event      VARCHAR(30)  NOT NULL COMMENT 'online|offline|enrolled|approved|error|app_update',
  detail     VARCHAR(300) DEFAULT NULL,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_screen (screen_id, created_at),
  KEY idx_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
