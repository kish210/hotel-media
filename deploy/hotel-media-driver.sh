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
LOCK=/var/lock/hotel-media-driver.lock

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
        *)         echo "" ;;
    esac
}

apt_install() {
    run apt-get update -q || echo "هشدار: به‌روزرسانی فهرست بسته‌ها کامل نشد — با فهرست موجود ادامه می‌دهیم"
    # shellcheck disable=SC2086
    run apt-get install -y -q $1
}

cmd="${1:-}"; id="${2:-}"

case "$cmd" in
    check)
        echo ok; exit 0 ;;

    online)
        case "$id" in
            ffmpeg|intel|amd|v4l)
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
        case "$id" in ffmpeg|intel|amd|v4l|nvidia|decklink) ;; *) die "درایور ناشناخته: $id" ;; esac

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
