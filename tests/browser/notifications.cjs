const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('@playwright/test');

(async () => {
  const root = path.resolve(__dirname, '../..');
  const base = process.env.PORTAL_TEST_URL || 'http://127.0.0.1:8011';
  const credentials = fs.readFileSync(path.join(root, 'storage/app/private/initial-admin.txt'), 'utf8');
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const desktop = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
  const mobile = await browser.newContext({ viewport: { width: 393, height: 852 }, reducedMotion: 'reduce' });
  const admin = await browser.newContext();
  const errors = [];
  const page = await desktop.newPage();
  const phone = await mobile.newPage();
  const editor = await admin.newPage();
  let editUrl;
  let title;
  for (const current of [page, phone, editor]) {
    current.on('pageerror', error => errors.push(error.message));
    current.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  }
  const open = async current => {
    const feed = current.waitForResponse(response => response.url().includes('/notifikasi/feed') && response.ok());
    await current.goto(base);
    await feed;
  };
  const save = async () => Promise.all([editor.waitForURL('**/admin/announcements'), editor.getByRole('button', { name: 'Simpan pengumuman', exact: true }).click()]);
  try {
    await Promise.all([open(page), open(phone)]);
    assert.equal(await page.locator('[data-update-stack]').isVisible(), false);
    await editor.goto(base + '/admin/login');
    await editor.getByLabel('Username admin').fill(credentials.match(/^Username: (.+)$/m)[1].trim());
    await editor.getByLabel('Kata sandi', { exact: true }).fill(credentials.match(/^Kata sandi: (.+)$/m)[1].trim());
    await Promise.all([editor.waitForURL('**/admin/announcements'), editor.getByRole('button', { name: 'Masuk ke dashboard' }).click()]);
    await editor.getByRole('link', { name: 'Tambah pengumuman', exact: true }).first().click();
    title = '[Uji notifikasi] Problem jaringan ' + Date.now();
    await editor.getByLabel('Judul pengumuman').fill(title);
    await editor.getByLabel('Isi pengumuman', { exact: false }).fill('<img src=x onerror=alert(1)> Perbaikan sedang berlangsung.');
    await editor.getByRole('combobox', { name: 'Kategori', exact: false }).selectOption('Maintenance');
    assert.equal(await editor.getByLabel('Kirim notifikasi pembaruan').isChecked(), true);
    await save();
    await editor.getByRole('link', { name: title, exact: true }).click();
    editUrl = editor.url();
    await page.waitForTimeout(5500);
    assert.equal(await page.locator('[data-update-stack]').isVisible(), false, 'Draft must not appear');
    await editor.getByLabel('Status', { exact: false }).selectOption('published');
    await save();
    for (const current of [page, phone]) {
      const popup = current.locator('[data-update-stack]');
      await popup.getByText(title, { exact: true }).waitFor({ timeout: 12000 });
      assert.equal(await popup.locator('img').count(), 0, 'Announcement HTML must stay text');
      assert.equal(await current.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      await current.screenshot({ path: path.join(root, '.cache/portal-notification-' + (current === page ? 'desktop' : 'mobile') + '.png'), fullPage: true });
      await popup.getByRole('button', { name: 'Tutup pop-up pengumuman' }).click();
    }
    const captive = await browser.newContext({ viewport: { width: 393, height: 852 }, reducedMotion: 'reduce' });
    const captivePage = await captive.newPage();
    const firstFeed = captivePage.waitForResponse(response => response.url().includes('/notifikasi/feed') && response.ok());
    await captivePage.goto(base + '/portal');
    await firstFeed;
    await captivePage.locator('[data-update-stack]').getByText(title, { exact: true }).waitFor();
    await captive.close();
    const feed = page.waitForResponse(response => response.url().includes('/notifikasi/feed') && response.ok());
    await page.reload();
    await feed;
    await page.waitForTimeout(500);
    assert.equal(await page.locator('[data-update-stack]').isVisible(), false, 'Reload must not repeat dismissed popup');
    await editor.goto(editUrl);
    await editor.getByLabel('Isi pengumuman', { exact: false }).fill('Problem jaringan sudah ditangani.');
    await save();
    await page.locator('[data-update-stack]').getByText('Problem jaringan sudah ditangani.', { exact: true }).waitFor({ timeout: 12000 });
    await page.locator('[data-update-stack]').getByRole('button', { name: 'Tutup pop-up pengumuman' }).click();
    await editor.goto(editUrl);
    await editor.getByLabel('Kirim notifikasi pembaruan').uncheck();
    await editor.getByLabel('Isi pengumuman', { exact: false }).fill('Perubahan tanpa notifikasi.');
    await save();
    await page.waitForTimeout(5500);
    assert.equal(await page.locator('[data-update-stack]').isVisible(), false, 'Opt-out must not generate popup');

    const consent = await browser.newContext({ permissions: ['notifications'] });
    await consent.addInitScript(() => {
      let subscribed = false;
      const fake = {
        toJSON: () => ({ endpoint: 'https://fcm.googleapis.com/fcm/send/browser-test', keys: { p256dh: 'test', auth: 'test' } }),
        unsubscribe: async () => { subscribed = false; return true; },
      };
      PushManager.prototype.getSubscription = async () => subscribed ? fake : null;
      PushManager.prototype.subscribe = async () => { subscribed = true; return fake; };
    });
    const consentPage = await consent.newPage();
    consentPage.on('pageerror', error => errors.push(error.message));
    const publicKey = JSON.parse(fs.readFileSync(path.join(root, 'storage/app/private/web-push-keys.json'), 'utf8')).publicKey;
    let pushAvailable = false;
    await consentPage.route('**/notifikasi/feed*', async route => {
      const response = await route.fetch();
      const data = await response.json();
      await route.fulfill({ response, json: { ...data, push: { configured: pushAvailable, public_key: pushAvailable ? publicKey : null } } });
    });
    let subscribedRequests = 0;
    let unsubscribedRequests = 0;
    await consentPage.route('**/notifikasi/langganan', async route => {
      const request = route.request();
      assert.ok(request.headers()['x-csrf-token']);
      assert.equal(request.postDataJSON().token.length, 64);
      if (request.method() === 'POST') subscribedRequests++; else unsubscribedRequests++;
      await route.fulfill({ status: 201, json: { subscribed: request.method() === 'POST' } });
    });
    await open(consentPage);
    assert.equal(await consentPage.getByRole('button', { name: 'Aktifkan notifikasi perangkat', exact: true }).isVisible(), false);
    pushAvailable = true;
    await consentPage.getByRole('button', { name: 'Aktifkan notifikasi perangkat', exact: true }).click();
    await consentPage.getByRole('button', { name: 'Nonaktifkan notifikasi perangkat', exact: true }).waitFor();
    assert.equal(subscribedRequests, 1);
    assert.equal(await consentPage.evaluate(async () => Boolean((await navigator.serviceWorker.ready).active)), true);
    await consentPage.getByRole('button', { name: 'Nonaktifkan notifikasi perangkat', exact: true }).click();
    await consentPage.getByRole('button', { name: 'Aktifkan notifikasi perangkat', exact: true }).waitFor();
    assert.equal(unsubscribedRequests, 1);
    await consent.close();

    await editor.goto(editUrl);
    const csrf = await editor.locator('input[name="_token"]').first().inputValue();
    const deleted = await admin.request.post(editUrl.replace(/\/edit$/, ''), { form: { _token: csrf, _method: 'DELETE' } });
    assert.ok(deleted.ok());
    editUrl = undefined;
    assert.deepEqual(errors, []);
    const result = { status: 'passed', checks: ['draft stays private', 'live update reaches two device contexts', 'HTML is escaped', 'responsive popup', 'dismissal persists after reload', 'published edits notify', 'admin can disable notification', 'consent flow and service worker registration', 'unsubscribe'], realProviderDelivery: 'requires deployed HTTPS and a real subscribed device' };
    fs.writeFileSync(path.join(root, '.cache/portal-notification-validation.json'), JSON.stringify(result, null, 2));
    console.log(JSON.stringify(result));
  } finally {
    if (editUrl) {
      try {
        await editor.goto(editUrl);
        const token = await editor.locator('input[name="_token"]').first().inputValue();
        await admin.request.post(editUrl.replace(/\/edit$/, ''), { form: { _token: token, _method: 'DELETE' } });
      } catch { console.error('Cleanup required for notification test announcement.'); }
    }
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });