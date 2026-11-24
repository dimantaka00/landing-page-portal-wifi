<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryCoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_category_has_a_default_cover_on_cards_details_and_editor(): void
    {
        $urls = [];
        $admin = User::factory()->create(['is_admin' => true]);
        foreach (Announcement::CATEGORIES as $category) {
            $announcement = Announcement::factory()->create(['category' => $category]);
            $this->assertTrue($announcement->uses_category_cover);
            $this->assertFileExists(public_path(parse_url($announcement->category_cover_url, PHP_URL_PATH)));
            $this->get(route('home', ['category' => $category]))->assertOk()->assertSee($announcement->category_cover_url);
            $this->get(route('announcements.show', $announcement))->assertOk()->assertSee($announcement->category_cover_url);
            $this->actingAs($admin)->get(route('admin.announcements.edit', $announcement))->assertOk()->assertSee('Gunakan gambar bawaan sesuai kategori');
            $urls[] = $announcement->category_cover_url;
        }
        $this->assertCount(5, array_unique($urls));
        $this->get(route('admin.announcements.create'))->assertOk();
    }

    public function test_admin_can_select_default_cover_preserve_uploaded_photos_and_switch_back(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $payload = ['title' => 'Pemeliharaan', 'body' => 'Informasi layanan', 'category' => 'Maintenance', 'status' => 'published', 'use_default_cover' => 1];
        $this->post(route('admin.announcements.store'), [...$payload, 'photos' => [UploadedFile::fake()->image('office.jpg')]])->assertSessionHasNoErrors();
        $announcement = Announcement::firstOrFail();
        $this->assertTrue($announcement->use_default_cover);
        $this->assertTrue($announcement->uses_category_cover);
        $photo = $announcement->photos->first();
        $this->get(route('announcements.show', $announcement))->assertOk()->assertSee($announcement->category_cover_url)->assertSee(route('photos.show', $photo));
        Storage::disk('local')->assertExists($photo->path);
        $this->put(route('admin.announcements.update', $announcement), [...$payload, 'use_default_cover' => 0])->assertSessionHasNoErrors();
        $this->assertSame(route('photos.show', $photo), $announcement->fresh()->cover_url);
        $this->put(route('admin.announcements.update', $announcement), [...$payload, 'category' => 'Kegiatan'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('/kegiatan.svg', $announcement->fresh()->cover_url);
        Storage::disk('local')->assertExists($photo->path);
    }

    public function test_removing_last_upload_falls_back_to_category_and_deletion_preserves_shared_assets(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $announcement = Announcement::factory()->create(['category' => 'Maintenance']);
        Storage::disk('local')->put('announcements/temp.jpg', 'photo');
        $photo = $announcement->photos()->create(['path' => 'announcements/temp.jpg']);
        $this->put(route('admin.announcements.update', $announcement), [
            'title' => 'Maintenance', 'body' => 'Perbaikan', 'category' => 'Maintenance', 'status' => 'published',
            'use_default_cover' => 0, 'remove_photos' => [$photo->id],
        ])->assertSessionHasNoErrors();
        $this->assertTrue($announcement->fresh()->uses_category_cover);
        $this->delete(route('admin.announcements.destroy', $announcement))->assertRedirect();
        $this->assertFileExists(public_path('assets/category-covers/maintenance.svg'));
    }
}
