<?php

namespace App\Notifications;

use App\Channels\FcmPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GradeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public int $attemptId,
        public ?int $assignmentId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'grade',
            'title' => $this->title,
            'message' => $this->message,
            'attempt_id' => $this->attemptId,
            'assignment_id' => $this->assignmentId,
        ];
    }
}
