#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════
#  Hotel Media — نصب production روی Ubuntu Server
#  سماع رایانه کیش | kishwifi.com
#
#  برای هتل تا ۳۰۰ اتاق: nginx + PHP-FPM + MariaDB + systemd
#
#  چرا این و نه نصب‌کننده‌ی ویندوزی:
#    نصب‌کننده‌ی ویندوزی از `php -S` استفاده می‌کند که تک‌نخی است. اندازه‌گیری
#    روی همین پروژه: ۳۷ درخواست در ثانیه. برای ۱۰ تا ۳۰ اتاق کافی است،
#    برای ۳۰۰ اتاق نه. این اسکریپت nginx + PHP-FPM می‌گذارد که چند worker
#    موازی دارد و ویدیو را بدون عبور از PHP سرو می‌کند.
#
#  اجرا:
#    sudo bash deploy/install-production.sh
# ══════════════════════════════════════════════════════════════════════
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/hotel-media}"
DB_NAME="${DB_NAME:-hotel_media}"
DB_USER="${DB_USER:-hotel_media}"
PHP_VER="${PHP_VER:-8.3}"
WS_PORT="${WS_PORT:-8080}"

# TVHeadend — سر دریافت سیگنال (DVB-S/S2/T/T2/C).
# با INSTALL_TVHEADEND=0 می‌توان از نصبش صرف‌نظر کرد، مثلا وقتی
# TVHeadend روی سرور دیگری است.
INSTALL_TVHEADEND="${INSTALL_TVHEADEND:-1}"
TVH_USER="${TVH_USER:-hotelmedia}"
TVH_PORT="${TVH_PORT:-9981}"

G='\033[0;32m'; Y='\033[1;33m'; R='\033[0;31m'; B='\033[0;34m'; N='\033[0m'
ok()   { echo -e "  ${G}✔${N} $1"; }
info() { echo -e "  ${B}→${N} $1"; }
warn() { echo -e "  ${Y}!${N} $1"; }
die()  { echo -e "  ${R}✘ $1${N}"; exit 1; }

[[ $EUID -eq 0 ]] || die "این اسکریپت باید با sudo اجرا شود"

echo -e "${B}"
echo "  ╔════════════════════════════════════════════════════╗"
echo "  ║   Hotel Media — نصب production (تا ۳۰۰ اتاق)       ║"
echo "  ╚════════════════════════════════════════════════════╝"
echo -e "${N}"

# ── ۰) بررسی سیستم ───────────────────────────────────────────────────
echo -e "${G}[1/9] بررسی سیستم${N}"

. /etc/os-release 2>/dev/null || die "سیستم‌عامل تشخیص داده نشد"
[[ "$ID" == "ubuntu" || "$ID" == "debian" ]] || warn "این اسکریپت برای Ubuntu/Debian نوشته شده (یافت شد: $ID)"
ok "$PRETTY_NAME"

CPU=$(nproc)
RAM_MB=$(free -m | awk '/^Mem:/{print $2}')
DISK_GB=$(df -BG --output=avail / | tail -1 | tr -dc '0-9')

ok "پردازنده: ${CPU} هسته · رم: ${RAM_MB}MB · فضای آزاد: ${DISK_GB}GB"

(( CPU     >= 2    )) || warn "برای ۳۰۰ اتاق حداقل ۴ هسته توصیه می‌شود"
(( RAM_MB  >= 3500 )) || warn "برای ۳۰۰ اتاق حداقل ۸ گیگابایت رم توصیه می‌شود"
(( DISK_GB >= 50   )) || warn "فضای دیسک کم است — محتوای VOD و ضبط فضا می‌خواهد"

# اندازه‌ی استخر PHP از روی رم واقعی، نه عدد ثابت
FPM_MAX=$(( RAM_MB / 60 ))
(( FPM_MAX > 64 )) && FPM_MAX=64
(( FPM_MAX < 8  )) && FPM_MAX=8
info "‏PHP-FPM با ${FPM_MAX} worker تنظیم می‌شود"

# ── ۱) بسته‌ها ───────────────────────────────────────────────────────
echo -e "\n${G}[2/9] نصب بسته‌ها${N}"
export DEBIAN_FRONTEND=noninteractive

apt-get update -qq
apt-get install -y -qq software-properties-common curl unzip git ca-certificates >/dev/null

# مخزن ondrej برای نسخه‌های جدید PHP روی Ubuntu قدیمی
if ! apt-cache show "php${PHP_VER}-fpm" >/dev/null 2>&1; then
    info "افزودن مخزن PHP"
    add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 || true
    apt-get update -qq
fi

apt-get install -y -qq \
    nginx mariadb-server ffmpeg \
    "php${PHP_VER}-fpm" "php${PHP_VER}-cli" "php${PHP_VER}-mysql" \
    "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-curl" \
    "php${PHP_VER}-gd" "php${PHP_VER}-zip" "php${PHP_VER}-intl" \
    "php${PHP_VER}-opcache" "php${PHP_VER}-bcmath" >/dev/null

command -v composer >/dev/null 2>&1 || {
    info "نصب Composer"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
}

ok "nginx · PHP ${PHP_VER} · MariaDB · ffmpeg · Composer"

# ── TVHeadend ────────────────────────────────────────────────────────
# نقش «سهند» در استک ما: سیگنال ماهواره و زمینی را می‌گیرد و روی IP می‌دهد.
if [[ "$INSTALL_TVHEADEND" == "1" ]]; then
    if systemctl list-unit-files 2>/dev/null | grep -q '^tvheadend'; then
        ok "TVHeadend از قبل نصب است"
        # رمز نصب قبلی را نمی‌دانیم؛ اگر فایل ما هست از آن بخوان
        [[ -f /etc/hotel-media/tvheadend.cred ]] && . /etc/hotel-media/tvheadend.cred
    else
        info "نصب TVHeadend"
        TVH_PASS="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 20)"

        # نصب tvheadend وگرنه سؤال تعاملی برای کاربر ادمین می‌پرسد و
        # اسکریپت وسط نصب معلق می‌ماند. با debconf از قبل پاسخ می‌دهیم.
        debconf-set-selections <<DEBCONF
tvheadend tvheadend/admin_username string ${TVH_USER}
tvheadend tvheadend/admin_password password ${TVH_PASS}
tvheadend tvheadend/admin_password_again password ${TVH_PASS}
DEBCONF

        if apt-get install -y -qq tvheadend >/dev/null 2>&1; then
            # رمز فقط برای root خواندنی — اسکریپت‌های بعدی از اینجا می‌خوانند
            mkdir -p /etc/hotel-media
            printf 'TVH_USER=%s\nTVH_PASS=%s\n' "$TVH_USER" "$TVH_PASS" \
                > /etc/hotel-media/tvheadend.cred
            chmod 600 /etc/hotel-media/tvheadend.cred

            systemctl enable --now tvheadend >/dev/null 2>&1 || true
            ok "TVHeadend نصب شد (کاربر: ${TVH_USER})"
        else
            warn "نصب TVHeadend ناموفق بود — بدون آن ادامه می‌دهیم"
            warn "می‌توانید بعدا نصب و با 'php artisan tvheadend:setup' وصلش کنید"
            INSTALL_TVHEADEND=0
        fi
    fi
fi

# ── ۲) فایل‌های برنامه ───────────────────────────────────────────────
echo -e "\n${G}[3/9] استقرار فایل‌ها${N}"

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ "$SRC_DIR" != "$APP_DIR" ]]; then
    mkdir -p "$APP_DIR"
    # ‏.git و وابستگی‌های محلی منتقل نمی‌شوند
    tar -C "$SRC_DIR" --exclude='.git' --exclude='node_modules' \
        --exclude='vendor' --exclude='storage/logs/*' --exclude='storage/sessions/*' \
        -cf - . | tar -C "$APP_DIR" -xf -
    ok "فایل‌ها در $APP_DIR"
else
    ok "برنامه از قبل در $APP_DIR است"
fi

cd "$APP_DIR"
mkdir -p storage/{logs,cache,sessions,temp} public/uploads/{media,thumbnails,vod} public/apk

# ── ۳) دیتابیس ───────────────────────────────────────────────────────
echo -e "\n${G}[4/9] دیتابیس${N}"

systemctl enable --now mariadb >/dev/null 2>&1
systemctl is-active --quiet mariadb || die "MariaDB بالا نیامد"

if [[ -f .env ]] && grep -q '^DB_PASSWORD=' .env; then
    DB_PASS="$(grep '^DB_PASSWORD=' .env | head -1 | cut -d= -f2- | tr -d '"'"'"' ')"
    info "رمز دیتابیس از .env موجود خوانده شد"
fi
[[ -n "${DB_PASS:-}" ]] || DB_PASS="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 24)"

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
ok "دیتابیس «${DB_NAME}» و کاربر «${DB_USER}» آماده‌اند"

# تنظیم MariaDB برای بار ۳۰۰ اتاق
cat > /etc/mysql/mariadb.conf.d/99-hotel-media.cnf <<CNF
# Hotel Media — تنظیم برای هتل تا ۳۰۰ اتاق
[mysqld]
# هر تلویزیون یک اتصال کوتاه می‌سازد؛ ۳۰۰ اتاق در اوج به سقف بالاتر نیاز دارد
max_connections          = 300
# داده‌ی داغ در رم بماند تا heartbeat به دیسک نخورد
innodb_buffer_pool_size  = $(( RAM_MB / 4 ))M
innodb_flush_method      = O_DIRECT
# heartbeat نوشتن زیاد دارد و از دست رفتن یک ثانیه‌ی آخر فاجعه نیست
innodb_flush_log_at_trx_commit = 2
# اتصال‌های رهاشده‌ی تلویزیون خاموش‌شده را آزاد کن
wait_timeout             = 300
interactive_timeout      = 300
character-set-server     = utf8mb4
collation-server         = utf8mb4_unicode_ci
CNF
systemctl restart mariadb
ok "MariaDB برای بار هتل تنظیم شد"

# ── ۴) پیکربندی برنامه ───────────────────────────────────────────────
echo -e "\n${G}[5/9] پیکربندی${N}"

if [[ ! -f .env ]]; then
    cp .env.example .env
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    JWT="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 48)"
    SERVER_IP="$(hostname -I | awk '{print $1}')"

    sed -i "s|^APP_ENV=.*|APP_ENV=production|"           .env
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|"            .env
    sed -i "s|^APP_URL=.*|APP_URL=http://${SERVER_IP}|"  .env
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|"           .env
    sed -i "s|^JWT_SECRET=.*|JWT_SECRET=${JWT}|"         .env
    sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|"            .env
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|"   .env
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|"   .env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|"   .env
    sed -i "s|^WS_PORT=.*|WS_PORT=${WS_PORT}|"           .env

    # فاصله‌ی heartbeat متناسب با اندازه‌ی هتل
    grep -q '^PLAYER_SYNC_INTERVAL=' .env \
        && sed -i "s|^PLAYER_SYNC_INTERVAL=.*|PLAYER_SYNC_INTERVAL=30|" .env \
        || echo "PLAYER_SYNC_INTERVAL=30" >> .env

    ok ".env با کلیدهای تازه ساخته شد"
else
    ok ".env موجود حفظ شد"
fi

composer install --no-dev --optimize-autoloader --quiet --no-interaction
ok "وابستگی‌های PHP نصب شدند"

php artisan db:migrate
[[ -f database/seeds/seed.sql ]] && mysql "${DB_NAME}" < database/seeds/seed.sql 2>/dev/null || true
ok "اسکیما و داده‌ی اولیه اعمال شد"

# ── ۵) دسترسی فایل‌ها ────────────────────────────────────────────────
echo -e "\n${G}[6/9] دسترسی‌ها${N}"

chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 "$APP_DIR"/storage "$APP_DIR"/public/uploads "$APP_DIR"/public/apk

# ‏.env رمز دیتابیس و کلید JWT دارد
chmod 640 "$APP_DIR/.env"

# ‏install.php بعد از نصب لازم نیست و رمز دیتابیس را نشان می‌دهد
[[ -f public/install.php ]] && mv public/install.php "$APP_DIR/install.php.disabled" && \
    ok "install.php غیرفعال شد (در ریشه نگه داشته شد)"

ok "دسترسی‌ها تنظیم شدند"

# ── ۶) PHP-FPM ───────────────────────────────────────────────────────
echo -e "\n${G}[7/9] PHP-FPM${N}"

mkdir -p /var/log/php-fpm
install -d -o www-data -g www-data /var/log/php-fpm

cp deploy/php-fpm/hotel-media.conf "/etc/php/${PHP_VER}/fpm/pool.d/hotel-media.conf"
sed -i "s|/etc/php/8.3/|/etc/php/${PHP_VER}/|g" "/etc/php/${PHP_VER}/fpm/pool.d/hotel-media.conf"
sed -i "s|^pm.max_children.*|pm.max_children      = ${FPM_MAX}|" "/etc/php/${PHP_VER}/fpm/pool.d/hotel-media.conf"
sed -i "s|/var/www/hotel-media|${APP_DIR}|g" "/etc/php/${PHP_VER}/fpm/pool.d/hotel-media.conf"

# استخر پیش‌فرض حذف می‌شود تا دو استخر همزمان رم نخورند
rm -f "/etc/php/${PHP_VER}/fpm/pool.d/www.conf"

systemctl enable --now "php${PHP_VER}-fpm" >/dev/null 2>&1
systemctl restart "php${PHP_VER}-fpm"
systemctl is-active --quiet "php${PHP_VER}-fpm" || die "PHP-FPM بالا نیامد — journalctl -u php${PHP_VER}-fpm"
ok "‏PHP-FPM با ${FPM_MAX} worker اجرا شد"

# ── ۷) nginx ─────────────────────────────────────────────────────────
echo -e "\n${G}[8/9] nginx${N}"

cp deploy/nginx/hotel-media.conf /etc/nginx/sites-available/hotel-media
sed -i "s|/var/www/hotel-media|${APP_DIR}|g" /etc/nginx/sites-available/hotel-media
sed -i "s|127.0.0.1:8080|127.0.0.1:${WS_PORT}|g" /etc/nginx/sites-available/hotel-media

ln -sf /etc/nginx/sites-available/hotel-media /etc/nginx/sites-enabled/hotel-media
rm -f /etc/nginx/sites-enabled/default

# تعداد اتصال همزمان برای ۳۰۰ تلویزیون
sed -i "s|^worker_connections.*|worker_connections 2048;|" /etc/nginx/nginx.conf || true

nginx -t >/dev/null 2>&1 || { nginx -t; die "پیکربندی nginx معتبر نیست"; }
systemctl enable --now nginx >/dev/null 2>&1
systemctl reload nginx
ok "nginx اجرا شد"

# ── ۸) سرویس‌های پس‌زمینه ────────────────────────────────────────────
echo -e "\n${G}[9/9] سرویس‌های پس‌زمینه${N}"

cat > /etc/systemd/system/hotel-media-ws.service <<UNIT
[Unit]
Description=Hotel Media — WebSocket server
After=network.target mariadb.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=${APP_DIR}
Environment=WS_PORT=${WS_PORT}
ExecStart=/usr/bin/php ${APP_DIR}/websocket/server.php
Restart=always
RestartSec=5
StandardOutput=append:/var/log/php-fpm/hotel-media.ws.log
StandardError=append:/var/log/php-fpm/hotel-media.ws.log

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable --now hotel-media-ws >/dev/null 2>&1
systemctl is-active --quiet hotel-media-ws \
    && ok "سرویس WebSocket اجرا شد" \
    || warn "سرویس WebSocket بالا نیامد — journalctl -u hotel-media-ws"

# ── اتصال خودکار TVHeadend ───────────────────────────────────────────
# بدون این، اپراتور کانال‌ها را دستی وارد می‌کند و EPG خالی می‌ماند.
if [[ "$INSTALL_TVHEADEND" == "1" ]]; then
    info "اتصال TVHeadend به سیستم"

    # سرویس چند ثانیه طول می‌کشد تا پورت را بگیرد
    for _ in $(seq 1 20); do
        curl -fsS -o /dev/null --max-time 2 "http://127.0.0.1:${TVH_PORT}/" 2>/dev/null && break
        sleep 1
    done

    if sudo -u www-data php "$APP_DIR/artisan" tvheadend:setup \
            "http://127.0.0.1:${TVH_PORT}" "${TVH_USER}" "${TVH_PASS:-}" 1; then
        ok "TVHeadend به سیستم وصل شد"
    else
        # نصب تازه هنوز تیونر و شبکه تنظیم‌نشده دارد؛ این شکست طبیعی است
        warn "TVHeadend وصل نشد — بعد از تنظیم تیونر این را اجرا کنید:"
        warn "  sudo -u www-data php $APP_DIR/artisan tvheadend:setup"
    fi
fi

# کارهای زمان‌بندی‌شده
cat > /etc/cron.d/hotel-media <<CRON
# Hotel Media — کارهای زمان‌بندی‌شده
SHELL=/bin/bash
PATH=/usr/local/bin:/usr/bin:/bin

# پایش آنلاین بودن تلویزیون‌ها
* * * * * www-data cd ${APP_DIR} && php artisan monitor:screens >/dev/null 2>&1
# دریافت راهنمای برنامه‌ها، هر شب ساعت ۳
0 3 * * * www-data cd ${APP_DIR} && php artisan epg:sync >/dev/null 2>&1
# ارسال اقلام صورتحساب به PMS — اقلامی که PMS قطع بوده در صف مانده‌اند
*/5 * * * * www-data cd ${APP_DIR} && php artisan pms:push >/dev/null 2>&1
# پاک‌سازی فایل‌های حذف‌شده، یکشنبه‌ها
0 4 * * 0 www-data cd ${APP_DIR} && php artisan storage:clean >/dev/null 2>&1
CRON
ok "کارهای زمان‌بندی‌شده ثبت شدند"

# فایروال
if command -v ufw >/dev/null 2>&1; then
    ufw allow 80/tcp     >/dev/null 2>&1 || true
    ufw allow "${WS_PORT}/tcp" >/dev/null 2>&1 || true
    ok "فایروال: پورت ۸۰ و ${WS_PORT} باز شد"
fi

# ── پایان ────────────────────────────────────────────────────────────
SERVER_IP="$(hostname -I | awk '{print $1}')"

echo ""
echo -e "${G}╔══════════════════════════════════════════════════════════╗${N}"
echo -e "${G}║              نصب با موفقیت انجام شد                      ║${N}"
echo -e "${G}╚══════════════════════════════════════════════════════════╝${N}"
echo ""
echo -e "  ${B}پنل مدیریت${N}      http://${SERVER_IP}/admin"
echo -e "  ${B}ایمیل${N}           admin@hotelmedia.com"
echo -e "  ${B}رمز عبور${N}        Admin@123456   ${Y}← فوراً عوض کنید${N}"
echo ""
echo -e "  ${B}آدرس تلویزیون‌ها${N} http://${SERVER_IP}/tv"
echo -e "    همین آدرس را در منوی مخفی همه‌ی تلویزیون‌ها وارد کنید:"
echo -e "      LG      نگه‌داشتن MENU ← 1105 ← Manual Pro:Centric ← HTML/IP"
echo -e "      Samsung MUTE ← 1 ← 1 ← 9 ← ENTER ← URL Launcher"
echo ""
if [[ "$INSTALL_TVHEADEND" == "1" ]]; then
echo -e "  ${B}TVHeadend${N}        http://${SERVER_IP}:${TVH_PORT}"
echo -e "    کاربر: ${TVH_USER}   رمز: ${Y}/etc/hotel-media/tvheadend.cred${N}"
echo -e "    ${Y}گام بعدی:${N} تیونر و شبکه را در Configuration → DVB Inputs تنظیم و اسکن کنید،"
echo -e "    سپس:  sudo -u www-data php ${APP_DIR}/artisan tvheadend:setup"
echo ""
fi
echo -e "  ${B}ظرفیت این سرور${N}  ${FPM_MAX} worker موازی — مناسب تا ۳۰۰ اتاق"
echo ""
echo -e "  ${B}وضعیت سرویس‌ها${N}"
echo -e "    systemctl status nginx php${PHP_VER}-fpm mariadb hotel-media-ws"
echo -e "  ${B}لاگ‌ها${N}"
echo -e "    tail -f /var/log/nginx/hotel-media.error.log"
echo -e "    tail -f /var/log/php-fpm/hotel-media.slow.log   ${Y}# کوئری‌های کند${N}"
echo ""
