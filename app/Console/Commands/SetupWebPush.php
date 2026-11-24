<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class SetupWebPush extends Command
{
    protected $signature = 'portal:push-setup {--subject= : Alamat HTTPS portal atau mailto:alamat-admin}';

    protected $description = 'Siapkan kunci Web Push privat tanpa menampilkan kunci pada terminal';

    public function handle(): int
    {
        $path = config('webpush.key_file');
        $keys = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : VAPID::createVapidKeys();
        $subject = $this->option('subject') ?: ($keys['subject'] ?? '');
        if ($subject !== '' && ! ((str_starts_with($subject, 'https://') && filter_var($subject, FILTER_VALIDATE_URL))
            || (str_starts_with($subject, 'mailto:') && filter_var(substr($subject, 7), FILTER_VALIDATE_EMAIL)))) {
            $this->error('Subject harus berupa URL HTTPS atau mailto:alamat-email yang valid.');

            return self::FAILURE;
        }
        $keys['subject'] = $subject;
        if (file_put_contents($path, json_encode($keys, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new \RuntimeException('Kunci Web Push tidak dapat disimpan.');
        }
        chmod($path, 0600);
        $this->info('Kunci Web Push tersimpan privat. Kunci lama dipertahankan bila sudah ada.');
        if ($subject === '') {
            $this->warn('Jalankan kembali dengan --subject=https://DOMAIN-PORTAL setelah alamat server tersedia.');
        }

        return self::SUCCESS;
    }
}
