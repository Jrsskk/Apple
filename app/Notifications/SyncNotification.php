<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SyncNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'sync',
            'title' => $this->title,
            'message' => $this->message,
        ];
    }
}
