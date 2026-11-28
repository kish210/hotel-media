const { app, BrowserWindow, ipcMain, Tray, Menu, powerSaveBlocker } = require('electron');
const path  = require('path');
const fs    = require('fs');
const https = require('https');
const http  = require('http');

// ── تنظیمات ──────────────────────────────────────────────────────
const CONFIG_FILE = path.join(app.getPath('userData'), 'config.json');

/* پیش‌فرض‌ها همان رفتار نسخه‌ی ۱.۵ است تا نصب‌های موجود بی‌دلیل
   عوض نشوند؛ تکنسین از بخش «تنظیمات» هر کدام را خاموش می‌کند. */
const DEFAULT_SETTINGS = {
  autoStart:         true,   // با روشن شدن ویندوز بالا بیاید
  keepAwake:         true,   // صفحه/سیستم به خواب نرود
  hideCursor:        true,   // نشانگر ماوس روی تلویزیون دیده نشود
  alwaysOnTop:       true,   // پنجره‌ی پلیر همیشه جلو
  retrySeconds:      10,     // تلاش دوباره بعد از قطعی
  autoReloadMinutes: 0,      // ۰ = خاموش؛ مثلا ۳۶۰ برای تازه‌سازی ساعتی
};

function normalize(cfg) {
  cfg = (cfg && typeof cfg === 'object') ? cfg : {};
  const src = (cfg.settings && typeof cfg.settings === 'object') ? cfg.settings : {};
  const s = {};
  for (const k of Object.keys(DEFAULT_SETTINGS)) {
    s[k] = (typeof src[k] === typeof DEFAULT_SETTINGS[k]) ? src[k] : DEFAULT_SETTINGS[k];
  }
  return {
    serverUrl:  typeof cfg.serverUrl === 'string' ? cfg.serverUrl : '',
    screenCode: typeof cfg.screenCode === 'string' ? cfg.screenCode : '',
    settings:   s,
  };
}

function loadConfig() {
  try {
    if (fs.existsSync(CONFIG_FILE)) return normalize(JSON.parse(fs.readFileSync(CONFIG_FILE, 'utf8')));
  } catch (e) {}
  return normalize(null);
}

function saveConfig(cfg) {
  try {
    fs.mkdirSync(path.dirname(CONFIG_FILE), { recursive: true });
    fs.writeFileSync(CONFIG_FILE, JSON.stringify(cfg, null, 2));
  } catch (e) { console.error('[Player] ذخیره تنظیمات نشد:', e.message); }
}

// ── Global ───────────────────────────────────────────────────────
let mainWindow  = null;
let tray        = null;
let config      = loadConfig();
let retryTimer  = null;
let reloadTimer = null;
let blockerId   = -1;
let cursorCssKey = null;
let quitting    = false;

// ── اتصال به سرور ────────────────────────────────────────────────
function pingServer(url, cb) {
  let done = false;
  const finish = (ok) => { if (!done) { done = true; cb(ok); } };
  try {
    const mod = url.startsWith('https') ? https : http;
    const req = mod.get(url, { timeout: 5000 }, (res) => {
      res.resume();
      finish(res.statusCode < 500);
    });
    req.on('error', () => finish(false));
    req.on('timeout', () => { req.destroy(); finish(false); });
  } catch (e) { finish(false); }
}

function playerUrl() {
  if (!config.serverUrl) return '';
  const base = config.serverUrl.replace(/\/+$/, '');
  return config.screenCode
    ? `${base}/player/${encodeURIComponent(config.screenCode)}`
    : `${base}/player/`;
}

// ── تنظیمات که روی سیستم اثر دارند ────────────────────────────────
function applyAutoStart() {
  /* در حالت توسعه (electron .) مسیر اجرا خودِ electron است و ثبت آن
     باعث می‌شود ویندوز برنامه‌ی اشتباهی را بالا بیاورد. */
  if (!app.isPackaged) return;
  try {
    app.setLoginItemSettings({
      openAtLogin: !!config.settings.autoStart,
      args: [],
    });
  } catch (e) { console.error('[Player] اجرای خودکار تنظیم نشد:', e.message); }
}

function applyKeepAwake() {
  try {
    if (config.settings.keepAwake) {
      if (blockerId === -1 || !powerSaveBlocker.isStarted(blockerId)) {
        blockerId = powerSaveBlocker.start('prevent-display-sleep');
      }
    } else if (blockerId !== -1 && powerSaveBlocker.isStarted(blockerId)) {
      powerSaveBlocker.stop(blockerId);
      blockerId = -1;
    }
  } catch (e) { /* روی بعضی ویندوزهای سرور پشتیبانی نمی‌شود */ }
}

function applyCursor() {
  if (!mainWindow) return;
  const wc = mainWindow.webContents;
  const wantHidden = !!config.settings.hideCursor;

  if (wantHidden && !cursorCssKey) {
    wc.insertCSS('* { cursor: none !important; }').then((key) => { cursorCssKey = key; }).catch(() => {});
  } else if (!wantHidden && cursorCssKey) {
    wc.removeInsertedCSS(cursorCssKey).catch(() => {});
    cursorCssKey = null;
  }
}

function applyWatchdog() {
  clearInterval(reloadTimer);
  const mins = Number(config.settings.autoReloadMinutes) || 0;
  if (mins > 0) {
    reloadTimer = setInterval(() => {
      if (mainWindow) mainWindow.webContents.reloadIgnoringCache();
    }, mins * 60 * 1000);
  }
}

function applyAll() {
  applyAutoStart();
  applyKeepAwake();
  applyCursor();
  applyWatchdog();
  if (mainWindow) {
    mainWindow.setAlwaysOnTop(!!config.settings.alwaysOnTop);
  }
}

// ── پنجره اصلی (Player) ──────────────────────────────────────────
function createPlayerWindow(url) {
  clearTimeout(retryTimer);

  if (mainWindow) {
    mainWindow.loadURL(url);
    mainWindow.show();
    mainWindow.focus();
    return;
  }

  mainWindow = new BrowserWindow({
    fullscreen:        true,
    frame:             false,
    alwaysOnTop:       !!config.settings.alwaysOnTop,
    autoHideMenuBar:   true,
    backgroundColor:   '#000000',
    webPreferences: {
      preload:              path.join(__dirname, 'preload.js'),
      contextIsolation:     true,
      nodeIntegration:      false,
      webSecurity:          true,
      backgroundThrottling: false,
      allowRunningInsecureContent: true,  // برای HTTP player
    },
  });

  mainWindow.setMenuBarVisibility(false);

  // حذف منوی راست‌کلیک در production
  if (!process.argv.includes('--dev')) {
    mainWindow.webContents.on('context-menu', (e) => e.preventDefault());
  }

  // جلوگیری از navigation خارج از سرور
  mainWindow.webContents.on('will-navigate', (e, navUrl) => {
    try {
      const base = new URL(config.serverUrl).origin;
      if (!navUrl.startsWith(base)) e.preventDefault();
    } catch (err) { e.preventDefault(); }
  });

  // پنجره‌های popup (لینک‌های بیرونی) باز نشوند
  mainWindow.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));

  mainWindow.webContents.on('did-finish-load', () => {
    cursorCssKey = null;
    applyCursor();
  });

  mainWindow.webContents.on('did-fail-load', (_e, code, desc, validatedURL, isMainFrame) => {
    if (!isMainFrame || code === -3) return;   // -3 = ابطال عمدی ناوبری
    console.log(`[Player] Load failed: ${code} ${desc} — retrying`);
    scheduleRetry();
  });

  mainWindow.webContents.on('render-process-gone', () => {
    console.log('[Player] renderer gone — reloading');
    scheduleRetry();
  });

  mainWindow.on('unresponsive', () => {
    console.log('[Player] unresponsive — reloading');
    if (mainWindow) mainWindow.webContents.reload();
  });

  mainWindow.loadURL(url);
  mainWindow.on('closed', () => { mainWindow = null; cursorCssKey = null; });
}

function scheduleRetry() {
  const secs = Math.max(3, Number(config.settings.retrySeconds) || 10);
  clearTimeout(retryTimer);
  retryTimer = setTimeout(() => {
    if (!mainWindow) return;
    const url = playerUrl();
    if (url) mainWindow.loadURL(url);
  }, secs * 1000);
}

// ── پنجره Setup / تنظیمات ────────────────────────────────────────
function createSetupWindow() {
  if (mainWindow && mainWindow.isVisible() && !mainWindow.isFullScreen()) {
    mainWindow.focus();
    return;
  }
  if (mainWindow) { mainWindow.close(); mainWindow = null; }

  mainWindow = new BrowserWindow({
    width:           560,
    height:          720,
    minWidth:        480,
    minHeight:       600,
    resizable:       true,
    frame:           true,
    alwaysOnTop:     false,
    autoHideMenuBar: true,
    backgroundColor: '#0a0a14',
    title:           'Hotel Media Player — تنظیمات',
    webPreferences: {
      preload:          path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration:  false,
    },
  });

  mainWindow.loadFile('setup.html');
  mainWindow.on('closed', () => { mainWindow = null; });
}

// ── Tray Icon ────────────────────────────────────────────────────
function createTray() {
  const iconPath = path.join(__dirname, 'assets', 'tray.png');
  if (!fs.existsSync(iconPath)) return;

  try {
    tray = new Tray(iconPath);
    tray.setToolTip('Hotel Media Player');
    updateTrayMenu();
  } catch (e) { tray = null; }
}

function updateTrayMenu() {
  if (!tray) return;
  const menu = Menu.buildFromTemplate([
    { label: 'Hotel Media Player', enabled: false },
    { type: 'separator' },
    { label: 'تنظیمات / Setup',  click: () => createSetupWindow() },
    { label: 'بارگذاری مجدد',    click: () => mainWindow?.webContents.reload() },
    { label: 'Dev Tools',         click: () => mainWindow?.webContents.openDevTools(), visible: process.argv.includes('--dev') },
    { type: 'separator' },
    { label: 'خروج',              click: () => { quitting = true; app.quit(); } },
  ]);
  tray.setContextMenu(menu);
  tray.on('double-click', () => createSetupWindow());
}

// ── IPC handlers ─────────────────────────────────────────────────
ipcMain.handle('get-config', () => ({
  serverUrl:  config.serverUrl,
  screenCode: config.screenCode,
  settings:   config.settings,
  autoStartActive: app.isPackaged ? app.getLoginItemSettings().openAtLogin : null,
  version:    app.getVersion(),
}));

ipcMain.handle('save-and-launch', async (_e, payload) => {
  const serverUrl = String(payload?.serverUrl || '').trim().replace(/\/+$/, '');
  if (!serverUrl) return { ok: false, error: 'آدرس سرور خالی است' };

  const screenCode = String(payload?.screenCode || '').trim().toUpperCase().replace(/\s+/g, '');
  const settings = normalize({ settings: { ...config.settings, ...(payload?.settings || {}) } }).settings;

  const url = screenCode
    ? `${serverUrl}/player/${encodeURIComponent(screenCode)}`
    : `${serverUrl}/player/`;

  return new Promise((resolve) => {
    pingServer(url, (ok) => {
      if (!ok) {
        resolve({ ok: false, error: 'سرور در دسترس نیست — آدرس را بررسی کنید' });
        return;
      }
      config = { serverUrl, screenCode, settings };
      saveConfig(config);
      applyAll();
      createPlayerWindow(url);
      resolve({ ok: true });
    });
  });
});

/* ذخیره‌ی تنظیمات بدون شروع پلیر (وقتی پلیر در حال پخش است) */
ipcMain.handle('save-settings', (_e, settings) => {
  config = { ...config, settings: normalize({ settings: { ...config.settings, ...(settings || {}) } }).settings };
  saveConfig(config);
  applyAll();
  return { ok: true, settings: config.settings };
});

ipcMain.handle('open-setup', () => createSetupWindow());
ipcMain.handle('ping', (_e, url) => new Promise((resolve) => pingServer(String(url || ''), resolve)));
ipcMain.handle('quit', () => { quitting = true; app.quit(); });

// ── App lifecycle ────────────────────────────────────────────────
const gotLock = app.requestSingleInstanceLock();
if (!gotLock) {
  /* ویندوز هنگام ورود ممکن است دو بار اجرا کند؛ دو پلیر روی هم
     یعنی دو پخش همزمان و پورتال تار. */
  app.quit();
} else {
  app.on('second-instance', () => {
    if (mainWindow) { mainWindow.show(); mainWindow.focus(); }
    else if (config.serverUrl) createPlayerWindow(playerUrl());
  });

  app.whenReady().then(() => {
    createTray();
    applyKeepAwake();
    applyAutoStart();

    if (config.serverUrl) {
      createPlayerWindow(playerUrl());
    } else {
      createSetupWindow();
    }

    // کلید F5 برای reload و F12 برای DevTools
    app.on('web-contents-created', (_e, wc) => {
      wc.on('before-input-event', (ev, input) => {
        if (input.key === 'F5')  wc.reload();
        if (input.key === 'F12') wc.openDevTools();
      });
    });
  });
}

app.on('before-quit', () => { quitting = true; clearInterval(reloadTimer); });

app.on('window-all-closed', () => {
  // tray هست — برنامه باز می‌مونه
});

app.on('activate', () => {
  if (!mainWindow) {
    config.serverUrl ? createPlayerWindow(playerUrl()) : createSetupWindow();
  }
});
