<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\PortalNotifications;
use App\Services\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PortalNotificationController extends Controller
{
    public function feed(Request $request, PortalNotifications $notifications, WebPushSender $sender): JsonResponse
    {
        $input = $request->validate(['after' => ['nullable', 'integer', 'min:0']]);
        $notifications->prepare();
        $head = (int) DB::table('portal_notifications')->max('id');
        $after = isset($input['after']) ? (int) $input['after'] : null;
        $reset = $after !== null && $after > $head;
        $events = [];
        $cursor = $head;
        if ($after !== null && ! $reset) {
            $rows = DB::table('portal_notifications')->where('id', '>', $after)->where('id', '<=', $head)->orderBy('id')->limit(50)->get();
            foreach ($rows as $event) {
                if ($announcement = $notifications->announcementFor($event)) {
                    $events[] = $notifications->payload($announcement, $event->id);
                }
            }
            $cursor = $rows->last()?->id ?? $head;
        }
        $latest = $after === null || $reset
            ? Announcement::published()->where('notify_users', true)->orderByDesc('published_at')->orderByDesc('id')->first()
            : null;

        return response()->json([
            'cursor' => $cursor, 'has_more' => $cursor < $head, 'reset' => $reset,
            'events' => $events, 'latest' => $latest ? $notifications->payload($latest) : null,
            'push' => ['configured' => $sender->configured(), 'public_key' => $sender->configured() ? $sender->keys()['publicKey'] : null],
            'csrf_token' => csrf_token(),
        ]);
    }

    public function item(int $notification, PortalNotifications $notifications): JsonResponse
    {
        $event = DB::table('portal_notifications')->find($notification);
        abort_unless($event, 404);
        $announcement = $notifications->announcementFor($event);
        abort_unless($announcement, 404);

        return response()->json($notifications->payload($announcement, $event->id));
    }

    public function subscribe(Request $request, PortalNotifications $notifications, WebPushSender $sender): JsonResponse
    {
        abort_unless($sender->configured(), 503, 'Notifikasi perangkat belum dikonfigurasi pada server.');
        $data = $request->validate([
            'token' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{32,128}$/'],
            'endpoint' => ['required', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:100'],
            'keys.auth' => ['required', 'string', 'max:30'],
        ]);
        $parts = parse_url($data['endpoint']);
        $host = strtolower($parts['host'] ?? '');
        $allowed = in_array($host, ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'], true)
            || preg_match('/^[a-z0-9-]+\.notify\.windows\.com$/', $host);
        $publicKey = base64_decode(strtr($data['keys']['p256dh'], '-_', '+/'), true);
        $auth = base64_decode(strtr($data['keys']['auth'], '-_', '+/'), true);
        if (! $allowed || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (($parts['port'] ?? 443) !== 443) || strlen($parts['path'] ?? '') < 2
            || $publicKey === false || strlen($publicKey) !== 65 || ord($publicKey[0]) !== 4
            || $auth === false || strlen($auth) !== 16) {
            throw ValidationException::withMessages(['endpoint' => 'Langganan notifikasi tidak valid atau layanan push belum didukung.']);
        }
        $notifications->prepare();
        $hash = hash('sha256', $data['endpoint']);
        $tokenHash = hash('sha256', $data['token']);
        DB::transaction(function () use ($data, $hash, $tokenHash): void {
            $existing = DB::table('push_subscriptions')->where('endpoint_hash', $hash)->first();
            abort_if($existing && ! hash_equals($existing->token_hash, $tokenHash), 409, 'Langganan sudah terdaftar. Nonaktifkan notifikasi browser lalu aktifkan kembali.');
            $values = [
                'token_hash' => $tokenHash,
                'details' => Crypt::encryptString(json_encode(['endpoint' => $data['endpoint'], 'keys' => $data['keys']], JSON_THROW_ON_ERROR)),
                'updated_at' => now(),
            ];
            if ($existing) {
                DB::table('push_subscriptions')->where('id', $existing->id)->update($values);
            } else {
                DB::table('push_subscriptions')->insert([...$values, 'endpoint_hash' => $hash, 'created_at' => now(),
                    'last_notification_id' => (int) DB::table('portal_notifications')->max('id')]);
            }
        }, 3);

        return response()->json(['subscribed' => true], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{32,128}$/']]);
        DB::table('push_subscriptions')->where('token_hash', hash('sha256', $data['token']))->delete();

        return response()->json(['subscribed' => false]);
    }
}
