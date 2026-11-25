#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════
#  Hotel Media — به‌روزرسانی از مخزن گیت
#
#  برای نصبی که با git clone انجام شده. نصب ISO از بستهٔ انتشار
#  استفاده می‌کند (پنل → به‌روزرسانی سیستم).
#
#  ترتیب عمدی است: پشتیبان → دریافت → مهاجرت → ریلود.
#  اگر هر مرحله شکست بخورد، همان‌جا می‌ایستد و فایل‌های قبلی دست‌نخورده
#  می‌مانند (به‌جز مرحلهٔ pull که فقط فایل‌های کد را عوض می‌کند).
# ══════════════════════════════════════════════════════════════════════
set -euo pipefail

APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRANCH="${BRANCH:-main}"
LOG="/var/log/hotel-media-update.log"

[[ $EUID -eq 0 ]] || { echo "با sudo اجرا کنید"; exit 1; }

say() { echo -e "\033[36m→\033[0m $1"; }
ok()  { echo -e "\033[32m✔\033[0m $1"; }

cd "$APP"
exec > >(tee -a "$LOG") 2>&1

echo "══════════════════════════════════════════"
echo "  Hotel Media — به‌روزرسانی  $(date '+%F %T')"
echo "══════════════════════════════════════════"

[[ -d .git ]] || { echo "این پوشه مخزن گیت نیست: $APP"; exit 1; }

BEFORE="$(cat VERSION 2>/dev/null || echo '?')"

say "پشتیبان دیتابیس"
mkdir -p storage/backups
DUMP="storage/backups/pre-update-$(date +%Y%m%d-%H%M%S).sql"
if command -v mariadb-dump >/dev/null 2>&1; then
    set -a; . ./.env; set +a
    MYSQL_PWD="${DB_PASSWORD:-}" mariadb-dump --single-transaction --routines --no-tablespaces \
        -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME:-root}" "${DB_DATABASE:-signage_cms}" > "$DUMP"
    ok "پشتیبان: $DUMP"
else
    echo "  mariadb-dump نیست — بدون پشتیبان ادامه می‌دهیم (خطرناک)"
fi

say "دریافت نسخهٔ تازه از $BRANCH"
git fetch --tags --prune origin
git pull --ff-only origin "$BRANCH"
ok "کد به‌روز شد"

say "مهاجرت دیتابیس"
php artisan db:migrate

# .env نباید از مخزن بیاید؛ اگر آمد، به‌روزرسانی نباید رمز هتل را عوض کند.
if git status --porcelain -- .env | grep -q .; then
    echo "  ⚠ .env تغییر کرده — دستی بررسی کنید"
fi

say "ریلود سرویس‌ها"
systemctl reload php8.3-fpm 2>/dev/null || systemctl reload php-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

AFTER="$(cat VERSION 2>/dev/null || echo '?')"
ok "نسخه $BEFORE → $AFTER"
echo "  اگر تلویزیون نسخهٔ قدیمی را نشان می‌دهد، پنل → به‌روزرسانی → ارسال فرمان reload"
