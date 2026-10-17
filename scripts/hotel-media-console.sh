#!/bin/bash
# ═══════════════════════════════════════════════════════════════════
#  Hotel Media — کنسول مدیریت سرور
#  سماع رایانه کیش · kishwifi.com
# ═══════════════════════════════════════════════════════════════════
#
#  روی سرور نصب‌شده با یک کلمه اجرا می‌شود:
#
#      hotel-media
#
#  برای چه هست: کسی که در اتاق سرور هتل جلوی مونیتور می‌نشیند معمولا
#  لینوکس‌کار نیست. تنظیم IP، دیدن اینکه کدام سرویس خوابیده، و
#  فهمیدن اینکه چرا تلویزیون‌ها وصل نمی‌شوند نباید به دانستن netplan و
#  journalctl و mysql نیاز داشته باشد.
#
#  ‏whiptail در نصب پایهٔ اوبونتو هست؛ اگر نبود، منوی متنی سادهٔ
#  جایگزین اجرا می‌شود تا روی کنسول سری هم کار کند.
# ═══════════════════════════════════════════════════════════════════

APP_DIR=/var/www/hotel-media

# نسخهٔ PHP از سرویس واقعا نصب‌شده خوانده می‌شود، نه هاردکد.
#
# پیش از این `PHP_V=8.3` ثابت بود. روی Ubuntu 26.04 (فقط PHP 8.5) هر
# جای این کنسول که `php8.3-fpm` را می‌پرسید «خوابیده» می‌گفت، و «تعمیر
# برنامه» می‌خواست سرویسی را بالا بیاورد که اصلا وجود ندارد. همان خطای
# هاردکد که در نصب‌کننده رفع شد و در این فایل جا مانده بود.
PHP_V=$(systemctl list-unit-files 'php*-fpm.service' --no-legend 2>/dev/null \
        | sed -E 's/^php([0-9]+\.[0-9]+)-fpm\.service.*/\1/' | sort -V | tail -1)
[ -n "$PHP_V" ] || PHP_V=$(ls /etc/php 2>/dev/null | sort -V | tail -1)
[ -n "$PHP_V" ] || PHP_V=8.3
TITLE="Hotel Media — کنسول مدیریت"

# ── کمک‌کننده‌ها ───────────────────────────────────────────────────

# ‏whiptail بی TTY بی‌صدا شکست می‌خورد. بی این بررسی،
# ‏`ssh server hotel-media status` هیچ خروجی نمی‌داد — و این دقیقا
# حالتی است که یک ابزار عیب‌یابی باید در آن کار کند.
have_ui() {
    [ -z "${HM_PLAIN:-}" ] || return 1
    command -v whiptail >/dev/null 2>&1 && [ -t 0 ] && [ -t 1 ]
}

# در حالت زیرفرمان (status/diag) پس از چاپ، منتظر Enter نمی‌مانیم
NO_WAIT=""
pause_or_not() { [ -n "$NO_WAIT" ] || read -rp "Enter برای ادامه... " _; }

need_root() {
    [ "$(id -u)" = "0" ] && return 0
    msg "این کار دسترسی مدیر می‌خواهد.\n\nدوباره با sudo اجرا کنید:\n\n  sudo hotel-media"
    return 1
}

msg() {
    if have_ui; then whiptail --title "$TITLE" --msgbox "$1" 20 74
    else printf "\n%b\n\n" "$1"; pause_or_not; fi
}

ask_yes() {
    if have_ui; then whiptail --title "$TITLE" --yesno "$1" 14 72
    else read -rp "$1 [y/N] " a; [ "$a" = "y" ] || [ "$a" = "Y" ]; fi
}

ask_text() {   # ask_text "پرسش" "پیش‌فرض"
    if have_ui; then whiptail --title "$TITLE" --inputbox "$1" 11 72 "$2" 3>&1 1>&2 2>&3
    else read -rp "$1 [$2]: " a; echo "${a:-$2}"; fi
}

show_file() { # show_file "عنوان" "متن"
    if have_ui; then whiptail --title "$1" --scrolltext --msgbox "$2" 24 90
    # ‏%b و نه %s: متن عیب‌یابی با \n ساخته می‌شود و با %s تحت‌اللفظی
    # چاپ می‌شد — یک گزارش یک‌خطیِ ناخوانا.
    else printf "\n── %s ──\n%b\n" "$1" "$2"; pause_or_not; fi
}

# نام کارت شبکه‌ی اصلی — اسمش روی هر سرور فرق می‌کند (ens160، eno1،
# enp3s0)، پس هاردکد نمی‌شود.
main_nic() {
    ip -o -4 route show default 2>/dev/null | awk '{print $5; exit}' \
      || ip -o -br link | awk '$1!="lo" && $2=="UP" {print $1; exit}'
}

env_val() { # env_val KEY
    [ -f "$APP_DIR/.env" ] || return 1
    grep -m1 "^$1=" "$APP_DIR/.env" 2>/dev/null | cut -d= -f2- | tr -d '"'
}

svc_state() { systemctl is-active "$1" 2>/dev/null || echo unknown; }
svc_mark()  { [ "$(svc_state "$1")" = "active" ] && echo "✔ فعال" || echo "✘ خوابیده"; }

# ═══════════════════════════════════════════════════════════════════
#  ۱) وضعیت کلی
# ═══════════════════════════════════════════════════════════════════
do_status() {
    local nic ip4 ver db_ok screens online pending disk mem load
    nic=$(main_nic)
    ip4=$(ip -4 -br addr show "$nic" 2>/dev/null | awk '{print $3}')
    ver=$(cat "$APP_DIR/VERSION" 2>/dev/null || echo "?")
    disk=$(df -h / | awk 'NR==2{print $4" آزاد از "$2" ("$5" پر)"}')
    mem=$(free -m | awk '/Mem:/{printf "%dMB از %dMB", $3, $2}')
    load=$(cut -d' ' -f1-3 /proc/loadavg)

    # دیتابیس: اتصال واقعی، نه فقط «سرویس فعال است». سرویس بالا و
    # دسترسی خراب، شایع‌ترین حالتی است که اپراتور را گمراه می‌کند.
    local dbu dbp dbn
    dbu=$(env_val DB_USERNAME); dbp=$(env_val DB_PASSWORD); dbn=$(env_val DB_DATABASE)
    if mysql -u"$dbu" -p"$dbp" -N -B -e "SELECT 1" "$dbn" >/dev/null 2>&1; then
        db_ok="✔ وصل"
        screens=$(mysql -u"$dbu" -p"$dbp" -N -B -e "SELECT COUNT(*) FROM screens" "$dbn" 2>/dev/null)
        online=$(mysql -u"$dbu" -p"$dbp" -N -B -e \
          "SELECT COUNT(*) FROM screens WHERE last_seen_at > DATE_SUB(NOW(), INTERVAL 3 MINUTE)" "$dbn" 2>/dev/null)
        pending=$(mysql -u"$dbu" -p"$dbp" -N -B -e \
          "SELECT COUNT(*) FROM screens WHERE status='pending'" "$dbn" 2>/dev/null)
    else
        db_ok="✘ وصل نشد"
    fi

    local http
    http=$(curl -s -o /dev/null -m 5 -w "%{http_code}" http://127.0.0.1/login 2>/dev/null || echo "---")

    show_file "وضعیت سرور" "\
نسخهٔ برنامه :  $ver
نشانی پنل    :  http://${ip4%%/*}/login
کارت شبکه    :  $nic  ($ip4)

── سرویس‌ها ─────────────────────────────────
nginx        :  $(svc_mark nginx)
PHP-FPM      :  $(svc_mark php${PHP_V}-fpm)
MariaDB      :  $(svc_mark mariadb)
دیتابیس      :  $db_ok
پاسخ وب      :  HTTP $http  $([ "$http" = "200" ] && echo "(سالم)" || echo "(بررسی کنید)")

── تلویزیون‌ها ──────────────────────────────
ثبت‌شده      :  ${screens:-?}
روشن (۳ دقیقه):  ${online:-?}
در انتظار تأیید:  ${pending:-?}

── سخت‌افزار ───────────────────────────────
دیسک         :  $disk
حافظه        :  $mem
بار پردازنده :  $load
ffmpeg       :  $(command -v ffmpeg >/dev/null && ffmpeg -version 2>/dev/null | head -1 | cut -d' ' -f3 || echo "نصب نیست")"
}

# ═══════════════════════════════════════════════════════════════════
#  ۲) شبکه و IP
# ═══════════════════════════════════════════════════════════════════
do_network() {
    local nic ip4 gw dns mode cfg
    nic=$(main_nic)
    ip4=$(ip -4 -br addr show "$nic" 2>/dev/null | awk '{print $3}')
    gw=$(ip -4 route show default 2>/dev/null | awk '{print $3; exit}')
    dns=$(resolvectl dns "$nic" 2>/dev/null | sed 's/.*: //' | head -1)
    cfg=$(ls /etc/netplan/*.yaml 2>/dev/null | head -1)
    grep -q "dhcp4: *true" "$cfg" 2>/dev/null && mode="DHCP (خودکار)" || mode="ثابت (Static)"

    local choice
    if have_ui; then
        choice=$(whiptail --title "$TITLE — شبکه" --menu "\
کارت: $nic
نشانی فعلی: $ip4
دروازه: ${gw:-ندارد}    DNS: ${dns:-ندارد}
حالت: $mode" 20 76 4 \
          "1" "تنظیم نشانی ثابت (Static IP)" \
          "2" "برگشت به دریافت خودکار (DHCP)" \
          "3" "نمایش فایل پیکربندی شبکه" \
          "4" "بازگشت" 3>&1 1>&2 2>&3) || return
    else
        printf "\nکارت: %s | نشانی: %s | دروازه: %s | حالت: %s\n" "$nic" "$ip4" "${gw:-—}" "$mode"
        printf "1) نشانی ثابت  2) DHCP  3) نمایش پیکربندی  4) بازگشت\n"
        read -rp "انتخاب: " choice
    fi

    case "$choice" in
      1) net_static "$nic" "${ip4%%/*}" "$gw" ;;
      2) net_dhcp   "$nic" ;;
      3) show_file "پیکربندی شبکه ($cfg)" "$(cat "$cfg" 2>/dev/null)" ;;
      *) return ;;
    esac
}

# netplan try و نه apply: اگر نشانی را اشتباه بدهید و از راه SSH وصل
# باشید، apply ارتباط را قطع می‌کند و سرور دست‌نیافتنی می‌شود.
# try پس از ۱۲۰ ثانیه بی‌تأیید، خودش برمی‌گرداند.
net_apply() {
    local file=$1
    if ! netplan generate 2>/tmp/np.err; then
        msg "پیکربندی معتبر نیست و اعمال نشد:\n\n$(head -5 /tmp/np.err)"
        rm -f "$file"; netplan generate >/dev/null 2>&1
        return 1
    fi

    if ask_yes "پیکربندی نوشته شد.\n\nحالا با «netplan try» اعمال می‌شود: اگر تا ۱۲۰ ثانیه تأیید نکنید، خودش به حالت قبل برمی‌گردد.\n\nاگر از راه SSH وصلید و نشانی عوض شود، ارتباطتان قطع می‌شود — همان وقت صبر کنید تا برگردد.\n\nادامه؟"; then
        clear
        echo "در حال اعمال... (در صورت پرسش، Enter را بزنید تا تأیید شود)"
        netplan try --timeout 120 || {
            msg "اعمال تأیید نشد و پیکربندی به حالت قبل برگشت."
            return 1
        }
        msg "شبکه اعمال شد.\n\nنشانی تازه: $(ip -4 -br addr show "$(main_nic)" | awk '{print $3}')"
    else
        rm -f "$file"; netplan generate >/dev/null 2>&1
        msg "لغو شد و تغییری اعمال نشد."
    fi
}

net_static() {
    local nic=$1 cur=$2 curgw=$3
    need_root || return

    local addr mask gw dns1
    addr=$(ask_text "نشانی IP سرور:" "$cur") || return
    [ -z "$addr" ] && return
    mask=$(ask_text "طول ماسک شبکه (معمولا ۲۴ برای 255.255.255.0):" "24") || return
    gw=$(ask_text "نشانی دروازه (روتر):" "$curgw") || return
    dns1=$(ask_text "سرور DNS:" "${gw:-8.8.8.8}") || return

    # اعتبارسنجی ساده پیش از نوشتن: netplan خطای قالب را دیر می‌گوید و
    # پیامش برای اپراتور غیرفنی بی‌معناست.
    if ! echo "$addr" | grep -qE '^([0-9]{1,3}\.){3}[0-9]{1,3}$'; then
        msg "نشانی «$addr» معتبر نیست. نمونهٔ درست: 172.16.100.29"; return
    fi
    if ! echo "$mask" | grep -qE '^[0-9]{1,2}$' || [ "$mask" -gt 32 ]; then
        msg "طول ماسک «$mask» معتبر نیست. معمولا ۲۴ است."; return
    fi

    # فایل با شمارهٔ بالاتر: روی 01-network.yaml نصب اوبونتو مقدم
    # می‌شود، بی اینکه آن را دست بزنیم — اگر این فایل را پاک کنید،
    # سرور به همان پیکربندی اولیه برمی‌گردد.
    local f=/etc/netplan/90-hotel-media.yaml
    cat > "$f" <<NP
# نوشتهٔ کنسول Hotel Media — برای برگشت به حالت خودکار این فایل را
# پاک کنید و «netplan apply» بزنید.
network:
  version: 2
  ethernets:
    $nic:
      dhcp4: false
      addresses: [$addr/$mask]
      routes:
        - to: default
          via: $gw
      nameservers:
        addresses: [$dns1]
NP
    chmod 600 "$f"
    net_apply "$f"
}

net_dhcp() {
    local nic=$1
    need_root || return
    if [ -f /etc/netplan/90-hotel-media.yaml ]; then
        rm -f /etc/netplan/90-hotel-media.yaml
        netplan apply && msg "به دریافت خودکار (DHCP) برگشت.\n\nنشانی تازه: $(ip -4 -br addr show "$nic" | awk '{print $3}')"
    else
        msg "پیکربندی ثابتی از این کنسول نوشته نشده بود؛ تنظیم فعلی همان نصب اوبونتو است."
    fi
}

# ═══════════════════════════════════════════════════════════════════
#  ۳) لاگ‌ها
# ═══════════════════════════════════════════════════════════════════
do_logs() {
    local choice
    if have_ui; then
        choice=$(whiptail --title "$TITLE — لاگ‌ها" --menu "کدام لاگ؟" 21 76 8 \
          "1" "خطای وب (nginx)" \
          "2" "خطای PHP" \
          "3" "کارهای زمان‌بندی‌شده — فقط شکست‌ها" \
          "4" "نصب اولیه" \
          "5" "دیتابیس (MariaDB)" \
          "6" "رخدادهای برنامه (۳۰ مورد آخر)" \
          "7" "بازگشت" 3>&1 1>&2 2>&3) || return
    else
        printf "1) nginx 2) PHP 3) cron 4) نصب 5) MariaDB 6) رخدادها 7) بازگشت\n"
        read -rp "انتخاب: " choice
    fi

    case "$choice" in
      1) show_file "خطای nginx" "$(tail -60 /var/log/nginx/hotel-media.error.log 2>/dev/null || echo 'لاگی نیست')" ;;
      2) show_file "خطای PHP"  "$(tail -60 /var/log/php-fpm/hotel-media.error.log 2>/dev/null || echo 'لاگی نیست')" ;;
      3) show_file "شکست کارهای زمان‌بندی‌شده" "$(tail -60 "$APP_DIR/storage/logs/cron.log" 2>/dev/null || echo 'هیچ شکستی ثبت نشده — همین خوب است')" ;;
      4) show_file "نصب اولیه" "$(tail -80 /var/log/hotel-media-install.log 2>/dev/null || echo 'این سرور از ISO نصب نشده یا لاگ پاک شده')" ;;
      5) show_file "MariaDB" "$(journalctl -u mariadb -n 40 --no-pager 2>/dev/null || echo 'دسترسی نیست')" ;;
      6) log_events ;;
      *) return ;;
    esac
}

log_events() {
    local dbu dbp dbn out
    dbu=$(env_val DB_USERNAME); dbp=$(env_val DB_PASSWORD); dbn=$(env_val DB_DATABASE)
    out=$(mysql -u"$dbu" -p"$dbp" -t -e \
      "SELECT created_at, action, subject_type FROM activity_logs ORDER BY id DESC LIMIT 30" \
      "$dbn" 2>/dev/null) || out="خوانده نشد — اتصال دیتابیس را بررسی کنید"
    show_file "رخدادهای برنامه" "$out"
}

# ═══════════════════════════════════════════════════════════════════
#  ۴) عیب‌یابی
# ═══════════════════════════════════════════════════════════════════
do_diag() {
    local choice
    if have_ui; then
        choice=$(whiptail --title "$TITLE — عیب‌یابی" --menu "چه چیزی را بسنجیم؟" 21 78 7 \
          "1" "بررسی کامل سیستم" \
          "2" "تست شبکهٔ تلویزیون‌ها (پینگ یک نشانی)" \
          "3" "فهرست تلویزیون‌ها و آخرین ضربان" \
          "4" "تست ضربان یک تلویزیون (با کد صفحه)" \
          "5" "صف پردازش ویدیو" \
          "6" "بازگشت" 3>&1 1>&2 2>&3) || return
    else
        printf "1) بررسی کامل 2) پینگ 3) فهرست تلویزیون 4) تست ضربان 5) صف ویدیو 6) بازگشت\n"
        read -rp "انتخاب: " choice
    fi

    case "$choice" in
      1) diag_full ;;
      2) diag_ping ;;
      3) diag_screens ;;
      4) diag_heartbeat ;;
      5) show_file "صف پردازش ویدیو" "$(cd "$APP_DIR" && sudo -u www-data php artisan vod:queue 2>&1 | tail -20)" ;;
      *) return ;;
    esac
}

diag_full() {
    local out="" dbu dbp dbn
    dbu=$(env_val DB_USERNAME); dbp=$(env_val DB_PASSWORD); dbn=$(env_val DB_DATABASE)

    out+="── سرویس‌ها ──\n"
    for s in nginx php${PHP_V}-fpm mariadb; do
        out+="$(printf '%-16s %s' "$s" "$(svc_mark $s)")\n"
    done

    out+="\n── اتصال‌ها ──\n"
    mysql -u"$dbu" -p"$dbp" -N -B -e "SELECT 1" "$dbn" >/dev/null 2>&1 \
      && out+="دیتابیس          ✔ وصل\n" || out+="دیتابیس          ✘ وصل نشد (.env را بررسی کنید)\n"

    local code; code=$(curl -s -o /dev/null -m 5 -w "%{http_code}" http://127.0.0.1/login 2>/dev/null)
    [ "$code" = "200" ] && out+="صفحهٔ ورود       ✔ HTTP 200\n" || out+="صفحهٔ ورود       ✘ HTTP ${code:-timeout}\n"

    code=$(curl -s -o /dev/null -m 5 -w "%{http_code}" -X POST \
           http://127.0.0.1/api/v1/screens/__diag__/heartbeat -H 'Content-Type: application/json' -d '{}' 2>/dev/null)
    # ۴۰۴ هم پاسخ سالمی است: یعنی مسیر کار می‌کند و فقط آن کد صفحه
    # وجود ندارد. آنچه بد است، ۵۰۰ یا بی‌پاسخی است.
    case "$code" in
      200|404) out+="مسیر ضربان       ✔ پاسخ می‌دهد (HTTP $code)\n" ;;
      *)       out+="مسیر ضربان       ✘ HTTP ${code:-timeout}\n" ;;
    esac

    out+="\n── ابزارهای رسانه ──\n"
    out+="$(printf '%-16s %s' ffmpeg "$(command -v ffmpeg >/dev/null && echo "✔ $(ffmpeg -version 2>/dev/null | head -1 | cut -d' ' -f3)" || echo '✘ نصب نیست')")\n"
    out+="$(printf '%-16s %s' ffprobe "$(command -v ffprobe >/dev/null && echo '✔' || echo '✘ نصب نیست')")\n"
    out+="$(printf '%-16s %s' pdftoppm "$(command -v pdftoppm >/dev/null && echo '✔ (کتاب‌خوان)' || echo '✘ poppler-utils نیست')")\n"

    out+="\n── نوشتن روی دیسک ──\n"
    for d in storage/logs storage/sessions public/uploads/media public/uploads/vod; do
        if sudo -u www-data test -w "$APP_DIR/$d" 2>/dev/null; then
            out+="$(printf '%-24s ✔' "$d")\n"
        else
            out+="$(printf '%-24s ✘ www-data نمی‌تواند بنویسد' "$d")\n"
        fi
    done

    out+="\n── فضا ──\n$(df -h / | awk 'NR==2{print "ریشه: "$4" آزاد ("$5" پر)"}')\n"
    local hb
    hb=$(mysql -u"$dbu" -p"$dbp" -N -B -e "SELECT COUNT(*) FROM heartbeats" "$dbn" 2>/dev/null)
    [ -n "$hb" ] && out+="ردیف ضربان: $hb (هرس هفتگی: artisan storage:clean)\n"

    show_file "بررسی کامل سیستم" "$out"
}

diag_ping() {
    local t; t=$(ask_text "نشانی یا نام میزبان برای پینگ:" "172.16.100.1") || return
    [ -z "$t" ] && return
    show_file "پینگ $t" "$(ping -c 4 -W 2 "$t" 2>&1 | tail -8)"
}

diag_screens() {
    local dbu dbp dbn out
    dbu=$(env_val DB_USERNAME); dbp=$(env_val DB_PASSWORD); dbn=$(env_val DB_DATABASE)
    out=$(mysql -u"$dbu" -p"$dbp" -t -e "
      SELECT code AS 'کد', LEFT(name,18) AS 'نام', screen_type AS 'نوع', platform AS 'پلتفرم',
             status AS 'وضعیت',
             IFNULL(CONCAT(TIMESTAMPDIFF(MINUTE, last_seen_at, NOW()),' دقیقه پیش'),'هیچ‌وقت') AS 'آخرین ضربان'
        FROM screens ORDER BY last_seen_at DESC LIMIT 40" "$dbn" 2>/dev/null) \
      || out="خوانده نشد"
    show_file "تلویزیون‌ها" "$out"
}

diag_heartbeat() {
    local c; c=$(ask_text "کد صفحه (مثلا SCR001):" "") || return
    [ -z "$c" ] && return
    local r
    r=$(curl -s -m 8 -X POST "http://127.0.0.1/api/v1/screens/$c/heartbeat" \
        -H 'Content-Type: application/json' -d '{}' 2>&1)
    show_file "پاسخ ضربان $c" "${r:-بی‌پاسخ}"
}

# ═══════════════════════════════════════════════════════════════════
#  ۵) سرویس‌ها
# ═══════════════════════════════════════════════════════════════════
do_services() {
    need_root || return
    local choice
    if have_ui; then
        choice=$(whiptail --title "$TITLE — سرویس‌ها" --menu "\
nginx: $(svc_state nginx)    PHP: $(svc_state php${PHP_V}-fpm)    MariaDB: $(svc_state mariadb)" 18 74 5 \
          "1" "راه‌اندازی دوبارهٔ همه" \
          "2" "فقط nginx" \
          "3" "فقط PHP-FPM" \
          "4" "فقط MariaDB" \
          "5" "بازگشت" 3>&1 1>&2 2>&3) || return
    else
        printf "1) همه 2) nginx 3) PHP 4) MariaDB 5) بازگشت\n"; read -rp "انتخاب: " choice
    fi

    case "$choice" in
      1) systemctl restart mariadb php${PHP_V}-fpm nginx 2>&1 | head -5
         msg "راه‌اندازی دوباره انجام شد.\n\nnginx: $(svc_state nginx)\nPHP: $(svc_state php${PHP_V}-fpm)\nMariaDB: $(svc_state mariadb)" ;;
      2) systemctl restart nginx;            msg "nginx: $(svc_state nginx)" ;;
      3) systemctl restart php${PHP_V}-fpm;  msg "PHP-FPM: $(svc_state php${PHP_V}-fpm)" ;;
      4) systemctl restart mariadb;          msg "MariaDB: $(svc_state mariadb)" ;;
      *) return ;;
    esac
}

# ═══════════════════════════════════════════════════════════════════
#  ۶) مدیریت برنامه
# ═══════════════════════════════════════════════════════════════════
do_admin() {
    local choice
    if have_ui; then
        choice=$(whiptail --title "$TITLE — برنامه" --menu "نسخهٔ فعلی: $(cat "$APP_DIR/VERSION" 2>/dev/null)" 19 76 6 \
          "1" "نمایش نشانی پنل و کاربر مدیر" \
          "2" "تعیین رمز تازه برای مدیر" \
          "3" "اجرای migration دیتابیس" \
          "4" "پاک‌سازی و هرس (storage:clean)" \
          "5" "پشتیبان‌گیری دیتابیس" \
          "6" "بازگشت" 3>&1 1>&2 2>&3) || return
    else
        printf "1) نشانی پنل 2) رمز مدیر 3) migration 4) پاک‌سازی 5) پشتیبان 6) بازگشت\n"
        read -rp "انتخاب: " choice
    fi

    case "$choice" in
      1) local ip4; ip4=$(ip -4 -br addr show "$(main_nic)" | awk '{print $3}')
         msg "پنل مدیریت:\n  http://${ip4%%/*}/login\n\nکاربر مدیر در .env:\n  $(env_val ADMIN_EMAIL)\n\nتلویزیون‌ها را به این نشانی ببرید:\n  http://${ip4%%/*}/tv" ;;
      2) admin_passwd ;;
      3) show_file "migration" "$(cd "$APP_DIR" && sudo -u www-data php artisan db:migrate 2>&1 | tail -20)" ;;
      4) show_file "پاک‌سازی" "$(cd "$APP_DIR" && sudo -u www-data php artisan storage:clean 2>&1 | tail -15)" ;;
      5) admin_backup ;;
      *) return ;;
    esac
}

#  رمز کاربران پنل
#
#  دو نقص نسخهٔ اول (که اینجا رفع شدند):
#   • ایمیل و هش مستقیم داخل رشتهٔ SQL چسبانده می‌شد: ایمیلی با `'`
#     تزریق SQL بود. حالا همه‌چیز با پارامتر PDO می‌رود، نه الحاق رشته.
#   • رمز آرگومان `php -r` بود و در `ps` برای هر کاربر محلی دیده می‌شد.
#     حالا از stdin خوانده می‌شود.
#  و اپراتور باید ایمیل را از حفظ تایپ می‌کرد؛ حالا فهرست کاربران را
#  می‌بیند و انتخاب می‌کند.
# اجرای یک کوئری پارامتری از طریق PHP/PDO و با اتصال خودِ برنامه.
#   hm_sql "SELECT ..." پارامتر۱ پارامتر۲ ...    (خروجی: ردیف‌ها جداشده با TAB)
# مقدارها از stdin می‌آیند (JSON) تا نه در ps دیده شوند نه با شل تفسیر شوند.
hm_sql() {
    local sql="$1"; shift
    local json; json=$(printf '%s\n' "$@" | php -r '
        $a = array_values(array_filter(explode("\n", stream_get_contents(STDIN)), fn($x) => $x !== ""));
        echo json_encode($a, JSON_UNESCAPED_UNICODE);')
    HM_SQL="$sql" HM_PARAMS="$json" HM_APP="$APP_DIR" php -r '
        $env = [];
        foreach (file(getenv("HM_APP")."/.env", FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l) {
            if (($l[0] ?? "") === "#" || !str_contains($l, "=")) continue;
            [$k, $v] = explode("=", $l, 2); $env[trim($k)] = trim($v, " \t\"\x27");
        }
        try {
            $pdo = new PDO("mysql:host=".($env["DB_HOST"]??"127.0.0.1").";port=".($env["DB_PORT"]??3306).
                           ";dbname=".$env["DB_DATABASE"].";charset=utf8mb4",
                           $env["DB_USERNAME"], $env["DB_PASSWORD"],
                           [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $st = $pdo->prepare(getenv("HM_SQL"));
            $st->execute(json_decode(getenv("HM_PARAMS"), true) ?: []);
            if ($st->columnCount()) {
                foreach ($st->fetchAll(PDO::FETCH_NUM) as $r) echo implode("\t", array_map("strval", $r)), "\n";
            } else { echo $st->rowCount(), "\n"; }
        } catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }'
}

admin_passwd() {
    need_root || return

    # فهرست کاربران برای انتخاب — اپراتور ایمیل را حفظ نیست
    local rows; rows=$(hm_sql "SELECT id, email, role FROM users WHERE is_active = 1 AND deleted_at IS NULL ORDER BY role = 'super_admin' DESC, id") \
        || { msg "فهرست کاربران خوانده نشد.\nاتصال دیتابیس را بررسی کنید (عیب‌یابی ← بررسی کامل)."; return; }
    [ -n "$rows" ] || { msg "هیچ کاربر فعالی نیست."; return; }

    local items=() uid mail role n=0
    while IFS=$'\t' read -r uid mail role; do
        items+=("$uid" "$mail  ($role)"); n=$((n+1))
    done <<< "$rows"

    local pick
    if have_ui; then
        pick=$(whiptail --title "$TITLE — رمز کاربر" --menu "رمز کدام کاربر عوض شود؟" 20 76 10 "${items[@]}" 3>&1 1>&2 2>&3) || return
    else
        printf '%s\n' "$rows"; read -rp "شناسهٔ کاربر: " pick
    fi
    [[ "$pick" =~ ^[0-9]+$ ]] || { msg "شناسهٔ نامعتبر."; return; }

    local p1 p2
    if have_ui; then
        p1=$(whiptail --title "$TITLE" --passwordbox "رمز تازه (حداقل ۸ نویسه):" 10 70 3>&1 1>&2 2>&3) || return
        p2=$(whiptail --title "$TITLE" --passwordbox "رمز را دوباره بنویسید:" 10 70 3>&1 1>&2 2>&3) || return
    else
        read -rsp "رمز تازه: " p1; echo; read -rsp "تکرار: " p2; echo
    fi
    # تکرار لازم است: رمزی که اشتباه تایپ شود کاربر را از پنل بیرون می‌اندازد
    # و تنها راه برگشت همین کنسول است.
    [ "$p1" = "$p2" ]   || { msg "دو رمز یکی نیست — تغییری داده نشد."; return; }
    [ ${#p1} -ge 8 ]    || { msg "رمز کوتاه است (حداقل ۸ نویسه)."; return; }

    # هش با همان تابع خودِ برنامه (bcrypt)؛ هش با الگوریتم دیگر کاربر را
    # بی‌صدا قفل می‌کند. رمز از stdin می‌آید نه آرگومان.
    local hash; hash=$(printf '%s' "$p1" | php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_BCRYPT, ["cost"=>12]);' 2>/dev/null)
    [ -n "$hash" ] || { msg "ساخت هش رمز ناموفق بود."; return; }

    local changed; changed=$(hm_sql "UPDATE users SET password = ? WHERE id = ?" "$hash" "$pick") \
        || { msg "به‌روزرسانی رمز ناموفق بود."; return; }
    if [ "${changed:-0}" -ge 1 ]; then
        msg "رمز کاربر عوض شد.\n\nهمین حالا با رمز تازه وارد پنل شوید."
    else
        msg "کاربر پیدا نشد."
    fi
}

admin_backup() {
    need_root || return
    local dbu dbp dbn dir f
    dbu=$(env_val DB_USERNAME); dbp=$(env_val DB_PASSWORD); dbn=$(env_val DB_DATABASE)
    dir=/var/backups/hotel-media
    mkdir -p "$dir"
    f="$dir/db-$(date +%Y%m%d-%H%M).sql.gz"
    if mysqldump -u"$dbu" -p"$dbp" --single-transaction --quick "$dbn" 2>/dev/null | gzip > "$f"; then
        msg "پشتیبان گرفته شد:\n\n  $f\n  اندازه: $(du -h "$f" | cut -f1)\n\nفایل‌های رسانه در این پشتیبان **نیستند** — پوشهٔ\n$APP_DIR/public/uploads را جدا کپی کنید."
    else
        rm -f "$f"; msg "پشتیبان‌گیری ناموفق بود."
    fi
}

# ═══════════════════════════════════════════════════════════════════
#  تعمیر برنامه — «چیزی کار نمی‌کند، چه کنم؟»
# ═══════════════════════════════════════════════════════════════════
#
#  هر مرحله **می‌سنجد، بعد رفع می‌کند، بعد دوباره می‌سنجد** و نتیجه را
#  می‌گوید. تعمیرِ کورکورانه («همه‌چیز را chmod کن») خرابی تازه می‌سازد و
#  اپراتور نمی‌فهمد چه چیزی عوض شد؛ پس هر قدم می‌نویسد چه یافت و چه کرد.
#  فهرست از خرابی‌هایی ساخته شده که واقعا روی سرورها دیده شد، نه حدس.
do_repair() {
    need_root || return
    ask_yes "تعمیر خودکار این موارد را بررسی و رفع می‌کند:\n\n • سرویس‌های خوابیده\n • مجوز پوشه‌های نوشتنی\n • migration عقب‌مانده\n • صفحهٔ ورود\n\nبه دادهٔ هتل دست نمی‌زند. ادامه؟" || return

    local out="" fixed=0 bad=0
    ok()   { out+="  ✔ $1\n"; }
    fix()  { out+="  ✎ $1\n"; fixed=$((fixed+1)); }
    fail() { out+="  ✘ $1\n"; bad=$((bad+1)); }

    # ۱) سرویس‌ها: خوابیده را بالا بیاور و دوباره بسنج
    out+="── سرویس‌ها ──\n"
    local s
    for s in mariadb php${PHP_V}-fpm nginx; do
        if [ "$(svc_state "$s")" = "active" ]; then ok "$s فعال است"
        else
            systemctl reset-failed "$s" 2>/dev/null; systemctl start "$s" 2>/dev/null; sleep 2
            if [ "$(svc_state "$s")" = "active" ]; then fix "$s خوابیده بود و راه‌اندازی شد"
            else fail "$s بالا نیامد — لاگ: journalctl -u $s -n 20"; fi
        fi
    done
    # nginx با پیکربندی خراب بالا نمی‌آید؛ علت را بگو نه فقط «خوابیده»
    nginx -t >/dev/null 2>&1 || fail "پیکربندی nginx معتبر نیست: $(nginx -t 2>&1 | grep -iE 'emerg|error' | head -1 | cut -c1-90)"

    # ۲) مجوز پوشه‌ها: فقط پوشه‌هایی که برنامه باید در آن‌ها بنویسد
    out+="\n── مجوز پوشه‌ها ──\n"
    local d
    for d in storage/logs storage/sessions storage/cache public/uploads/media public/uploads/vod public/uploads/subtitles storage/drivers/incoming; do
        mkdir -p "$APP_DIR/$d" 2>/dev/null
        if sudo -u www-data test -w "$APP_DIR/$d" 2>/dev/null; then ok "$d"
        else
            chown -R www-data:www-data "$APP_DIR/$d" 2>/dev/null; chmod -R u+rwX,g+rwX "$APP_DIR/$d" 2>/dev/null
            if sudo -u www-data test -w "$APP_DIR/$d" 2>/dev/null; then fix "$d قابل‌نوشتن نبود و درست شد"
            else fail "$d همچنان قابل‌نوشتن نیست"; fi
        fi
    done
    # .env را فقط نمایش می‌دهیم، نه عوض می‌کنیم: رمزهای دیتابیس آنجاست
    [ -r "$APP_DIR/.env" ] && ok ".env خوانا است" || fail ".env پیدا نشد یا خوانا نیست"

    # ۳) دیتابیس: migration عقب‌مانده
    out+="\n── دیتابیس ──\n"
    if hm_sql "SELECT 1" >/dev/null 2>&1; then
        ok "اتصال دیتابیس سالم است"
        local before after
        before=$(hm_sql "SELECT COUNT(*) FROM schema_migrations" 2>/dev/null)
        (cd "$APP_DIR" && sudo -u www-data php artisan db:migrate >/dev/null 2>&1)
        after=$(hm_sql "SELECT COUNT(*) FROM schema_migrations" 2>/dev/null)
        if [ "${after:-0}" -gt "${before:-0}" ]; then fix "$((after-before)) migration عقب‌مانده اعمال شد"
        else ok "همهٔ migration ها اعمال شده‌اند ($after)"; fi
    else
        fail "اتصال دیتابیس برقرار نیست — رمز در .env با دیتابیس نمی‌خواند؟"
    fi

    # ۴) کش PHP: بعد از به‌روزرسانی دستی ممکن است کد قدیمی را سرو کند
    systemctl reload "php${PHP_V}-fpm" 2>/dev/null && ok "کش PHP خالی شد"

    # ۵) آزمون نهایی از بیرون: همان چیزی که تلویزیون و مرورگر می‌بینند
    out+="\n── آزمون نهایی ──\n"
    local c
    c=$(curl -s -o /dev/null -m 8 -w '%{http_code}' http://127.0.0.1/login 2>/dev/null)
    [ "$c" = "200" ] && ok "صفحهٔ ورود پاسخ می‌دهد" || fail "صفحهٔ ورود HTTP ${c:-timeout} داد (باید ۲۰۰ باشد)"
    c=$(curl -s -o /dev/null -m 8 -w '%{http_code}' -X POST -H 'Content-Type: application/json' -d '{}' http://127.0.0.1/api/v1/screens/__repair__/heartbeat 2>/dev/null)
    case "$c" in 200|404) ok "مسیر ضربان تلویزیون‌ها پاسخ می‌دهد" ;; *) fail "مسیر ضربان HTTP ${c:-timeout} داد" ;; esac

    local verdict
    if   [ "$bad" -gt 0 ];   then verdict="⚠ $bad مورد رفع نشد — خروجی را برای پشتیبانی بفرستید"
    elif [ "$fixed" -gt 0 ]; then verdict="✔ $fixed مورد رفع شد و همه‌چیز سالم است"
    else                          verdict="✔ مشکلی پیدا نشد"; fi
    show_file "نتیجهٔ تعمیر" "$out\n$verdict"
}

# ═══════════════════════════════════════════════════════════════════
#  درایور و تیونر
# ═══════════════════════════════════════════════════════════════════
#
#  نصب از طریق همان پل root برنامه (`hotel-media-driver`) انجام می‌شود،
#  نه apt مستقیم: آن اسکریپت فهرست ثابت id و بررسی مسیر دارد و پنل هم از
#  همان استفاده می‌کند — یک راه نصب، نه دو راه که از هم دور شوند.
HM_DRIVER=/usr/local/sbin/hotel-media-driver

do_drivers() {
    local choice
    if have_ui; then
        choice=$(whiptail --title "$TITLE — درایور و تیونر" --menu "TVHeadend: $(svc_mark tvheadend)    تیونر: $(dvb_count) دستگاه" 21 78 8 \
          "1" "تشخیص سخت‌افزار (تیونر، کارت کپچر، گرافیک)" \
          "2" "نصب درایور تیونر DVB (برای TVHeadend)" \
          "3" "تست تیونر و اسکن کانال" \
          "4" "نصب درایور کارت گرافیک / انکود سخت‌افزاری" \
          "5" "نصب درایور کارت کپچر (v4l)" \
          "6" "وضعیت TVHeadend و تست اتصال برنامه" \
          "7" "نصب از فایل (.deb / .run که خودتان آورده‌اید)" \
          "8" "بازگشت" 3>&1 1>&2 2>&3) || return
    else
        printf "1) تشخیص 2) درایور DVB 3) تست تیونر 4) گرافیک 5) کپچر 6) TVHeadend 7) از فایل 8) بازگشت\n"
        read -rp "انتخاب: " choice
    fi
    case "$choice" in
      1) drv_detect ;;
      2) drv_install dvb ;;
      3) drv_tuner_test ;;
      4) drv_gpu ;;
      5) drv_install v4l ;;
      6) drv_tvh_status ;;
      7) drv_from_file ;;
      *) return ;;
    esac
}

dvb_count() { ls -d /dev/dvb/adapter* 2>/dev/null | wc -l; }

drv_helper_ok() {
    if [ -x "$HM_DRIVER" ]; then return 0; fi
    msg "ابزار نصب درایور روی این سرور نیست:\n  $HM_DRIVER\n\nنصب‌کننده آن را می‌گذارد. با «تعمیر برنامه» یا نصب دوباره درست می‌شود."
    return 1
}

drv_install() {
    need_root || return
    drv_helper_ok || return
    local id=$1
    ask_yes "نصب «$id» از اینترنت (apt) انجام می‌شود.\nچند دقیقه طول می‌کشد. ادامه؟" || return
    clear; echo "در حال نصب $id ..."
    local log; log=$(HM_DRIVER_DRYRUN=0 "$HM_DRIVER" online "$id" 2>&1); local rc=$?
    # فقط انتهای خروجی: لاگ apt بلند است و اپراتور فقط نتیجه را می‌خواهد
    if [ $rc -eq 0 ]; then
        msg "نصب $id تمام شد.\n\n$(printf '%s' "$log" | grep -E 'رد شد|راه‌اندازی دوباره' | head -3)\n\nبرای تیونر: USB را بکشید و دوباره بزنید، یا سرور را راه‌اندازی دوباره کنید."
    else
        show_file "نصب $id ناموفق بود" "$(printf '%s' "$log" | tail -12)"
    fi
}

drv_detect() {
    local out=""
    out+="── تیونر DVB ──\n"
    if ls /dev/dvb/adapter* >/dev/null 2>&1; then
        local a; for a in /dev/dvb/adapter*; do out+="  ✔ $a\n"; done
    else
        out+="  ✘ هیچ تیونری شناسایی نشد (/dev/dvb خالی است)\n"
    fi
    # تیونر USB/PCI که دیده می‌شود ولی درایورش بار نشده، رایج‌ترین حالت است
    local hits; hits=$( { lsusb 2>/dev/null; lspci 2>/dev/null; } | grep -iE 'dvb|tv tuner|hauppauge|tbs|dvbsky|astrometa|terratec|avermedia|pctv|digital ?devices|technisat|siliconDust|cx2388|saa713|em28|af9035|rtl28|mxl|tevii|geniatech' | head -5)
    if [ -n "$hits" ]; then out+="\n  سخت‌افزاری که شبیه تیونر است:\n$(printf '%s\n' "$hits" | sed 's/^/    /')\n"
    else out+="\n  هیچ سخت‌افزار شبیه تیونر روی USB یا PCI دیده نشد.\n"; fi

    out+="\n── کارت کپچر ──\n"
    if ls /dev/video* >/dev/null 2>&1; then
        local v; for v in /dev/video*; do
            out+="  ✔ $v  $(v4l2-ctl -d "$v" --info 2>/dev/null | awk -F': ' '/Card type/ {print $2; exit}')\n"
        done
    else out+="  ✘ دستگاه ویدیویی دیده نشد\n"; fi

    out+="\n── گرافیک / انکود سخت‌افزاری ──\n"
    local g; g=$(lspci 2>/dev/null | grep -iE 'vga|3d|display' | head -3)
    [ -n "$g" ] && out+="$(printf '%s\n' "$g" | sed 's/^/  /')\n" || out+="  (کارت گرافیک جدا دیده نشد)\n"
    # وجود /dev/dri/renderD128 فقط یعنی کرنل برای یک کارت **نمایشی**
    # درایور دارد، نه اینکه انکودر ویدیو هست — روی VMware SVGA هم هست و
    # انکودی ندارد. پیام قبلی («انکود VAAPI ممکن است») روی همین VM
    # دروغ بود. تنها مرجع درست `vainfo` است: فهرست پروفایل‌هایی که
    # سخت‌افزار واقعا می‌تواند **انکود** کند (VAEntrypointEncSlice).
    if command -v vainfo >/dev/null 2>&1; then
        if vainfo 2>/dev/null | grep -q 'VAEntrypointEncSlice'; then
            out+="  ✔ انکود سخت‌افزاری VAAPI: $(vainfo 2>/dev/null | grep -m1 'VAEntrypointEncSlice' | awk '{print $1}')\n"
        else
            out+="  ✘ کارت انکود ویدیو ندارد (vainfo فقط رمزگشایی یا هیچ‌چیز نشان می‌دهد) — انکود با CPU\n"
        fi
    elif ls /dev/dri/render* >/dev/null 2>&1; then
        out+="  ? /dev/dri هست ولی نمی‌توان انکود را سنجید — درایور گرافیک (گزینهٔ ۴) را نصب کنید تا vainfo بیاید\n"
    else
        out+="  ✘ /dev/dri نیست — انکود فقط با CPU\n"
    fi
    command -v nvidia-smi >/dev/null && out+="  ✔ NVIDIA: $(nvidia-smi --query-gpu=name --format=csv,noheader 2>/dev/null | head -1)\n"
    show_file "تشخیص سخت‌افزار" "$out"
}

drv_gpu() {
    local choice
    have_ui && choice=$(whiptail --title "$TITLE" --menu "کارت گرافیک شما؟" 14 70 4 \
        "intel"  "Intel (QSV / VAAPI)" \
        "amd"    "AMD (Mesa VAAPI)" \
        "nvidia" "NVIDIA (NVENC) — نیاز به راه‌اندازی دوباره" \
        "back"   "بازگشت" 3>&1 1>&2 2>&3) || return
    [ -z "${choice:-}" ] && { read -rp "intel|amd|nvidia: " choice; }
    case "$choice" in intel|amd|nvidia) drv_install "$choice" ;; esac
}

drv_from_file() {
    need_root || return
    drv_helper_ok || return
    local inc="$APP_DIR/storage/drivers/incoming"
    mkdir -p "$inc"
    local files; files=$(ls -1 "$inc" 2>/dev/null)
    if [ -z "$files" ]; then
        msg "هیچ فایلی در این پوشه نیست:\n  $inc\n\nفایل .deb یا .run را با USB یا scp اینجا بگذارید، سپس دوباره بیایید.\nاز پنل (درایورها) هم می‌شود بارگذاری کرد."
        return
    fi
    local f id
    f=$(ask_text "نام فایل (از فهرست):\n$files" "$(printf '%s\n' "$files" | head -1)") || return
    id=$(ask_text "برای کدام درایور؟ (ffmpeg / intel / amd / nvidia / dvb / v4l / decklink)" "dvb") || return
    [ -f "$inc/$f" ] || { msg "فایل «$f» در پوشه نیست."; return; }
    ask_yes "فایل «$f» با دسترسی root نصب می‌شود.\nفقط فایلی را نصب کنید که از منبع معتبر گرفته‌اید. ادامه؟" || return
    clear
    show_file "نصب از فایل" "$("$HM_DRIVER" file "$id" "$inc/$f" 2>&1 | tail -15)"
}

# ── TVHeadend ──────────────────────────────────────────────────────
drv_tvh_status() {
    local out="" ver code
    out+="سرویس:        $(svc_mark tvheadend)\n"
    if command -v tvheadend >/dev/null 2>&1; then
        ver=$(dpkg-query -W -f='${Version}' tvheadend 2>/dev/null | cut -d~ -f1)
        out+="نسخه:         ${ver:-?}\n"
    else
        msg "TVHeadend روی این سرور نصب نیست.\n\nنصب‌کننده آن را می‌آورد؛ برای نصب دستی:\n  sudo bash $APP_DIR/scripts/install-ubuntu.sh"
        return
    fi
    out+="گوش‌دادن:     $(ss -tlnp 2>/dev/null | awk '/:9981 / {print $4; exit}')\n"
    out+="تیونرها:      $(dvb_count) دستگاه\n"

    # پاسخ بدون رمز باید ۴۰۱ باشد؛ ۲۰۰ یعنی هر کسی ادمین است
    code=$(curl -s -o /dev/null -m 6 -w '%{http_code}' http://127.0.0.1:9981/api/serverinfo 2>/dev/null)
    case "$code" in
      401) out+="احراز هویت:  ✔ فعال (بدون رمز رد می‌شود)\n" ;;
      200) out+="احراز هویت:  ✘ خطر — بدون رمز ادمین است!\n             دکمهٔ «ساخت دوبارهٔ حساب» را بزنید.\n" ;;
      *)   out+="احراز هویت:  ? HTTP ${code:-timeout}\n" ;;
    esac

    # اتصال از دید خودِ برنامه: همان چیزی که IPTV واقعا استفاده می‌کند
    local src; src=$(hm_sql "SELECT COUNT(*) FROM tvheadend_sources WHERE is_active = 1" 2>/dev/null)
    local ch;  ch=$(hm_sql "SELECT COUNT(*) FROM iptv_channels WHERE source_type = 'tvheadend' AND is_active = 1" 2>/dev/null)
    out+="ثبت در برنامه: ${src:-0} منبع  ·  کانال: ${ch:-0}\n"
    [ "${ch:-0}" = "0" ] && out+="\nکانالی نیست: تیونر را وصل و «تست تیونر و اسکن» را بزنید.\n"
    show_file "TVHeadend" "$out"
}

drv_tuner_test() {
    if [ "$(dvb_count)" = "0" ]; then
        msg "هیچ تیونری شناسایی نشد.\n\nبررسی کنید:\n 1) تیونر USB وصل است؟ (دوباره بکشید و بزنید)\n 2) «تشخیص سخت‌افزار» چیزی شبیه تیونر نشان می‌دهد؟\n 3) درایور DVB نصب شده؟ (گزینهٔ ۲)\n 4) dmesg | tail — خطای firmware می‌دهد؟"
        return
    fi
    local out="" a
    for a in /dev/dvb/adapter*; do
        out+="── $a ──\n"
        if command -v dvb-fe-tool >/dev/null 2>&1; then
            out+="$(dvb-fe-tool -a "${a##*adapter}" 2>&1 | head -6 | sed 's/^/  /')\n"
        else out+="  (dvb-fe-tool نیست — درایور DVB را نصب کنید)\n"; fi
    done
    # خطای فریم‌ور: رایج‌ترین دلیل «تیونر هست ولی کار نمی‌کند»
    local fw; fw=$(dmesg 2>/dev/null | grep -iE 'firmware.*(fail|missing|not found)|dvb.*error' | tail -3)
    [ -n "$fw" ] && out+="\n⚠ خطای فریم‌ور در لاگ هسته:\n$(printf '%s\n' "$fw" | sed 's/^/  /')\n"
    out+="\nبرای اسکن کانال، از پنل TVHeadend: http://127.0.0.1:9981 (با tunnel SSH)\n→ Configuration → DVB Inputs → Networks"
    show_file "تست تیونر" "$out"
}

# ═══════════════════════════════════════════════════════════════════
#  کارهای ادمین سیستم — بدون تایپ فرمان
# ═══════════════════════════════════════════════════════════════════
do_sysadmin() {
    local choice
    if have_ui; then
        choice=$(whiptail --title "$TITLE — ابزار سیستم" --menu "کارهای رایج مدیر سرور" 22 78 10 \
          "1" "به‌روزرسانی برنامه از اینترنت" \
          "2" "به‌روزرسانی امنیتی اوبونتو (apt)" \
          "3" "تنظیم ساعت و منطقهٔ زمانی" \
          "4" "تغییر نام سرور (hostname)" \
          "5" "فضای دیسک و بزرگ‌ترین پوشه‌ها" \
          "6" "پاک‌سازی لاگ و فایل موقت" \
          "7" "دیوار آتش (ufw) — باز و بستن پورت" \
          "8" "پشتیبان‌گیری کامل (دیتابیس + رسانه)" \
          "9" "راه‌اندازی دوبارهٔ سرور" \
          "10" "خاموش‌کردن سرور" \
          "11" "بازگشت" 3>&1 1>&2 2>&3) || return
    else
        printf "1) به‌روزرسانی برنامه 2) apt 3) ساعت 4) hostname 5) دیسک 6) پاک‌سازی 7) فایروال 8) پشتیبان 9) ریبوت 10) خاموشی 11) بازگشت\n"
        read -rp "انتخاب: " choice
    fi
    case "$choice" in
      1) sys_app_update ;;
      2) sys_apt_update ;;
      3) sys_time ;;
      4) sys_hostname ;;
      5) sys_disk ;;
      6) sys_clean ;;
      7) sys_firewall ;;
      8) sys_backup_full ;;
      9) need_root && ask_yes "سرور راه‌اندازی دوباره شود؟\nتلویزیون‌ها چند دقیقه قطع می‌شوند." && { clear; systemctl reboot; } ;;
      10) need_root && ask_yes "سرور خاموش شود؟\nتا روشن‌کردن دستی، همهٔ تلویزیون‌ها قطع می‌ماند." && { clear; systemctl poweroff; } ;;
      *) return ;;
    esac
}

sys_app_update() {
    need_root || return
    local cur; cur=$(cat "$APP_DIR/VERSION" 2>/dev/null)
    local latest; latest=$(curl -s -m 12 https://api.github.com/repos/kish210/hotel-media/releases/latest 2>/dev/null | grep -oE '"tag_name": *"v?[^"]+"' | head -1 | grep -oE '[0-9]+(\.[0-9]+)+')
    [ -n "$latest" ] || { msg "نسخهٔ تازه خوانده نشد — اینترنت سرور یا GitHub در دسترس نیست."; return; }
    if [ "$cur" = "$latest" ]; then msg "برنامه به‌روز است (نسخه $cur)."; return; fi
    # مقایسهٔ عددی نه رشته‌ای: «1.9» از «1.10» بزرگ‌تر نیست
    if [ "$(printf '%s\n%s\n' "$cur" "$latest" | sort -V | tail -1)" = "$cur" ]; then
        msg "نسخهٔ نصب‌شده ($cur) از آخرین انتشار ($latest) جلوتر یا برابر است."; return
    fi
    ask_yes "نسخهٔ تازه: $latest (فعلی: $cur)\n\nپیش از به‌روزرسانی پشتیبان دیتابیس گرفته می‌شود. ادامه؟" || return
    admin_backup_quiet || { msg "پشتیبان گرفته نشد — به‌روزرسانی انجام نشد."; return; }
    clear; echo "در حال به‌روزرسانی..."
    local r
    if [ -d "$APP_DIR/.git" ]; then
        r=$(cd "$APP_DIR" && git fetch -q --tags && git checkout -q "v$latest" 2>&1 && chown -R www-data:www-data "$APP_DIR" && sudo -u www-data php artisan db:migrate 2>&1 | tail -3)
    else
        r="این نصب با git نیست؛ به‌روزرسانی را از پنل (بخش سیستم) بزنید."
    fi
    systemctl reload "php${PHP_V}-fpm" 2>/dev/null
    show_file "به‌روزرسانی" "$r\n\nنسخهٔ فعلی: $(cat "$APP_DIR/VERSION" 2>/dev/null)"
}

sys_apt_update() {
    need_root || return
    ask_yes "به‌روزرسانی امنیتی اوبونتو اجرا شود؟\n\nبعضی به‌روزرسانی‌ها نیاز به راه‌اندازی دوباره دارند؛ خودکار انجام نمی‌شود." || return
    clear; echo "در حال به‌روزرسانی فهرست و بسته‌ها..."
    # upgrade نه dist-upgrade: نسخهٔ اوبونتو یا هسته را بی‌اجازه عوض نکن
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq 2>&1 | tail -2
    local r; r=$(apt-get upgrade -y -qq 2>&1 | tail -4)
    local need=""; [ -f /var/run/reboot-required ] && need="\n\n⚠ سرور برای اعمال به‌روزرسانی باید راه‌اندازی دوباره شود."
    show_file "به‌روزرسانی اوبونتو" "${r:-همه‌چیز به‌روز بود.}$need"
}

sys_time() {
    need_root || return
    local cur; cur=$(timedatectl show -p Timezone --value 2>/dev/null)
    local tz; tz=$(ask_text "منطقهٔ زمانی (مثلا Asia/Tehran):\nساعت فعلی: $(date '+%F %T')  ($cur)" "${cur:-Asia/Tehran}") || return
    [ -n "$tz" ] || return
    # اعتبارسنجی با خود timedatectl: نام اشتباه را رد می‌کند
    timedatectl list-timezones 2>/dev/null | grep -qx "$tz" || { msg "«$tz» منطقهٔ زمانی معتبر نیست."; return; }
    timedatectl set-timezone "$tz" && msg "منطقهٔ زمانی: $tz\nساعت: $(date '+%F %T')\n\nهمگام‌سازی با اینترنت: $(timedatectl show -p NTPSynchronized --value 2>/dev/null)\nبرنامه (PHP) پس از ریلود ساعت تازه را می‌گیرد." \
        && systemctl reload "php${PHP_V}-fpm" 2>/dev/null
}

sys_hostname() {
    need_root || return
    local cur new; cur=$(hostname)
    new=$(ask_text "نام تازهٔ سرور (حروف انگلیسی، عدد و -):" "$cur") || return
    [[ "$new" =~ ^[a-zA-Z0-9][a-zA-Z0-9-]{0,62}$ ]] || { msg "نام نامعتبر: فقط حروف انگلیسی، عدد و خط تیره."; return; }
    hostnamectl set-hostname "$new" && msg "نام سرور: $new\n(برای اعمال کامل در ترمینال‌های باز، خروج و ورود دوباره)"
}

sys_disk() {
    local out; out="$(df -h / /var 2>/dev/null | awk 'NR==1 || !seen[$1]++')"
    out+="\n\n── بزرگ‌ترین پوشه‌ها ──\n"
    out+="$(du -xsh "$APP_DIR/public/uploads" /var/log /var/lib/mysql /var/backups /srv 2>/dev/null | sort -rh | head -8)"
    local pct; pct=$(df / | awk 'NR==2 {gsub("%",""); print $5}')
    [ "${pct:-0}" -ge 90 ] && out+="\n\n⚠ دیسک بیش از ۹۰٪ پر است — ضبط و ویدیو در خطرند."
    show_file "فضای دیسک" "$out"
}

sys_clean() {
    need_root || return
    local before after
    before=$(df --output=used / | tail -1)
    ask_yes "این‌ها پاک می‌شود:\n • لاگ‌های قدیمی‌تر از ۱۴ روز\n • کش apt\n • فایل موقت قدیمی\n\nرسانه، دیتابیس و پشتیبان دست نمی‌خورد. ادامه؟" || return
    journalctl --vacuum-time=14d >/dev/null 2>&1
    apt-get clean 2>/dev/null
    find /tmp -xdev -type f -atime +3 -delete 2>/dev/null
    (cd "$APP_DIR" && sudo -u www-data php artisan storage:clean 2>&1 | tail -2) >/dev/null
    after=$(df --output=used / | tail -1)
    msg "پاک‌سازی تمام شد.\n\nآزاد شد: $(( (before-after)/1024 )) مگابایت"
}

sys_firewall() {
    need_root || return
    command -v ufw >/dev/null || { msg "ufw نصب نیست.\nنصب: sudo apt install ufw"; return; }
    local st; st=$(ufw status 2>/dev/null | head -1)
    local choice
    have_ui && choice=$(whiptail --title "$TITLE — فایروال" --menu "$st" 17 72 5 \
        "1" "نمایش قواعد" \
        "2" "باز کردن پورت" \
        "3" "بستن پورت" \
        "4" "روشن‌کردن (با باز ماندن SSH و وب)" \
        "5" "بازگشت" 3>&1 1>&2 2>&3) || return
    case "$choice" in
      1) show_file "قواعد ufw" "$(ufw status numbered 2>&1)" ;;
      2) local p; p=$(ask_text "شمارهٔ پورت (مثلا 8080):" "") || return
         [[ "$p" =~ ^[0-9]{1,5}$ ]] && [ "$p" -le 65535 ] || { msg "پورت نامعتبر."; return; }
         ufw allow "$p"/tcp >/dev/null && msg "پورت $p باز شد." ;;
      3) local p; p=$(ask_text "شمارهٔ پورت برای بستن:" "") || return
         [[ "$p" =~ ^[0-9]{1,5}$ ]] || { msg "پورت نامعتبر."; return; }
         # SSH را نبند: اپراتور دسترسی راه دور خودش را از دست می‌دهد
         [ "$p" = "22" ] && ! ask_yes "پورت ۲۲ (SSH) بسته می‌شود. اگر از راه دور وصل باشید دیگر وارد نمی‌شوید. مطمئنید؟" && return
         ufw delete allow "$p"/tcp >/dev/null 2>&1; msg "قاعدهٔ پورت $p حذف شد." ;;
      4) # قبل از روشن‌کردن، راه‌های ورود را باز کن: ufw روشن بی SSH یعنی قفل‌شدن بیرون
         ufw allow 22/tcp >/dev/null; ufw allow 80/tcp >/dev/null; ufw allow 443/tcp >/dev/null
         ufw --force enable >/dev/null && msg "فایروال روشن شد.\nباز: 22 (SSH) · 80 · 443" ;;
    esac
}

admin_backup_quiet() {
    local dir=/var/backups/hotel-media f
    mkdir -p "$dir"; f="$dir/db-$(date +%Y%m%d-%H%M%S).sql.gz"
    local dbu dbp dbn; dbu=$(env_val DB_USERNAME); dbp=$(env_val DB_PASSWORD); dbn=$(env_val DB_DATABASE)
    MYSQL_PWD="$dbp" mysqldump -u"$dbu" --single-transaction --quick "$dbn" 2>/dev/null | gzip > "$f" \
        && [ -s "$f" ] && [ "$(gzip -t "$f" 2>&1 | wc -l)" = "0" ]
}

sys_backup_full() {
    need_root || return
    local free_gb used_gb
    used_gb=$(du -sBG "$APP_DIR/public/uploads" 2>/dev/null | cut -dG -f1)
    free_gb=$(df -BG --output=avail /var/backups 2>/dev/null | tail -1 | tr -dc '0-9')
    # پشتیبان بزرگ روی دیسک پر، خودش سرور را می‌خواباند
    if [ "${free_gb:-0}" -le "${used_gb:-0}" ]; then
        msg "فضای کافی نیست.\n\nرسانه: ${used_gb:-?}GB — فضای آزاد: ${free_gb:-?}GB\n\nپشتیبان روی دیسک دیگر یا USB بگیرید."
        return
    fi
    ask_yes "پشتیبان کامل: دیتابیس + رسانه (${used_gb:-?}GB).\nمدتی طول می‌کشد. ادامه؟" || return
    clear; echo "در حال پشتیبان‌گیری..."
    local dir=/var/backups/hotel-media st; st=$(date +%Y%m%d-%H%M)
    admin_backup_quiet || { msg "پشتیبان دیتابیس ناموفق بود."; return; }
    tar -C "$APP_DIR/public" -czf "$dir/uploads-$st.tar.gz" uploads 2>/dev/null
    msg "پشتیبان کامل گرفته شد:\n\n$(ls -lh "$dir" | tail -3 | awk '{print "  "$5"  "$9}')"
}

# ═══════════════════════════════════════════════════════════════════
#  منوی اصلی
# ═══════════════════════════════════════════════════════════════════
main_menu() {
    while true; do
        local ip4 ver choice
        ip4=$(ip -4 -br addr show "$(main_nic)" 2>/dev/null | awk '{print $3}')
        ver=$(cat "$APP_DIR/VERSION" 2>/dev/null || echo "?")

        # ترتیب بر اساس «چه می‌خواهم» است، نه فهرست فنی: اپراتور هتل
        # لینوکس‌کار نیست. مشکل‌داشتن (تعمیر) و رمز بالاترند چون رایج‌ترین
        # دلیل آمدن به کنسول‌اند.
        if have_ui; then
            choice=$(whiptail --title "$TITLE" --menu "\
نسخه $ver   ·   http://${ip4%%/*}/login

سماع رایانه کیش — kishwifi.com" 24 78 12 \
              "1"  "🔧 چیزی کار نمی‌کند — تعمیر خودکار" \
              "2"  "📊 وضعیت سرور" \
              "3"  "🌐 شبکه و تنظیم IP" \
              "4"  "🔑 تغییر رمز کاربر پنل" \
              "5"  "📡 درایور و تیونر (TVHeadend)" \
              "6"  "🔍 عیب‌یابی و تست" \
              "7"  "📜 لاگ‌ها" \
              "8"  "🔄 سرویس‌ها (راه‌اندازی دوباره)" \
              "9"  "🛠 ابزار سیستم (به‌روزرسانی، پشتیبان، ساعت، فایروال)" \
              "10" "📦 برنامه (نشانی پنل، migration، پشتیبان دیتابیس)" \
              "11" "خروج به خط‌فرمان" 3>&1 1>&2 2>&3) || break
        else
            clear
            printf "\n  Hotel Media — کنسول مدیریت   (نسخه %s)\n" "$ver"
            printf "  پنل: http://%s/login\n\n" "${ip4%%/*}"
            printf "   1) تعمیر خودکار       2) وضعیت        3) شبکه و IP     4) رمز کاربر\n"
            printf "   5) درایور و تیونر     6) عیب‌یابی     7) لاگ‌ها        8) سرویس‌ها\n"
            printf "   9) ابزار سیستم       10) برنامه      11) خروج\n\n"
            read -rp "  انتخاب: " choice
        fi

        case "$choice" in
          1)  do_repair   ;;
          2)  do_status   ;;
          3)  do_network  ;;
          4)  admin_passwd ;;
          5)  do_drivers  ;;
          6)  do_diag     ;;
          7)  do_logs     ;;
          8)  do_services ;;
          9)  do_sysadmin ;;
          10) do_admin    ;;
          11|"") break    ;;
        esac
    done
    clear
}

# ── حالت غیرتعاملی، برای اسکریپت و SSH ────────────────────────────
case "${1:-}" in
  status)  NO_WAIT=1; HM_PLAIN=1; do_status ;;
  diag)    NO_WAIT=1; HM_PLAIN=1; diag_full ;;
  # بی تایید: برای اسکریپت، SSH و آزمون خودکار. تعمیر به دادهٔ هتل دست
  # نمی‌زند (فقط سرویس، مجوز پوشه، migration)، پس بی‌پرسش اجرا می‌شود.
  repair)  NO_WAIT=1; HM_PLAIN=1; ask_yes() { return 0; }; do_repair ;;
  detect)  NO_WAIT=1; HM_PLAIN=1; drv_detect ;;
  tuner)   NO_WAIT=1; HM_PLAIN=1; drv_tuner_test ;;
  tvh)     NO_WAIT=1; HM_PLAIN=1; drv_tvh_status ;;
  ip)      ip -4 -br addr show "$(main_nic)" | awk '{print $3}' ;;
  help|-h|--help)
    cat <<HELP
Hotel Media — کنسول مدیریت سرور

  hotel-media           منوی تعاملی
  hotel-media status    وضعیت سرور
  hotel-media diag      بررسی کامل سیستم
  hotel-media ip        نشانی IP فعلی
HELP
    ;;
  *) main_menu ;;
esac
