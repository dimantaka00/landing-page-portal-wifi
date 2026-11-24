'use strict';

self.addEventListener('install', event => { event.waitUntil(self.skipWaiting()); });
self.addEventListener('activate', event => { event.waitUntil(self.clients.claim()); });

function safePath(value) {
  try {
    const url = new URL(value, self.location.origin);
    return url.origin === self.location.origin && /^\/pengumuman\/\d+$/.test(url.pathname) ? url.pathname : '/';
  } catch { return '/'; }
}

self.addEventListener('push', event => {
  event.waitUntil((async () => {
    let payload = { title: 'Pembaruan Kementerian Sosial RI', body: 'Ada pembaruan informasi kantor. Buka portal untuk melihat informasi yang masih berlaku.', url: '/', tag: 'portal-update' };
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 5000);
    try {
      const message = event.data?.json();
      if (Number.isSafeInteger(message?.event_id) && message.event_id > 0) {
        const response = await fetch('/notifikasi/item/' + message.event_id, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
        if (response.ok) {
          const current = await response.json();
          payload = { ...current, url: safePath(current.url) };
        }
      }
    } catch {} finally { clearTimeout(timeout); }
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    if (payload.announcement_id) clients.forEach(client => { client.postMessage({ type: 'PORTAL_UPDATE', payload }); });
    await self.registration.showNotification(payload.title, {
      body: payload.body, icon: '/assets/logo-kemensos.png',
      tag: payload.tag, data: { url: safePath(payload.url) },
    });
  })());
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const path = safePath(event.notification.data?.url);
  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of windows) {
      if (new URL(client.url).origin === self.location.origin && !new URL(client.url).pathname.startsWith('/admin') && 'navigate' in client) {
        await client.navigate(path);
        await client.focus();
        return;
      }
    }
    await self.clients.openWindow(path);
  })());
});