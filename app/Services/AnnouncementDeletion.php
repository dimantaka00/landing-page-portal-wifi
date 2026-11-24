<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AnnouncementDeletion
{
    public function delete(Announcement $announcement, bool $automatic = false, ?User $actor = null): bool
    {
        $logId = DB::transaction(function () use ($announcement, $automatic, $actor): ?int {
            $current = Announcement::query()->lockForUpdate()->find($announcement->id);
            if (! $current || ($automatic && (! $current->delete_at || $current->delete_at->isFuture()))) {
                return null;
            }

            $logId = DB::table('announcement_deletion_logs')->insertGetId([
                'announcement_id' => $current->id,
                'title' => $current->title,
                'category' => $current->category,
                'reason' => $automatic ? 'automatic' : 'manual',
                'actor' => $automatic ? 'Sistem' : ($actor?->username ?? $actor?->name ?? 'Administrator'),
                'scheduled_at' => $current->delete_at,
                'deleted_at' => now(),
                'photo_paths' => json_encode($current->photos()->pluck('path')->all(), JSON_THROW_ON_ERROR),
            ]);
            $current->delete();

            return $logId;
        }, 3);

        if ($logId === null) {
            return false;
        }

        $this->cleanupPhotos($logId);

        return true;
    }

    public function cleanupPhotos(int $logId): bool
    {
        $entry = DB::table('announcement_deletion_logs')->where('id', $logId)->whereNull('photos_removed_at')->first();
        if (! $entry) {
            return true;
        }
        try {
            $paths = json_decode($entry->photo_paths, true, 512, JSON_THROW_ON_ERROR);
            if ($paths !== [] && ! Storage::disk('local')->delete($paths)) {
                throw new \RuntimeException('Pembersihan foto belum berhasil.');
            }
            DB::table('announcement_deletion_logs')->where('id', $logId)->update(['photos_removed_at' => now()]);
        } catch (Throwable $error) {
            Log::warning('Foto pengumuman menunggu pembersihan ulang.', ['deletion_log_id' => $logId, 'error' => $error->getMessage()]);

            return false;
        }

        return true;
    }
}
