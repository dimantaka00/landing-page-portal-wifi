<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;

class WebPushSender
{
    public function __construct(private ?ClientInterface $client = null) {}

    public function keys(): array
    {
        $path = config('webpush.key_file');
        if (! is_file($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function configured(): bool
    {
        $keys = $this->keys();
        $subject = $keys['subject'] ?? '';
        $validSubject = (str_starts_with($subject, 'https://') && filter_var($subject, FILTER_VALIDATE_URL))
            || (str_starts_with($subject, 'mailto:') && filter_var(substr($subject, 7), FILTER_VALIDATE_EMAIL));

        return ! empty($keys['publicKey']) && ! empty($keys['privateKey']) && (bool) $validSubject;
    }

    public function send(array $subscription, int $eventId): string
    {
        $push = new WebPush(['VAPID' => $this->keys()], ['TTL' => 300, 'urgency' => 'normal'],
            client: $this->client ?? new Client(['timeout' => 6, 'connect_timeout' => 3, 'allow_redirects' => false]),
            logger: Log::getFacadeRoot());
        $report = $push->sendOneNotification(Subscription::create([...$subscription, 'contentEncoding' => 'aes128gcm']), json_encode(['event_id' => $eventId], JSON_THROW_ON_ERROR));

        return $report->isSuccess() ? 'accepted' : ($report->isSubscriptionExpired() ? 'expired' : 'retry');
    }
}
