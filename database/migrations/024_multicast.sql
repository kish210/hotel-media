-- ── پخش زنده به‌صورت Multicast ───────────────────────────────────
-- مسئله‌ای که حل می‌کند:
--   با HTTP/HLS هر تلویزیون یک جریان جدا می‌گیرد. اگر ۲۰۰ اتاق یک کانال
--   را ببینند، سرور ۲۰۰ جریان می‌فرستد:
--       ۲۰۰ × ۸Mbps = ۱.۶ Gbps
--   با multicast، همان کانال **یک بار** روی شبکه پخش می‌شود و سوییچ با
--   IGMP snooping آن را فقط به پورت‌هایی می‌دهد که درخواست کرده‌اند:
--       ۱ × ۸Mbps = ۸ Mbps   (مستقل از تعداد اتاق)
--
-- محدودیت مهمی که باید بدانید:
--   ‏HTML5 و تگ <video> نمی‌توانند UDP multicast بخوانند — مرورگر فقط
--   HTTP/HLS/DASH می‌فهمد. پس:
--     • تلویزیون هتلی LG و Samsung کانال multicast را با تیونر IP خودشان
--       می‌گیرند (بار صفر روی سرور) — از فهرست کانال خود تلویزیون
--     • اپ بومی Android TV هم می‌تواند
--     • پورتال HTML5 ما نمی‌تواند؛ برای آن udpxy لازم است که multicast را
--       به HTTP تبدیل می‌کند (فقط برای تعداد کم)

-- نوع تحویل کانال
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_channels' AND COLUMN_NAME='delivery')=0,
  "ALTER TABLE iptv_channels ADD COLUMN delivery ENUM('unicast','multicast','both') NOT NULL DEFAULT 'unicast' COMMENT 'multicast = پخش یک‌باره روی شبکه'",
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- آدرس multicast — مثلا udp://@239.1.1.10:5000 یا rtp://@239.1.1.10:5000
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_channels' AND COLUMN_NAME='multicast_url')=0,
  'ALTER TABLE iptv_channels ADD COLUMN multicast_url VARCHAR(255) DEFAULT NULL COMMENT "udp://@239.x.x.x:port"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- شماره کانال روی ریموت تلویزیون — در فهرست کانال هتل لازم است
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_channels' AND COLUMN_NAME='channel_no')=0,
  'ALTER TABLE iptv_channels ADD COLUMN channel_no SMALLINT UNSIGNED DEFAULT NULL COMMENT "شماره‌ای که مهمان روی ریموت می‌زند"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_channels' AND INDEX_NAME='idx_channel_no')=0,
  'ALTER TABLE iptv_channels ADD INDEX idx_channel_no (tenant_id, channel_no)',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── تنظیمات multicast هر هتل ────────────────────────────────────
CREATE TABLE IF NOT EXISTS multicast_config (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id      INT UNSIGNED NOT NULL,
  -- محدوده‌ی آدرس گروه — پیش‌فرض بازه‌ی administratively scoped
  base_group     VARCHAR(20)  NOT NULL DEFAULT '239.1.1.1' COMMENT 'اولین آدرس گروه',
  base_port      SMALLINT UNSIGNED NOT NULL DEFAULT 5000,
  port_step      SMALLINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'RTP پورت زوج می‌خواهد',
  ttl            TINYINT UNSIGNED NOT NULL DEFAULT 4,
  -- udpxy برای کلاینت‌هایی که multicast نمی‌فهمند (پورتال HTML5)
  udpxy_url      VARCHAR(255) DEFAULT NULL COMMENT 'مثلا http://10.0.0.5:4022',
  interface_ip   VARCHAR(45)  DEFAULT NULL COMMENT 'IP کارت شبکه‌ای که پخش از آن خارج می‌شود',
  is_active      TINYINT(1)   NOT NULL DEFAULT 0,
  created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
