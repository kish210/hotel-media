#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════
#  Hotel Media — ساخت ISO سفارشی Ubuntu Server
#  سماع رایانه کیش | kishwifi.com
#
#  خروجی: یک ISO قابل بوت که سرور را بدون هیچ سؤالی نصب می‌کند و در
#  پایان Hotel Media آماده و در حال اجراست.
#
#  اجرا روی Ubuntu یا Debian (یا WSL):
#      sudo bash deploy/iso/build-iso.sh
#
#  گزینه‌ها:
#      UBUNTU_VERSION=24.04.1   نسخه‌ی پایه
#      OUT_DIR=/tmp/hm-iso      پوشه‌ی کار و خروجی
#      OFFLINE=1                بسته‌ها هم داخل ISO بسته‌بندی شوند
# ══════════════════════════════════════════════════════════════════════
set -euo pipefail

UBUNTU_VERSION="${UBUNTU_VERSION:-24.04.1}"
ARCH="${ARCH:-amd64}"
OUT_DIR="${OUT_DIR:-/tmp/hotel-media-iso}"
OFFLINE="${OFFLINE:-0}"

BASE_ISO_URL="https://releases.ubuntu.com/${UBUNTU_VERSION%.*}/ubuntu-${UBUNTU_VERSION}-live-server-${ARCH}.iso"
BASE_ISO="${OUT_DIR}/ubuntu-${UBUNTU_VERSION}-live-server-${ARCH}.iso"
WORK="${OUT_DIR}/work"
OUT_ISO="${OUT_DIR}/HotelMedia-${UBUNTU_VERSION}-${ARCH}.iso"

G='\033[0;32m'; Y='\033[1;33m'; R='\033[0;31m'; B='\033[0;34m'; N='\033[0m'
ok()   { echo -e "  ${G}✔${N} $1"; }
info() { echo -e "  ${B}→${N} $1"; }
warn() { echo -e "  ${Y}!${N} $1"; }
die()  { echo -e "  ${R}✘ $1${N}"; exit 1; }

[[ $EUID -eq 0 ]] || die "با sudo اجرا کنید"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

echo -e "${B}"
echo "  ╔════════════════════════════════════════════════════╗"
echo "  ║   Hotel Media — ساخت ISO سفارشی Ubuntu             ║"
echo "  ╚════════════════════════════════════════════════════╝"
echo -e "${N}"
info "پایه   : Ubuntu ${UBUNTU_VERSION} ${ARCH}"
info "مخزن   : ${REPO_ROOT}"
info "خروجی  : ${OUT_ISO}"
[[ "$OFFLINE" == "1" ]] && info "حالت   : آفلاین (بسته‌ها داخل ISO)"
echo ""

# ── ۱) ابزارها ───────────────────────────────────────────────────────
echo -e "${G}[1/6] ابزارهای ساخت${N}"

MISSING=()
for t in xorriso rsync curl; do
    command -v "$t" >/dev/null 2>&1 || MISSING+=("$t")
done

if ((${#MISSING[@]})); then
    info "نصب: ${MISSING[*]}"
    export DEBIAN_FRONTEND=noninteractive
    if apt-get update -qq 2>/dev/null; then
        # ‏p7zip اختیاری است — xorriso خودش هم می‌تواند استخراج کند
        apt-get install -y -qq xorriso rsync curl p7zip-full isolinux >/dev/null 2>&1 || \
            apt-get install -y -qq xorriso rsync curl >/dev/null 2>&1 || true
    fi
fi

for t in xorriso rsync curl; do
    command -v "$t" >/dev/null 2>&1 || die "ابزار لازم نصب نیست: $t"
done

# ‏7z اگر باشد سریع‌تر است، ولی xorriso هم استخراج می‌کند و هیچ‌کدام به
# ‏loop mount نیاز ندارند — پس داخل WSL و کانتینر هم کار می‌کند.
if command -v 7z >/dev/null 2>&1; then
    EXTRACTOR=7z
else
    EXTRACTOR=xorriso
fi
ok "xorriso · rsync · curl  (استخراج با ${EXTRACTOR})"

# ── ۲) ISO پایه ──────────────────────────────────────────────────────
echo -e "\n${G}[2/6] ISO پایه‌ی Ubuntu${N}"
mkdir -p "$OUT_DIR"

if [[ -f "$BASE_ISO" ]]; then
    ok "از قبل دانلود شده ($(du -h "$BASE_ISO" | cut -f1))"
else
    info "دانلود ${BASE_ISO_URL}"
    curl -fL --progress-bar -o "${BASE_ISO}.part" "$BASE_ISO_URL" \
        || die "دانلود ناموفق — نسخه را بررسی کنید: UBUNTU_VERSION=$UBUNTU_VERSION"
    mv "${BASE_ISO}.part" "$BASE_ISO"
    ok "دانلود شد ($(du -h "$BASE_ISO" | cut -f1))"
fi

# ── ۳) استخراج ───────────────────────────────────────────────────────
echo -e "\n${G}[3/6] استخراج ISO${N}"
rm -rf "$WORK"
mkdir -p "$WORK"

if [[ "$EXTRACTOR" == "7z" ]]; then
    7z x -o"$WORK" "$BASE_ISO" -bso0 -bsp0 >/dev/null || die "استخراج ناموفق"
    # ‏7z پوشه‌ی مجازی [BOOT] می‌سازد که جزو محتوای ISO نیست
    rm -rf "$WORK/[BOOT]"
else
    # ‏xorriso فایل‌های بوت را هم مثل فایل معمولی بیرون می‌کشد
    xorriso -osirrox on -indev "$BASE_ISO" -extract / "$WORK" >/dev/null 2>&1 \
        || die "استخراج با xorriso ناموفق"
fi

chmod -R u+w "$WORK"

[[ -d "$WORK/casper" ]] || die "ساختار ISO شناخته نشد — آیا ISO سرور اوبونتو است؟"
ok "استخراج شد ($(du -sh "$WORK" | cut -f1))"

# ── ۴) تزریق برنامه و autoinstall ────────────────────────────────────
echo -e "\n${G}[4/6] افزودن Hotel Media${N}"

# برنامه
mkdir -p "$WORK/hotel-media"
# ‏.env حتما باید بیرون بماند: ISO دست هتل‌های مختلف می‌گردد و .env توسعه
# رمز دیتابیس و APP_KEY و JWT_SECRET دارد. firstboot.sh از .env.example
# یک .env تازه با رازهای تصادفیِ همان سرور می‌سازد.
rsync -a \
    --exclude='.env' --exclude='.env.*' \
    --exclude='.git' --exclude='.github' --exclude='.claude' \
    --exclude='node_modules' --exclude='vendor' \
    --exclude='storage/logs/*' --exclude='storage/sessions/*' \
    --exclude='storage/cache/*' --exclude='public/uploads/media/*' \
    --exclude='*.iso' --exclude='dist' \
    --exclude='*.bat' --exclude='*.ps1' --exclude='android' \
    --exclude='tests' --exclude='public/__*.html' \
    "$REPO_ROOT/" "$WORK/hotel-media/"

# اگر .env با وجود استثنا جایی جا مانده بود، ساخت را متوقف کن — این
# نوع نشتی را نباید با هشدار رد کرد.
if [[ -e "$WORK/hotel-media/.env" ]]; then
    die ".env داخل محتوای ISO است — ساخت متوقف شد"
fi
cp -f "$REPO_ROOT/.env.example" "$WORK/hotel-media/.env.example"
ok "فایل‌های برنامه ($(du -sh "$WORK/hotel-media" | cut -f1))"

# وابستگی‌های composer از قبل — سرور هتل ممکن است به packagist نرسد
if command -v composer >/dev/null 2>&1; then
    info "نصب وابستگی‌های PHP داخل ISO"
    (cd "$WORK/hotel-media" && composer install --no-dev --optimize-autoloader --quiet --no-interaction) \
        && ok "vendor/ بسته‌بندی شد" \
        || warn "composer ناموفق بود — در اولین بوت تلاش می‌شود"
else
    warn "composer روی این سیستم نیست — vendor/ در اولین بوت نصب می‌شود"
fi

# autoinstall
mkdir -p "$WORK/server"
cp "$REPO_ROOT/deploy/iso/autoinstall/user-data" "$WORK/server/user-data"
: > "$WORK/server/meta-data"
ok "پیکربندی autoinstall"

# ── بسته‌های آفلاین ──────────────────────────────────────────────────
if [[ "$OFFLINE" == "1" ]]; then
    info "دانلود بسته‌های .deb برای نصب آفلاین (چند دقیقه)"
    POOL="$WORK/hotel-media/offline-pool"
    mkdir -p "$POOL"

    # فقط دانلود، بدون نصب روی سیستم ساخت
    ( cd "$POOL" && apt-get download \
        nginx mariadb-server ffmpeg udpxy \
        php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml \
        php8.4-curl php8.4-gd php8.4-zip php8.4-intl php8.4-opcache php8.4-bcmath \
        >/dev/null 2>&1 ) || warn "بعضی بسته‌ها دانلود نشدند"

    COUNT=$(find "$POOL" -name '*.deb' | wc -l)
    if (( COUNT > 0 )); then
        ok "${COUNT} بسته بسته‌بندی شد ($(du -sh "$POOL" | cut -f1))"
    else
        warn "هیچ بسته‌ای دانلود نشد — ISO به اینترنت نیاز خواهد داشت"
    fi
fi

# ── ۵) بوت خودکار ────────────────────────────────────────────────────
echo -e "\n${G}[5/6] تنظیم منوی بوت${N}"

# GRUB (بوت UEFI و اکثر سیستم‌های جدید)
GRUB_CFG="$WORK/boot/grub/grub.cfg"
if [[ -f "$GRUB_CFG" ]]; then
    cat > "$GRUB_CFG" <<'GRUB'
set timeout=10
set default=0

menuentry "Install Hotel Media Server (automatic)" {
    set gfxpayload=keep
    linux  /casper/vmlinuz autoinstall ds=nocloud\;s=/cdrom/server/ quiet ---
    initrd /casper/initrd
}

menuentry "Install Ubuntu Server (manual)" {
    set gfxpayload=keep
    linux  /casper/vmlinuz quiet ---
    initrd /casper/initrd
}

menuentry "Check disc for defects" {
    linux  /casper/vmlinuz integrity-check quiet ---
    initrd /casper/initrd
}
GRUB
    ok "منوی GRUB"
else
    warn "grub.cfg پیدا نشد — ساختار ISO تغییر کرده است"
fi

# ‏isolinux برای سیستم‌های قدیمی BIOS — روی ISO های جدید همیشه نیست
ISOLINUX="$WORK/isolinux/txt.cfg"
if [[ -f "$ISOLINUX" ]]; then
    cat > "$ISOLINUX" <<'ISOL'
default hotelmedia
label hotelmedia
  menu label ^Install Hotel Media Server (automatic)
  kernel /casper/vmlinuz
  append initrd=/casper/initrd autoinstall ds=nocloud;s=/cdrom/server/ quiet ---
ISOL
    ok "منوی isolinux (BIOS قدیمی)"
fi

# ── ۶) ساخت ISO ──────────────────────────────────────────────────────
echo -e "\n${G}[6/6] ساخت ISO${N}"

# ‏md5sum اصلی دیگر معتبر نیست چون فایل اضافه کردیم؛ اگر بماند،
# بررسی یکپارچگی هنگام بوت شکست می‌خورد.
rm -f "$WORK/md5sum.txt"

cd "$WORK"

rm -f "$OUT_ISO"

# روش مطمئن: ساختار بوت را از خود ISO پایه کپی می‌کنیم.
# ‏-boot_image any replay یعنی «هرچه ISO اصلی برای بوت داشت، همان را
# بساز» — بدون اینکه لازم باشد نام و مسیر تصاویر BIOS و UEFI را حدس
# بزنیم، که بین نسخه‌های اوبونتو عوض می‌شود و بوت را خاموش خراب می‌کند.
if xorriso -indev "$BASE_ISO" \
           -outdev "$OUT_ISO" \
           -map "$WORK" / \
           -boot_image any replay \
           -volid "HOTEL_MEDIA" \
           -padding 0 >/dev/null 2>&1; then
    ok "ساختار بوت از ISO پایه حفظ شد"
else
    warn "replay ناموفق — تلاش با پارامترهای صریح"

    XORRISO_ARGS=(-as mkisofs -r -V "HOTEL_MEDIA" -J -joliet-long -o "$OUT_ISO")

    [[ -f "boot/grub/i386-pc/eltorito.img" ]] && XORRISO_ARGS+=(
        -b boot/grub/i386-pc/eltorito.img -c boot.catalog
        -no-emul-boot -boot-load-size 4 -boot-info-table
    )

    EFI_IMG="$(find . -name 'efi.img' 2>/dev/null | head -1 | sed 's|^\./||')"
    [[ -n "$EFI_IMG" ]] && XORRISO_ARGS+=(-eltorito-alt-boot -e "$EFI_IMG" -no-emul-boot)

    XORRISO_ARGS+=(-isohybrid-gpt-basdat .)

    xorriso "${XORRISO_ARGS[@]}" 2>&1 | tail -5 || die "ساخت ISO ناموفق بود"
fi

[[ -f "$OUT_ISO" ]] || die "فایل خروجی ساخته نشد"

SIZE="$(du -h "$OUT_ISO" | cut -f1)"
sha256sum "$OUT_ISO" > "${OUT_ISO}.sha256"

echo ""
echo -e "${G}╔══════════════════════════════════════════════════════════╗${N}"
echo -e "${G}║                  ISO آماده است                           ║${N}"
echo -e "${G}╚══════════════════════════════════════════════════════════╝${N}"
echo ""
echo -e "  ${B}فایل${N}      ${OUT_ISO}"
echo -e "  ${B}حجم${N}       ${SIZE}"
echo -e "  ${B}checksum${N}  ${OUT_ISO}.sha256"
echo ""
echo -e "  ${B}ساخت USB بوتیبل${N}"
echo -e "    Linux    sudo dd if=${OUT_ISO} of=/dev/sdX bs=4M status=progress conv=fsync"
echo -e "    Windows  با Rufus یا balenaEtcher"
echo ""
echo -e "  ${Y}هشدار:${N} گزینه‌ی اول بوت، کل دیسک سرور را پاک می‌کند."
echo -e "  ${B}ورود پیش‌فرض${N}  کاربر hotel / HotelMedia@2026"
echo ""
