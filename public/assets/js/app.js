/**
 * SignageCMS - Admin Panel JavaScript
 * Handles: Auth token, API helpers, real-time WS, toasts
 */

// ─── Token management ───────────────────────────────────────
const Auth = {
  getToken() { return localStorage.getItem('signage_token') || ''; },
  setToken(t) { localStorage.setItem('signage_token', t); },
  removeToken() { localStorage.removeItem('signage_token'); },

  async login(email, password) {
    const r = await API.post('/auth/login', { email, password });
    if (r.success) this.setToken(r.data.token);
    return r;
  },

  async logout() {
    await API.post('/auth/logout');
    this.removeToken();
    location.href = '/login';
  }
};

// ─── API helper ─────────────────────────────────────────────
const API = {
  base: '/api/v1',

  headers(extra = {}) {
    return {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${Auth.getToken()}`,
      'X-Requested-With': 'XMLHttpRequest',
      ...extra,
    };
  },

  async request(method, path, body = null, isForm = false) {
    const opts = { method, headers: this.headers(isForm ? { 'Content-Type': undefined } : {}) };
    if (isForm) delete opts.headers['Content-Type'];
    if (body) opts.body = isForm ? body : JSON.stringify(body);

    try {
      const r   = await fetch(this.base + path, opts);
      const data = await r.json();
      if (!r.ok && r.status === 401) {
        Auth.removeToken();
        location.href = '/login';
      }
      return data;
    } catch (e) {
      console.error('API error:', e);
      return { success: false, message: 'خطای شبکه', data: null };
    }
  },

  get(path, params = {})       { return this.request('GET',    path + (Object.keys(params).length ? '?' + new URLSearchParams(params) : '')); },
  post(path, body)             { return this.request('POST',   path, body); },
  put(path, body)              { return this.request('PUT',    path, body); },
  patch(path, body)            { return this.request('PATCH',  path, body); },
  delete(path)                 { return this.request('DELETE', path); },
  upload(path, formData)       { return this.request('POST',   path, formData, true); },
};

// ─── WebSocket Client ────────────────────────────────────────
const WS = {
  socket: null,
  reconnTimer: null,
  handlers: {},
  tenantId: document.body.dataset.tenantId || '1',

  connect() {
    const url = `ws://${location.hostname}:${window.WS_PORT || 8080}`;
    try {
      this.socket = new WebSocket(url);
      this.socket.onopen    = () => this._onOpen();
      this.socket.onmessage = (e) => this._onMessage(e);
      this.socket.onclose   = () => this._onClose();
      this.socket.onerror   = () => this.socket.close();
    } catch(e) {}
  },

  _onOpen() {
    this._setStatus(true);
    this.subscribe(`admin_${this.tenantId}`);
    clearTimeout(this.reconnTimer);
  },

  _onMessage(e) {
    try {
      const msg = JSON.parse(e.data);
      const handlers = this.handlers[msg.type] || this.handlers['*'] || [];
      handlers.forEach(fn => fn(msg));
      this._defaultHandler(msg);
    } catch(ex) {}
  },

  _onClose() {
    this._setStatus(false);
    this.reconnTimer = setTimeout(() => this.connect(), 5000);
  },

  _setStatus(online) {
    const dot = document.getElementById('ws-indicator');
    const lbl = document.getElementById('ws-status');
    if (dot) dot.style.background = online ? '#4ade80' : '#f87171';
    if (lbl) lbl.textContent      = online ? 'زنده'    : 'قطع';
  },

  _defaultHandler(msg) {
    const toastTypes = {
      screen_online:  () => Toast.success(`📺 ${msg.data?.name || 'صفحه'} آنلاین شد`),
      screen_offline: () => Toast.error(`📴 ${msg.data?.name || 'صفحه'} آفلاین شد`),
      notification:   () => Toast.warn(msg.data?.title || 'اعلان جدید'),
      emergency:      () => Toast.error(`🚨 پخش اضطراری: ${msg.data}`),
    };
    toastTypes[msg.type]?.();
  },

  subscribe(channel) {
    this.send({ type: 'subscribe', channel });
  },

  send(data) {
    if (this.socket?.readyState === WebSocket.OPEN) {
      this.socket.send(JSON.stringify(data));
    }
  },

  on(type, fn) {
    this.handlers[type] = this.handlers[type] || [];
    this.handlers[type].push(fn);
    return this;
  },
};

// ─── Toast notifications ─────────────────────────────────────
const Toast = {
  _show(type, msg, duration = 4000) {
    const icons = { success: 'check-circle', error: 'circle-xmark', warn: 'triangle-exclamation', info: 'circle-info' };
    const t = document.createElement('div');
    t.className = `toast toast-${type === 'warn' ? 'error' : type}`;
    t.style.cssText = `opacity:0;transition:opacity 0.3s ease,transform 0.3s ease;transform:translateX(-20px);`;
    t.innerHTML = `<i class="fas fa-${icons[type] || 'circle-info'}"></i> ${msg}`;
    document.body.appendChild(t);
    requestAnimationFrame(() => { t.style.opacity = '1'; t.style.transform = 'translateX(0)'; });
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, duration);
  },
  success(msg) { this._show('success', msg); },
  error(msg)   { this._show('error', msg); },
  warn(msg)    { this._show('warn', msg); },
  info(msg)    { this._show('info', msg); },
};

// Expose globals
window.showToast = (type, msg) => Toast[type]?.(msg) ?? Toast.info(msg);
window.API  = API;
window.Auth = Auth;
window.WS   = WS;
window.Toast = Toast;

// ─── Init ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Auto-connect WS
  WS.connect();

  // CSRF for AJAX forms
  document.querySelectorAll('form[data-ajax]').forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(form));
      const r = await API.post(form.action.replace(location.origin, ''), data);
      Toast[r.success ? 'success' : 'error'](r.message);
      if (r.success && form.dataset.redirect) location.href = form.dataset.redirect;
    });
  });

  // Auto-dismiss flash messages
  document.querySelectorAll('[id^="flash-msg"]').forEach(el => {
    setTimeout(() => { el.style.opacity='0'; setTimeout(()=>el.remove(), 300); }, 4000);
  });

  // Confirm delete buttons
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      if (!confirm(btn.dataset.confirm)) e.preventDefault();
    });
  });

  // Real-time screen status refresh on dashboard
  if (location.pathname.includes('/dashboard')) {
    WS.on('screen_online',  () => setTimeout(() => location.reload(), 500));
    WS.on('screen_offline', () => setTimeout(() => location.reload(), 500));
  }
});
