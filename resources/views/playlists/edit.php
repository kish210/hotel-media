<?php include VIEWS_PATH . '/partials/layout.php';
$isEdit   = isset($playlist);
$title    = $isEdit ? 'ویرایش پلی‌لیست' : 'پلی‌لیست جدید';
$action   = $isEdit ? '/admin/playlists/' . $playlist['id'] : '/admin/playlists';
$playlist = $playlist ?? [];
?>

<div class="flex items-center gap-3 mb-6">
  <a href="/admin/playlists" class="btn-ghost text-sm px-3"><i class="fas fa-arrow-right text-xs"></i></a>
  <h1 class="text-xl font-bold text-white"><?= e($title) ?></h1>
</div>

<div class="max-w-2xl">
  <div class="card p-6">
    <form method="POST" action="<?= e($action) ?>" class="space-y-4">
      <?= csrf_field() ?>

      <div>
        <label class="form-label">نام پلی‌لیست *</label>
        <input type="text" name="name" class="form-input" required
          value="<?= e($playlist['name'] ?? '') ?>" placeholder="مثلاً: منوی صبحانه">
      </div>

      <div>
        <label class="form-label">توضیحات</label>
        <input type="text" name="description" class="form-input"
          value="<?= e($playlist['description'] ?? '') ?>" placeholder="توضیح اختیاری">
      </div>

      <div>
        <label class="form-label">این پلی‌لیست برای کجاست؟ *</label>
        <?php $pw = $playlist['screen_type'] ?? 'signage'; ?>
        <select name="screen_type" class="form-input">
          <option value="signage" <?= $pw === 'signage' ? 'selected' : '' ?>>🖼 تابلو (Signage)</option>
          <option value="iptv"    <?= $pw === 'iptv'    ? 'selected' : '' ?>>📺 تلویزیون اتاق (IPTV)</option>
          <option value="any"     <?= $pw === 'any'     ? 'selected' : '' ?>>هر دو</option>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">چیدمان (Layout)</label>
          <select name="layout_id" class="form-input">
            <option value="">— بدون چیدمان —</option>
            <?php foreach ($layouts ?? [] as $l): ?>
            <option value="<?= $l['id'] ?>" <?= ($playlist['layout_id'] ?? '') == $l['id'] ? 'selected' : '' ?>>
              <?= e($l['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">زمان پیش‌فرض هر آیتم (ثانیه)</label>
          <input type="number" name="default_duration" class="form-input"
            value="<?= e($playlist['default_duration'] ?? 10) ?>" min="1" max="3600">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">انیمیشن انتقال</label>
          <select name="transition" class="form-input">
            <?php foreach (['fade'=>'محو','slide'=>'لغزش','zoom'=>'زوم','none'=>'بدون'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($playlist['transition'] ?? 'fade') === $v ? 'selected' : '' ?>>
              <?= $l ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php /* هر چک‌باکس یک hidden با مقدار ۰ جلوتر از خودش دارد.
                 بدون آن، چک‌باکسِ تیک‌نخورده اصلا POST نمی‌شود و چون
                 به‌روزرسانی فقط کلیدهای موجود را می‌نویسد، خاموش‌کردن
                 «پخش تصادفی» یا «فعال» هیچ‌وقت ذخیره نمی‌شد — فرم
                 بی‌صدا تیک را برمی‌گرداند. */ ?>
        <div class="flex flex-col gap-2 pt-5">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="shuffle" value="0">
            <input type="checkbox" name="shuffle" value="1" class="accent-orange-500 w-4 h-4"
              <?= !empty($playlist['shuffle']) ? 'checked' : '' ?>>
            <span class="text-sm text-slate-400">پخش تصادفی</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="accent-orange-500 w-4 h-4"
              <?= ($playlist['is_active'] ?? 1) ? 'checked' : '' ?>>
            <span class="text-sm text-slate-400">فعال</span>
          </label>
        </div>
      </div>

      <?php if ($isEdit): ?>
      <?php /* زیرنویس و دما داخل همین فرم‌اند چون ستون پلی‌لیست‌اند و
               با همان دکمه‌ی ذخیره می‌روند. لوگو فرم جداگانه دارد،
               چون آپلود فایل به enctype دیگری نیاز دارد و اگر این فرم
               multipart می‌شد، بقیه‌ی فیلدها هم از آن مسیر می‌رفتند. */ ?>
      <div class="pt-5 mt-5 border-t border-white/5 space-y-4">
        <div class="text-sm font-semibold text-slate-300">نمایش روی تابلو</div>

        <div>
          <label class="form-label">زیرنویس متنی (نوار پایین صفحه)</label>
          <?php /* textarea نه input: اپراتور باید بتواند چند پیام
                   بگذارد — اطلاعات اقامتی، رویداد امروز، ساعت صبحانه —
                   و هرکدام یک خط باشد. سرور آن‌ها را به یک نوار پیوسته
                   وصل می‌کند و خط خالی را می‌اندازد. */ ?>
          <textarea name="ticker_text" class="form-input" rows="4"
                    maxlength="2000"
                    placeholder="هر خط یک پیام&#10;مثال: صبحانه ۷ تا ۱۰ صبح در رستوران طبقه‌ی همکف&#10;مثال: شب موسیقی زنده، امشب ساعت ۲۱ در لابی"
          ><?= e($playlist['ticker_text'] ?? '') ?></textarea>
          <p class="text-xs text-slate-500 mt-1">
            هر خط یک پیام جدا. روی تلویزیون پشت سر هم با «•» می‌چرخند.
            خط خالی نادیده گرفته می‌شود.
          </p>
        </div>

        <?php /* جاهای خالیِ ویدیو. ویدیویی که نسبت تصویرش با صفحه یکی
                 نیست نوار سیاه می‌گذارد و روی تابلوی تبلیغاتی آن سیاهی
                 مثل خرابی دیده می‌شود. */ ?>
        <div class="pt-4 border-t border-white/5">
          <label class="form-label">جاهای خالی اطراف ویدیو</label>
          <p class="text-xs text-slate-500 mb-3">
            وقتی اندازه‌ی ویدیو با صفحه جور نیست، این پشتِ آن دیده می‌شود.
          </p>

          <?php $bd = $playlist['backdrop_mode'] ?? 'black'; ?>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
            <?php foreach ([
              'black' => 'سیاه (پیش‌فرض)',
              'color' => 'یک رنگ',
              'logo'  => 'تکرار لوگو',
              'image' => 'تصویر دلخواه',
            ] as $val => $lbl): ?>
            <label class="flex items-center gap-2 cursor-pointer px-3 py-2 rounded-xl
                          bg-white/5 border border-white/10 hover:bg-white/10">
              <input type="radio" name="backdrop_mode" value="<?= $val ?>"
                     class="accent-orange-500"
                     <?= $bd === $val ? 'checked' : '' ?>>
              <span class="text-xs text-slate-300"><?= $lbl ?></span>
            </label>
            <?php endforeach; ?>
          </div>

          <div class="flex items-center gap-3 flex-wrap">
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-500">رنگ:</span>
              <input type="color" name="backdrop_color"
                     value="<?= e($playlist['backdrop_color'] ?? '#000000') ?>"
                     class="w-12 h-9 rounded-lg bg-transparent border border-white/10 cursor-pointer">
            </div>
            <p class="text-xs text-slate-500">
              رنگ برای حالت «یک رنگ» و برای زمینه‌ی پشتِ «تکرار لوگو» به کار می‌رود.
            </p>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-3 items-end">
          <label class="flex items-center gap-2 cursor-pointer col-span-3 sm:col-span-1">
            <input type="hidden" name="weather_enabled" value="0">
            <input type="checkbox" name="weather_enabled" value="1" class="accent-orange-500 w-4 h-4"
              <?= ($playlist['weather_enabled'] ?? 1) ? 'checked' : '' ?>>
            <span class="text-sm text-slate-400">نمایش دمای هوا</span>
          </label>
          <div>
            <label class="form-label">هر چند ثانیه یک‌بار</label>
            <input type="number" name="weather_every" class="form-input" min="30" max="3600"
                   value="<?= (int)($playlist['weather_every'] ?? 240) ?>">
          </div>
          <div>
            <label class="form-label">چند ثانیه بماند</label>
            <input type="number" name="weather_show" class="form-input" min="10" max="600"
                   value="<?= (int)($playlist['weather_show'] ?? 60) ?>">
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="flex gap-3 pt-4 border-t border-white/5">
        <button type="submit" class="btn-primary flex-1 py-3">
          <?= $isEdit ? 'ذخیره تغییرات' : 'ایجاد پلی‌لیست' ?>
        </button>
        <a href="/admin/playlists" class="btn-ghost px-6">لغو</a>
      </div>
    </form>

    <?php if ($isEdit): ?>
    <div class="mt-6 pt-6 border-t border-white/5">
      <div class="text-sm font-semibold text-slate-300 mb-1">لوگوی گوشه‌ی تابلو</div>
      <p class="text-xs text-slate-500 mb-4">
        همیشه بالا-راست صفحه دیده می‌شود، روی تبلیغات هم. اگر چیزی
        نگذارید، لوگوی هتل استفاده می‌شود.
      </p>

      <div class="flex items-center gap-4 flex-wrap">
        <?php if (!empty($playlist['logo_path'])): ?>
          <?php /* پس‌زمینه‌ی روشن عمدی: لوگوهای سفید روی کارت تیره
                   نامرئی می‌شوند و اپراتور فکر می‌کند آپلود نشده. */ ?>
          <div class="p-3 rounded-xl bg-slate-200 border border-white/10">
            <img src="<?= e($playlist['logo_path']) ?>" alt="لوگوی فعلی"
                 class="h-12 w-auto block">
          </div>
        <?php else: ?>
          <div class="px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-xs text-slate-500">
            لوگویی انتخاب نشده
          </div>
        <?php endif; ?>

        <form method="POST" action="/admin/playlists/<?= (int)$playlist['id'] ?>/logo"
              enctype="multipart/form-data" class="flex items-center gap-2 flex-wrap">
          <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"
                 class="form-input text-xs py-2" required>
          <button type="submit" class="btn-primary px-5 py-2 text-sm">جایگزینی</button>
        </form>

        <?php if (!empty($playlist['logo_path'])): ?>
        <form method="POST" action="/admin/playlists/<?= (int)$playlist['id'] ?>/logo"
              onsubmit="return confirm('لوگوی این پلی‌لیست حذف شود؟');">
          <input type="hidden" name="remove" value="1">
          <button type="submit" class="btn-ghost px-4 py-2 text-sm">حذف</button>
        </form>
        <?php endif; ?>
      </div>

      <p class="text-xs text-slate-500 mt-3">PNG، JPG یا WebP — حداکثر ۳ مگابایت.</p>

      <div class="mt-6 pt-6 border-t border-white/5">
        <div class="text-sm font-semibold text-slate-300 mb-1">تصویر پس‌زمینه</div>
        <p class="text-xs text-slate-500 mb-4">
          فقط وقتی به کار می‌رود که بالا «تصویر دلخواه» را انتخاب کرده باشید.
        </p>

        <div class="flex items-center gap-4 flex-wrap">
          <?php if (!empty($playlist['backdrop_image'])): ?>
            <div class="p-1 rounded-xl bg-slate-200 border border-white/10">
              <img src="<?= e($playlist['backdrop_image']) ?>" alt="پس‌زمینه‌ی فعلی"
                   class="h-12 w-auto block rounded-lg">
            </div>
          <?php else: ?>
            <div class="px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-xs text-slate-500">
              تصویری انتخاب نشده
            </div>
          <?php endif; ?>

          <form method="POST" action="/admin/playlists/<?= (int)$playlist['id'] ?>/backdrop"
                enctype="multipart/form-data" class="flex items-center gap-2 flex-wrap">
            <input type="file" name="backdrop" accept="image/png,image/jpeg,image/webp"
                   class="form-input text-xs py-2" required>
            <button type="submit" class="btn-primary px-5 py-2 text-sm">بارگذاری</button>
          </form>

          <?php if (!empty($playlist['backdrop_image'])): ?>
          <form method="POST" action="/admin/playlists/<?= (int)$playlist['id'] ?>/backdrop"
                onsubmit="return confirm('تصویر پس‌زمینه حذف شود؟');">
            <input type="hidden" name="remove" value="1">
            <button type="submit" class="btn-ghost px-4 py-2 text-sm">حذف</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
