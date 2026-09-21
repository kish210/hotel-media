/**
 * شبیه‌ساز پلتفرم تلویزیون
 *
 * کد شناسایی دستگاه در bootstrap.php را با APIهای واقعی Tizen و webOS
 * اجرا می‌کند تا معلوم شود روی تلویزیون واقعی چه چیزی خوانده می‌شود.
 * چون تلویزیون فیزیکی اینجا نیست، این نزدیک‌ترین تست ممکن است.
 *
 * اجرا:  node tests/Support/tv-platform-sim.js
 */
'use strict';

const fs   = require('fs');
const path = require('path');
const vm   = require('vm');

const BOOTSTRAP = path.join(__dirname, '..', '..', 'resources', 'views', 'player', 'bootstrap.php');

let pass = 0, fail = 0;
function check(label, ok, detail) {
  if (ok) { pass++; console.log('  ✅ ' + label); }
  else    { fail++; console.log('  ❌ ' + label + (detail ? '\n       → ' + detail : '')); }
}

// ── استخراج تابع deviceInfo از فایل bootstrap ────────────────────
const src = fs.readFileSync(BOOTSTRAP, 'utf8');
const scriptMatch = src.match(/<script>([\s\S]*?)<\/script>/);
if (!scriptMatch) { console.error('بلاک <script> در bootstrap.php پیدا نشد'); process.exit(1); }
let js = scriptMatch[1];

// در حالت تست، خودِ enroll نباید اجرا شود — فقط توابع را می‌خواهیم
js = js.replace(/var TOKEN\s*=\s*'[^']*';/, "var TOKEN = 'TESTTOKEN';");
js = js.replace(/var known = loadCode\(\);[\s\S]*$/, 'window.__deviceInfo = deviceInfo;\n})();');

/** یک محیط تلویزیون می‌سازد و deviceInfo را در آن اجرا می‌کند */
function runOn(env) {
  const win = {
    localStorage: (function () {
      const m = {};
      return {
        getItem: k => (k in m ? m[k] : null),
        setItem: (k, v) => { m[k] = String(v); },
      };
    })(),
    location: { href: '' },
    XMLHttpRequest: function () {
      this.open = () => {}; this.setRequestHeader = () => {};
      this.send = () => {}; this.readyState = 0;
    },
  };

  Object.assign(win, env.globals || {});

  const sandbox = {
    window: win,
    document: {
      cookie: '',
      getElementById: () => ({ innerHTML: '', className: '' }),
    },
    navigator: { userAgent: env.ua },
    screen: { width: 1920, height: 1080 },
    setTimeout, setInterval, clearInterval,
    XMLHttpRequest: win.XMLHttpRequest,
  };
  sandbox.window.window = sandbox.window;
  // کد از window.localStorage استفاده می‌کند، ولی بعضی جاها مستقیم
  sandbox.localStorage = win.localStorage;

  vm.createContext(sandbox);
  vm.runInContext(js, sandbox, { timeout: 5000 });

  return new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error('deviceInfo پاسخ نداد (callback صدا نشد)')), 3000);
    sandbox.window.__deviceInfo(info => { clearTimeout(t); resolve(info); });
  });
}

(async function () {
  console.log('\n── ۱) سازگاری نحوی با موتورهای قدیمی ──');

  // Tizen 2.3/3.0 و webOS 3 روی WebKit قدیمی‌اند: ES5 only.
  // کامنت‌ها حذف می‌شوند چون خودِ توضیحات این ممنوعه‌ها را نام می‌برند.
  const code = js
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/(^|[^:])\/\/[^\n]*/g, '$1');

  const es6 = [];
  if (/=>\s*[{(]/.test(code))         es6.push('arrow function');
  if (/\bconst\s+\w+\s*=/.test(code)) es6.push('const');
  if (/\blet\s+\w+\s*=/.test(code))   es6.push('let');
  if (code.includes('`'))             es6.push('template literal');
  if (/\bfetch\s*\(/.test(code))      es6.push('fetch()');
  if (/\bPromise\b/.test(code))       es6.push('Promise');
  if (/\.\.\./.test(code))            es6.push('spread');
  check('کد ES5 خالص است', es6.length === 0, 'یافت شد: ' + es6.join('، '));

  // خود موتور باید بتواند در حالت ES5 پارسش کند
  try {
    new vm.Script(js, { filename: 'bootstrap.js' });
    check('کد بدون خطای نحوی پارس می‌شود', true);
  } catch (e) {
    check('کد بدون خطای نحوی پارس می‌شود', false, e.message);
  }

  // ── ۲) Samsung Tizen — تلویزیون هتلی ──────────────────────────
  console.log('\n── ۲) Samsung Tizen (تلویزیون هتلی) ──');

  const tizenInfo = await runOn({
    ua: 'Mozilla/5.0 (SMART-TV; LINUX; Tizen 6.0) AppleWebKit/537.36 (KHTML, like Gecko) ' +
        'Version/6.0 TV Safari/537.36',
    globals: {
      tizen: {
        systeminfo: {
          getPropertyValue(prop, ok) {
            if (prop === 'ETHERNET_NETWORK') {
              setTimeout(() => ok({ macAddress: 'B4:2E:99:11:22:33' }), 5);
            }
          },
        },
      },
      webapis: {
        productinfo: {
          getModel:    () => 'HG43AU800',
          getFirmware: () => 'T-KTMAKUC-1460.3',
          getDuid:     () => 'DUID-SAMSUNG-TEST',
        },
      },
      b2bapis: {
        b2bcontrol: { getSerialNumber: () => 'SN-SAMSUNG-9988' },
      },
    },
  });

  check('پلتفرم tizen تشخیص داده شد', tizenInfo.platform === 'tizen', 'platform=' + tizenInfo.platform);
  check('MAC از tizen.systeminfo خوانده شد',
    tizenInfo.mac === 'B4:2E:99:11:22:33', 'mac=' + tizenInfo.mac);
  check('مدل از webapis.productinfo خوانده شد',
    tizenInfo.model === 'HG43AU800', 'model=' + tizenInfo.model);
  check('فریم‌ور خوانده شد',
    tizenInfo.firmware === 'T-KTMAKUC-1460.3', 'firmware=' + tizenInfo.firmware);
  check('سریال از b2bapis (API تلویزیون هتلی) خوانده شد',
    tizenInfo.serial === 'SN-SAMSUNG-9988', 'serial=' + tizenInfo.serial);
  check('رزولوشن ارسال می‌شود', tizenInfo.resolution === '1920x1080');

  // ── ۳) Tizen قدیمی بدون b2bapis ───────────────────────────────
  console.log('\n── ۳) Tizen قدیمی — بدون API هتلی ──');

  const tizenOld = await runOn({
    ua: 'Mozilla/5.0 (SMART-TV; X11; Linux armv7l) AppleWebKit/538.1 (KHTML, like Gecko) ' +
        'Tizen/2.3 Safari/538.1',
    globals: {
      tizen: {
        systeminfo: {
          getPropertyValue(prop, ok, err) {
            // مدل‌های قدیمی گاهی روی ETHERNET خطا می‌دهند
            setTimeout(() => err(new Error('not supported')), 5);
          },
        },
      },
      webapis: { productinfo: { getModel: () => 'HG32EE690', getFirmware: () => 'T-OLD' } },
    },
  });

  check('پلتفرم tizen حتی بدون b2bapis تشخیص داده شد', tizenOld.platform === 'tizen');
  check('نبودن MAC باعث کرش نشد', tizenOld.mac === '', 'mac=' + JSON.stringify(tizenOld.mac));
  check('مدل با وجود خطای شبکه خوانده شد', tizenOld.model === 'HG32EE690');
  check('سریال خالی می‌ماند نه undefined', tizenOld.serial === '');

  // ── ۴) Tizen با خطا در همه APIها ──────────────────────────────
  console.log('\n── ۴) Tizen خراب — همه APIها خطا می‌دهند ──');

  const tizenBroken = await runOn({
    ua: 'Mozilla/5.0 (SMART-TV; Linux; Tizen 4.0) AppleWebKit/538.1',
    globals: {
      tizen: { systeminfo: { getPropertyValue() { throw new Error('boom'); } } },
      webapis: { productinfo: { getModel() { throw new Error('boom'); } } },
      b2bapis: { b2bcontrol: { getSerialNumber() { throw new Error('boom'); } } },
    },
  });

  check('با خطای همه APIها باز هم callback صدا شد', !!tizenBroken);
  check('پلتفرم از User-Agent تشخیص داده شد', tizenBroken.platform === 'tizen');
  check('User-Agent برای سرور ارسال می‌شود', (tizenBroken.user_agent || '').includes('Tizen'));

  // ── ۵) LG webOS ───────────────────────────────────────────────
  console.log('\n── ۵) LG webOS ──');

  const webosInfo = await runOn({
    ua: 'Mozilla/5.0 (Web0S; Linux/SmartTV) AppleWebKit/537.36 (KHTML, like Gecko) ' +
        'Chrome/68.0 Safari/537.36 WebAppManager',
    globals: {
      webOS: {
        deviceInfo(cb) {
          setTimeout(() => cb({
            modelName: '43UT570H', version: '5.2.0', serialNumber: 'SN-LG-1234',
          }), 5);
        },
        service: {
          request(uri, opts) {
            setTimeout(() => opts.onSuccess({
              wiredInfo: { macAddress: 'CC:2D:8C:44:55:66' },
            }), 5);
          },
        },
      },
    },
  });

  check('پلتفرم webos تشخیص داده شد', webosInfo.platform === 'webos', 'platform=' + webosInfo.platform);
  check('مدل از webOS.deviceInfo خوانده شد', webosInfo.model === '43UT570H', 'model=' + webosInfo.model);
  check('سریال خوانده شد', webosInfo.serial === 'SN-LG-1234');
  check('MAC از سرویس Luna خوانده شد',
    webosInfo.mac === 'CC:2D:8C:44:55:66', 'mac=' + webosInfo.mac);

  // ── ۶) webOS بدون سرویس Luna (نسخه‌های قدیمی) ────────────────
  console.log('\n── ۶) webOS قدیمی — بدون سرویس Luna ──');

  const webosOld = await runOn({
    ua: 'Mozilla/5.0 (Web0S; Linux/SmartTV) AppleWebKit/538.2 Safari/538.2',
    globals: {
      webOS: { deviceInfo(cb) { setTimeout(() => cb({ modelName: 'OLD-LG' }), 5); } },
    },
  });

  check('بدون سرویس Luna هم پاسخ داد', webosOld.platform === 'webos');
  check('MAC خالی می‌ماند (fallback سمت صفحه اعمال می‌شود)', webosOld.mac === '');

  // ── ۷) Android WebView ────────────────────────────────────────
  console.log('\n── ۷) Android TV ──');

  const androidInfo = await runOn({
    ua: 'Mozilla/5.0 (Linux; Android 11; MiBOX4 Build/RTT) AppleWebKit/537.36',
    globals: {
      SignageNative: {
        getMac:     () => 'DC:A6:32:77:88:99',
        getModel:   () => 'MiBOX4',
        getSerial:  () => 'SN-ANDROID-777',
        getVersion: () => '2.1.0',
      },
    },
  });

  check('پلتفرم android تشخیص داده شد', androidInfo.platform === 'android');
  check('MAC از رابط بومی خوانده شد', androidInfo.mac === 'DC:A6:32:77:88:99');
  check('نسخه اپ خوانده شد', androidInfo.app_version === '2.1.0');

  // ── ۸) مرورگر معمولی ──────────────────────────────────────────
  console.log('\n── ۸) مرورگر معمولی (بدون هیچ API تلویزیون) ──');

  const plain = await runOn({
    ua: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0',
    globals: {},
  });

  check('بدون API تلویزیون هم کرش نکرد', !!plain);
  check('پلتفرم خالی می‌ماند تا سرور از UA تشخیص دهد', plain.platform === '');

  console.log('\n' + '─'.repeat(58));
  console.log(fail === 0 ? `✅ هر ${pass} تست پاس شد` : `❌ ${fail} شکست از ${pass + fail} تست`);
  process.exit(fail === 0 ? 0 : 1);
})().catch(e => {
  console.error('\n❌ تست با خطا متوقف شد: ' + e.message);
  process.exit(1);
});
