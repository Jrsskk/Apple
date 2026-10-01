<?php

namespace App\Services;

use App\Enums\QuizStatus;
use App\Exceptions\SupabaseStorageException;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AssignmentService
{
    public function __construct(
        private AuditLogService $auditLogService,
        private SupabaseStorage $storage,
    ) {}

    public function create(User $teacher, array $data, ?UploadedFile $attachment = null): Assignment
    {
        $stored = $attachment ? $this->storeFile($attachment, 'attachments') : null;

        try {
            return DB::transaction(function () use ($teacher, $data, $stored) {
                $status = $data['status'] ?? QuizStatus::Draft->value;
                $assignment = Assignment::create([
                    ...Arr::only($data, [
                        'school_class_id', 'subject_id', 'title', 'description', 'instructions',
                        'deadline', 'max_score', 'allow_resubmit', 'status',
                    ]),
                    'teacher_id' => $teacher->id,
                    'status' => $status,
                    'attachment_path' => $stored['path'] ?? null,
                    'attachment_storage_disk' => $stored ? 'supabase' : null,
                    'attachment_file_name' => $stored['name'] ?? null,
                    'attachment_file_type' => $stored['mime_type'] ?? null,
                    'attachment_file_size' => $stored['size'] ?? null,
                ]);

                $this->auditLogService->log($teacher, 'create', 'assignment', "Created assignment: {$assignment->title}");

                return $assignment;
            });
        } catch (Throwable $exception) {
            if ($stored) {
                $this->cleanupUpload($stored['path']);
            }

            throw $exception;
        }
    }

    public function update(Assignment $assignment, array $data, ?UploadedFile $attachment = null): Assignment
    {
        $stored = $attachment ? $this->storeFile($attachment, 'attachments') : null;
        $oldPath = $assignment->attachment_path;
        $oldDisk = $assignment->attachment_storage_disk;
        $oldMetadata = [
            'attachment_file_name' => $assignment->attachment_file_name,
            'attachment_file_type' => $assignment->attachment_file_type,
            'attachment_file_size' => $assignment->attachment_file_size,
        ];

        try {
            DB::transaction(function () use ($assignment, $data, $stored) {
                $assignment->update(Arr::only($data, [
                    'title', 'description', 'instructions', 'deadline',
                    'max_score', 'allow_resubmit', 'status',
                ]));
                if ($stored) {
                    $assignment->update([
                        'attachment_path' => $stored['path'],
                        'attachment_storage_disk' => 'supabase',
                        'attachment_file_name' => $stored['name'],
                        'attachment_file_type' => $stored['mime_type'],
                        'attachment_file_size' => $stored['size'],
                    ]);
                }

                $this->auditLogService->log($assignment->teacher, 'update', 'assignment', "Updated assignment: {$assignment->title}");
            });
        } catch (Throwable $exception) {
            if ($stored) {
                $this->cleanupUpload($stored['path']);
            }

            throw $exception;
        }

        if ($stored && $oldPath) {
            try {
                $this->deleteFile($oldPath, $oldDisk);
            } catch (RuntimeException $exception) {
                $assignment->update([
                    'attachment_path' => $oldPath,
                    'attachment_storage_disk' => $oldDisk,
                    ...$oldMetadata,
                ]);
                $this->cleanupUpload($stored['path']);

                throw $exception;
            }
        }

        return $assignment->fresh();
    }

    public function delete(Assignment $assignment): void
    {
        $assignment->loadMissing('submissions');
        $files = collect([
            [$assignment->attachment_path, $assignment->attachment_storage_disk],
        ])->merge($assignment->submissions->map(
            fn (AssignmentSubmission $submission) => [$submission->file_path, $submission->storage_disk],
        ))->filter(fn (array $file) => filled($file[0]))->values();

        DB::transaction(function () use ($assignment) {
            $assignment->update([
                'attachment_path' => null,
                'attachment_storage_disk' => null,
                'attachment_file_name' => null,
                'attachment_file_type' => null,
                'attachment_file_size' => null,
            ]);
            $assignment->submissions()->update([
                'file_path' => null,
                'storage_disk' => null,
                'file_name' => null,
                'file_type' => null,
                'file_size' => null,
            ]);

            $this->auditLogService->log(
                $assignment->teacher,
                'delete',
                'assignment',
                "Deleted assignment: {$assignment->title}"
            );

            $assignment->delete();
        });

        $failures = [];
        foreach ($files as [$path, $disk]) {
            try {
                $this->deleteFile($path, $disk);
            } catch (Throwable $exception) {
                $failures[] = $exception;
                Log::error('Unable to remove an unreferenced assignment file.', [
                    'assignment_id' => $assignment->id,
                    'path' => $path,
                    'exception' => $exception,
                ]);
            }
        }

        if ($failures !== []) {
            throw new RuntimeException(
                'The assignment was deleted, but one or more files could not be removed from storage.',
                previous: $failures[0],
            );
        }
    }

    /**
     * @return array{path: string, name: string, mime_type: string, size: int}
     */
    public function storeSubmissionFile(UploadedFile $file): array
    {
        return $this->storeFile($file, 'submissions');
    }

    public function deleteSubmissionFile(AssignmentSubmission $submission): void
    {
        $this->deleteFile($submission->file_path, $submission->storage_disk);
    }

    public function deleteStoredSubmissionFile(string $path): void
    {
        $this->deleteFile($path, 'supabase');
    }

    public function deleteAssignmentAttachment(string $path, ?string $disk): void
    {
        $this->deleteFile($path, $disk);
    }

    /**
     * @return array{path: string, name: string, mime_type: string, size: int}
     */
    private function storeFile(UploadedFile $file, string $directory): array
    {
        return $this->storage->upload('assignments', $file, $directory);
    }

    private function deleteFile(?string $path, ?string $disk = null): void
    {
        if (! $path) {
            return;
        }

        if ($disk === 'supabase') {
            $this->storage->delete('assignments', $path);
        } elseif (Storage::disk('public')->exists($path)) {
            if (! Storage::disk('public')->delete($path)) {
                throw new RuntimeException('The assignment file could not be deleted from local storage.');
            }
        }
    }

    private function cleanupUpload(string $path): void
    {
        try {
            $this->storage->delete('assignments', $path);
        } catch (SupabaseStorageException $exception) {
            Log::error('Unable to clean up an orphaned Supabase assignment file.', [
                'path' => $path,
                'exception' => $exception,
            ]);
        }
    }
}
