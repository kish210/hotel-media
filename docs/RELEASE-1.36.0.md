# نسخه ۱.۳۶.۰ — کلاینت‌ها، اندروید ۴، رزبری‌پای و ISO آماده

> این سند «همه‌چیزِ» نسخه ۱.۳۶.۰ است: چه عوض شد، چه چیزی ساخته شد، چطور نصب
> می‌شود، چه چیزی واقعاً تست شده و چه چیزی تست نشده. اگر فقط یک صفحه می‌خوانید،
> همین است.

---

## ۱) فایل‌های آماده

| فایل | مسیر | حجم | توضیح |
|---|---|---|---|
| 🏨 **ISO نصب سرور** | `dist/HotelMedia-1.36.0-amd64.iso` | ۳٫۰۸ GB | Ubuntu 24.04.3 Server + نصب خودکار Hotel Media |
| #️⃣ **چک‌سام ISO** | `dist/HotelMedia-1.36.0-amd64.iso.sha256` | — | مقدار رسمی در همین فایل؛ بررسی: `sha256sum -c` |
| 🪟 **پلیر ویندوز** | سورس در `windows-player/` | — | ۱.۶.۰ + بخش تنظیمات؛ ساخت نصب‌کننده نیاز به دسترسی npm دارد (بخش ۶) |
| 🍓 **کلاینت رزبری‌پای** | `rpi/hotel-media-player/` | ~۲۰ KB | `player.py` + `install.sh` + سرویس systemd |
| 🤖 **اپ اندروید** | سورس در `android/` | — | ساخت APK نیاز به Android SDK دارد (بخش ۶) |

مقایسه با نسخه قبل: `dist/HotelMedia-1.35.0-amd64.iso` دست‌نخورده مانده است.

> روی ISO **کد سرور** است، نه سورس کلاینت‌ها: پوشه‌های `android/`،
> `windows-player/` و `dist/` عمداً از بستهٔ نصب حذف می‌شوند تا ISO چند صد
> مگابایت سبک‌تر باشد. کلاینت رزبری‌پای چون فقط یک اسکریپت است روی ISO
> می‌ماند و می‌توانید همان‌جا از `/opt/…/hotelmedia/rpi/hotel-media-player`
> نصبش کنید.

---

## ۲) چه چیزی در این نسخه عوض شد

### بخش تنظیمات کلاینت‌ها (خواسته‌ی اصلی)
هر کلاینت حالا صفحه/بخش تنظیمات دارد و تنظیمات **روی خود دستگاه** ذخیره
می‌شود — نه روی سرور. اگر شبکه هتل قطع باشد، دستگاه باید بالا بیاید و پورتال
خودش را نشان دهد.

| تنظیم | ویندوز | اندروید | رزبری‌پای |
|---|---|---|---|
| اجرای خودکار بعد از روشن شدن | ✅ `setLoginItemSettings` | ✅ `BootReceiver` | ✅ `systemd enable` |
| مکث بعد از بوت | — | ✅ ۰–۱۲۰ ثانیه | ✅ `server_wait` |
| صفحه بیدار بماند | ✅ `powerSaveBlocker` | ✅ `FLAG_KEEP_SCREEN_ON` | ✅ `xset` |
| نشانگر ماوس پنهان | ✅ CSS | — | ✅ `unclutter` |
| قفل‌گاه | ✅ تمام‌صفحه + بدون popup | ✅ Lock Task / Device Owner | ✅ `--kiosk` |
| بروزرسانی خودکار | — | ✅ OTA از پنل | — |
| تلاش دوباره بعد از خطا | ✅ ۳–۳۰۰ ثانیه | ✅ ۳–۳۰۰ ثانیه | ✅ `restart_seconds` |
| تازه‌سازی دوره‌ای | ✅ هر N دقیقه | — | ✅ `reload_hour` |
| همیشه بالاتر از بقیه پنجره‌ها | ✅ | — | — |

**راه رسیدن به تنظیمات**
- ویندوز: پنجره‌ی راه‌اندازی (اولین اجرا) یا آیکن Tray → «تنظیمات / Setup».
- اندروید: دکمه‌ی «⚙ تنظیمات دستگاه» روی صفحه‌ی راه‌اندازی، یا **۵ بار BACK**
  پشت‌سرهم روی پلیر (از بار سوم شمارش معکوس نشان داده می‌شود).
- رزبری‌پای: `sudo python3 /opt/hotel-media-player/player.py --server … --code …`
  یا ویرایش `/etc/hotel-media-player/config.json`.

### اندروید ۴
`minSdk` از ۲۱ به **۱۶** آمد (اندروید ۴.۱ تا ۴.۴). همه‌ی APIهای تازه محافظت
شده‌اند: `evaluateJavascript` (۱۹) با `loadUrl("javascript:…")`،
`setMixedContentMode` و `TvView` و `DevicePolicyManager` (۲۱)،
`isDeviceOwnerApp` (۱۸)، `NotificationChannel` (۲۶). `okhttp` و `gson` که
هیچ‌جا استفاده نمی‌شدند حذف شدند؛ okhttp 4 خودش minSdk 21 می‌خواست و نصب روی
اندروید ۴ را ناممکن می‌کرد.

### رزبری‌پای
`rpi/hotel-media-player/`: پلیر کیوسک با پایتون ۳ (فقط کتابخانه‌ی استاندارد)،
سرویس systemd، نصب‌کننده، و User-Agent اختصاصی `HotelMediaRPi/1.0` که باعث
می‌شود سرور دستگاه را با پلتفرم `rpi` ثبت کند.

### سرور و دیتابیس
- مهاجرت `061_rpi_platform.sql`: مقدار `rpi` به `screens.platform`.
- `DeviceService`: `rpi` در `PLATFORMS`/`CAPABILITIES`/`platformLabel` + تشخیص
  از UA. فرمان‌های مجاز: `refresh`, `message`, `open_url`, `clear_cache`.
- پنل: برچسب «Raspberry Pi» در `/admin/devices` و `/screens`.
- رفع باگ `POST /api/v1/auth/login`: متد ورود API، `Auth::login()` را با
  آرگومان اشتباه صدا می‌زد (این متد فقط نشست می‌سازد و رمز را بررسی نمی‌کند)
  و `Auth::loginWithToken()` هم در کد وجود نداشت؛ نتیجه این بود که این
  مسیر **همیشه ۴۰۱** برمی‌گرداند و هیچ توکن JWT نمی‌داد. حالا مثل ورود وب،
  کاربر از دیتابیس خوانده و رمز با `password_verify` بررسی می‌شود و توکن با
  `JWT::encode()` ساخته می‌شود (کلید `sub` که میان‌افزار می‌خواند).

---

## ۳) نصب ISO

### روش A — نصب خودکار (پیشنهادی)
1. ISO را روی USB (با Rufus/balenaEtcher) یا به CD/DVD ماشین مجازی وصل کنید.
2. بوت کنید؛ نصب Ubuntu و بعد `firstboot` خودکار انجام می‌شود.
3. بعد از ریبوت: `http://<ip-سرور>/admin` — `admin@hotelmedia.com` / `Admin@123456`.

`firstboot.sh` این کارها را می‌کند: نصب Nginx/PHP 8.3/MariaDB، ساخت دیتابیس،
اجرای همه‌ی مهاجرت‌ها (تا ۰۶۱)، نصب TVHeadend، ساخت سرویس‌ها، و باز کردن
پنل. لاگ: `/var/log/hotelmedia-firstboot.log`.

### روش B — نصب روی Ubuntu موجود
```bash
tar -xzf hotelmedia.tar.gz -C /tmp/hm && sudo bash /tmp/hm/deploy/install-production.sh
```

### روش C — ارتقای نصب موجود
**نصب با `git clone`:**
```bash
cd /var/www/hotel-media
sudo bash deploy/update.sh          # پشتیبان → pull → migrate → reload
```

**نصب از ISO (بدون مخزن گیت):** بستهٔ برنامه داخل ISO در فایل
`/HOTELMD.TAR` (روی لینوکس: `hotelmedia.tar.gz`) است:
```bash
# روی سرور، ISO را موقت mount کنید (یا فایل tar را کپی کنید)
sudo mount -o loop HotelMedia-1.36.0-amd64.iso /mnt
sudo tar -xzf /mnt/HOTELMD.TAR -C /tmp/hm
sudo rsync -a --exclude='.env' --exclude='storage/' /tmp/hm/ /var/www/hotel-media/
cd /var/www/hotel-media && sudo -u www-data php artisan db:migrate
sudo systemctl reload php8.3-fpm
sudo umount /mnt
```

یا بدون دست زدن به فایل‌ها، از خود پنل: **مدیر ارشد → سیستم → به‌روزرسانی**
(این مسیر فقط وقتی کار می‌کند که یک Release پایدار روی گیت‌هاب وجود داشته
باشد؛ بخش ۶).

### بررسی سلامت بعد از نصب
```bash
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1/admin/login   # 200
php artisan db:migrate                                                  # «همه اعمال شده‌اند»
systemctl status nginx php8.3-fpm mysql --no-pager | head -20
```

---

## ۴) نصب کلاینت‌ها

### ویندوز
`windows-player/dist/Hotel Media Player Setup 1.6.0.exe` → نصب → آدرس سرور و
کد صفحه → «ذخیره و شروع پلیر» → «اجرای خودکار با ویندوز» روشن بماند.

### اندروید
```bash
adb connect <tv-ip> && adb install -r app-release.apk
```
برای قفل کامل (اختیاری، روی دستگاه ریست‌شده و بدون حساب گوگل):
```bash
adb shell dpm set-device-owner com.signagecms.player/.kiosk.KioskAdminReceiver
```

### رزبری‌پای
```bash
sudo bash install.sh --server http://192.168.1.100 --code SCR001
sudo raspi-config     # System Options → Boot / Auto Login → Desktop Autologin
python3 /opt/hotel-media-player/player.py --check
```

---

## ۵) مهاجرت دیتابیس
```bash
php artisan db:migrate
```
- فایل: `database/migrations/061_rpi_platform.sql`
- ماهیت: فقط یک `ALTER … MODIFY` روی ENUM؛ داده‌ای پاک نمی‌شود و اجرای دوباره
  بی‌خطر است.
- بازگشت: مقدار `rpi` در ENUM می‌ماند (بی‌اثر)؛ نیازی به rollback نیست.

---

## ۶) ساخت APK اندروید (وضعیت و راه)

در این نسخه **APK ساخته نشد**، به دو دلیل که هر دو محیطی است نه کدی:

1. **GitHub Actions برای این اکانت غیرفعال است.** پاسخ API:
   `Actions has been disabled for this user` — بنابراین نه push و نه
   `workflow_dispatch` هیچ اجرایی نمی‌سازد. (ورک‌فلوها سالم‌اند و در ۲۳ نوامبر
   با موفقیت APK ساخته‌اند.)
2. **Android SDK از این شبکه قابل دانلود نیست.** `dl.google.com/android/repository/*`
   برای همه‌ی مسیرها ۴۰۴ برمی‌گرداند، پس نه `sdkmanager` و نه دانلود مستقیم
   `platform-34`/`build-tools` ممکن نیست (نه روی این ماشین، نه روی VM).

به‌محض رفع هر کدام، ساخت یک دستور است:

```bash
# با GitHub Actions (بعد از فعال‌شدن Actions)
gh workflow run build-android.yml -R kish210/hotel-media --ref main
gh run watch -R kish210/hotel-media
gh run download -R kish210/hotel-media -n "Hotel Media-Release-APK" -D dist/clients/android

# یا محلی، روی ماشینی که Android SDK دارد
cd android && ./gradlew assembleDebug
```

### نصب‌کننده ویندوز
`npm install` از این شبکه هنگام دانلود بسته‌ها با خطای `TLS/stream` قطع
می‌شود (رجیستری خودش پاسخ ۲۰۰ می‌دهد، ولی دانلود بسته‌های بزرگ نیمه‌کاره
می‌ماند). روی ماشینی با اینترنت سالم:

```bash
cd windows-player
npm install
npm run build        # خروجی: windows-player/dist/*.exe
npm run build:ia32   # برای مینی‌پی‌سی‌های ۳۲ بیتی
```

منطق پلیر مستقل از این موضوع تست شده است (۲۰/۲۰ چک رفتاری با stub الکترون،
بخش ۷).

**امضای APK:** برای OTA روی دستگاه‌های موجود، نسخه‌ی جدید باید با همان کلید
قبلی امضا شود. در Actions این کار با اسرار `KEYSTORE_BASE64`, `KEY_ALIAS`,
`KEY_PASSWORD`, `STORE_PASSWORD` انجام می‌شود. بدون کلید، APK دیباگ نصب
می‌شود ولی روی دستگاه‌هایی که نسخه‌ی امضاشده دارند نصب نمی‌شود (اختلاف امضا).

---

## ۷) تست‌هایی که واقعاً اجرا شد

| موضوع | ابزار | نتیجه |
|---|---|---|
| سینتکس ۱۲ فایل جاوا | `java-parser` | ۰ خطا |
| هم‌خوانی `R.id` با لایه‌ها | مقایسه‌ی خودکار | ۱۷ ↔ ۱۷، بدون اختلاف |
| کلاس‌های Manifest | بررسی فایل‌ها | همه موجود |
| منطق پلیر ویندوز (بدون GUI) | stub الکترون + تست رفتاری | **۲۰/۲۰** |
| کانال‌های IPC و idهای `setup.html` | مقایسه‌ی خودکار | بدون اختلاف |
| `player.py` | `py_compile` + اجرای `--check`/`--setup` + تست توابع | سالم |
| `install.sh` | `bash -n` | سالم |
| `DeviceService` جدید (اجرا روی VM، PHP 8.3) | Reflection + آرگومان‌های واقعی UA | **۱۰/۱۰** (rpi/android/windows/webos/orsay) |
| ورود API (فرم و JSON) | `curl` روی VM | **۲/۲** + رمز غلط → ۴۰۱ |
| E2E روی نصب تازه از ISO (۱.۳۶.۰) | `vm/e2e-136.sh` | **۱۸/۱۸** (ورود، صفحات، API، ثبت توکن، enroll، platform=rpi، player) |
| `php -l` فایل‌های تغییریافته | PHP 8.3 روی VM | ۴ فایل، بدون خطا |
| ساخت ISO | `deploy/iso/build-iso.py` | موفق + sha256 |
| ساخت نصب‌کننده ویندوز | `npm install` + `electron-builder` | **ناموفق** — رجیستری npm از این شبکه با `TLS/stream` قطع می‌شود |

### تست نشده (صادقانه)
- **کامپایل نوع‌محور جاوا و ساخت APK**: بدون Android SDK نبود.
- **اجرای اپ روی دستگاه اندروید ۴ واقعی**: سخت‌افزار نبود.
- **اجرای نصب‌کننده ویندوز روی ویندوز هتل**: فقط ساخت انجام شد.

### تست‌شده روی نصب تازه از ISO (hm-ok، ۱.۳۶.۰)
- **مهاجرت ۰۶۱**: جدول `screens.platform` دارای مقدار `rpi`؛ enroll با UA
  `HotelMediaRPi/1.0` پلتفرم `rpi` را ذخیره کرد و پلیر `/player/{code}` → ۲۰۰.
- **بوت واقعی از ISO**: نصب خودکار Ubuntu + firstboot کامل شد؛ پنل ۱.۳۶.۰
  و ۱۸/۱۸ چک E2E پاس.

---

## ۸) یادآوری‌های عملیاتی
- **گیت‌هاب Actions خاموش است** — تا فعال‌شدنش (Billing/Setting) هیچ release
  خودکاری ساخته نمی‌شود؛ ISO و نصب‌کننده‌ها محلی ساخته می‌شوند.
- **کلید امضای APK** را جای امن نگه دارید؛ بدون آن نمی‌توانید نسخه‌های بعدی را
  روی نصب‌های موجود به‌روزرسانی کنید.
- **رزبری‌پای** به ورود خودکار گرافیکی نیاز دارد؛ بدون آن سرویس بالا می‌آید ولی
  مرورگر جایی برای کشیدن پنجره ندارد.
- **پشتیبان قبل از ارتقا**: `deploy/update.sh` خودش پشتیبان می‌گیرد؛ دستی هم
  `php artisan db:backup` (اگر موجود است) یا `mysqldump` توصیه می‌شود.

---

## ۹) فایل‌های مرتبط
- [`docs/CLIENTS.md`](CLIENTS.md) — مقایسه و جزئیات کلاینت‌ها
- [`windows-player/README.md`](../windows-player/README.md)
- [`android/README.md`](../android/README.md)
- [`rpi/hotel-media-player/README.md`](../rpi/hotel-media-player/README.md)
- [`docs/DATABASE-SCHEMA.md`](DATABASE-SCHEMA.md) — اسکیمای تولیدشده از مهاجرت‌ها
- [`docs/INSTALL-ISSUES.md`](INSTALL-ISSUES.md) — خطاهای رایج نصب
