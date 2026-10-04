<?php

namespace App\Notifications;

use App\Channels\FcmPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuizNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public int $quizId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quiz',
            'title' => $this->title,
            'message' => $this->message,
            'quiz_id' => $this->quizId,
        ];
    }
}
