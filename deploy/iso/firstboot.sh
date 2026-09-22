#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════
#  Hotel Media — نصب در اولین بوت
#
#  چرا اینجا و نه در late-commands نصب‌کننده:
#    در محیط نصب‌کننده، MariaDB و nginx سرویس ندارند و نمی‌شود بالایشان
#    آورد. نصبی که به دیتابیس زنده نیاز دارد باید بعد از اولین بوت واقعی
#    انجام شود.
#
#  این اسکریپت یک‌بار اجرا می‌شود و بعد خودش را غیرفعال می‌کند.
# ══════════════════════════════════════════════════════════════════════
set -uo pipefail

SRC="/opt/hotel-media-src"
APP="/var/www/hotel-media"
LOG="/var/log/hotel-media-firstboot.log"
FLAG="/var/lib/hotel-media-installed"

exec > >(tee -a "$LOG") 2>&1

echo "══════════════════════════════════════════════════"
echo "  Hotel Media — نصب اولیه  $(date '+%F %T')"
echo "══════════════════════════════════════════════════"

if [[ -f "$FLAG" ]]; then
    echo "  از قبل نصب شده — کاری لازم نیست"
    systemctl disable hotel-media-firstboot.service >/dev/null 2>&1 || true
    exit 0
fi

if [[ ! -d "$SRC" ]]; then
    echo "  ✘ فایل‌های برنامه در $SRC پیدا نشد"
    echo "    ISO ناقص ساخته شده. نصب دستی:"
    echo "      git clone https://github.com/kish210/hotel-media.git"
    echo "      sudo bash hotel-media/deploy/install-production.sh"
    exit 1
fi

# ── انتظار برای شبکه ─────────────────────────────────────────────────
# بدون این، composer و apt روی سروری که هنوز DHCP نگرفته شکست می‌خورند.
echo "  در انتظار شبکه…"
for _ in $(seq 1 30); do
    ip route get 1.1.1.1 >/dev/null 2>&1 && break
    sleep 2
done

# ── نصب ──────────────────────────────────────────────────────────────
mkdir -p "$APP"
rsync -a --exclude='.git' "$SRC/" "$APP/"

cd "$APP" || exit 1

# نصب‌کننده‌ی production همه‌ی کار را می‌کند. بسته‌ها از قبل روی ISO
# نصب شده‌اند، پس این مرحله بدون اینترنت هم پیش می‌رود.
if bash deploy/install-production.sh; then
    touch "$FLAG"
    echo ""
    echo "  ✔ نصب کامل شد"
else
    echo ""
    echo "  ✘ نصب ناموفق بود — جزئیات در $LOG"
    echo "    بعد از رفع مشکل:"
    echo "      sudo bash $APP/deploy/install-production.sh"
    # سرویس غیرفعال نمی‌شود تا بوت بعدی دوباره تلاش کند
    exit 1
fi

# ── خلاصه روی کنسول ──────────────────────────────────────────────────
IP="$(hostname -I | awk '{print $1}')"

cat > /etc/motd <<MOTD

  ╔════════════════════════════════════════════════════════╗
  ║                    Hotel Media                         ║
  ╚════════════════════════════════════════════════════════╝

   پنل مدیریت      http://${IP}/admin
   آدرس تلویزیون‌ها  http://${IP}/tv
   TVHeadend       http://${IP}:9981

   ورود پیش‌فرض     admin@hotelmedia.com / Admin@123456
                   ← فوراً عوض کنید

   وضعیت سرویس‌ها   systemctl status nginx php8.3-fpm mariadb hotel-media-ws
   لاگ نصب          ${LOG}

MOTD

systemctl disable hotel-media-firstboot.service >/dev/null 2>&1 || true
echo "  سرویس نصب اولیه غیرفعال شد"
