# نقشهٔ کد — Hotel Media

راهنمای توسعه‌دهنده: کجا چه چیزی است، درخواست چه مسیری را طی می‌کند، و برای افزودن
یک قابلیت تازه کدام فایل‌ها را باید دست زد.

اسکیمای دیتابیس جدا است: [DATABASE-SCHEMA.md](DATABASE-SCHEMA.md) (تولیدشده).
فهرست APIها: [API.md](API.md). مشکلات نصب و درس‌های میدانی: [INSTALL-ISSUES.md](INSTALL-ISSUES.md).

---

## ۱) این برنامه چیست

یک پلتفرم واحد با **دو محصول** که عمدا در یک کد زندگی می‌کنند، چون صفحه، رسانه،
کاربر و دستگاه بینشان مشترک است:

| | IPTV هتل | تابلوی دیجیتال (Signage) |
|---|---|---|
| مخاطب | مهمان در اتاق، با ریموت | رهگذر در لابی/راهرو |
| پروفایل پلیر | `player/profiles/iptv.php` | `modern.php` و خویشاوندانش |
| محتوا | منوی اتاق، کانال زنده، VOD، خدمات هتل | پلی‌لیست، قالب، زمان‌بندی |
| جدول کلیدی | `iptv_menus`, `iptv_channels`, `iptv_rooms` | `playlists`, `playlist_items`, `schedules` |

مرز را `screens.screen_type` تعیین می‌کند: `signage` \| `iptv` \| `inflight` \| `monitor_3d`.
**تلویزیون اتاق پلی‌لیست پخش نمی‌کند** — این را هنگام افزودن قابلیت فراموش نکنید،
وگرنه هشدار «پلی‌لیست ندارد» روی ۲۰۰ اتاق سالم ظاهر می‌شود.

---

## ۲) پشته و اجرا

بدون فریم‌ورک و بدون Composer در زمان اجرا. MVC دست‌ساز، PHP 8.1+ (روی ۲۶٫۰۴: PHP 8.5).

```
nginx → public/index.php → Router → Middleware → Controller → Model/Service → View
```

`public/index.php` این‌ها را به‌ترتیب انجام می‌دهد:
ثابت‌های مسیر (`ROOT_PATH`, `APP_PATH`, `VIEWS_PATH`, `STORAGE_PATH`) →
autoloader ساده (`App\X\Y` → `app/X/Y.php`) →
توابع کمکی سراسری که **در همین فایل** تعریف شده‌اند (`e()`, `csrf_token()`, `csrf_field()`, `v()`, `asset()`, `url()`)
و `app/Helpers/helpers.php` (فقط `slugify`, `truncate`) →
خواندن `.env` → نشست (`signage_session`) → سرآیندهای امنیتی →
`routes/api.php` و `routes/web.php` → `$router->dispatch()`.

سرویس‌های جانبی: MariaDB، nginx + PHP-FPM، TVHeadend (پخش زنده)، ffmpeg (ترنسکد)،
یک سرور WebSocket (`websocket/`)، و cron برای کارهای دوره‌ای.

---

## ۳) ساختار پوشه‌ها

| مسیر | چیست |
|---|---|
| `app/Core/` | ۹ کلاس پایه: `Router`, `Controller`, `Database`, `Auth`, `Request`, `Response`, `JWT`, `Lang`, `Branch` |
| `app/Middleware/` | `Auth`, `ApiAuth`, `Csrf`, `Guest`, `Permission`, `RateLimit` |
| `app/Controllers/Web/` | ۵۰ کنترلر پنل مدیریت (خروجی HTML یا JSON برای fetch همان صفحه) |
| `app/Controllers/Api/` | ۳۳ کنترلر `/api/v1/*` برای پلیرها و اپ‌ها |
| `app/Services/` | ۳۶ سرویس — منطق واقعی اینجاست، نه در کنترلر |
| `app/Models/` | فقط ۵ مدل (`Screen`, `Playlist`, `Media`, `Schedule`, `User`). بقیه مستقیم با `Database` کار می‌کنند |
| `app/Modules/` | ماژول‌های قابل خاموش‌کردن: `IPTV`, `VOD`, `Hotel`, `Menu` + `ModuleRegistry` |
| `resources/views/admin/` | صفحه‌های پنل |
| `resources/views/player/profiles/` | ۱۰ پروفایل پلیر (هر خانوادهٔ تلویزیون یکی) |
| `resources/views/partials/` | `layout.php`, `nav.php`, `layout_footer.php`, `tv_health.php` |
| `public/assets/js/` | JS پلیر تلویزیون (ES5) + `command-palette.js` پنل |
| `public/assets/css/design-system.css` | توکن‌ها و کامپوننت‌های پنل |
| `database/migrations/` | ۵۹ فایل SQL، به‌ترتیب شماره |
| `tests/Feature/` | ۲۴ تست؛ با `php tests/Feature/X.php` اجرا می‌شوند |
| `scripts/` | نصب، کنسول متنی `hotel-media`، bootstrap تی‌وی‌هدند |
| `tools/` | ابزار تولید مستندات |

---

## ۴) مسیریابی

دو فایل: `routes/api.php` (۲۶۴ مسیر) و `routes/web.php` (۱۸۵ مسیر).

```php
$router->group(['prefix' => '/admin', 'middleware' => [AuthMiddleware::class, PermissionMiddleware::class]],
  function ($r) {
      $r->get('/iptv',  [IPTVController::class, 'index']);
      $r->post('/iptv', [IPTVController::class, 'store'], [CsrfMiddleware::class]);
  });
```

قاعده‌ها:
- **هر POST پنل باید `CsrfMiddleware` داشته باشد.** مسیرهای `/api/` از CSRF معاف‌اند و با JWT یا نشست کار می‌کنند.
- `PermissionMiddleware` نقشهٔ «پیشوند مسیر → مجوز» دارد؛ **پیش‌فرض باز است** و فقط مسیرهای حساس فهرست می‌شوند. اگر صفحهٔ حساسی اضافه کردید، آنجا هم ثبتش کنید.
- مسیرهای رو به تلویزیون (`/player/...`, `/tv/...`, `/api/v1/screens/{code}/...`) بدون ورود کار می‌کنند و با کد صفحه یا امضا محافظت می‌شوند.

---

## ۵) نقش‌ها و دسترسی

`App\Core\Auth::$permissions` نقشهٔ نقش→مجوز است: `super_admin` (`*`), `admin`, `manager`,
`editor`, `viewer`. در ویوها `$isSuper` و `$isTech` (در `nav.php`) و تابع `modOn('iptv')`
برای ماژول‌های خاموش/روشن استفاده می‌شوند.

`Auth::tenantId()` همه‌جا مرز داده است: **هر کوئری باید `tenant_id` را فیلتر کند.**

---

## ۶) چرخهٔ عمر تلویزیون

```
تلویزیون → /tv یا /player/{code}
        → کوکی دستگاه، جفت‌شدن، انتخاب پروفایل از screen_type
        → پلیر: تیزر (یک‌بار) → منو/پلی‌لیست
        → heartbeat هر ۳۰ ثانیه: فرمان‌ها، پیام‌ها، فاصلهٔ همگام‌سازی
```

- **تیزر:** `tv-intro.js` → `GET /api/v1/screens/{code}/intro`. «یک‌بار» با `sessionStorage` سمت پلیر شمرده می‌شود.
- **پیام و زیرنویس:** جدول `screen_messages`، از پاسخ heartbeat می‌آید، با `tv-messages.js` رندر می‌شود (سبک‌ها: overlay/popup/fullscreen/banner/ticker).
- **فرمان‌ها:** `screen_commands` صف است؛ پلیر در heartbeat می‌گیرد و اجرا می‌کند (reload، reboot، screenshot…).
- **سازگاری:** JS پلیر **ES5** است و CSS بدون `var()`, `clamp()`, `gap`, `inset`, `backdrop-filter` — چون Tizen 2.x و Orsay 2013 هم باید اجرا کنند. این محدودیت عمدی است؛ آن را «مدرن» نکنید.

---

## ۷) زنجیرهٔ پخش زنده (DVB)

```
آنتن → تیونر USB → TVHeadend (127.0.0.1:9981، Digest)
     → TvheadendTunerService  (شبکه، فرکانس، اسکن)
     → TvheadendService::syncChannels  (channel/grid → iptv_channels)
     → StreamProxyController  /tv/stream/{uuid}?sig=…
     → تلویزیون (MPEG-TS خام) | مرورگر (&compat=1 → ffmpeg yadif)
```

نکته‌های میدانی که به‌سختی به‌دست آمدند:
- **به تنظیمات TVHeadend دست نزنید.** هر کاری از API رسمی انجام می‌شود، وگرنه با به‌روزرسانی آن می‌شکند.
- **یک تیونر = یک فرکانس (mux) در هر لحظه.** کانال فرکانس دیگر ۵۰۳ می‌گیرد؛ این محدودیت سخت‌افزاری است نه باگ.
- TVHeadend روی loopback است و رمز می‌خواهد، پس آدرس مستقیمش به درد تلویزیون نمی‌خورد — پروکسی امضاشده لازم است.
- کانال‌های زمینی ایران H.264 **ردیفی** (576i) هستند؛ مرورگر دسکتاپ نمی‌پخشد، تلویزیون می‌پخشد.

---

## ۸) قراردادهای کد

- **منطق در سرویس، نه در کنترلر.** کنترلر ورودی می‌گیرد، سرویس را صدا می‌زند، پاسخ می‌دهد.
- **توضیح فارسی بنویسید و «چرا» را توضیح دهید، نه «چه».** کد موجود پر از توضیحاتی است که می‌گویند چرا یک راه‌حل بدیهی کار نکرد؛ همان سبک را ادامه دهید.
- خروجی JSON با `Response::success()` / `Response::error()`.
- در ویوها `e()` برای escape و `csrf_field()` در هر فرم. این توابع در `public/index.php` تعریف شده‌اند، نه در `app/Helpers/`.
- `data-confirm="…" data-confirm-name="…"` برای حذف، نه `confirm()` خام.
- `showToast(type, msg)` یکی است و در `layout_footer.php` تعریف شده.

---

## ۹) مهاجرت دیتابیس

فایل تازه با شمارهٔ بعدی در `database/migrations/`. **قابل اجرای دوباره بنویسید**:

```sql
SET @db = DATABASE();
SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA=@db AND TABLE_NAME='x' AND COLUMN_NAME='y');
SET @q = IF(@has = 0, "ALTER TABLE x ADD COLUMN y INT NOT NULL DEFAULT 0", 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
```

اجرا: `php artisan db:migrate`. بعد مستند را تازه کنید: `node tools/gen-schema-doc.js`.

---

## ۱۰) تست

تست‌ها PHP ساده‌اند و روی سرور اجرا می‌شوند (نه ویندوز):

```bash
php tests/Feature/TvheadendTest.php
```

**درس تلخ:** تستی که پاسخ ساختگی با شکل غیرواقعی برمی‌گرداند، ناسازگاری واقعی API را
پنهان می‌کند. `channel/list` در تست `uuid/name` می‌داد در حالی که TVHeadend واقعی
`{key,val}` می‌دهد — تست سبز بود و هیچ کانالی وارد نمی‌شد. شکل ساختگی را از پاسخ
واقعی بردارید. همین‌طور تستی که روی عدد دقیق قفل شود (مثل «دقیقا ۴ آیتم منو») با
اولین دادهٔ واقعی می‌شکند بی‌آنکه چیزی خراب شده باشد.

---

## ۱۱) کارهای دوره‌ای (cron)

همه از `scripts/cron-run.sh` می‌گذرند تا خطا ثبت شود:

| فاصله | فرمان |
|---|---|
| هر دقیقه | `monitor:screens`, `transcoder:supervise` |
| هر ۲ دقیقه | `vod:queue` |
| هر ۵ دقیقه | `flights:sync`, `pms:push` |

فرمان‌های دیگر `artisan`: `db:migrate`, `db:seed`, `tvheadend:setup`, `tvheadend:status`,
`epg:sync`, `news:sync`, `media:reconvert`, `storage:clean`, `user:create`, `ws:start`.

---

## ۱۲) افزودن یک قابلیت — چک‌لیست

۱. مهاجرت (اگر جدول/ستون لازم است) — قابل اجرای دوباره.
۲. سرویس در `app/Services/` با منطق.
۳. کنترلر در `Web/` (پنل) یا `Api/` (پلیر).
۴. مسیر در `routes/web.php` یا `routes/api.php` — POST پنل با `CsrfMiddleware`.
۵. ویو در `resources/views/admin/…` + لینک در `partials/nav.php`.
۶. اگر پلیر درگیر است: JS را **ES5** بنویسید و در هر دو پروفایل (`iptv.php` و `modern.php`) وصل کنید.
۷. تست در `tests/Feature/`.
۸. مستند: `node tools/gen-schema-doc.js` و یک سطر در `docs/INSTALL-ISSUES.md` اگر درس میدانی داشت.

**تلهٔ کش:** آدرس فایل‌های JS/CSS با `?v=` از فایل `VERSION` می‌آید. اگر نسخه را بالا
نبرید، مرورگر و تلویزیون نسخهٔ قدیمی را نگه می‌دارند و تغییر شما دیده نمی‌شود.
