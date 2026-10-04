<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Quiz;
use App\Models\SyncQueue;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SyncController extends Controller
{
    public function __construct(private SyncService $syncService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['nullable', 'date'],
        ]);
        $since = isset($validated['since']) ? Carbon::parse($validated['since']) : null;
        $data = $this->syncService->pullForStudent($request->user(), $since);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $studentId = $request->user()->id;
        $counts = SyncQueue::query()
            ->where('user_id', $studentId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'success' => true,
            'data' => [
                'pending' => (int) ($counts['pending'] ?? 0),
                'syncing' => (int) ($counts['syncing'] ?? 0),
                'synced' => (int) ($counts['synced'] ?? 0),
                'failed' => (int) ($counts['failed'] ?? 0),
                'last_synced_at' => SyncQueue::where('user_id', $studentId)->max('synced_at'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sync_uuid' => 'required|uuid',
            'entity_type' => 'required|in:quiz_attempt,assignment_submission',
            'entity_id' => 'nullable|integer',
            'action' => 'required|in:create,update',
            'payload' => 'required|array',
            'payload.quiz_id' => 'required_if:entity_type,quiz_attempt|integer|exists:quizzes,id',
            'payload.assignment_id' => 'required_if:entity_type,assignment_submission|integer|exists:assignments,id',
            'payload.answers' => 'present_if:entity_type,quiz_attempt|array',
            'payload.answers.*' => 'required_if:entity_type,quiz_attempt|array:question_id,answer_text,selected_options',
            'payload.answers.*.question_id' => 'required_if:entity_type,quiz_attempt|integer|distinct',
            'payload.answers.*.answer_text' => 'nullable|string',
            'payload.answers.*.selected_options' => 'nullable|array',
            'payload.answers.*.selected_options.*' => 'integer',
            'payload.attempt_number' => 'sometimes|integer|min:1',
            'payload.started_at' => 'sometimes|date',
            'payload.completed_at' => 'sometimes|date',
            'payload.submitted_at' => 'sometimes|date',
            'payload.integrity_log' => 'sometimes|array',
            'payload.text_response' => 'nullable|string',
            'payload.version' => 'sometimes|integer|min:1',
            'payload.status' => 'sometimes|in:submitted,late',
            'checksum' => 'nullable|string',
            'device_id' => 'nullable|string',
        ]);

        $allowedPayloadKeys = $data['entity_type'] === 'quiz_attempt'
            ? ['quiz_id', 'attempt_number', 'started_at', 'completed_at', 'submitted_at', 'integrity_log', 'answers']
            : ['assignment_id', 'text_response', 'file_path', 'status', 'version', 'submitted_at'];
        if (array_diff(array_keys($data['payload']), $allowedPayloadKeys) !== []) {
            throw ValidationException::withMessages([
                'payload' => 'The submission contains unsupported fields.',
            ]);
        }
        if (isset($data['payload']['file_path']) && $data['payload']['file_path'] !== null) {
            throw ValidationException::withMessages([
                'payload.file_path' => 'Upload assignment files through the assignment submission endpoint.',
            ]);
        }

        $resourceId = $data['entity_type'] === 'quiz_attempt'
            ? (int) $data['payload']['quiz_id']
            : (int) $data['payload']['assignment_id'];

        if (isset($data['entity_id']) && (int) $data['entity_id'] !== $resourceId) {
            throw ValidationException::withMessages([
                'entity_id' => 'The entity ID must match the submitted resource.',
            ]);
        }

        $resource = $data['entity_type'] === 'quiz_attempt'
            ? Quiz::findOrFail($resourceId)
            : Assignment::findOrFail($resourceId);
        $this->authorize($data['entity_type'] === 'quiz_attempt' ? 'view' : 'submit', $resource);

        $data['entity_id'] = $resourceId;
        $item = $this->syncService->queueItem($request->user(), $data);
        $processed = $this->syncService->processItem($item);
        $synced = $processed->status->value === 'synced';
        $processing = $processed->status->value === 'syncing';
        $failureLog = $synced || $processing
            ? null
            : $processed->logs()->latest('id')->first();
        $retryable = (bool) ($failureLog?->metadata['retryable'] ?? false);
        $message = $synced
            ? 'Submission synchronized successfully.'
            : ($processing
                ? 'Submission is already being synchronized.'
                : ($retryable
                    ? 'Temporary server error. The submission remains queued and will retry automatically.'
                    : ($failureLog?->message ?? 'Submission synchronization failed.')));

        return response()->json([
            'success' => $synced,
            'message' => $message,
            'data' => $processed,
        ], $synced ? 200 : ($processing ? 202 : ($retryable ? 503 : 422)));
    }

    public function syncNow(Request $request): JsonResponse
    {
        $results = $this->syncService->processPendingForUser($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Processed '.count($results).' sync item(s).',
            'data' => ['processed' => count($results)],
        ]);
    }
}
