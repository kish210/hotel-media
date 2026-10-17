#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════
#  Hotel Media — نصب درایورهای ترنسکدر و کارت کپچر (اجرا با root)
# ══════════════════════════════════════════════════════════════════
#
#  نصب‌کننده این فایل را در /usr/local/sbin/hotel-media-driver می‌گذارد
#  و در /etc/sudoers.d/hotel-media-driver فقط همین یک فایل را برای
#  www-data بدون رمز مجاز می‌کند. پس این اسکریپت مرز امنیتی است:
#  پنل هر چیزی بفرستد، اینجا فقط این‌ها اجرا می‌شود —
#
#    check                       → «ok» (پنل می‌فهمد sudo تنظیم شده)
#    online <id>                 → apt با فهرست بسته‌ی ثابت همان id
#    file   <id> <path>          → نصب فایل بارگذاری‌شده، فقط از پوشه‌ی incoming
#
#  هیچ آرگومانی مستقیم به apt یا shell نمی‌رسد؛ id از یک فهرست ثابت
#  انتخاب می‌شود و مسیر فایل با realpath داخل incoming بررسی می‌شود.
#
#  HM_DRIVER_DRYRUN=1 فقط فرمان‌ها را چاپ می‌کند (برای تست).
# ══════════════════════════════════════════════════════════════════
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

APP_DIR="${HM_APP_DIR_OVERRIDE:-__APP_DIR__}"
INCOMING="$APP_DIR/storage/drivers/incoming"
# قفل در /run/lock نیست، در پوشهٔ مخصوص root است.
#
# `/run/lock` برای همه نوشتنی و sticky است، و اوبونتو
# `fs.protected_regular=2` دارد: در چنین پوشه‌ای **حتی root** نمی‌تواند
# فایلی را که مالکش کس دیگری است با `>` باز کند. اگر هر کاربر غیر root
# (مثلا یک آزمایش با `sudo -u www-data` بی‌گذار از sudo) فایل قفل را یک
# بار بسازد، از آن به بعد هر اجرای helper با «Permission denied» و پیام
# گمراه‌کنندهٔ «قفل ساخته نشد» می‌میرد — و صفحهٔ درایور پنل برای همیشه
# خراب می‌ماند. روی سرور دوم همین رخ داد (INSTALL-ISSUES.md، ردیف ۱۸).
# در /run/hotel-media که فقط root می‌سازد، کس دیگری نمی‌تواند مالک شود.
LOCKDIR=/run/hotel-media
# حالت آزمایشی (DRYRUN) هیچ‌چیز را نصب نمی‌کند و بدون root هم اجرا می‌شود
# (DriverTest همین‌طور آن را صدا می‌زند). /run را فقط root می‌نویسد، پس
# آنجا قفل را در پوشهٔ موقت کاربر می‌گذاریم؛ در حالت واقعی همیشه با sudo
# و root است و همان /run/hotel-media بالا می‌ماند.
if [[ "${HM_DRIVER_DRYRUN:-0}" == "1" && "$(id -u)" != "0" ]]; then
    LOCKDIR="${TMPDIR:-/tmp}/hm-driver-$(id -u)"
fi
mkdir -p "$LOCKDIR" && chmod 0700 "$LOCKDIR"
LOCK="$LOCKDIR/driver.lock"

die() { echo "خطا: $*" >&2; exit 2; }
run() {
    echo "+ $*"
    if [[ "${HM_DRIVER_DRYRUN:-0}" == "1" ]]; then return 0; fi
    "$@"
}

# دو نصب هم‌زمان apt را قفل می‌کنند و یکی با خطای مبهم می‌افتد
exec 9>"$LOCK" || die "قفل ساخته نشد"
flock -n 9 || die "یک نصب دیگر در حال اجراست — چند دقیقه بعد دوباره امتحان کنید"

packages_for() {
    case "$1" in
        ffmpeg)    echo "ffmpeg" ;;
        intel)     echo "intel-media-va-driver-non-free vainfo" ;;
        amd)       echo "mesa-va-drivers vainfo" ;;
        v4l)       echo "v4l-utils" ;;
        # تیونر DVB (ماهواره/زمینی/کابلی) برای TVHeadend. ماژول هسته
        # (حدود ۱۲۰ فرانت‌اند) از قبل هست؛ آنچه معمولا کم است فریم‌ور خودِ
        # تیونر (linux-firmware) و ابزار اسکن است.
        dvb)       echo "dvb-tools w-scan-cpp linux-firmware v4l-utils" ;;
        *)         echo "" ;;
    esac
}

apt_install() {
    run apt-get update -q || echo "هشدار: به‌روزرسانی فهرست بسته‌ها کامل نشد — با فهرست موجود ادامه می‌دهیم"

    # فقط بسته‌هایی که در مخزن همین نسخهٔ اوبونتو هستند.
    #
    # یک بستهٔ ناموجود کل `apt-get install` را با کد ۱۰۰ می‌کشد و حتی
    # بسته‌های سالم را هم نصب نمی‌کند. دقیقا همین بود که `php8.5-opcache`
    # (که در 26.04 وجود ندارد) کل نصب‌کنندهٔ ما را زمین زد. نام بسته‌ها بین
    # نسخه‌ها عوض می‌شود (مثلا w-scan ↔ w-scan-cpp)، پس ناموجود را
    # **نام می‌بریم** و بقیه را نصب می‌کنیم، نه اینکه همه‌چیز را از دست بدهیم.
    local want="" skipped="" p cand
    for p in $1; do
        cand=$(apt-cache policy "$p" 2>/dev/null | awk '/Candidate:/ {print $2}')
        if [[ -n "$cand" && "$cand" != "(none)" ]]; then want="$want $p"; else skipped="$skipped $p"; fi
    done
    [[ -z "$skipped" ]] || echo "رد شد (در مخزن این نسخه نیست):$skipped"
    [[ -n "$want" ]]   || die "هیچ‌کدام از بسته‌ها در مخزن این نسخهٔ اوبونتو نیست: $1"

    # shellcheck disable=SC2086
    run apt-get install -y -q $want
}

cmd="${1:-}"; id="${2:-}"

case "$cmd" in
    check)
        echo ok; exit 0 ;;

    online)
        case "$id" in
            ffmpeg|intel|amd|v4l|dvb)
                apt_install "$(packages_for "$id")" ;;
            nvidia)
                # درایور پیشنهادی همان کارتی که روی سرور هست؛ نسخه‌ی ثابت
                # ممکن است با کارت یا کرنل جور نباشد
                if command -v ubuntu-drivers >/dev/null 2>&1; then
                    run ubuntu-drivers install
                else
                    apt_install "ubuntu-drivers-common"
                    run ubuntu-drivers install
                fi
                echo "برای فعال شدن درایور NVIDIA سرور باید یک بار راه‌اندازی دوباره شود." ;;
            decklink)
                die "درایور DeckLink (Desktop Video) در مخزن‌ها نیست و Blackmagic دانلودش را پشت ثبت‌نام گذاشته — فایل را از blackmagicdesign.com بگیرید و در همین صفحه بارگذاری کنید" ;;
            *)
                die "درایور ناشناخته: $id" ;;
        esac ;;

    file)
        path="${3:-}"
        [[ -n "$path" ]] || die "مسیر فایل داده نشده"
        case "$id" in ffmpeg|intel|amd|v4l|dvb|nvidia|decklink) ;; *) die "درایور ناشناخته: $id" ;; esac

        # فقط فایل معمولی داخل incoming — نه symlink، نه مسیر با ..
        [[ -f "$path" && ! -L "$path" ]] || die "فایل پیدا نشد"
        real="$(realpath -e "$path")"
        inc="$(realpath -e "$INCOMING")"
        [[ "$real" == "$inc/"* ]] || die "فایل باید از پوشه‌ی بارگذاری سامانه باشد"

        case "$real" in
            *.deb)
                # apt وابستگی‌ها را از مخزن (اگر در دسترس باشد) می‌گیرد؛
                # سرور بی‌اینترنت فقط با بسته‌ای که وابستگی کم دارد موفق است
                run apt-get install -y -q "$real" ;;
            *.tar.gz|*.tgz)
                tmp="$(mktemp -d)"
                trap 'rm -rf "$tmp"' EXIT
                run tar -xzf "$real" -C "$tmp" --no-same-owner --no-same-permissions
                mapfile -t debs < <(find "$tmp" -type f -name '*.deb' | sort)
                [[ ${#debs[@]} -gt 0 ]] || die "داخل این آرشیو فایل .deb نیست"
                run apt-get install -y -q "${debs[@]}" ;;
            *.run)
                [[ "$id" == "nvidia" ]] || die "فایل .run فقط برای درایور NVIDIA پذیرفته می‌شود"
                run apt-get install -y -q build-essential dkms "linux-headers-$(uname -r)" || \
                    echo "هشدار: ابزار ساخت نصب نشد — اگر سرور اینترنت ندارد، نصب‌کننده‌ی NVIDIA ممکن است شکست بخورد"
                run sh "$real" --silent --dkms --no-questions
                echo "برای فعال شدن درایور NVIDIA سرور باید یک بار راه‌اندازی دوباره شود." ;;
            *)
                die "نوع فایل پشتیبانی نمی‌شود — .deb، .tar.gz یا (برای NVIDIA) .run" ;;
        esac ;;

    *)
        die "فرمان نامعتبر — check | online <id> | file <id> <path>" ;;
esac

echo "✓ انجام شد"
