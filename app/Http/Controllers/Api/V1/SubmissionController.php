<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SupabaseStorageException;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\AssignmentService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionController extends ApiController
{
    public function __construct(
        private SyncService $syncService,
        private AssignmentService $assignmentService,
    ) {}

    public function submitQuiz(Request $request): JsonResponse
    {
        $data = $request->validate([
            'quiz_id' => ['required', 'exists:quizzes,id'],
            'sync_uuid' => ['required', 'uuid'],
            'checksum' => ['nullable', 'string'],
            'device_id' => ['nullable', 'string'],
            'attempt_number' => ['nullable', 'integer'],
            'started_at' => ['nullable', 'date'],
            'submitted_at' => ['nullable', 'date'],
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer', 'distinct'],
            'answers.*.answer_text' => ['nullable', 'string'],
            'answers.*.selected_options' => ['nullable', 'array'],
            'answers.*.selected_options.*' => ['nullable'],
        ]);

        $quiz = Quiz::findOrFail($data['quiz_id']);
        $this->authorize('view', $quiz);

        if (QuizAttempt::where('sync_uuid', $data['sync_uuid'])
            ->where('student_id', $request->user()->id)
            ->exists()) {
            $attempt = QuizAttempt::where('sync_uuid', $data['sync_uuid'])
                ->where('student_id', $request->user()->id)
                ->with('answers')
                ->first();

            return $this->success(['attempt' => $attempt, 'duplicate' => true], 'Submission already received');
        }

        $item = $this->syncService->queueItem($request->user(), [
            'sync_uuid' => $data['sync_uuid'],
            'entity_type' => 'quiz_attempt',
            'entity_id' => $quiz->id,
            'action' => 'create',
            'device_id' => $data['device_id'] ?? null,
            'checksum' => $data['checksum'] ?? null,
            'payload' => [
                'quiz_id' => $data['quiz_id'],
                'attempt_number' => $data['attempt_number'] ?? 1,
                'started_at' => $data['started_at'] ?? now()->toIso8601String(),
                'completed_at' => $data['submitted_at'] ?? now()->toIso8601String(),
                'submitted_at' => $data['submitted_at'] ?? now()->toIso8601String(),
                'answers' => $data['answers'],
            ],
        ]);

        $processed = $this->syncService->processItem($item);

        if ($processed->status->value === 'failed') {
            return $this->error('Sync failed', 422);
        }

        $attempt = QuizAttempt::where('sync_uuid', $data['sync_uuid'])->with('answers')->first();

        return $this->success(['attempt' => $attempt, 'sync_item' => $processed], 'Quiz submitted successfully', 201);
    }

    public function submitAssignment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assignment_id' => ['required', 'exists:assignments,id'],
            'sync_uuid' => ['required', 'uuid'],
            'checksum' => ['nullable', 'string'],
            'device_id' => ['nullable', 'string'],
            'text_response' => ['nullable', 'string'],
            'status' => ['nullable', 'in:submitted,late'],
            'version' => ['nullable', 'integer', 'min:1'],
            'submitted_at' => ['nullable', 'date'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        $assignment = Assignment::findOrFail($data['assignment_id']);
        $this->authorize('submit', $assignment);

        $existing = AssignmentSubmission::where('sync_uuid', $data['sync_uuid'])
            ->where('student_id', $request->user()->id)
            ->first();
        if ($existing) {
            abort_unless($existing->assignment_id === $assignment->id, 403);
            $existingStatus = $existing->status instanceof \BackedEnum
                ? $existing->status->value
                : (string) $existing->status;
            if ($existing->submitted_at || in_array($existingStatus, ['submitted', 'late', 'graded', 'returned'], true)) {
                return $this->success(
                    ['submission' => $existing, 'duplicate' => true],
                    'Submission already received',
                );
            }
        }

        try {
            $uploaded = $request->hasFile('file')
                ? $this->assignmentService->storeSubmissionFile($request->file('file'))
                : null;
        } catch (SupabaseStorageException $exception) {
            return $this->error($exception->getMessage(), 503);
        }

        $item = $this->syncService->queueItem($request->user(), [
            'sync_uuid' => $data['sync_uuid'],
            'entity_type' => 'assignment_submission',
            'entity_id' => $data['assignment_id'],
            'action' => 'create',
            'device_id' => $data['device_id'] ?? null,
            'checksum' => $data['checksum'] ?? null,
            'payload' => [
                'assignment_id' => $data['assignment_id'],
                'text_response' => $data['text_response'] ?? null,
                'file_path' => $uploaded['path'] ?? null,
                'storage_disk' => $uploaded ? 'supabase' : null,
                'file_name' => $uploaded['name'] ?? null,
                'file_type' => $uploaded['mime_type'] ?? null,
                'file_size' => $uploaded['size'] ?? null,
                'status' => $data['status'] ?? 'submitted',
                'version' => $data['version'] ?? 1,
                'submitted_at' => $data['submitted_at'] ?? now()->toIso8601String(),
            ],
        ]);

        $processed = $this->syncService->processItem($item);

        if ($processed->status->value === 'failed') {
            return $this->error('Sync failed', 422);
        }

        $submission = AssignmentSubmission::where('sync_uuid', $data['sync_uuid'])->first();

        return $this->success(['submission' => $submission, 'sync_item' => $processed], 'Assignment submitted successfully', 201);
    }
}
