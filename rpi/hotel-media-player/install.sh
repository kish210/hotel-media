#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────
# نصب پلیر Hotel Media روی Raspberry Pi
#
#   sudo bash install.sh                     # نصب + فعال‌سازی سرویس
#   sudo bash install.sh --user pi           # کاربری که X را اجرا می‌کند
#   sudo bash install.sh --no-apt            # بدون نصب بسته‌ها (آفلاین)
#   sudo bash install.sh --server http://192.168.1.100 --code SCR001
# ─────────────────────────────────────────────────────────────────────
set -euo pipefail

APP_DIR=/opt/hotel-media-player
CONF_DIR=/etc/hotel-media-player
SERVICE=/etc/systemd/system/hotel-media-player.service
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

TARGET_USER=""
SERVER_URL=""
SCREEN_CODE=""
DO_APT=1

while [ $# -gt 0 ]; do
  case "$1" in
    --user)   TARGET_USER="$2"; shift 2 ;;
    --server) SERVER_URL="$2"; shift 2 ;;
    --code)   SCREEN_CODE="$2"; shift 2 ;;
    --no-apt) DO_APT=0; shift ;;
    -h|--help) sed -n '2,10p' "$0"; exit 0 ;;
    *) echo "گزینه‌ی ناشناس: $1" >&2; exit 1 ;;
  esac
done

if [ "$(id -u)" -ne 0 ]; then
  echo "این اسکریپت باید با sudo اجرا شود" >&2
  exit 1
fi

# کاربری که نشست گرافیکی را دارد (پیش‌فرض: اولین کاربر عادی)
if [ -z "$TARGET_USER" ]; then
  TARGET_USER="$(awk -F: '$3>=1000 && $3<65534 {print $1; exit}' /etc/passwd || true)"
fi
if [ -z "$TARGET_USER" ] || ! id "$TARGET_USER" >/dev/null 2>&1; then
  echo "کاربر هدف پیدا نشد — با --user مشخص کنید" >&2
  exit 1
fi
TARGET_HOME="$(getent passwd "$TARGET_USER" | cut -d: -f6)"
echo "کاربر پلیر: $TARGET_USER ($TARGET_HOME)"

if [ "$DO_APT" -eq 1 ] && command -v apt-get >/dev/null 2>&1; then
  echo "نصب پیش‌نیازها…"
  apt-get update -y
  # روی Raspberry Pi OS Bookworm بسته‌ی chromium است، در نسخه‌های قدیمی‌تر
  # chromium-browser. هر کدام بود همان نصب می‌شود.
  apt-get install -y --no-install-recommends python3 x11-xserver-utils unclutter || true
  if ! apt-get install -y --no-install-recommends chromium; then
    apt-get install -y --no-install-recommends chromium-browser || \
      echo "هشدار: مرورگر نصب نشد؛ دستی نصب کنید (chromium یا cog)" >&2
  fi
fi

echo "کپی فایل‌ها…"
install -d -m 755 "$APP_DIR" "$CONF_DIR"
install -m 755 "$SRC_DIR/player.py" "$APP_DIR/player.py"
install -d -m 755 /var/lib/hotel-media-player
chown -R "$TARGET_USER":"$TARGET_USER" /var/lib/hotel-media-player

if [ ! -f "$CONF_DIR/config.json" ]; then
  install -m 644 "$SRC_DIR/config.example.json" "$CONF_DIR/config.json"
fi

# مقدارهای داده‌شده روی خط فرمان در پیکربندی نوشته شوند
if [ -n "$SERVER_URL" ] || [ -n "$SCREEN_CODE" ]; then
  # root اجرا می‌شود چون فایل در /etc نوشته می‌شود
  python3 "$APP_DIR/player.py" \
    ${SERVER_URL:+--server "$SERVER_URL"} ${SCREEN_CODE:+--code "$SCREEN_CODE"}
fi

echo "نصب سرویس systemd…"
sed -e "s|^User=.*|User=$TARGET_USER|" \
    -e "s|^Environment=XAUTHORITY=.*|Environment=XAUTHORITY=$TARGET_HOME/.Xauthority|" \
    "$SRC_DIR/hotel-media-player.service" > "$SERVICE"

systemctl daemon-reload
systemctl enable hotel-media-player.service
systemctl restart hotel-media-player.service

echo
echo "✓ نصب شد."
echo "  وضعیت:      systemctl status hotel-media-player"
echo "  لاگ:        journalctl -u hotel-media-player -f"
echo "  تنظیمات:    sudo nano $CONF_DIR/config.json"
echo "  تست سرور:   python3 $APP_DIR/player.py --check"
echo
echo "یادآوری: برای بالا آمدن خودکار پلیر، Raspberry Pi OS باید"
echo "با ورود خودکار به محیط گرافیکی بالا بیاید (raspi-config →"
echo "System Options → Boot / Auto Login → Desktop Autologin)."
