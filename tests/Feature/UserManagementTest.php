<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['is_admin' => true, 'can_manage_users' => true]);
    }

    private function payload(array $extra = []): array
    {
        return array_replace([
            'name' => 'Pengelola Baru', 'username' => 'pengelola.baru', 'email' => 'pengelola@example.test',
            'role' => 'editor', 'is_active' => 1, 'password' => 'Test-Password-2026!',
            'password_confirmation' => 'Test-Password-2026!',
        ], $extra);
    }

    public function test_only_active_managers_can_open_settings_and_create_users(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $editor = User::factory()->create(['is_admin' => true, 'can_manage_users' => false]);
        $this->actingAs($editor)->get(route('admin.users.index'))->assertForbidden();
        $this->post(route('admin.users.store'), $this->payload(['role' => 'admin']))->assertForbidden();
        $this->assertDatabaseCount('users', 1);

        $this->actingAs($this->manager())->get(route('admin.users.create'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_created_editor_can_login_but_cannot_manage_other_accounts(): void
    {
        $this->actingAs($this->manager())->post(route('admin.users.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
        $account = User::where('username', 'pengelola.baru')->firstOrFail();
        $this->assertTrue($account->is_admin);
        $this->assertFalse($account->can_manage_users);
        $this->assertTrue(Hash::check('Test-Password-2026!', $account->password));
        $this->assertNotSame('Test-Password-2026!', $account->password);
        $this->post(route('admin.logout'));
        $this->post(route('login.store'), ['username' => 'pengelola.baru', 'password' => 'Test-Password-2026!'])->assertRedirect();
        $this->assertAuthenticatedAs($account);
        $this->get(route('admin.announcements.index'))->assertOk()->assertDontSee('Pengaturan pengguna');
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_usernames_and_emails_are_normalized_and_duplicates_are_rejected(): void
    {
        $this->actingAs($this->manager());
        $this->post(route('admin.users.store'), $this->payload(['username' => ' Pengelola.Baru ', 'email' => 'PENGELOLA@EXAMPLE.TEST']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['username' => 'pengelola.baru', 'email' => 'pengelola@example.test']);
        $this->post(route('admin.users.store'), $this->payload(['username' => ['invalid'], 'email' => ['invalid']]))->assertSessionHasErrors(['username', 'email']);
        $this->post(route('admin.users.store'), $this->payload())->assertSessionHasErrors(['username', 'email']);
        $this->post(route('admin.users.store'), $this->payload(['username' => 'bad name', 'email' => 'new@example.test', 'password' => 'short', 'password_confirmation' => 'mismatch']))->assertSessionHasErrors(['username', 'password']);
        $this->post(route('admin.users.store'), $this->payload(['username' => 'new-user', 'email' => 'new@example.test', 'role' => 'owner']))->assertSessionHasErrors('role');
    }

    public function test_edit_profile_keeps_password_when_empty_and_does_not_accept_privileged_extra_fields(): void
    {
        $admin = $this->manager();
        $account = User::factory()->create(['is_admin' => true, 'username' => 'editor']);
        $hash = $account->password;
        $this->actingAs($admin)->put(route('admin.users.update', $account), $this->payload([
            'username' => 'editor', 'email' => $account->email, 'password' => '', 'password_confirmation' => '',
            'can_manage_users' => true,
        ]))->assertSessionHasNoErrors();
        $this->assertSame($hash, $account->fresh()->password);
        $this->assertFalse($account->fresh()->can_manage_users);
        $this->get(route('admin.users.edit', $account))->assertOk()->assertDontSee($hash);
    }

    public function test_disabled_accounts_cannot_login_and_old_sessions_lose_access_to_dashboard_and_private_photos(): void
    {
        Storage::fake('local');
        $admin = $this->manager();
        $account = User::factory()->create(['is_admin' => true, 'username' => 'editor']);
        $announcement = Announcement::factory()->create(['status' => 'draft']);
        $photo = $announcement->photos()->create(['path' => 'announcements/private.jpg']);
        Storage::disk('local')->put($photo->path, 'private photo');
        $this->actingAs($account)->withSession(['admin_auth_version' => 0])->get(route('photos.show', $photo))->assertOk();

        $this->actingAs($admin)->put(route('admin.users.update', $account), $this->payload([
            'username' => 'editor', 'email' => $account->email, 'is_active' => 0,
        ]))->assertSessionHasNoErrors();
        $account->refresh();
        $this->assertFalse($account->is_active);
        $this->assertSame(1, $account->auth_version);
        $this->actingAs($account)->withSession(['admin_auth_version' => 0])->get(route('photos.show', $photo))->assertNotFound();
        $this->get(route('admin.announcements.index'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.store'), ['username' => 'editor', 'password' => 'Test-Password-2026!'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_password_reset_revokes_old_active_sessions_and_accepts_new_login(): void
    {
        $account = User::factory()->create(['is_admin' => true, 'username' => 'editor']);
        $this->actingAs($this->manager())->put(route('admin.users.update', $account), $this->payload([
            'username' => 'editor', 'email' => $account->email,
        ]))->assertSessionHasNoErrors();
        $account->refresh();
        $this->assertSame(1, $account->auth_version);
        $this->actingAs($account)->withSession(['admin_auth_version' => 0])->get(route('admin.announcements.index'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['username' => 'editor', 'password' => 'Test-Password-2026!'])->assertRedirect();
        $this->get(route('admin.announcements.index'))->assertOk();
    }

    public function test_current_manager_cannot_disable_or_demote_their_own_account(): void
    {
        $admin = $this->manager();
        $this->actingAs($admin)->put(route('admin.users.update', $admin), $this->payload(['email' => $admin->email, 'role' => 'editor']))->assertSessionHasErrors('role');
        $this->put(route('admin.users.update', $admin), $this->payload(['email' => $admin->email, 'role' => 'admin', 'is_active' => 0]))->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->can_manage_users);
    }

    public function test_cli_admins_have_management_access_and_user_list_escapes_content(): void
    {
        Storage::fake('local');
        $this->artisan('portal:admin', ['email' => 'manager@example.test', '--generate' => true])->assertSuccessful();
        $admin = User::where('email', 'manager@example.test')->firstOrFail();
        $this->assertTrue($admin->can_manage_users);
        $this->assertTrue($admin->is_active);
        $this->assertSame(1, $admin->auth_version);
        $admin->forceFill(['name' => '<script>alert(1)</script>'])->save();
        $this->actingAs($admin)->withSession(['admin_auth_version' => 1])->get(route('admin.users.index'))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('admin.users.index', ['q' => 'not-found']))->assertSee('Pengguna tidak ditemukan');
    }
}
