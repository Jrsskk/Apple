<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Notifications\AssignmentNotification;
use App\Notifications\GradeNotification;
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

    public function notifyGradePosted(User $user, QuizAttempt $attempt): void
    {
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

    public function notifyNewQuiz(User $student, string $quizTitle): void
    {
        $student->notify(new QuizNotification(
            title: 'New Quiz Available',
            message: "Quiz \"{$quizTitle}\" is now available.",
            quizId: 0,
        ));
    }

    public function notifyNewAssignment(User $student, string $assignmentTitle): void
    {
        $student->notify(new AssignmentNotification(
            title: 'New Assignment',
            message: "Assignment \"{$assignmentTitle}\" has been posted.",
            assignmentId: 0,
        ));
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
