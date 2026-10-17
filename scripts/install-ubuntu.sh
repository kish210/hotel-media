#!/bin/bash
# ═══════════════════════════════════════════════════════════════════
#  Hotel Media — نصب روی Ubuntu Server
#  سماع رایانه کیش · kishwifi.com
# ═══════════════════════════════════════════════════════════════════
#
#  یک فرمان، روی یک Ubuntu Server تازه:
#
#      sudo bash install-ubuntu.sh
#
#  چه می‌کند: nginx، PHP 8.3-FPM، MariaDB، ffmpeg را نصب می‌کند؛
#  دیتابیس و کاربرش را می‌سازد؛ کد را در /var/www/hotel-media
#  می‌گذارد؛ migration ها را اجرا می‌کند؛ کارهای زمان‌بندی‌شده را
#  ثبت می‌کند؛ و رمز مدیر را چاپ می‌کند.
#
#  چرا اوبونتو و نه ویندوز: نصب‌کنندهٔ ویندوزی وب‌سرور را با `php -S`
#  بالا می‌آورد که تک‌نخی است. اندازه‌گیری در docs/INSTALL-300-ROOMS.md
#  نشان داد روی مسیر پورتال ۳۷ درخواست در ثانیه می‌دهد — برای هتل
#  بزرگ کافی نیست.
#
#  این فایل **روادار** است: اجرای دوباره چیزی را خراب نمی‌کند. اگر
#  دیتابیس از قبل هست، داده‌اش دست نمی‌خورد و فقط migration های تازه
#  اجرا می‌شوند.
# ═══════════════════════════════════════════════════════════════════

set -euo pipefail

APP_DIR=/var/www/hotel-media
DB_NAME=hotel_media
DB_USER=hotel_media
LOG=/var/log/hotel-media-install.log

# نسخهٔ PHP از مخزن همین سیستم کشف می‌شود، نه هاردکد.
#
# پیش از این `PHP_V=8.3` ثابت بود. اوبونتو 24.04 همان 8.3 را دارد و
# همه‌چیز کار می‌کرد، ولی 26.04 فقط PHP 8.5 دارد و نصب‌کننده روی آن
# بی‌هیچ پیامی می‌شکست (INSTALL-ISSUES.md، ردیف ۱). ‏PPA ‏ondrej هم
# راه‌حل نیست: برای 26.04 بسته ندارد (dists/resolute → ۴۰۴).
#
# بالاترین نسخهٔ موجود برداشته می‌شود؛ با `PHP_V=8.3 sudo -E bash ...`
# می‌شود دستی عوضش کرد.
if [ -z "${PHP_V:-}" ]; then
    PHP_V=$(apt-cache search --names-only '^php[0-9]+\.[0-9]+-fpm$' 2>/dev/null \
            | sed -E 's/^php([0-9]+\.[0-9]+)-fpm.*/\1/' | sort -V | tail -1)
fi
[ -n "$PHP_V" ] || { echo "هیچ بستهٔ php-fpm در مخزن پیدا نشد" >&2; exit 1; }
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

c_ok()   { printf "\033[32m  ✓\033[0m %s\n" "$1"; }
c_info() { printf "\033[36m→\033[0m %s\n" "$1"; }
c_warn() { printf "\033[33m  !\033[0m %s\n" "$1"; }
c_die()  { printf "\033[31m  ✗ %s\033[0m\n" "$1" >&2; exit 1; }

# رشتهٔ تصادفی با طول مشخص.
#
# ‏`tr -dc ... </dev/urandom | head -c N` زیر `set -o pipefail` اسکریپت را
# **بی‌صدا می‌کشد**: head بعد از N نویسه می‌بندد، tr با SIGPIPE (کد ۱۴۱)
# می‌میرد، pipefail آن را کد خروج خط می‌کند و `set -e` کل نصب را قطع
# می‌کند — بی هیچ پیام. نصب روی 26.04 دقیقا همین‌جا، بعد از «آماده‌سازی
# دیتابیس» می‌مرد (INSTALL-ISSUES.md، ردیف ۹). ‏`|| true` فقط کد خروج را
# خنثی می‌کند؛ خروجی سالم است.
rand() { tr -dc 'A-Za-z0-9' </dev/urandom | head -c "$1" || true; }

[ "$(id -u)" = "0" ] || c_die "با sudo اجرا کنید"

# ── ۰) بررسی سیستم ────────────────────────────────────────────────
c_info "بررسی سیستم"
. /etc/os-release 2>/dev/null || c_die "سیستم‌عامل شناسایی نشد"
[ "${ID:-}" = "ubuntu" ] || c_warn "این اسکریپت برای اوبونتو نوشته شده (یافت شد: ${ID:-نامشخص})"
c_ok "${PRETTY_NAME:-?}"

# RAM و دیسک: برای ۳۰۰ اتاق کمتر از این عملا کار نمی‌کند و بهتر است
# همین اول گفته شود تا بعد از نصب کامل.
MEM_MB=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
DISK_GB=$(df -BG --output=avail / | tail -1 | tr -dc '0-9')
[ "$MEM_MB" -lt 1800 ] && c_warn "حافظه ${MEM_MB}MB است — برای هتل واقعی حداقل ۴ گیگابایت توصیه می‌شود"
[ "$DISK_GB" -lt 20 ] && c_warn "فضای آزاد ${DISK_GB}GB است — ویدیو و ضبط زیاد جا می‌خواهد"
c_ok "حافظه ${MEM_MB}MB · فضای آزاد ${DISK_GB}GB"

# ── ۱) بسته‌ها ────────────────────────────────────────────────────
c_info "نصب بسته‌ها (چند دقیقه طول می‌کشد)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq

# فهرست از روی همان چیزی است که روی سرور تولید کار می‌کند، نه حدس:
#   php-gd      تصویر و پوستر
#   php-intl    تاریخ شمسی و مرتب‌سازی فارسی
#   php-mbstring متن فارسی
#   ffmpeg      تبدیل ویدیو، HLS، دوربین، ترنسکد
#   poppler-utils  تبدیل PDF به تصویر برای کتاب‌خوان تلویزیون
PKGS="nginx mariadb-server ffmpeg poppler-utils curl unzip git ca-certificates whiptail gnupg
  php${PHP_V}-fpm php${PHP_V}-cli php${PHP_V}-mysql php${PHP_V}-mbstring
  php${PHP_V}-xml php${PHP_V}-curl php${PHP_V}-gd php${PHP_V}-intl
  php${PHP_V}-zip php${PHP_V}-bcmath"

# ‏opcache فقط اگر بستهٔ جدا دارد. از PHP 8.5 داخل هسته ادغام شده و
# `php8.5-opcache` در مخزن **وجود ندارد** — هاردکدکردنش باعث می‌شد
# کل `apt-get install` با exit=100 بشکند (INSTALL-ISSUES.md، ردیف ۲).
if [ -n "$(apt-cache policy php${PHP_V}-opcache 2>/dev/null | awk '/Candidate:/ && $2 != "(none)" {print $2}')" ]; then
    PKGS="$PKGS php${PHP_V}-opcache"
else
    c_ok "opcache داخل هستهٔ PHP ${PHP_V} است (بستهٔ جدا لازم نیست)"
fi

# خروجی apt ثبت می‌شود، نه دور ریخته: با `>/dev/null` فقط «exit=100»
# دیده می‌شد و علت (یک بستهٔ ناموجود) برای همیشه پنهان می‌ماند.
# shellcheck disable=SC2086
if ! apt-get install -y -qq --no-install-recommends $PKGS >>"$LOG" 2>&1; then
    echo "── آخرین خطوط خروجی apt ($LOG) ──" >&2
    tail -15 "$LOG" >&2
    c_die "نصب بسته‌ها ناموفق بود"
fi

# درستی را بررسی می‌کنیم، نه فقط کد خروج: apt گاهی با کد ۰ برمی‌گردد
# ولی بسته‌ای نصب نشده. بستهٔ جاافتاده **نام برده می‌شود**.
MISSING=""
for p in $PKGS; do
    dpkg -s "$p" >/dev/null 2>&1 || MISSING="$MISSING $p"
done
[ -z "$MISSING" ] || c_die "این بسته‌ها نصب نشدند:$MISSING"
c_ok "بسته‌ها نصب شدند (PHP ${PHP_V})"

# ── ۱b) TVHeadend ───────────────────────────────────────────────
# جزو بستهٔ Hotel Media است: ‏IPTV (کانال زنده، EPG، ضبط، تایم‌شیفت)
# بی آن کار نمی‌کند و کد ما انتظار آن را روی `http://127.0.0.1:9981`
# دارد (TvheadendService)، یعنی روی همین ماشین.
#
# چرا مخزن رسمی و نه `apt install tvheadend`: بسته‌ی tvheadend در مخزن
# اوبونتو 26.04 **اصلا نیست** (apt-cache خالی برمی‌گرداند — روی
# سرور دوم دیده شد). مخزن رسمی Cloudsmith برای `resolute` و `noble`
# هر دو بسته دارد.
#
# چرا کلید در keyring جدا و `signed-by` و نه `curl | sudo bash`: اسکریپت
# راه‌انداز مخزن را بی‌بازبینی با دسترسی root اجرا می‌کند. این‌جا فقط
# یک کلید عمومی گرفته می‌شود و فقط برای همین یک مخزن معتبر است.
#
# نصب شکست بخورد کشنده نیست: بقیهٔ پلتفرم (تابلو، VOD، پورتال مهمان)
# بی TVHeadend کار می‌کند و اپراتور بعدا می‌تواند دستی نصبش کند.
install_tvheadend() {
    local codename; codename=$(. /etc/os-release && echo "$VERSION_CODENAME")
    local base="https://dl.cloudsmith.io/public/tvheadend/tvheadend"

    # فقط اگر مخزن برای همین نسخه بسته دارد (404 یعنی ندارد)
    if ! curl -fsI -m 15 "$base/deb/ubuntu/dists/$codename/InRelease" >/dev/null 2>&1; then
        c_warn "مخزن رسمی TVHeadend برای «$codename» بسته ندارد — رد شد"
        return 1
    fi

    install -d -m 0755 /usr/share/keyrings
    if ! curl -fsSL -m 30 "$base/gpg.key" | gpg --dearmor --yes -o /usr/share/keyrings/tvheadend.gpg 2>>"$LOG"; then
        c_warn "کلید مخزن TVHeadend گرفته نشد — رد شد"
        return 1
    fi

    echo "deb [signed-by=/usr/share/keyrings/tvheadend.gpg] $base/deb/ubuntu $codename main" \
        > /etc/apt/sources.list.d/tvheadend.list
    apt-get update -qq >>"$LOG" 2>&1 || { c_warn "به‌روزرسانی فهرست بسته‌ها شکست خورد"; return 1; }

    # tvheadend یک debconf می‌پرسد (نام کاربر/رمز مدیر). noninteractive
    # پیش‌فرض می‌گذارد؛ رمز را خودمان پایین‌تر می‌گذاریم.
    if ! apt-get install -y -qq --no-install-recommends tvheadend >>"$LOG" 2>&1; then
        c_warn "نصب tvheadend شکست خورد — آخرین خطوط:"; tail -5 "$LOG" >&2
        return 1
    fi
    dpkg -s tvheadend >/dev/null 2>&1 || { c_warn "tvheadend نصب نشد"; return 1; }

    # فقط روی loopback. بسته به‌طور پیش‌فرض روی `*` گوش می‌دهد (پورت‌های
    # ۹۹۸۱ و ۹۹۸۲ از کل شبکهٔ هتل دیده می‌شد — روی سرور دوم با `ss`
    # دیده شد). برنامهٔ ما فقط با 127.0.0.1 حرف می‌زند، پس هیچ دلیلی
    # برای باز گذاشتنش نیست؛ اپراتور لازم است از بیرون ببیندش، با
    # tunnel SSH یا تغییر همین خط.
    sed -i 's|^OPTIONS=.*|OPTIONS="-u hts -g video -6 -b 127.0.0.1"|' /etc/default/tvheadend

    # حساب ادمین با رمز تصادفی (نگاه کنید به tvheadend-bootstrap.sh)
    TVH_PASS=$(rand 20)
    if [ -f "$APP_DIR/scripts/tvheadend-bootstrap.sh" ] \
       && bash "$APP_DIR/scripts/tvheadend-bootstrap.sh" "$TVH_PASS" >>"$LOG" 2>&1; then
        # رمز برای tvheadend:setup و پایش ذخیره می‌شود — بی ذخیره‌اش
        # برنامه نمی‌تواند وصل شود.
        TVH_USER=hotelmedia
        c_ok "حساب ادمین TVHeadend ساخته شد"
    else
        c_warn "حساب TVHeadend ساخته نشد (آخرین خطوط $LOG را ببینید)"
        TVH_PASS=""
    fi

    systemctl enable tvheadend >/dev/null 2>&1 || true
    systemctl restart tvheadend >/dev/null 2>&1 || true
    c_ok "TVHeadend $(dpkg-query -W -f='${Version}' tvheadend 2>/dev/null | cut -d~ -f1) نصب شد (http://127.0.0.1:9981)"
}

c_info "نصب TVHeadend"
install_tvheadend || c_warn "TVHeadend نصب نشد؛ بقیهٔ نصب ادامه دارد (IPTV زنده بی آن کار نمی‌کند)"

# ── ۲) کد برنامه ──────────────────────────────────────────────────
c_info "استقرار کد در $APP_DIR"
mkdir -p "$APP_DIR"

if [ "$(readlink -f "$SRC_DIR")" = "$(readlink -f "$APP_DIR")" ]; then
    # اسکریپت از داخل خودِ پوشهٔ مقصد اجرا شده (مثلا بعد از git clone).
    # کپی کردن یک پوشه روی خودش لازم نیست و کشنده هم هست: tar فایل‌ها
    # را «در حین خواندن» تغییر‌یافته می‌بیند، با کد ۱ برمی‌گردد و
    # `set -e` + `pipefail` کل نصب را می‌کشد — فقط با خطوط
    # «tar: file changed as we read it» و بی هیچ پیام فارسی
    # (INSTALL-ISSUES.md، ردیف ۸).
    c_ok "کد همین‌جاست ($APP_DIR) — کپی لازم نیست"
elif [ -f "$SRC_DIR/artisan" ] && [ -d "$SRC_DIR/app" ]; then
    # کنار همین اسکریپت کد هست (حالت ISO یا آرشیو)
    tar -C "$SRC_DIR" --exclude=.git --exclude=storage/logs \
        -cf - . | tar -C "$APP_DIR" -xf -
    c_ok "کد از $SRC_DIR کپی شد"
elif [ -d "$APP_DIR/.git" ]; then
    git -C "$APP_DIR" pull --ff-only -q && c_ok "کد به‌روز شد"
else
    git clone -q --depth 1 https://github.com/kish210/hotel-media.git "$APP_DIR" \
      && c_ok "کد از GitHub گرفته شد" \
      || c_die "نه کد محلی پیدا شد نه دسترسی به GitHub بود"
fi

mkdir -p "$APP_DIR"/storage/{logs,sessions,cache} \
         "$APP_DIR"/public/uploads/{media,vod,subtitles,thumbs}
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod 775 "$APP_DIR"/storage "$APP_DIR"/storage/* "$APP_DIR"/public/uploads
[ -f "$APP_DIR/artisan" ] && chmod 755 "$APP_DIR/artisan"
[ -f "$APP_DIR/scripts/cron-run.sh" ] && chmod 755 "$APP_DIR/scripts/cron-run.sh"
c_ok "دسترسی فایل‌ها تنظیم شد"

# ── ۳) دیتابیس ───────────────────────────────────────────────────
c_info "آماده‌سازی دیتابیس"
systemctl enable --now mariadb >/dev/null 2>&1

# رمز تازه فقط اگر .env از قبل نباشد؛ وگرنه رمز موجود نگه داشته
# می‌شود، وگرنه اجرای دوباره دسترسی برنامه به دادهٔ خودش را می‌شکند.
if [ -f "$APP_DIR/.env" ] && grep -q '^DB_PASSWORD=' "$APP_DIR/.env"; then
    DB_PASS=$(grep '^DB_PASSWORD=' "$APP_DIR/.env" | cut -d= -f2-)
    c_ok "رمز دیتابیس از .env موجود خوانده شد"
else
    DB_PASS=$(rand 24)
fi

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
c_ok "دیتابیس $DB_NAME آماده است"

# ── ۴) فایل تنظیمات ─────────────────────────────────────────────
if [ ! -f "$APP_DIR/.env" ]; then
    c_info "ساخت .env"
    IP=$(hostname -I | awk '{print $1}')
    ADMIN_PASS=$(rand 14)

    cat > "$APP_DIR/.env" <<ENV
APP_NAME="Hotel Media"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://$IP
APP_KEY=$(rand 48)
APP_TIMEZONE=Asia/Tehran

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASS
DB_CHARSET=utf8mb4

JWT_SECRET=$(rand 64)
JWT_EXPIRY=86400
JWT_REFRESH_EXPIRY=2592000

WS_HOST=127.0.0.1
WS_PORT=8080

STORAGE_DRIVER=local
MAX_UPLOAD_SIZE=5368709120

MULTI_TENANT=false
DEFAULT_TENANT=1
ADMIN_EMAIL=admin@hotelmedia.local
ADMIN_PASSWORD=$ADMIN_PASS
PLAYER_SYNC_INTERVAL=30
BCRYPT_ROUNDS=12
SESSION_LIFETIME=28800
ENV
    chown www-data:www-data "$APP_DIR/.env"
    chmod 640 "$APP_DIR/.env"
    c_ok ".env ساخته شد"
else
    c_ok ".env موجود دست‌نخورده ماند"
fi

# ── ۵) PHP-FPM ───────────────────────────────────────────────────
c_info "تنظیم PHP-FPM"
mkdir -p /var/log/php-fpm
chown www-data:www-data /var/log/php-fpm

# این مقادیر از pool کارکردهٔ سرور تولید گرفته شده‌اند.
# نکتهٔ مهم: open_basedir تابع is_executable را برای /usr/bin/ffmpeg
# دروغ می‌کند، پس کد از `command -v` استفاده می‌کند — /usr/bin در
# فهرست نیست و لازم هم نیست.
cat > /etc/php/${PHP_V}/fpm/pool.d/hotel-media.conf <<POOL
[hotel-media]
user  = www-data
group = www-data
listen       = /run/php/hotel-media.sock
listen.owner = www-data
listen.group = www-data
listen.mode  = 0660
listen.backlog = 1024

pm                   = dynamic
pm.max_children      = $(( MEM_MB / 48 < 16 ? 16 : (MEM_MB / 48 > 96 ? 96 : MEM_MB / 48) ))
pm.start_servers     = 8
pm.min_spare_servers = 6
pm.max_spare_servers = 16
pm.max_requests      = 500
pm.status_path = /fpm-status
ping.path      = /fpm-ping

access.log = /var/log/php-fpm/hotel-media.access.log
slowlog    = /var/log/php-fpm/hotel-media.slow.log
request_slowlog_timeout   = 5s
request_terminate_timeout = 300s
catch_workers_output = yes

php_admin_value[error_log] = /var/log/php-fpm/hotel-media.error.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit]        = 256M
php_admin_value[upload_max_filesize] = 5G
php_admin_value[post_max_size]       = 5G
php_admin_value[max_execution_time]  = 300
php_admin_value[max_input_time]      = 300
php_admin_flag[display_errors] = off
php_admin_flag[expose_php]     = off

php_admin_value[session.save_path]       = $APP_DIR/storage/sessions
php_admin_value[session.gc_maxlifetime]  = 28800
php_admin_value[session.cookie_httponly] = 1
php_admin_value[session.use_strict_mode] = 1

php_admin_value[open_basedir] = $APP_DIR:/tmp:/usr/share/php

php_admin_value[opcache.enable]                  = 1
php_admin_value[opcache.memory_consumption]      = 192
php_admin_value[opcache.interned_strings_buffer] = 16
php_admin_value[opcache.max_accelerated_files]   = 20000
; بازبینی هر ۲ ثانیه، نه صفر: با صفر، به‌روزرسانی از پنل فایل را عوض می‌کند ولی
; PHP کد قدیمی را از حافظه سرو می‌کند تا FPM دستی ریلود شود (INSTALL-ISSUES ردیف ۷)
php_admin_value[opcache.validate_timestamps]     = 1
php_admin_value[opcache.revalidate_freq]         = 2
POOL

# pool پیش‌فرض لازم نیست و فقط حافظه می‌گیرد
[ -f /etc/php/${PHP_V}/fpm/pool.d/www.conf ] && \
  mv /etc/php/${PHP_V}/fpm/pool.d/www.conf /etc/php/${PHP_V}/fpm/pool.d/www.conf.disabled
c_ok "pool تنظیم شد (max_children=$(grep -oP 'pm.max_children *= *\K\d+' /etc/php/${PHP_V}/fpm/pool.d/hotel-media.conf))"

# ── ۶) nginx ─────────────────────────────────────────────────────
c_info "تنظیم nginx"

# ‏text/vtt در نقشهٔ پیش‌فرض اوبونتو نیست و زیرنویس با
# application/octet-stream سرو می‌شود
grep -q "text/vtt" /etc/nginx/mime.types || \
  sed -i '0,/^types {/s//types {\n    text\/vtt                                         vtt;/' /etc/nginx/mime.types

cat > /etc/nginx/sites-available/hotel-media <<'NGINX'
upstream hotel_media_php {
    server unix:/run/php/hotel-media.sock;
    keepalive 32;
}

server {
    listen      80 default_server;
    listen      [::]:80 default_server;
    server_name _;

    root  /var/www/hotel-media/public;
    index index.php;
    charset utf-8;

    # ویدیوی تبلیغاتی هتل به‌راحتی چند گیگابایت می‌شود
    client_max_body_size 5G;
    client_body_timeout  300s;

    access_log /var/log/nginx/hotel-media.access.log;
    error_log  /var/log/nginx/hotel-media.error.log warn;

    gzip            on;
    gzip_vary       on;
    gzip_min_length 1024;
    gzip_proxied    any;
    gzip_types      text/plain text/css text/javascript application/javascript
                    application/json application/xml image/svg+xml font/woff2;

    location ~ /\.(?!well-known) { deny all; }
    location = /install.php      { deny all; }

    location /assets/vendor/ {
        expires 365d;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files $uri =404;
    }
    location /assets/ {
        expires 7d;
        add_header Cache-Control "public";
        access_log off;
        try_files $uri =404;
    }
    location /uploads/ {
        expires 30d;
        add_header Cache-Control "public";
        access_log off;
        sendfile       on;
        tcp_nopush     on;
        output_buffers 2 1m;
        try_files $uri =404;
    }
    # قطعه‌های HLS کوتاه‌عمرند و نباید کش شوند
    location /hls/ {
        add_header Cache-Control "no-cache";
        access_log off;
        try_files $uri $uri/ @php;
    }

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location @php {
        fastcgi_pass hotel_media_php;
        include      fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass  hotel_media_php;
        fastcgi_index index.php;
        include       fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTPS $https if_not_empty;
        fastcgi_read_timeout 300s;
        fastcgi_buffer_size  16k;
        fastcgi_buffers      16 16k;
        fastcgi_keep_conn    on;
    }

    location /ws {
        proxy_pass         http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade    $http_upgrade;
        proxy_set_header   Connection "upgrade";
        proxy_set_header   Host       $host;
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
    }
}
NGINX

ln -sf /etc/nginx/sites-available/hotel-media /etc/nginx/sites-enabled/hotel-media
rm -f /etc/nginx/sites-enabled/default
nginx -t >/dev/null 2>&1 || c_die "پیکربندی nginx معتبر نیست"
c_ok "nginx تنظیم شد"

# ── ۷) راه‌اندازی سرویس‌ها و migration ──────────────────────────
c_info "اجرای migration"
systemctl enable --now php${PHP_V}-fpm >/dev/null 2>&1
systemctl restart php${PHP_V}-fpm
systemctl enable --now nginx >/dev/null 2>&1
systemctl reload nginx

cd "$APP_DIR"
sudo -u www-data php artisan db:migrate 2>&1 | tail -3 || c_warn "migration با خطا تمام شد — خروجی بالا را ببینید"

# دانهٔ اولیه فقط روی نصب تازه: روی نصب موجود، کاربر و تنظیمات هتل
# را بازنویسی می‌کرد.
USERS=$(mysql -N -B -e "SELECT COUNT(*) FROM users;" "$DB_NAME" 2>/dev/null || echo 0)
if [ "${USERS:-0}" = "0" ]; then
    c_info "بارگذاری دادهٔ اولیه"
    sudo -u www-data php artisan db:seed >/dev/null 2>&1 && c_ok "دادهٔ اولیه ثبت شد" \
      || c_warn "دادهٔ اولیه ثبت نشد"
fi

# ── ۸) کارهای زمان‌بندی‌شده ─────────────────────────────────────
# ── پل نصب درایور (پنل ← root) ─────────────────────────────────
# صفحهٔ «درایورها» در پنل با کاربر www-data اجرا می‌شود و نصب بسته root
# می‌خواهد. پل، `/usr/local/sbin/hotel-media-driver` است و sudoers فقط
# همان را مجاز می‌کند؛ همهٔ بررسی‌های امنیتی (فهرست ثابت id، realpath
# مسیر فایل) داخل خود اسکریپت است.
#
# این گام در `deploy/install-production.sh` بود ولی در این نصب‌کننده
# نبود: روی سرور دوم `/usr/local/sbin/hotel-media-driver` و
# `/etc/sudoers.d/` خالی بودند، پس صفحهٔ درایور همیشه می‌گفت «ابزار root
# تنظیم نیست» و هیچ درایوری از پنل نصب نمی‌شد.
if [ -f "$APP_DIR/deploy/hotel-media-driver.sh" ]; then
    install -o root -g root -m 0755 "$APP_DIR/deploy/hotel-media-driver.sh" /usr/local/sbin/hotel-media-driver
    sed -i "s#__APP_DIR__#${APP_DIR}#" /usr/local/sbin/hotel-media-driver
    mkdir -p "$APP_DIR/storage/drivers/incoming"
    chown -R www-data:www-data "$APP_DIR/storage/drivers"

    echo 'www-data ALL=(root) NOPASSWD: /usr/local/sbin/hotel-media-driver' > /etc/sudoers.d/hotel-media-driver
    chmod 0440 /etc/sudoers.d/hotel-media-driver
    # sudoers نامعتبر **کل sudo را می‌خواباند** (هیچ‌کس دیگر root نمی‌شود)،
    # پس پیش از ماندن اعتبارسنجی می‌شود.
    if visudo -cf /etc/sudoers.d/hotel-media-driver >/dev/null 2>&1; then
        c_ok "پل نصب درایور فعال شد"
    else
        rm -f /etc/sudoers.d/hotel-media-driver
        c_warn "sudoers درایور نامعتبر بود و حذف شد — درایورها از پنل نصب نمی‌شوند"
    fi
fi

# ── ثبت TVHeadend در برنامه ─────────────────────────────────────
# `artisan tvheadend:setup` از ابتدا وجود داشت و کامنتش می‌گفت «نصب‌کننده‌ی
# production بعد از نصب TVHeadend این را صدا می‌زند» — ولی نصب‌کننده
# هرگز صدایش نمی‌زد (صفر ارجاع). یعنی TVHeadend نصب می‌شد و برنامه از
# وجودش خبر نداشت: IPTV خالی می‌ماند تا اپراتور دستی وصلش کند.
if [ -n "${TVH_PASS:-}" ]; then
    c_info "ثبت TVHeadend در برنامه"
    # توجه: رمز **آرگومان** است (artisan آن را از argv می‌خواند) و در همین
    # چند ثانیه در `ps` برای هر کاربر محلی دیده می‌شود. این سرور تک‌منظوره
    # است و حساب محلی دیگری ندارد، و TVHeadend فقط روی loopback است — پس
    # خطر عملی کم است، ولی صفر نیست. اصلاح درست این است که artisan رمز را
    # از stdin یا env بخواند؛ در INSTALL-ISSUES.md ثبت شده.
    if (cd "$APP_DIR" && sudo -u www-data php artisan tvheadend:setup \
            http://127.0.0.1:9981 "${TVH_USER:-hotelmedia}" "$TVH_PASS" 1) >>"$LOG" 2>&1; then
        c_ok "TVHeadend در برنامه ثبت شد"
    else
        c_warn "ثبت TVHeadend در برنامه ناموفق بود — دستی: php artisan tvheadend:setup"
    fi
fi

c_info "ثبت کارهای زمان‌بندی‌شده"
cat > /etc/cron.d/hotel-media <<'CRON'
SHELL=/bin/bash
PATH=/usr/local/bin:/usr/bin:/bin

# cron-run.sh فقط شکست‌ها را لاگ می‌کند — نگاه کنید به storage/logs/cron.log
* * * * * www-data /var/www/hotel-media/scripts/cron-run.sh monitor:screens
* * * * * www-data /var/www/hotel-media/scripts/cron-run.sh transcoder:supervise
*/2 * * * * www-data /var/www/hotel-media/scripts/cron-run.sh vod:queue
*/5 * * * * www-data /var/www/hotel-media/scripts/cron-run.sh flights:sync
*/5 * * * * www-data /var/www/hotel-media/scripts/cron-run.sh pms:push
0 * * * * www-data /var/www/hotel-media/scripts/cron-run.sh news:sync
0 3 * * * www-data /var/www/hotel-media/scripts/cron-run.sh epg:sync
0 4 * * 0 www-data /var/www/hotel-media/scripts/cron-run.sh storage:clean
CRON
chmod 644 /etc/cron.d/hotel-media
c_ok "کارهای زمان‌بندی‌شده ثبت شدند"

# ── ۹) کنسول مدیریت ────────────────────────────────────────────
c_info "نصب کنسول مدیریت"
if [ -f "$APP_DIR/scripts/hotel-media-console.sh" ]; then
    install -m755 -o root -g root "$APP_DIR/scripts/hotel-media-console.sh" /usr/local/bin/hotel-media
    ln -sf /usr/local/bin/hotel-media /usr/local/bin/hm

    # ‏whiptail در نصب پایهٔ اوبونتو هست، ولی نصب minimal ممکن است
    # نداشته باشد و منوی گرافیکی بی آن اجرا نمی‌شود (متنی کار می‌کند).
    command -v whiptail >/dev/null 2>&1 || \
      DEBIAN_FRONTEND=noninteractive apt-get install -y -qq whiptail >/dev/null 2>&1 || true

    # روی کنسول فیزیکی (tty1) خودش بالا می‌آید: کسی که جلوی سرور
    # می‌نشیند دنبال نام فرمان نمی‌گردد. روی SSH دست نمی‌زنیم تا
    # نشست‌های خودکار و scp گیر نکنند.
    for home in /home/*; do
        [ -d "$home" ] || continue
        prof="$home/.bash_profile"
        grep -q "hotel-media" "$prof" 2>/dev/null && continue
        cat >> "$prof" <<'PROF'

# کنسول مدیریت Hotel Media روی نمایشگر فیزیکی. برای رد شدن Ctrl+C.
if [ "$(tty)" = "/dev/tty1" ] && command -v hotel-media >/dev/null 2>&1; then
    sudo hotel-media
fi
PROF
        chown "$(basename "$home")":"$(basename "$home")" "$prof" 2>/dev/null || true
    done

    cat > /etc/motd <<MOTD

  Hotel Media — سماع رایانه کیش
  ─────────────────────────────────────────────────────
  کنسول مدیریت:   sudo hotel-media
  وضعیت سریع:     sudo hotel-media status
  بررسی کامل:     sudo hotel-media diag
  نشانی پنل:      http://$(hostname -I | awk '{print $1}')/login
  ─────────────────────────────────────────────────────

MOTD
    c_ok "کنسول نصب شد — با «sudo hotel-media» اجرا می‌شود"
else
    c_warn "فایل کنسول پیدا نشد؛ بی آن ادامه داد"
fi

# ── ۱۰) دیوار آتش ───────────────────────────────────────────────
if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
    ufw allow 80/tcp  >/dev/null 2>&1
    ufw allow 443/tcp >/dev/null 2>&1
    c_ok "پورت ۸۰ و ۴۴۳ در ufw باز شد"
fi

# ── ۱۱) بررسی سلامت ───────────────────────────────────────────────────────────────────────────────────────
c_info "بررسی سلامت"
sleep 2
CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1/login || echo 000)
[ "$CODE" = "200" ] && c_ok "صفحهٔ ورود پاسخ می‌دهد (HTTP $CODE)" \
                    || c_warn "صفحهٔ ورود پاسخ نداد (HTTP $CODE) — /var/log/nginx/hotel-media.error.log را ببینید"

command -v ffmpeg >/dev/null && c_ok "ffmpeg: $(ffmpeg -version 2>/dev/null | head -1 | cut -d' ' -f3)"

IP=$(hostname -I | awk '{print $1}')
ADMIN_PASS=$(grep '^ADMIN_PASSWORD=' "$APP_DIR/.env" | cut -d= -f2-)
ADMIN_MAIL=$(grep '^ADMIN_EMAIL=' "$APP_DIR/.env" | cut -d= -f2-)

echo
echo "═══════════════════════════════════════════════════════════════"
echo "  Hotel Media نصب شد"
echo "═══════════════════════════════════════════════════════════════"
echo
echo "  پنل مدیریت:   http://$IP/login"
echo "  نام کاربری:   $ADMIN_MAIL"
echo "  رمز عبور:     $ADMIN_PASS"
echo
echo "  تلویزیون‌ها را به این نشانی ببرید:  http://$IP/tv"
echo
echo "  رمز بالا در $APP_DIR/.env هم هست."
echo "  اگر دادهٔ اولیه بارگذاری شده، رمز پیش‌فرض دانه Admin@123456 است"
echo "  و باید همان اول عوضش کنید."
echo "═══════════════════════════════════════════════════════════════"
