-- ── IPTV Menus & Items ───────────────────────────────────────────
-- هر گروه IPTV می‌تونه یک یا چند منو داشته باشه
-- هر منو شامل آیتم‌هایی مثل پخش زنده، VOD، اخبار، ... میشه

CREATE TABLE IF NOT EXISTS iptv_menus (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED NOT NULL,
    group_id    INT UNSIGNED DEFAULT NULL COMMENT 'FK → screen_groups',
    name        VARCHAR(120)  NOT NULL,
    description VARCHAR(300)  DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_iptv_menus_tenant (tenant_id),
    INDEX idx_iptv_menus_group  (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS iptv_menu_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_id     INT UNSIGNED NOT NULL,
    type        ENUM('live','vod','news','info','weather','fids','hotel','corporate','retail','url','custom')
                NOT NULL DEFAULT 'live',
    label       VARCHAR(100) NOT NULL,
    icon        VARCHAR(80)  NOT NULL DEFAULT 'fas fa-play',
    color       VARCHAR(20)  NOT NULL DEFAULT '#f97316',
    target_url  VARCHAR(500) DEFAULT NULL,
    config      JSON         DEFAULT NULL,
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_menu_items_menu (menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ستون menu_id در screens
-- (installer از addColIfMissing استفاده می‌کنه)
