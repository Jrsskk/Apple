<?php

namespace App\Notifications;

use App\Channels\FcmPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaterialNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public int $materialId,
        public int $schoolClassId,
        public int $subjectId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'material',
            'title' => $this->title,
            'message' => $this->message,
            'material_id' => $this->materialId,
            'school_class_id' => $this->schoolClassId,
            'subject_id' => $this->subjectId,
        ];
    }
}
