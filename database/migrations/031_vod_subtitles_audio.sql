-- ════════════════════════════════════════════════════════════════════
-- زیرنویس چندزبانه و دوبله برای VOD
-- ════════════════════════════════════════════════════════════════════
--
-- مهمان خارجی در هتل ایرانی فیلم فارسی می‌بیند و مهمان ایرانی فیلم
-- خارجی — هر دو به زیرنویس نیاز دارند، و بعضی فیلم‌ها دوبله هم دارند.
--
-- ── چرا این ساختار ──────────────────────────────────────────────────
--
-- زیرنویس: فایل بیرونی که در پلیر با تگ <track> سوار می‌شود. این تنها
-- راهی است که روی هر سه پلتفرم کار می‌کند:
--   • مرورگر تلویزیون LG و Samsung از Chromium 23 تگ track را دارد
--   • AVPlay سامسونگ فقط SAMI و SMPTE-TT می‌پذیرد، نه SRT و VTT، و
--     فایل بیرونی را هم باید اول در حافظه‌ی محلی دانلود کند — که روی
--     URL Launcher اصلا ممکن نیست
-- پس سرور هر چیزی را به WebVTT تبدیل می‌کند و همه‌جا همان سرو می‌شود.
--
-- دوبله: دو حالت دارد و هر دو پشتیبانی می‌شود، چون منبع محتوا در
-- هتل‌های مختلف فرق می‌کند:
--   • kind='file' — یک فایل ویدیوی جداگانه با صدای دوبله. پلیر موقع
--     تعویض، منبع را عوض می‌کند و زمان فعلی را نگه می‌دارد. روی هر
--     پلتفرمی کار می‌کند.
--   • kind='hls'  — باند صوتی داخل همان جریان HLS. پلیر با
--     hls.audioTrack عوضش می‌کند، بدون قطع تصویر.
--
-- حالت سوم — چند باند صوتی داخل یک فایل MP4 — عمدا پشتیبانی نمی‌شود:
-- مرورگرهای Chromium خاصیت audioTracks را اصلا ندارند و هیچ راهی برای
-- تعویض باند داخل MP4 از سمت صفحه وجود ندارد.
--
-- این مهاجرت idempotent است.

-- ── زیرنویس‌ها ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vod_subtitles (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id   INT UNSIGNED NOT NULL DEFAULT 1,
  vod_id      INT UNSIGNED NOT NULL,

  -- کد زبان استاندارد، همان چیزی که در srclang تگ track می‌رود
  lang        VARCHAR(10)  NOT NULL COMMENT 'fa, en, ar …',
  -- نامی که مهمان می‌بیند؛ «فارسی» خواناتر از «fa» است
  label       VARCHAR(80)  NOT NULL,

  -- همیشه WebVTT ذخیره می‌شود. قالب اصلی فقط برای اینکه اپراتور
  -- بداند چه آپلود کرده و اگر تبدیل بد شد بتواند دنبالش بگردد.
  source_format ENUM('srt','vtt','ass','sub') NOT NULL DEFAULT 'srt',
  file_path   VARCHAR(1000) NOT NULL COMMENT 'مسیر فایل .vtt نسبت به public',
  file_size   INT UNSIGNED  NOT NULL DEFAULT 0,

  -- زیرنویس ناشنوایان؛ در فهرست جدا علامت می‌خورد
  is_sdh      TINYINT(1)   NOT NULL DEFAULT 0,
  -- کدام زیرنویس بدون انتخاب مهمان روشن باشد
  is_default  TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,

  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  -- یک زبان برای هر فیلم فقط یک بار؛ وگرنه فهرست مهمان تکراری می‌شود
  UNIQUE KEY uniq_vod_lang (vod_id, lang, is_sdh),
  KEY idx_vod (vod_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── باندهای صوتی (دوبله) ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vod_audio_tracks (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id   INT UNSIGNED NOT NULL DEFAULT 1,
  vod_id      INT UNSIGNED NOT NULL,

  lang        VARCHAR(10)  NOT NULL COMMENT 'fa, en, ar …',
  label       VARCHAR(80)  NOT NULL COMMENT 'مثلا «دوبله فارسی»',

  kind        ENUM('file','hls') NOT NULL DEFAULT 'file',
  -- برای kind=file: مسیر ویدیوی جایگزین با همان تصویر و صدای دیگر
  file_path   VARCHAR(1000) DEFAULT NULL,
  -- برای kind=hls: نمایه‌ی باند داخل جریان
  track_index TINYINT UNSIGNED DEFAULT NULL,

  is_default  TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,

  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_vod_lang (vod_id, lang),
  KEY idx_vod (vod_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── زبان مهمان ───────────────────────────────────────────────────────
-- ستون جدیدی لازم نیست: iptv_rooms از مهاجرت ۰۱۰ ستون guest_lang را
-- دارد و پذیرش همان را موقع پذیرش پر می‌کند. زیرنویس و باند صوتی از
-- همان خوانده می‌شوند، تا مهمانی که فارسی نمی‌داند مجبور نشود در منوی
-- ناآشنا دنبال تنظیمات بگردد.
