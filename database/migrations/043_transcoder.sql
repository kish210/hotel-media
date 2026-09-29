-- ── ترنسکدر — معادل «رامند» کاماسیستم ─────────────────────────────
-- تا امروز ترنسکدر یک فرم بود که ffmpeg را با nohup اجرا می‌کرد و
-- وضعیتش را در /tmp/signage_hls/sessions.json نگه می‌داشت: با
-- راه‌اندازی دوباره‌ی سرور همه‌چیز گم می‌شد، کانالی که ffmpeg اش
-- می‌افتاد دیگر برنمی‌گشت، و فقط سه کیفیت ثابت و یک خروجی HLS داشت.
--
-- هر ردیف یک «کار» است: یک ورودی، تنظیمات تصویر و صدا، یک یا چند
-- کیفیت (ABR)، و یک یا چند خروجی. کارِ زنده (live) تا توقف دستی
-- اجرا می‌ماند و ناظر (transcoder:supervise) اگر افتاد برش می‌گرداند؛
-- کارِ فایل (vod) یک بار اجرا می‌شود و تمام می‌شود.
--
-- تنظیمات به‌صورت JSON است نه ده‌ها ستون: ffmpeg گزینه‌های زیادی دارد
-- و هر ستون تازه یک مهاجرت تازه می‌خواست. قاعده‌ی معتبر بودن در
-- TranscoderService::normalize است، نه در اسکیما.

CREATE TABLE IF NOT EXISTS transcoder_jobs (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id     INT UNSIGNED NOT NULL,
  name          VARCHAR(120) NOT NULL,
  slug          VARCHAR(60)  NOT NULL COMMENT 'نام پوشه‌ی خروجی و مسیر /hls/tc-{slug}/',
  mode          ENUM('live','vod') NOT NULL DEFAULT 'live',
  input_url     VARCHAR(1000) NOT NULL COMMENT 'udp:// rtp:// rtsp:// rtmp:// http(s):// srt:// یا مسیر فایل/دستگاه',
  input_kind    ENUM('url','file','v4l2','decklink') NOT NULL DEFAULT 'url',
  settings      JSON         NOT NULL COMMENT 'video, renditions, audio, subtitles, overlay, outputs, hw',
  channel_id    INT UNSIGNED DEFAULT NULL COMMENT 'کانال IPTV که این خروجی را پخش می‌کند',
  -- چیزی که اپراتور خواسته. بعد از قطع برق هم می‌ماند، پس ناظر کانال‌های
  -- «running» را خودش دوباره راه می‌اندازد — ستون جدای autostart لازم نیست.
  desired       ENUM('running','stopped') NOT NULL DEFAULT 'stopped',
  status        ENUM('stopped','starting','running','error','finished') NOT NULL DEFAULT 'stopped',
  pid           INT UNSIGNED DEFAULT NULL,
  started_at    DATETIME     DEFAULT NULL,
  restarts      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'از آخرین شروع دستی',
  last_restart_at DATETIME   DEFAULT NULL,
  last_error    VARCHAR(500) DEFAULT NULL,
  progress      TINYINT UNSIGNED DEFAULT NULL COMMENT 'درصد پیشرفت کار vod',
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (tenant_id, slug),
  KEY idx_desired (desired, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
