#!/bin/bash
# ═══════════════════════════════════════════════════════════════════
#  ساخت ISO نصب خودکار Hotel Media (روی Ubuntu Server)
#  سماع رایانه کیش · kishwifi.com
# ═══════════════════════════════════════════════════════════════════
#
#  خروجی: یک ISO بوت‌شو که روی سرور تازه، اوبونتو را **بی هیچ سوالی**
#  نصب می‌کند و بعد خودِ Hotel Media را بالا می‌آورد. یک USB بسازید،
#  بزنید به سرور، و برگردید سراغ کار دیگر.
#
#  اجرا (روی یک ماشین اوبونتو، مثلا همین سرور):
#
#      sudo bash scripts/build-iso.sh
#
#  ───────────────────────────────────────────────────────────────────
#  چرا روش `replay`:
#  دستورالعمل رایج برای بازبسته‌بندی ISO اوبونتو، تصاویر بوت را بیرون
#  می‌کشد و بعد با offset و اندازهٔ پارتیشن دستی (`-append_partition`،
#  `--interval:appended_partition_2_start_...`) دوباره می‌سازد. آن
#  اعداد برای هر نسخهٔ ISO فرق می‌کنند و یک رقم اشتباه، ISO‌ای می‌دهد
#  که ظاهرا ساخته شده و روی سرور بوت نمی‌شود — بدترین حالت، چون در
#  اتاق سرور معلوم می‌شود.
#
#  ‏`-boot_image any replay` همان ساختار بوت ISO اصلی (BIOS و UEFI) را
#  عینا بازمی‌نویسد و ما فقط فایل اضافه می‌کنیم.
#  ───────────────────────────────────────────────────────────────────
#
#  نکتهٔ مهم: نصب به اینترنت نیاز دارد (بسته‌های اوبونتو از مخزن
#  می‌آیند). خودِ کد Hotel Media داخل ISO است و دانلود نمی‌خواهد.

set -euo pipefail

UBUNTU_VER="${UBUNTU_VER:-24.04.3}"
ISO_NAME="ubuntu-${UBUNTU_VER}-live-server-amd64.iso"
ISO_URL="https://releases.ubuntu.com/${UBUNTU_VER%.*}/${ISO_NAME}"

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORK="${WORK:-/var/tmp/hm-iso}"

# انبار مشترک ISO. جدا از فضای کار است چون /var/tmp جای موقت است و
# دانلود ۳ گیگابایتی نباید با پاک‌سازی سیستم از بین برود — همان فایل
# برای پروژه‌های دیگر هم به‌کار می‌آید.
ISO_STORE="${ISO_STORE:-/srv/iso}"
OUT="${OUT:-$ISO_STORE/hotel-media-installer-$(date +%Y%m%d).iso}"

# رمز پیش‌فرض کاربر سیستم. اول ورود باید عوض شود — در پیام پایانی
# هم گفته می‌شود.
OS_USER="${OS_USER:-hotel}"
OS_PASS="${OS_PASS:-HotelMedia@2026}"

c_ok()   { printf "\033[32m  ✓\033[0m %s\n" "$1"; }
c_info() { printf "\033[36m→\033[0m %s\n" "$1"; }
c_die()  { printf "\033[31m  ✗ %s\033[0m\n" "$1" >&2; exit 1; }

[ "$(id -u)" = "0" ] || c_die "با sudo اجرا کنید"

# ── ۱) ابزارها ────────────────────────────────────────────────────
c_info "بررسی ابزارها"
NEED=""
command -v xorriso  >/dev/null || NEED="$NEED xorriso"
command -v wget     >/dev/null || NEED="$NEED wget"
command -v openssl  >/dev/null || NEED="$NEED openssl"
if [ -n "$NEED" ]; then
    c_info "نصب:$NEED"
    DEBIAN_FRONTEND=noninteractive apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq $NEED >/dev/null \
      || c_die "نصب ابزارها ناموفق بود"
fi
c_ok "xorriso $(xorriso --version 2>&1 | grep -oP 'xorriso \K[0-9.]+' | head -1)"

# ── ۲) فضای کار ──────────────────────────────────────────────────
FREE_GB=$(df -BG --output=avail "$(dirname "$WORK")" | tail -1 | tr -dc '0-9')
[ "${FREE_GB:-0}" -lt 12 ] && c_die "حداقل ۱۲ گیگابایت فضای آزاد لازم است (موجود: ${FREE_GB}GB)"
mkdir -p "$WORK/payload" "$ISO_STORE"
chmod 755 "$ISO_STORE"
c_ok "فضای کار $WORK · انبار ISO $ISO_STORE (${FREE_GB}GB آزاد)"

# ── ۳) ISO اوبونتو (از انبار مشترک، وگرنه دانلود) ──────────────
BASE_ISO="$ISO_STORE/$ISO_NAME"
if [ -f "$BASE_ISO" ]; then
    c_ok "از انبار استفاده شد: $BASE_ISO ($(du -h "$BASE_ISO" | cut -f1))"
else
    c_info "دانلود $ISO_NAME (حدود ۳ گیگابایت) → $ISO_STORE"
    # ‏.part تا پایان: دانلود نیمه‌کاره‌ای که اسم نهایی بگیرد، در اجرای
    # بعدی «موجود» شمرده می‌شود و ISO خروجی خراب می‌دهد.
    wget -q --show-progress -c -O "$BASE_ISO.part" "$ISO_URL" \
      || c_die "دانلود ناموفق — آدرس یا دسترسی اینترنت را بررسی کنید"
    mv "$BASE_ISO.part" "$BASE_ISO"
    c_ok "دانلود شد و در انبار ماند (پروژه‌های دیگر هم می‌توانند استفاده کنند)"
fi

# ── ۴) کد برنامه برای داخل ISO ─────────────────────────────────
c_info "بسته‌بندی کد Hotel Media"
rm -rf "$WORK/payload/hotel-media"
mkdir -p "$WORK/payload/hotel-media"
# .git و لاگ و فایل‌های آپلودشده به ISO نمی‌روند: ISO باید یک نصب
# تازه بدهد، نه کپی دادهٔ یک هتل دیگر.
tar -C "$SRC_DIR" \
    --exclude=.git --exclude=storage/logs --exclude=storage/cache \
    --exclude=storage/sessions --exclude='public/uploads/*' \
    --exclude='*.iso' --exclude=node_modules \
    -cf - . | tar -C "$WORK/payload/hotel-media" -xf -
VER=$(cat "$SRC_DIR/VERSION" 2>/dev/null || echo "0.0.0")
c_ok "نسخهٔ $VER بسته‌بندی شد ($(du -sh "$WORK/payload/hotel-media" | cut -f1))"

# ── ۵) autoinstall ──────────────────────────────────────────────
c_info "ساخت پیکربندی نصب خودکار"
PASS_HASH=$(openssl passwd -6 "$OS_PASS")

mkdir -p "$WORK/payload/server"
: > "$WORK/payload/server/meta-data"

cat > "$WORK/payload/server/user-data" <<AUTOINSTALL
#cloud-config
autoinstall:
  version: 1

  # زبان سیستم انگلیسی می‌ماند و فقط منطقهٔ زمانی و صفحه‌کلید محلی
  # می‌شود: پیام خطای فارسیِ نیم‌کاره در کنسول سرور، عیب‌یابی را
  # سخت‌تر می‌کند نه آسان‌تر.
  locale: en_US.UTF-8
  keyboard:
    layout: us
  timezone: Asia/Tehran

  identity:
    hostname: hotel-media
    username: $OS_USER
    password: "$PASS_HASH"

  ssh:
    install-server: true
    allow-pw: true

  # کل دیسک با LVM: بزرگ‌کردن فضا بعدا بی نصب دوباره ممکن می‌شود، و
  # ویدیو و ضبط هتل همیشه بیشتر از برآورد اول جا می‌خواهند.
  storage:
    layout:
      name: lvm

  # بسته‌ها اینجا هم آمده‌اند تا اگر late-commands شکست خورد، سرور
  # دست‌کم با یک پشتهٔ کامل بالا بیاید و قابل عیب‌یابی باشد.
  packages:
    - nginx
    - mariadb-server
    - ffmpeg
    - poppler-utils
    - php8.3-fpm
    - php8.3-cli
    - php8.3-mysql
    - php8.3-mbstring
    - php8.3-xml
    - php8.3-curl
    - php8.3-gd
    - php8.3-intl
    - php8.3-zip
    - php8.3-bcmath
    - php8.3-opcache
    - curl
    - unzip

  late-commands:
    # /target همان سیستم نصب‌شده است. کد از ISO کپی می‌شود تا نصب
    # به GitHub نیاز نداشته باشد.
    - mkdir -p /target/opt/hotel-media-src
    - cp -a /cdrom/hotel-media/. /target/opt/hotel-media-src/
    # نصب‌کننده در اولین بوت اجرا می‌شود، نه اینجا: داخل
    # ‏late-commands سرویس‌ها (MariaDB) بالا نیستند و migration شکست
    # می‌خورد. این را با تجربه می‌گوییم نه با حدس.
    - |
      cat > /target/etc/systemd/system/hotel-media-firstboot.service <<'UNIT'
      [Unit]
      Description=Hotel Media first-boot installer
      After=network-online.target mariadb.service
      Wants=network-online.target
      ConditionPathExists=/opt/hotel-media-src/scripts/install-ubuntu.sh

      [Service]
      Type=oneshot
      RemainAfterExit=yes
      ExecStart=/bin/bash /opt/hotel-media-src/scripts/install-ubuntu.sh
      StandardOutput=append:/var/log/hotel-media-install.log
      StandardError=append:/var/log/hotel-media-install.log
      # یک‌بار بس است: فایل نشانه بعد از موفقیت ساخته می‌شود
      ExecStartPost=/bin/sh -c 'touch /var/lib/hotel-media-installed; systemctl disable hotel-media-firstboot.service'

      [Install]
      WantedBy=multi-user.target
      UNIT
    - curtin in-target --target=/target -- systemctl enable hotel-media-firstboot.service
    # پیام ورود: اپراتور بداند نصب در پس‌زمینه ادامه دارد
    - |
      cat > /target/etc/motd <<'MOTD'

        Hotel Media — سماع رایانه کیش
        ───────────────────────────────────────────────
        نصب در اولین بوت به‌صورت خودکار انجام می‌شود.
        پیشرفت:   tail -f /var/log/hotel-media-install.log
        پنل:      http://<این-سرور>/login
        ───────────────────────────────────────────────

      MOTD

  shutdown: reboot
AUTOINSTALL
c_ok "autoinstall ساخته شد"

# ── ۶) بازبسته‌بندی ISO ────────────────────────────────────────
c_info "بازنویسی ISO (چند دقیقه)"

# ‏grub.cfg اصلی را از ISO می‌گیریم و ورودی خودکار را اول می‌گذاریم.
rm -rf "$WORK/grub"
mkdir -p "$WORK/grub"
xorriso -osirrox on -indev "$BASE_ISO" \
        -extract /boot/grub/grub.cfg "$WORK/grub/grub.cfg" >/dev/null 2>&1 \
  || c_die "grub.cfg از ISO خوانده نشد"

# ‏autoinstall + nocloud: نصب‌کنندهٔ اوبونتو پیکربندی را از /cdrom/server
# می‌خواند. ‏timeout=1 تا اگر کسی حاضر نبود، خودش شروع کند.
python3 - "$WORK/grub/grub.cfg" <<'PY'
import sys, re
p = sys.argv[1]
s = open(p, encoding='utf-8', errors='replace').read()
entry = (
    'menuentry "Install Hotel Media (automatic, erases the disk)" {\n'
    '\tset gfxpayload=keep\n'
    '\tlinux\t/casper/vmlinuz autoinstall ds=nocloud\\;s=/cdrom/server/ ---\n'
    '\tinitrd\t/casper/initrd\n'
    '}\n'
)
s = re.sub(r'^set timeout=.*$', 'set timeout=5', s, flags=re.M)
if 'set timeout' not in s:
    s = 'set timeout=5\n' + s
# ورودی تازه پیش از نخستین menuentry موجود می‌نشیند تا پیش‌فرض باشد
i = s.find('menuentry')
s = s[:i] + entry + s[i:] if i != -1 else s + '\n' + entry
open(p, 'w', encoding='utf-8').write(s)
print("grub.cfg patched")
PY

xorriso -indev "$BASE_ISO" -outdev "$OUT" \
        -boot_image any replay \
        -volid "HOTEL_MEDIA" \
        -compliance no_emul_toc \
        -map "$WORK/payload/hotel-media" /hotel-media \
        -map "$WORK/payload/server"      /server \
        -map "$WORK/grub/grub.cfg"       /boot/grub/grub.cfg \
        >/dev/null 2>&1 \
  || c_die "بازنویسی ISO ناموفق بود"

[ -f "$OUT" ] || c_die "ISO ساخته نشد"
c_ok "ISO ساخته شد: $(du -h "$OUT" | cut -f1)"

# ── ۷) بررسی خروجی ────────────────────────────────────────────
c_info "بررسی ISO"
xorriso -indev "$OUT" -report_system_area plain 2>/dev/null | grep -iE "MBR|GPT|El Torito" | head -4
FOUND=$(xorriso -indev "$OUT" -find / -name user-data 2>/dev/null | grep -c "user-data" || true)
[ "${FOUND:-0}" -ge 1 ] && c_ok "پیکربندی نصب خودکار داخل ISO هست" \
                        || c_die "user-data داخل ISO پیدا نشد"
FOUND2=$(xorriso -indev "$OUT" -find /hotel-media -name artisan 2>/dev/null | grep -c artisan || true)
[ "${FOUND2:-0}" -ge 1 ] && c_ok "کد Hotel Media داخل ISO هست" \
                         || c_die "کد داخل ISO پیدا نشد"

sha256sum "$OUT" > "$OUT.sha256"

echo
echo "═══════════════════════════════════════════════════════════════"
echo "  ISO آماده است"
echo "═══════════════════════════════════════════════════════════════"
echo
echo "  فایل:   $OUT"
echo "  اندازه: $(du -h "$OUT" | cut -f1)"
echo "  نسخه:   Hotel Media $VER روی Ubuntu $UBUNTU_VER"
echo
echo "  ساخت USB:"
echo "    لینوکس:  sudo dd if=$OUT of=/dev/sdX bs=4M status=progress oflag=sync"
echo "    ویندوز:  با Rufus در حالت DD"
echo
echo "  هنگام بوت، گزینهٔ اول «Install Hotel Media» را بزنید."
echo "  هشدار: نصب خودکار است و \033[31mکل دیسک را پاک می‌کند\033[0m."
echo
echo "  ورود به سیستم‌عامل:  $OS_USER / $OS_PASS   ← همان اول عوضش کنید"
echo "  نصب برنامه در اولین بوت ادامه دارد:"
echo "    tail -f /var/log/hotel-media-install.log"
echo
echo "  نصب به اینترنت نیاز دارد (بستهٔ اوبونتو از مخزن می‌آید)."
echo "═══════════════════════════════════════════════════════════════"
