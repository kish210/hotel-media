# 💿 ISO سفارشی Ubuntu — نصب یک‌مرحله‌ای سرور هتل

یک فایل ISO که روی سرور بوت می‌شود، اوبونتو را **بدون هیچ سؤالی** نصب
می‌کند و در پایان Hotel Media آماده و در حال اجراست.

مناسب وقتی که چند هتل دارید و نمی‌خواهید هر بار سرور را دستی آماده کنید.

---

## چه چیزی داخل ISO است

| بخش | توضیح |
|-----|-------|
| Ubuntu Server LTS | سیستم‌عامل پایه |
| nginx · PHP-FPM · MariaDB | پیش‌نیازها، از قبل نصب‌شده |
| TVHeadend · ffmpeg · udpxy | دریافت ماهواره، ترنسکد، پل multicast |
| Hotel Media | کل برنامه به‌همراه `vendor/` |
| autoinstall | پیکربندی نصب بدون دخالت کاربر |

---

## ساخت ISO

روی یک ماشین Ubuntu یا Debian (یا WSL):

```bash
sudo bash deploy/iso/build-iso.sh
```

خروجی: `/tmp/hotel-media-iso/HotelMedia-<version>-amd64.iso`

### گزینه‌ها

```bash
# نسخه‌ی پایه‌ی دیگر
sudo UBUNTU_VERSION=24.04.5 bash deploy/iso/build-iso.sh

# پوشه‌ی خروجی دلخواه
sudo OUT_DIR=/srv/build bash deploy/iso/build-iso.sh

# بسته‌های .deb هم داخل ISO — برای هتلی که سرورش اینترنت ندارد
sudo OFFLINE=1 bash deploy/iso/build-iso.sh
```

> اگر ماشین ساخت به اینترنت دسترسی ندارد، ISO پایه‌ی اوبونتو را دستی در
> `$OUT_DIR` بگذارید؛ اسکریپت از همان استفاده می‌کند و دوباره دانلود نمی‌کند.

### ساخت USB بوتیبل

```bash
# Linux — مراقب باشید /dev/sdX دیسک درست باشد
sudo dd if=HotelMedia-24.04.5-amd64.iso of=/dev/sdX bs=4M status=progress conv=fsync
```

روی ویندوز با **Rufus** (حالت DD) یا **balenaEtcher**.

---

## نصب روی سرور

1. USB را به سرور بزنید و از آن بوت کنید
2. در منو گزینه‌ی اول را انتخاب کنید:
   **Install Hotel Media Server (automatic)**
3. صبر کنید — نصب کاملاً خودکار است (حدود ۱۰ تا ۲۰ دقیقه)
4. سرور خودش ریبوت می‌شود
5. در اولین بوت، Hotel Media نصب می‌شود (چند دقیقه‌ی دیگر)
6. آدرس‌ها روی صفحه‌ی ورود کنسول نمایش داده می‌شوند

> ⚠️ **گزینه‌ی اول کل دیسک سرور را پاک می‌کند.** فقط روی سروری بوت کنید
> که برای همین کار آماده شده. برای نصب دستی، گزینه‌ی دوم منو را بزنید.

### ورود پیش‌فرض

| | |
|---|---|
| کاربر سیستم | `hotel` / `HotelMedia@2026` |
| پنل مدیریت | `admin@hotelmedia.com` / `Admin@123456` |
| TVHeadend | `hotelmedia` / `ChangeMe@2026` |

**هر سه را فوراً عوض کنید.**

---

## بعد از نصب — سه کار واجب

### ۱) IP ثابت

آدرس پورتال در منوی مخفی **همه‌ی** تلویزیون‌ها وارد می‌شود؛ اگر IP سرور
عوض شود، همه‌ی تلویزیون‌ها قطع می‌شوند.

```bash
sudo nano /etc/netplan/50-cloud-init.yaml
sudo netplan apply
```

### ۲) رمزها

```bash
passwd                                    # رمز کاربر سیستم
sudo nano /etc/hotel-media/tvheadend.cred # رمز TVHeadend
# رمز پنل از داخل خود پنل عوض می‌شود
```

### ۳) تیونر TVHeadend

نصب تازه هنوز تیونر تنظیم‌نشده دارد — این مرحله به دیش و کارت هر هتل
بستگی دارد:

1. `http://<IP سرور>:9981`
2. Configuration ← DVB Inputs ← شبکه و تیونر را تنظیم و اسکن کنید
3. سپس: `sudo -u www-data php /var/www/hotel-media/artisan tvheadend:setup`

---

## عیب‌یابی

| نشانه | بررسی |
|-------|-------|
| نصب وسط کار متوقف شد | `Ctrl+Alt+F2` → `journalctl -u subiquity` |
| بوت شد ولی پنل بالا نیامد | `journalctl -u hotel-media-firstboot` |
| نصب اولیه ناموفق بود | `cat /var/log/hotel-media-firstboot.log` |
| اجرای دوباره نصب | `sudo bash /var/www/hotel-media/deploy/install-production.sh` |

سرویس نصب اولیه در صورت شکست **غیرفعال نمی‌شود** و در بوت بعدی دوباره
تلاش می‌کند.

---

## چطور کار می‌کند

```
بوت از USB
   ↓
GRUB → autoinstall ds=nocloud;s=/cdrom/server/
   ↓
subiquity فایل user-data را می‌خواند
   ├─ پارتیشن‌بندی LVM روی کل دیسک
   ├─ نصب بسته‌ها (nginx, PHP, MariaDB, TVHeadend…)
   ├─ debconf از قبل پاسخ داده شده ← TVHeadend سؤال نمی‌پرسد
   └─ late-commands: کپی برنامه از /cdrom به /opt و فعال‌سازی سرویس
   ↓
ریبوت
   ↓
hotel-media-firstboot.service
   └─ install-production.sh ← دیتابیس، nginx، سرویس‌ها، cron
   ↓
سرور آماده
```

**چرا نصب برنامه در اولین بوت و نه داخل نصب‌کننده؟** در محیط نصب‌کننده
MariaDB سرویس ندارد و بالا نمی‌آید؛ نصبی که به دیتابیس زنده نیاز دارد
آنجا شکست می‌خورد.

---

## نکته درباره‌ی نسخه‌ی PHP

`user-data` بسته‌های `php8.4-*` را نصب می‌کند که نسخه‌ی پیش‌فرض Ubuntu
24.04 است. اگر ISO پایه را عوض کردید، نسخه‌ی PHP در `user-data` و
`PHP_VER` در `install-production.sh` را هم هماهنگ کنید:

```bash
sudo PHP_VER=8.3 bash deploy/install-production.sh
```
