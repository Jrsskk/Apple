<?php

namespace App\Jobs;

use App\Models\Assignment;
use App\Models\Quiz;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use App\Notifications\QuizNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class SendDeadlineReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $type,
        public int $entityId,
    ) {}

    public function handle(): void
    {
        if ($this->type === 'quiz') {
            $this->remindQuiz(Quiz::with('schoolClass.students')->find($this->entityId));

            return;
        }

        if ($this->type === 'assignment') {
            $this->remindAssignment(Assignment::with('schoolClass.students')->find($this->entityId));
        }
    }

    private function remindQuiz(?Quiz $quiz): void
    {
        if (! $quiz || ! $quiz->deadline) {
            return;
        }

        $recipients = $this->studentsWithoutSubmission(
            $quiz->schoolClass?->students ?? collect(),
            $quiz->attempts()->pluck('student_id')
        );

        foreach ($recipients as $student) {
            $student->notify(new QuizNotification(
                title: 'Quiz Deadline Reminder',
                message: "Quiz \"{$quiz->title}\" is due soon.",
                quizId: $quiz->id,
            ));
        }
    }

    private function remindAssignment(?Assignment $assignment): void
    {
        if (! $assignment || ! $assignment->deadline) {
            return;
        }

        $recipients = $this->studentsWithoutSubmission(
            $assignment->schoolClass?->students ?? collect(),
            $assignment->submissions()->pluck('student_id')
        );

        foreach ($recipients as $student) {
            $student->notify(new AssignmentNotification(
                title: 'Assignment Deadline Reminder',
                message: "Assignment \"{$assignment->title}\" is due soon.",
                assignmentId: $assignment->id,
            ));
        }
    }

    private function studentsWithoutSubmission(Collection $students, Collection $submittedUserIds): Collection
    {
        return $students->filter(fn (User $student) => ! $submittedUserIds->contains($student->id));
    }
}
