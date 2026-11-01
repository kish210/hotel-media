# اسکیمای دیتابیس — Hotel Media

> این فایل **تولیدشده** است: `node tools/gen-schema-doc.js` آن را از روی `database/migrations/*.sql` می‌سازد.
> دستی ویرایشش نکنید؛ ستون تازه را در یک مهاجرت تازه اضافه کنید و دوباره تولید کنید.

منبع حقیقت، خود مهاجرت‌هاست. هر نصب با اجرای ترتیبی `database/migrations/*.sql` ساخته می‌شود
(`php artisan db:migrate`). مهاجرت‌ها **قابل اجرای دوباره** نوشته شده‌اند: ستونی که هست دوباره اضافه نمی‌شود.

جمع: **93 جدول**، **1341 ستون**، از 33 فایل مهاجرت.

## فهرست

- [پایه و دسترسی](#پایه-و-دسترسی) — 12 جدول
- [صفحه‌ها و دستگاه‌ها (مشترک)](#صفحه-ها-و-دستگاه-ها-مشترک-) — 13 جدول
- [محتوا و تابلوی دیجیتال](#محتوا-و-تابلوی-دیجیتال) — 14 جدول
- [IPTV و پخش زنده](#IPTV-و-پخش-زنده) — 18 جدول
- [ویدیو درخواستی (VOD)](#ویدیو-درخواستی-VOD-) — 6 جدول
- [هتل و مهمان](#هتل-و-مهمان) — 23 جدول
- [پرواز و شرکتی](#پرواز-و-شرکتی) — 7 جدول

## پایه و دسترسی

حساب‌ها، مستأجر، نقش، توکن و لاگ.

### `tenants`

<sub>از مهاجرت 001_complete_schema · 13 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `slug` | VARCHAR(80) NOT NULL UNIQUE |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `domain` | VARCHAR(255) DEFAULT NULL |  |
| `logo` | VARCHAR(500) DEFAULT NULL |  |
| `plan` | ENUM('free','starter','pro','enterprise') NOT NULL DEFAULT 'starter' |  |
| `storage_limit` | BIGINT UNSIGNED NOT NULL DEFAULT 10737418240 | 10 GB in bytes |
| `screen_limit` | SMALLINT UNSIGNED NOT NULL DEFAULT 5 |  |
| `settings` | JSON DEFAULT NULL | Tenant-level config overrides |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `trial_ends_at` | TIMESTAMP DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `users`

<sub>از مهاجرت 001_complete_schema · 24 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `email` | VARCHAR(180) NOT NULL |  |
| `password` | VARCHAR(255) NOT NULL |  |
| `role` | ENUM('super_admin','restaurant_manager','content_editor') NOT NULL DEFAULT 'content_editor' |  |
| `avatar` | VARCHAR(255) DEFAULT NULL |  |
| `phone` | VARCHAR(20) DEFAULT NULL |  |
| `language` | VARCHAR(10) NOT NULL DEFAULT 'fa' |  |
| `timezone` | VARCHAR(60) NOT NULL DEFAULT 'Asia/Tehran' |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `email_verified_at` | TIMESTAMP NULL DEFAULT NULL |  |
| `last_login_at` | TIMESTAMP NULL DEFAULT NULL |  |
| `last_login_ip` | VARCHAR(45) DEFAULT NULL |  |
| `two_factor_secret` | VARCHAR(32) DEFAULT NULL |  |
| `two_factor_enabled` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `remember_token` | VARCHAR(100) DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `deleted_at` | TIMESTAMP DEFAULT NULL |  |
| `password_hash` | VARCHAR(255) NOT NULL |  |
| `locale` | VARCHAR(10) NOT NULL DEFAULT 'fa' |  |
| `preferences` | JSON DEFAULT NULL |  |
| `location_id` | INT UNSIGNED DEFAULT NULL COMMENT | _(افزوده در 046_user_branch)_ |

### `user_tokens`

<sub>از مهاجرت 001_complete_schema · 9 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `user_id` | INT UNSIGNED NOT NULL |  |
| `token_hash` | VARCHAR(255) NOT NULL |  |
| `type` | ENUM('access','refresh','reset','verify','api') NOT NULL DEFAULT 'access' |  |
| `device_info` | JSON DEFAULT NULL |  |
| `ip_address` | VARCHAR(45) DEFAULT NULL |  |
| `expires_at` | TIMESTAMP NOT NULL |  |
| `revoked_at` | TIMESTAMP DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |

### `refresh_tokens`

<sub>از مهاجرت 001_initial_schema · 8 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `user_id` | INT UNSIGNED NOT NULL |  |
| `token_hash` | VARCHAR(64) NOT NULL UNIQUE | SHA-256 of token |
| `ip_address` | VARCHAR(45) DEFAULT NULL |  |
| `user_agent` | TEXT DEFAULT NULL |  |
| `expires_at` | TIMESTAMP NOT NULL |  |
| `revoked_at` | TIMESTAMP NULL DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |

### `api_keys`

<sub>از مهاجرت 001_complete_schema · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `user_id` | INT UNSIGNED DEFAULT NULL |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `key_hash` | VARCHAR(64) NOT NULL UNIQUE | SHA-256 of API key |
| `key_prefix` | VARCHAR(8) NOT NULL | First 8 chars for display |
| `permissions` | JSON DEFAULT NULL | Scopes: read, write, admin |
| `last_used_at` | TIMESTAMP NULL DEFAULT NULL |  |
| `expires_at` | TIMESTAMP NULL DEFAULT NULL |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |

### `activity_logs`

<sub>از مهاجرت 001_complete_schema · 14 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED DEFAULT NULL |  |
| `user_id` | INT UNSIGNED DEFAULT NULL |  |
| `screen_id` | INT UNSIGNED DEFAULT NULL |  |
| `action` | VARCHAR(80) NOT NULL | e.g. user.login, screen.paired, playlist.updated |
| `subject_type` | VARCHAR(60) DEFAULT NULL |  |
| `subject_id` | INT UNSIGNED DEFAULT NULL |  |
| `description` | TEXT DEFAULT NULL |  |
| `old_values` | JSON DEFAULT NULL |  |
| `new_values` | JSON DEFAULT NULL |  |
| `ip_address` | VARCHAR(45) DEFAULT NULL |  |
| `user_agent` | TEXT DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `properties` | JSON DEFAULT NULL |  |

### `notifications`

<sub>از مهاجرت 001_complete_schema · 12 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `user_id` | INT UNSIGNED DEFAULT NULL |  |
| `type` | ENUM('screen_offline','screen_online','storage_warning','campaign_expired','campaign_starting','system','emerg |  |
| `title` | VARCHAR(255) NOT NULL |  |
| `body` | TEXT DEFAULT NULL |  |
| `data` | JSON DEFAULT NULL | Extra context: screen_id, campaign_id, etc. |
| `severity` | ENUM('info','warning','error','critical') NOT NULL DEFAULT 'info' |  |
| `read_at` | TIMESTAMP NULL DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `channel` | SET('in_app','email','sms','webhook') NOT NULL DEFAULT 'in_app' |  |
| `sent_at` | TIMESTAMP NULL DEFAULT NULL |  |

### `modules`

<sub>از مهاجرت 001_complete_schema · 8 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | VARCHAR(50) NOT NULL |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `name` | VARCHAR(255) NOT NULL |  |
| `version` | VARCHAR(20) DEFAULT '1.0.0' |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `settings` | JSON DEFAULT NULL |  |
| `installed_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `rate_limits`

<sub>از مهاجرت 001_complete_schema · 7 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `key` | VARCHAR(128) NOT NULL | IP:endpoint or user_id:endpoint |
| `attempts` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `reset_at` | TIMESTAMP NOT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `hits` | SMALLINT UNSIGNED NOT NULL DEFAULT 1 |  |
| `window_start` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |

### `ip_whitelist`

<sub>از مهاجرت 035_ip_whitelist · 6 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `label` | VARCHAR(120) NOT NULL | مثلا «رنج تلویزیون‌های طبقه ۳» |
| `range_spec` | VARCHAR(80) NOT NULL | CIDR یا رنج a-b یا تک IP |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |

### `update_log`

<sub>از مهاجرت 030_update_log · 6 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `from_version` | VARCHAR(20) NOT NULL |  |
| `to_version` | VARCHAR(20) NOT NULL |  |
| `success` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `note` | VARCHAR(500) DEFAULT NULL | پیام نتیجه یا دلیل شکست |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

### `ws_connections`

<sub>از مهاجرت 001_initial_schema · 8 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `connection_id` | VARCHAR(64) NOT NULL UNIQUE |  |
| `tenant_id` | INT UNSIGNED DEFAULT NULL |  |
| `user_id` | INT UNSIGNED DEFAULT NULL |  |
| `screen_id` | INT UNSIGNED DEFAULT NULL |  |
| `type` | ENUM('admin','screen','mobile') NOT NULL DEFAULT 'admin' |  |
| `connected_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `disconnected_at` | TIMESTAMP NULL DEFAULT NULL |  |

## صفحه‌ها و دستگاه‌ها (مشترک)

هر تلویزیون یا تابلو یک «صفحه» است؛ `screen_type` تعیین می‌کند کدام پروفایل پلیر اجرا شود.

### `screens`

<sub>از مهاجرت 001_complete_schema · 49 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `location_id` | INT UNSIGNED DEFAULT NULL |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `code` | VARCHAR(8) NOT NULL UNIQUE |  |
| `activation_code` | VARCHAR(8) DEFAULT NULL | Temp 8-char pairing PIN |
| `activation_expires_at` | TIMESTAMP DEFAULT NULL |  |
| `description` | TEXT DEFAULT NULL |  |
| `orientation` | ENUM('landscape','portrait') NOT NULL DEFAULT 'landscape' |  |
| `resolution` | VARCHAR(20) DEFAULT '1920x1080' |  |
| `status` | ENUM('online','offline','pairing','maintenance') NOT NULL DEFAULT 'pairing' |  |
| `is_online` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `last_seen_at` | TIMESTAMP NULL DEFAULT NULL |  |
| `last_ip` | VARCHAR(45) DEFAULT NULL |  |
| `current_playlist_id` | INT UNSIGNED DEFAULT NULL |  |
| `device_info` | JSON DEFAULT NULL |  |
| `tags` | JSON DEFAULT NULL |  |
| `screenshot_url` | VARCHAR(255) DEFAULT NULL |  |
| `screenshot_at` | TIMESTAMP NULL DEFAULT NULL |  |
| `brightness` | TINYINT UNSIGNED NOT NULL DEFAULT 100 |  |
| `volume` | TINYINT UNSIGNED NOT NULL DEFAULT 70 |  |
| `reboot_requested` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `refresh_requested` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `emergency_broadcast` | TEXT DEFAULT NULL |  |
| `settings` | JSON DEFAULT NULL | Screen-level overrides: brightness, volume, etc. |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `unique_code` | VARCHAR(16) NOT NULL UNIQUE | Device pairing code |
| `ip_address` | VARCHAR(45) DEFAULT NULL |  |
| `mac_address` | VARCHAR(17) DEFAULT NULL |  |
| `os_info` | VARCHAR(120) DEFAULT NULL |  |
| `app_version` | VARCHAR(20) DEFAULT NULL |  |
| `last_heartbeat_at` | TIMESTAMP NULL DEFAULT NULL |  |
| `notes` | TEXT DEFAULT NULL |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `iptv_room_id` | INT UNSIGNED DEFAULT NULL | _(افزوده در 010_iptv_rooms)_ |
| `inflight_flight_id` | INT UNSIGNED DEFAULT NULL | _(افزوده در 011_inflight)_ |
| `screen_type` | ENUM( | _(افزوده در 020_schema_parity)_ |
| `group_id` | INT UNSIGNED DEFAULT NULL | _(افزوده در 020_schema_parity)_ |
| `iptv_channel_id` | INT UNSIGNED DEFAULT NULL | _(افزوده در 020_schema_parity)_ |
| `iptv_menu_id` | INT UNSIGNED DEFAULT NULL | _(افزوده در 020_schema_parity)_ |
| `iptv_settings` | JSON DEFAULT NULL | _(افزوده در 020_schema_parity)_ |
| `pending_commands` | JSON DEFAULT NULL | _(افزوده در 020_schema_parity)_ |
| `platform` | ENUM( | _(افزوده در 021_device_management)_ |
| `serial_number` | VARCHAR(60) DEFAULT NULL | _(افزوده در 021_device_management)_ |
| `model` | VARCHAR(80) DEFAULT NULL COMMENT | _(افزوده در 021_device_management)_ |
| `firmware` | VARCHAR(60) DEFAULT NULL | _(افزوده در 021_device_management)_ |
| `enrolled_at` | DATETIME DEFAULT NULL COMMENT | _(افزوده در 021_device_management)_ |
| `venue_id` | INT UNSIGNED DEFAULT NULL COMMENT | _(افزوده در 026_venues_and_signage)_ |

### `screen_groups`

<sub>از مهاجرت 001_complete_schema · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 006_screen_groups)_ |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 | _(افزوده در 006_screen_groups)_ |
| `name` | VARCHAR(100) NOT NULL | _(افزوده در 006_screen_groups)_ |
| `description` | VARCHAR(255) DEFAULT NULL | _(افزوده در 006_screen_groups)_ |
| `color` | VARCHAR(7) DEFAULT '#f97316' | _(افزوده در 006_screen_groups)_ |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | _(افزوده در 006_screen_groups)_ |
| `type` | ENUM('signage','iptv') NOT NULL DEFAULT 'signage' | _(افزوده در 006_screen_groups)_ |
| `sort_order` | SMALLINT UNSIGNED DEFAULT 0 | _(افزوده در 006_screen_groups)_ |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 006_screen_groups)_ |
| `location_id` | INT UNSIGNED DEFAULT NULL COMMENT | _(افزوده در 032_flight_board_and_properties)_ |
| `theme` | VARCHAR(20) NOT NULL DEFAULT | _(افزوده در 055_themes)_ |

### `screen_group_members`

<sub>از مهاجرت 001_complete_schema · 2 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `group_id` | INT UNSIGNED NOT NULL |  |
| `screen_id` | INT UNSIGNED NOT NULL |  |

### `screen_commands`

<sub>از مهاجرت 001_initial_schema · 14 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 021_device_management)_ |
| `screen_id` | INT UNSIGNED NOT NULL | _(افزوده در 021_device_management)_ |
| `issued_by` | INT UNSIGNED DEFAULT NULL | users.id _(افزوده در 021_device_management)_ |
| `command` | VARCHAR(40) NOT NULL | reboot\|refresh\|volume\|brightness\|power\|channel\|message\|update_app\|open_url\|clear_cache\|screenshot _(افزوده در 021_device_management)_ |
| `payload` | TEXT DEFAULT NULL | JSON پارامترها _(افزوده در 021_device_management)_ |
| `status` | ENUM('pending','sent','done','failed','expired') NOT NULL DEFAULT 'pending' | _(افزوده در 021_device_management)_ |
| `delivered_at` | TIMESTAMP NULL DEFAULT NULL |  |
| `executed_at` | TIMESTAMP NULL DEFAULT NULL |  |
| `expires_at` | DATETIME DEFAULT NULL | فرمان کهنه نباید بعدا ناگهان اجرا شود _(افزوده در 021_device_management)_ |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | _(افزوده در 021_device_management)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 021_device_management)_ |
| `result` | VARCHAR(400) DEFAULT NULL | پاسخ دستگاه _(افزوده در 021_device_management)_ |
| `sent_at` | DATETIME DEFAULT NULL | _(افزوده در 021_device_management)_ |
| `acked_at` | DATETIME DEFAULT NULL | _(افزوده در 021_device_management)_ |

### `screen_events`

<sub>از مهاجرت 021_device_management · 6 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `screen_id` | INT UNSIGNED NOT NULL |  |
| `event` | VARCHAR(30) NOT NULL | online\|offline\|enrolled\|approved\|error\|app_update |
| `detail` | VARCHAR(300) DEFAULT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

### `screen_heartbeats`

<sub>از مهاجرت 001_initial_schema · 12 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `screen_id` | INT UNSIGNED NOT NULL |  |
| `ip_address` | VARCHAR(45) DEFAULT NULL |  |
| `cpu` | TINYINT UNSIGNED DEFAULT NULL | CPU % 0-100 |
| `memory` | TINYINT UNSIGNED DEFAULT NULL | Memory % 0-100 |
| `disk` | TINYINT UNSIGNED DEFAULT NULL | Disk % 0-100 |
| `temperature` | TINYINT DEFAULT NULL | Celsius |
| `current_media_id` | INT UNSIGNED DEFAULT NULL |  |
| `uptime` | INT UNSIGNED DEFAULT NULL | Seconds |
| `bandwidth_rx` | INT UNSIGNED DEFAULT NULL | Bytes/s |
| `bandwidth_tx` | INT UNSIGNED DEFAULT NULL | Bytes/s |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |

### `heartbeats`

<sub>از مهاجرت 001_complete_schema · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `screen_id` | INT UNSIGNED NOT NULL |  |
| `ip_address` | VARCHAR(45) NOT NULL |  |
| `cpu_usage` | DECIMAL(5,2) DEFAULT NULL |  |
| `memory_usage` | DECIMAL(5,2) DEFAULT NULL |  |
| `disk_usage` | DECIMAL(5,2) DEFAULT NULL |  |
| `uptime` | INT UNSIGNED DEFAULT NULL | seconds |
| `current_item` | VARCHAR(255) DEFAULT NULL |  |
| `player_version` | VARCHAR(20) DEFAULT NULL |  |
| `errors` | JSON DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |

### `enrollment_tokens`

<sub>از مهاجرت 021_device_management · 13 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `token` | VARCHAR(64) NOT NULL |  |
| `label` | VARCHAR(120) DEFAULT NULL | مثلا «طبقه ۳ - نصب مهر» |
| `group_id` | INT UNSIGNED DEFAULT NULL | دستگاه‌های ثبت‌شده به این گروه می‌روند |
| `menu_id` | INT UNSIGNED DEFAULT NULL | منوی IPTV پیش‌فرض |
| `screen_type` | VARCHAR(20) NOT NULL DEFAULT 'iptv' |  |
| `auto_approve` | TINYINT(1) NOT NULL DEFAULT 0 | ۰ = منتظر تایید IT بماند |
| `max_devices` | SMALLINT UNSIGNED DEFAULT NULL | سقف ثبت با این توکن |
| `used_count` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `expires_at` | DATETIME DEFAULT NULL |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

### `apk_versions`

<sub>از مهاجرت 005_apk_versions · 10 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 030_update_log)_ |
| `version_code` | INT UNSIGNED NOT NULL | عدد صعودی؛ اپ با این مقایسه می‌کند _(افزوده در 030_update_log)_ |
| `version_name` | VARCHAR(20) NOT NULL | _(افزوده در 030_update_log)_ |
| `apk_filename` | VARCHAR(190) NOT NULL | داخل public/apk _(افزوده در 030_update_log)_ |
| `file_size` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | _(افزوده در 030_update_log)_ |
| `changelog` | TEXT DEFAULT NULL | _(افزوده در 030_update_log)_ |
| `force_update` | TINYINT(1) NOT NULL DEFAULT 0 | _(افزوده در 030_update_log)_ |
| `min_version` | INT UNSIGNED NOT NULL DEFAULT 0 | _(افزوده در 030_update_log)_ |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 030_update_log)_ |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | _(افزوده در 030_update_log)_ |

### `locations`

<sub>از مهاجرت 001_complete_schema · 15 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `address` | TEXT DEFAULT NULL |  |
| `city` | VARCHAR(80) DEFAULT NULL |  |
| `country` | VARCHAR(80) DEFAULT 'Iran' |  |
| `lat` | DECIMAL(10,8) DEFAULT NULL |  |
| `lng` | DECIMAL(11,8) DEFAULT NULL |  |
| `timezone` | VARCHAR(60) NOT NULL DEFAULT 'Asia/Tehran' |  |
| `phone` | VARCHAR(20) DEFAULT NULL |  |
| `manager_id` | INT UNSIGNED DEFAULT NULL |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `meta` | JSON DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `venues`

<sub>از مهاجرت 026_venues_and_signage · 18 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `kind` | ENUM('lobby','restaurant','cafe','hall','pool','spa','gym' |  |
| `NOT` | NULL DEFAULT 'other' |  |
| `name` | VARCHAR(120) NOT NULL | سالن کنفرانس A، رستوران اصلی |
| `name_en` | VARCHAR(120) DEFAULT NULL |  |
| `floor` | VARCHAR(20) DEFAULT NULL |  |
| `description` | VARCHAR(400) DEFAULT NULL |  |
| `image_url` | VARCHAR(500) DEFAULT NULL |  |
| `capacity` | SMALLINT UNSIGNED DEFAULT NULL | برای سالن |
| `open_from` | TIME DEFAULT NULL | ساعت کاری — برای رستوران و استخر |
| `open_to` | TIME DEFAULT NULL |  |
| `menu_board_id` | INT UNSIGNED DEFAULT NULL |  |
| `sort_order` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `bookable` | TINYINT(1) NOT NULL DEFAULT 0 COMMENT | _(افزوده در 042_reservations)_ |

### `monitor_3d_configs`

<sub>از مهاجرت 015_screen_type_3d · 15 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `screen_id` | INT UNSIGNED NOT NULL |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `format_3d` | ENUM('normal','sbs','top_bottom','hologram','anaglyphic') NOT NULL DEFAULT 'normal' |  |
| `depth_level` | TINYINT UNSIGNED NOT NULL DEFAULT 5 | 1-10 |
| `depth_color` | VARCHAR(20) NOT NULL DEFAULT '#00e5ff' |  |
| `is_outdoor` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `bg_color` | VARCHAR(20) NOT NULL DEFAULT '#000000' |  |
| `auto_rotate` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `rotate_speed` | INT UNSIGNED NOT NULL DEFAULT 5 |  |
| `aspect_ratio` | VARCHAR(20) NOT NULL DEFAULT '16:9' |  |
| `parallax_intensity` | TINYINT UNSIGNED NOT NULL DEFAULT 6 | 1-10 |
| `show_depth_badge` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `widgets`

<sub>از مهاجرت 001_initial_schema · 8 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `type` | ENUM('clock','weather','rss','ticker','qr_code','iframe','custom_html') NOT NULL |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `config` | JSON NOT NULL | Widget-specific settings |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

## محتوا و تابلوی دیجیتال

رسانه، پلی‌لیست، زمان‌بندی و قالب. تلویزیون اتاق پلی‌لیست پخش نمی‌کند.

### `media`

<sub>از مهاجرت 001_complete_schema · 23 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `uploaded_by` | INT UNSIGNED DEFAULT NULL |  |
| `name` | VARCHAR(255) NOT NULL |  |
| `original_name` | VARCHAR(255) DEFAULT NULL |  |
| `type` | ENUM('image','video','url','html','stream') NOT NULL |  |
| `mime_type` | VARCHAR(80) DEFAULT NULL |  |
| `file_path` | VARCHAR(512) DEFAULT NULL | Relative path from storage root |
| `url` | VARCHAR(512) DEFAULT NULL | For type=url/stream |
| `thumbnail_path` | VARCHAR(512) DEFAULT NULL |  |
| `file_size` | BIGINT UNSIGNED DEFAULT 0 |  |
| `duration` | INT UNSIGNED DEFAULT 10 | Seconds — for images/URLs |
| `width` | SMALLINT UNSIGNED DEFAULT NULL |  |
| `height` | SMALLINT UNSIGNED DEFAULT NULL |  |
| `tags` | JSON DEFAULT NULL |  |
| `folder` | VARCHAR(255) DEFAULT NULL |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `meta` | JSON DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `deleted_at` | TIMESTAMP DEFAULT NULL |  |
| `status` | ENUM( | _(افزوده در 039_media_convert_and_subtitles)_ |
| `conv_note` | VARCHAR(300) DEFAULT NULL COMMENT | _(افزوده در 039_media_convert_and_subtitles)_ |

### `media_subtitles`

<sub>از مهاجرت 039_media_convert_and_subtitles · 10 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `media_id` | INT UNSIGNED NOT NULL |  |
| `lang` | VARCHAR(10) NOT NULL | fa, en, ar … |
| `label` | VARCHAR(80) NOT NULL | نامی که روی تلویزیون دیده می‌شود |
| `source_format` | ENUM('srt','vtt','ass','sub') NOT NULL DEFAULT 'srt' |  |
| `file_path` | VARCHAR(1000) NOT NULL | مسیر فایل .vtt نسبت به public |
| `file_size` | INT UNSIGNED NOT NULL DEFAULT 0 |  |
| `is_default` | TINYINT(1) NOT NULL DEFAULT 0 | بدون انتخاب کاربر روشن باشد |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |

### `playlists`

<sub>از مهاجرت 001_complete_schema · 21 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `created_by` | INT UNSIGNED DEFAULT NULL |  |
| `layout_id` | INT UNSIGNED DEFAULT NULL |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `description` | TEXT DEFAULT NULL |  |
| `default_duration` | INT UNSIGNED NOT NULL DEFAULT 10 | seconds per item |
| `transition` | ENUM('none','fade','slide','zoom','flip') NOT NULL DEFAULT 'fade' |  |
| `transition_duration` | SMALLINT UNSIGNED NOT NULL DEFAULT 500 | ms |
| `loop` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `shuffle` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `tags` | JSON DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `screen_type` | ENUM( | _(افزوده در 038_playlist_world)_ |
| `logo_path` | VARCHAR(500) DEFAULT NULL COMMENT | _(افزوده در 040_flight_source_and_board_brand)_ |
| `ticker_text` | TEXT DEFAULT NULL COMMENT | _(افزوده در 040_flight_source_and_board_brand)_ |
| `weather_enabled` | TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN weather_every SMALLINT UNSIGNED NOT NULL DEFAULT 240 COMMENT | _(افزوده در 040_flight_source_and_board_brand)_ |
| `backdrop_mode` | ENUM( | _(افزوده در 041_playlist_backdrop)_ |
| `timeline` | LONGTEXT DEFAULT NULL COMMENT | _(افزوده در 054_playlist_timeline)_ |

### `playlist_items`

<sub>از مهاجرت 001_complete_schema · 22 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `playlist_id` | INT UNSIGNED NOT NULL |  |
| `media_id` | INT UNSIGNED NOT NULL |  |
| `zone_id` | VARCHAR(36) DEFAULT NULL | Zone UUID from layout |
| `sort_order` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `duration` | INT UNSIGNED DEFAULT NULL | Override media default duration (seconds) |
| `start_at` | TIME DEFAULT NULL | daily start time |
| `end_at` | TIME DEFAULT NULL | daily end time |
| `volume` | TINYINT UNSIGNED NOT NULL DEFAULT 100 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `settings` | JSON DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `start_date` | DATE DEFAULT NULL |  |
| `end_date` | DATE DEFAULT NULL |  |
| `start_time` | TIME DEFAULT NULL |  |
| `end_time` | TIME DEFAULT NULL |  |
| `weekdays` | TINYINT UNSIGNED DEFAULT 127 | Bitmask: Mon=1,Tue=2,Wed=4,Thu=8,Fri=16,Sat=32,Sun=64 |
| `conditions` | JSON DEFAULT NULL | Advanced scheduling conditions |
| `item_type` | ENUM( | _(افزوده در 026_venues_and_signage)_ |
| `ref_id` | INT UNSIGNED DEFAULT NULL AFTER media_id | _(افزوده در 026_venues_and_signage)_ |
| `fit_mode` | ENUM( | _(افزوده در 053_playlist_item_display)_ |

### `screen_playlists`

<sub>از مهاجرت 001_initial_schema · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `screen_id` | INT UNSIGNED NOT NULL |  |
| `playlist_id` | INT UNSIGNED NOT NULL |  |
| `priority` | TINYINT UNSIGNED NOT NULL DEFAULT 0 | Higher = takes precedence |
| `start_date` | DATE DEFAULT NULL |  |
| `end_date` | DATE DEFAULT NULL |  |
| `start_time` | TIME DEFAULT NULL |  |
| `end_time` | TIME DEFAULT NULL |  |
| `weekdays` | TINYINT UNSIGNED DEFAULT 127 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |

### `schedules`

<sub>از مهاجرت 001_complete_schema · 16 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `screen_id` | INT UNSIGNED DEFAULT NULL |  |
| `playlist_id` | INT UNSIGNED NOT NULL |  |
| `name` | VARCHAR(255) NOT NULL |  |
| `type` | ENUM('once','daily','weekly','monthly','always') NOT NULL DEFAULT 'always' |  |
| `start_date` | DATE DEFAULT NULL |  |
| `end_date` | DATE DEFAULT NULL |  |
| `start_time` | TIME DEFAULT NULL |  |
| `end_time` | TIME DEFAULT NULL |  |
| `weekdays` | JSON DEFAULT NULL | [0=Sun,1=Mon,...,6=Sat] |
| `priority` | TINYINT UNSIGNED NOT NULL DEFAULT 5 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `IF` | NOT EXISTS `venue_id` INT UNSIGNED DEFAULT NULL AFTER `screen_id`, ADD COLUMN IF NOT EXISTS `group_id` INT UNS | _(افزوده در 048_zone_publish)_ |

### `layouts`

<sub>از مهاجرت 001_complete_schema · 18 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `created_by` | INT UNSIGNED DEFAULT NULL |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `description` | TEXT DEFAULT NULL |  |
| `thumbnail` | VARCHAR(255) DEFAULT NULL |  |
| `canvas_width` | INT UNSIGNED NOT NULL DEFAULT 1920 |  |
| `canvas_height` | INT UNSIGNED NOT NULL DEFAULT 1080 |  |
| `zones` | JSON NOT NULL | Array of zone objects with position/size/type |
| `background_color` | VARCHAR(7) DEFAULT '#000000' |  |
| `background_image` | VARCHAR(500) DEFAULT NULL |  |
| `is_template` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `canvas_w` | SMALLINT UNSIGNED NOT NULL DEFAULT 1920 |  |
| `canvas_h` | SMALLINT UNSIGNED NOT NULL DEFAULT 1080 |  |
| `is_default` | TINYINT(1) NOT NULL DEFAULT 0 |  |

### `content_items`

<sub>از مهاجرت 025_access_and_content · 19 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `kind` | ENUM('news','quran','book','directory','info','prayer_audio') |  |
| `NOT` | NULL DEFAULT 'info' |  |
| `category` | VARCHAR(80) DEFAULT NULL | گروه‌بندی داخل هر نوع |
| `title` | VARCHAR(250) NOT NULL |  |
| `title_en` | VARCHAR(250) DEFAULT NULL |  |
| `subtitle` | VARCHAR(250) DEFAULT NULL |  |
| `body` | MEDIUMTEXT DEFAULT NULL | متن کامل — برای خبر و قرآن |
| `image_url` | VARCHAR(500) DEFAULT NULL |  |
| `audio_url` | VARCHAR(500) DEFAULT NULL | تلاوت یا کتاب صوتی |
| `file_url` | VARCHAR(500) DEFAULT NULL | PDF کتاب یا روزنامه |
| `extra` | VARCHAR(250) DEFAULT NULL | شماره داخلی، نویسنده، منبع خبر |
| `lang` | VARCHAR(10) NOT NULL DEFAULT 'fa' |  |
| `sort_order` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `published_at` | DATETIME DEFAULT NULL | برای خبر |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `campaigns`

<sub>از مهاجرت 001_complete_schema · 20 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `created_by` | INT UNSIGNED DEFAULT NULL |  |
| `name` | VARCHAR(160) NOT NULL |  |
| `type` | ENUM('standard','promotional','emergency','menu_board') NOT NULL DEFAULT 'standard' |  |
| `content` | TEXT NOT NULL |  |
| `media_id` | INT UNSIGNED DEFAULT NULL |  |
| `target` | ENUM('all','group','screen') NOT NULL DEFAULT 'all' |  |
| `target_ids` | JSON DEFAULT NULL |  |
| `priority` | TINYINT UNSIGNED NOT NULL DEFAULT 5 |  |
| `start_at` | DATETIME NOT NULL |  |
| `end_at` | DATETIME NOT NULL |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `description` | TEXT DEFAULT NULL |  |
| `status` | ENUM('draft','scheduled','active','paused','expired','cancelled') NOT NULL DEFAULT 'draft' |  |
| `budget` | DECIMAL(12,2) DEFAULT NULL |  |
| `target_tags` | JSON DEFAULT NULL | Target screens with these tags |
| `settings` | JSON DEFAULT NULL |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `screen_messages`

<sub>از مهاجرت 016_screen_messages · 29 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `title` | VARCHAR(500) NOT NULL |  |
| `title_en` | VARCHAR(500) DEFAULT NULL |  |
| `title_ar` | VARCHAR(500) DEFAULT NULL |  |
| `body` | TEXT NOT NULL |  |
| `body_en` | TEXT DEFAULT NULL |  |
| `body_ar` | TEXT DEFAULT NULL |  |
| `type` | ENUM('welcome','congratulation','announcement','warning','info') |  |
| `NOT` | NULL DEFAULT 'once' |  |
| `style` | ENUM('overlay','fullscreen','popup','banner') |  |
| `icon` | VARCHAR(10) DEFAULT NULL | emoji icon |
| `bg_color` | VARCHAR(20) NOT NULL DEFAULT '#1a1a2e' |  |
| `text_color` | VARCHAR(20) NOT NULL DEFAULT '#ffffff' |  |
| `accent_color` | VARCHAR(20) NOT NULL DEFAULT '#f97316' |  |
| `target` | ENUM('all','screen','group') |  |
| `target_ids` | JSON DEFAULT NULL | array of screen/group IDs |
| `start_at` | DATETIME NOT NULL |  |
| `end_at` | DATETIME DEFAULT NULL |  |
| `duration` | INT UNSIGNED NOT NULL DEFAULT 15 | seconds to show per display |
| `repeat_type` | ENUM('once','daily','weekly','monthly') |  |
| `repeat_days` | VARCHAR(20) DEFAULT NULL | comma-separated 0-6 (Sun-Sat) |
| `repeat_time` | TIME DEFAULT NULL | time of day for repeating messages |
| `show_count` | INT UNSIGNED NOT NULL DEFAULT 0 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_by` | INT UNSIGNED DEFAULT NULL |  |
| `created_at` | TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `ticker_speed` | SMALLINT UNSIGNED NOT NULL DEFAULT 40 | _(افزوده در 057_ticker_and_intro)_ |

### `tv_intro_items`

<sub>از مهاجرت 057_ticker_and_intro · 12 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `name` | VARCHAR(200) NOT NULL |  |
| `media_id` | INT UNSIGNED DEFAULT NULL | عکس یا ویدیو از کتابخانه |
| `url` | VARCHAR(1000) DEFAULT NULL | به‌جای رسانه: آدرس مستقیم |
| `duration` | SMALLINT UNSIGNED NOT NULL DEFAULT 8 | ثانیه؛ ویدیو تا پایان خودش |
| `target` | ENUM('all','screen','group') NOT NULL DEFAULT 'all' |  |
| `target_ids` | JSON DEFAULT NULL |  |
| `scope` | ENUM('both','signage','iptv') NOT NULL DEFAULT 'both' |  |
| `sort_order` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP |  |

### `news_feeds`

<sub>از مهاجرت 025_access_and_content · 12 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `url` | VARCHAR(500) NOT NULL |  |
| `category` | VARCHAR(80) DEFAULT NULL |  |
| `lang` | VARCHAR(10) NOT NULL DEFAULT 'fa' |  |
| `max_items` | TINYINT UNSIGNED NOT NULL DEFAULT 20 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `last_sync_at` | DATETIME DEFAULT NULL |  |
| `last_sync_msg` | VARCHAR(300) DEFAULT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `menu_boards`

<sub>از مهاجرت 001_initial_schema · 19 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 023_pms_charges_and_menus)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 023_pms_charges_and_menus)_ |
| `location_id` | INT UNSIGNED DEFAULT NULL |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `currency` | VARCHAR(5) NOT NULL DEFAULT 'IRR' |  |
| `layout` | ENUM('grid','list','featured','split') NOT NULL DEFAULT 'grid' |  |
| `theme` | JSON DEFAULT NULL | Colors, fonts, background image |
| `show_qr` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `qr_url` | VARCHAR(512) DEFAULT NULL |  |
| `footer_text` | VARCHAR(255) DEFAULT NULL |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 023_pms_charges_and_menus)_ |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | _(افزوده در 023_pms_charges_and_menus)_ |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | _(افزوده در 023_pms_charges_and_menus)_ |
| `category` | ENUM('restaurant','room_service','laundry','minibar','breakfast','spa','other') | _(افزوده در 023_pms_charges_and_menus)_ |
| `NOT` | NULL DEFAULT 'restaurant' | _(افزوده در 023_pms_charges_and_menus)_ |
| `title` | VARCHAR(160) NOT NULL | _(افزوده در 023_pms_charges_and_menus)_ |
| `title_en` | VARCHAR(160) DEFAULT NULL | _(افزوده در 023_pms_charges_and_menus)_ |
| `description` | VARCHAR(400) DEFAULT NULL | _(افزوده در 023_pms_charges_and_menus)_ |
| `sort_order` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 | _(افزوده در 023_pms_charges_and_menus)_ |

### `menu_board_pages`

<sub>از مهاجرت 023_pms_charges_and_menus · 9 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `board_id` | INT UNSIGNED NOT NULL |  |
| `image_url` | VARCHAR(500) NOT NULL |  |
| `caption` | VARCHAR(200) DEFAULT NULL |  |
| `sort_order` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `width` | SMALLINT UNSIGNED DEFAULT NULL |  |
| `height` | SMALLINT UNSIGNED DEFAULT NULL |  |
| `file_size` | INT UNSIGNED DEFAULT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

## IPTV و پخش زنده

کانال، منوی اتاق، EPG، ضبط و تیونر.

### `iptv_channels`

<sub>از مهاجرت 004_iptv_channels · 26 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `name` | VARCHAR(255) NOT NULL |  |
| `name_en` | VARCHAR(255) DEFAULT NULL |  |
| `stream_url` | TEXT NOT NULL |  |
| `logo_url` | VARCHAR(500) DEFAULT NULL |  |
| `category` | VARCHAR(100) DEFAULT 'general' |  |
| `protocol` | VARCHAR(20) DEFAULT 'hls' |  |
| `epg_id` | VARCHAR(100) DEFAULT NULL |  |
| `sort_order` | SMALLINT UNSIGNED DEFAULT 0 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `IF` | NOT EXISTS `source_type` VARCHAR(20) DEFAULT | _(افزوده در 014_tvheadend)_ |
| `delivery` | ENUM( | _(افزوده در 024_multicast)_ |
| `multicast_url` | VARCHAR(255) DEFAULT NULL COMMENT | _(افزوده در 024_multicast)_ |
| `channel_no` | SMALLINT UNSIGNED DEFAULT NULL COMMENT | _(افزوده در 024_multicast)_ |
| `access_level` | TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT | _(افزوده در 025_access_and_content)_ |
| `is_adult` | TINYINT(1) NOT NULL DEFAULT 0 COMMENT | _(افزوده در 025_access_and_content)_ |
| `is_radio` | TINYINT(1) NOT NULL DEFAULT 0 COMMENT | _(افزوده در 025_access_and_content)_ |
| `tvh_uuid` | VARCHAR(64) DEFAULT NULL COMMENT | _(افزوده در 034_dvr_and_catchup)_ |
| `catchup_enabled` | TINYINT(1) NOT NULL DEFAULT 0 COMMENT | _(افزوده در 034_dvr_and_catchup)_ |
| `catchup_window_hours` | SMALLINT UNSIGNED NOT NULL DEFAULT 24 COMMENT | _(افزوده در 034_dvr_and_catchup)_ |
| `catchup_autorec_uuid` | VARCHAR(64) DEFAULT NULL COMMENT | _(افزوده در 034_dvr_and_catchup)_ |
| `backup_stream_url` | TEXT DEFAULT NULL COMMENT | _(افزوده در 034_dvr_and_catchup)_ |
| `srt_url` | TEXT DEFAULT NULL COMMENT | _(افزوده در 034_dvr_and_catchup)_ |

### `iptv_channel_groups`

<sub>از مهاجرت 049_channel_groups · 12 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `name_en` | VARCHAR(120) DEFAULT NULL |  |
| `slug` | VARCHAR(140) NOT NULL |  |
| `icon` | VARCHAR(40) DEFAULT NULL |  |
| `color` | VARCHAR(7) DEFAULT NULL |  |
| `sort_order` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `is_adult` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `iptv_menus`

<sub>از مهاجرت 008_iptv_menus · 24 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `group_id` | INT UNSIGNED DEFAULT NULL | FK → screen_groups |
| `name` | VARCHAR(120) NOT NULL |  |
| `description` | VARCHAR(300) DEFAULT NULL |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `sort_order` | TINYINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `bg_image` | VARCHAR(500) DEFAULT NULL | _(افزوده در 009_iptv_menu_appearance)_ |
| `logo_url` | VARCHAR(500) DEFAULT NULL | _(افزوده در 009_iptv_menu_appearance)_ |
| `welcome_title` | VARCHAR(200) DEFAULT NULL | _(افزوده در 009_iptv_menu_appearance)_ |
| `welcome_sub` | VARCHAR(300) DEFAULT NULL | _(افزوده در 009_iptv_menu_appearance)_ |
| `accent_color` | VARCHAR(7) NOT NULL DEFAULT \ | _(افزوده در 009_iptv_menu_appearance)_ |
| `bg_dim` | DECIMAL(3,2) NOT NULL DEFAULT 0.55 | _(افزوده در 009_iptv_menu_appearance)_ |
| `bg_blur` | TINYINT UNSIGNED NOT NULL DEFAULT 0 | _(افزوده در 009_iptv_menu_appearance)_ |
| `ticker_text` | TEXT DEFAULT NULL | _(افزوده در 009_iptv_menu_appearance)_ |
| `ticker_color` | VARCHAR(7) NOT NULL DEFAULT \ | _(افزوده در 009_iptv_menu_appearance)_ |
| `ticker_bg` | VARCHAR(7) NOT NULL DEFAULT \ | _(افزوده در 009_iptv_menu_appearance)_ |
| `ticker_speed` | TINYINT UNSIGNED NOT NULL DEFAULT 40 | _(افزوده در 009_iptv_menu_appearance)_ |
| `header_widgets` | VARCHAR(200) DEFAULT | _(افزوده در 019_portal_experience)_ |
| `show_guest_name` | TINYINT(1) NOT NULL DEFAULT 1 COMMENT | _(افزوده در 019_portal_experience)_ |
| `city` | VARCHAR(80) DEFAULT NULL COMMENT | _(افزوده در 019_portal_experience)_ |
| `theme` | VARCHAR(20) NOT NULL DEFAULT | _(افزوده در 055_themes)_ |

### `iptv_menu_items`

<sub>از مهاجرت 008_iptv_menus · 14 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `menu_id` | INT UNSIGNED NOT NULL |  |
| `type` | ENUM('live','vod','news','info','weather','fids','hotel','corporate','retail','url','custom') |  |
| `NOT` | NULL DEFAULT 'live' |  |
| `label` | VARCHAR(100) NOT NULL |  |
| `icon` | VARCHAR(80) NOT NULL DEFAULT 'fas fa-play' |  |
| `color` | VARCHAR(20) NOT NULL DEFAULT '#f97316' |  |
| `target_url` | VARCHAR(500) DEFAULT NULL |  |
| `config` | JSON DEFAULT NULL |  |
| `sort_order` | TINYINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `shortcut_key` | TINYINT UNSIGNED DEFAULT NULL COMMENT | _(افزوده در 019_portal_experience)_ |
| `label_en` | VARCHAR(100) DEFAULT NULL AFTER label | _(افزوده در 019_portal_experience)_ |

### `iptv_menu_backgrounds`

<sub>از مهاجرت 019_portal_experience · 5 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `menu_id` | INT UNSIGNED NOT NULL |  |
| `image_url` | VARCHAR(500) NOT NULL |  |
| `sort_order` | TINYINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

### `iptv_rooms`

<sub>از مهاجرت 010_iptv_rooms · 21 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `group_id` | INT UNSIGNED DEFAULT NULL |  |
| `room_number` | VARCHAR(20) NOT NULL | شماره اتاق: 101، A-205 |
| `room_name` | VARCHAR(100) DEFAULT NULL | نام اتاق: Suite Deluxe |
| `floor` | TINYINT DEFAULT NULL |  |
| `room_type` | VARCHAR(50) DEFAULT NULL | single\|double\|suite\|vip |
| `status` | ENUM('available','occupied','maintenance') NOT NULL DEFAULT 'available' |  |
| `guest_name` | VARCHAR(100) DEFAULT NULL |  |
| `guest_lang` | VARCHAR(10) NOT NULL DEFAULT 'fa' |  |
| `check_in_at` | DATETIME DEFAULT NULL |  |
| `check_out_at` | DATETIME DEFAULT NULL |  |
| `pms_room_id` | VARCHAR(50) DEFAULT NULL | کد اتاق در سیستم PMS |
| `notes` | VARCHAR(300) DEFAULT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `access_level` | TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT | _(افزوده در 025_access_and_content)_ |
| `parental_pin` | VARCHAR(255) DEFAULT NULL COMMENT | _(افزوده در 025_access_and_content)_ |
| `parental_enabled` | TINYINT(1) NOT NULL DEFAULT 0 | _(افزوده در 025_access_and_content)_ |
| `location_id` | INT UNSIGNED DEFAULT NULL COMMENT | _(افزوده در 032_flight_board_and_properties)_ |
| `building` | VARCHAR(50) DEFAULT NULL COMMENT | _(افزوده در 032_flight_board_and_properties)_ |

### `iptv_room_messages`

<sub>از مهاجرت 010_iptv_rooms · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `room_id` | INT UNSIGNED DEFAULT NULL | NULL = پخش به همه اتاق‌ها |
| `title` | VARCHAR(200) DEFAULT NULL |  |
| `body` | TEXT NOT NULL |  |
| `msg_type` | ENUM('info','welcome','urgent','promo','custom') NOT NULL DEFAULT 'info' |  |
| `display_mode` | ENUM('banner','popup','ticker') NOT NULL DEFAULT 'banner' |  |
| `priority` | TINYINT UNSIGNED NOT NULL DEFAULT 5 |  |
| `expires_at` | DATETIME DEFAULT NULL |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

### `tvheadend_sources`

<sub>از مهاجرت 014_tvheadend · 12 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `name` | VARCHAR(255) NOT NULL DEFAULT 'TVHeadend Server' |  |
| `server_url` | VARCHAR(500) NOT NULL |  |
| `username` | VARCHAR(255) DEFAULT NULL |  |
| `password` | VARCHAR(255) DEFAULT NULL |  |
| `stream_profile` | VARCHAR(100) DEFAULT 'pass' |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `last_sync` | TIMESTAMP NULL DEFAULT NULL |  |
| `sync_count` | INT UNSIGNED DEFAULT 0 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `epg_sources`

<sub>از مهاجرت 018_epg · 13 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `source_type` | ENUM('tvheadend','xmltv_url','xmltv_file') NOT NULL DEFAULT 'tvheadend' |  |
| `tvh_source_id` | INT UNSIGNED DEFAULT NULL | tvheadend_sources.id وقتی نوع tvheadend است |
| `url` | VARCHAR(500) DEFAULT NULL | آدرس XMLTV |
| `days_ahead` | TINYINT UNSIGNED NOT NULL DEFAULT 7 | چند روز جلوتر دریافت شود |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `last_sync_at` | DATETIME DEFAULT NULL |  |
| `last_sync_msg` | VARCHAR(300) DEFAULT NULL |  |
| `last_count` | INT UNSIGNED NOT NULL DEFAULT 0 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `epg_programs`

<sub>از مهاجرت 018_epg · 18 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `channel_id` | INT UNSIGNED DEFAULT NULL | iptv_channels.id — پس از تطبیق |
| `channel_key` | VARCHAR(120) NOT NULL | کلید منبع: tvh uuid یا xmltv channel id |
| `title` | VARCHAR(300) NOT NULL |  |
| `subtitle` | VARCHAR(300) DEFAULT NULL |  |
| `description` | TEXT DEFAULT NULL |  |
| `category` | VARCHAR(80) DEFAULT NULL | فیلم، خبر، ورزش، کودک |
| `lang` | VARCHAR(10) NOT NULL DEFAULT 'fa' |  |
| `starts_at` | DATETIME NOT NULL |  |
| `ends_at` | DATETIME NOT NULL |  |
| `season` | SMALLINT UNSIGNED DEFAULT NULL |  |
| `episode` | SMALLINT UNSIGNED DEFAULT NULL |  |
| `image` | VARCHAR(500) DEFAULT NULL |  |
| `rating` | VARCHAR(20) DEFAULT NULL | رده سنی |
| `source_id` | INT UNSIGNED DEFAULT NULL | epg_sources.id |
| `external_id` | VARCHAR(120) DEFAULT NULL | شناسه رویداد در منبع |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

### `epg_channel_map`

<sub>از مهاجرت 018_epg · 5 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `channel_key` | VARCHAR(120) NOT NULL | کلید در منبع EPG |
| `channel_id` | INT UNSIGNED NOT NULL | iptv_channels.id |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

### `dvr_recordings`

<sub>از مهاجرت 034_dvr_and_catchup · 18 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `kind` | ENUM('pvr','npvr','catchup') NOT NULL DEFAULT 'pvr' |  |
| `channel_id` | INT UNSIGNED DEFAULT NULL | iptv_channels.id |
| `room_id` | INT UNSIGNED DEFAULT NULL | صاحب ضبط برای NPVR — iptv_rooms.id |
| `screen_code` | VARCHAR(32) DEFAULT NULL | صفحه‌ای که درخواست داد |
| `tvh_uuid` | VARCHAR(64) DEFAULT NULL | uuid ورودی DVR در TVHeadend |
| `event_id` | INT UNSIGNED DEFAULT NULL | شناسه رویداد EPG (اگر از روی برنامه ضبط شد) |
| `title` | VARCHAR(300) NOT NULL |  |
| `starts_at` | DATETIME NOT NULL |  |
| `stops_at` | DATETIME NOT NULL |  |
| `status` | ENUM('scheduled','recording','finished','failed','removed') NOT NULL DEFAULT 'scheduled' |  |
| `file_url` | VARCHAR(500) DEFAULT NULL | آدرس پخش فایل ضبط‌شده |
| `size_bytes` | BIGINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `error` | VARCHAR(300) DEFAULT NULL |  |
| `created_by` | INT UNSIGNED DEFAULT NULL | کاربر پنل، یا NULL برای مهمان |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `dvr_quotas`

<sub>از مهاجرت 034_dvr_and_catchup · 7 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `room_id` | INT UNSIGNED NOT NULL | iptv_rooms.id |
| `max_recordings` | SMALLINT UNSIGNED NOT NULL DEFAULT 20 |  |
| `max_minutes` | INT UNSIGNED NOT NULL DEFAULT 1200 | سقف مجموع دقیقه |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `multicast_config`

<sub>از مهاجرت 024_multicast · 12 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `base_group` | VARCHAR(20) NOT NULL DEFAULT '239.1.1.1' | اولین آدرس گروه |
| `base_port` | SMALLINT UNSIGNED NOT NULL DEFAULT 5000 |  |
| `port_step` | SMALLINT UNSIGNED NOT NULL DEFAULT 2 | RTP پورت زوج می‌خواهد |
| `ttl` | TINYINT UNSIGNED NOT NULL DEFAULT 4 |  |
| `udpxy_url` | VARCHAR(255) DEFAULT NULL | مثلا http://10.0.0.5:4022 |
| `interface_ip` | VARCHAR(45) DEFAULT NULL | IP کارت شبکه‌ای که پخش از آن خارج می‌شود |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `native_platforms` | VARCHAR(120) NOT NULL DEFAULT | _(افزوده در 027_multicast_native_platforms)_ |

### `transcoder_jobs`

<sub>از مهاجرت 043_transcoder · 20 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `slug` | VARCHAR(60) NOT NULL | نام پوشه‌ی خروجی و مسیر /hls/tc-{slug}/ |
| `mode` | ENUM('live','vod') NOT NULL DEFAULT 'live' |  |
| `input_url` | VARCHAR(1000) NOT NULL | udp:// rtp:// rtsp:// rtmp:// http(s):// srt:// یا مسیر فایل/دستگاه |
| `input_kind` | ENUM('url','file','v4l2','decklink') NOT NULL DEFAULT 'url' |  |
| `settings` | JSON NOT NULL | video, renditions, audio, subtitles, overlay, outputs, hw |
| `channel_id` | INT UNSIGNED DEFAULT NULL | کانال IPTV که این خروجی را پخش می‌کند |
| `desired` | ENUM('running','stopped') NOT NULL DEFAULT 'stopped' |  |
| `status` | ENUM('stopped','starting','running','error','finished') NOT NULL DEFAULT 'stopped' |  |
| `pid` | INT UNSIGNED DEFAULT NULL |  |
| `started_at` | DATETIME DEFAULT NULL |  |
| `restarts` | INT UNSIGNED NOT NULL DEFAULT 0 | از آخرین شروع دستی |
| `last_restart_at` | DATETIME DEFAULT NULL |  |
| `last_error` | VARCHAR(500) DEFAULT NULL |  |
| `progress` | TINYINT UNSIGNED DEFAULT NULL | درصد پیشرفت کار vod |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `fail_count` | INT UNSIGNED NOT NULL DEFAULT 0 | _(افزوده در 058_transcoder_failcount)_ |

### `cameras`

<sub>از مهاجرت 036_cameras · 15 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `name` | VARCHAR(120) NOT NULL | مثلا «زمین بازی کودکان» |
| `location` | VARCHAR(120) DEFAULT NULL | محل نصب برای اپراتور |
| `rtsp_url` | VARCHAR(500) NOT NULL | آدرس RTSP دوربین — هرگز به مهمان داده نمی‌شود |
| `stream_name` | VARCHAR(60) NOT NULL | اسلاگ مسیر HLS |
| `quality` | ENUM('low','medium','high','copy') NOT NULL DEFAULT 'medium' |  |
| `audio` | ENUM('mute','include') NOT NULL DEFAULT 'mute' | صدای دوربین معمولا لازم نیست |
| `guest_visible` | TINYINT(1) NOT NULL DEFAULT 1 | در پورتال مهمان دیده شود |
| `access_level` | TINYINT UNSIGNED NOT NULL DEFAULT 0 | حداقل سطح اتاق برای دیدن |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `sort_order` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `last_started_at` | DATETIME DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `portal_live_cache`

<sub>از مهاجرت 019_portal_experience · 7 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `kind` | ENUM('weather','currency','prayer') NOT NULL |  |
| `cache_key` | VARCHAR(120) NOT NULL DEFAULT '' | معمولا نام شهر |
| `payload` | TEXT NOT NULL | JSON |
| `fetched_at` | DATETIME NOT NULL |  |
| `expires_at` | DATETIME NOT NULL |  |

### `portal_translations`

<sub>از مهاجرت 019_portal_experience · 6 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `lang` | VARCHAR(10) NOT NULL |  |
| `trans_key` | VARCHAR(120) NOT NULL |  |
| `trans_value` | VARCHAR(500) NOT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

## ویدیو درخواستی (VOD)

### `vod_videos`

<sub>از مهاجرت 007_vod_tables · 35 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `category_id` | INT UNSIGNED DEFAULT NULL |  |
| `title` | VARCHAR(500) NOT NULL |  |
| `title_en` | VARCHAR(500) DEFAULT NULL |  |
| `description` | TEXT DEFAULT NULL |  |
| `type` | ENUM('upload','url','youtube','vimeo') NOT NULL DEFAULT 'upload' |  |
| `file_path` | VARCHAR(1000) DEFAULT NULL |  |
| `file_name` | VARCHAR(500) DEFAULT NULL |  |
| `file_size` | BIGINT UNSIGNED DEFAULT 0 |  |
| `mime_type` | VARCHAR(100) DEFAULT 'video/mp4' |  |
| `stream_url` | VARCHAR(2000) DEFAULT NULL |  |
| `thumbnail` | VARCHAR(500) DEFAULT NULL |  |
| `thumbnail_auto` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `duration` | INT UNSIGNED DEFAULT NULL | seconds |
| `duration_fmt` | VARCHAR(20) DEFAULT NULL | HH:MM:SS |
| `width` | SMALLINT UNSIGNED DEFAULT NULL |  |
| `height` | SMALLINT UNSIGNED DEFAULT NULL |  |
| `codec` | VARCHAR(50) DEFAULT NULL |  |
| `bitrate` | INT UNSIGNED DEFAULT NULL |  |
| `tags` | JSON DEFAULT NULL |  |
| `year` | SMALLINT DEFAULT NULL |  |
| `language` | VARCHAR(10) DEFAULT 'fa' |  |
| `rating` | TINYINT UNSIGNED DEFAULT NULL | 1-5 |
| `status` | ENUM('processing','ready','error') NOT NULL DEFAULT 'ready' |  |
| `views` | INT UNSIGNED NOT NULL DEFAULT 0 |  |
| `sort_order` | SMALLINT UNSIGNED DEFAULT 0 |  |
| `is_featured` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `uploaded_by` | INT UNSIGNED DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `price` | DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT | _(افزوده در 022_room_folio)_ |
| `access_hours` | SMALLINT UNSIGNED NOT NULL DEFAULT 24 COMMENT | _(افزوده در 022_room_folio)_ |
| `currency` | VARCHAR(10) NOT NULL DEFAULT | _(افزوده در 022_room_folio)_ |

### `vod_watch_history`

<sub>از مهاجرت 058_vod_watch_history · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `room_id` | INT UNSIGNED NOT NULL |  |
| `stay_started_at` | DATETIME NOT NULL |  |
| `video_id` | INT UNSIGNED NOT NULL |  |
| `position` | INT UNSIGNED NOT NULL DEFAULT 0 | ثانیه |
| `duration` | INT UNSIGNED NOT NULL DEFAULT 0 | ثانیه، از خود پخش‌کننده |
| `completed` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `hidden` | TINYINT(1) NOT NULL DEFAULT 0 | مهمان از «ادامه‌ی تماشا» برداشت |
| `started_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `vod_categories`

<sub>از مهاجرت 007_vod_tables · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `parent_id` | INT UNSIGNED DEFAULT NULL |  |
| `name` | VARCHAR(255) NOT NULL |  |
| `slug` | VARCHAR(255) NOT NULL |  |
| `description` | TEXT DEFAULT NULL |  |
| `cover` | VARCHAR(500) DEFAULT NULL |  |
| `color` | VARCHAR(7) DEFAULT '#7c3aed' |  |
| `sort_order` | SMALLINT UNSIGNED DEFAULT 0 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |

### `vod_subtitles`

<sub>از مهاجرت 031_vod_subtitles_audio · 12 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `vod_id` | INT UNSIGNED NOT NULL |  |
| `lang` | VARCHAR(10) NOT NULL | fa, en, ar … |
| `label` | VARCHAR(80) NOT NULL |  |
| `source_format` | ENUM('srt','vtt','ass','sub') NOT NULL DEFAULT 'srt' |  |
| `file_path` | VARCHAR(1000) NOT NULL | مسیر فایل .vtt نسبت به public |
| `file_size` | INT UNSIGNED NOT NULL DEFAULT 0 |  |
| `is_sdh` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `is_default` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `sort_order` | TINYINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

### `vod_audio_tracks`

<sub>از مهاجرت 031_vod_subtitles_audio · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `vod_id` | INT UNSIGNED NOT NULL |  |
| `lang` | VARCHAR(10) NOT NULL | fa, en, ar … |
| `label` | VARCHAR(80) NOT NULL | مثلا «دوبله فارسی» |
| `kind` | ENUM('file','hls') NOT NULL DEFAULT 'file' |  |
| `file_path` | VARCHAR(1000) DEFAULT NULL |  |
| `track_index` | TINYINT UNSIGNED DEFAULT NULL |  |
| `is_default` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `sort_order` | TINYINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

### `ppv_purchases`

<sub>از مهاجرت 022_room_folio · 10 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `room_id` | INT UNSIGNED NOT NULL |  |
| `video_id` | INT UNSIGNED NOT NULL |  |
| `charge_id` | INT UNSIGNED DEFAULT NULL |  |
| `price` | DECIMAL(12,2) NOT NULL DEFAULT 0 |  |
| `currency` | VARCHAR(10) NOT NULL DEFAULT 'IRR' |  |
| `expires_at` | DATETIME NOT NULL |  |
| `cancelled_at` | DATETIME DEFAULT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

## هتل و مهمان

رزرو، صورتحساب، سفارش و خدمات اتاق.

### `reservations`

<sub>از مهاجرت 042_reservations · 20 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `venue_id` | INT UNSIGNED NOT NULL |  |
| `room_id` | INT UNSIGNED DEFAULT NULL | NULL = مهمان بیرونی که پذیرش ثبت کرده |
| `guest_name` | VARCHAR(100) DEFAULT NULL | کپی در لحظه‌ی ثبت |
| `party_size` | TINYINT UNSIGNED NOT NULL DEFAULT 1 |  |
| `start_at` | DATETIME NOT NULL |  |
| `end_at` | DATETIME NOT NULL |  |
| `status` | ENUM('pending','confirmed','completed','no_show','cancelled') |  |
| `NOT` | NULL DEFAULT 'pending' |  |
| `note` | VARCHAR(300) DEFAULT NULL | یادداشت مهمان — صندلی کودک، کنار پنجره |
| `staff_note` | VARCHAR(300) DEFAULT NULL |  |
| `price` | DECIMAL(12,2) NOT NULL DEFAULT 0 | کل مبلغ در لحظه‌ی ثبت |
| `charge_id` | INT UNSIGNED DEFAULT NULL | room_charges.id بعد از تایید |
| `source` | ENUM('tv','panel') NOT NULL DEFAULT 'tv' |  |
| `created_by` | INT UNSIGNED DEFAULT NULL |  |
| `confirmed_at` | DATETIME DEFAULT NULL |  |
| `cancelled_at` | DATETIME DEFAULT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `room_charges`

<sub>از مهاجرت 022_room_folio · 23 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `room_id` | INT UNSIGNED NOT NULL |  |
| `stay_started_at` | DATETIME NOT NULL |  |
| `source` | ENUM('minibar','room_service','laundry','ppv','service','manual','pms') |  |
| `NOT` | NULL DEFAULT 'manual' |  |
| `reference_id` | INT UNSIGNED DEFAULT NULL | guest_requests.id یا vod_videos.id |
| `title` | VARCHAR(200) NOT NULL | متنی که مهمان می‌بیند |
| `qty` | SMALLINT UNSIGNED NOT NULL DEFAULT 1 |  |
| `unit_price` | DECIMAL(12,2) NOT NULL DEFAULT 0 |  |
| `amount` | DECIMAL(12,2) NOT NULL DEFAULT 0 | qty × unit_price |
| `currency` | VARCHAR(10) NOT NULL DEFAULT 'IRR' |  |
| `status` | ENUM('posted','void','settled') NOT NULL DEFAULT 'posted' |  |
| `pms_status` | ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending' |  |
| `pms_ref` | VARCHAR(80) DEFAULT NULL |  |
| `pms_error` | VARCHAR(300) DEFAULT NULL |  |
| `note` | VARCHAR(300) DEFAULT NULL |  |
| `created_by` | INT UNSIGNED DEFAULT NULL | users.id — خالی یعنی خودکار |
| `voided_by` | INT UNSIGNED DEFAULT NULL |  |
| `voided_at` | DATETIME DEFAULT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `pms_attempts` | TINYINT UNSIGNED NOT NULL DEFAULT 0 | _(افزوده در 023_pms_charges_and_menus)_ |

### `checkout_requests`

<sub>از مهاجرت 022_room_folio · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `room_id` | INT UNSIGNED NOT NULL |  |
| `guest_name` | VARCHAR(100) DEFAULT NULL |  |
| `total_amount` | DECIMAL(12,2) NOT NULL DEFAULT 0 | مبلغ در لحظه درخواست |
| `currency` | VARCHAR(10) NOT NULL DEFAULT 'IRR' |  |
| `status` | ENUM('requested','confirmed','rejected') NOT NULL DEFAULT 'requested' |  |
| `note` | VARCHAR(300) DEFAULT NULL |  |
| `handled_by` | INT UNSIGNED DEFAULT NULL |  |
| `handled_at` | DATETIME DEFAULT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

### `guest_services`

<sub>از مهاجرت 017_guest_services · 19 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `category` | ENUM('room_service','housekeeping','laundry','taxi','breakfast','maintenance','other') |  |
| `NOT` | NULL DEFAULT 'other' |  |
| `name_fa` | VARCHAR(120) NOT NULL |  |
| `name_en` | VARCHAR(120) DEFAULT NULL |  |
| `description` | VARCHAR(400) DEFAULT NULL |  |
| `icon` | VARCHAR(60) DEFAULT NULL | نام آیکون در رابط پلیر |
| `image` | VARCHAR(255) DEFAULT NULL |  |
| `price` | DECIMAL(12,2) NOT NULL DEFAULT 0 | ۰ = رایگان |
| `currency` | VARCHAR(10) NOT NULL DEFAULT 'IRR' |  |
| `unit` | VARCHAR(30) DEFAULT NULL | عدد، پرس، کیلو |
| `is_orderable` | TINYINT(1) NOT NULL DEFAULT 1 | ۰ = فقط درخواست بدون تعداد |
| `available_from` | TIME DEFAULT NULL | بازه سرویس‌دهی، مثلا صبحانه ۰۶:۰۰ |
| `available_to` | TIME DEFAULT NULL |  |
| `sort_order` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `guest_requests`

<sub>از مهاجرت 017_guest_services · 22 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `room_id` | INT UNSIGNED NOT NULL |  |
| `category` | ENUM('room_service','housekeeping','laundry','taxi','breakfast','maintenance','wakeup','feedback','other') |  |
| `NOT` | NULL DEFAULT 'pending' |  |
| `status` | ENUM('pending','accepted','in_progress','done','cancelled') |  |
| `guest_name` | VARCHAR(100) DEFAULT NULL | کپی از اتاق در لحظه ثبت |
| `guest_lang` | VARCHAR(10) NOT NULL DEFAULT 'fa' |  |
| `note` | VARCHAR(500) DEFAULT NULL | یادداشت مهمان |
| `scheduled_at` | DATETIME DEFAULT NULL | زمان بیدارباش یا تحویل درخواستی |
| `total_price` | DECIMAL(12,2) NOT NULL DEFAULT 0 |  |
| `currency` | VARCHAR(10) NOT NULL DEFAULT 'IRR' |  |
| `assigned_to` | INT UNSIGNED DEFAULT NULL | users.id کارمند مسئول |
| `staff_note` | VARCHAR(500) DEFAULT NULL |  |
| `rating` | TINYINT UNSIGNED DEFAULT NULL | ۱ تا ۵ — برای category=feedback |
| `source` | ENUM('tv','panel','api') NOT NULL DEFAULT 'tv' |  |
| `accepted_at` | DATETIME DEFAULT NULL |  |
| `done_at` | DATETIME DEFAULT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `delivered_at` | DATETIME DEFAULT NULL COMMENT | _(افزوده در 025_access_and_content)_ |
| `acknowledged_at` | DATETIME DEFAULT NULL COMMENT | _(افزوده در 025_access_and_content)_ |

### `guest_request_items`

<sub>از مهاجرت 017_guest_services · 8 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `request_id` | INT UNSIGNED NOT NULL |  |
| `service_id` | INT UNSIGNED DEFAULT NULL | NULL اگر سرویس بعدا حذف شود |
| `menu_item_id` | INT UNSIGNED DEFAULT NULL | ارجاع به iptv_menu_items در صورت سفارش از منو |
| `name_snapshot` | VARCHAR(160) NOT NULL | نام در لحظه سفارش |
| `qty` | SMALLINT UNSIGNED NOT NULL DEFAULT 1 |  |
| `unit_price` | DECIMAL(12,2) NOT NULL DEFAULT 0 |  |
| `line_total` | DECIMAL(12,2) NOT NULL DEFAULT 0 |  |

### `guest_request_log`

<sub>از مهاجرت 017_guest_services · 7 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `request_id` | INT UNSIGNED NOT NULL |  |
| `from_status` | VARCHAR(20) DEFAULT NULL |  |
| `to_status` | VARCHAR(20) NOT NULL |  |
| `user_id` | INT UNSIGNED DEFAULT NULL |  |
| `note` | VARCHAR(300) DEFAULT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

### `minibar_items`

<sub>از مهاجرت 022_room_folio · 12 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `name_fa` | VARCHAR(120) NOT NULL |  |
| `name_en` | VARCHAR(120) DEFAULT NULL |  |
| `price` | DECIMAL(12,2) NOT NULL DEFAULT 0 |  |
| `currency` | VARCHAR(10) NOT NULL DEFAULT 'IRR' |  |
| `image` | VARCHAR(255) DEFAULT NULL |  |
| `par_level` | TINYINT UNSIGNED NOT NULL DEFAULT 2 | موجودی استاندارد هر اتاق |
| `sort_order` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `minibar_checks`

<sub>از مهاجرت 022_room_folio · 9 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `room_id` | INT UNSIGNED NOT NULL |  |
| `item_id` | INT UNSIGNED NOT NULL |  |
| `found_qty` | TINYINT UNSIGNED NOT NULL | چند عدد در یخچال بود |
| `consumed_qty` | TINYINT UNSIGNED NOT NULL | par_level منهای found_qty |
| `charge_id` | INT UNSIGNED DEFAULT NULL | room_charges.id اگر ثبت شد |
| `checked_by` | INT UNSIGNED DEFAULT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

### `menu_categories`

<sub>از مهاجرت 001_complete_schema · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `location_id` | INT UNSIGNED DEFAULT NULL |  |
| `name` | VARCHAR(120) NOT NULL |  |
| `name_en` | VARCHAR(120) DEFAULT NULL |  |
| `sort_order` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `icon` | VARCHAR(255) DEFAULT NULL |  |
| `color` | VARCHAR(7) DEFAULT '#FF6B35' |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `menu_board_id` | INT UNSIGNED NOT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |

### `menu_items`

<sub>از مهاجرت 001_complete_schema · 25 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `category_id` | INT UNSIGNED NOT NULL |  |
| `name` | VARCHAR(160) NOT NULL |  |
| `name_en` | VARCHAR(160) DEFAULT NULL |  |
| `description` | TEXT DEFAULT NULL |  |
| `price` | DECIMAL(14,0) NOT NULL DEFAULT 0 |  |
| `original_price` | DECIMAL(12,2) DEFAULT NULL |  |
| `currency` | VARCHAR(3) NOT NULL DEFAULT 'IRR' |  |
| `image` | VARCHAR(500) DEFAULT NULL |  |
| `calories` | SMALLINT UNSIGNED DEFAULT NULL |  |
| `is_special` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `is_available` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `sort_order` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `tags` | JSON DEFAULT NULL | ["vegan","spicy","popular"] |
| `available_from` | TIME DEFAULT NULL |  |
| `available_to` | TIME DEFAULT NULL |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `old_price` | DECIMAL(14,0) DEFAULT NULL | For showing discount |
| `image_id` | INT UNSIGNED DEFAULT NULL |  |
| `badge` | VARCHAR(60) DEFAULT NULL | e.g. NEW, HOT, SPICY |
| `badge_color` | VARCHAR(7) DEFAULT '#FF0000' |  |
| `allergens` | JSON DEFAULT NULL |  |
| `is_featured` | TINYINT(1) NOT NULL DEFAULT 0 |  |

### `retail_products`

<sub>از مهاجرت 001_complete_schema · 19 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 003_module_tables_only)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `category` | VARCHAR(100) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `name` | VARCHAR(255) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `name_en` | VARCHAR(255) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `price` | DECIMAL(15,2) NOT NULL DEFAULT 0 | _(افزوده در 003_module_tables_only)_ |
| `old_price` | DECIMAL(15,2) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `currency` | VARCHAR(10) NOT NULL DEFAULT 'تومان' | _(افزوده در 003_module_tables_only)_ |
| `unit` | VARCHAR(50) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `image` | VARCHAR(500) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `barcode` | VARCHAR(100) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `is_featured` | TINYINT(1) NOT NULL DEFAULT 0 | _(افزوده در 003_module_tables_only)_ |
| `is_offer` | TINYINT(1) NOT NULL DEFAULT 0 | _(افزوده در 003_module_tables_only)_ |
| `offer_ends` | DATETIME DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `stock` | INT DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `sort_order` | SMALLINT UNSIGNED DEFAULT 0 | _(افزوده در 003_module_tables_only)_ |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 003_module_tables_only)_ |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP | _(افزوده در 003_module_tables_only)_ |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | _(افزوده در 003_module_tables_only)_ |

### `retail_queue`

<sub>از مهاجرت 001_complete_schema · 6 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 003_module_tables_only)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `counter` | VARCHAR(50) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `ticket_number` | INT UNSIGNED NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `called_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP | _(افزوده در 003_module_tables_only)_ |
| `status` | ENUM('waiting','serving','done') DEFAULT 'serving' | _(افزوده در 003_module_tables_only)_ |

### `hotel_info`

<sub>از مهاجرت 001_complete_schema · 16 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 003_module_tables_only)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `hotel_name` | VARCHAR(255) NOT NULL DEFAULT 'هتل ما' | _(افزوده در 003_module_tables_only)_ |
| `hotel_name_en` | VARCHAR(255) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `slogan` | VARCHAR(500) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `logo` | VARCHAR(500) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `address` | TEXT DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `phone` | VARCHAR(20) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `email` | VARCHAR(255) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `website` | VARCHAR(500) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `checkin_time` | TIME DEFAULT '14:00:00' | _(افزوده در 003_module_tables_only)_ |
| `checkout_time` | TIME DEFAULT '12:00:00' | _(افزوده در 003_module_tables_only)_ |
| `wifi_name` | VARCHAR(100) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `wifi_pass` | VARCHAR(100) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `stars` | TINYINT UNSIGNED DEFAULT 5 | _(افزوده در 003_module_tables_only)_ |
| `settings` | JSON DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |

### `hotel_amenities`

<sub>از مهاجرت 001_complete_schema · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 003_module_tables_only)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `name` | VARCHAR(255) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `name_en` | VARCHAR(255) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `description` | TEXT DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `icon` | VARCHAR(100) DEFAULT 'fas fa-star' | _(افزوده در 003_module_tables_only)_ |
| `floor` | VARCHAR(20) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `hours` | VARCHAR(100) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `phone` | VARCHAR(20) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `sort_order` | SMALLINT UNSIGNED DEFAULT 0 | _(افزوده در 003_module_tables_only)_ |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 003_module_tables_only)_ |

### `hotel_attractions`

<sub>از مهاجرت 001_complete_schema · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 003_module_tables_only)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `name` | VARCHAR(255) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `name_en` | VARCHAR(255) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `description` | TEXT DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `category` | VARCHAR(100) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `distance` | VARCHAR(50) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `image` | VARCHAR(500) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `map_url` | VARCHAR(1000) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `sort_order` | SMALLINT UNSIGNED DEFAULT 0 | _(افزوده در 003_module_tables_only)_ |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 003_module_tables_only)_ |

### `hotel_events`

<sub>از مهاجرت 001_complete_schema · 19 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 003_module_tables_only)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `title` | VARCHAR(255) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `title_en` | VARCHAR(255) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `description` | TEXT DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `location` | VARCHAR(255) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `hall_name` | VARCHAR(100) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `floor` | VARCHAR(20) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `start_at` | DATETIME NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `end_at` | DATETIME DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `organizer` | VARCHAR(255) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `capacity` | INT UNSIGNED DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `type` | ENUM('conference','wedding','seminar','party','exhibition','other') DEFAULT 'conference' | _(افزوده در 003_module_tables_only)_ |
| `image` | VARCHAR(500) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `color` | VARCHAR(7) DEFAULT '#d4af37' | _(افزوده در 003_module_tables_only)_ |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 003_module_tables_only)_ |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP | _(افزوده در 003_module_tables_only)_ |
| `venue_id` | INT UNSIGNED DEFAULT NULL | _(افزوده در 026_venues_and_signage)_ |
| `status` | ENUM( | _(افزوده در 026_venues_and_signage)_ |

### `hotel_room_service`

<sub>از مهاجرت 001_complete_schema · 15 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 003_module_tables_only)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `category` | VARCHAR(100) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `category_en` | VARCHAR(100) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `name` | VARCHAR(255) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `name_en` | VARCHAR(255) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `description` | TEXT DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `price` | DECIMAL(12,2) NOT NULL DEFAULT 0 | _(افزوده در 003_module_tables_only)_ |
| `currency` | VARCHAR(3) DEFAULT 'IRR' | _(افزوده در 003_module_tables_only)_ |
| `image` | VARCHAR(500) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `is_available` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 003_module_tables_only)_ |
| `available_from` | TIME DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `available_to` | TIME DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `sort_order` | SMALLINT UNSIGNED DEFAULT 0 | _(افزوده در 003_module_tables_only)_ |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 003_module_tables_only)_ |

### `local_offers`

<sub>از مهاجرت 045_local_offers · 22 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `business_name` | VARCHAR(150) NOT NULL |  |
| `category` | VARCHAR(40) NOT NULL DEFAULT 'other' | food, shopping, tour, beauty, fun, other |
| `title` | VARCHAR(200) NOT NULL | مثلا «۲۰٪ تخفیف شام» |
| `description` | TEXT DEFAULT NULL |  |
| `terms` | VARCHAR(500) DEFAULT NULL | شرایط استفاده |
| `address` | VARCHAR(300) DEFAULT NULL |  |
| `phone` | VARCHAR(40) DEFAULT NULL |  |
| `distance` | VARCHAR(50) DEFAULT NULL |  |
| `image` | VARCHAR(500) DEFAULT NULL |  |
| `valid_from` | DATE DEFAULT NULL |  |
| `valid_to` | DATE DEFAULT NULL |  |
| `per_stay_limit` | TINYINT UNSIGNED NOT NULL DEFAULT 1 | چند کد برای هر اقامت |
| `total_limit` | INT UNSIGNED DEFAULT NULL | سقف کل کدها؛ خالی = بی‌سقف |
| `partner_token` | CHAR(32) NOT NULL | لینک تأیید کد برای خود کسب‌وکار |
| `partner_fails` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `partner_fail_at` | DATETIME DEFAULT NULL |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `sort_order` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

### `offer_claims`

<sub>از مهاجرت 045_local_offers · 12 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `offer_id` | INT UNSIGNED NOT NULL |  |
| `room_id` | INT UNSIGNED DEFAULT NULL |  |
| `screen_code` | VARCHAR(32) DEFAULT NULL |  |
| `guest_name` | VARCHAR(150) DEFAULT NULL | نام مهمان هنگام گرفتن کد |
| `code` | VARCHAR(12) NOT NULL |  |
| `status` | ENUM('issued','redeemed','void') NOT NULL DEFAULT 'issued' |  |
| `redeemed_at` | DATETIME DEFAULT NULL |  |
| `redeemed_via` | ENUM('panel','partner') DEFAULT NULL |  |
| `redeemed_by` | INT UNSIGNED DEFAULT NULL | کاربر پنل |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |

### `transport_schedules`

<sub>از مهاجرت 001_complete_schema · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 003_module_tables_only)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `type` | ENUM('bus','metro','train','tram') NOT NULL DEFAULT 'bus' | _(افزوده در 003_module_tables_only)_ |
| `line` | VARCHAR(100) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `direction` | VARCHAR(255) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `station` | VARCHAR(255) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `departure` | TIME NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `frequency_min` | SMALLINT UNSIGNED DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `days` | JSON DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `notes` | TEXT DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 003_module_tables_only)_ |

### `pms_integrations`

<sub>از مهاجرت 010_iptv_rooms · 16 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `name` | VARCHAR(100) NOT NULL | نام سیستم PMS |
| `api_key` | VARCHAR(64) NOT NULL |  |
| `pms_type` | VARCHAR(50) DEFAULT 'custom' | opera\|fidelio\|hotelogix\|custom |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `last_used_at` | DATETIME DEFAULT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `charge_url` | VARCHAR(500) DEFAULT NULL COMMENT | _(افزوده در 023_pms_charges_and_menus)_ |
| `auth_type` | ENUM( | _(افزوده در 023_pms_charges_and_menus)_ |
| `auth_secret` | VARCHAR(500) DEFAULT NULL COMMENT | _(افزوده در 023_pms_charges_and_menus)_ |
| `auth_header` | VARCHAR(80) DEFAULT NULL COMMENT | _(افزوده در 023_pms_charges_and_menus)_ |
| `push_charges` | TINYINT(1) NOT NULL DEFAULT 0 COMMENT | _(افزوده در 023_pms_charges_and_menus)_ |
| `field_map` | TEXT DEFAULT NULL COMMENT | _(افزوده در 023_pms_charges_and_menus)_ |
| `last_push_at` | DATETIME DEFAULT NULL | _(افزوده در 023_pms_charges_and_menus)_ |
| `last_push_msg` | VARCHAR(300) DEFAULT NULL | _(افزوده در 023_pms_charges_and_menus)_ |

### `pms_push_log`

<sub>از مهاجرت 023_pms_charges_and_menus · 9 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `charge_id` | INT UNSIGNED NOT NULL |  |
| `pms_id` | INT UNSIGNED DEFAULT NULL |  |
| `attempt` | TINYINT UNSIGNED NOT NULL DEFAULT 1 |  |
| `http_status` | SMALLINT UNSIGNED DEFAULT NULL |  |
| `ok` | TINYINT(1) NOT NULL DEFAULT 0 |  |
| `response` | VARCHAR(500) DEFAULT NULL |  |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |

## پرواز و شرکتی

تابلوی پرواز فرودگاه و صفحه‌های اداری.

### `flights`

<sub>از مهاجرت 032_flight_board_and_properties · 20 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT |  |
| `tenant_id` | INT UNSIGNED NOT NULL DEFAULT 1 |  |
| `location_id` | INT UNSIGNED DEFAULT NULL |  |
| `direction` | ENUM('arrival','departure') NOT NULL |  |
| `flight_number` | VARCHAR(20) NOT NULL |  |
| `airline` | VARCHAR(100) NOT NULL |  |
| `airline_logo` | VARCHAR(500) DEFAULT NULL |  |
| `city` | VARCHAR(100) NOT NULL |  |
| `city_en` | VARCHAR(100) DEFAULT NULL |  |
| `scheduled_at` | DATETIME NOT NULL |  |
| `estimated_at` | DATETIME DEFAULT NULL | اگر تاخیر داشته باشد |
| `status` | ENUM('scheduled','boarding','departed','arrived','delayed','cancelled') |  |
| `NOT` | NULL DEFAULT 'scheduled' |  |
| `terminal` | VARCHAR(20) DEFAULT NULL |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `source` | VARCHAR(40) DEFAULT 'manual' | manual یا نام سامانه‌ی مبدا |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `source_ref` | VARCHAR(40) DEFAULT NULL COMMENT | _(افزوده در 040_flight_source_and_board_brand)_ |
| `status_text` | VARCHAR(160) DEFAULT NULL COMMENT | _(افزوده در 040_flight_source_and_board_brand)_ |

### `fids_flights`

<sub>از مهاجرت 001_complete_schema · 27 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 003_module_tables_only)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `flight_number` | VARCHAR(20) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `airline_code` | VARCHAR(5) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `airline_name` | VARCHAR(100) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `airline_name_en` | VARCHAR(100) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `airline_logo` | VARCHAR(500) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `type` | ENUM('departure','arrival') NOT NULL DEFAULT 'departure' | _(افزوده در 003_module_tables_only)_ |
| `origin` | VARCHAR(100) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `origin_code` | VARCHAR(5) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `destination` | VARCHAR(100) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `destination_code` | VARCHAR(5) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `scheduled_time` | DATETIME NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `estimated_time` | DATETIME DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `actual_time` | DATETIME DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `terminal` | VARCHAR(20) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `gate` | VARCHAR(20) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `belt` | VARCHAR(20) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `status` | ENUM('scheduled','boarding','departed','arrived','delayed','cancelled','diverted','gate_change') NOT NULL DEFA | _(افزوده در 003_module_tables_only)_ |
| `status_fa` | VARCHAR(50) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `delay_minutes` | SMALLINT DEFAULT 0 | _(افزوده در 003_module_tables_only)_ |
| `aircraft_type` | VARCHAR(50) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `remarks` | TEXT DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `remarks_en` | TEXT DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 003_module_tables_only)_ |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP | _(افزوده در 003_module_tables_only)_ |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | _(افزوده در 003_module_tables_only)_ |

### `fids_airlines`

<sub>از مهاجرت 001_complete_schema · 7 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 003_module_tables_only)_ |
| `code` | VARCHAR(5) NOT NULL UNIQUE | _(افزوده در 003_module_tables_only)_ |
| `name_fa` | VARCHAR(100) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `name_en` | VARCHAR(100) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `logo_url` | VARCHAR(500) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `country` | VARCHAR(50) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `color` | VARCHAR(7) DEFAULT '#FFFFFF' | _(افزوده در 003_module_tables_only)_ |

### `inflight_flights`

<sub>از مهاجرت 011_inflight · 33 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PRIMARY KEY |  |
| `tenant_id` | INT UNSIGNED NOT NULL |  |
| `flight_number` | VARCHAR(20) NOT NULL |  |
| `airline_name` | VARCHAR(100) DEFAULT NULL |  |
| `airline_logo` | VARCHAR(500) DEFAULT NULL |  |
| `origin_iata` | VARCHAR(10) DEFAULT NULL |  |
| `origin_city` | VARCHAR(100) DEFAULT NULL |  |
| `origin_country` | VARCHAR(100) DEFAULT NULL |  |
| `origin_lat` | DECIMAL(10,6) DEFAULT NULL |  |
| `origin_lng` | DECIMAL(10,6) DEFAULT NULL |  |
| `origin_timezone` | VARCHAR(60) DEFAULT 'UTC' |  |
| `dest_iata` | VARCHAR(10) DEFAULT NULL |  |
| `dest_city` | VARCHAR(100) DEFAULT NULL |  |
| `dest_country` | VARCHAR(100) DEFAULT NULL |  |
| `dest_lat` | DECIMAL(10,6) DEFAULT NULL |  |
| `dest_lng` | DECIMAL(10,6) DEFAULT NULL |  |
| `dest_timezone` | VARCHAR(60) DEFAULT 'UTC' |  |
| `departure_at` | DATETIME DEFAULT NULL |  |
| `arrival_at` | DATETIME DEFAULT NULL |  |
| `phase` | ENUM('preflight','taxi','takeoff','climb','cruise','descent','approach','landing','landed') |  |
| `NOT` | NULL DEFAULT 'preflight' |  |
| `progress_pct` | TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 0-100 |  |
| `altitude_ft` | INT UNSIGNED NOT NULL DEFAULT 0 |  |
| `speed_kmh` | SMALLINT UNSIGNED NOT NULL DEFAULT 0 |  |
| `heading_deg` | SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- 0-359 |  |
| `accent_color` | VARCHAR(7) NOT NULL DEFAULT '#00b4d8' |  |
| `bg_style` | ENUM('space','clouds','ocean','dusk') NOT NULL DEFAULT 'space' |  |
| `welcome_msg` | VARCHAR(255) DEFAULT NULL |  |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |  |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP |  |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |
| `rpi_ip` | VARCHAR(45) DEFAULT NULL AFTER welcome_msg | _(افزوده در 012_inflight_rpi)_ |
| `rpi_port` | SMALLINT UNSIGNED NOT NULL DEFAULT 5055 AFTER rpi_ip | _(افزوده در 012_inflight_rpi)_ |

### `corp_departments`

<sub>از مهاجرت 001_complete_schema · 10 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 003_module_tables_only)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `name` | VARCHAR(255) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `floor` | VARCHAR(20) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `room` | VARCHAR(20) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `phone` | VARCHAR(20) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `manager` | VARCHAR(255) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `icon` | VARCHAR(100) DEFAULT 'fas fa-door-open' | _(افزوده در 003_module_tables_only)_ |
| `sort_order` | SMALLINT UNSIGNED DEFAULT 0 | _(افزوده در 003_module_tables_only)_ |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 003_module_tables_only)_ |

### `corp_kpi`

<sub>از مهاجرت 001_complete_schema · 12 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 003_module_tables_only)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `name` | VARCHAR(255) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `value` | VARCHAR(100) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `target` | VARCHAR(100) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `unit` | VARCHAR(50) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `change_pct` | DECIMAL(5,2) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `icon` | VARCHAR(100) DEFAULT 'fas fa-chart-line' | _(افزوده در 003_module_tables_only)_ |
| `color` | VARCHAR(7) DEFAULT '#6366f1' | _(افزوده در 003_module_tables_only)_ |
| `sort_order` | SMALLINT UNSIGNED DEFAULT 0 | _(افزوده در 003_module_tables_only)_ |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 003_module_tables_only)_ |
| `updated_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | _(افزوده در 003_module_tables_only)_ |

### `corp_news`

<sub>از مهاجرت 001_complete_schema · 11 ستون</sub>

| ستون | نوع | توضیح |
|---|---|---|
| `id` | INT UNSIGNED NOT NULL AUTO_INCREMENT | _(افزوده در 003_module_tables_only)_ |
| `tenant_id` | INT UNSIGNED NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `title` | VARCHAR(500) NOT NULL | _(افزوده در 003_module_tables_only)_ |
| `body` | TEXT DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `image` | VARCHAR(500) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `category` | VARCHAR(100) DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `priority` | TINYINT UNSIGNED DEFAULT 5 | _(افزوده در 003_module_tables_only)_ |
| `is_pinned` | TINYINT(1) DEFAULT 0 | _(افزوده در 003_module_tables_only)_ |
| `published_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | _(افزوده در 003_module_tables_only)_ |
| `expires_at` | TIMESTAMP NULL DEFAULT NULL | _(افزوده در 003_module_tables_only)_ |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | _(افزوده در 003_module_tables_only)_ |
