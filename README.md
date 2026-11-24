# Portal Informasi Kantor

Aplikasi pengumuman berbasis Laravel 13, PHP 8.4+, Blade, dan SQLite. Tampilan terinspirasi warna serta susunan portal instansi Kemensos, dengan identitas kantor yang dapat diganti. Website ini berdiri sendiri di `D:\KERJAAN\landing-page-portal-wifi` dengan database dan runtime sendiri.

## Fitur

- Halaman publik responsif, pencarian, kategori, pagination, detail, dan galeri foto.
- Halaman `/portal` untuk sambutan yang nantinya diarahkan dari captive portal kantor.
- Login admin dengan password yang di-hash, pembatasan percobaan login, proteksi CSRF, dan pembatasan peran admin.
- Tambah, edit, hapus pengumuman; draf, terbit, penjadwalan, waktu berakhir, serta sematkan ke urutan atas.
- Maksimal 5 foto per pengumuman; JPG, PNG, WebP; masing-masing 5 MB, maksimal 6000 × 6000 piksel.
- Foto disimpan privat dan hanya disajikan melalui pemeriksaan publikasi atau sesi admin. Foto draf tidak dapat dibuka pengunjung melalui URL langsung.
- Seluruh aset disimpan lokal agar tidak membutuhkan CDN atau koneksi internet pada perangkat pengunjung. Jadwal menggunakan Asia/Jakarta (WIB). Penghapusan otomatis dan Web Push memerlukan scheduler aktif melalui scripts/start.ps1 atau scheduler server.

## Menjalankan di komputer ini (Windows)

Dari folder utama `D:\KERJAAN\landing-page-portal-wifi`:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/start.ps1
```

Buka `http://127.0.0.1:8011` dan halaman admin `http://127.0.0.1:8011/admin`.

PHP portable dapat dipasang di `.runtime/php` untuk instalasi lokal. Skrip juga dapat memakai PHP yang tersedia di PATH. Runtime PHP dan Composer tersedia di `.runtime` proyek ini. Snapshot backup mandiri juga menyertakan runtime dan dependency agar dapat dipulihkan tanpa mengunduh ulang.

Penyiapan ulang di Windows (aman dijalankan lagi, tidak menghapus data dan tidak mengganti APP_KEY yang sudah ada):

```powershell
cd D:\KERJAAN\landing-page-portal-wifi
composer install
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/setup.ps1 -AdminEmail admin@kantor.local
```

Opsional tambahkan `-Demo` hanya untuk mengisi database yang masih kosong. Tiga pengumuman berlabel `[Contoh]` sudah tersedia pada instalasi lokal saat ini. Hapus atau sesuaikan melalui admin sebelum penggunaan operasional.

## Akses admin

Akun lokal awal: `admin@kantor.local`. Kata sandi acak disimpan hanya di:

```text
storage/app/private/initial-admin.txt
```

Berkas akses, `.env`, dan database lokal dibatasi ke pengguna Windows yang menjalankan setup, pemilik direktori proyek, SYSTEM, dan Administrators. Jangan menyalin atau menerbitkan berkas akses. Tidak ada registrasi publik atau kata sandi bawaan yang dibagikan antarpemasangan.

Untuk membuat admin lain atau mengganti kata sandi, dari folder aplikasi:

```text
php artisan portal:admin admin@kantor.local
```

Perintah meminta kata sandi tersembunyi, minimal 12 karakter. Setelah mengganti kata sandi, hapus berkas akses awal yang sudah tidak berlaku atau simpan di pengelola kata sandi lokal. Jangan memberikan kata sandi sebagai argumen command line.

## Identitas kantor

Edit `.env` aplikasi:

```dotenv
APP_URL=http://alamat-server-kantor:8011
PORTAL_OFFICE_NAME="Nama Kantor Anda"
PORTAL_UNIT_NAME="Pusat Informasi Internal"
PORTAL_HELP_EMAIL=helpdesk@domain-kantor.go.id
```

Jalankan `php artisan config:clear` sesudah perubahan konfigurasi. Emblem aplikasi menggunakan logo Kementerian Sosial RI. Foto ilustrasi kantor berasal dari [Unsplash](https://images.unsplash.com/photo-1497366754035-f200968a6e72), disimpan lokal; ganti `public/assets/office.jpg` dengan foto kantor sendiri bila diperlukan. Referensi desain: [Kemensos](https://kemensos.go.id/).

## Akses percobaan melalui LAN

Jalankan pada antarmuka jaringan server:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/start.ps1 -BindAddress 0.0.0.0 -Port 8011
```

Gunakan `http://IP-LAN-SERVER:8011` dari perangkat di jaringan kantor. Server harus dapat dijangkau dari VLAN pengguna dan port tersebut harus diizinkan firewall. Skrip tidak mengubah firewall. Server bawaan PHP digunakan untuk pengembangan dan percobaan; gunakan PHP-FPM dengan Nginx atau Apache untuk operasi kantor.

## Pemasangan di server PHP

1. Salin folder aplikasi, instal PHP 8.4+ dengan extension Laravel serta `pdo_sqlite`, `sqlite3`, `fileinfo`, `mbstring`, `openssl`, dan `gd`. Composer akan memeriksa persyaratan framework. Versi PHP minimum aplikasi ini mengikuti `composer.lock` yang sudah diuji.
2. Jalankan `composer install --no-dev --optimize-autoloader`. Salin `.env.example` menjadi `.env`; isi alamat dan identitas kantor.
3. Jalankan `php artisan key:generate` hanya pada pemasangan baru. Pertahankan APP_KEY ketika memulihkan backup.
4. Buat berkas kosong `database/database.sqlite`, jalankan `php artisan migrate --force`, lalu `php artisan portal:admin EMAIL-ADMIN`.
5. Arahkan document root web server hanya ke folder `public`. Beri proses PHP izin tulis ke `storage`, `bootstrap/cache`, dan database SQLite beserta direktori `database`. SQLite harus berada pada disk lokal server.
6. Atur PHP `upload_max_filesize=5M` atau lebih, `post_max_size=32M` atau lebih, dan batas body web server setidaknya 32 MB. Aplikasi tetap memvalidasi setiap foto maksimal 5 MB.
7. Atur `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` yang sesuai, serta HTTPS dengan `SESSION_SECURE_COOKIE=true` untuk halaman admin. Pastikan jam server akurat.
8. Jalankan `php artisan config:cache` dan `php artisan view:cache`. Siapkan backup berkala untuk database dan foto.

Contoh lokasi Nginx di dalam server block, sesuaikan document root dan socket PHP:

```nginx
root /var/www/landing-page-portal-wifi/public;
index index.php;
client_max_body_size 32m;
location / { try_files $uri $uri/ /index.php?$query_string; }
location = /index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
}
location ~ \.php$ { return 404; }
location ~ /\. { deny all; }
```

## Captive portal Huawei: tahap berikutnya

Wi-Fi kantor saat ini masih memakai password biasa, dengan WLC Huawei dan AP371. Aplikasi menyediakan halaman `/portal` tetapi belum mengonfigurasi atau mengautentikasi perangkat pada WLC. Mengakses halaman tersebut tidak membuka otorisasi internet. Tidak ada tombol yang mengklaim koneksi sudah diotorisasi.

Setelah website dipasang di server kantor, siapkan model WLC, versi firmware/VRP, SSID/VLAN yang akan dipakai, IP/DNS portal, serta alur autentikasi yang dipilih. Pengalihan dan pemberian akses harus disesuaikan dengan fitur WLC dan metode Portal Huawei. Jangan mengganti SSID operasional sebelum uji pada SSID percobaan.

Halaman ini dapat dipakai sebagai halaman informasi setelah autentikasi berhasil. Untuk menggunakannya sebagai halaman pra-autentikasi, integrasi protokol/layanan autentikasi Portal Huawei harus dikerjakan sesuai model dan versi WLC. Aplikasi Laravel HTTP ini belum mengimplementasikan protokol autentikasi Portal Huawei.

Sumber resmi: [URL server Portal Huawei](https://info.support.huawei.com/network/ptmngsys/Web/tsrev_wlan/en/content/wlan/17_edesk_sta_extra_portal_cdx/edesk_sta_extra_portal_cdx_edesk004.html), [pemeriksaan popup Portal](https://info.support.huawei.com/network/ptmngsys/Web/tsrev_wlan/en/content/wlan/32_edesk_auto-popup_portal_page_failed/edesk_auto-popup_portal_page_failed_edesk001.html).

## Validasi dan backup

Dari folder aplikasi:

```text
php artisan test --compact tests/Feature/AnnouncementTest.php
```

Uji fitur menggunakan database SQLite dalam memori dan disk foto palsu. Uji browser menggunakan Chrome dan dependency Playwright lokal. Dependency sudah disalin ke proyek ini; gunakan `npm ci` jika perlu memasangnya kembali:

```text
node tests/browser/portal.cjs
```

Perintah browser dijalankan dari folder proyek ini, dengan portal aktif di `127.0.0.1:8011`; dapat diubah melalui `PORTAL_TEST_URL`. Skrip membuat pengumuman uji lalu menghapusnya, dan menyimpan screenshot/hasil di `.cache` proyek ini. Jangan jalankan uji browser pada instalasi produksi berisi pengumuman operasional.

Backup penuh dijalankan dari folder proyek ini, setelah server portal dihentikan:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/backup-project.ps1
```

Snapshot disimpan di folder saudara `D:\KERJAAN\landing-page-portal-wifi-backups`, dengan ID unik. `project.zip` berisi source, konfigurasi privat, SQLite, foto, runtime PHP, serta dependency Composer dan pengujian. Skrip memverifikasi checksum seluruh file dan menjalankan uji restore SQLite beserta foto sebelum menetapkan `manifest.json` berstatus `verified`. Arsip tetap lokal dengan izin terbatas; jangan unggah snapshot, `.env`, atau berkas akses admin ke GitHub.

Jika proyek menggunakan Git, riwayat ikut disimpan dalam `repository.bundle`. Snapshot pemindahan pertama juga menyertakan bundle riwayat repository asal sebagai referensi; aplikasi ini tetap dapat berjalan sendiri tanpa folder proyek asal.

Untuk memulihkan, periksa status `verified` pada manifest dan checksum SHA-256 `project.zip`, lalu ekstrak arsip ke folder kosong. Gunakan lokasi asli agar konfigurasi PHP portable langsung sesuai; jika memakai lokasi lain, sesuaikan `extension_dir`, `curl.cainfo`, dan `openssl.cafile` pada `.runtime/php/php.ini`. Jalankan `scripts/start.ps1`. Pertahankan APP_KEY dan database dari backup agar akses serta data tetap tersedia. Hasil uji pemulihan dicatat di `restore-test.json` dalam snapshot.
### Galeri gedung Kementerian Sosial

Ilustrasi segitiga pada hero diganti dengan foto Salemba dan Cawang Kencana yang disimpan lokal di public/assets/kemensos. Slideshow berganti setiap 5 detik, dengan pilihan foto, navigasi, dan jeda/lanjutkan. Pergantian otomatis berhenti saat pointer berada di galeri, tab tersembunyi, atau fokus masuk ke galeri. Preferensi reduced motion menonaktifkan autoplay awal; pengguna tetap dapat memilih Lanjutkan.

Sumber foto ditautkan pada masing-masing caption:
- Salemba: UIN Syarif Hidayatullah Jakarta, https://uinjkt.ac.id/id/kemensos-ri-uin-jakarta-jajaki-kerjasama
- Cawang Kencana: Kemensos melalui IDN Times, https://www.idntimes.com/news/indonesia/kembalinya-gedung-cawang-kencana-csc-1-02-1kg27-mcdfnh

Foto merupakan dokumentasi gedung dari artikel sumber, bukan siaran langsung.

### Pengelolaan akun pada instalasi kantor

Kredensial instalasi lokal tidak disertakan dalam repository. Buat akun administrator melalui portal:admin pada setiap pemasangan. Login mendukung username atau email; password disimpan sebagai hash.

Perintah setup untuk instalasi baru tetap membuat kata sandi acak. Perubahan akun pada instalasi ini tidak mereset kata sandi setiap aplikasi dijalankan. Perintah portal:admin EMAIL-ADMIN tetap dapat dipakai untuk mengganti kata sandi akun yang sama.
### Maintenance dan penghapusan otomatis

Kategori Maintenance tersedia pada form admin dan filter portal. Kolom Hapus otomatis pada (WIB) bersifat opsional dan terpisah dari Berakhir pada. Waktu hapus harus di masa depan serta sesudah waktu publikasi. Jadwal dapat diubah atau dibatalkan dengan mengosongkan kolom. Setelah waktunya tiba, pengumuman langsung tidak dapat dibaca publik; scheduler menghapus data dan foto pada pemeriksaan per menit berikutnya, termasuk draf yang dijadwalkan untuk dihapus.

Menu admin Log penghapusan mencatat ID, judul, kategori, jenis penghapusan, pelaku, jadwal, waktu aktual penghapusan (WIB), dan status pembersihan foto. Log tetap tersimpan setelah pengumuman dihapus. Penghapusan manual juga dicatat. Kegagalan pembersihan foto dicoba ulang pada pemeriksaan berikutnya.

scripts/start.ps1 menjalankan server dan Laravel schedule:work bersamaan. Scheduler aktif selama aplikasi berjalan melalui skrip tersebut. Saat aplikasi mati, jadwal tertunda akan diproses setelah aplikasi dijalankan kembali. Pada server produksi, jalankan php artisan schedule:run setiap menit melalui cron/Task Scheduler atau kelola schedule:work sebagai service. Menjalankan PHP server secara langsung tanpa scheduler tidak menjalankan penghapusan otomatis. Pemeriksaan manual: php artisan portal:purge-announcements.
### Pop-up pembaruan dan Web Push

Form admin menyediakan Kirim notifikasi pembaruan (aktif secara bawaan). Saat pengumuman terbit atau disimpan ulang dengan pilihan ini, revisi pembaruan disiapkan. Draf dan publikasi masa depan baru dikirim saat sudah terbit. Menonaktifkan pilihan ini mencegah pengiriman; pengumuman yang ditarik, berakhir, atau dijadwalkan untuk dihapus tidak dibocorkan melalui feed maupun Web Push. Popup awal pada /portal menampilkan informasi terbaru sekali per perangkat/browser. Halaman publik memeriksa pembaruan setiap 5 detik saat terlihat; kursor persisten mencegah pengulangan setelah reload. Scheduler memeriksa Web Push setiap 10 detik, dengan percobaan ulang saat layanan push bermasalah. Waktu pengiriman bukan jaminan tampil seketika: jaringan, OS, izin, dan jumlah perangkat memengaruhinya.

Pengunjung memilih Aktifkan notifikasi perangkat melalui browser utama. Izin tidak diminta otomatis. Pengunjung dapat menonaktifkan langganan dari portal atau pengaturan browser. Browser captive portal bawaan OS sering tidak mendukung notifikasi/service worker. iPhone/iPad membutuhkan web app di Layar Utama dan versi OS yang mendukung Web Push. Perangkat yang belum memberi izin tidak bisa dipaksa menerima popup OS. Untuk pop-up dalam portal, halaman harus tetap terbuka; perangkat offline menerima pembaruan saat kembali terhubung.

Penyiapan setelah dipasang di server:
1. Pasang domain portal dengan sertifikat HTTPS yang dipercaya perangkat. Pastikan alamat portal diizinkan pada captive portal Huawei dan arahkan halaman masuk ke /portal. Aplikasi ini belum mengubah konfigurasi WLC.
2. Atur APP_URL di konfigurasi lokal ke URL HTTPS sebenarnya, lalu jalankan php artisan config:clear.
3. Siapkan identitas Web Push: php artisan portal:push-setup --subject=https://DOMAIN-PORTAL. Kunci yang sudah ada dipertahankan. Pada Windows dengan runtime portable, gunakan powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/artisan.ps1 portal:push-setup --subject=https://DOMAIN-PORTAL agar konfigurasi OpenSSL terpasang sejak proses PHP dimulai.
4. scripts/start.ps1 mengaktifkan scheduler untuk instalasi lokal. Untuk server produksi, kelola php artisan schedule:work sebagai service atau panggil schedule:run setiap menit; tugas sub-menit bekerja selama scheduler aktif.
5. Izinkan HTTPS keluar dari server dan perangkat ke layanan push browser (Google FCM, Mozilla, Apple, atau Windows), termasuk setelah autentikasi Wi-Fi. Langganan hanya menerima pembaruan baru setelah pendaftaran.
6. Uji dengan perangkat nyata: buka portal di browser utama, berikan izin, tutup portal, lalu terbitkan pengumuman uji. Penerimaan layanan push tidak membuktikan notifikasi sudah ditampilkan oleh OS.

Kunci privat disimpan lokal di storage/app/private/web-push-keys.json dengan izin terbatas. Jangan unggah atau mencetak berkas tersebut. Detail endpoint dan kunci langganan disimpan terenkripsi memakai APP_KEY. Endpoint dibatasi ke layanan push yang didukung untuk mencegah permintaan ke alamat internal sembarang. Langganan kedaluwarsa dihapus; kegagalan sementara dicoba ulang dengan jeda. Penarikan pengumuman tidak bisa menarik kembali notifikasi yang sudah ditampilkan OS.

Web Push mengirim ke perangkat yang berlangganan dan dapat berjalan di luar Wi-Fi kantor. Pembatasan berdasarkan perangkat yang saat itu benar-benar terhubung ke SSID memerlukan integrasi sesi/API WLC Huawei yang belum dikonfigurasi. Keanggotaan Wi-Fi tidak dapat ditentukan hanya dari JavaScript portal. Jangan menganggap ketersediaan fitur ini sebagai bukti integrasi WLC atau pengujian pada semua model perangkat.

Uji tambahan: node tests/browser/notifications.cjs dan node tests/browser/notification-worker.cjs. Uji protokol Web Push menggunakan transport HTTP tiruan untuk memeriksa enkripsi dan VAPID tanpa menghubungi perangkat atau layanan push eksternal.
Pada Windows portable, jalankan seluruh uji backend melalui wrapper agar OpenSSL membaca konfigurasi sebelum PHP dimulai:
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/artisan.ps1 test --compact tests/Feature/AnnouncementTest.php tests/Feature/AutomaticDeletionTest.php tests/Feature/PortalNotificationTest.php
### Sumber foto background kategori

Foto dipilih setelah pencarian Google Images dengan kata kunci kategori dan diverifikasi pada halaman sumber. Semuanya ilustrasi umum; foto pegawai/kegiatan bukan dokumentasi pegawai atau kegiatan Kemensos. Berkas JPEG asli hasil unduhan disimpan di public/assets/category-covers/photos. SVG sampul menyematkan foto lokal beserta ikon kategori agar tidak membutuhkan koneksi ke situs sumber.

- Umum: Slidebean, ruang kantor, https://unsplash.com/photos/empty-meeting-room-6MtJ-y1hzTs
- Kepegawaian: Vitaly Gariev, kolaborasi pegawai, https://unsplash.com/photos/business-people-collaborating-in-a-modern-office-meeting-VRT8k7BJ7wk
- Kegiatan: Vitaly Gariev, kegiatan rapat, https://unsplash.com/photos/business-professionals-collaborating-in-a-modern-office-meeting-Cnsh9WwhVCw
- Teknologi Informasi: Massimo Botturi, jaringan server, https://unsplash.com/photos/electronic-wire-lot-zFYUsLk_50Y
- Maintenance: Ricardo IV Tamayo, teknisi memperbaiki komputer, https://unsplash.com/photos/two-men-working-on-a-computer-motherboard-nApaD08bFAE

Halaman sumber menandai foto sebagai foto gratis di bawah Unsplash License: https://unsplash.com/license. Atribusi disimpan di sini untuk penelusuran sumber; jangan menyiratkan dukungan dari fotografer atau bahwa subjek adalah pegawai Kemensos.

### Mengambil proyek dari GitHub

Repository menyertakan source, migrasi, aset kategori, serta lockfile Composer dan npm. Runtime portable, dependency hasil instalasi, database, unggahan pengguna, kredensial admin, konfigurasi lokal, dan kunci Web Push disimpan di komputer/server masing-masing.

Untuk pemasangan baru: clone repository, jalankan composer install, salin .env.example menjadi .env, lalu ikuti langkah penyiapan di atas. Gunakan PHP 8.4+ yang tersedia di PATH atau sediakan runtime portable sendiri. Jalankan npm ci hanya bila ingin menjalankan pengujian browser. Jangan menyalin APP_KEY atau kunci Web Push dari instalasi lain ke repository; simpan backup privat secara terpisah.
## Menjalankan dengan Docker

Docker menggunakan PHP 8.4 + Apache dengan document root public serta service scheduler terpisah. Instal Docker Engine/Desktop dan Docker Compose terlebih dahulu.

Dari folder proyek, salin konfigurasi contoh (jangan unggah docker.env):
~~~powershell
Copy-Item docker.env.example docker.env
docker compose --env-file docker.env up -d --build --wait
~~~

Buka http://localhost:8080. Untuk akses LAN, ubah DOCKER_BIND_ADDRESS menjadi 0.0.0.0 dan DOCKER_APP_URL menjadi alamat server, lalu jalankan perintah up kembali. Pengaturan bawaan hanya membuka port pada localhost. Untuk HTTPS di server, tempatkan reverse proxy TLS di depan aplikasi dan atur URL serta DOCKER_SESSION_SECURE_COOKIE=true.

Instalasi Docker memakai volume terpisah; database dan akun pada instalasi Windows tidak otomatis disalin. Buat admin baru:
~~~text
docker compose --env-file docker.env exec --user www-data app php artisan portal:admin admin@kantor.local --generate
~~~
Kredensial acak disimpan di storage/app/private/initial-admin.txt pada volume container. Baca hanya pada terminal pribadi:
~~~text
docker compose --env-file docker.env exec --user www-data app cat storage/app/private/initial-admin.txt
~~~
Gunakan email tersebut pada kolom Username admin. Jangan menyalin isi berkas ini ke issue GitHub atau log publik.

APP_KEY dibuat sekali saat volume pertama kali diinisialisasi. Kuncinya disimpan pada storage/app/private/docker-app-key dan dibaca bersama oleh app, scheduler, serta perintah Artisan. Kunci Web Push juga dibuat lokal tanpa subject; setelah HTTPS siap, jalankan portal:push-setup --subject=https://DOMAIN-PORTAL melalui exec app. APP_KEY dan data tetap sama saat container dibuat ulang. DOCKER_APP_KEY bersifat opsional untuk pemulihan data lama; nilai yang berbeda dari kunci volume yang sudah ada ditolak.

Volume portal_storage menyimpan foto, sesi, cache, dan kunci privat. Volume portal_database menyimpan database SQLite. Nama volume mendapat awalan nama project Compose. Jangan menggunakan down -v untuk penghentian biasa karena opsi tersebut menghapus volume. Gunakan:
~~~text
docker compose --env-file docker.env down
docker compose --env-file docker.env up -d --wait
docker compose --env-file docker.env logs --tail=100 app scheduler
~~~

Build mengabaikan .env, docker.env, database lokal, foto privat, runtime Windows, dependency host, dan arsip backup. Dependency produksi diinstal dari composer.lock. Perubahan source memerlukan build ulang. Scheduler otomatis menangani penghapusan pengumuman dan Web Push; jangan menjalankan scheduler kedua terhadap volume yang sama.

### Backup dan pemulihan Docker

Skrip backup-project.ps1 mencadangkan source dan instalasi lokal Windows. Data pada volume Docker memerlukan backup Docker tambahan:
~~~powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/backup-docker.ps1 -EnvFile docker.env
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/backup-project.ps1
~~~
backup-docker.ps1 menghentikan app/scheduler project yang dipilih, menyimpan image serta kedua volume ke folder backup saudara dengan izin terbatas, menguji restore ke volume sementara, memeriksa SQLite/foto dan checksum semua file storage, lalu menjalankan kembali service yang sebelumnya aktif. Pastikan manifest.json berstatus verified dan restore-test.json berstatus passed. Snapshot sebelumnya tetap dipertahankan. Untuk project Compose bernama lain, tambahkan -ProjectName NAMA. Jangan mengunggah arsip tersebut ke GitHub.

Pemulihan dilakukan pada instalasi Compose kosong atau volume baru: docker load --input PATH/docker-image.tar, tag image dengan ID pada manifest menjadi landing-page-portal-wifi:local, lalu docker compose create untuk membuat container dan volume kosong tanpa menjalankannya. Ekstrak storage.tar dan database.tar ke volume masing-masing dengan container sementara menggunakan image yang dipulihkan dan entrypoint tar. Jangan mengekstrak ke volume yang masih berisi data operasional. Setelah itu jalankan docker compose up -d --no-build --wait. Kunci dan database harus berasal dari snapshot yang sama. Periksa nama volume melalui docker volume inspect dan label com.docker.compose.project sebelum memulihkan data.
Seluruh perintah Artisan melalui exec sebaiknya memakai --user www-data agar file yang dihasilkan tetap dapat dibaca aplikasi. Backup Docker juga menyimpan container-environment.json (privat) sebagai acuan variabel lingkungan dan port saat pemulihan. Berkas ini dapat berisi konfigurasi sensitif; simpan bersama arsip privat, jangan unggah ke GitHub. Gunakan konfigurasi tersebut bersama docker.env dari backup source saat menyiapkan ulang instalasi.
## Pengaturan pengguna dashboard

Administrator dapat membuka Pengaturan pengguna pada sidebar untuk menambahkan nama, username, email, peran, status aktif, dan password baru (minimal 12 karakter dengan konfirmasi). Login dapat memakai username atau email. Password tidak ditampilkan kembali dan disimpan sebagai hash. Password kosong pada halaman edit mempertahankan password yang ada.

Administrator dapat mengelola akun dan pengumuman. Pengelola pengumuman dapat mengelola konten dan melihat log penghapusan, tanpa akses pengaturan akun. Akun admin yang sudah ada mendapat akses pengelolaan pengguna saat migrasi dijalankan. Akun yang dibuat melalui portal:admin juga mendapat akses administrator.

Penonaktifan akun, penggantian password, atau perubahan akses mencabut sesi lama pada permintaan berikutnya, termasuk akses foto draf. Administrator tidak dapat menonaktifkan atau menurunkan peran akun yang sedang dipakai. Untuk pemulihan akses melalui server, gunakan perintah portal:admin dengan email akun yang bersangkutan.

Pada Docker, build ulang lalu jalankan Compose agar migrasi terbaru diterapkan. Kredensial akun instalasi lokal maupun password pengguna tidak disertakan dalam repository. Uji alur pengguna melalui node tests/browser/users.cjs menggunakan server lokal di 127.0.0.1:8011.