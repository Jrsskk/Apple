<?php

namespace App\Channels;

use App\Models\User;
use App\Services\FcmService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User) {
            Log::warning('FCM notification skipped for an unsupported notifiable.', [
                'notifiable_type' => $notifiable::class,
            ]);

            return;
        }

        try {
            app(FcmService::class)->send(
                $notifiable,
                $notification->toArray($notifiable),
                $notification->id,
            );
        } catch (Throwable $exception) {
            Log::error('Unable to deliver an FCM push notification.', [
                'user_id' => $notifiable->getKey(),
                'notification_type' => $notification::class,
                'exception' => $exception,
            ]);
        }
    }
}
