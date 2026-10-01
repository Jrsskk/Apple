<?php

namespace App\Events;

use App\Models\Quiz;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizPublished implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Quiz $quiz,
        public int $studentId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("student.{$this->studentId}")];
    }

    public function broadcastAs(): string
    {
        return 'quiz.published';
    }

    public function broadcastWith(): array
    {
        return [
            'quiz_id' => $this->quiz->id,
            'title' => $this->quiz->title,
            'school_class_id' => $this->quiz->school_class_id,
            'starts_at' => $this->quiz->starts_at?->toIso8601String(),
            'deadline' => $this->quiz->deadline?->toIso8601String(),
        ];
    }
}