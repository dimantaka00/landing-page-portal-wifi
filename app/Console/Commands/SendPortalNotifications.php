<?php

namespace App\Console\Commands;

use App\Services\PortalNotifications;
use App\Services\WebPushSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPortalNotifications extends Command
{
    protected $signature = 'portal:send-notifications';

    protected $description = 'Siapkan pop-up dan kirim pembaruan ke perangkat yang berlangganan';

    public function handle(PortalNotifications $notifications, WebPushSender $sender): int
    {
        $notifications->prepare();
        if (! $sender->configured()) {
            $this->info('Pop-up portal siap. Web Push menunggu konfigurasi alamat server.');

            return self::SUCCESS;
        }
        $deadline = microtime(true) + 30;
        $accepted = 0;
        $subscriptions = DB::table('push_subscriptions')
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('updated_at')->orderBy('id')->limit(100)->get();
        foreach ($subscriptions as $subscription) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $events = DB::table('portal_notifications')->where('id', '>', $subscription->last_notification_id)->orderBy('id')->limit(25)->get();
            foreach ($events as $event) {
                if (microtime(true) >= $deadline) {
                    break;
                }
                if (! $notifications->announcementFor($event)) {
                    DB::table('push_subscriptions')->where('id', $subscription->id)->update(['last_notification_id' => $event->id]);

                    continue;
                }
                try {
                    $details = json_decode(Crypt::decryptString($subscription->details), true, 512, JSON_THROW_ON_ERROR);
                    $result = $sender->send($details, $event->id);
                } catch (Throwable $error) {
                    Log::warning('Pengiriman Web Push akan dicoba ulang.', ['subscription_id' => $subscription->id, 'error_type' => $error::class]);
                    $result = 'retry';
                }
                if ($result === 'expired') {
                    DB::table('push_subscriptions')->where('id', $subscription->id)->delete();
                    break;
                }
                if ($result !== 'accepted') {
                    $failures = min(10, $subscription->failure_count + 1);
                    DB::table('push_subscriptions')->where('id', $subscription->id)->update([
                        'failure_count' => $failures, 'next_attempt_at' => now()->addSeconds(min(600, 10 * (2 ** $failures))), 'updated_at' => now(),
                    ]);
                    break;
                }
                $accepted++;
                DB::table('push_subscriptions')->where('id', $subscription->id)->update([
                    'last_notification_id' => $event->id, 'failure_count' => 0, 'next_attempt_at' => null,
                    'last_success_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('push_subscriptions')->where('id', $subscription->id)->update(['updated_at' => now()]);
        }
        $this->info("Notifikasi diterima layanan push: {$accepted}. Penerimaan perangkat bergantung browser dan jaringan.");

        return self::SUCCESS;
    }
}
