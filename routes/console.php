<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Artisan::command('portal:admin {email} {--name=Administrator} {--generate : Simpan kata sandi acak ke berkas lokal privat}', function () {
    $email = Str::lower($this->argument('email'));
    $password = $this->option('generate') ? Str::random(24) : $this->secret('Kata sandi baru (minimal 12 karakter)');
    $validation = Validator::make(['email' => $email, 'password' => $password, 'name' => $this->option('name')], [
        'email' => 'required|email|max:254', 'password' => 'required|string|min:12|max:128', 'name' => 'required|string|max:100',
    ]);
    if ($validation->fails()) {
        $this->error($validation->errors()->first());

        return 1;
    }
    $user = User::firstOrNew(['email' => $email]);
    $user->forceFill(['name' => $this->option('name'), 'password' => Hash::make($password), 'is_admin' => true])->save();
    if ($this->option('generate')) {
        $path = 'initial-admin.txt';
        Storage::disk('local')->put($path, "Akses admin portal kantor\nEmail: {$email}\nKata sandi: {$password}\n\nSimpan secara privat. Ganti kata sandi dengan php artisan portal:admin {$email}\n");
        @chmod(Storage::disk('local')->path($path), 0600);
        $this->info('Akses tersimpan lokal di storage/app/private/initial-admin.txt.');
    }
    $this->info('Akun admin berhasil dibuat atau diperbarui.');

    return 0;
})->purpose('Buat admin atau ganti kata sandi melalui terminal server');

Artisan::command('portal:demo', function () {
    if (Announcement::exists()) {
        $this->warn('Data sudah tersedia; tidak ada pengumuman yang diubah.');

        return 0;
    }
    $samples = [
        ['title' => '[Contoh] Selamat datang di portal informasi kantor', 'category' => 'Umum', 'is_pinned' => true,
            'body' => "Portal ini menjadi tempat berbagi pengumuman untuk seluruh pegawai dan tamu kantor.\n\nAnda dapat menemukan informasi umum, agenda kegiatan, kepegawaian, serta pemberitahuan dari tim teknologi informasi di halaman ini.\n\nIni adalah konten contoh untuk memperkenalkan aplikasi. Administrator dapat mengedit atau menghapusnya melalui halaman admin sebelum portal digunakan secara operasional."],
        ['title' => '[Contoh] Panduan penggunaan jaringan Wi-Fi', 'category' => 'Teknologi Informasi', 'is_pinned' => false,
            'body' => "Gunakan jaringan kantor untuk mendukung pekerjaan dan kegiatan yang diperbolehkan. Jaga kerahasiaan akun dan jangan membagikan kata sandi jaringan kepada pihak lain.\n\nJika mengalami kendala koneksi, hubungi pengelola atau tim TI kantor.\n\nPengumuman ini adalah contoh. Sesuaikan dengan kebijakan jaringan di kantor Anda."],
        ['title' => '[Contoh] Agenda koordinasi mingguan', 'category' => 'Kegiatan', 'is_pinned' => false,
            'body' => "Bagikan agenda rapat, lokasi kegiatan, dan informasi persiapan kepada rekan kantor melalui portal ini.\n\nCantumkan tanggal, jam, tempat, serta narahubung yang dapat dihubungi agar informasi mudah dipahami.\n\nIni adalah pengumuman contoh, bukan undangan kegiatan yang sebenarnya."],
    ];
    foreach ($samples as $index => $sample) {
        $announcement = Announcement::create([...$sample, 'status' => 'published', 'published_at' => now()->subMinutes($index + 1)]);
        if ($index === 0 && is_file(public_path('assets/office.jpg'))) {
            $path = 'announcements/demo-office.jpg';
            Storage::disk('local')->put($path, file_get_contents(public_path('assets/office.jpg')));
            $announcement->photos()->create(['path' => $path]);
        }
    }
    $this->info('Tiga pengumuman contoh ditambahkan. Hapus atau sesuaikan sebelum penggunaan operasional.');

    return 0;
})->purpose('Tambahkan pengumuman contoh hanya pada database kosong');

Schedule::command('portal:purge-announcements')->everyMinute()->withoutOverlapping(10);

Schedule::command('portal:send-notifications')->everyTenSeconds()->withoutOverlapping(5);
