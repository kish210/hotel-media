# کلاینت‌های Hotel Media

سه کلاینت بومی داریم که همه یک پورتال را نشان می‌دهند: `http://<server>/player/<CODE>`.
هر کدام بخش **تنظیمات** خودش را دارد و تنظیمات روی خود دستگاه ذخیره می‌شود،
نه روی سرور — اگر شبکه قطع باشد دستگاه باید بالا بیاید.

| کلاینت | مسیر | نسخه | حداقل سیستم |
|---|---|---|---|
| ویندوز (Electron) | `windows-player/` | ۱.۶.۰ | Windows 10/11 (x64/ia32) |
| اندروید (WebView) | `android/` | ۱.۷.۰ | Android 4.1 (API 16) تا ۱۴ |
| رزبری‌پای (Python + Chromium) | `rpi/hotel-media-player/` | ۱.۰ | Raspberry Pi OS با محیط گرافیکی |
| تلویزیون‌های هتلی | پورتال در مرورگر تلویزیون | — | webOS / Tizen / Orsay |
| مرورگر / کیوسک | پورتال در مرورگر | — | هر مرورگر مدرن |

## تنظیمات مشترک

| تنظیم | ویندوز | اندروید | رزبری‌پای | توضیح |
|---|---|---|---|---|
| اجرای خودکار بعد از روشن شدن | ✅ با ویندوز | ✅ `BootReceiver` | ✅ systemd | کلید `auto_start` |
| مکث بعد از بوت | — | ✅ ثانیه | ✅ `server_wait` | شبکه‌های کند |
| صفحه بیدار بماند | ✅ `powerSaveBlocker` | ✅ `FLAG_KEEP_SCREEN_ON` | ✅ `xset` | |
| نشانگر ماوس پنهان | ✅ CSS | — | ✅ `unclutter` | |
| قفل‌گاه (خروج مهمان بسته) | ✅ پنجره‌ی تمام‌صفحه | ✅ `Lock Task` / Device Owner | ✅ Chromium kiosk | |
| بروزرسانی خودکار | ⬜ دستی | ✅ OTA از پنل | ⬜ `git`/بسته | |
| تلاش دوباره بعد از خطا | ✅ `retrySeconds` | ✅ `retry_seconds` | ✅ `restart_seconds` | |
| تازه‌سازی دوره‌ای | ✅ `autoReloadMinutes` | — | ✅ `reload_hour` | |
| همیشه بالاتر از بقیه | ✅ `alwaysOnTop` | — | — | |

`⬜` یعنی در این نسخه تنظیم ندارد و رفتار ثابت است.

---

## ویندوز

```bash
cd windows-player
npm install
npm start          # اجرا در حالت توسعه
npm run build      # ساخت نصب‌کننده‌ی NSIS در dist/
```

بخش تنظیمات در همان پنجره‌ی راه‌اندازی است و از آیکن Tray هم باز می‌شود.
«اجرای خودکار با ویندوز» با `app.setLoginItemSettings` ثبت می‌شود و فقط در
نسخه‌ی نصب‌شده اعمال می‌گردد (در `npm start` مسیر اجرا خودِ Electron است).

## اندروید

```bash
cd android
./gradlew assembleRelease
# app/build/outputs/apk/release/app-release.apk
```

- `minSdk 16` → اندروید ۴.۱ تا ۴.۴ نصب و اجرا می‌شود. ابزارهای امروزی
  (AGP 8 + AndroidX) پایین‌تر از API 16 را پشتیبانی نمی‌کنند؛ اندروید ۴.۰
  (API 14/15) پوشش داده نمی‌شود.
- صفحه‌ی تنظیمات: دکمه‌ی «⚙ تنظیمات دستگاه» روی صفحه‌ی راه‌اندازی،
  یا ۵ بار BACK پشت‌سرهم روی پلیر (راه تکنسین).
- روی اندروید ۴ مرورگر داخلی (WebView) قدیمی است؛ اگر پورتال با
  JavaScript مدرن نوشته شده باشد ممکن است کامل رندر نشود. قفل‌گاه،
  اجرای خودکار و تنظیمات همه بومی‌اند و کار می‌کنند.

## رزبری‌پای

```bash
cd rpi/hotel-media-player
sudo bash install.sh --server http://192.168.1.100 --code SCR001
```

`install.sh` مرورگر و ابزارهای X را نصب می‌کند، `player.py` را در
`/opt/hotel-media-player/` می‌گذارد و سرویس `hotel-media-player` را
فعال می‌کند. User-Agent اختصاصی `HotelMediaRPi/1.0` باعث می‌شود
`DeviceService` دستگاه را با پلتفرم `rpi` ثبت کند.

---

## دیتابیس

مهاجرت `061_rpi_platform.sql` مقدار `rpi` را به
`screens.platform` اضافه می‌کند. `DeviceService::PLATFORMS`،
`CAPABILITIES` و `platformLabel()` هم همین مقدار را می‌شناسند، پس:

- ثبت خودکار دستگاه (heartbeat پورتال) بدون خطای ۵۰۰ انجام می‌شود.
- فرمان‌های مجاز برای `rpi`: `refresh`, `message`, `open_url`, `clear_cache`.
- پنل (`/admin/devices` و `/screens`) برچسب «Raspberry Pi» را نشان می‌دهد.

اجرای مهاجرت:

```bash
php artisan db:migrate
```
