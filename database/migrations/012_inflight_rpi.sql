-- ── 012 In-Flight RPi Bridge columns ───────────────────────────────────────
SET @db = DATABASE();

SET @q1 = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=@db AND TABLE_NAME='inflight_flights' AND COLUMN_NAME='rpi_ip') = 0,
    'ALTER TABLE inflight_flights ADD COLUMN rpi_ip VARCHAR(45) DEFAULT NULL AFTER welcome_msg',
    'SELECT 1'
);
PREPARE s1 FROM @q1; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @q2 = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=@db AND TABLE_NAME='inflight_flights' AND COLUMN_NAME='rpi_port') = 0,
    'ALTER TABLE inflight_flights ADD COLUMN rpi_port SMALLINT UNSIGNED NOT NULL DEFAULT 5055 AFTER rpi_ip',
    'SELECT 1'
);
PREPARE s2 FROM @q2; EXECUTE s2; DEALLOCATE PREPARE s2;
