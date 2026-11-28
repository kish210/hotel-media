# SignageCMS Android Player App

اپ پلیر اندروید برای تلویزیون‌های هتل. پورتال را در WebView تمام‌صفحه
نشان می‌دهد و به‌عنوان launcher دستگاه کار می‌کند.

نسخه: **۱.۷.۰** (`versionCode 10700`)

## ساخت APK

### پیش‌نیازها
- Android Studio Hedgehog (2023.1.1) یا جدیدتر
- JDK 17
- Android SDK 34

### مراحل
```bash
cd android
./gradlew assembleRelease
# APK در: app/build/outputs/apk/release/app-release.apk
```

### با Android Studio
1. پروژه را در Android Studio باز کن
2. Build → Generate Signed Bundle/APK
3. APK → Release

---

## پشتیبانی از نسخه‌های اندروید

| نسخه | API | وضعیت |
|---|---|---|
| Android 4.1 – 4.4 | 16–19 | ✅ نصب و اجرا می‌شود |
| Android 5 – 14 | 21–34 | ✅ کامل |
| Android 4.0 | 14–15 | ❌ ابزارهای امروزی (AGP 8 + AndroidX) پایین‌تر از 16 را نمی‌سازند |

`minSdk 16` است. هر API تازه در کد با `Build.VERSION.SDK_INT` محافظت شده:

- `evaluateJavascript` (API 19) → روی اندروید ۴ با `loadUrl("javascript:…")`
- `setMixedContentMode` (API 21) → فقط روی ۲۱+
- `TvView` و ورودی HDMI (API 21) → روی اندروید ۴ ساخته نمی‌شود
- `isDeviceOwnerApp` (API 18) و APIهای `DevicePolicyManager` (API 21) → محافظت‌شده
- `okhttp` و `gson` که استفاده نمی‌شدند حذف شدند (okhttp 4 خودش minSdk 21 می‌خواست)

> نکته‌ی صادقانه: روی اندروید ۴.۱–۴.۳ مرورگر داخلی WebKit قدیمی است.
> اگر پورتال از JavaScript مدرن استفاده کند ممکن است کامل رندر نشود؛
> قفل‌گاه، اجرای خودکار، OTA و تنظیمات بومی همه کار می‌کنند.

---

## بخش تنظیمات

دو راه ورود:

1. دکمه‌ی **⚙ تنظیمات دستگاه** روی صفحه‌ی راه‌اندازی اولیه
2. **پنج بار BACK پشت‌سرهم** روی پلیر (راه تکنسین؛ بعد از بار سوم
   شمارش معکوس نشان داده می‌شود)

| تنظیم | کلید | پیش‌فرض |
|---|---|---|
| آدرس سرور | `server_url` | — |
| کد صفحه | `screen_code` | — |
| اجرای خودکار بعد از روشن شدن | `auto_start` | روشن |
| مکث بعد از بوت (ثانیه) | `boot_delay` | ۳ |
| صفحه خاموش نشود | `keep_screen_on` | روشن |
| قفل‌گاه بعد از شروع | `kiosk_on_start` | خاموش |
| بررسی خودکار بروزرسانی | `auto_update` | روشن |
| تلاش دوباره (ثانیه) | `retry_seconds` | ۱۰ |

همه در `SharedPreferences` (فایل `signage_prefs`) روی خود دستگاه ذخیره
می‌شوند. `BootReceiver` مقدار `auto_start` و `boot_delay` را می‌خواند و
`UpdateService` به `auto_update` احترام می‌گذارد.

پل وب هم دو متد تازه دارد:

```js
SignageBridge.getSettings()   // JSON تنظیمات محلی
SignageBridge.openSettings()  // باز کردن صفحه‌ی تنظیمات
```

---

## ویژگی‌های اپ
- ✅ **Auto-start**: پس از روشن شدن TV خودکار اجرا می‌شه (قابل خاموش‌کردن)
- ✅ **Full Screen Kiosk**: بدون status bar و navigation bar
- ✅ **OTA Update**: بروزرسانی خودکار از سرور
- ✅ **Network Recovery**: وقتی اینترنت برقرار می‌شه خودکار load می‌کنه
- ✅ **Autoplay Video**: بدون نیاز به کلیک
- ✅ **Keep Screen On**: صفحه خاموش نمی‌شه
- ✅ **Bridge JS↔Android**: ارتباط بین WebView و Android
- ✅ **تنظیمات محلی**: سرور، کد، اجرای خودکار، قفل‌گاه، بروزرسانی

---

## نصب روی دستگاه

### Android TV / TV Box
```bash
adb connect 192.168.1.X
adb install -r SignageCMS.apk
```

### نصب دستی (USB)
1. `Settings → Device Preferences → Security → Unknown Sources: ON`
2. کپی APK به USB
3. نصب با File Manager

### قفل‌گاه کامل (اختیاری، یک‌بار روی دستگاه ریست‌شده و بدون حساب گوگل)
```bash
adb shell dpm set-device-owner com.signagecms.player/.kiosk.KioskAdminReceiver
```
سپس در تنظیمات اپ «قفل‌گاه: مهمان از اپ خارج نشود» را روشن کنید.

---

## OTA Update (نصب شبکه‌ای)
1. از پنل مدیریت → اپ Android → آپلود APK جدید
2. دستگاه‌ها هر ۱۰ دقیقه چک می‌کنند
3. در صورت نسخه جدید → دانلود و نصب خودکار

### Force Update
اگه "بروزرسانی اجباری" انتخاب شد، دستگاه بدون تأیید نصب می‌کنه.
