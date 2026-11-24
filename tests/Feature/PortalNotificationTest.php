<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use App\Services\PortalNotifications;
use App\Services\WebPushSender;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

class PortalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyPath = storage_path('app/private/test-webpush-'.Str::uuid().'.json');
        file_put_contents($this->keyPath, json_encode([...VAPID::createVapidKeys(), 'subject' => 'https://portal.example.test'], JSON_THROW_ON_ERROR));
        config(['webpush.key_file' => $this->keyPath]);
    }

    protected function tearDown(): void
    {
        @unlink($this->keyPath);
        parent::tearDown();
    }

    private function subscription(string $token = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'): array
    {
        $keys = VAPID::createVapidKeys();

        return ['token' => $token, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'keys' => ['p256dh' => $keys['publicKey'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')]];
    }

    public function test_feed_excludes_drafts_future_expired_deleted_and_opted_out_content(): void
    {
        Announcement::factory()->create(['title' => 'Visible', 'notification_revision' => 1]);
        foreach ([
            ['status' => 'draft'], ['published_at' => now()->addDay()],
            ['expires_at' => now()->subMinute()], ['delete_at' => now()->subMinute()], ['notify_users' => false],
        ] as $fields) {
            Announcement::factory()->create([...$fields, 'title' => 'Hidden', 'notification_revision' => 1]);
        }
        $response = $this->getJson(route('notifications.feed', ['after' => 0]));
        $response->assertOk()->assertJsonCount(1, 'events')->assertJsonPath('events.0.title', 'Visible')
            ->assertDontSee('Hidden')->assertDontSee('privateKey')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('portal_notifications', 1);
        $event = DB::table('portal_notifications')->first();
        $this->getJson(route('notifications.item', $event->id))->assertOk();
        Announcement::whereKey($event->announcement_id)->update(['status' => 'draft']);
        $this->getJson(route('notifications.item', $event->id))->assertNotFound();
    }

    public function test_scheduled_publication_is_delivered_after_existing_cursor_and_same_second_edits_get_new_ids(): void
    {
        $this->travelTo(now()->startOfMinute());
        $scheduled = Announcement::factory()->create(['notification_revision' => 1, 'published_at' => now()->addMinute()]);
        Announcement::factory()->create(['notification_revision' => 1]);
        $cursor = $this->getJson(route('notifications.feed', ['after' => 0]))->json('cursor');
        $this->travel(2)->minutes();
        $response = $this->getJson(route('notifications.feed', ['after' => $cursor]));
        $response->assertJsonCount(1, 'events')->assertJsonPath('events.0.announcement_id', $scheduled->id);
        $cursor = $response->json('cursor');

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $payload = ['title' => 'Updated immediately', 'body' => 'Current content', 'category' => 'Maintenance', 'status' => 'published', 'notify_users' => 1];
        $this->put(route('admin.announcements.update', $scheduled), $payload)->assertSessionHasNoErrors();
        $this->getJson(route('notifications.feed', ['after' => $cursor]))->assertJsonCount(1, 'events')->assertJsonPath('events.0.title', 'Updated immediately');
        $this->assertSame(2, $scheduled->fresh()->notification_revision);
        $this->put(route('admin.announcements.update', $scheduled), [...$payload, 'notify_users' => 0])->assertSessionHasNoErrors();
        $this->getJson(route('notifications.feed', ['after' => 0]))->assertDontSee('Updated immediately');
    }

    public function test_bootstrap_reset_and_pagination_preserve_cursor_without_exposing_removed_content(): void
    {
        $announcement = Announcement::factory()->create(['title' => 'Welcome']);
        $this->getJson(route('notifications.feed'))->assertJsonPath('latest.title', 'Welcome')->assertJsonCount(0, 'events');
        for ($index = 0; $index < 55; $index++) {
            DB::table('portal_notifications')->insert(['announcement_id' => null, 'revision' => 1, 'created_at' => now()]);
        }
        $this->getJson(route('notifications.feed', ['after' => 0]))->assertJsonPath('cursor', 50)->assertJsonPath('has_more', true)->assertJsonCount(0, 'events');
        $this->getJson(route('notifications.feed', ['after' => 50]))->assertJsonPath('cursor', 55)->assertJsonPath('has_more', false);
        $this->getJson(route('notifications.feed', ['after' => 999]))->assertJsonPath('reset', true)->assertJsonPath('latest.announcement_id', $announcement->id);
    }

    public function test_subscription_is_encrypted_cannot_be_hijacked_and_can_be_revoked_by_its_token(): void
    {
        $data = $this->subscription();
        $this->postJson(route('notifications.subscribe'), $data)->assertCreated();
        $entry = DB::table('push_subscriptions')->first();
        $this->assertStringNotContainsString($data['endpoint'], $entry->details);
        $this->assertStringNotContainsString($data['keys']['auth'], $entry->details);
        $this->postJson(route('notifications.subscribe'), [...$data, 'token' => str_repeat('b', 32)])->assertStatus(409);
        $this->deleteJson(route('notifications.unsubscribe'), ['token' => str_repeat('b', 32)])->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->deleteJson(route('notifications.unsubscribe'), ['token' => $data['token']])->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_subscription_rejects_private_or_untrusted_urls_and_invalid_keys(): void
    {
        $data = $this->subscription();
        foreach (['http://fcm.googleapis.com/send/test', 'https://127.0.0.1/private', 'https://fcm.googleapis.com.evil.example/send/x',
            'https://fcm.googleapis.com:444/send/x', 'https://user@fcm.googleapis.com/send/x', 'https://fcm.googleapis.com/send/x#fragment'] as $endpoint) {
            $this->postJson(route('notifications.subscribe'), [...$data, 'endpoint' => $endpoint])->assertUnprocessable();
        }
        $this->postJson(route('notifications.subscribe'), [...$data, 'keys' => ['auth' => 'invalid', 'p256dh' => 'invalid']])->assertUnprocessable();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_push_job_sends_only_new_visible_events_and_does_not_repeat_successful_events(): void
    {
        $announcement = Announcement::factory()->create(['notification_revision' => 1]);
        app(PortalNotifications::class)->prepare();
        $this->postJson(route('notifications.subscribe'), $this->subscription())->assertCreated();
        $announcement->forceFill(['notification_revision' => 2])->save();
        $this->mock(WebPushSender::class, function ($mock): void {
            $mock->shouldReceive('configured')->andReturn(true);
            $mock->shouldReceive('send')->once()->andReturn('accepted');
        });
        $this->artisan('portal:send-notifications')->assertSuccessful();
        $this->artisan('portal:send-notifications')->assertSuccessful();
        $this->assertDatabaseCount('portal_notifications', 2);
        $this->assertSame(2, DB::table('push_subscriptions')->value('last_notification_id'));
        $this->assertNotNull(DB::table('push_subscriptions')->value('last_success_at'));
    }

    public function test_push_failure_is_retried_with_backoff_and_expired_subscriptions_are_removed(): void
    {
        $this->postJson(route('notifications.subscribe'), $this->subscription())->assertCreated();
        Announcement::factory()->create(['notification_revision' => 1]);
        $this->mock(WebPushSender::class, function ($mock): void {
            $mock->shouldReceive('configured')->andReturn(true);
            $mock->shouldReceive('send')->twice()->andReturn('retry', 'expired');
        });
        $this->artisan('portal:send-notifications')->assertSuccessful();
        $this->assertSame(0, DB::table('push_subscriptions')->value('last_notification_id'));
        $this->assertSame(1, DB::table('push_subscriptions')->value('failure_count'));
        $this->artisan('portal:send-notifications')->assertSuccessful();
        $this->travel(21)->seconds();
        $this->artisan('portal:send-notifications')->assertSuccessful();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_real_web_push_encrypts_payload_and_signs_vapid_without_external_network(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(201), new Response(410)]));
        $stack->push(Middleware::history($history));
        $sender = new WebPushSender(new Client(['handler' => $stack]));
        $data = $this->subscription();
        $this->assertSame('accepted', $sender->send($data, 42));
        $request = $history[0]['request'];
        $this->assertSame('aes128gcm', $request->getHeaderLine('Content-Encoding'));
        $this->assertStringStartsWith('vapid ', $request->getHeaderLine('Authorization'));
        $this->assertStringNotContainsString('event_id', (string) $request->getBody());
        $this->assertSame('expired', $sender->send($data, 43));
    }

    public function test_push_setup_preserves_keys_and_rejects_invalid_subject(): void
    {
        $hash = hash('sha256', json_encode(json_decode(file_get_contents($this->keyPath), true), JSON_THROW_ON_ERROR));
        $this->artisan('portal:push-setup')->assertSuccessful();
        $this->assertTrue(hash_equals($hash, hash('sha256', json_encode(json_decode(file_get_contents($this->keyPath), true), JSON_THROW_ON_ERROR))));
        $this->artisan('portal:push-setup', ['--subject' => 'http://not-secure.test'])->assertFailed();
        $this->assertTrue(hash_equals($hash, hash('sha256', json_encode(json_decode(file_get_contents($this->keyPath), true), JSON_THROW_ON_ERROR))));
    }
}
