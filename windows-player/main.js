const { app, BrowserWindow, ipcMain, Tray, Menu, dialog, shell } = require('electron');
const path  = require('path');
const fs    = require('fs');
const https = require('https');
const http  = require('http');

// ── تنظیمات ذخیره‌شده ────────────────────────────────────────────
const CONFIG_FILE = path.join(app.getPath('userData'), 'config.json');

function loadConfig() {
  try {
    if (fs.existsSync(CONFIG_FILE)) {
      return JSON.parse(fs.readFileSync(CONFIG_FILE, 'utf8'));
    }
  } catch (e) {}
  return { serverUrl: '', screenCode: '' };
}

function saveConfig(cfg) {
  try { fs.writeFileSync(CONFIG_FILE, JSON.stringify(cfg, null, 2)); } catch (e) {}
}

// ── Global ───────────────────────────────────────────────────────
let mainWindow = null;
let tray       = null;
let config     = loadConfig();
let retryTimer = null;

// ── اتصال به سرور ────────────────────────────────────────────────
function pingServer(url, cb) {
  try {
    const mod = url.startsWith('https') ? https : http;
    const req = mod.get(url, { timeout: 5000 }, (res) => cb(res.statusCode < 500))
      .on('error', () => cb(false))
      .on('timeout', () => { req.destroy(); cb(false); });
  } catch (e) { cb(false); }
}

// ── پنجره اصلی (Player) ──────────────────────────────────────────
function createPlayerWindow(url) {
  if (mainWindow) { mainWindow.close(); mainWindow = null; }
  clearTimeout(retryTimer);

  mainWindow = new BrowserWindow({
    fullscreen:        true,
    frame:             false,
    alwaysOnTop:       true,
    autoHideMenuBar:   true,
    backgroundColor:   '#000000',
    webPreferences: {
      preload:              path.join(__dirname, 'preload.js'),
      contextIsolation:     true,
      nodeIntegration:      false,
      webSecurity:          true,
      allowRunningInsecureContent: true,  // برای HTTP player
    },
  });

  // حذف منوی راست‌کلیک در production
  if (!process.argv.includes('--dev')) {
    mainWindow.webContents.on('context-menu', (e) => e.preventDefault());
  }

  // جلوگیری از navigation خارج از سرور
  mainWindow.webContents.on('will-navigate', (e, navUrl) => {
    const base = new URL(config.serverUrl).origin;
    if (!navUrl.startsWith(base)) e.preventDefault();
  });

  mainWindow.webContents.on('did-fail-load', (_e, code, desc) => {
    console.log(`[Player] Load failed: ${code} ${desc} — retrying in 10s`);
    retryTimer = setTimeout(() => {
      if (mainWindow) mainWindow.loadURL(url);
    }, 10000);
  });

  mainWindow.loadURL(url);
  mainWindow.on('closed', () => { mainWindow = null; });
}

// ── پنجره Setup (اولین اجرا) ────────────────────────────────────
function createSetupWindow() {
  if (mainWindow) { mainWindow.close(); mainWindow = null; }

  mainWindow = new BrowserWindow({
    width:           520,
    height:          480,
    resizable:       false,
    frame:           true,
    alwaysOnTop:     false,
    autoHideMenuBar: true,
    backgroundColor: '#0a0a14',
    title:           'Hotel Media Player — راه‌اندازی',
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

  tray = new Tray(iconPath);
  tray.setToolTip('Hotel Media Player');
  updateTrayMenu();
}

function updateTrayMenu() {
  if (!tray) return;
  const menu = Menu.buildFromTemplate([
    { label: 'Hotel Media Player', enabled: false },
    { type: 'separator' },
    { label: 'تنظیمات / Setup',  click: () => createSetupWindow() },
    { label: 'بارگذاری مجدد',    click: () => mainWindow?.reload() },
    { label: 'Dev Tools',         click: () => mainWindow?.webContents.openDevTools(), visible: process.argv.includes('--dev') },
    { type: 'separator' },
    { label: 'خروج',              click: () => app.quit() },
  ]);
  tray.setContextMenu(menu);
  tray.on('double-click', () => mainWindow?.show());
}

// ── IPC handlers ─────────────────────────────────────────────────
ipcMain.handle('get-config', () => config);

ipcMain.handle('save-and-launch', async (_e, { serverUrl, screenCode }) => {
  serverUrl = serverUrl.trim().replace(/\/$/, '');
  if (!serverUrl) return { ok: false, error: 'آدرس سرور خالی است' };

  const playerUrl = screenCode
    ? `${serverUrl}/player/${encodeURIComponent(screenCode)}`
    : `${serverUrl}/player/`;

  return new Promise((resolve) => {
    pingServer(playerUrl, (ok) => {
      if (ok) {
        config = { serverUrl, screenCode };
        saveConfig(config);
        createPlayerWindow(playerUrl);
        resolve({ ok: true });
      } else {
        resolve({ ok: false, error: 'سرور در دسترس نیست — آدرس را بررسی کنید' });
      }
    });
  });
});

ipcMain.handle('open-setup', () => createSetupWindow());
ipcMain.handle('ping', (_e, url) => new Promise((resolve) => pingServer(url, resolve)));

// ── App lifecycle ────────────────────────────────────────────────
app.whenReady().then(() => {
  createTray();

  if (config.serverUrl) {
    const url = config.screenCode
      ? `${config.serverUrl}/player/${encodeURIComponent(config.screenCode)}`
      : `${config.serverUrl}/player/`;
    createPlayerWindow(url);
  } else {
    createSetupWindow();
  }

  // کلید F5 برای reload و F12 برای DevTools
  app.on('web-contents-created', (_e, wc) => {
    wc.on('before-input-event', (_e2, input) => {
      if (input.key === 'F5')  wc.reload();
      if (input.key === 'F12') wc.openDevTools();
      // Escape در kiosk mode — برای خروج اضطراری (5 بار پشت هم)
    });
  });
});

app.on('window-all-closed', () => {
  // tray هست — برنامه باز می‌مونه
});

app.on('activate', () => {
  if (!mainWindow) {
    config.serverUrl ? createPlayerWindow(`${config.serverUrl}/player/`) : createSetupWindow();
  }
});
