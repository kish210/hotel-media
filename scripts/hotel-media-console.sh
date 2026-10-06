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
PHP_V=8.3
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

admin_passwd() {
    need_root || return
    local mail pass dbu dbp dbn hash
    mail=$(ask_text "ایمیل کاربری که رمزش عوض شود:" "$(env_val ADMIN_EMAIL)") || return
    [ -z "$mail" ] && return
    pass=$(ask_text "رمز تازه (حداقل ۸ نویسه):" "") || return
    [ ${#pass} -lt 8 ] && { msg "رمز کوتاه است."; return; }

    dbu=$(env_val DB_USERNAME); dbp=$(env_val DB_PASSWORD); dbn=$(env_val DB_DATABASE)
    # هش با همان تابع خودِ برنامه ساخته می‌شود تا با منطق ورود یکی
    # باشد؛ هش دست‌ساز با الگوریتم متفاوت، کاربر را بی‌صدا قفل می‌کند.
    hash=$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT, ["cost"=>12]);' "$pass" 2>/dev/null)
    [ -z "$hash" ] && { msg "ساخت هش رمز ناموفق بود."; return; }

    if mysql -u"$dbu" -p"$dbp" "$dbn" -e \
        "UPDATE users SET password='$hash' WHERE email='$mail'" 2>/dev/null; then
        local n; n=$(mysql -u"$dbu" -p"$dbp" -N -B -e "SELECT COUNT(*) FROM users WHERE email='$mail'" "$dbn" 2>/dev/null)
        [ "${n:-0}" -gt 0 ] && msg "رمز «$mail» عوض شد." || msg "کاربری با ایمیل «$mail» پیدا نشد."
    else
        msg "به‌روزرسانی رمز ناموفق بود."
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
#  منوی اصلی
# ═══════════════════════════════════════════════════════════════════
main_menu() {
    while true; do
        local ip4 ver choice
        ip4=$(ip -4 -br addr show "$(main_nic)" 2>/dev/null | awk '{print $3}')
        ver=$(cat "$APP_DIR/VERSION" 2>/dev/null || echo "?")

        if have_ui; then
            choice=$(whiptail --title "$TITLE" --menu "\
نسخه $ver   ·   http://${ip4%%/*}/login

سماع رایانه کیش — kishwifi.com" 21 76 7 \
              "1" "وضعیت سرور" \
              "2" "شبکه و تنظیم IP" \
              "3" "لاگ‌ها" \
              "4" "عیب‌یابی و تست" \
              "5" "سرویس‌ها (راه‌اندازی دوباره)" \
              "6" "مدیریت برنامه" \
              "7" "خروج به خط‌فرمان" 3>&1 1>&2 2>&3) || break
        else
            clear
            printf "\n  Hotel Media — کنسول مدیریت   (نسخه %s)\n" "$ver"
            printf "  پنل: http://%s/login\n\n" "${ip4%%/*}"
            printf "  1) وضعیت سرور\n  2) شبکه و IP\n  3) لاگ‌ها\n  4) عیب‌یابی\n"
            printf "  5) سرویس‌ها\n  6) مدیریت برنامه\n  7) خروج\n\n"
            read -rp "  انتخاب: " choice
        fi

        case "$choice" in
          1) do_status   ;;
          2) do_network  ;;
          3) do_logs     ;;
          4) do_diag     ;;
          5) do_services ;;
          6) do_admin    ;;
          7|"") break    ;;
        esac
    done
    clear
}

# ── حالت غیرتعاملی، برای اسکریپت و SSH ────────────────────────────
case "${1:-}" in
  status)  NO_WAIT=1; HM_PLAIN=1; do_status ;;
  diag)    NO_WAIT=1; HM_PLAIN=1; diag_full ;;
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
