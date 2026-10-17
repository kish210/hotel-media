#!/bin/bash
# ساخت حساب ادمین TVHeadend، بی باز گذاشتن آن به شبکه.
#
#   sudo bash tvheadend-bootstrap.sh <رمز>
#
# مشکل: tvheadend تازه‌نصب هیچ حسابی ندارد. `--firstrun` (-C) حسابی با
# **دسترسی جهانی** می‌سازد — تعریف خود TVHeadend: «world-wide
# administrative access». اگر آن لحظه پورت روی شبکه باز باشد، هر کسی در
# LAN هتل می‌تواند کانال‌ها و ضبط‌ها را دست بزند. پس:
#   ۱) سرویس را متوقف می‌کنیم
#   ۲) یک بار با --firstrun و **فقط روی loopback** بالا می‌آوریم
#   ۳) با API حساب واقعی با رمز تصادفی می‌سازیم
#   ۴) حساب جهانی firstrun را پاک می‌کنیم
#   ۵) سرویس را عادی راه می‌اندازیم (override همیشه روی loopback می‌بندد)
#
# آنچه در پیش‌نویس اول اشتباه بود (و روی سرور دوم دیده شد):
#   • `-f` (fork) و `sudo -u hts` همراه `-u hts` — فرایند در پس‌زمینه
#     بی‌صدا می‌مرد، `set -e` اسکریپت را می‌کشت و سرویس **متوقف و
#     بی‌حساب رها می‌شد**. حالا با trap حتما سرویس برگردانده می‌شود.
#   • هیچ انتظاری برای آماده‌شدن API وجود نداشت.
set -uo pipefail

CONF=/var/lib/tvheadend
USER_NAME=hotelmedia
PASS="${1:-}"
[ -n "$PASS" ] || { echo "رمز لازم است" >&2; exit 2; }

PIDF=/run/tvh-bootstrap.pid
LOGF=/var/log/tvh-bootstrap.log
: > "$LOGF"

# هر چه شد، سرویس عادی برگردد — یک اسکریپت نیمه‌کاره نباید IPTV را
# خاموش بگذارد.
cleanup() {
    [ -f "$PIDF" ] && kill "$(cat "$PIDF")" 2>/dev/null
    pkill -u hts tvheadend 2>/dev/null
    sleep 1
    systemctl start tvheadend 2>/dev/null
}
trap cleanup EXIT

systemctl stop tvheadend
sleep 1

# بدون -f: در foreground به پس‌زمینه می‌بریم تا خطا در لاگ بماند.
# -u/-g خودِ باینری دسترسی را رها می‌کند، پس sudo -u لازم نیست.
/usr/bin/tvheadend -c "$CONF" -C -b 127.0.0.1 --http_port 9981 \
    -p "$PIDF" -u hts -g video >>"$LOGF" 2>&1 &
TVH_PID=$!

# صبر برای آماده‌شدن API (نه sleep کور)
up=""
for i in $(seq 1 40); do
    code=$(curl -s -o /dev/null -m 2 -w '%{http_code}' http://127.0.0.1:9981/api/serverinfo 2>/dev/null)
    # ۲۰۰ (حساب جهانی firstrun) یا ۴۰۱ هر دو یعنی بالاست
    case "$code" in 200|401) up=1; break ;; esac
    kill -0 "$TVH_PID" 2>/dev/null || { echo "tvheadend مرد؛ لاگ:" >&2; tail -8 "$LOGF" >&2; exit 3; }
    sleep 1
done
[ -n "$up" ] || { echo "API بالا نیامد؛ لاگ:" >&2; tail -8 "$LOGF" >&2; exit 4; }

# firstrun حساب جهانی (بدون نام) می‌سازد: با آن مجوز ساخت حساب واقعی هست
api() { curl -s -m 15 "http://127.0.0.1:9981/api/$1" --data-urlencode "conf=$2"; }

api "passwd/entry/create" \
  "{\"enabled\":true,\"username\":\"$USER_NAME\",\"password\":\"$PASS\",\"comment\":\"Hotel Media\"}" >>"$LOGF" 2>&1
api "access/entry/create" \
  "{\"enabled\":true,\"username\":\"$USER_NAME\",\"prefix\":\"0.0.0.0/0,::/0\",\"admin\":true,\"webui\":true,\"streaming\":[\"basic\",\"advanced\",\"htsp\"],\"dvr\":[\"basic\",\"htsp\",\"all\",\"all_rw\",\"failed\"],\"comment\":\"Hotel Media admin\"}" >>"$LOGF" 2>&1

# حساب جهانی firstrun (نام `*`) را حذف کن: همان که بدون رمز ادمین می‌کرد.
#
# پیش‌نویس اول این را با grep روی JSON خام جستجو می‌کرد و هیچ‌چیز پیدا
# نمی‌کرد (قالب واقعی فاصله و ترتیب کلید دیگری دارد)، ولی اسکریپت
# «bootstrap-done» چاپ می‌کرد. نتیجه: ورودی `*` با admin=true می‌ماند و
# **هر کسی در شبکه بدون رمز ادمین TVHeadend بود** — روی سرور دوم با
# `curl` بدون رمز و پاسخ ۲۰۰ دیده شد. حالا JSON واقعا پارس می‌شود.
GLOBAL=$(curl -s -m 15 --digest -u "$USER_NAME:$PASS" http://127.0.0.1:9981/api/access/entry/grid \
         | python3 -c '
import sys, json
for e in json.load(sys.stdin).get("entries", []):
    if e.get("username") in ("*", ""): print(e["uuid"])
' 2>>"$LOGF")
for uuid in $GLOBAL; do
    curl -s -m 10 --digest -u "$USER_NAME:$PASS" http://127.0.0.1:9981/api/idnode/delete \
         --data-urlencode "uuid=$uuid" >>"$LOGF" 2>&1
done

# «تمام شد» را فقط با آزمون می‌گوییم، نه با رسیدن به آخر اسکریپت:
# بدون رمز باید رد شود و با رمز باید قبول شود.
anon=$(curl -s -o /dev/null -m 8 -w '%{http_code}' http://127.0.0.1:9981/api/serverinfo)
auth=$(curl -s -o /dev/null -m 8 --digest -u "$USER_NAME:$PASS" -w '%{http_code}' http://127.0.0.1:9981/api/serverinfo)
if [ "$anon" != "401" ] || [ "$auth" != "200" ]; then
    echo "bootstrap ناقص: بدون رمز=$anon (باید ۴۰۱)، با رمز=$auth (باید ۲۰۰)" >&2
    exit 5
fi

echo "bootstrap-done"
