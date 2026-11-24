<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Services\AnnouncementDeletion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeAnnouncements extends Command
{
    protected $signature = 'portal:purge-announcements';

    protected $description = 'Hapus pengumuman sesuai jadwal dan simpan log waktu penghapusan';

    public function handle(AnnouncementDeletion $deletion): int
    {
        DB::table('announcement_deletion_logs')->whereNull('photos_removed_at')->orderBy('id')
            ->chunkById(100, function ($entries) use ($deletion): void {
                foreach ($entries as $entry) {
                    $deletion->cleanupPhotos($entry->id);
                }
            });

        $count = 0;
        Announcement::whereNotNull('delete_at')->where('delete_at', '<=', now())
            ->chunkById(100, function ($announcements) use ($deletion, &$count): void {
                foreach ($announcements as $announcement) {
                    if ($deletion->delete($announcement, automatic: true)) {
                        $count++;
                    }
                }
            });
        $this->info("Pengumuman dihapus otomatis: {$count}.");

        if (DB::table('announcement_deletion_logs')->whereNull('photos_removed_at')->exists()) {
            $this->warn('Sebagian foto menunggu pembersihan ulang pada pemeriksaan berikutnya.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
