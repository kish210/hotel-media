# Hotel Media REST API Documentation
**Base URL:** `https://your-domain.com/api/v1`  
**Auth:** Bearer token (JWT) in `Authorization` header  
**Content-Type:** `application/json`

---

## Authentication

### POST /auth/login
```json
{ "email": "admin@example.com", "password": "Admin@123456" }
```
**Response:**
```json
{
  "success": true,
  "data": {
    "token": "eyJ...",
    "expires_in": 86400,
    "user": { "id": 1, "name": "Admin", "role": "super_admin" }
  }
}
```

### GET /auth/me
Returns current authenticated user info.

### POST /auth/logout
Invalidates current session.

---

## Screens

### GET /screens
Query params: `page`, `per_page`, `status`, `location_id`, `search`, `online`

### POST /screens
```json
{ "name": "Menu Screen", "orientation": "landscape", "location_id": 1 }
```

### GET /screens/stats
```json
{ "total": 5, "online": 3, "offline": 2, "active": 4, "error": 1 }
```

### GET /screens/{id}
### PUT /screens/{id}
### DELETE /screens/{id}

### POST /screens/{id}/activation
Generate 6-digit activation code (expires in 10 min).

### POST /screens/{id}/command
```json
{ "command": "refresh" }          // Options: reboot, refresh, emergency
{ "command": "emergency", "payload": "Fire drill!" }
```

---

## Screen Player (No Auth Required)

### POST /screens/{code}/heartbeat
Called every 15s by the player.
```json
{
  "version": "1.0.0",
  "cpu": 23.5,
  "memory": 45.2,
  "disk": 60.1,
  "uptime": 3600,
  "current_item": "media_name"
}
```
**Response includes pending commands:**
```json
{
  "data": {
    "commands": [{"cmd": "refresh"}, {"cmd": "emergency", "data": "Alert!"}],
    "playlist_id": 3,
    "sync_interval": 30
  }
}
```

### GET /screens/{code}/playlist
Returns full playlist with items for the player.

---

## Media

### GET /media
Query: `page`, `type` (image|video|url), `search`, `folder`

### POST /media/upload
`multipart/form-data`: `file`, `name`, `folder`

### POST /media/url
```json
{ "name": "Restaurant Website", "url": "https://example.com", "folder": "urls" }
```

### GET /media/storage
```json
{ "used": 524288000, "limit": 5368709120, "percent": 9.8 }
```

### DELETE /media/{id}

---

## Playlists

### GET /playlists
### POST /playlists
```json
{
  "name": "Lunch Menu",
  "layout_id": 2,
  "default_duration": 15,
  "transition": "fade",
  "loop": 1,
  "shuffle": 0,
  "items": [
    { "media_id": 1, "duration": 10, "zone_id": "left" },
    { "media_id": 2, "duration": 30, "zone_id": "left" }
  ]
}
```
### GET /playlists/{id}
### PUT /playlists/{id}
### DELETE /playlists/{id}

---

## Schedules

### GET /schedules
### POST /schedules
```json
{
  "name": "Morning Menu",
  "playlist_id": 1,
  "screen_id": null,
  "type": "daily",
  "start_time": "08:00:00",
  "end_time": "12:00:00",
  "priority": 8
}
```
Types: `always`, `once`, `daily`, `weekly`, `monthly`

### DELETE /schedules/{id}

---

## Menu Board (Restaurant)

### GET /menu/categories
### GET /menu/items?category_id={id}

---

## Dashboard

### GET /dashboard/stats
```json
{
  "screens": { "total": 5, "online": 3 },
  "storage": { "used": 524288000, "percent": 9.8 },
  "playlists": 8,
  "schedules": 12
}
```

---

## Error Responses
```json
{ "success": false, "message": "Error description", "errors": {} }
```

| Code | Meaning |
|------|---------|
| 200  | OK |
| 201  | Created |
| 400  | Bad Request |
| 401  | Unauthorized |
| 403  | Forbidden |
| 404  | Not Found |
| 422  | Validation Error |
| 429  | Rate Limited |
| 500  | Server Error |

---

## Android TV / Mobile App Integration

1. Call `POST /auth/login` → get JWT token
2. Register screen: `POST /screens` → get `screen_code`
3. Poll `GET /screens/{code}/playlist` every 30s
4. Send `POST /screens/{code}/heartbeat` every 15s
5. Handle commands returned by heartbeat (`reboot`, `refresh`, `emergency`)
6. Connect WebSocket `ws://host:8080` → subscribe to `screen_{code}` channel

---

## Module System API

### GET /modules
Lists all modules with installation status, zone types, and stats.

### GET /modules/{id}
Single module info including zone_types and settings.

### POST /modules/{id}/install
Install a module and run its database migrations.
Returns `201` on success.

### POST /modules/{id}/toggle
```json
{ "enable": true }
```

### PUT /modules/{id}/settings
Save module-specific settings (key-value pairs depending on module).

### GET /modules/{id}/preview?zone={zone_type}
Returns rendered HTML widget for a zone type.
```json
{ "data": { "html": "<div>...</div>", "zone_type": "fids_departures" } }
```

### GET /modules/zone-types
All zone types from all installed modules — use in layout designer.

---

## FIDS API

### GET /fids/flights?type=departure&limit=15
Returns today's flights sorted by scheduled time.

Query params: `type` (departure|arrival), `limit`, `gate`

### POST /fids/flights
```json
{
  "flight_number": "IR123",
  "airline_code": "IR",
  "airline_name": "ایران ایر",
  "type": "departure",
  "destination": "مشهد",
  "destination_code": "MHD",
  "scheduled_time": "2024-01-15 14:30:00",
  "gate": "A5",
  "terminal": "T1",
  "status": "scheduled"
}
```

### POST /fids/flights/{id}/status
```json
{ "status": "boarding", "gate": "A7", "delay_minutes": 0 }
```
Status options: `scheduled`, `boarding`, `departed`, `arrived`, `delayed`, `cancelled`, `diverted`, `gate_change`

### GET /fids/stats — Today's flight statistics

---

## Hotel API

### GET /hotel/info
### POST /hotel/info — Save hotel information
### GET /hotel/events — Upcoming events
### POST /hotel/events — Create event
### GET /hotel/amenities — Hotel amenities list
### GET /hotel/room-service?category={cat}
### GET /hotel/attractions
### GET /hotel/weather — Current weather (uses OpenWeatherMap)

---

## Corporate API

### GET /corporate/kpi — KPI list
### POST /corporate/kpi
```json
{ "name": "فروش ماهانه", "value": "125000", "target": "150000", "unit": "تومان", "change_pct": 8.3, "icon": "fas fa-chart-line", "color": "#22c55e" }
```
### GET /corporate/news — Pinned + recent news
### GET /corporate/departments — Building directory

---

## Retail API

### GET /retail/products?offer=1&featured=1&category={cat}
### POST /retail/products
```json
{ "name": "شیر گاو", "category": "لبنیات", "price": 45000, "old_price": 52000, "is_offer": 1, "offer_ends": "2024-02-01 00:00:00" }
```
### GET /retail/queue?counter={name} — Current queue number
### POST /retail/queue/call — Call next ticket
### GET /retail/currency?pairs=USD,EUR,GBP — Exchange rates

---

## Transport API

### GET /transport/schedules?type=bus&station={name}&limit=15
### POST /transport/schedules
```json
{ "type": "bus", "line": "خط ۱۴", "direction": "میدان انقلاب", "station": "ایستگاه مرکزی", "departure": "08:30:00" }
```

---

## IPTV Rooms API

> Auth: JWT Bearer — مدیریت اتاق‌های هتل

### GET /iptv/rooms
### POST /iptv/rooms
```json
{ "room_number": "101", "room_name": "اتاق دلوکس", "floor": 1, "room_type": "double" }
```
### GET /iptv/rooms/{id}
### PUT /iptv/rooms/{id}
### DELETE /iptv/rooms/{id}

### POST /iptv/rooms/{id}/checkin
```json
{
  "guest_name":  "علی محمدی",
  "guest_email": "ali@example.com",
  "language":    "fa",
  "nights":      3,
  "send_welcome": true,
  "welcome_msg":  "خوش‌آمدید آقای محمدی!"
}
```

### POST /iptv/rooms/{id}/checkout
Clears guest info and deactivates all room messages.

### POST /iptv/rooms/{id}/message
```json
{
  "title":         "پیام از مدیریت",
  "body":          "لطفاً با پذیرش تماس بگیرید",
  "mode":          "popup",
  "msg_type":      "info",
  "expires_hours": 2
}
```
**mode options:** `banner` | `popup` | `ticker`  
**msg_type options:** `info` | `welcome` | `urgent` | `promo` | `custom`

### GET /iptv/rooms/{id}/messages
Returns all active messages for the room.

### POST /iptv/rooms/broadcast
```json
{ "title": "اعلان هتل", "body": "شام از ساعت ۷ تا ۱۰ سرو می‌شود", "mode": "banner" }
```
Sends to **all** rooms simultaneously.

### DELETE /iptv/room-messages/{msgId}
### POST /iptv/room-messages/{msgId}/deactivate

### GET /iptv/pms — List PMS integration keys (JWT)
### POST /iptv/pms — Create API key (JWT)
```json
{ "name": "Opera PMS", "description": "Main hotel PMS" }
```
### DELETE /iptv/pms/{pmsId}

---

## PMS External API

> Auth: `X-PMS-Key: YOUR_API_KEY` header — **no JWT required**

### POST /pms/checkin
```json
{
  "room_number": "101",
  "guest_name":  "John Smith",
  "language":    "en",
  "nights":      2,
  "check_in":    "2026-05-23",
  "check_out":   "2026-05-25"
}
```

### POST /pms/checkout
```json
{ "room_number": "101" }
```

### POST /pms/message
```json
{
  "room_number":   "101",
  "title":         "Reception",
  "body":          "Your luggage is ready at the lobby",
  "mode":          "popup",
  "expires_hours": 1
}
```

---

## Player Room Info (Public)

> No auth required — called by IPTV player every 30s

### GET /player/room-info/{screen_code}
```json
{
  "room": {
    "id": 1,
    "room_number": "101",
    "status": "occupied",
    "guest_name": "علی محمدی"
  },
  "messages": [
    {
      "id": 5,
      "title": "پیام پذیرش",
      "body": "کابل برق آماده است",
      "mode": "popup",
      "msg_type": "info"
    }
  ]
}
```

---

## In-Flight Display API

> Auth: JWT Bearer for admin/control | No auth for player endpoint

### GET /inflight
Returns all flights for the tenant.

### POST /inflight
```json
{
  "flight_number":    "IRA711",
  "airline_name":     "Iran Air",
  "origin_iata":      "IKA",
  "origin_city":      "Tehran",
  "origin_country":   "Iran",
  "origin_lat":       35.4161,
  "origin_lng":       51.1522,
  "origin_timezone":  "Asia/Tehran",
  "dest_iata":        "DXB",
  "dest_city":        "Dubai",
  "dest_country":     "UAE",
  "dest_lat":         25.2528,
  "dest_lng":         55.3644,
  "dest_timezone":    "Asia/Dubai",
  "departure_at":     "2026-05-23 10:00:00",
  "arrival_at":       "2026-05-23 12:30:00",
  "accent_color":     "#00b4d8",
  "bg_style":         "space",
  "welcome_msg":      "خوش‌آمدید — لطفاً کمربند ایمنی ببندید"
}
```

### GET /inflight/{id}
### PUT /inflight/{id} — Full update
### DELETE /inflight/{id}

### PUT /inflight/{id}/live — Update telemetry only (fast, no full update)
```json
{
  "phase":        "cruise",
  "progress_pct": 65,
  "altitude_ft":  36000,
  "speed_kmh":    870,
  "heading_deg":  142
}
```
**phase values:** `preflight` | `taxi` | `takeoff` | `climb` | `cruise` | `descent` | `approach` | `landing` | `landed`

### GET /inflight/player/{id} — **No auth** — for player screen
```json
{
  "flight_number": "IRA711",
  "phase": "cruise",
  "progress_pct": 65,
  "altitude_ft": 36000,
  "speed_kmh": 870,
  "origin_iata": "IKA",
  "dest_iata": "DXB",
  "dist_km": 1200,
  "eta_mins": 29,
  "server_time_utc": "2026-05-23T10:35:00+00:00"
}
```

---

## Raspberry Pi Bridge API

> Runs on Raspberry Pi at port 5055 — called by Hotel Media backend (server-side proxy)

### GET :5055/api/status — Full status
```json
{
  "version": "1.0",
  "uptime_s": 3600,
  "gps": {
    "fix": true, "mode": 3,
    "lat": 32.4, "lng": 53.6,
    "alt_m": 10972.5, "alt_ft": 36000,
    "speed_kmh": 870.0, "heading": 142.3,
    "satellites_used": 12
  },
  "adsb": { "total": 4, "updated_at": "..." },
  "push": { "push_enabled": true, "last_push_at": "...", "push_count": 120 }
}
```

### GET :5055/api/gps — GPS only (fast)
### GET :5055/api/adsb — ADS-B aircraft list
### GET :5055/api/health — Health check `{"ok": true, "gps_fix": true}`

### POST :5055/api/config — Update config
```json
{
  "hotelmedia_url":  "https://your-server.com",
  "flight_id":       1,
  "api_token":       "JWT_TOKEN",
  "push_enabled":    true,
  "push_interval":   10
}
```

---

## Hotel Media → RPi Proxy Endpoints

> Auth: JWT — Hotel Media fetches from RPi on behalf of admin

### GET /inflight/{id}/rpi-status
Proxies `GET :5055/api/status` from the flight's saved RPi IP.

### POST /inflight/{id}/rpi-sync
Fetches GPS data from RPi and updates flight telemetry.  
Also auto-calculates `progress_pct` from GPS position on great-circle route.

### POST /inflight/{id}/rpi-save
```json
{ "rpi_ip": "192.168.1.100", "rpi_port": 5055 }
```

### POST /inflight/{id}/rpi-push-config
Sends Hotel Media connection config to RPi.
```json
{
  "cms_url":       "https://your-server.com",
  "api_token":     "JWT_TOKEN",
  "push_enabled":  true,
  "push_interval": 10
}
```

---

## Guest Services API — خدمات مهمان

گردش‌کار کامل سفارش از تلویزیون اتاق تا صف کاری کارکنان.
جدول‌ها: `guest_services`, `guest_requests`, `guest_request_items`, `guest_request_log`
(migration `017_guest_services.sql`).

**دسته‌ها:** `room_service` · `breakfast` · `housekeeping` · `laundry` · `taxi` ·
`maintenance` · `wakeup` · `feedback` · `other`

**وضعیت‌ها و گذارهای مجاز:**

```
pending  → accepted | cancelled
accepted → in_progress | done | cancelled
in_progress → done | cancelled
done / cancelled → (نهایی)
```

### کاتالوگ خدمات (JWT یا session)

### GET /guest/services
پارامترها: `category`, `active`

### POST /guest/services
```json
{ "name_fa": "صبحانه کامل", "category": "breakfast", "price": 450000,
  "unit": "پرس", "available_from": "06:00", "available_to": "10:00" }
```

### PUT /guest/services/{id}
### DELETE /guest/services/{id}
حذف سرویس، اقلام سفارش‌های قبلی را خراب نمی‌کند (`service_id` خالی می‌شود، نام در `name_snapshot` می‌ماند).

### صف درخواست‌ها (JWT یا session)

### GET /guest/requests
پارامترها: `status` (`open` = همه‌ی بازها), `category`, `room`, `from`, `to`, `page`, `per_page`

### GET /guest/requests/stats
```json
{ "pending": 3, "in_progress": 2, "done_today": 14, "revenue_today": 8400000,
  "avg_minutes": 11.4, "avg_rating": 4.6, "by_category": [...], "wakeups_next": [...] }
```

### GET /guest/requests/{id}
شامل `items` و `log` (تاریخچه‌ی کامل تغییر وضعیت).

### PUT /guest/requests/{id}/status
```json
{ "status": "accepted", "assigned_to": 5, "staff_note": "..." }
```
گذار نامعتبر → `422`.

---

## Guest Portal (Public) — تلویزیون اتاق

بدون JWT. هویت با **کد صفحه‌نمایش** (`screens.code`) که به یک اتاق
(`screens.iptv_room_id`) متصل است. اگر صفحه به اتاقی وصل نباشد → `404`.

### GET /guest/{screen_code}/services
```json
{ "success": true, "data": {
    "room_number": "101", "guest_name": "...", "guest_lang": "fa",
    "categories": { "breakfast": [ { "id": 8, "name_fa": "صبحانه کامل",
      "price": 450000, "available_now": true } ] } } }
```
`available_now` بازه‌ی سرویس‌دهی را (حتی بازه‌های عبوری از نیمه‌شب) محاسبه می‌کند.

### POST /guest/{screen_code}/requests
```json
{ "category": "room_service",
  "items": [ { "service_id": 8, "qty": 2 } ],
  "note": "بدون نمک",
  "scheduled_at": "2026-09-22 07:30" }
```
- فقط وقتی اتاق `occupied` است → در غیر این صورت `409`
- `wakeup`: `scheduled_at` الزامی و باید در آینده باشد
- `feedback`: `rating` بین ۱ تا ۵ الزامی
- `room_service` / `laundry` / `breakfast`: حداقل یک قلم الزامی
- قیمت‌ها **سمت سرور** از کاتالوگ خوانده می‌شود، نه از بدنه‌ی درخواست
- محدودیت: حداکثر ۱۰ درخواست باز و ۵ درخواست در ۱۰ دقیقه برای هر اتاق → `429`

### GET /guest/{screen_code}/requests
فقط درخواست‌های **اقامت جاری** (از `check_in_at` به بعد) — مهمان قبلی دیده نمی‌شود.

### POST /guest/{screen_code}/requests/{id}/cancel
فقط وقتی هنوز `pending` است؛ بعد از پذیرش → `409`.

---

## EPG API — راهنمای الکترونیکی برنامه‌ها

جدول‌ها: `epg_programs`, `epg_sources`, `epg_channel_map` (migration `018_epg.sql`).
کلید تطبیق روی کانال، ستون موجود `iptv_channels.epg_id` است.

**منابع پشتیبانی‌شده:** `tvheadend` (از `/api/epg/events/grid`) ·
`xmltv_url` (آدرس اینترنتی) · `xmltv_file` (فایل داخل `storage/`)

**ترتیب تطبیق کانال:** نگاشت دستی (`epg_channel_map`) ← `iptv_channels.epg_id`
← `iptv_channels.tvh_uuid` ← نام یکسان کانال.

### مدیریت منابع (JWT یا session)

### GET /epg/sources
هر منبع به‌همراه `last_sync_at`، `last_sync_msg` و تعداد برنامه‌های پیش‌رو.

### POST /epg/sources
```json
{ "name": "تی‌وی‌هدند اصلی", "source_type": "tvheadend",
  "tvh_source_id": 1, "days_ahead": 7 }
```
```json
{ "name": "XMLTV ملی", "source_type": "xmltv_url",
  "url": "https://example.com/epg.xml", "days_ahead": 3 }
```

### POST /epg/sources/{id}/sync
همگام‌سازی دستی. منبع ناموفق → `502` با پیام خطا.

### DELETE /epg/sources/{id}
منبع و همه‌ی برنامه‌های آن را حذف می‌کند.

### جدول پخش (JWT یا session)

### GET /epg/grid
پارامترها: `from` (پیش‌فرض الان)، `hours` (۱ تا ۴۸، پیش‌فرض ۶)،
`channel_id` (اختیاری، چند شناسه با کاما).
خروجی بر اساس کانال گروه‌بندی شده — همان شکلی که رابط جدول EPG می‌خواهد.

### GET /epg/channel/{id}
برنامه‌های یک کانال تا `days` روز جلوتر (۱ تا ۱۴، پیش‌فرض ۲).

### GET /epg/now
برای هر کانال: برنامه‌ی در حال پخش، برنامه‌ی بعدی و `progress` (درصد پیشرفت).

---

## EPG (Public) — تلویزیون اتاق

### GET /player/epg/{screen_code}
بدون JWT. `tenant` از روی خود صفحه‌نمایش گرفته می‌شود.
با `?current_only=1` فقط کانالی که همین صفحه روی آن است برمی‌گردد.

```json
{ "success": true, "data": [
  { "channel_id": 4, "channel_name": "شبکه یک", "logo_url": "...",
    "now":  { "title": "اخبار ساعت ۲۰", "starts_at": "...", "ends_at": "...", "minutes": 60 },
    "next": { "title": "فیلم سینمایی", "starts_at": "..." },
    "progress": 42 } ] }
```

### همگام‌سازی خودکار

```bash
php artisan epg:sync        # همه منابع فعال همه tenant ها
php artisan epg:sync 3      # فقط tenant شماره ۳
```

روی ویندوز با Task Scheduler و روی لینوکس با cron، روزی یک‌بار اجرا کنید.
رویدادهای گذشته‌ی بیش از ۲ روز خودکار پاک می‌شوند تا جدول رشد بی‌پایان نکند.

---

## Portal API — صفحه اصلی تلویزیون اتاق

همه‌چیزِ لازم برای رندر صفحه‌ی اصلی در **یک درخواست**. بدون JWT؛ هویت با
کد صفحه‌نمایش، چون ست‌تاپ‌باکس چیزی جز کد خودش نمی‌داند.
(migration `019_portal_experience.sql` — فاز ۳ نقشه‌راه)

### GET /portal/{screen_code}

پارامتر اختیاری `lang` زبان اتاق را override می‌کند.

```json
{ "success": true, "data": {
  "screen":   { "code": "TV101", "name": "TV اتاق ۱۰۱" },
  "room":     { "room_number": "101", "occupied": true, "guest_name": "آقای احمدی" },
  "branding": { "logo_url": "...", "accent_color": "#0ea5e9",
                "backgrounds": ["/uploads/s1.jpg", "/uploads/s2.jpg"],
                "bg_dim": 0.4, "bg_blur": 4,
                "welcome_title": "...", "welcome_sub": "...",
                "ticker": { "text": "...", "color": "#fff", "bg": "#111", "speed": 30 } },
  "menu":     [ { "id": 3, "type": "live", "label": "Live TV",
                  "icon": "fas fa-tv", "color": "#ef4444", "shortcut_key": 1 } ],
  "header":   { "widgets": ["clock","weather","prayer"],
                "data": { "server_time": "...", "weather": {...}, "prayer": {...} } },
  "lang":     "en",
  "strings":  { "welcome": "Welcome", "live_tv": "Live TV" } } }
```

**انتخاب منو** به ترتیب: منوی مستقیم صفحه (`screens.iptv_menu_id`) ←
منوی گروه صفحه ← اولین منوی فعال tenant. صفحه‌ای که به هیچ منویی وصل نیست
هم با برندینگ پیش‌فرض رندر می‌شود، نه خطا.

**زبان**: `lang` در query ← `iptv_rooms.guest_lang` ← فارسی.
کلیدهایی که در زبان مهمان ترجمه ندارند از فارسی پر می‌شوند تا رابط
نیمه‌خالی نماند. زبان‌های پایه: `fa`, `en`, `ar` (جدول `portal_translations`).

**میانبر عددی** (`shortcut_key`): عدد ۰ تا ۹ روی ریموت. عدد تکراری یا
خارج از بازه `null` برمی‌گردد — اولین آیتم مالک آن عدد است.

### GET /portal/{screen_code}/live

فقط داده‌های زنده‌ی نوار بالا. پلیر این را هر چند دقیقه صدا می‌زند بدون
اینکه کل صفحه را دوباره بگیرد.

**کش:** آب‌وهوا ۱۵ دقیقه، نرخ ارز ۱۰ دقیقه، اوقات شرعی ۱۲ ساعت
(جدول `portal_live_cache`). بدون کش، یک هتل ۲۰۰ اتاقه صدها درخواست بیرونی
در دقیقه می‌سازد. اگر واکشی تازه شکست بخورد، مقدار کش منقضی برگردانده
می‌شود — نوار کمی کهنه بهتر از نوار خالی است.

**تنظیمات `.env`:**

| متغیر | کاربرد |
|-------|--------|
| `WEATHER_API_KEY` | OpenWeather — بدون آن ویجت آب‌وهوا نمایش داده نمی‌شود |
| `WEATHER_DEFAULT_CITY` | شهر پیش‌فرض وقتی روی منو تعیین نشده |
| `CURRENCY_API_URL` | آدرس JSON نرخ ارز به شکل `{"usd":000,"eur":000}` |
| `PRAYER_COUNTRY` | کشور برای اوقات شرعی (پیش‌فرض `Iran`، منبع Aladhan با روش ۷ — ژئوفیزیک تهران) |

---

## Folio API — صورتحساب اتاق

مینی‌بار، سفارش‌ها، خرید فیلم و خروج سریع همه به یک دفتر بدهکاری مشترک
می‌نشینند (migration `022` و `023`).

**قاعده‌ی محوری:** هر قلم به **اقامت** گره می‌خورد (`stay_started_at`)، نه فقط
به اتاق. بدون این، مهمان تازه صورتحساب مهمان قبلی را روی تلویزیون می‌دید.

### مهمان (بدون JWT، با کد صفحه‌نمایش)

| مسیر | کار |
|------|-----|
| `GET /guest/{code}/folio` | صورتحساب اقامت جاری |
| `POST /guest/{code}/checkout` | درخواست خروج سریع |
| `GET /guest/{code}/vod/{id}/access` | آیا اجازه‌ی پخش دارد؟ |
| `POST /guest/{code}/vod/{id}/purchase` | خرید فیلم → روی صورتحساب |
| `GET /guest/{code}/menus` | منوهای تصویری |

### کارکنان (JWT یا session)

| مسیر | کار |
|------|-----|
| `GET /rooms/{id}/folio` | صورتحساب کامل با جزئیات |
| `POST /rooms/{id}/charges` | ثبت قلم دستی |
| `POST /charges/{id}/void` | ابطال (حذف نمی‌شود — حسابرسی) |
| `POST /rooms/{id}/minibar` | ثبت شمارش خانه‌داری |
| `GET|POST|PUT|DELETE /minibar/items` | کاتالوگ مینی‌بار |
| `GET /checkout-requests` | صف خروج سریع |
| `POST /checkout-requests/{id}` | تایید یا رد خروج |

**مینی‌بار:** خانه‌دار موجودی **فعلی** یخچال را ثبت می‌کند؛ تفاضل با
`par_level` مصرف است و خودکار روی صورتحساب می‌نشیند.

```json
POST /rooms/12/minibar
{ "items": { "3": 0, "5": 2 } }
```

**خروج سریع:** با تایید پذیرش، اقلام `settled` می‌شوند، اتاق آزاد می‌شود و
دسترسی فیلم‌های خریداری‌شده تمام می‌شود.

---

## اتصال خروجی به PMS

تا نسخه‌ی قبل، اتصال PMS یک‌طرفه بود: هتل به ما checkin و checkout می‌فرستاد.
حالا اقلام صورتحساب به **PMS فرستاده** می‌شوند.

### تنظیم

پنل ← اتاق‌های IPTV ← یکپارچه‌سازی PMS، یا مستقیم روی `pms_integrations`:

| فیلد | توضیح |
|------|-------|
| `charge_url` | آدرس ثبت قلم در PMS |
| `auth_type` | `none` · `bearer` · `basic` · `header` |
| `auth_secret` | توکن یا `user:pass` |
| `auth_header` | نام هدر وقتی `auth_type=header` |
| `push_charges` | `1` تا ارسال خودکار فعال شود |
| `field_map` | JSON نگاشت نام فیلدها |

**نگاشت نام فیلد** — هر PMS اسم دیگری دارد:

```json
{ "room": "RoomNo", "amount": "Amount", "description": "Narrative" }
```

بدنه‌ی ارسالی پیش‌فرض:

```json
{ "room_number": "101", "amount": 150000, "description": "روم‌سرویس",
  "quantity": 2, "currency": "IRR", "reference": "hm-charge-84",
  "category": "room_service", "posted_at": "2026-09-21T20:14:00+03:30" }
```

`reference` یکتاست تا اگر پاسخ گم شد و دوباره فرستادیم، PMS بتواند تکراری را
تشخیص دهد.

### رفتار در قطعی

ارسال **همیشه پس‌زمینه** است. اگر ثبت مینی‌بار منتظر PMS بماند، خانه‌دار
پشت صفحه‌ی بارگذاری گیر می‌کند. قلمی که نرفته در صف می‌ماند و بعداً می‌رود؛
هیچ قلمی به‌خاطر شبکه گم نمی‌شود. بعد از ۵ تلاش ناموفق، `failed` می‌شود تا
اپراتور ببیند. جدول `pms_push_log` همه‌ی تلاش‌ها را نگه می‌دارد.

```bash
php artisan pms:push        # صف را می‌فرستد — cron هر ۵ دقیقه
php artisan pms:push 3      # فقط tenant شماره ۳
```

| مسیر | کار |
|------|-----|
| `POST /pms/test` | آزمایش اتصال بدون ثبت قلم واقعی |
| `POST /pms/push` | ارسال دستی صف |
| `POST /charges/{id}/pms-retry` | تلاش دوباره روی یک قلم |

---

## Menu Board API — منوی تصویری

‏IT هتل عکس منوی چاپی را بارگذاری می‌کند و همان روی تلویزیون نمایش داده
می‌شود. برای هتلی که نمی‌خواهد تک‌تک اقلام را وارد سیستم کند، راه‌اندازی را
از چند روز به چند دقیقه می‌رساند.

| مسیر | کار |
|------|-----|
| `GET|POST /menu-boards` | فهرست و ساخت منو |
| `PUT|DELETE /menu-boards/{id}` | ویرایش و حذف (با فایل‌ها) |
| `POST /menu-boards/{id}/pages` | بارگذاری تصویر (multipart، چندفایلی با `image[]`) |
| `POST /menu-boards/{id}/pages/sort` | ترتیب صفحه‌ها |
| `DELETE /menu-board-pages/{pageId}` | حذف یک صفحه |

**دسته‌ها:** `restaurant` · `room_service` · `laundry` · `minibar` ·
`breakfast` · `spa` · `other`

**محدودیت‌ها:** JPG، PNG یا WebP · حداکثر ۱۵ مگابایت هر صفحه · حداکثر ۳۰ فایل
در هر بارگذاری. تصویر پهن‌تر از ۲۵۶۰ پیکسل خودکار کوچک می‌شود — عکس موبایل
معمولاً ۴۰۰۰ پیکسل است و روی تلویزیون فقط بوت را کند می‌کند.

> PDF عمداً پشتیبانی نمی‌شود: تلویزیون هتل نمی‌تواند آن را رندر کند.

---

## کنترل دسترسی کانال و قفل والدین

دو لایه‌ی **مستقل** روی کانال‌ها (migration `025`):

| لایه | چه کسی تصمیم می‌گیرد | رفتار |
|------|---------------------|-------|
| **سطح اتاق** (`access_level`) | هتل | کانال بالاتر از سطح اتاق **اصلا در فهرست نمی‌آید** |
| **قفل والدین** | مهمان | کانال دیده می‌شود ولی بدون آدرس؛ با رمز باز می‌شود |

تفاوت عمدی است: مهمان نباید حتی بداند کانال VIP وجود دارد، ولی کانال
قفل‌شده باید دیده شود تا بتواند بازش کند.

### GET /guest/{code}/channels

```json
{ "channels": [
    { "id": 4, "name": "...", "channel_no": 1, "is_radio": false,
      "locked": false, "stream_url": "..." },
    { "id": 9, "name": "...", "locked": true,
      "stream_url": null, "multicast_url": null } ],
  "parental_active": true, "unlocked": false }
```

**آدرس کانال قفل‌شده `null` است.** اگر فرستاده می‌شد، قفل فقط ظاهری بود و
با خواندن پاسخ API دور زده می‌شد. همین قاعده در `GET /portal/{code}` هم
اعمال می‌شود (`via: "locked"`).

### قفل والدین

| مسیر | کار |
|------|-----|
| `POST /guest/{code}/parental/pin` | تنظیم یا تغییر رمز — تغییر نیاز به `current_pin` دارد |
| `DELETE /guest/{code}/parental/pin` | خاموش کردن، با رمز فعلی |
| `POST /guest/{code}/parental/unlock` | باز کردن با رمز → توکن |

رمز **۴ رقمی** و با bcrypt ذخیره می‌شود؛ رمزهای `0000`، `1111`، `1234` و
`9999` رد می‌شوند. بعد از **۵ تلاش ناموفق**، ورود رمز برای آن اتاق ۵ دقیقه
قفل می‌شود.

باز کردن یک **توکن امضاشده** می‌دهد (HMAC با `APP_KEY`، اعتبار ۲ ساعت) که
پلیر در هدر `X-Parental-Token` می‌فرستد — وگرنه مهمان برای هر تعویض کانال
باید رمز بزند. توکن به اتاق گره خورده و روی اتاق دیگر کار نمی‌کند.

---

## Content API — محتوای جانبی

یک جدول برای اخبار، قرآن، کتاب، دفترچه تلفن و اطلاعات (`content_items`).
پنج جدول جدا، نگهداری و نمایش در پلیر را بی‌دلیل پیچیده می‌کرد.

**انواع:** `news` · `quran` · `book` · `directory` · `info` · `prayer_audio`

| مسیر | کار |
|------|-----|
| `GET /guest/{code}/content/{kind}` | فهرست — خبر بر اساس تازگی، بقیه بر اساس ترتیب |
| `GET /guest/{code}/content/{kind}/{id}` | متن کامل |
| `GET|POST /content` · `PUT|DELETE /content/{id}` | مدیریت از پنل |

آدرس‌های `image_url`، `audio_url` و `file_url` فقط `http(s)` یا مسیر داخلی
می‌پذیرند — `javascript:` و `data:` مستقیم روی تلویزیون اجرا می‌شدند.

### خبر از RSS

جدول `news_feeds` منبع را نگه می‌دارد و `php artisan news:sync` آن را
می‌خواند (cron هر ساعت). RSS 2.0 و Atom هر دو پشتیبانی می‌شوند.

- تصویر از `enclosure`، `media:content`، `media:thumbnail` یا اولین `<img>`
  داخل متن استخراج می‌شود
- خبر تکراری بر اساس عنوان در ۳۰ روز گذشته رد می‌شود — بسیاری از فیدهای
  فارسی `guid` پایدار ندارند
- خبر قدیمی‌تر از ۱۴ روز خودکار پاک می‌شود
- اگر منبع قطع باشد، خبرهای قبلی روی تلویزیون می‌مانند

---

## بیدارباش روی تلویزیون

| مسیر | کار |
|------|-----|
| `GET /guest/{code}/wakeups` | بیدارباش‌هایی که باید **الان** نشان داده شوند |
| `POST /guest/{code}/wakeups/{id}/ack` | مهمان آن را بست |

پلیر این را هر دقیقه می‌پرسد. پنجره‌ی تحویل **۵ دقیقه** است: اگر تلویزیون
چند دقیقه آفلاین بوده بیدارباش گم نمی‌شود، ولی یک ساعت بعد هم ناگهان ظاهر
نمی‌شود. اولین نمایش در `delivered_at` ثبت می‌شود تا در پنل معلوم باشد
واقعاً تحویل شده.
