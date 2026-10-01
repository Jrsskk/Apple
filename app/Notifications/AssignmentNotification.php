<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AssignmentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public int $assignmentId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'assignment',
            'title' => $this->title,
            'message' => $this->message,
            'assignment_id' => $this->assignmentId,
        ];
    }
}
