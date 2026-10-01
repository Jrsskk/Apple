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
        $since = $request->filled('since')
            ? Carbon::parse($request->string('since'))
            : null;
        $data = $this->syncService->pullForStudent($request->user(), $since);

        return response()->json([
            'success' => true,
            'data' => $data,
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
            'checksum' => 'nullable|string',
            'device_id' => 'nullable|string',
        ]);

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

        $item = $this->syncService->queueItem($request->user(), $data);
        $processed = $this->syncService->processItem($item);

        return response()->json([
            'success' => $processed->status->value === 'synced',
            'message' => $processed->status->value === 'synced' ? 'Submission synchronized successfully.' : 'Sync queued or failed.',
            'data' => $processed,
        ], $processed->status->value === 'failed' ? 422 : 200);
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
