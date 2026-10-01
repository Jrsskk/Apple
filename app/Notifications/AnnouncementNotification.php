<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public int $announcementId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'announcement',
            'title' => $this->title,
            'message' => $this->message,
            'announcement_id' => $this->announcementId,
        ];
    }
}
