# SignageCMS REST API Documentation
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

> Runs on Raspberry Pi at port 5055 — called by SignageCMS backend (server-side proxy)

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
  "signagecms_url":  "https://your-server.com",
  "flight_id":       1,
  "api_token":       "JWT_TOKEN",
  "push_enabled":    true,
  "push_interval":   10
}
```

---

## SignageCMS → RPi Proxy Endpoints

> Auth: JWT — SignageCMS fetches from RPi on behalf of admin

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
Sends SignageCMS connection config to RPi.
```json
{
  "cms_url":       "https://your-server.com",
  "api_token":     "JWT_TOKEN",
  "push_enabled":  true,
  "push_interval": 10
}
```
