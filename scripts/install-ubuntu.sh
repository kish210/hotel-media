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
PHP_V=8.3
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

c_ok()   { printf "\033[32m  ✓\033[0m %s\n" "$1"; }
c_info() { printf "\033[36m→\033[0m %s\n" "$1"; }
c_warn() { printf "\033[33m  !\033[0m %s\n" "$1"; }
c_die()  { printf "\033[31m  ✗ %s\033[0m\n" "$1" >&2; exit 1; }

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
apt-get install -y -qq --no-install-recommends \
  nginx mariadb-server ffmpeg poppler-utils curl unzip git ca-certificates \
  php${PHP_V}-fpm php${PHP_V}-cli php${PHP_V}-mysql php${PHP_V}-mbstring \
  php${PHP_V}-xml php${PHP_V}-curl php${PHP_V}-gd php${PHP_V}-intl \
  php${PHP_V}-zip php${PHP_V}-bcmath php${PHP_V}-opcache \
  >/dev/null || c_die "نصب بسته‌ها ناموفق بود"
c_ok "بسته‌ها نصب شدند"

# ── ۲) کد برنامه ──────────────────────────────────────────────────
c_info "استقرار کد در $APP_DIR"
mkdir -p "$APP_DIR"

if [ -f "$SRC_DIR/artisan" ] && [ -d "$SRC_DIR/app" ]; then
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
    DB_PASS=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 24)
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
    ADMIN_PASS=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 14)

    cat > "$APP_DIR/.env" <<ENV
APP_NAME="Hotel Media"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://$IP
APP_KEY=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 48)
APP_TIMEZONE=Asia/Tehran

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASS
DB_CHARSET=utf8mb4

JWT_SECRET=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 64)
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
php_admin_value[opcache.validate_timestamps]     = 0
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

# ── ۹) دیوار آتش ────────────────────────────────────────────────
if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
    ufw allow 80/tcp  >/dev/null 2>&1
    ufw allow 443/tcp >/dev/null 2>&1
    c_ok "پورت ۸۰ و ۴۴۳ در ufw باز شد"
fi

# ── ۱۰) بررسی سلامت ────────────────────────────────────────────
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
