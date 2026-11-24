const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('@playwright/test');
const root = path.resolve(__dirname, '../..');
const project = process.env.PORTAL_DOCKER_PROJECT || 'portal-wifi-docker-test';
if (!/^portal-wifi-docker-test(?:-[a-z0-9-]+)?$/.test(project)) throw new Error('Use an isolated Docker test project.');
const port = process.env.DOCKER_PORT || '18011';
const base = 'http://127.0.0.1:' + port;
const env = { ...process.env, DOCKER_PORT: port, DOCKER_APP_URL: base, DOCKER_BIND_ADDRESS: '127.0.0.1', DOCKER_APP_KEY: '' };
const docker = args => execFileSync('docker', args, { cwd: root, env, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], timeout: 180000 });
const compose = args => docker(['compose', '-p', project, ...args]);
const php = code => compose(['exec', '-T', 'app', 'php', '-r', 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); ' + code]);
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
  compose(['up', '-d', '--no-build', '--wait']);
  docker(['run', '--rm', '--network', 'none', '--entrypoint', 'sh', 'landing-page-portal-wifi:local', '-c',
    'test ! -e /var/www/html/.env && test ! -e /var/www/html/.runtime && test ! -e /var/www/html/.git && test ! -e /var/www/html/database/database.sqlite && test ! -e /var/www/html/storage/app/private/initial-admin.txt']);
  compose(['exec', '-T', '--user', 'www-data', 'app', 'php', 'artisan', 'portal:admin', 'docker-smoke@example.test', '--generate', '--no-interaction']);
  const credentials = compose(['exec', '-T', '--user', 'www-data', 'app', 'cat', 'storage/app/private/initial-admin.txt']);
  const email = credentials.match(/^Email: (.+)$/m)[1].trim();
  const password = credentials.match(/^Kata sandi: (.+)$/m)[1].trim();
  const fingerprint = () => compose(['exec', '-T', 'app', 'sha256sum', 'storage/app/private/docker-app-key', 'storage/app/private/web-push-keys.json']).trim();
  const keysBefore = fingerprint();
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const login = async () => {
      await page.goto(base + '/admin/login');
      if (!page.url().endsWith('/admin/login')) return;
      await page.getByLabel('Username admin').fill(email);
      await page.getByLabel('Kata sandi', { exact: true }).fill(password);
      await Promise.all([page.waitForURL('**/admin/announcements'), page.getByRole('button', { name: 'Masuk ke dashboard' }).click()]);
    };
    assert.equal((await context.request.get(base + '/up')).status(), 200);
    await login();
    await page.getByRole('link', { name: 'Tambah pengumuman', exact: true }).first().click();
    const title = '[Docker smoke] Maintenance ' + Date.now();
    await page.getByLabel('Judul pengumuman').fill(title);
    await page.getByRole('combobox', { name: 'Kategori', exact: false }).selectOption('Maintenance');
    await page.getByLabel('Isi pengumuman', { exact: false }).fill('Pemeriksaan Docker, volume, dan scheduler.');
    await page.getByLabel('Status', { exact: false }).selectOption('published');
    await page.getByLabel('Kirim notifikasi pembaruan').uncheck();
    await page.locator('#photos').setInputFiles(path.join(root, 'public/assets/office.jpg'));
    await Promise.all([page.waitForURL('**/admin/announcements'), page.getByRole('button', { name: 'Simpan pengumuman' }).click()]);
    await page.getByRole('link', { name: title, exact: true }).click();
    const editUrl = page.url();
    const id = Number(editUrl.match(/announcements\/(\d+)\/edit/)[1]);
    const photoUrl = await page.locator('.existing-photo img').first().getAttribute('src');
    assert.equal((await context.request.get(photoUrl)).status(), 200);
    compose(['up', '-d', '--no-build', '--force-recreate', '--wait']);
    assert.equal(fingerprint(), keysBefore, 'Container recreation must retain keys');
    await page.goto(editUrl);
    await page.getByLabel('Judul pengumuman').waitFor();
    assert.equal(await page.getByLabel('Judul pengumuman').inputValue(), title);
    assert.equal((await context.request.get(photoUrl)).status(), 200);
    assert.equal(php('echo hash("sha256", config("app.key"));').trim().length, 64, 'Artisan exec loads persisted key');

    php('App\\Models\\Announcement::whereKey(' + id + ')->update(["delete_at"=>now()->subSecond()]);');
    const deadline = Date.now() + 90000;
    let deleted = false;
    while (Date.now() < deadline) {
      deleted = php('echo Illuminate\\Support\\Facades\\DB::table("announcement_deletion_logs")->where("announcement_id",' + id + ')->whereNotNull("photos_removed_at")->exists() ? "yes" : "no";').trim() === 'yes';
      if (deleted) break;
      await delay(4000);
    }
    assert.equal(deleted, true, 'Scheduler automatically deletes due content and photos');
    assert.equal((await context.request.get(photoUrl)).status(), 404);
    await page.goto(base + '/admin/deletion-logs?q=' + encodeURIComponent(title));
    await page.getByText(title, { exact: true }).waitFor();
    await page.getByRole('table').getByText('Otomatis', { exact: true }).waitFor();

    for (const url of ['/.env', '/composer.json', '/vendor/autoload.php', '/storage/app/private/docker-app-key']) {
      assert.equal((await context.request.get(base + url)).status(), 404, 'Private path must not be served');
    }
    await page.goto(base + '/admin/announcements/create');
    await page.getByLabel('Judul pengumuman').fill('[Docker backup] Foto tersimpan');
    await page.getByLabel('Isi pengumuman', { exact: false }).fill('Data uji untuk verifikasi pemulihan foto.');
    await page.locator('#photos').setInputFiles(path.join(root, 'public/assets/office.jpg'));
    await Promise.all([page.waitForURL('**/admin/announcements'), page.getByRole('button', { name: 'Simpan pengumuman' }).click()]);
    assert.deepEqual(errors, []);
    fs.writeFileSync(path.join(root, '.cache/docker-validation.json'), JSON.stringify({ status: 'passed', checks: ['fresh initialization', 'admin login and upload', 'key and photo persistence after recreation', 'Artisan exec key loading', 'automatic deletion by scheduler', 'private paths denied', 'backup photo fixture ready'] }, null, 2));
    console.log('Docker integration checks passed.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });