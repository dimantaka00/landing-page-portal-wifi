const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('@playwright/test');

(async () => {
  const root = path.resolve(__dirname, '../..');
  const base = 'http://127.0.0.1:8011';
  const credentials = fs.readFileSync(path.join(root, 'storage/app/private/initial-admin.txt'), 'utf8');
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const managerContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const editorContext = await browser.newContext();
  const page = await managerContext.newPage();
  const editor = await editorContext.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  editor.on('pageerror', error => errors.push(error.message));
  const suffix = Date.now().toString();
  const username = 'browser-user-' + suffix;
  const email = username + '@example.test';
  const password = 'Browser-Test-Password-2026!';
  let editUrl;
  const login = async (current, user, secret) => {
    await current.goto(base + '/admin/login');
    await current.getByLabel('Username admin').fill(user);
    await current.getByLabel('Kata sandi', { exact: true }).fill(secret);
    await Promise.all([current.waitForURL('**/admin/announcements'), current.getByRole('button', { name: 'Masuk ke dashboard' }).click()]);
  };
  const save = async () => Promise.all([page.waitForURL('**/admin/users'), page.getByRole('button', { name: 'Simpan pengguna', exact: true }).click()]);
  try {
    await login(page, credentials.match(/^(?:Username|Email): (.+)$/m)[1].trim(), credentials.match(/^Kata sandi: (.+)$/m)[1].trim());
    await page.getByRole('link', { name: 'Pengaturan pengguna', exact: true }).click();
    await page.getByRole('heading', { name: 'Pengaturan pengguna', exact: true }).waitFor();
    await page.getByRole('link', { name: 'Tambah pengguna', exact: true }).click();
    await page.getByLabel('Nama lengkap', { exact: false }).fill('[Uji pengguna] ' + suffix);
    await page.getByLabel('Username', { exact: false }).fill(username);
    await page.getByLabel('Email', { exact: false }).fill(email);
    await page.getByLabel('Password *', { exact: true }).fill(password);
    await page.getByLabel('Konfirmasi password', { exact: true }).fill(password);
    for (const width of [320, 393, 768, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    }
    await save();
    await page.getByRole('link', { name: 'Edit pengguna [Uji pengguna] ' + suffix, exact: true }).click();
    editUrl = page.url();
    await login(editor, username, password);
    assert.equal(await editor.getByRole('link', { name: 'Pengaturan pengguna', exact: true }).count(), 0);
    assert.equal((await editorContext.request.get(base + '/admin/users')).status(), 403);

    await page.getByLabel('Akun aktif', { exact: false }).uncheck();
    await save();
    await editor.goto(base + '/admin/announcements');
    await editor.waitForURL('**/admin/login');
    await page.goto(editUrl);
    await page.getByLabel('Akun aktif', { exact: false }).check();
    await page.getByLabel('Peran', { exact: false }).selectOption('admin');
    await page.getByLabel('Password baru (opsional)', { exact: true }).fill('Updated-Test-Password-2026!');
    await page.getByLabel('Konfirmasi password', { exact: true }).fill('Updated-Test-Password-2026!');
    await save();
    await login(editor, username, 'Updated-Test-Password-2026!');
    await editor.getByRole('link', { name: 'Pengaturan pengguna', exact: true }).click();
    await editor.getByRole('heading', { name: 'Pengaturan pengguna', exact: true }).waitFor();
    for (const width of [320, 393, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      if (width !== 320) await page.screenshot({ path: path.join(root, '.cache/users-settings-' + width + '.png'), fullPage: true });
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({ status: 'passed', checks: ['manager user settings', 'create and login as editor', 'editor cannot manage users', 'deactivation revokes existing browser session', 'password reset and role promotion', 'responsive create and list pages'] }));
  } finally {
    await browser.close();
    if (editUrl) {
      const id = Number(editUrl.match(/users\/(\d+)\/edit/)[1]);
      const php = path.join(root, '.runtime/php/php.exe');
      const code = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); App\\Models\\User::whereKey(' + id + ')->where("username","' + username + '")->where("email","' + email + '")->delete();';
      execFileSync(php, ['-r', code], { cwd: root, stdio: ['ignore', 'pipe', 'pipe'] });
    }
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });