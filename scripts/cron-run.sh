#!/bin/sh
# ═══════════════════════════════════════════════════════════════════
# اجراکننده‌ی کارهای زمان‌بندی‌شده — با ثبت خطا
# ═══════════════════════════════════════════════════════════════════
# چرا لازم است: خطوط cron همه با `>/dev/null 2>&1` نوشته شده بودند، پس
# اگر flights:sync یا epg:sync روزی خطا می‌داد هیچ‌جا ثبت نمی‌شد و
# اپراتور فقط می‌دید تابلو داده‌ی قدیمی نشان می‌دهد، بدون هیچ سرنشانه‌ای.
#
# چرا همه‌ی خروجی ثبت نمی‌شود: monitor:screens هر دقیقه اجرا می‌شود؛
# ثبت خروجی موفق، لاگ را در یک روز پر می‌کند و خطای واقعی را گم. پس
# فقط وقتی چیزی نوشته می‌شود که دستور شکست بخورد.
#
# استفاده:  cron-run.sh <artisan-command> [args...]
# ═══════════════════════════════════════════════════════════════════

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LOG="$ROOT/storage/logs/cron.log"
MAX_BYTES=524288          # ۵۱۲ کیلوبایت؛ بیشتر شد، نصف می‌شود

[ $# -ge 1 ] || { echo "usage: cron-run.sh <artisan-command> [args...]" >&2; exit 2; }

CMD="$1"; shift

# ‏php خط‌فرمان — نه هر چیزی که در PATH کمِ cron باشد
PHP="$(command -v php 2>/dev/null)"
[ -n "$PHP" ] || PHP="/usr/bin/php"

OUT="$("$PHP" "$ROOT/artisan" "$CMD" "$@" 2>&1)"
CODE=$?

if [ "$CODE" -ne 0 ]; then
    mkdir -p "$(dirname "$LOG")" 2>/dev/null

    # چرخش ساده: نیمه‌ی دوم نگه داشته می‌شود تا لاگ بی‌نهایت رشد نکند
    if [ -f "$LOG" ]; then
        SIZE=$(wc -c < "$LOG" 2>/dev/null || echo 0)
        if [ "$SIZE" -gt "$MAX_BYTES" ]; then
            tail -c $((MAX_BYTES / 2)) "$LOG" > "$LOG.tmp" 2>/dev/null \
                && mv "$LOG.tmp" "$LOG"
        fi
    fi

    {
        printf '[%s] %s: exit %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$CMD" "$CODE"
        # فقط چند خط آخر — خروجی کامل یک دستور شکست‌خورده می‌تواند بلند باشد
        printf '%s\n' "$OUT" | tail -n 12 | sed 's/^/    /'
    } >> "$LOG" 2>/dev/null
fi

exit "$CODE"
