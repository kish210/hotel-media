-- ============================================================
--  ۰۴۵ — تخفیف کسب‌وکارهای محلی (TODO ۴.۳)
--
--  hotel_attractions فقط معرفی بود. اینجا هر کسب‌وکار طرف قرارداد
--  (رستوران بیرون، مرکز خرید، تور، اسپا) یک پیشنهاد دارد و مهمان از
--  تلویزیون یک کد شخصی می‌گیرد. کد شخصی است تا کسب‌وکار بفهمد مهمان
--  همین هتل است و هتل بفهمد کدام معرفی به خرید رسید (کمیسیون).
--
--  تأیید کد: پذیرش از پنل، یا خود کسب‌وکار با لینک اختصاصی
--  (partner_token) بدون حساب کاربری در پنل.
-- ============================================================

CREATE TABLE IF NOT EXISTS `local_offers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`      INT UNSIGNED NOT NULL DEFAULT 1,
  `business_name`  VARCHAR(150) NOT NULL,
  `category`       VARCHAR(40)  NOT NULL DEFAULT 'other' COMMENT 'food, shopping, tour, beauty, fun, other',
  `title`          VARCHAR(200) NOT NULL COMMENT 'مثلا «۲۰٪ تخفیف شام»',
  `description`    TEXT         DEFAULT NULL,
  `terms`          VARCHAR(500) DEFAULT NULL COMMENT 'شرایط استفاده',
  `address`        VARCHAR(300) DEFAULT NULL,
  `phone`          VARCHAR(40)  DEFAULT NULL,
  `distance`       VARCHAR(50)  DEFAULT NULL,
  `image`          VARCHAR(500) DEFAULT NULL,
  `valid_from`     DATE         DEFAULT NULL,
  `valid_to`       DATE         DEFAULT NULL,
  `per_stay_limit` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'چند کد برای هر اقامت',
  `total_limit`    INT UNSIGNED DEFAULT NULL COMMENT 'سقف کل کدها؛ خالی = بی‌سقف',
  `partner_token`  CHAR(32)     NOT NULL COMMENT 'لینک تأیید کد برای خود کسب‌وکار',
  `partner_fails`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `partner_fail_at` DATETIME    DEFAULT NULL,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_offer_partner` (`partner_token`),
  KEY `idx_offer_tenant` (`tenant_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `offer_claims` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    INT UNSIGNED NOT NULL DEFAULT 1,
  `offer_id`     INT UNSIGNED NOT NULL,
  `room_id`      INT UNSIGNED DEFAULT NULL,
  `screen_code`  VARCHAR(32)  DEFAULT NULL,
  `guest_name`   VARCHAR(150) DEFAULT NULL COMMENT 'نام مهمان هنگام گرفتن کد',
  `code`         VARCHAR(12)  NOT NULL,
  `status`       ENUM('issued','redeemed','void') NOT NULL DEFAULT 'issued',
  `redeemed_at`  DATETIME     DEFAULT NULL,
  `redeemed_via` ENUM('panel','partner') DEFAULT NULL,
  `redeemed_by`  INT UNSIGNED DEFAULT NULL COMMENT 'کاربر پنل',
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_claim_code` (`tenant_id`, `code`),
  KEY `idx_claim_offer` (`offer_id`, `status`),
  KEY `idx_claim_room`  (`room_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- کاشی «تخفیف‌های اطراف» روی منوی تلویزیون
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_menu_items' AND COLUMN_NAME='type' AND COLUMN_TYPE LIKE '%''offers''%')=0,
  (SELECT CONCAT('ALTER TABLE iptv_menu_items MODIFY COLUMN type ',
                 REPLACE(COLUMN_TYPE, ')', ',''offers'')'),
                 ' NOT NULL DEFAULT ''live''')
     FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_menu_items' AND COLUMN_NAME='type'),
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
