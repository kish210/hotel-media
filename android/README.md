# SignageCMS Android Player App

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

## ویژگی‌های اپ
- ✅ **Auto-start**: پس از روشن شدن TV خودکار اجرا می‌شه
- ✅ **Full Screen Kiosk**: بدون status bar و navigation bar
- ✅ **OTA Update**: بروزرسانی خودکار از سرور
- ✅ **Network Recovery**: وقتی اینترنت برقرار می‌شه خودکار load می‌کنه
- ✅ **Autoplay Video**: بدون نیاز به کلیک
- ✅ **Keep Screen On**: صفحه خاموش نمی‌شه
- ✅ **Bridge JS↔Android**: ارتباط بین WebView و Android

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

---

## OTA Update (نصب شبکه‌ای)
1. از پنل مدیریت → اپ Android → آپلود APK جدید
2. دستگاه‌ها هر ۱۰ دقیقه چک می‌کنند
3. در صورت نسخه جدید → دانلود و نصب خودکار

### Force Update
اگه "بروزرسانی اجباری" انتخاب شد، دستگاه بدون تأیید نصب می‌کنه.
