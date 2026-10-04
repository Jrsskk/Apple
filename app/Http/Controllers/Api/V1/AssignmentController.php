<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Assignment;
use App\Services\AssignmentFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classIds = $user->enrolledClasses()->pluck('school_classes.id');

        $assignments = Assignment::whereIn('school_class_id', $classIds)
            ->where('status', 'published')
            ->with(['schoolClass.subject', 'teacher:id,first_name,middle_name,last_name,email'])
            ->latest()
            ->get();

        return $this->success($assignments);
    }

    public function show(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $assignment->load(['schoolClass.subject', 'teacher']);

        return $this->success($assignment);
    }

    public function download(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        return $this->success([
            'assignment' => $assignment->load(['schoolClass.subject', 'teacher']),
            'attachment_url' => $assignment->attachment_path
                ? route('api.v1.assignments.attachment', $assignment)
                : null,
            'downloaded_at' => now()->toIso8601String(),
        ], 'Assignment data ready for offline use');
    }

    public function attachment(
        Request $request,
        Assignment $assignment,
        AssignmentFileService $files,
    ): StreamedResponse {
        $this->authorize('view', $assignment);
        abort_unless($assignment->attachment_path, 404);

        return $files->response(
            $assignment->attachment_path,
            $assignment->attachment_storage_disk,
            $assignment->attachment_file_name ?: basename($assignment->attachment_path),
            true,
            $assignment->attachment_file_type,
        );
    }
}
