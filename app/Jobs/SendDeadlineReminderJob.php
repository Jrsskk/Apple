<?php

namespace App\Jobs;

use App\Models\Assignment;
use App\Models\Quiz;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use App\Notifications\QuizNotification;
use App\Services\NotificationService;
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

    public function handle(NotificationService $notifications): void
    {
        if ($this->type === 'quiz') {
            $this->remindQuiz(Quiz::with('schoolClass')->find($this->entityId), $notifications);

            return;
        }

        if ($this->type === 'assignment') {
            $this->remindAssignment(Assignment::with('schoolClass')->find($this->entityId), $notifications);
        }
    }

    private function remindQuiz(?Quiz $quiz, NotificationService $notifications): void
    {
        if (! $quiz || ! $quiz->deadline) {
            return;
        }

        $recipients = $this->studentsWithoutSubmission(
            $notifications->studentsForClassSubject((int) $quiz->school_class_id, (int) $quiz->subject_id),
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

    private function remindAssignment(?Assignment $assignment, NotificationService $notifications): void
    {
        if (! $assignment || ! $assignment->deadline) {
            return;
        }

        $recipients = $this->studentsWithoutSubmission(
            $notifications->studentsForClassSubject((int) $assignment->school_class_id, (int) $assignment->subject_id),
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
