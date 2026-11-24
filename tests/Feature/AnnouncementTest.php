<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_open_create_and_edit_forms_for_every_category(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.announcements.create'))->assertOk()->assertSee('Tambah pengumuman')->assertSee('name="category"', false);

        foreach (Announcement::CATEGORIES as $category) {
            $announcement = Announcement::factory()->create(['category' => $category]);
            $this->get(route('admin.announcements.edit', $announcement))
                ->assertOk()->assertSee('Edit pengumuman')->assertSee($category);
        }
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['title' => 'Koordinasi kantor', 'body' => "Informasi lengkap.\nParagraf kedua.", 'category' => 'Umum', 'status' => 'published'], $extra);
    }

    public function test_publication_rules_also_protect_direct_links_and_photos(): void
    {
        Storage::fake('local');
        $active = Announcement::factory()->create(['title' => 'Terbit hari ini']);
        $hidden = [
            Announcement::factory()->create(['status' => 'draft', 'title' => 'Rahasia draf']),
            Announcement::factory()->create(['published_at' => now()->addDay(), 'title' => 'Rahasia jadwal']),
            Announcement::factory()->create(['expires_at' => now()->subMinute(), 'title' => 'Rahasia berakhir']),
        ];
        $this->get('/')->assertOk()->assertSee('Terbit hari ini')->assertDontSee('Rahasia');
        $this->get('/portal')->assertOk()->assertSee('Selamat datang di jaringan kantor');
        $this->get(route('announcements.show', $active))->assertOk();
        foreach ($hidden as $announcement) {
            $path = UploadedFile::fake()->image('private.jpg')->store('announcements', 'local');
            $photo = $announcement->photos()->create(['path' => $path]);
            $this->get(route('announcements.show', $announcement))->assertNotFound();
            $this->get(route('photos.show', $photo))->assertNotFound();
            $this->get('/storage/'.$path)->assertNotFound();
        }
    }

    public function test_guests_and_non_admin_users_cannot_manage_announcements(): void
    {
        $announcement = Announcement::factory()->create();
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->post(route('admin.announcements.store'), $this->payload())->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/announcements')->assertForbidden();
        $this->delete(route('admin.announcements.destroy', $announcement))->assertForbidden();
        $this->assertDatabaseHas('announcements', ['id' => $announcement->id]);
    }

    public function test_admin_can_create_edit_publish_replace_photos_and_delete(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin())->post(route('admin.announcements.store'), $this->payload([
            'status' => 'draft', 'photos' => [UploadedFile::fake()->image('first.jpg'), UploadedFile::fake()->image('second.png')],
        ]))->assertRedirect(route('admin.announcements.index'))->assertSessionHasNoErrors();
        $announcement = Announcement::firstOrFail();
        $this->assertCount(2, $announcement->photos);
        $first = $announcement->photos->first();
        Storage::disk('local')->assertExists($first->path);
        $this->get(route('admin.announcements.edit', $announcement))->assertOk()->assertSee('Edit pengumuman');
        $this->get(route('photos.show', $first))->assertOk();
        $this->put(route('admin.announcements.update', $announcement), $this->payload([
            'title' => 'Informasi diperbarui', 'is_pinned' => '1', 'remove_photos' => [$first->id],
            'photos' => [UploadedFile::fake()->image('replacement.webp')],
        ]))->assertSessionHasNoErrors()->assertRedirect();
        Storage::disk('local')->assertMissing($first->path);
        $announcement->refresh();
        $this->assertTrue($announcement->is_pinned);
        $this->assertCount(2, $announcement->photos);
        $this->get('/')->assertOk()->assertSee('Informasi diperbarui');
        $paths = $announcement->photos->pluck('path')->all();
        $this->delete(route('admin.announcements.destroy', $announcement))->assertRedirect();
        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
        $this->assertDatabaseCount('announcement_photos', 0);
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_photo_removal_is_scoped_to_its_own_announcement(): void
    {
        Storage::fake('local');
        $first = Announcement::factory()->create();
        $second = Announcement::factory()->create();
        $path = UploadedFile::fake()->image('private.jpg')->store('announcements', 'local');
        $photo = $second->photos()->create(['path' => $path]);
        $this->actingAs($this->admin())->put(route('admin.announcements.update', $first), $this->payload(['remove_photos' => [$photo->id]]))
            ->assertSessionHasErrors('remove_photos.0');
        $this->assertDatabaseHas('announcement_photos', ['id' => $photo->id]);
        Storage::disk('local')->assertExists($path);
    }

    public function test_invalid_files_and_photo_count_limits_are_rejected(): void
    {
        Storage::fake('local');
        $announcement = Announcement::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $path = UploadedFile::fake()->image("{$i}.jpg")->store('announcements', 'local');
            $announcement->photos()->create(['path' => $path]);
        }
        $this->actingAs($this->admin())->put(route('admin.announcements.update', $announcement), $this->payload([
            'photos' => [UploadedFile::fake()->image('extra.jpg')],
        ]))->assertSessionHasErrors('photos');
        $this->assertDatabaseCount('announcement_photos', 5);
        $this->post(route('admin.announcements.store'), $this->payload([
            'photos' => [UploadedFile::fake()->create('large.jpg', 6000, 'image/jpeg')],
        ]))->assertSessionHasErrors('photos.0');
        $this->post(route('admin.announcements.store'), $this->payload([
            'photos' => [UploadedFile::fake()->createWithContent('attack.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
        ]))->assertSessionHasErrors('photos.0');
        $this->assertDatabaseCount('announcements', 1);
    }

    public function test_malformed_upload_and_removal_fields_return_validation_errors(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin())->postJson(route('admin.announcements.store'), $this->payload([
            'remove_photos' => 'not-an-array',
        ]))->assertUnprocessable()->assertJsonValidationErrors('remove_photos');
        $this->post(route('admin.announcements.store'), $this->payload([
            'photos' => UploadedFile::fake()->image('single.jpg'),
        ]))->assertSessionHasErrors('photos');
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_scheduling_and_expiration_follow_jakarta_time(): void
    {
        $this->travelTo(now()->startOfMinute());
        $this->actingAs($this->admin())->post(route('admin.announcements.store'), $this->payload([
            'title' => 'Jadwal pengumuman', 'published_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'expires_at' => now()->addHours(2)->format('Y-m-d\TH:i'),
        ]))->assertSessionHasNoErrors();
        $this->get('/')->assertDontSee('Jadwal pengumuman');
        $this->travel(1)->hours();
        $this->get('/')->assertSee('Jadwal pengumuman');
        $this->travel(1)->hours();
        $this->get('/')->assertDontSee('Jadwal pengumuman');
    }

    public function test_invalid_dates_categories_and_empty_content_are_rejected(): void
    {
        $this->actingAs($this->admin())->post(route('admin.announcements.store'), $this->payload([
            'body' => '', 'category' => 'Tidak valid', 'published_at' => now()->format('Y-m-d\TH:i'),
            'expires_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ]))->assertSessionHasErrors(['body', 'category', 'expires_at']);
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_search_category_and_pagination_preserve_the_filters(): void
    {
        Announcement::factory()->count(11)->create(['title' => 'Agenda rapat koordinasi', 'category' => 'Kegiatan']);
        Announcement::factory()->create(['title' => 'Jaringan internal', 'category' => 'Teknologi Informasi']);
        $this->get('/?q=rapat&category=Kegiatan')->assertOk()->assertSee('Agenda rapat koordinasi')->assertDontSee('Jaringan internal')
            ->assertSee('Halaman 1 dari 2')->assertSee('q=rapat&amp;category=Kegiatan&amp;page=2', false);
        $this->get('/?q=tidakada')->assertOk()->assertSee('Pengumuman tidak ditemukan');
    }

    public function test_public_text_is_escaped_and_responses_are_not_cached(): void
    {
        $announcement = Announcement::factory()->create(['title' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(1)>']);
        $this->get(route('announcements.show', $announcement))->assertOk()->assertSee('&lt;script&gt;', false)
            ->assertSee('&lt;img', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_login_requires_admin_credentials_and_logout_revokes_access(): void
    {
        $user = User::factory()->create(['password' => Hash::make('TestingPassword123!')]);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'TestingPassword123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $admin = $this->admin();
        $admin->forceFill(['password' => Hash::make('TestingPassword123!')])->save();
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'TestingPassword123!'])->assertRedirect(route('admin.announcements.index'));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.announcements.index'))->assertOk();
        $this->post(route('admin.logout'))->assertRedirect('/admin/login');
        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_username_login_rejects_invalid_password_and_non_admin_users(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['username' => 'admin', 'password' => Hash::make('admin')])->save();

        $this->post('/admin/login', ['username' => 'admin', 'password' => 'wrong'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->post('/admin/login', ['username' => 'admin', 'password' => 'admin'])
            ->assertRedirect(route('admin.announcements.index'));
        $this->assertAuthenticatedAs($admin);
        $this->post(route('admin.logout'));

        $admin->forceFill(['is_admin' => false])->save();
        $this->post('/admin/login', ['username' => 'admin', 'password' => 'admin'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_invalid_password_attempts_are_rate_limited(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        }
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $message = session('errors')->first('email');
        $this->assertStringContainsString('Terlalu banyak percobaan', $message);
    }

    public function test_admin_creation_and_demo_commands_keep_credentials_local_and_are_repeatable(): void
    {
        Storage::fake('local');
        $this->artisan('portal:admin', ['email' => 'admin@example.test', '--generate' => true])->assertSuccessful();
        $this->assertTrue(User::where('email', 'admin@example.test')->firstOrFail()->is_admin);
        Storage::disk('local')->assertExists('initial-admin.txt');
        $this->artisan('portal:demo')->assertSuccessful();
        $this->artisan('portal:demo')->assertSuccessful();
        $this->assertDatabaseCount('announcements', 3);
        $this->assertDatabaseCount('users', 1);
    }
}
