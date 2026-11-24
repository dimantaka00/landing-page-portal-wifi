const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../public/service-worker.js'), 'utf8');

function worker(fetcher, windows = []) {
  const handlers = {};
  const shown = [];
  const opened = [];
  const messages = [];
  const self = {
    location: { origin: 'https://portal.example.test' },
    addEventListener: (name, handler) => { handlers[name] = handler; },
    skipWaiting: async () => {},
    registration: { showNotification: async (title, options) => { shown.push({ title, ...options }); } },
    clients: { claim: async () => {}, matchAll: async () => windows, openWindow: async url => { opened.push(url); } },
  };
  vm.runInNewContext(source, { self, URL, AbortController, setTimeout, clearTimeout, fetch: fetcher });
  return { handlers, shown, opened, messages };
}

async function dispatch(handler, data) {
  let promise;
  handler({ ...data, waitUntil: value => { promise = value; } });
  await promise;
}

(async () => {
  const messages = [];
  const runtime = worker(async url => {
    assert.equal(url, '/notifikasi/item/42');
    return { ok: true, json: async () => ({ id: 42, announcement_id: 7, title: 'Maintenance', body: 'Perbaikan jaringan', url: '/pengumuman/7', tag: 'announcement-7-1' }) };
  }, [{ postMessage: data => messages.push(data) }]);
  await dispatch(runtime.handlers.push, { data: { json: () => ({ event_id: 42 }) } });
  assert.equal(runtime.shown[0].title, 'Maintenance');
  assert.equal(runtime.shown[0].data.url, '/pengumuman/7');
  assert.equal(messages[0].type, 'PORTAL_UPDATE');

  const withdrawn = worker(async () => ({ ok: false, status: 404 }));
  await dispatch(withdrawn.handlers.push, { data: { json: () => ({ event_id: 42 }) } });
  assert.equal(withdrawn.shown[0].title, 'Pembaruan Kementerian Sosial RI');
  assert.equal(withdrawn.shown[0].data.url, '/');

  const offline = worker(async () => { throw new Error('offline'); });
  await dispatch(offline.handlers.push, { data: { json: () => ({ event_id: 42 }) } });
  assert.equal(offline.shown[0].data.url, '/');
  await dispatch(offline.handlers.notificationclick, { notification: { close() {}, data: { url: 'https://evil.example/phishing' } } });
  assert.deepEqual(offline.opened, ['/']);

  const navigated = [];
  const existing = worker(async () => {}, [{
    url: 'https://portal.example.test/portal', navigate: async url => { navigated.push(url); }, focus: async () => { navigated.push('focused'); },
  }]);
  await dispatch(existing.handlers.notificationclick, { notification: { close() {}, data: { url: '/pengumuman/7' } } });
  assert.deepEqual(navigated, ['/pengumuman/7', 'focused']);
  const adminWindow = worker(async () => {}, [{
    url: 'https://portal.example.test/admin/announcements/1/edit', navigate: async () => { throw new Error('Do not interrupt admin editing'); }, focus: async () => {},
  }]);
  await dispatch(adminWindow.handlers.notificationclick, { notification: { close() {}, data: { url: '/pengumuman/7' } } });
  assert.deepEqual(adminWindow.opened, ['/pengumuman/7']);
  console.log(JSON.stringify({ status: 'passed', checks: ['background push resolves current public content', 'open clients receive update', 'withdrawn and offline content use generic notification', 'notification click stays on same origin', 'existing portal window is reused'] }));
})().catch(error => { console.error(error); process.exitCode = 1; });