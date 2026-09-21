# راهنمای انتشار در Google Play و App Store

## ۱. Google Play Store

### الف) ساخت Keystore امضا (یک‌بار)

```bash
keytool -genkey -v \
  -keystore hotelmedia-release.jks \
  -alias hotelmedia \
  -keyalg RSA -keysize 2048 -validity 10000 \
  -dname "CN=Hotel Media, OU=Mobile, O=Hotel Media, L=-, S=-, C=IR"
```

> ⚠️ این فایل را هرگز از دست ندهید و در گیت commit نکنید!

### ب) اضافه کردن Secrets به GitHub

Settings → Secrets → Actions → New repository secret:

| Secret Name | مقدار |
|---|---|
| `KEYSTORE_BASE64` | `base64 -w 0 hotelmedia-release.jks` |
| `KEY_ALIAS` | `hotelmedia` |
| `KEY_PASSWORD` | رمز key شما |
| `STORE_PASSWORD` | رمز store شما |

### ج) ساخت حساب Google Play Console

1. برو به https://play.google.com/console
2. پرداخت $25 (یک‌بار)
3. اطلاعات Developer را تکمیل کن
4. یک App جدید بساز: `com.hotelmedia.player`

### د) اولین بار — Upload دستی AAB

Play Console نیاز دارد اولین APK/AAB را **دستی** آپلود کنی:

1. از GitHub Release، فایل `*-android-playstore.aab` را دانلود کن
2. در Play Console → Internal Testing → Create new release
3. فایل AAB را آپلود کن
4. Store listing را از پوشه `fastlane/metadata/android/` کپی کن

### ه) بعد از اولین بار — آپلود خودکار با Fastlane

```bash
# نصب fastlane
gem install fastlane

# ایجاد Service Account در Google Play Console
# Settings → API access → Create service account
# دانلود JSON key و ذخیره به: fastlane/google-play-key.json

# آپلود به Internal Testing
fastlane android beta

# آپلود به Production
fastlane android deploy
```

### و) اضافه کردن Privacy Policy

Privacy policy آماده است در: `public/privacy/index.html`

در Play Console → Store listing → Privacy policy URL وارد کن:
```
https://your-server.com/privacy/
```

---

## ۲. App Store (iOS)

### پیش‌نیازها

- Mac با Xcode 15+
- Apple Developer Program ($99/سال): https://developer.apple.com/programs/
- App Store Connect account

### الف) Secrets برای GitHub Actions (iOS)

| Secret Name | چطور بسازی |
|---|---|
| `APPLE_CERT_BASE64` | Certificate از Xcode → Preferences → Accounts → Manage Certificates → Export → base64 |
| `APPLE_CERT_PASSWORD` | رمز certificate |
| `APPLE_PROFILE_BASE64` | Provisioning Profile از developer.apple.com → base64 |
| `APPLE_TEAM_ID` | از developer.apple.com → Membership |
| `APP_STORE_CONNECT_API_KEY_BASE64` | از App Store Connect → Users → Keys → Add |
| `APP_STORE_CONNECT_API_KEY_ID` | Key ID |
| `APP_STORE_CONNECT_ISSUER_ID` | Issuer ID |

### ب) اولین بار — App Store Connect

1. رفتن به https://appstoreconnect.apple.com
2. My Apps → + → New App
3. Bundle ID: `com.hotelmedia.player`
4. تکمیل Store listing (از `fastlane/metadata/android/en-US/` الگو بگیر)

### ج) Build از روی Mac

```bash
cd ios/Hotel MediaPlayer
xcodebuild archive \
  -scheme Hotel MediaPlayer \
  -archivePath build/Hotel MediaPlayer.xcarchive
```

### د) آپلود به TestFlight (اتوماتیک)

با push هر tag جدید، GitHub Actions فایل IPA را می‌سازد و به TestFlight آپلود می‌کند (اگر secrets تنظیم باشد).

---

## ۳. خلاصه Checklist

### Google Play
- [ ] Keystore ساخته شد
- [ ] Secrets در GitHub اضافه شد
- [ ] حساب Play Console ($25) ساخته شد
- [ ] Privacy Policy URL وارد شد: `/privacy/`
- [ ] اولین AAB دستی آپلود شد
- [ ] Screenshots آپلود شد (min 2, هر کدام 16:9)
- [ ] Feature Graphic آپلود شد (1024×500)
- [ ] App Icon آپلود شد (512×512)
- [ ] Content Rating تکمیل شد (Everyone / Business app)
- [ ] Target Audience تنظیم شد (18+, B2B)
- [ ] Declaration برای `REQUEST_INSTALL_PACKAGES` نوشته شد

### App Store
- [ ] Apple Developer Account ($99/سال)
- [ ] Certificate + Provisioning Profile ساخته شد
- [ ] Secrets در GitHub اضافه شد
- [ ] App در App Store Connect ثبت شد
- [ ] Screenshots برای iPhone 6.7" و iPad 12.9"
- [ ] Privacy Policy URL وارد شد
- [ ] Age Rating تنظیم شد (4+)
