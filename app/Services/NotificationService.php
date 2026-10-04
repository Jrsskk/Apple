<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\LearningMaterial;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Notifications\AssignmentNotification;
use App\Notifications\GradeNotification;
use App\Notifications\MaterialNotification;
use App\Notifications\QuizNotification;
use App\Notifications\SyncNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotificationService
{
    public function notifyQuizPublished(Quiz $quiz, Collection|array $recipients): void
    {
        foreach (Collection::wrap($recipients) as $user) {
            $user->notify(new QuizNotification(
                title: 'New Quiz Available',
                message: "Quiz \"{$quiz->title}\" is now available.",
                quizId: $quiz->id,
            ));
        }
    }

    public function notifyQuizUpdated(Quiz $quiz, Collection|array $recipients): void
    {
        foreach (Collection::wrap($recipients) as $user) {
            $user->notify(new QuizNotification(
                title: 'Quiz Schedule Updated',
                message: "The schedule or details for \"{$quiz->title}\" have changed.",
                quizId: $quiz->id,
            ));
        }
    }

    public function notifyAssignmentPosted(Assignment $assignment, Collection|array $recipients): void
    {
        foreach (Collection::wrap($recipients) as $user) {
            $user->notify(new AssignmentNotification(
                title: 'New Assignment',
                message: "Assignment \"{$assignment->title}\" has been posted.",
                assignmentId: $assignment->id,
            ));
        }
    }

    public function notifyAssignmentUpdated(Assignment $assignment, Collection|array $recipients): void
    {
        foreach (Collection::wrap($recipients) as $user) {
            $user->notify(new AssignmentNotification(
                title: 'Assignment Updated',
                message: "The details or deadline for \"{$assignment->title}\" have changed.",
                assignmentId: $assignment->id,
            ));
        }
    }

    public function notifyMaterial(LearningMaterial $material, Collection|array $recipients, bool $updated = false): void
    {
        foreach (Collection::wrap($recipients) as $user) {
            $user->notify(new MaterialNotification(
                title: $updated ? 'Learning Material Updated' : 'New Learning Material',
                message: $updated
                    ? "The learning material \"{$material->title}\" has been updated."
                    : "A new learning material, \"{$material->title}\", is available.",
                materialId: $material->id,
                schoolClassId: $material->school_class_id,
                subjectId: $material->subject_id,
            ));
        }
    }

    public function notifyGradePosted(User $user, QuizAttempt $attempt): void
    {
        if (! $this->isStudentEnrolledForClassSubject(
            $user,
            (int) $attempt->quiz->school_class_id,
            (int) $attempt->quiz->subject_id,
        )) {
            return;
        }

        $user->notify(new GradeNotification(
            title: 'Grade Posted',
            message: "Your quiz attempt has been graded. Score: {$attempt->score}/{$attempt->total_points}",
            attemptId: $attempt->id,
        ));
    }

    public function notifyAnnouncement(Announcement $announcement, Collection|array $recipients): void
    {
        foreach (Collection::wrap($recipients) as $user) {
            $user->notify(new AnnouncementNotification(
                title: $announcement->title,
                message: Str::limit($announcement->message, 120),
                announcementId: $announcement->id,
            ));
        }
    }

    public function notifyAnnouncementStudents(Announcement $announcement): void
    {
        if (! in_array($announcement->target_audience, ['all', 'students'], true)) {
            return;
        }

        $recipients = $announcement->school_class_id
            ? $this->studentsForClassSubject(
                (int) $announcement->school_class_id,
                (int) $announcement->schoolClass?->subject_id,
            )
            : User::query()
                ->where('role', 'student')
                ->where('status', 'active')
                ->whereHas('enrolledClasses', fn ($query) => $query->where('school_classes.status', 'active'))
                ->get();

        $this->notifyAnnouncement($announcement, $recipients);
    }

    public function notifyAssignmentGrade(AssignmentSubmission $submission): void
    {
        $submission->loadMissing('assignment.schoolClass', 'student');
        $assignment = $submission->assignment;

        if (! $assignment->schoolClass
            || (int) $assignment->schoolClass->subject_id !== (int) $assignment->subject_id
            || ! $this->isStudentEnrolledForClassSubject(
                $submission->student,
                (int) $assignment->school_class_id,
                (int) $assignment->subject_id,
            )) {
            return;
        }

        $feedback = filled($submission->feedback) ? " Feedback: {$submission->feedback}" : '';
        $submission->student->notify(new GradeNotification(
            title: 'Grade Released',
            message: "Your grade for \"{$assignment->title}\" is available: {$submission->score}/{$assignment->max_score}.{$feedback}",
            attemptId: 0,
            assignmentId: $assignment->id,
        ));
    }

    public function studentsForClassSubject(int $classId, int $subjectId): Collection
    {
        $schoolClass = SchoolClass::query()
            ->whereKey($classId)
            ->where('subject_id', $subjectId)
            ->where('status', 'active')
            ->first();

        if (! $schoolClass) {
            return collect();
        }

        return $schoolClass->students()
            ->wherePivot('status', 'enrolled')
            ->where('users.role', 'student')
            ->where('users.status', 'active')
            ->get();
    }

    private function isStudentEnrolledForClassSubject(User $student, int $classId, int $subjectId): bool
    {
        return $this->studentsForClassSubject($classId, $subjectId)->contains('id', $student->id);
    }

    public function notifySyncStatus(User $user, string $status, ?string $message = null): void
    {
        $user->notify(new SyncNotification(
            title: 'Sync '.ucfirst($status),
            message: $message ?? "Your offline data sync is {$status}.",
        ));
    }

    public function notifySyncSuccess(User $user): void
    {
        $this->notifySyncStatus($user, 'completed', 'Your offline activities have been synchronized.');
    }

    public function notifySyncFailed(User $user, string $reason): void
    {
        $this->notifySyncStatus($user, 'failed', "Synchronization failed: {$reason}");
    }

    public function notifyGradeReleased(User $student, string $activity): void
    {
        $student->notify(new GradeNotification(
            title: 'Grade Released',
            message: "Your grade for \"{$activity}\" is now available.",
            attemptId: 0,
        ));
    }
}
