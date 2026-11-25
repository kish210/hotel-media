#!/usr/bin/env python3
# ══════════════════════════════════════════════════════════════════════
#  Hotel Media — ساخت ISO سفارشی بدون root (pycdlib)
#
#  چرا این فایل هست: `build-iso.sh` به xorriso و loop mount و root
#  نیاز دارد و روی ویندوز یا WSL بدون امتیاز اجرا نمی‌شود. این نسخه
#  فقط ISO رسمی اوبونتو را باز می‌کند، چند فایل اضافه می‌کند و دوباره
#  می‌نویسد — بدون mount، بدون root.
#
#  چرا کل برنامه در یک tar.gz و نه هزاران فایل: نوشتن ۵۰۰۰ فایل کوچک
#  با pycdlib چند برابر کندتر است و درخت ISO را شکننده می‌کند. یک
#  بستهٔ tar هم همان چیزی است که `firstboot.sh` بعد از نصب باز می‌کند.
#
#  اجرا:
#      python deploy/iso/build-iso.py \
#          --base /path/ubuntu-24.04.3-live-server-amd64.iso \
#          --out  dist/HotelMedia-1.35.0-amd64.iso
# ══════════════════════════════════════════════════════════════════════
import argparse
import hashlib
import os
import sys
import tarfile
import tempfile
import time

# کنسول ویندوز پیش‌فرض cp1252 است و اولین print فارسی یا فلش، اسکریپت را
# با UnicodeEncodeError می‌کشد — نه ساخت ISO. پس خروجی را صریح utf-8
# می‌کنیم (روی لینوکس بی‌اثر است).
try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

try:
    import pycdlib
except ImportError:
    sys.exit("pycdlib نصب نیست:  pip install pycdlib")

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# چیزهایی که روی سرور هتل لازم نیستند. حذفشان ISO را چند صد مگابایت
# کوچک‌تر می‌کند و نصب را سریع‌تر؛ چیزی از کارکرد کم نمی‌شود.
EXCLUDE_DIRS = {
    ".git", ".github", ".claude", ".freebuff", "node_modules", "vendor",
    "dist", "android", "ios", "fastlane", "windows-player", "promo-video.html",
    "storage/logs", "storage/cache", "storage/sessions", "storage/update",
    "public/uploads", "public/apk", "tests", "offline-pool",
}
EXCLUDE_SUFFIX = (".iso", ".exe", ".apk", ".wav", ".zip", ".rar", ".7z", ".log")
EXCLUDE_NAMES = {".env", ".env.local", ".env.production"}


def should_skip(rel: str) -> bool:
    rel = rel.replace("\\", "/")
    if rel in EXCLUDE_NAMES or os.path.basename(rel) in EXCLUDE_NAMES:
        return True
    for d in EXCLUDE_DIRS:
        if rel == d or rel.startswith(d + "/"):
            return True
    if rel.lower().endswith(EXCLUDE_SUFFIX):
        return True
    return False


def build_tarball(dest: str) -> int:
    """بستهٔ برنامه — با tarfile خود پایتون تا به tar خارجی نیاز نباشد."""
    count = 0
    with tarfile.open(dest, "w:gz", compresslevel=6) as tar:
        for root, dirs, files in os.walk(REPO):
            dirs[:] = [d for d in dirs if not should_skip(os.path.relpath(os.path.join(root, d), REPO))]
            for name in files:
                full = os.path.join(root, name)
                rel = os.path.relpath(full, REPO).replace("\\", "/")
                if should_skip(rel):
                    continue
                try:
                    tar.add(full, arcname=rel, recursive=False)
                    count += 1
                except OSError:
                    continue
    return count


def make_grub(orig: str) -> bytes:
    """منوی بوت: گزینهٔ خودکار اول، گزینه‌های اصلی اوبونتو دست‌نخورده."""
    body = orig
    # گزینه‌های اصلی را نگه می‌داریم (نصب دستی و HWE) و فقط تایم‌اوت و
    # گزینهٔ خودکار را عوض می‌کنیم؛ حذفشان یعنی اگر نصب خودکار شکست
    # خورد، راه دومی نماند.
    if "menuentry \"Try or Install Ubuntu Server\"" in body:
        head, tail = body.split("menuentry \"Try or Install Ubuntu Server\"", 1)
        tail = "menuentry \"Try or Install Ubuntu Server\"" + tail
    else:
        head, tail = "", body

    head = "set timeout=10\nset default=0\n\n"
    auto = (
        'menuentry "Install Hotel Media Server (automatic)" {\n'
        "\tset gfxpayload=keep\n"
        "\tlinux\t/casper/vmlinuz autoinstall ds=nocloud\\;s=/cdrom/ quiet ---\n"
        "\tinitrd\t/casper/initrd\n"
        "}\n"
    )
    # loadfont و رنگ‌ها لازم‌اند وگرنه منو بی‌متن می‌شود
    head += "loadfont unicode\n\nset menu_color_normal=white/black\nset menu_color_highlight=black/light-gray\n\n"
    return (head + auto + "\n" + tail).encode("utf-8")


def sha256(path: str) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", required=True, help="ISO رسمی Ubuntu Server")
    ap.add_argument("--out", required=True, help="مسیر ISO خروجی")
    ap.add_argument("--version", default=open(os.path.join(REPO, "VERSION")).read().strip())
    args = ap.parse_args()

    if not os.path.isfile(args.base):
        sys.exit("ISO پایه پیدا نشد: " + args.base)

    os.makedirs(os.path.dirname(os.path.abspath(args.out)) or ".", exist_ok=True)
    tmp = tempfile.mkdtemp(prefix="hm-iso-")
    tarball = os.path.join(tmp, "hotel-media.tar.gz")
    user_data = os.path.join(tmp, "user-data")
    meta_data = os.path.join(tmp, "meta-data")
    grub_path = os.path.join(tmp, "grub.cfg")

    print("→ بسته‌بندی برنامه…")
    n = build_tarball(tarball)
    print("   %d فایل، %.1f MB" % (n, os.path.getsize(tarball) / 1048576))

    # ‏user-data همان فایل مخزن است؛ late-commands هم پوشه و هم tar را
    # می‌فهمد. فقط یک خط به آن اضافه می‌شود تا نسخهٔ ISO داخل خودش
    # معلوم باشد.
    src = os.path.join(REPO, "deploy", "iso", "autoinstall", "user-data")
    with open(src, encoding="utf-8") as f:
        ud = f.read()
    ud = ud.replace(
        "autoinstall:\n  version: 1",
        "autoinstall:\n  version: 1\n  # ساخته‌شده از Hotel Media %s — %s"
        % (args.version, time.strftime("%Y-%m-%d")),
        1,
    )
    with open(user_data, "w", encoding="utf-8", newline="\n") as f:
        f.write(ud)
    open(meta_data, "w").close()

    iso = pycdlib.PyCdlib()
    print("→ باز کردن ISO پایه…")
    iso.open(args.base)

    # ‏grub.cfg اصلی
    orig_path = os.path.join(tmp, "grub.orig")
    iso.get_file_from_iso(local_path=orig_path, rr_path="/boot/grub/grub.cfg")
    with open(orig_path, encoding="utf-8", errors="replace") as f:
        orig = f.read()
    with open(grub_path, "wb") as f:
        f.write(make_grub(orig))

    print("→ جایگزینی منوی بوت…")
    # ‏pycdlib در rm_file نام Rock Ridge را «rr_name» می‌گیرد نه مسیر؛
    # ‏iso_path تنها کافی است و رکورد را از همهٔ contextها برمی‌دارد.
    iso.rm_file(iso_path="/BOOT/GRUB/GRUB.CFG;1", joliet_path="/boot/grub/grub.cfg")
    iso.add_file(grub_path, iso_path="/BOOT/GRUB/GRUB.CFG;1",
                 rr_name="grub.cfg", joliet_path="/boot/grub/grub.cfg")

    # ‏md5sum اصلی با محتوای تازه نمی‌خواند؛ اگر بماند بررسی یکپارچگی
    # هنگام بوت شکست می‌خورد و کاربر فکر می‌کند ISO خراب است.
    print("→ حذف md5sum قدیمی…")
    try:
        iso.rm_file(iso_path="/MD5SUM.TXT;1", joliet_path="/md5sum.txt")
    except Exception:
        pass

    print("→ افزودن بسته و seed نصب…")
    iso.add_file(tarball, iso_path="/HOTELMD.TAR;1",
                 rr_name="hotel-media.tar.gz", joliet_path="/hotel-media.tar.gz")
    iso.add_file(user_data, iso_path="/USER_DAT.;1",
                 rr_name="user-data", joliet_path="/user-data")
    iso.add_file(meta_data, iso_path="/META_DAT.;1",
                 rr_name="meta-data", joliet_path="/meta-data")

    print("→ نوشتن ISO (چند دقیقه)…")
    if os.path.exists(args.out):
        os.remove(args.out)

    def progress(done, total, opaque=None):
        if total:
            pct = int(done * 100 / total)
            if pct % 10 == 0:
                print("   %d%%" % pct, flush=True)

    iso.write(args.out, progress_cb=progress)
    iso.close()

    size = os.path.getsize(args.out)
    digest = sha256(args.out)
    with open(args.out + ".sha256", "w", encoding="utf-8") as f:
        f.write("%s  %s\n" % (digest, os.path.basename(args.out)))

    print("\n✔ ISO آماده است")
    print("  فایل    %s" % args.out)
    print("  حجم     %.2f GB" % (size / 1073741824))
    print("  sha256  %s" % digest)


if __name__ == "__main__":
    main()
