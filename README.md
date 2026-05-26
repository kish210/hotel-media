# 🖥️ SignageCMS — Digital Signage Management System

> سیستم مدیریت تابلوی دیجیتال حرفه‌ای برای فرودگاه‌ها، هتل‌ها، رستوران‌ها، فروشگاه‌ها و محیط‌های شرکتی

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue?logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-orange?logo=mysql)](https://mysql.com)
[![Docker](https://img.shields.io/badge/Docker-Ready-blue?logo=docker)](docker-compose.yml)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![Release](https://img.shields.io/github/v/release/kish210/hotel-media?label=Latest%20Release&color=brightgreen)](https://github.com/kish210/hotel-media/releases/latest)

---

## 📥 دانلود پلیرها

| پلتفرم | فایل | توضیح |
|--------|------|-------|
| 🤖 **Android / Android TV** | [دانلود APK](https://github.com/kish210/hotel-media/releases/latest/download/SignageCMS-v1.5.1-android.apk) | Android 5.0+ — نصب مستقیم |
| 🖥️ **Windows Player** | [دانلود EXE](https://github.com/kish210/hotel-media/releases/latest/download/SignageCMS-v1.5.1-windows-player-setup.exe) | Windows 10/11 — نصب‌کننده خودکار |
| 🐳 **Server (Docker)** | [دانلود ZIP](https://github.com/kish210/hotel-media/releases/latest/download/SignageCMS-v1.5.1-server.zip) | همه سیستم‌عامل‌ها |

> 📦 همه نسخه‌ها: **[github.com/kish210/hotel-media/releases](https://github.com/kish210/hotel-media/releases)**

---

## ✨ امکانات

| ماژول | توضیح |
|-------|-------|
| 📺 **Multi-Screen** | مدیریت نامحدود صفحه‌نمایش با monitoring real-time |
| 🎬 **Playlist Designer** | ساخت پلی‌لیست drag & drop با transition |
| 🎨 **Layout Designer** | طراحی بصری multi-zone |
| 🗓️ **Content Scheduling** | زمان‌بندی روزانه / هفتگی / همیشه |
| ✈️ **FIDS Module** | دریافت خودکار اطلاعات پرواز از fids.airport.ir |
| 🍽️ **Restaurant Menu** | تابلوی منوی دیجیتال |
| 🏨 **Hotel Module** | اطلاعات هتل، خدمات، رویدادها |
| 🏢 **Corporate Module** | اطلاعیه و KPI شرکتی |
| 🛍️ **Retail Module** | تابلوهای فروشگاهی و تبلیغاتی |
| 📡 **WebSocket Real-time** | دستورات آنی از سرور به پلیر |
| 🚨 **Emergency Broadcast** | ارسال پیام اضطراری به همه صفحات |
| 🔗 **Cookie Player Binding** | آدرس ثابت `/player/` برای همه دستگاه‌ها |
| 👥 **Role-Based Access** | کنترل دسترسی ۵ سطح |
| 🌐 **REST API** | API کامل برای Android TV و اپ موبایل |
| 🐳 **Docker Ready** | استقرار آسان با یک دستور |
| 🇮🇷 **Persian RTL** | پشتیبانی کامل فارسی و RTL |

---

## 🚀 نصب سریع

### پیش‌نیاز
- [Docker Desktop](https://www.docker.com/products/docker-desktop) (Windows/Mac)  
- یا `curl -fsSL https://get.docker.com | sh` (Linux)

```bash
# 1. Clone
git clone https://github.com/mohammadgh/signage-cms.git
cd signage-cms

# 2. Environment
cp .env.example .env
# ویرایش .env — رمز DB، JWT secret و ...

# 3. Start
docker compose up -d --build

# 4. نصب database (یک‌بار)
docker exec signage_php php /var/www/html/public/install.php
```

**آدرس‌ها پس از نصب:**

| سرویس | آدرس |
|-------|------|
| 🎛️ Admin Panel | http://localhost/admin/dashboard |
| 📺 Player | http://localhost/player/ |
| 🗄️ phpMyAdmin | http://localhost:8081 |
| 🔌 WebSocket | ws://localhost:8080 |

**ورود پیش‌فرض:**
- Email: `admin@signagecms.com`
- Password: `Admin@123456`

---

## 📺 راه‌اندازی صفحه‌نمایش

### آدرس ثابت — همه دستگاه‌ها
همه صفحات‌نمایش از یک آدرس ثابت استفاده می‌کنند:

```
http://your-server/player/
```

### روش اتصال (Cookie-Based Binding)
1. مرورگر / TV را به `http://server/player/` ببرید  
2. کد فعال‌سازی را از **Admin → Screens → Activation** دریافت کنید  
3. کد را در صفحه pairing وارد کنید  
4. Cookie با مدت ۱ سال ذخیره می‌شود — دفعات بعد مستقیم پلیر باز می‌شود

```
/player/        ← اگر cookie دارد: پلیر
                ← اگر نه: صفحه ورود کد
/player/SCRXXXX ← URL مستقیم (cookie را هم ست می‌کند)
```

---

## ✈️ ماژول FIDS

دریافت خودکار اطلاعات پرواز از [fids.airport.ir](https://fids.airport.ir):

```
Admin → Modules → FIDS → تنظیمات
```

**قابلیت‌ها:**
- دریافت فوری با یک کلیک (Fetch Now)
- Cron Job برای به‌روزرسانی خودکار (هر N دقیقه)
- پشتیبانی از HTTP proxy (برای سرورهای خارج ایران)
- بررسی وضعیت اتصال با latency

**تنظیمات `.env`:**
```env
FIDS_TIMEOUT=10         # timeout به ثانیه
FIDS_CACHE_TTL=60       # کش به ثانیه
FIDS_HTTP_PROXY=        # proxy ایران (اگه سرور خارج باشه)
```

**Cron Job:**
```bash
*/5 * * * * curl -s "http://your-server/api/v1/fids/cron-sync?token=YOUR_TOKEN" > /dev/null
```

---

## 📁 ساختار پروژه

```
signage-cms/
├── app/
│   ├── Controllers/
│   │   ├── Api/              # REST API endpoints
│   │   └── Web/              # Admin panel controllers
│   ├── Core/                 # Framework core (Router, DB, Auth, JWT)
│   ├── Models/               # Database models
│   ├── Middleware/           # Auth, CSRF, Rate limit
│   ├── Modules/              # FIDS, Hotel, Retail, Corporate, Menu
│   └── Services/             # AirportIrFetcher, WebSocket, ...
├── config/                   # App & DB config
├── database/
│   ├── migrations/           # SQL schema
│   └── seeds/                # Sample data
├── docker/                   # Nginx, PHP, MySQL configs
├── docs/                     # API documentation
├── public/                   # Web root
│   ├── index.php
│   ├── assets/               # CSS, JS
│   └── uploads/              # رسانه‌های آپلود شده
├── resources/views/          # PHP templates (admin + player)
│   └── player/
│       ├── pair.php          # صفحه pairing (cookie-based)
│       ├── not_found.php     # 404 پلیر
│       └── profiles/         # modern, android_tv, lg_tv, kiosk, ...
├── routes/
│   ├── web.php               # Web + player routes
│   └── api.php               # API routes
└── docker-compose.yml
```

---

## 🔌 API خلاصه

**Base URL:** `http://server/api/v1`  
**Auth:** `Authorization: Bearer <JWT>`

```bash
# Login
POST /api/v1/auth/login
{ "email": "...", "password": "..." }

# Screens
GET  /api/v1/screens
POST /api/v1/screens/{code}/heartbeat
GET  /api/v1/screens/{code}/playlist

# Player binding
GET  /player/                   # صفحه pairing (بدون auth)
GET  /player/{SCRXXXXX}         # bind + پلیر
POST /player/activate           # فعال‌سازی با activation code

# FIDS
GET  /api/v1/fids/ping?airport_id=2    # بررسی اتصال
POST /api/v1/fids/sync-live            # fetch + ذخیره (auth)
GET  /api/v1/fids/cron-sync?token=...  # cron (بدون auth)
GET  /api/v1/fids/live?airport_id=2    # خواندن از cache
```

📄 **مستندات کامل API:** [`docs/API.md`](docs/API.md)

---

## 🐳 سرویس‌های Docker

| سرویس | Port | توضیح |
|-------|------|-------|
| nginx | 80 | Web server |
| php-fpm | 9000 | PHP 8.2 |
| mysql | 3306 | MySQL 8 |
| redis | 6379 | Cache & Session |
| websocket | 8080 | Real-time server |
| phpmyadmin | 8081 | مدیریت DB |

---

## 🖥️ نیازمندی‌های سرور

### مصرف پایه سیستم (بدون کلاینت)

| سرویس | RAM |
|-------|-----|
| Nginx | ~50 MB |
| PHP-FPM | ~300 MB |
| WebSocket Server | ~50 MB |
| MySQL 8 | ~350 MB |
| Redis | ~30 MB |
| **جمع پایه** | **~800 MB** |

### منابع به‌ازای هر ۵ کلاینت

| منبع | اضافه می‌شود | توضیح |
|------|------------|-------|
| RAM | +200 MB | WebSocket × 5 + PHP sessions |
| CPU | +5–10% | polling هر ۳۰ ثانیه + heartbeat |
| Bandwidth | **+25 Mbps** | هر کلاینت ویدیو ۱۰۸۰p ≈ 5 Mbps |

### توصیه عملی

| تعداد صفحه | CPU | RAM | SSD | اینترنت (Upload) |
|-----------|-----|-----|-----|-----------------|
| **۵ صفحه (حداقل)** | 2 هسته | 2 GB | 40 GB | 30 Mbps |
| **۵ صفحه (پیشنهادی)** | 2 هسته | 4 GB | 100 GB | 50 Mbps |
| **۲۰ صفحه** | 4 هسته | 6 GB | 200 GB | 100 Mbps |
| **۵۰ صفحه** | 8 هسته | 12 GB | 500 GB | 250 Mbps |

### مصرف پهنای باند بر اساس نوع محتوا

| نوع محتوا | هر کلاینت | ۵ کلاینت | ۲۰ کلاینت |
|-----------|----------|----------|----------|
| تصویر / اسلاید | < 1 Mbps | ~3 Mbps | ~10 Mbps |
| ویدیو 720p | ~2 Mbps | ~10 Mbps | ~40 Mbps |
| ویدیو 1080p | ~5 Mbps | ~25 Mbps | ~100 Mbps |
| ویدیو 4K | ~20 Mbps | ~100 Mbps | ~400 Mbps |

> 💡 اگر کلاینت‌ها ویدیو را **یک‌بار دانلود و کش** کنند، مصرف bandwidth به‌شدت کاهش می‌یابد.

---

## ⚙️ متغیرهای محیطی

```env
APP_NAME=SignageCMS
APP_URL=http://localhost
APP_DEBUG=false
APP_TIMEZONE=Asia/Tehran

DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=signage_cms
DB_USERNAME=signage_user
DB_PASSWORD=StrongPassword123!

JWT_SECRET=your-super-secret-32-char-min-key
JWT_EXPIRY=86400

WS_HOST=0.0.0.0
WS_PORT=8080
WS_ALLOWED_ORIGINS=*

REDIS_HOST=redis
REDIS_PORT=6379

# FIDS Module
FIDS_TIMEOUT=10
FIDS_CACHE_TTL=60
FIDS_HTTP_PROXY=          # http://proxy-ip:port (برای سرور خارج ایران)
```

---

## 🔐 سطوح دسترسی

| نقش | دسترسی |
|-----|--------|
| `super_admin` | همه چیز |
| `admin` | همه به جز tenant management |
| `manager` | صفحات، پلی‌لیست، رسانه |
| `editor` | رسانه، پلی‌لیست |
| `viewer` | فقط خواندن |

---

## 🛠️ تکنولوژی‌ها

- **Backend:** PHP 8.2, MVC (no framework)
- **Database:** MySQL 8
- **Cache:** Redis
- **Real-time:** Ratchet WebSocket
- **Auth:** JWT + Session (dual)
- **Container:** Docker + Nginx + PHP-FPM
- **Player Profiles:** modern, android_tv, lg_tv, samsung_tv, kiosk, minimal, legacy

---

## 📋 Changelog

### v1.5.1
- ✅ Android Player — fix Java package import errors (UpdateService, MainActivity)
- ✅ Windows Player — fix CI rename step با absolute path
- ✅ VOD — تبدیل خودکار ویدیو به فرمت TS + محدودیت ۵ گیگابایت
- ✅ GitHub Actions — ارتقاء به Node.js 24 (checkout@v6, upload-artifact@v7)
- ✅ Release pipeline — ساخت خودکار APK + EXE + ZIP با یک tag

### v1.5.0
- ✅ Cookie-based screen binding — آدرس ثابت `/player/` برای همه دستگاه‌ها
- ✅ FIDS auto-sync از fids.airport.ir با Cron Job
- ✅ صفحه pairing با کد فعال‌سازی
- ✅ Fix: trailing slash 404 در Router
- ✅ Fix: JSON body در `Request::input()` برای PUT/POST
- ✅ بروزرسانی دکمه refresh پروازها با نمایش خطا

### v1.4.x
- ✅ IPTV & In-Flight Display modules
- ✅ Media transcoder
- ✅ VOD module
- ✅ Screen groups

### v1.3.0
- ✅ FIDS module اولیه
- ✅ Emergency broadcast
- ✅ Android TV player profile

---

## 📄 License

MIT License — See [LICENSE](LICENSE)

---

<div align="center">
Made with ❤️ for digital signage professionals
</div>
