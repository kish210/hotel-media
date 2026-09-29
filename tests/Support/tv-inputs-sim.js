/**
 * شبیه‌ساز تعویض ورودی (نمایش گوشی/لپ‌تاپ مهمان روی تلویزیون)
 *
 * TV.inputs در tv-base.js را روی APIهای جعلی LG HCAP، Samsung Tizen،
 * اپ اندروید و webOS اجرا می‌کند. مهم‌ترین قاعده: بعد از خاموش و روشن
 * شدن (یا reload پورتال) تلویزیون باید روی پورتال باشد، نه روی HDMI
 * مهمان قبلی.
 *
 * اجرا:  node tests/Support/tv-inputs-sim.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const SRC = fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'tv-base.js'), 'utf8');

let pass = 0, fail = 0;
function check(label, ok, detail) {
  if (ok) { pass++; console.log('  ✅ ' + label); }
  else    { fail++; console.log('  ❌ ' + label + (detail ? '\n       → ' + detail : '')); }
}

/** یک «تلویزیون» تازه؛ storage بین روشن/خاموش‌ها مشترک می‌ماند */
function makeTV(extra, storage) {
  const handlers = {};
  const doc = {
    hidden: false,
    documentElement: { clientWidth: 1920, style: {}, className: '' },
    body: { clientWidth: 1920, appendChild() {} },
    addEventListener(ev, fn) { (handlers[ev] = handlers[ev] || []).push(fn); },
    getElementById() { return null; },
    getElementsByTagName() { return []; },
    createElement() { return { style: {}, setAttribute() {}, appendChild() {} }; }
  };
  const store = storage || {};
  const win = Object.assign({
    document: doc,
    innerWidth: 1920, innerHeight: 1080,
    screen: { width: 1920, height: 1080 },
    localStorage: { getItem: k => (k in store ? store[k] : null), setItem: (k, v) => { store[k] = String(v); } },
    addEventListener(ev, fn) { (handlers['win:' + ev] = handlers['win:' + ev] || []).push(fn); },
    setTimeout: (fn) => { fn.__t = true; return 0; },   // مهلت‌ها در تست فوری اجرا نمی‌شوند
    clearTimeout() {},
    navigator: { userAgent: 'test' },
    JSON, Math, String, Number, Error, parseInt
  }, extra || {});
  win.window = win;
  vm.runInNewContext(SRC, Object.assign(win, { document: doc }));
  return {
    TV: win.TV, win, doc, store,
    fire(ev) { (handlers[ev] || []).forEach(f => f({})); },
    fireWin(ev) { (handlers['win:' + ev] || []).forEach(f => f({})); }
  };
}

// ════════════════════════════════════════════════════════════════════
//  LG Pro:Centric — HCAP
// ════════════════════════════════════════════════════════════════════
console.log('\n── LG Pro:Centric (HCAP) ──');
function fakeHcap(state) {
  const T = { TV: 1, HDMI: 6 };
  return {
    externalinput: {
      ExternalInputType: T,
      getCurrentExternalInput(o) { o.onSuccess({ type: state.cur.type, index: state.cur.index }); },
      setCurrentExternalInput(o) { state.sets.push({ type: o.type, index: o.index }); state.cur = { type: o.type, index: o.index }; o.onSuccess({}); },
      getExternalInputList(o) { o.onSuccess({ list: [{ type: T.TV, index: 0, name: 'TV' }, { type: T.HDMI, index: 0, name: 'HDMI1' }, { type: T.HDMI, index: 1, name: 'HDMI2' }] }); },
      isExternalInputConnected(o) { o.onSuccess({ isConnected: o.index === 1 }); }
    }
  };
}

const hs = { cur: { type: 1, index: 0 }, sets: [] };
const disk = {};                                 // localStorage تلویزیون، بین روشن/خاموش
let tv = makeTV({ hcap: fakeHcap(hs) }, disk);
check('HCAP تشخیص داده شد', tv.TV.inputs.supported() === 'hcap');
tv.TV.inputs.boot();
check('بوت روی ورودی پورتال: چیزی عوض نشد و ورودی پورتال ذخیره شد', hs.sets.length === 0 && JSON.parse(disk.hm_home_input).type === 1);

let list = null;
tv.TV.inputs.list((e, l) => { list = l; });
check('فهرست فقط HDMIها، بدون تیونر', list && list.length === 2 && list.every(x => x.type === 'HDMI'), JSON.stringify(list));
check('اتصال HDMI2 تشخیص داده شد', list && list[1].connected === true && list[0].connected === false);

let changes = [];
tv.TV.inputs.onChange(s => changes.push(s ? s.kind : null));
tv.TV.inputs.switchTo(list[1], () => {});
check('رفتن به HDMI2', hs.cur.type === 6 && hs.cur.index === 1);
check('جلسه با پورتال شفاف (overlay)', tv.TV.inputs.active() && tv.TV.inputs.active().overlay === true);

tv.TV.inputs.back();
check('BACK: برگشت به ورودی پورتال', hs.cur.type === 1 && hs.cur.index === 0 && tv.TV.inputs.active() === null);
check('رابط کاربر هر دو تغییر را شنید', changes.join(',') === 'hcap,');

tv.TV.inputs.switchTo(list[1], () => {});
tv.fire('power_mode_changed');                   // رفتن به آماده‌به‌کار
check('خاموش شدن (آماده‌به‌کار) وسط HDMI: برگشت به پورتال', hs.cur.type === 1 && tv.TV.inputs.active() === null);

/* قطع برق وسط HDMI: هیچ رویدادی نرسید، تلویزیون با همان HDMI روشن شد */
hs.cur = { type: 6, index: 1 }; hs.sets = [];
tv = makeTV({ hcap: fakeHcap(hs) }, disk);
tv.TV.inputs.boot();
check('روشن شدن بعد از قطع برق روی HDMI: پورتال خودش برمی‌گرداند', hs.cur.type === 1 && hs.cur.index === 0, JSON.stringify(hs.sets));

/* بیدار شدن بدون reload صفحه، با HDMI جامانده (مثلا از دکمه‌ی INPUT ریموت) */
hs.cur = { type: 6, index: 0 };
tv.fire('power_mode_changed');
check('بیدار شدن با HDMI جامانده: برگشت به پورتال', hs.cur.type === 1);

// ════════════════════════════════════════════════════════════════════
//  Samsung — Tizen tvwindow
// ════════════════════════════════════════════════════════════════════
console.log('\n── Samsung (Tizen) ──');
const ts = { shown: false, source: { type: 'TV', number: 1 }, hides: 0, rect: null };
const tizen = {
  systeminfo: { getPropertyValue(p, ok) { ok({ connected: [{ type: 'TV', number: 1 }, { type: 'HDMI', number: 2 }] }); } },
  tvwindow: {
    getSource() { return ts.source; },
    setSource(src, ok) { ts.source = src; ok(); },
    show(ok, err, rect) { ts.shown = true; ts.rect = rect; ok(); },
    hide(ok) { ts.shown = false; ts.hides++; ok && ok(); }
  }
};
tv = makeTV({ tizen });
check('Tizen تشخیص داده شد', tv.TV.inputs.supported() === 'tizen');
tv.TV.inputs.boot();
check('بوت: پنجره‌ی ورودیِ احتمالی بسته شد', ts.hides === 1);
tv.TV.inputs.list((e, l) => { list = l; });
check('فهرست Tizen بدون تیونر', list.length === 1 && list[0].label === 'HDMI 2');
tv.TV.inputs.switchTo(list[0], () => {});
check('HDMI داخل پنجره‌ی خود اپ، تمام‌صفحه', ts.shown && ts.source.type === 'HDMI' && ts.rect.join() === '0px,0px,1920px,1080px');
tv.doc.hidden = true; tv.fire('visibilitychange');
check('اپ به پس‌زمینه/خاموشی رفت: پنجره بسته و منبع قبلی برگشت', !ts.shown && ts.source.type === 'TV' && tv.TV.inputs.active() === null);

// ════════════════════════════════════════════════════════════════════
//  اپ اندروید
// ════════════════════════════════════════════════════════════════════
console.log('\n── اندروید (اپ خودمان) ──');
const as = { switched: null, closed: 0 };
const AndroidBridge = {
  listInputs: () => JSON.stringify([{ id: 'hw:hdmi1', label: 'HDMI 1', type: 'HDMI', connected: true }]),
  switchInput: id => { as.switched = id; return true; },
  closeInput: () => { as.closed++; },
  getKioskState: () => '{"deviceOwner":true,"locked":false}'
};
tv = makeTV({ AndroidBridge });
check('پل با نام AndroidBridge پیدا شد (قبلا فقط SignageBridge)', tv.TV.inputs.supported() === 'android');
check('قفل‌گاه هم از همان پل', tv.TV.kiosk.state().available === true && tv.TV.kiosk.state().deviceOwner === true);
tv.TV.inputs.boot();
check('بوت: ورودی بازمانده بسته شد', as.closed === 1);
tv.TV.inputs.switchTo({ id: 'hw:hdmi1', label: 'HDMI 1' }, () => {});
check('تعویض به اپ سپرده شد و پورتال شفاف نمی‌شود', as.switched === 'hw:hdmi1' && tv.TV.inputs.active().overlay === false);
tv.win.TVInputClosed();
check('اپ خبر داد ورودی بسته شد: جلسه تمام', tv.TV.inputs.active() === null);

// ════════════════════════════════════════════════════════════════════
//  webOS معمولی
// ════════════════════════════════════════════════════════════════════
console.log('\n── webOS (بدون HCAP) ──');
let launched = null;
tv = makeTV({ webOS: { service: { request(uri, o) { launched = o.parameters.id; o.onSuccess({}); } } } });
tv.TV.inputs.boot();
tv.TV.inputs.switchTo({ id: 'com.webos.app.hdmi2', label: 'HDMI 2' }, () => {});
check('اپ HDMI باز شد', launched === 'com.webos.app.hdmi2' && tv.TV.inputs.active().kind === 'webos');
tv.fireWin('webOSRelaunch');
check('برگشت به پورتال (relaunch): جلسه تمام', tv.TV.inputs.active() === null);

// ── بدون هیچ API ─────────────────────────────────────────────────
console.log('\n── مرورگر معمولی ──');
tv = makeTV({});
let err = null;
tv.TV.inputs.switchTo({ id: 'x' }, e => { err = e; });
check('بدون API: خطای روشن، نه سکوت', tv.TV.inputs.supported() === '' && err instanceof Error);

console.log('\n' + '─'.repeat(58));
console.log(fail === 0 ? `✅ هر ${pass} تست پاس شد` : `❌ ${fail} شکست از ${pass + fail} تست`);
process.exit(fail === 0 ? 0 : 1);
