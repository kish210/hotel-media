-- ── 011 In-Flight Display Module ────────────────────────────────────────────
-- MySQL 8 compatible (no ADD COLUMN IF NOT EXISTS)

CREATE TABLE IF NOT EXISTS inflight_flights (
    id            INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT UNSIGNED     NOT NULL,
    flight_number VARCHAR(20)      NOT NULL,
    airline_name  VARCHAR(100)     DEFAULT NULL,
    airline_logo  VARCHAR(500)     DEFAULT NULL,

    -- Origin airport
    origin_iata     VARCHAR(10)    DEFAULT NULL,
    origin_city     VARCHAR(100)   DEFAULT NULL,
    origin_country  VARCHAR(100)   DEFAULT NULL,
    origin_lat      DECIMAL(10,6)  DEFAULT NULL,
    origin_lng      DECIMAL(10,6)  DEFAULT NULL,
    origin_timezone VARCHAR(60)    DEFAULT 'UTC',

    -- Destination airport
    dest_iata       VARCHAR(10)    DEFAULT NULL,
    dest_city       VARCHAR(100)   DEFAULT NULL,
    dest_country    VARCHAR(100)   DEFAULT NULL,
    dest_lat        DECIMAL(10,6)  DEFAULT NULL,
    dest_lng        DECIMAL(10,6)  DEFAULT NULL,
    dest_timezone   VARCHAR(60)    DEFAULT 'UTC',

    -- Scheduled times (stored in UTC)
    departure_at    DATETIME       DEFAULT NULL,
    arrival_at      DATETIME       DEFAULT NULL,

    -- Live telemetry (updated in real time by admin/crew)
    phase           ENUM('preflight','taxi','takeoff','climb','cruise','descent','approach','landing','landed')
                                   NOT NULL DEFAULT 'preflight',
    progress_pct    TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- 0-100
    altitude_ft     INT UNSIGNED   NOT NULL DEFAULT 0,
    speed_kmh       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    heading_deg     SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- 0-359

    -- Appearance
    accent_color    VARCHAR(7)     NOT NULL DEFAULT '#00b4d8',
    bg_style        ENUM('space','clouds','ocean','dusk') NOT NULL DEFAULT 'space',

    -- Welcome message shown on screen
    welcome_msg     VARCHAR(255)   DEFAULT NULL,

    is_active       TINYINT(1)     NOT NULL DEFAULT 1,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant  (tenant_id),
    INDEX idx_active  (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add inflight_flight_id to screens (MySQL 8 compatible)
SET @db = DATABASE();

SET @q1 = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=@db AND TABLE_NAME='screens' AND COLUMN_NAME='inflight_flight_id') = 0,
    'ALTER TABLE screens ADD COLUMN inflight_flight_id INT UNSIGNED DEFAULT NULL',
    'SELECT 1'
);
PREPARE s1 FROM @q1; EXECUTE s1; DEALLOCATE PREPARE s1;
