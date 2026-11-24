<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use App\Services\AnnouncementDeletion;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AutomaticDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_can_be_scheduled_rescheduled_and_cancelled(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $payload = ['title' => 'Maintenance jaringan', 'body' => 'Pemeliharaan Wi-Fi', 'category' => 'Maintenance', 'status' => 'published', 'delete_at' => now()->addDay()->format('Y-m-d\TH:i')];
        $this->post(route('admin.announcements.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $announcement = Announcement::firstOrFail();
        $this->assertSame('Maintenance', $announcement->category);
        $this->assertSame($payload['delete_at'], $announcement->delete_at->format('Y-m-d\TH:i'));
        $this->get('/?category=Maintenance')->assertOk()->assertSee('Maintenance jaringan');

        $payload['delete_at'] = now()->addDays(2)->format('Y-m-d\TH:i');
        $this->put(route('admin.announcements.update', $announcement), $payload)->assertSessionHasNoErrors();
        $this->assertSame($payload['delete_at'], $announcement->fresh()->delete_at->format('Y-m-d\TH:i'));
        $payload['delete_at'] = '';
        $this->put(route('admin.announcements.update', $announcement), $payload)->assertSessionHasNoErrors();
        $this->assertNull($announcement->fresh()->delete_at);
    }

    public function test_invalid_deletion_dates_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $payload = ['title' => 'Maintenance', 'body' => 'Isi', 'category' => 'Maintenance', 'status' => 'published'];
        foreach (['not-a-date', now()->subMinute()->format('Y-m-d\TH:i')] as $date) {
            $this->post(route('admin.announcements.store'), [...$payload, 'delete_at' => $date])->assertSessionHasErrors('delete_at');
        }
        $this->post(route('admin.announcements.store'), [...$payload, 'published_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'delete_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertSessionHasErrors('delete_at');
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_due_announcements_and_photos_are_removed_once_with_durable_wib_logs(): void
    {
        Storage::fake('local');
        $this->travelTo(now()->startOfMinute());
        $due = Announcement::factory()->create(['category' => 'Maintenance', 'delete_at' => now()]);
        Storage::disk('local')->put('announcements/due.jpg', 'test photo');
        $due->photos()->create(['path' => 'announcements/due.jpg']);
        $draft = Announcement::factory()->create(['status' => 'draft', 'delete_at' => now()->subMinute()]);
        $future = Announcement::factory()->create(['delete_at' => now()->addDay()]);
        $expired = Announcement::factory()->create(['expires_at' => now()->subDay()]);
        $normal = Announcement::factory()->create();

        $this->artisan('portal:purge-announcements')->assertSuccessful();
        $this->assertModelMissing($due);
        $this->assertModelMissing($draft);
        $this->assertModelExists($future);
        $this->assertModelExists($expired);
        $this->assertModelExists($normal);
        $this->assertDatabaseCount('announcement_photos', 0);
        Storage::disk('local')->assertMissing('announcements/due.jpg');
        $this->assertDatabaseHas('announcement_deletion_logs', ['announcement_id' => $due->id, 'title' => $due->title, 'reason' => 'automatic', 'actor' => 'Sistem', 'deleted_at' => now()->format('Y-m-d H:i:s'), 'scheduled_at' => now()->format('Y-m-d H:i:s')]);
        $this->assertNotNull(DB::table('announcement_deletion_logs')->where('announcement_id', $due->id)->value('photos_removed_at'));
        $this->artisan('portal:purge-announcements')->assertSuccessful();
        $this->assertDatabaseCount('announcement_deletion_logs', 2);
    }

    public function test_due_content_and_photos_are_hidden_before_the_scheduler_runs(): void
    {
        Storage::fake('local');
        $announcement = Announcement::factory()->create(['delete_at' => now()->subSecond()]);
        $photo = $announcement->photos()->create(['path' => 'announcements/hidden.jpg']);
        Storage::disk('local')->put($photo->path, 'photo');
        $this->get('/')->assertDontSee($announcement->title);
        $this->get(route('announcements.show', $announcement))->assertNotFound();
        $this->get(route('photos.show', $photo))->assertNotFound();
        $this->assertModelExists($announcement);
    }

    public function test_manual_deletions_are_audited_and_log_access_is_admin_only(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->forceFill(['username' => 'admin'])->save();
        $announcement = Announcement::factory()->create(['title' => '<script>alert(1)</script>']);
        $this->get(route('admin.deletion-logs.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.deletion-logs.index'))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.announcements.destroy', $announcement))->assertRedirect();
        $this->assertDatabaseHas('announcement_deletion_logs', ['announcement_id' => $announcement->id, 'reason' => 'manual', 'actor' => 'admin']);
        $this->get(route('admin.deletion-logs.index', ['reason' => 'manual']))
            ->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('WIB');
        $this->get(route('admin.deletion-logs.index', ['reason' => 'automatic']))->assertDontSee('&lt;script&gt;', false);
    }

    public function test_failed_photo_cleanup_is_retried_without_losing_the_audit_log(): void
    {
        $disk = Storage::fake('local');
        $disk->put('announcements/retry.jpg', 'photo');
        $announcement = Announcement::factory()->create(['delete_at' => now()->subMinute()]);
        $announcement->photos()->create(['path' => 'announcements/retry.jpg']);
        $failingDisk = Mockery::mock(FilesystemAdapter::class);
        $failingDisk->shouldReceive('delete')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($failingDisk, $disk);

        $this->assertTrue(app(AnnouncementDeletion::class)->delete($announcement, automatic: true));
        $this->assertModelMissing($announcement);
        $entry = DB::table('announcement_deletion_logs')->first();
        $this->assertNull($entry->photos_removed_at);
        $disk->assertExists('announcements/retry.jpg');

        $this->artisan('portal:purge-announcements')->assertSuccessful();
        $disk->assertMissing('announcements/retry.jpg');
        $this->assertNotNull(DB::table('announcement_deletion_logs')->value('photos_removed_at'));
        $this->assertDatabaseCount('announcement_deletion_logs', 1);
    }

    public function test_stale_due_record_is_not_deleted_after_rescheduling(): void
    {
        $announcement = Announcement::factory()->create(['delete_at' => now()->subMinute()]);
        Announcement::whereKey($announcement->id)->update(['delete_at' => now()->addDay()]);
        $this->assertFalse(app(AnnouncementDeletion::class)->delete($announcement, automatic: true));
        $this->assertModelExists($announcement);
        $this->assertDatabaseCount('announcement_deletion_logs', 0);
    }
}
