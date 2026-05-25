-- ماژول FIDS
CREATE TABLE IF NOT EXISTS `fids_airlines` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(5) NOT NULL UNIQUE,
    `name_fa` VARCHAR(100) NOT NULL,
    `name_en` VARCHAR(100) NOT NULL,
    `logo_url` VARCHAR(500) DEFAULT NULL,
    `country` VARCHAR(50) DEFAULT NULL,
    `color` VARCHAR(7) DEFAULT '#FFFFFF',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `fids_airlines` (`code`,`name_fa`,`name_en`,`color`) VALUES
('IR','هواپیمایی ایران ایر','Iran Air','#0B6EA9'),
('W5','هواپیمایی ماهان','Mahan Air','#C8102E'),
('EP','ایران ایرتور','Iran Airtour','#00A86B'),
('TK','ترکیش ایرلاینز','Turkish Airlines','#E81932'),
('EK','امارات','Emirates','#D71921'),
('QR','قطر ایرویز','Qatar Airways','#5C0632');

CREATE TABLE IF NOT EXISTS `fids_flights` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `flight_number` VARCHAR(20) NOT NULL,
    `airline_code` VARCHAR(5) NOT NULL,
    `airline_name` VARCHAR(100) NOT NULL,
    `airline_name_en` VARCHAR(100) DEFAULT NULL,
    `airline_logo` VARCHAR(500) DEFAULT NULL,
    `type` ENUM('departure','arrival') NOT NULL DEFAULT 'departure',
    `origin` VARCHAR(100) DEFAULT NULL,
    `origin_code` VARCHAR(5) DEFAULT NULL,
    `destination` VARCHAR(100) DEFAULT NULL,
    `destination_code` VARCHAR(5) DEFAULT NULL,
    `scheduled_time` DATETIME NOT NULL,
    `estimated_time` DATETIME DEFAULT NULL,
    `actual_time` DATETIME DEFAULT NULL,
    `terminal` VARCHAR(20) DEFAULT NULL,
    `gate` VARCHAR(20) DEFAULT NULL,
    `belt` VARCHAR(20) DEFAULT NULL,
    `status` ENUM('scheduled','boarding','departed','arrived','delayed','cancelled','diverted','gate_change') NOT NULL DEFAULT 'scheduled',
    `status_fa` VARCHAR(50) DEFAULT NULL,
    `delay_minutes` SMALLINT DEFAULT 0,
    `aircraft_type` VARCHAR(50) DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `remarks_en` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fids_tenant` (`tenant_id`),
    KEY `idx_fids_time` (`scheduled_time`),
    KEY `idx_fids_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ماژول Hotel
CREATE TABLE IF NOT EXISTS `hotel_info` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `hotel_name` VARCHAR(255) NOT NULL DEFAULT 'هتل ما',
    `hotel_name_en` VARCHAR(255) DEFAULT NULL,
    `slogan` VARCHAR(500) DEFAULT NULL,
    `logo` VARCHAR(500) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `website` VARCHAR(500) DEFAULT NULL,
    `checkin_time` TIME DEFAULT '14:00:00',
    `checkout_time` TIME DEFAULT '12:00:00',
    `wifi_name` VARCHAR(100) DEFAULT NULL,
    `wifi_pass` VARCHAR(100) DEFAULT NULL,
    `stars` TINYINT UNSIGNED DEFAULT 5,
    `settings` JSON DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_hotel_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hotel_events` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `title_en` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `hall_name` VARCHAR(100) DEFAULT NULL,
    `floor` VARCHAR(20) DEFAULT NULL,
    `start_at` DATETIME NOT NULL,
    `end_at` DATETIME DEFAULT NULL,
    `organizer` VARCHAR(255) DEFAULT NULL,
    `capacity` INT UNSIGNED DEFAULT NULL,
    `type` ENUM('conference','wedding','seminar','party','exhibition','other') DEFAULT 'conference',
    `image` VARCHAR(500) DEFAULT NULL,
    `color` VARCHAR(7) DEFAULT '#d4af37',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_hotel_events_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hotel_amenities` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `name_en` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `icon` VARCHAR(100) DEFAULT 'fas fa-star',
    `floor` VARCHAR(20) DEFAULT NULL,
    `hours` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `sort_order` SMALLINT UNSIGNED DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_hotel_amenities_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `hotel_amenities` (`tenant_id`,`name`,`name_en`,`icon`,`floor`,`hours`,`sort_order`) VALUES
(1,'رستوران اصلی','Main Restaurant','fas fa-utensils','طبقه همکف','07:00 - 23:00',1),
(1,'استخر','Swimming Pool','fas fa-person-swimming','طبقه پنجم','06:00 - 22:00',2),
(1,'سالن ورزشی','Fitness Center','fas fa-dumbbell','طبقه زیرزمین','06:00 - 22:00',3),
(1,'اسپا','Spa & Wellness','fas fa-spa','طبقه پنجم','09:00 - 21:00',4),
(1,'پارکینگ','Parking','fas fa-parking','زیرزمین','24 ساعت',7),
(1,'Wi-Fi رایگان','Free WiFi','fas fa-wifi','همه طبقات','24 ساعت',8);

CREATE TABLE IF NOT EXISTS `hotel_room_service` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `category_en` VARCHAR(100) DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `name_en` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `currency` VARCHAR(3) DEFAULT 'IRR',
    `image` VARCHAR(500) DEFAULT NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `available_from` TIME DEFAULT NULL,
    `available_to` TIME DEFAULT NULL,
    `sort_order` SMALLINT UNSIGNED DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_hotel_rs_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hotel_attractions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `name_en` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `distance` VARCHAR(50) DEFAULT NULL,
    `image` VARCHAR(500) DEFAULT NULL,
    `map_url` VARCHAR(1000) DEFAULT NULL,
    `sort_order` SMALLINT UNSIGNED DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_hotel_attr_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ماژول Corporate
CREATE TABLE IF NOT EXISTS `corp_kpi` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `value` VARCHAR(100) NOT NULL,
    `target` VARCHAR(100) DEFAULT NULL,
    `unit` VARCHAR(50) DEFAULT NULL,
    `change_pct` DECIMAL(5,2) DEFAULT NULL,
    `icon` VARCHAR(100) DEFAULT 'fas fa-chart-line',
    `color` VARCHAR(7) DEFAULT '#6366f1',
    `sort_order` SMALLINT UNSIGNED DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_corp_kpi_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `corp_kpi` (`tenant_id`,`name`,`value`,`target`,`unit`,`change_pct`,`icon`,`color`,`sort_order`) VALUES
(1,'فروش ماهانه','۱۲۵,۰۰۰','۱۵۰,۰۰۰','میلیون تومان',8.3,'fas fa-chart-line','#22c55e',1),
(1,'تعداد مشتریان','۴,۸۲۰','۵,۰۰۰','نفر',2.1,'fas fa-users','#6366f1',2),
(1,'رضایت مشتری','۹۲','۹۵','درصد',-1.2,'fas fa-smile','#f59e0b',3);

CREATE TABLE IF NOT EXISTS `corp_news` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(500) NOT NULL,
    `body` TEXT DEFAULT NULL,
    `image` VARCHAR(500) DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `priority` TINYINT UNSIGNED DEFAULT 5,
    `is_pinned` TINYINT(1) DEFAULT 0,
    `published_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_corp_news_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_departments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `floor` VARCHAR(20) DEFAULT NULL,
    `room` VARCHAR(20) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `manager` VARCHAR(255) DEFAULT NULL,
    `icon` VARCHAR(100) DEFAULT 'fas fa-door-open',
    `sort_order` SMALLINT UNSIGNED DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_corp_dept_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ماژول Retail
CREATE TABLE IF NOT EXISTS `retail_products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `name_en` VARCHAR(255) DEFAULT NULL,
    `price` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `old_price` DECIMAL(15,2) DEFAULT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'تومان',
    `unit` VARCHAR(50) DEFAULT NULL,
    `image` VARCHAR(500) DEFAULT NULL,
    `barcode` VARCHAR(100) DEFAULT NULL,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_offer` TINYINT(1) NOT NULL DEFAULT 0,
    `offer_ends` DATETIME DEFAULT NULL,
    `stock` INT DEFAULT NULL,
    `sort_order` SMALLINT UNSIGNED DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_retail_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `retail_queue` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `counter` VARCHAR(50) NOT NULL,
    `ticket_number` INT UNSIGNED NOT NULL,
    `called_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('waiting','serving','done') DEFAULT 'serving',
    PRIMARY KEY (`id`),
    KEY `idx_queue_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ماژول Transport
CREATE TABLE IF NOT EXISTS `transport_schedules` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `type` ENUM('bus','metro','train','tram') NOT NULL DEFAULT 'bus',
    `line` VARCHAR(100) NOT NULL,
    `direction` VARCHAR(255) NOT NULL,
    `station` VARCHAR(255) NOT NULL,
    `departure` TIME NOT NULL,
    `frequency_min` SMALLINT UNSIGNED DEFAULT NULL,
    `days` JSON DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_trans_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- اصلاح ستون uploaded_by
ALTER TABLE `media` MODIFY COLUMN `uploaded_by` INT UNSIGNED DEFAULT NULL;

-- ثبت ماژول‌ها
INSERT IGNORE INTO `modules` (`id`,`tenant_id`,`name`,`version`,`is_active`) VALUES
('fids',      1,'سامانه اطلاع‌رسانی پرواز (FIDS)','1.2.0',1),
('hotel',     1,'اطلاع‌رسانی هتل','1.1.0',1),
('menu',      1,'منوی رستوران','2.0.0',1),
('transport', 1,'حمل‌ونقل عمومی','1.0.0',0),
('retail',    1,'فروشگاه و خرده‌فروشی','1.0.0',0),
('corporate', 1,'اطلاع‌رسانی سازمانی','1.0.0',0);

SELECT 'Module tables created successfully!' AS status;
