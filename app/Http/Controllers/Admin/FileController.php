<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\LearningMaterial;
use App\Models\User;
use App\Services\AssignmentFileService;
use App\Services\GoogleDriveStorage;
use App\Services\LearningMaterialFileService;
use App\Services\SupabaseStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function index(): Response
    {
        $materials = LearningMaterial::with(['schoolClass', 'uploader'])
            ->latest()
            ->paginate(15, ['*'], 'materials_page');

        $submissions = AssignmentSubmission::whereNotNull('file_path')
            ->with(['student', 'assignment'])
            ->latest()
            ->paginate(15, ['*'], 'submissions_page');

        $storageSize = $this->calculateStorageSize();

        return Inertia::render('Admin/Files/Index', [
            'materials' => $materials,
            'submissions' => $submissions,
            'storageSize' => $storageSize,
        ]);
    }

    public function downloadMaterial(LearningMaterial $material, LearningMaterialFileService $files): StreamedResponse
    {
        return $files->response($material, true);
    }

    public function downloadSubmission(AssignmentSubmission $submission, AssignmentFileService $files): StreamedResponse
    {
        abort_unless($submission->file_path, 404);

        return $files->response(
            $submission->file_path,
            $submission->storage_disk,
            $submission->file_name ?: basename($submission->file_path),
            true,
            $submission->file_type,
        );
    }

    public function destroyMaterial(
        LearningMaterial $material,
        GoogleDriveStorage $drive,
        SupabaseStorage $storage,
    ): RedirectResponse {

        $material->delete();
        try {
            if ($material->storage_disk === 'supabase' && $material->file_path) {
                $storage->delete('materials', $material->file_path);
            } elseif ($material->google_drive_file_id) {
                $drive->delete($material->google_drive_file_id);
            } elseif ($material->file_path) {
                Storage::disk('local')->delete($material->file_path);
            }
        } catch (\RuntimeException $exception) {
            $material->restore();

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'File deleted.');
    }

    private function calculateStorageSize(): int
    {
        $size = 0;
        $publicDisk = Storage::disk('public');

        foreach ($publicDisk->allFiles() as $file) {
            $size += $publicDisk->size($file);
        }

        $size += (int) LearningMaterial::sum('file_size');
        $size += (int) Assignment::where('attachment_storage_disk', 'supabase')->sum('attachment_file_size');
        $size += (int) AssignmentSubmission::where('storage_disk', 'supabase')->sum('file_size');
        $size += (int) User::where('profile_image_storage_disk', 'supabase')->sum('profile_image_file_size');

        $privateDisk = Storage::disk('local');
        foreach (LearningMaterial::withTrashed()->pluck('file_path') as $path) {
            if ($path && $privateDisk->exists($path)) {
                $size += $privateDisk->size($path);
            }
        }

        return $size;
    }
}
