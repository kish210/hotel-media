-- ═══════════════════════════════════════════════════════════════════
-- 035 — رنج‌های IP مورد اعتماد برای فعال‌سازی خودکار تلویزیون
-- ═══════════════════════════════════════════════════════════════════
-- تلویزیون‌های هتلی (سامسونگ/ال‌جی) با ریموت نمی‌توانند کد فعال‌سازی
-- تایپ کنند. راه‌حل: اگر IP دستگاه در یک رنج مورد اعتماد باشد، خودکار
-- فعال می‌شود و صفحه‌ی «کد را وارد کنید» اصلا نمایش داده نمی‌شود.
-- پشتیبانی از CIDR (۱۷۲.۳۳.۰.۰/۲۰)، رنج (۱۷۲.۱۶.۱۰۰.۱-۱۷۲.۱۶.۱۰۰.۲۵۴)
-- و تک‌IP. تطبیق در IpWhitelistService انجام می‌شود.
-- ═══════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `ip_whitelist` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL DEFAULT 1,
  `label`      VARCHAR(120) NOT NULL COMMENT 'مثلا «رنج تلویزیون‌های طبقه ۳»',
  `range_spec` VARCHAR(80)  NOT NULL COMMENT 'CIDR یا رنج a-b یا تک IP',
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wl_tenant` (`tenant_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
