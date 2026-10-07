<?php

namespace App\Services;

use App\Enums\SyncStatus;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\LearningMaterial;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\SyncLog;
use App\Models\SyncQueue;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SyncService
{
    public function __construct(
        private GradingService $gradingService,
        private NotificationService $notificationService,
        private QuizService $quizService,
    ) {}

    public function queueItem(User $user, array $data): SyncQueue
    {
        $expectedEntityId = (int) ($data['entity_id'] ?? match ($data['entity_type']) {
            'quiz_attempt' => $data['payload']['quiz_id'],
            'assignment_submission' => $data['payload']['assignment_id'],
        });
        $item = SyncQueue::firstOrCreate(
            ['sync_uuid' => $data['sync_uuid']],
            [
                'user_id' => $user->id,
                'device_id' => $data['device_id'] ?? null,
                'entity_type' => $data['entity_type'],
                'entity_id' => $expectedEntityId,
                'action' => $data['action'],
                'payload' => $data['payload'],
                'checksum' => hash('sha256', json_encode($this->canonicalize($data['payload']))),
                'status' => SyncStatus::Pending,
            ]
        );

        abort_unless($item->user_id === $user->id, 403);
        if (
            $item->entity_type !== $data['entity_type']
            || (int) $item->entity_id !== $expectedEntityId
            || $item->action !== $data['action']
            || ! hash_equals(
                hash('sha256', json_encode($this->canonicalize($item->payload ?? []))),
                hash('sha256', json_encode($this->canonicalize($data['payload']))),
            )
        ) {
            throw ValidationException::withMessages([
                'sync_uuid' => 'This sync ID was already used for a different submission.',
            ]);
        }

        return $item;
    }

    public function processItem(SyncQueue $item): SyncQueue
    {
        $claimed = DB::transaction(function () use ($item): ?SyncQueue {
            $locked = SyncQueue::query()->lockForUpdate()->find($item->id);
            if (! $locked || $locked->status === SyncStatus::Synced) {
                return null;
            }
            if (
                $locked->status === SyncStatus::Syncing
                && $locked->updated_at?->gt(now()->subMinutes(5))
            ) {
                return null;
            }

            $locked->update([
                'status' => SyncStatus::Syncing,
                'attempts' => $locked->attempts + 1,
            ]);

            return $locked->fresh();
        });
        if (! $claimed) {
            return $item->fresh();
        }
        $item = $claimed;

        try {
            DB::transaction(function () use ($item) {
                match ($item->entity_type) {
                    'quiz_attempt' => $this->syncQuizAttempt($item),
                    'assignment_submission' => $this->syncAssignmentSubmission($item),
                    default => throw new \InvalidArgumentException("Unknown entity type: {$item->entity_type}"),
                };
            });

            $item->update(['status' => SyncStatus::Synced, 'synced_at' => now()]);
            $this->log($item, 'sync', 'Synchronized successfully.', 'success');
            $this->notificationService->notifySyncSuccess($item->user);
        } catch (\Throwable $e) {
            $item->update(['status' => SyncStatus::Failed]);
            $retryable = ! (
                $e instanceof ValidationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof ModelNotFoundException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
            );
            $this->log($item, 'sync', $e->getMessage(), 'failed', [
                'exception' => $e::class,
                'retryable' => $retryable,
            ]);
            $this->notificationService->notifySyncFailed($item->user, $e->getMessage());
        }

        return $item->fresh();
    }

    public function processPendingForUser(User $user, int $limit = 50): array
    {
        $processed = [];
        SyncQueue::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereIn('status', [SyncStatus::Pending, SyncStatus::Failed])
                    ->orWhere(function ($stale) {
                        $stale->where('status', SyncStatus::Syncing)
                            ->where('updated_at', '<=', now()->subMinutes(5));
                    });
            })
            ->orderBy('created_at')
            ->limit($limit)
            ->each(function (SyncQueue $item) use (&$processed) {
                $processed[] = $this->processItem($item);
            });

        return $processed;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    public function pullForStudent(User $user, ?Carbon $since = null): array
    {
        $classIds = $user->enrolledClasses()->pluck('school_classes.id');
        $now = now();
        $changed = static fn ($query) => $since ? $query->where('updated_at', '>', $since) : $query;

        $newlyEnrolledClassIds = $since
            ? $user->enrolledClasses()->wherePivot('enrolled_at', '>=', $since)->pluck('school_classes.id')->all()
            : [];

        $classQuery = $user->enrolledClasses()->with([
            'subject',
            'teacher:id,first_name,middle_name,last_name,email',
            'materials' => fn ($query) => $query->forConsistentClassSubject(),
        ]);
        $classes = $classQuery->get()->map(fn (SchoolClass $class) => [
            'id' => $class->id,
            'name' => $class->name,
            'section' => $class->section,
            'grade_level' => $class->grade_level,
            'class_code' => $class->class_code,
            'schedule' => $class->schedule,
            'room' => $class->room,
            'status' => $class->status,
            'description' => $class->description ?? null,
            'subject' => $class->subject,
            'teacher' => [
                'id' => $class->teacher?->id,
                'name' => $class->teacher?->full_name,
                'email' => $class->teacher?->email,
            ],
            'materials' => $class->materials ?? [],
            'created_at' => $class->created_at?->toIso8601String(),
            'updated_at' => $class->updated_at?->toIso8601String(),
        ]);

        $quizQuery = Quiz::withTrashed()->whereIn('school_class_id', $classIds)
            ->where('status', 'published');
        if ($since) {
            $quizQuery->where(function ($q) use ($since, $newlyEnrolledClassIds) {
                $q->where('updated_at', '>', $since);
                if (! empty($newlyEnrolledClassIds)) {
                    $q->orWhereIn('school_class_id', $newlyEnrolledClassIds);
                }
            });
        }
        $quizzes = $quizQuery
            ->with(['schoolClass.subject', 'teacher:id,first_name,middle_name,last_name,email', 'questions.options'])
            ->get()
            ->map(function (Quiz $quiz) {
                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'instructions' => $quiz->instructions,
                    'subject_id' => $quiz->subject_id,
                    'school_class_id' => $quiz->school_class_id,
                    'teacher_id' => $quiz->teacher_id,
                    'teacher' => [
                        'id' => $quiz->teacher?->id,
                        'name' => $quiz->teacher?->full_name,
                        'email' => $quiz->teacher?->email,
                    ],
                    'subject' => $quiz->schoolClass?->subject,
                    'school_class' => [
                        'id' => $quiz->schoolClass?->id,
                        'name' => $quiz->schoolClass?->name,
                        'section' => $quiz->schoolClass?->section,
                    ],
                    'starts_at' => $quiz->starts_at?->toIso8601String(),
                    'deadline' => $quiz->deadline?->toIso8601String(),
                    'duration_minutes' => $quiz->duration_minutes,
                    'total_points' => $quiz->total_points,
                    'max_attempts' => $quiz->max_attempts,
                    'passing_score' => $quiz->passing_score,
                    'status' => $quiz->status?->value ?? $quiz->status,
                    'questions' => $this->quizService->buildStudentQuestionSet($quiz),
                    'created_at' => $quiz->created_at?->toIso8601String(),
                    'updated_at' => $quiz->updated_at?->toIso8601String(),
                    'deleted_at' => $quiz->deleted_at?->toIso8601String(),
                ];
            });

        $assignmentQuery = Assignment::withTrashed()->whereIn('school_class_id', $classIds)
            ->where('status', 'published');
        if ($since) {
            $assignmentQuery->where(function ($q) use ($since, $newlyEnrolledClassIds) {
                $q->where('updated_at', '>', $since);
                if (! empty($newlyEnrolledClassIds)) {
                    $q->orWhereIn('school_class_id', $newlyEnrolledClassIds);
                }
            });
        }
        $assignments = $assignmentQuery
            ->with(['schoolClass.subject', 'teacher:id,first_name,middle_name,last_name,email'])
            ->get()
            ->map(function (Assignment $assignment) {
                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'instructions' => $assignment->instructions,
                    'subject_id' => $assignment->subject_id,
                    'school_class_id' => $assignment->school_class_id,
                    'teacher_id' => $assignment->teacher_id,
                    'teacher' => [
                        'id' => $assignment->teacher?->id,
                        'name' => $assignment->teacher?->full_name,
                        'email' => $assignment->teacher?->email,
                    ],
                    'subject' => $assignment->schoolClass?->subject,
                    'school_class' => [
                        'id' => $assignment->schoolClass?->id,
                        'name' => $assignment->schoolClass?->name,
                        'section' => $assignment->schoolClass?->section,
                    ],
                    'attachment_path' => $assignment->attachment_path,
                    'posted_at' => $assignment->posted_at?->toIso8601String(),
                    'starts_at' => $assignment->starts_at?->toIso8601String(),
                    'deadline' => $assignment->deadline?->toIso8601String(),
                    'due_at' => $assignment->due_at,
                    'max_score' => $assignment->max_score,
                    'allow_resubmit' => (bool) $assignment->allow_resubmit,
                    'status' => $assignment->status?->value ?? $assignment->status,
                    'created_at' => $assignment->created_at?->toIso8601String(),
                    'updated_at' => $assignment->updated_at?->toIso8601String(),
                    'deleted_at' => $assignment->deleted_at?->toIso8601String(),
                ];
            });

        $announcementQuery = Announcement::query()
            ->where(fn ($query) => $query->whereNull('school_class_id')->orWhereIn('school_class_id', $classIds));
        if ($since) {
            $announcementQuery->where(function ($q) use ($since, $newlyEnrolledClassIds) {
                $q->where('updated_at', '>', $since);
                if (! empty($newlyEnrolledClassIds)) {
                    $q->orWhereIn('school_class_id', $newlyEnrolledClassIds);
                }
            });
        }
        $announcements = $announcementQuery->with(['author:id,first_name,last_name', 'schoolClass'])
            ->get()
            ->filter(fn ($announcement) => $user->can('view', $announcement));

        $materialQuery = LearningMaterial::withTrashed()
            ->whereIn('school_class_id', $classIds)
            ->forConsistentClassSubject();
        if ($since) {
            $materialQuery->where(function ($q) use ($since, $newlyEnrolledClassIds) {
                $q->where('updated_at', '>', $since);
                if (! empty($newlyEnrolledClassIds)) {
                    $q->orWhereIn('school_class_id', $newlyEnrolledClassIds);
                }
            });
        }
        $materials = $materialQuery
            ->with(['schoolClass.subject', 'subject', 'uploader:id,first_name,middle_name,last_name,email'])
            ->get()
            ->map(function (LearningMaterial $material) {
                return [
                    'id' => $material->id,
                    'title' => $material->title,
                    'description' => $material->description,
                    'school_class_id' => $material->school_class_id,
                    'subject_id' => $material->subject_id,
                    'uploaded_by' => $material->uploaded_by,
                    'uploader' => [
                        'id' => $material->uploader?->id,
                        'name' => $material->uploader?->full_name,
                        'email' => $material->uploader?->email,
                    ],
                    'file_path' => $material->file_path,
                    'file_type' => $material->file_type,
                    'file_url' => $material->file_url,
                    'download_url' => $material->download_url,
                    'class_id' => $material->school_class_id,
                    'created_at' => $material->created_at?->toIso8601String(),
                    'updated_at' => $material->updated_at?->toIso8601String(),
                    'deleted_at' => $material->deleted_at?->toIso8601String(),
                ];
            });

        $grades = $changed($user->quizAttempts()->where('status', 'graded')->with(['quiz.schoolClass.subject']))
            ->get()->map(fn ($attempt) => [
                'type' => 'quiz', 'id' => $attempt->id, 'title' => $attempt->quiz->title,
                'class' => $attempt->quiz->schoolClass?->display_name, 'score' => $attempt->score,
                'max_score' => $attempt->total_points, 'graded_at' => $attempt->submitted_at,
                'updated_at' => $attempt->updated_at,
            ]);
        $grades = $grades->concat($changed($user->assignmentSubmissions()->whereNotNull('score')
            ->with(['assignment.schoolClass.subject']))->get()->map(fn ($submission) => [
                'type' => 'assignment', 'id' => $submission->id, 'title' => $submission->assignment->title,
                'class' => $submission->assignment->schoolClass?->display_name, 'score' => $submission->score,
                'max_score' => $submission->assignment->max_score, 'graded_at' => $submission->submitted_at,
                'feedback' => $submission->feedback, 'updated_at' => $submission->updated_at,
            ]));

        $visible = function ($model) use ($now) {
            $deletedAt = is_array($model) ? ($model['deleted_at'] ?? null) : ($model->deleted_at ?? null);
            $status = is_array($model) ? ($model['status'] ?? null) : ($model->status?->value ?? $model->status);

            if ($deletedAt !== null && $deletedAt !== false) {
                return false;
            }

            if ($model instanceof Announcement || (is_array($model) && ($model['published_at'] ?? null) !== null)) {
                $publishedAt = is_array($model) ? ($model['published_at'] ?? null) : $model->published_at;

                return $publishedAt !== null && $publishedAt <= $now;
            }

            return $status === 'published';
        };
        $materialVisible = function ($model) {
            $deletedAt = is_array($model) ? ($model['deleted_at'] ?? null) : ($model->deleted_at ?? null);

            return $deletedAt === null || $deletedAt === false;
        };
        $data = [
            'classes' => $classes->values(),
            'quizzes' => $quizzes->filter($visible)->values(),
            'assignments' => $assignments->filter($visible)->values(),
            'announcements' => $announcements->filter($visible)->values(),
            'materials' => $materials->filter($materialVisible)->values(),
            'grades' => $grades->values(),
            'deleted' => [
                'quizzes' => $quizzes->reject($visible)->pluck('id')->values(),
                'assignments' => $assignments->reject($visible)->pluck('id')->values(),
                'announcements' => $announcements->reject($visible)->pluck('id')->values(),
                'materials' => $materials->reject($materialVisible)->pluck('id')->values(),
            ],
            'synced_at' => $now->toIso8601String(),
        ];
        Log::info('Student sync pull', [
            'student_id' => $user->id,
            'class_ids' => $classIds->all(),
            'since' => $since?->toIso8601String(),
            'quizzes' => $data['quizzes']->count(),
            'assignments' => $data['assignments']->count(),
            'announcements' => $data['announcements']->count(),
            'materials' => $data['materials']->count(),
            'grades' => $data['grades']->count(),
            'response_keys' => array_keys($data),
        ]);

        return $data;
    }

    private function syncQuizAttempt(SyncQueue $item): void
    {
        $payload = $item->payload;
        $quiz = Quiz::findOrFail($payload['quiz_id']);
        Gate::forUser($item->user)->authorize('view', $quiz);

        $existing = QuizAttempt::where('sync_uuid', $item->sync_uuid)->first();

        if ($existing) {
            abort_unless(
                $existing->student_id === $item->user_id && $existing->quiz_id === $quiz->id,
                403
            );

            if (! in_array($existing->status->value ?? $existing->status, ['submitted', 'graded'], true)) {
                [$startedAt, $submittedAt] = $this->quizSubmissionTimes($quiz, $payload, $existing);
                $existing->update([
                    'started_at' => $startedAt,
                    'completed_at' => $submittedAt,
                    'submitted_at' => $submittedAt,
                    'status' => 'submitted',
                    'integrity_log' => $payload['integrity_log'] ?? $existing->integrity_log,
                    'synced_at' => now(),
                ]);

                foreach ($payload['answers'] ?? [] as $answerData) {
                    abort_unless($quiz->questions()->whereKey($answerData['question_id'])->exists(), 422);
                    $existing->answers()->updateOrCreate(
                        ['quiz_question_id' => $answerData['question_id']],
                        [
                            'answer_text' => $answerData['answer_text'] ?? null,
                            'selected_options' => $answerData['selected_options'] ?? null,
                        ]
                    );
                }

                $this->gradingService->autoGradeAttempt($existing);
            }

            return;
        }

        $attemptCount = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $item->user_id)
            ->count();
        abort_if($attemptCount >= $quiz->max_attempts, 422, 'Maximum quiz attempts reached.');
        abort_unless((int) ($payload['attempt_number'] ?? 1) === $attemptCount + 1, 422, 'Invalid quiz attempt number.');
        [$startedAt, $submittedAt] = $this->quizSubmissionTimes($quiz, $payload);

        $attempt = QuizAttempt::create([
            'quiz_id' => $payload['quiz_id'],
            'student_id' => $item->user_id,
            'attempt_number' => $attemptCount + 1,
            'sync_uuid' => $item->sync_uuid,
            'started_at' => $startedAt,
            'completed_at' => $submittedAt,
            'submitted_at' => $submittedAt,
            'synced_at' => now(),
            'device_id' => $item->device_id,
            'integrity_log' => $payload['integrity_log'] ?? [],
            'status' => 'submitted',
        ]);

        foreach ($payload['answers'] ?? [] as $answerData) {
            abort_unless($quiz->questions()->whereKey($answerData['question_id'])->exists(), 422);
            QuizAnswer::create([
                'quiz_attempt_id' => $attempt->id,
                'quiz_question_id' => $answerData['question_id'],
                'answer_text' => $answerData['answer_text'] ?? null,
                'selected_options' => $answerData['selected_options'] ?? null,
            ]);
        }

        $this->gradingService->autoGradeAttempt($attempt);
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function quizSubmissionTimes(Quiz $quiz, array $payload, ?QuizAttempt $attempt = null): array
    {
        $startedAt = $attempt?->started_at ?? Carbon::parse($payload['started_at'] ?? now());
        $submittedAt = Carbon::parse($payload['submitted_at'] ?? now());

        if ($submittedAt->isFuture() || $submittedAt->lt($startedAt)) {
            throw ValidationException::withMessages([
                'submitted_at' => 'The quiz submission time is invalid.',
            ]);
        }

        $deadline = $startedAt->copy()->addMinutes($quiz->duration_minutes);
        if ($quiz->deadline && $quiz->deadline->lt($deadline)) {
            $deadline = $quiz->deadline;
        }

        if ($submittedAt->gt($deadline) || ($quiz->deadline && $submittedAt->gt($quiz->deadline))) {
            throw ValidationException::withMessages([
                'submitted_at' => 'The quiz submission was received after the allowed time.',
            ]);
        }

        return [$startedAt, $submittedAt];
    }

    private function syncAssignmentSubmission(SyncQueue $item): void
    {
        $payload = $item->payload;
        $assignment = Assignment::findOrFail($payload['assignment_id']);
        Gate::forUser($item->user)->authorize('submit', $assignment);
        $existing = AssignmentSubmission::where('sync_uuid', $item->sync_uuid)->first();

        if ($existing) {
            abort_unless(
                $existing->student_id === $item->user_id && $existing->assignment_id === $assignment->id,
                403
            );

            if (($payload['version'] ?? 1) > $existing->version) {
                $existing->update([
                    'text_response' => $payload['text_response'] ?? $existing->text_response,
                    'file_path' => $payload['file_path'] ?? $existing->file_path,
                    'storage_disk' => $payload['storage_disk'] ?? $existing->storage_disk,
                    'file_name' => $payload['file_name'] ?? $existing->file_name,
                    'file_type' => $payload['file_type'] ?? $existing->file_type,
                    'file_size' => $payload['file_size'] ?? $existing->file_size,
                    'status' => $payload['status'] ?? $existing->status?->value ?? $existing->status,
                    'version' => $payload['version'],
                    'submitted_at' => $payload['submitted_at'] ?? now(),
                    'synced_at' => now(),
                ]);
            }

            return;
        }

        AssignmentSubmission::create([
            'assignment_id' => $payload['assignment_id'],
            'student_id' => $item->user_id,
            'sync_uuid' => $item->sync_uuid,
            'text_response' => $payload['text_response'] ?? null,
            'file_path' => $payload['file_path'] ?? null,
            'storage_disk' => $payload['storage_disk'] ?? null,
            'file_name' => $payload['file_name'] ?? null,
            'file_type' => $payload['file_type'] ?? null,
            'file_size' => $payload['file_size'] ?? null,
            'status' => $payload['status'] ?? 'submitted',
            'submitted_at' => $payload['submitted_at'] ?? now(),
            'synced_at' => now(),
            'version' => $payload['version'] ?? 1,
            'device_id' => $item->device_id,
        ]);
    }

    private function log(
        SyncQueue $item,
        string $action,
        string $message,
        string $status,
        ?array $metadata = null,
    ): void
    {
        SyncLog::create([
            'user_id' => $item->user_id,
            'sync_queue_id' => $item->id,
            'action' => $action,
            'message' => $message,
            'status' => $status,
            'metadata' => $metadata,
        ]);
    }

    public static function generateDeviceId(): string
    {
        return Str::uuid()->toString();
    }
}
