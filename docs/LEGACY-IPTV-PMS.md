# بستهٔ IPTV قدیمی (VINAK / sms) — آنچه از آن استخراج شد

منبع: `VINAK-IPTV-…UBUNTU16.iso` و `sms.rar`. برنامهٔ اصلی در `install/filesystem.squashfs` (بستهٔ `mmk_v2_…deb` → `/opt/sms`) است؛ کد پایتونش با pytransform رمز شده و استفاده‌اش جایز نیست، ولی PHP سمت وب خوانا است.
رمزها و کلیدهای داخل فایل پیش‌تنظیم ISO (`preseed/mmk.cfg`) عمدا اینجا نیامده.

## استفاده‌شده در Hotel Media
- **لوگوی کانال‌ها** (`chan_logo.tar.gz`، ۴۳۰ فایل ۵۰×۵۰) ← `public/assets/channel-logos/` + `ChannelLogoService` (تطبیق با نام کانال، هنگام ورود از TVHeadend).

## آمادهٔ استفاده، هنوز وارد نشده
- `all_dvbt_freq`، `iran-city.txt` (۹۱ شهر)، `woeid.txt` (کد آب‌وهوا برای ۱۲۸ شهر)، `satellites/scan/dvb-s` (فرکانس ماهواره‌ها).
- `live_udp.m3u`: نمونهٔ چینش multicast (IRIB TV1.. روی `239.1.1.x:3200`).

## اتصال PMS (`iptv/s/data/pms.php`)
نه سیستم هتلداری ایرانی؛ برای هر کدام «اطلاعات مهمان» و «صورتحساب». رمز و آدرس از تنظیمات (`middleware->pms`: name/ip/user/pass) می‌آید.

| سیستم | پروتکل | مهمان | صورتحساب |
|---|---|---|---|
| Tolooe | HTTP JSON | `GET /api/getroominfo?RoomNumber=` | `GET /api/getroominvoice?RoomNumber=` → `Return_Code=ok` و `image_paths`؛ تصویر JPG از `/api/imgs?img=` |
| Mosafer | HTTP JSON | `GET /GuestInfo.aspx?room=` | `POST /GuestBill.aspx?room=` با `en=0/1` → `items[{datetime,title,debit,credit}]` |
| Medrik | REST JSON | `GetReceptionRoom` | `GET /HotelChargingService/REST/GetHotelReceptionBills?username&password&RoomNo` → `Result.ReceptionCustomers[].CustomerBillDetails` (تاریخ به قالب `/Date(ms+tz)/`) |
| Rain | REST JSON + Basic | همان مسیر `HotelChargingService/REST/*?RoomNo=` | `GetHotelReceptionBills`؛ ساختار پاسخ مثل Medrik، شرط موفقیت `Success=true` |
| Protel | XML (WINDOWS-1256) | `GET /pp_xml_ckit_guest?room=` ← `Guest/ResNo` | `GET /pp_xml_ckit_GustBill?ResNo=` ← `Item{Text,Quantity,price,Date,Time}` |
| ASA | SOAP (nusoap) + HMAC | `register_view` | `guest_view` روی `/hotel/agency/api/iptv/index.php`؛ امضا `base64(hmac_sha256('{"room_number":"N"}', pass))`، پاسخ `base64(gzcompress(json))`؛ ثبت فیلم پولی: `iptv_bill_mojafzar.php` |
| Marlik | HTTP JSON | `GET /api/GuestInformationRequest` | `GET /api/InvoiceRequest?RequestID=100&RoomNumber=&Language=1` |
| Harris 1 | SOAP | `/Service.asmx?WSDL` (HarrisPassword) | `GuesInvoiceDt` (نتیجه XML diffgram) |
| Harris 2 | SOAP | `/IPTVService.asmx?wsdl` (UserName/Password) | `GuesInvoiceDt` |

وضعیت: **هیچ‌کدام در Hotel Media پیاده نشده** (PMS فعلی عمومی است: `PmsChargeService` + `pms_integrations`). پورت هر کدام بدون سیستم واقعی برای آزمون ممکن نیست؛ باید معلوم شود هتل کدام را دارد.

## کلاینت تلویزیون (کار می‌کند روی اندروید قدیمی و سامسونگ)
`iptv/s/data/apk/app_tv_*.apk` (اندروید)، `tizen.wgt` و `tizen_ticker.wgt` + `tizen_install.sh` (سامسونگ Tizen)، `samsung_offline/` (صفحهٔ قطع شبکه)، `iptv/procentric` و `iptv/rms` (LG ProCentric). رابط وب آن‌ها `iptv/s` (چارچوب Antie، ۲۷۹ فایل تنظیم دستگاه در `antie/config/devices`).
