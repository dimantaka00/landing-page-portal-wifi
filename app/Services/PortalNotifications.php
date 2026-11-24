<?php

namespace App\Services;

use App\Models\Announcement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PortalNotifications
{
    public function prepare(): void
    {
        Announcement::published()->where('notify_users', true)->whereColumn('notification_revision', '>', 'dispatched_revision')
            ->chunkById(100, function ($announcements): void {
                foreach ($announcements as $announcement) {
                    DB::transaction(function () use ($announcement): void {
                        $current = Announcement::whereKey($announcement->id)->lockForUpdate()->first();
                        if (! $current || ! $current->notify_users || $current->notification_revision <= $current->dispatched_revision
                            || ! Announcement::published()->whereKey($current->id)->exists()) {
                            return;
                        }
                        DB::table('portal_notifications')->insert([
                            'announcement_id' => $current->id,
                            'revision' => $current->notification_revision,
                            'created_at' => now(),
                        ]);
                        $current->forceFill(['dispatched_revision' => $current->notification_revision])->save();
                    }, 3);
                }
            });
    }

    public function announcementFor(object $event): ?Announcement
    {
        return Announcement::published()->where('notify_users', true)->whereKey($event->announcement_id)
            ->where('notification_revision', $event->revision)->first();
    }

    public function payload(Announcement $announcement, int $eventId = 0): array
    {
        return [
            'id' => $eventId,
            'announcement_id' => $announcement->id,
            'title' => $announcement->title,
            'body' => Str::limit($announcement->body, 180),
            'category' => $announcement->category,
            'url' => route('announcements.show', $announcement, absolute: false),
            'published_at' => $announcement->published_at?->toIso8601String(),
            'tag' => 'announcement-'.$announcement->id.'-'.$announcement->notification_revision,
        ];
    }
}
