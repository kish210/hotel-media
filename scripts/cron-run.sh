#!/bin/sh
# ═══════════════════════════════════════════════════════════════════
# اجراکننده‌ی کارهای زمان‌بندی‌شده — با ثبت خطا و عقب‌نشینی
# ═══════════════════════════════════════════════════════════════════
# چرا لازم است: خطوط cron همه با `>/dev/null 2>&1` نوشته شده بودند، پس
# اگر flights:sync یا epg:sync روزی خطا می‌داد هیچ‌جا ثبت نمی‌شد و
# اپراتور فقط می‌دید تابلو داده‌ی قدیمی نشان می‌دهد، بدون هیچ سرنخی.
#
# چرا همه‌ی خروجی ثبت نمی‌شود: monitor:screens هر دقیقه اجرا می‌شود؛
# ثبت خروجی موفق، لاگ را در یک روز پر می‌کند و خطای واقعی را گم. پس
# فقط وقتی چیزی نوشته می‌شود که دستور شکست بخورد.
#
# ── چرا عقب‌نشینی (backoff) ─────────────────────────────────────────
# روی سرور «پالاس» دیده شد: `flights:sync` از ۸ آبان تا امروز **۲۱۹۰
# بار** پشت سر هم شکست خورده بود — هر ۵ دقیقه، بدون حتی یک موفقیت —
# چون آن سرور اصلا به اینترنت وصل نیست و سایت فرودگاه در دسترس نبود.
# نتیجه: ۲۱۹۰ خط یکسان در لاگ که خطاهای واقعی را دفن می‌کرد، و ۲۱۹۰
# بار اجرای بی‌فایده‌ی PHP و تلاش شبکه.
#
# حالا بعد از چند شکست پیاپی، فاصله‌ی اجرا خودش بیشتر می‌شود
# (۱۵ دقیقه، ۳۰، ۱ ساعت، ۲، ۶ ساعت) و لاگ فقط هنگام **تغییر وضع**
# نوشته می‌شود، نه در هر تلاش. اولین موفقیت همه‌چیز را صفر می‌کند،
# پس وقتی اینترنت برگردد کار بدون دخالت کسی عادی می‌شود.
#
# استفاده:  cron-run.sh <artisan-command> [args...]
# ═══════════════════════════════════════════════════════════════════

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LOG="$ROOT/storage/logs/cron.log"
STATE_DIR="$ROOT/storage/logs/cron-state"
MAX_BYTES=524288          # ۵۱۲ کیلوبایت؛ بیشتر شد، نصف می‌شود

[ $# -ge 1 ] || { echo "usage: cron-run.sh <artisan-command> [args...]" >&2; exit 2; }

CMD="$1"; shift
KEY=$(printf '%s' "$CMD" | tr -c 'a-zA-Z0-9' '_')
STATE="$STATE_DIR/$KEY"

mkdir -p "$STATE_DIR" 2>/dev/null

# ── وضع قبلی: «تعداد شکست پیاپی» و «زمان آخرین تلاش» ────────────────
FAILS=0; LAST=0
if [ -f "$STATE" ]; then
    read -r FAILS LAST < "$STATE" 2>/dev/null
    [ -n "$FAILS" ] || FAILS=0
    [ -n "$LAST" ]  || LAST=0
fi

NOW=$(date +%s)

# ── فاصله‌ی لازم بر حسب تعداد شکست ─────────────────────────────────
# تا دو شکست، همان فاصله‌ی cron (بدون تاخیر): خطای گذرای شبکه نباید
# به‌روزرسانی را ساعت‌ها عقب بیندازد.
if   [ "$FAILS" -lt 3  ]; then WAIT=0
elif [ "$FAILS" -lt 6  ]; then WAIT=900      # ۱۵ دقیقه
elif [ "$FAILS" -lt 10 ]; then WAIT=1800     # ۳۰ دقیقه
elif [ "$FAILS" -lt 15 ]; then WAIT=3600     # ۱ ساعت
elif [ "$FAILS" -lt 25 ]; then WAIT=7200     # ۲ ساعت
else                           WAIT=21600    # ۶ ساعت
fi

if [ "$WAIT" -gt 0 ] && [ $((NOW - LAST)) -lt "$WAIT" ]; then
    exit 0          # هنوز وقتش نشده — بی‌صدا رد شو
fi

# ‏php خط‌فرمان — نه هر چیزی که در PATH کمِ cron باشد
PHP="$(command -v php 2>/dev/null)"
[ -n "$PHP" ] || PHP="/usr/bin/php"

OUT="$("$PHP" "$ROOT/artisan" "$CMD" "$@" 2>&1)"
CODE=$?

log_line() {
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
        printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1"
        [ -n "$2" ] && printf '%s\n' "$2" | tail -n 12 | sed 's/^/    /'
    } >> "$LOG" 2>/dev/null
}

if [ "$CODE" -eq 0 ]; then
    # موفق شد: اگر قبلا خراب بود، بازگشت را ثبت کن — اپراتور باید
    # بداند مشکل خودش حل شد، وگرنه آخرین چیزی که در لاگ می‌بیند خطاست.
    if [ "$FAILS" -ge 3 ]; then
        log_line "$CMD: دوباره موفق شد (بعد از $FAILS شکست پیاپی)"
    fi
    rm -f "$STATE" 2>/dev/null
    exit 0
fi

FAILS=$((FAILS + 1))
printf '%s %s\n' "$FAILS" "$NOW" > "$STATE" 2>/dev/null

# فقط هنگام تغییر وضع لاگ کن: سه شکست اول، و بعد هر بار که پله‌ی
# عقب‌نشینی عوض می‌شود. وگرنه همان سیل ۲۱۹۰ خطی دوباره ساخته می‌شود.
case "$FAILS" in
    1|2|3|6|10|15|25|50|100)
        NOTE="$CMD: exit $CODE (شکست پیاپی شماره $FAILS"
        if   [ "$FAILS" -ge 25 ]; then NOTE="$NOTE — از این پس هر ۶ ساعت تلاش می‌شود)"
        elif [ "$FAILS" -ge 15 ]; then NOTE="$NOTE — از این پس هر ۲ ساعت)"
        elif [ "$FAILS" -ge 10 ]; then NOTE="$NOTE — از این پس هر ۱ ساعت)"
        elif [ "$FAILS" -ge 6  ]; then NOTE="$NOTE — از این پس هر ۳۰ دقیقه)"
        elif [ "$FAILS" -ge 3  ]; then NOTE="$NOTE — از این پس هر ۱۵ دقیقه)"
        else                           NOTE="$NOTE)"
        fi
        log_line "$NOTE" "$OUT"
        ;;
esac

exit "$CODE"
