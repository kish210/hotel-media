#!/bin/bash
# راه‌اندازی تیونر DVB-T در TVHeadend: ساخت شبکه، اتصال تیونر، شروع اسکن.
#
#   sudo bash tvheadend-dvb.sh [scanfile]        (پیش‌فرض: dvb-t/ir-Kish)
#
# چرا لازم است: روی یک نصب تازه TVHeadend هیچ شبکه‌ای ندارد و فرانت‌اند
# تیونر `enabled=false` و `networks=[]` است، پس هرگز دستگاه را باز نمی‌کند
# (fd=0 در /proc دیده شد). کانالی نمی‌آید و علتش هیچ‌جا نوشته نیست.
#
# موفقیت با آزمون اعلام می‌شود، نه با رسیدن به آخر اسکریپت: فرانت‌اند باید
# واقعا enabled باشد و شبکه به آن وصل.
set -uo pipefail

APP_DIR=/var/www/hotel-media
SCAN="${1:-dvb-t/ir-Kish}"
NAME="Hotel Media — ${SCAN##*/}"

# اعتبارنامه از جدول برنامه خوانده می‌شود و هرگز چاپ نمی‌شود
read_cred() {
    php -r '
        $e=[]; foreach (file($argv[1]."/.env", FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l) {
            if (($l[0]??"")==="#"||!str_contains($l,"=")) continue; [$k,$v]=explode("=",$l,2); $e[trim($k)]=trim($v," \t\"\x27"); }
        $p=new PDO("mysql:host=127.0.0.1;dbname=".$e["DB_DATABASE"].";charset=utf8mb4",$e["DB_USERNAME"],$e["DB_PASSWORD"]);
        $r=$p->query("SELECT username,password FROM tvheadend_sources WHERE is_active=1 LIMIT 1")->fetch(PDO::FETCH_NUM);
        echo $r ? $r[0]."\t".$r[1] : "";' "$APP_DIR"
}
CRED=$(read_cred)
U=$(printf '%s' "$CRED" | cut -f1); P=$(printf '%s' "$CRED" | cut -f2-)
[ -n "$U" ] || { echo "TVHeadend در برنامه ثبت نیست — اول: php artisan tvheadend:setup" >&2; exit 2; }

api() { curl -s -m 25 --digest -u "$U:$P" "http://127.0.0.1:9981/api/$1" "${@:2}"; }
pj()  { python3 -c "$1" 2>/dev/null; }

# فایل اسکن باید وجود داشته باشد: TVHeadend خطای مبهم می‌دهد
# فایل اسکن باید وجود داشته باشد: TVHeadend خطای مبهم می‌دهد
DIR=/usr/share/tvheadend/data/dvb-scan
[ -f "$DIR/$SCAN" ] || { echo "فایل اسکن «$SCAN» در $DIR نیست" >&2; exit 3; }

# TVHeadend فهرست فایل‌های اسکن را فقط **هنگام شروع** می‌خواند. فایلی که
# بعد از شروع سرویس گذاشته شود دیده نمی‌شود (روی سرور دوم: ۱۱۷۱ فایل بدون
# ir-Kish تا ریستارت). پس اگر کلید پیدا نشد، یک‌بار ریستارت می‌کنیم.
find_key() {
    api "dvb/scanfile/list?type=dvbt" | KEYPART="${SCAN#dvb-t/}" python3 -c '
import sys, json, os
want = os.environ["KEYPART"]
for e in json.load(sys.stdin).get("entries", []):
    # «Iran: ir-Kish» — مقدار نمایشی با نام فایل می‌خورد؛ کلید داخلی را
    # TVHeadend کوتاه می‌کند (dvb-t_ir-Kis) پس با val جست‌وجو می‌کنیم
    if e.get("val", "").endswith(want): print(e["key"]); break' 2>/dev/null
}
SKEY=$(find_key)
if [ -z "$SKEY" ]; then
    systemctl restart tvheadend; sleep 8
    SKEY=$(find_key)
fi
[ -n "$SKEY" ] || { echo "TVHeadend فایل اسکن «$SCAN» را نمی‌شناسد" >&2; exit 3; }

# ۱) شبکه. اگر از قبل هست ولی **mux ندارد** (ساخت قبلی scanfile را نپذیرفت)،
# پاکش می‌کنیم و دوباره می‌سازیم: scanfile ذخیره نمی‌شود (nosave) و فقط
# هنگام ساخت اعمال می‌شود، پس شبکهٔ بی‌mux با ویرایش درست نمی‌شود.
NET=""; NMUX=0
read -r NET NMUX < <(api "mpegts/network/grid" | NAME="$NAME" python3 -c '
import sys, json, os
for n in json.load(sys.stdin).get("entries", []):
    if n.get("networkname") == os.environ["NAME"]:
        print(n["uuid"], n.get("num_mux", 0)); break' 2>/dev/null)
if [ -n "$NET" ] && [ "${NMUX:-0}" = "0" ]; then
    api "idnode/delete" --data-urlencode "uuid=$NET" >/dev/null
    NET=""
fi
if [ -z "$NET" ]; then
    NET=$(api "mpegts/network/create" --data-urlencode "class=dvb_network_dvbt" \
          --data-urlencode "conf={\"networkname\":\"$NAME\",\"scanfile\":\"$SKEY\",\"autodiscovery\":1,\"skipinitscan\":false,\"idlescan\":false}" \
          | python3 -c 'import sys,json; print(json.load(sys.stdin).get("uuid",""))' 2>/dev/null)
fi
[ -n "$NET" ] || { echo "ساخت شبکه ناموفق بود" >&2; exit 4; }

# ۲) اتصال و فعال‌سازی فرانت‌اند
ADP=$(api "hardware/tree?uuid=root" | pj 'import sys,json; print(json.load(sys.stdin)[0]["uuid"])')
FE=$(api "hardware/tree?uuid=$ADP" | pj 'import sys,json; print(json.load(sys.stdin)[0]["uuid"])')
[ -n "$FE" ] || { echo "فرانت‌اند تیونر پیدا نشد (تیونر وصل است؟)" >&2; exit 5; }

api "idnode/save" --data-urlencode "node={\"uuid\":\"$FE\",\"enabled\":true,\"networks\":[\"$NET\"]}" >/dev/null

# ۳) آزمون: واقعا enabled و وصل است؟
ok=$(api "idnode/load?uuid=$FE" | NET="$NET" python3 -c '
import sys, json, os
d = {p["id"]: p.get("value") for p in json.load(sys.stdin)["entries"][0]["params"]}
print("OK" if d.get("enabled") is True and os.environ["NET"] in (d.get("networks") or []) else "NO")' 2>/dev/null)
[ "$ok" = "OK" ] || { echo "اتصال تیونر به شبکه تأیید نشد" >&2; exit 6; }

# شبکه باید واقعا mux داشته باشد. نسخهٔ اول اینجا فقط فرانت‌اند را
# می‌سنجید و «dvb-ready» می‌گفت در حالی که scanfile رد شده بود و
# `num_mux = 0` بود: شبکه‌ای که هیچ فرکانسی برای اسکن ندارد، پس هیچ‌وقت
# کانالی نمی‌آید — و آزمون سبز بود.
NMUX=$(api "idnode/load?uuid=$NET" | python3 -c '
import sys, json
d = {p["id"]: p.get("value") for p in json.load(sys.stdin)["entries"][0]["params"]}
print(d.get("num_mux") or 0)' 2>/dev/null)
[ "${NMUX:-0}" -gt 0 ] || { echo "شبکه ساخته شد ولی هیچ mux ندارد (فایل اسکن بار نشد)" >&2; exit 7; }

echo "network=$NET"
echo "mux=$NMUX"
echo "frontend=$FE"
echo "dvb-ready"
