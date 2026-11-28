# دادهٔ نمونه — کانال، رسانه، پلی‌لیست و پیام

این سند همان داده‌ای را توصیف می‌کند که برای آزمایش واقعی سامانه روی یک نصب
تازه ساخته شده است. همهٔ ردیف‌ها از **مسیرهای واقعی پنل** ساخته شده‌اند (فرم‌ها
و API)، نه با نوشتن مستقیم در دیتابیس؛ پس هر کسی با دسترسی اپراتور می‌تواند
همین وضعیت را بازسازی کند.

جدول‌های درگیر: `iptv_channels`، `iptv_channel_groups`، `media`، `playlists`،
`playlist_items`، `schedules`، `messages`.

---

## ۱) کانال‌های نمونه

سه کانال در گروه «نمونه» ساخته شده‌اند. آدرس‌ها **استریم‌های عمومی نمونه**
هستند، نه کانال واقعی هتل؛ برای جایگزینی، فقط `stream_url` را از فرم
«مدیریت IPTV» عوض کنید.

| # | نام | گروه | پروتکل | آدرس استریم |
|---|---|---|---|---|
| ۱ | کانال نمونه — سرگرمی | نمونه | `hls` | `https://devstreaming-cdn.apple.com/videos/streaming/examples/bipbop_16x9/bipbop_16x9_variant.m3u8` |
| ۲ | کانال نمونه — سینما | نمونه | `hls` | `https://demo.unified-streaming.com/k8s/features/stable/video/tears-of-steel/tears-of-steel.ism/.m3u8` |
| ۳ | کانال نمونه — آموزشی | نمونه | `hls` | `https://devstreaming-cdn.apple.com/videos/streaming/examples/img_bipbop_adv_example_fmp4/master.m3u8` |

### چرا این آدرس‌ها

پیش از ثبت، دسترسی سرور به هر آدرس با `curl` بررسی شد و هر سه **۲۰۰** دادند:

```bash
for u in \
  https://devstreaming-cdn.apple.com/videos/streaming/examples/bipbop_16x9/bipbop_16x9_variant.m3u8 \
  https://demo.unified-streaming.com/k8s/features/stable/video/tears-of-steel/tears-of-steel.ism/.m3u8 \
  https://devstreaming-cdn.apple.com/videos/streaming/examples/img_bipbop_adv_example_fmp4/master.m3u8
do
  echo "$u -> $(curl -s -o /dev/null -w '%{http_code}' --max-time 8 "$u")"
done
```

نمونه‌های `test-streams.mux.dev` روی این سرور **در دسترس نبودند** (`000`)؛
کانالی که آدرسش از خود سرور باز نمی‌شود، روی تلویزیون هم پخش نمی‌شود و فقط
یک ردیف قرمز در پنل است. اگر آدرس کانالی را عوض کردید، اول همین بررسی را
تکرار کنید.

### ساخت از پنل

**مدیریت IPTV** (`/admin/iptv`) → «کانال جدید»:

| فیلد | مقدار |
|---|---|
| نام | `کانال نمونه — سرگرمی` (و دو ردیف دیگر) |
| آدرس استریم | یکی از آدرس‌های جدول بالا |
| دسته | `نمونه` |
| پروتکل | `hls` (خودکار از آدرس تشخیص داده می‌شود) |

دستهٔ `نمونه` اگر وجود نداشته باشد، خودکار به یک گروه در
`iptv_channel_groups` تبدیل می‌شود (`ChannelGroupService::resolve`)؛ گروهِ
بی‌نام و «بی‌گروه» در فهرست تلویزیون زیر «سایر» می‌افتد.

### ساخت با SQL (روی نصبی که دسترسی دیتابیس دارد)

```sql
INSERT INTO iptv_channel_groups (tenant_id, name, name_en, slug, color, sort_order, is_active)
VALUES (1, 'نمونه', 'Sample', 'نمونه', '#22c55e', 10, 1);

INSERT INTO iptv_channels
  (tenant_id, name, stream_url, category, group_id, protocol, sort_order, is_active)
SELECT 1, x.name, x.url, 'نمونه', g.id, 'hls', x.so, 1
  FROM iptv_channel_groups g
  JOIN (
    SELECT 'کانال نمونه — سرگرمی' AS name,
           'https://devstreaming-cdn.apple.com/videos/streaming/examples/bipbop_16x9/bipbop_16x9_variant.m3u8' AS url, 1 AS so
    UNION ALL SELECT 'کانال نمونه — سینما',
           'https://demo.unified-streaming.com/k8s/features/stable/video/tears-of-steel/tears-of-steel.ism/.m3u8', 2
    UNION ALL SELECT 'کانال نمونه — آموزشی',
           'https://devstreaming-cdn.apple.com/videos/streaming/examples/img_bipbop_adv_example_fmp4/master.m3u8', 3
  ) x
 WHERE g.tenant_id = 1 AND g.slug = 'نمونه';
```

### بررسی واقعی

پنل (`/admin/iptv`) سه ردیف را در گروه «نمونه» نشان می‌دهد، و همان کانال‌ها
از API پورتال تلویزیون هم می‌آیند — این مسیر، مسیری است که خود پلیر اتاق
صدا می‌زند:

```bash
curl -s http://127.0.0.1/api/v1/portal/SCR001 \
  | python3 -c "import sys,json;d=json.load(sys.stdin)['data']['channels'];[print(c['id'],c['name'],c['playable'],c['stream_url']) for c in d]"
```

خروجی تأییدشده روی سرور آزمایشی:

```
4 کانال نمونه — سرگرمی True https://devstreaming-cdn.apple.com/...
5 کانال نمونه — سینما True https://demo.unified-streaming.com/...
6 کانال نمونه — آموزشی True https://devstreaming-cdn.apple.com/...
```

`playable=true` یعنی کانال روی این دستگاه از داخل پورتال باز می‌شود. اگر
`false` شد، به `multicast_config.native_platforms` و `udpxy_url` نگاه کنید؛
مقدار آدرس ممکن است به `udp://` تبدیل شده باشد.

---

## ۲) رسانه و پلی‌لیست نمونه (signage)

| مورد | مقدار |
|---|---|
| رسانه‌ها | ۳ تصویر ۱۶:۹ در `/uploads/media/1/` (شناسه‌های ۴، ۵، ۶) |
| پلی‌لیست | «نمونهٔ هتل — لابی» (شناسهٔ ۴) — ۳ آیتم ۱۰ ثانیه‌ای |
| زمان‌بندی | «نمونه — لابی» روی صفحهٔ `SCR001` با `priority=9` |

مسیر ساخت: **رسانه** → آپلود تصویر؛ **پلی‌لیست‌ها** → پلی‌لیست جدید + افزودن
آیتم؛ **زمان‌بندی** → بازهٔ امروز روی صفحهٔ لابی.

نکتهٔ مهم: `Screen::getCurrentPlaylist()` اول **زمان‌بندی** را می‌خواند و
پلی‌لیست به‌صورت دستی «پین‌شده» روی صفحه، فقط وقتی زمانی وجود نداشته باشد
اعمال می‌شود. برای همین پلی‌لیست نمونه بدون زمان‌بندی روی صفحه دیده نمی‌شد.

بررسی:

```bash
curl -s http://127.0.0.1/api/v1/screens/SCR001/playlist
# → پلی‌لیست id=4 با ۳ آیتم و src کامل؛ فایل رسانه با کد ۲۰۰ (~۹۴ کیلوبایت)
```

## ۳) پیام خوش‌آمد

پیام «خوش آمدید» از صفحهٔ **پیام‌ها** ساخته شد تا مسیر ارسال پیام به تلویزیون
اتاق آزموده شود. همین پیام اولین چیزی بود که دستیار هوشمند پس از رفع باگ
`OpenHandGuard` توانست واقعاً بسازد (نسخهٔ ۱.۳۸.۱).

---

## پاک‌کردن دادهٔ نمونه

- کانال: **مدیریت IPTV** → سطل زبالهٔ هر ردیف.
- پلی‌لیست و زمان‌بندی: از همان صفحه‌ها.
- رسانه: **رسانه** → حذف فایل.

دادهٔ نمونه به هیچ بخشی از کد وابسته نیست؛ نبودشان فقط صفحه‌های خالی می‌سازد.
