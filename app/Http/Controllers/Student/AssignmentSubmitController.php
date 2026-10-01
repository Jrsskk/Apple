<?php

namespace App\Http\Controllers\Student;

use App\Enums\QuizStatus;
use App\Exceptions\SupabaseStorageException;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Services\AssignmentFileService;
use App\Services\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentSubmitController extends Controller
{
    public function __construct(private AssignmentService $assignmentService) {}

    public function show(Assignment $assignment): View
    {
        $this->authorizeAccess($assignment);
        $submission = AssignmentSubmission::firstOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => auth()->id()],
            ['sync_uuid' => Str::uuid(), 'status' => 'not_started']
        );

        return view('student.assignments.show', compact('assignment', 'submission'));
    }

    public function saveDraft(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authorizeAccess($assignment);
        $data = $request->validate(['text_response' => 'nullable|string']);
        $submission = $this->getSubmission($assignment);
        $submission->update([
            'text_response' => $data['text_response'],
            'status' => 'draft',
            'version' => $submission->version + 1,
        ]);

        return back()->with('success', 'Draft saved.');
    }

    public function submit(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authorizeAccess($assignment);
        $data = $request->validate([
            'text_response' => 'nullable|string',
            'file' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
        ]);
        $submission = $this->getSubmission($assignment);
        $status = ($assignment->deadline && now()->gt($assignment->deadline)) ? 'late' : 'submitted';
        try {
            $uploaded = $request->hasFile('file')
                ? $this->assignmentService->storeSubmissionFile($request->file('file'))
                : null;
        } catch (\RuntimeException $exception) {
            return back()->withInput()->withErrors(['file' => $exception->getMessage()]);
        }
        $oldPath = $submission->file_path;
        $oldDisk = $submission->storage_disk;
        $oldMetadata = [
            'file_name' => $submission->file_name,
            'file_type' => $submission->file_type,
            'file_size' => $submission->file_size,
        ];
        $filePath = $uploaded['path'] ?? $oldPath;

        try {
            $submission->update([
                'text_response' => $data['text_response'] ?? $submission->text_response,
                'file_path' => $filePath,
                'storage_disk' => $uploaded ? 'supabase' : $oldDisk,
                'file_name' => $uploaded['name'] ?? $submission->file_name,
                'file_type' => $uploaded['mime_type'] ?? $submission->file_type,
                'file_size' => $uploaded['size'] ?? $submission->file_size,
                'status' => $status,
                'submitted_at' => now(),
                'version' => $submission->version + 1,
            ]);
        } catch (\Throwable $exception) {
            if ($uploaded) {
                $newSubmission = clone $submission;
                $newSubmission->forceFill(['file_path' => $filePath, 'storage_disk' => 'supabase']);
                try {
                    $this->assignmentService->deleteSubmissionFile($newSubmission);
                } catch (SupabaseStorageException $cleanupException) {
                    Log::error('Unable to clean up a failed assignment submission upload.', [
                        'submission_id' => $submission->id,
                        'exception' => $cleanupException,
                    ]);
                }
            }

            throw $exception;
        }

        if ($uploaded && $oldPath) {
            try {
                $oldSubmission = clone $submission;
                $oldSubmission->forceFill(['file_path' => $oldPath, 'storage_disk' => $oldDisk]);
                $this->assignmentService->deleteSubmissionFile($oldSubmission);
            } catch (\RuntimeException $exception) {
                $submission->update([
                    'file_path' => $oldPath,
                    'storage_disk' => $oldDisk,
                    ...$oldMetadata,
                ]);
                $newSubmission = clone $submission;
                $newSubmission->forceFill(['file_path' => $filePath, 'storage_disk' => 'supabase']);
                try {
                    $this->assignmentService->deleteSubmissionFile($newSubmission);
                } catch (SupabaseStorageException $cleanupException) {
                    Log::error('Unable to clean up an orphaned assignment submission upload.', [
                        'submission_id' => $submission->id,
                        'exception' => $cleanupException,
                    ]);
                }

                return back()->withErrors(['file' => $exception->getMessage()]);
            }
        }

        return redirect()->route('student.activities.index')->with('success', 'Assignment submitted.');
    }

    public function downloadSubmission(Assignment $assignment, AssignmentFileService $files): StreamedResponse
    {
        $this->authorizeAccess($assignment);
        $submission = $this->getSubmission($assignment);
        abort_unless($submission->file_path, 404);

        return $files->response(
            $submission->file_path,
            $submission->storage_disk,
            $submission->file_name ?: basename($submission->file_path),
            true,
            $submission->file_type,
        );
    }

    public function deleteSubmissionFile(Assignment $assignment): RedirectResponse
    {
        $this->authorizeAccess($assignment);
        $submission = $this->getSubmission($assignment);
        abort_unless($submission->file_path, 404);
        abort_if(in_array($submission->status?->value ?? $submission->status, ['submitted', 'late', 'graded', 'returned'], true), 403);

        $oldPath = $submission->file_path;
        $oldDisk = $submission->storage_disk;
        $oldMetadata = [
            'file_name' => $submission->file_name,
            'file_type' => $submission->file_type,
            'file_size' => $submission->file_size,
        ];
        $submission->update([
            'file_path' => null,
            'storage_disk' => null,
            'file_name' => null,
            'file_type' => null,
            'file_size' => null,
        ]);

        try {
            $oldSubmission = clone $submission;
            $oldSubmission->forceFill(['file_path' => $oldPath, 'storage_disk' => $oldDisk]);
            $this->assignmentService->deleteSubmissionFile($oldSubmission);
        } catch (\RuntimeException $exception) {
            $submission->update([
                'file_path' => $oldPath,
                'storage_disk' => $oldDisk,
                ...$oldMetadata,
            ]);

            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return back()->with('success', 'Submission file removed.');
    }

    public function index(): View
    {
        $classIds = auth()->user()->enrolledClasses()->pluck('school_classes.id');
        $assignments = Assignment::whereIn('school_class_id', $classIds)->where('status', 'published')->with('schoolClass')->latest()->get();

        return view('student.assignments.index', compact('assignments'));
    }

    public function download(Assignment $assignment): JsonResponse
    {
        $this->authorizeAccess($assignment);
        $submission = $this->getSubmission($assignment);

        return response()->json([
            'success' => true,
            'data' => [
                'assignment' => $assignment->load(['schoolClass.subject']),
                'submission' => [
                    'sync_uuid' => $submission->sync_uuid,
                    'text_response' => $submission->text_response,
                    'version' => $submission->version,
                ],
                'downloaded_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function downloadAttachment(
        Assignment $assignment,
        AssignmentFileService $files,
    ): StreamedResponse {
        $this->authorizeAccess($assignment);
        abort_unless($assignment->attachment_path, 404);

        return $files->response(
            $assignment->attachment_path,
            $assignment->attachment_storage_disk,
            $assignment->attachment_file_name ?: basename($assignment->attachment_path),
            true,
            $assignment->attachment_file_type,
        );
    }

    private function authorizeAccess(Assignment $assignment): void
    {
        abort_unless($assignment->status === QuizStatus::Published, 404);
        abort_unless(auth()->user()->enrolledClasses()->where('school_classes.id', $assignment->school_class_id)->exists(), 403);
    }

    private function getSubmission(Assignment $assignment): AssignmentSubmission
    {
        return AssignmentSubmission::firstOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => auth()->id()],
            ['sync_uuid' => Str::uuid(), 'status' => 'not_started']
        );
    }
}
