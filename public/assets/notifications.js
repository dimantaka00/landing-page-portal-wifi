'use strict';

(() => {
  const center = document.querySelector('[data-notification-center]');
  if (!center) return;
  const stack = document.querySelector('[data-update-stack]');
  const status = center.querySelector('[data-notification-status]');
  const enable = center.querySelector('[data-push-enable]');
  const disable = center.querySelector('[data-push-disable]');
  const supported = window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  const cursorKey = 'portal-notification-cursor-v1';
  const tagsKey = 'portal-notification-seen-v1';
  const tokenKey = 'portal-push-token-v1';
  const read = key => { try { return localStorage.getItem(key); } catch { return null; } };
  const write = (key, value) => { try { localStorage.setItem(key, value); } catch {} };
  let cursor = read(cursorKey);
  if (cursor !== null && !/^\d+$/.test(cursor)) cursor = null;
  let tags;
  try { tags = JSON.parse(read(tagsKey) || '[]'); } catch { tags = []; }
  if (!Array.isArray(tags)) tags = [];
  let queue = [];
  let csrf = '';
  let pushConfig = null;
  let registration = null;
  let subscription = null;
  let busy = false;
  let restoring = false;
  let disconnected = false;
  let token = read(tokenKey);

  function updateStatus() {
    enable.hidden = true;
    disable.hidden = true;
    if (!pushConfig) return;
    if (!supported) {
      status.textContent = 'Pop-up pembaruan aktif saat portal terbuka. Notifikasi perangkat memerlukan browser utama yang mendukung dan koneksi HTTPS.';
    } else if (!pushConfig.configured) {
      status.textContent = 'Pop-up pembaruan aktif saat portal terbuka. Notifikasi perangkat belum diaktifkan oleh pengelola.';
    } else if (Notification.permission === 'denied') {
      status.textContent = 'Notifikasi perangkat diblokir di pengaturan browser. Pop-up dalam portal tetap aktif.';
    } else if (subscription) {
      status.textContent = 'Notifikasi perangkat aktif. Pembaruan dapat diterima setelah portal ditutup, sesuai dukungan perangkat.';
      disable.hidden = false;
    } else {
      status.textContent = 'Pop-up pembaruan aktif. Aktifkan notifikasi perangkat untuk menerima kabar saat portal ditutup.';
      enable.hidden = false;
    }
  }

  function safeUrl(value) {
    try {
      const url = new URL(value, location.origin);
      return url.origin === location.origin && /^\/pengumuman\/\d+$/.test(url.pathname) ? url.href : null;
    } catch { return null; }
  }

  function render() {
    stack.replaceChildren();
    stack.hidden = queue.length === 0;
    if (!queue.length) return;
    const item = queue[0];
    const card = document.createElement('section');
    card.className = 'portal-update-card';
    card.setAttribute('role', 'status');
    const kicker = document.createElement('span');
    kicker.className = 'notification-kicker';
    kicker.textContent = 'PEMBARUAN · ' + item.category;
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'notification-close';
    close.textContent = 'Tutup';
    close.setAttribute('aria-label', 'Tutup pop-up pengumuman');
    close.addEventListener('click', () => { queue.shift(); render(); });
    const title = document.createElement('strong');
    title.textContent = item.title;
    const body = document.createElement('p');
    body.textContent = item.body;
    const link = document.createElement('a');
    link.className = 'button button-primary';
    link.textContent = 'Baca pengumuman';
    link.href = safeUrl(item.url);
    card.append(kicker, close, title, body);
    if (item.published_at) {
      const date = new Date(item.published_at);
      if (!Number.isNaN(date.valueOf())) {
        const time = document.createElement('time');
        time.dateTime = item.published_at;
        time.textContent = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(date) + ' WIB';
        card.append(time);
      }
    }
    card.append(link);
    if (queue.length > 1) {
      const remaining = document.createElement('small');
      remaining.textContent = (queue.length - 1) + ' pembaruan lain menunggu. Tutup untuk melihat berikutnya.';
      card.append(remaining);
    }
    stack.append(card);
  }

  function receive(item) {
    if (!item || !safeUrl(item.url) || typeof item.tag !== 'string') return;
    let sharedTags;
    try { sharedTags = JSON.parse(read(tagsKey) || '[]'); } catch { sharedTags = []; }
    if (tags.includes(item.tag) || (Array.isArray(sharedTags) && sharedTags.includes(item.tag))) return;
    tags = [...new Set([...tags, ...(Array.isArray(sharedTags) ? sharedTags : []), item.tag])].slice(-200);
    write(tagsKey, JSON.stringify(tags));
    queue.unshift(item);
    queue = queue.slice(0, 50);
    render();
  }

  async function changeSubscription(method, data) {
    const response = await fetch(center.dataset.subscribeUrl, {
      method, credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(data),
    });
    if (!response.ok) throw new Error('Pendaftaran notifikasi belum berhasil (' + response.status + '). Coba lagi.');
  }

  async function worker() {
    if (!registration) registration = await navigator.serviceWorker.register(center.dataset.workerUrl, { scope: '/' });
    await navigator.serviceWorker.ready;
    return registration;
  }

  async function restoreSubscription() {
    if (restoring || !supported || !pushConfig?.configured || Notification.permission !== 'granted') return;
    restoring = true;
    try {
      const current = await worker();
      const existing = await current.pushManager.getSubscription();
      if (existing && token) {
        await changeSubscription('POST', { ...existing.toJSON(), token });
        subscription = existing;
      }
    } catch {
      status.textContent = 'Pendaftaran notifikasi belum terhubung. Coba aktifkan kembali; pop-up portal tetap aktif.';
    } finally {
      updateStatus();
    }
  }

  async function poll() {
    if (busy || document.hidden || navigator.onLine === false) return;
    busy = true;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 8000);
    try {
      const sharedCursor = read(cursorKey);
      if (sharedCursor !== null && /^\d+$/.test(sharedCursor)) cursor = String(Math.max(Number(cursor || 0), Number(sharedCursor)));
      const url = new URL(center.dataset.feedUrl);
      if (cursor !== null) url.searchParams.set('after', cursor);
      const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal, cache: 'no-store' });
      if (!response.ok) throw new Error('feed');
      const data = await response.json();
      csrf = data.csrf_token;
      const first = pushConfig === null;
      const configurationChanged = pushConfig && (pushConfig.configured !== data.push.configured || pushConfig.public_key !== data.push.public_key);
      pushConfig = data.push;
      if (data.latest && center.dataset.initialPopup === 'true') receive(data.latest);
      data.events.forEach(receive);
      cursor = String(data.cursor);
      write(cursorKey, cursor);
      if (disconnected) { disconnected = false; updateStatus(); }
      if (first || configurationChanged) {
        updateStatus();
        void restoreSubscription();
      }
      if (data.has_more) setTimeout(poll, 0);
    } catch {
      disconnected = true;
      status.textContent = 'Koneksi pembaruan terputus. Portal akan mencoba kembali secara otomatis.';
    } finally {
      clearTimeout(timeout);
      busy = false;
    }
  }

  enable.addEventListener('click', async () => {
    if (!supported || !pushConfig?.configured) return;
    enable.disabled = true;
    try {
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') { updateStatus(); return; }
      const current = await worker();
      if (!token) {
        token = [...crypto.getRandomValues(new Uint8Array(32))].map(byte => byte.toString(16).padStart(2, '0')).join('');
        write(tokenKey, token);
      }
      const key = pushConfig.public_key.replace(/-/g, '+').replace(/_/g, '/');
      const bytes = Uint8Array.from(atob(key + '='.repeat((4 - key.length % 4) % 4)), char => char.charCodeAt(0));
      subscription = await current.pushManager.getSubscription() || await current.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bytes });
      await changeSubscription('POST', { ...subscription.toJSON(), token });
      updateStatus();
    } catch (error) {
      subscription = null;
      updateStatus();
      status.textContent = error.message || 'Notifikasi belum dapat diaktifkan. Gunakan browser utama atau aplikasi portal di Layar Utama.';
    } finally { enable.disabled = false; }
  });

  disable.addEventListener('click', async () => {
    disable.disabled = true;
    try {
      if (subscription) await subscription.unsubscribe();
      subscription = null;
      await changeSubscription('DELETE', { token });
      updateStatus();
    } catch {
      updateStatus();
      status.textContent = 'Notifikasi browser dihentikan. Hubungkan kembali untuk menyelesaikan penghapusan langganan server.';
    } finally { disable.disabled = false; }
  });

  navigator.serviceWorker?.addEventListener('message', event => {
    if (event.data?.type === 'PORTAL_UPDATE') receive(event.data.payload);
  });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) void poll(); });
  window.addEventListener('focus', () => { updateStatus(); void poll(); });
  window.addEventListener('online', () => { void poll(); });
  setInterval(() => { void poll(); }, Math.max(5, Number(center.dataset.pollSeconds) || 5) * 1000);
  void poll();
})();