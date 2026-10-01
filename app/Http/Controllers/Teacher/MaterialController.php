<?php

namespace App\Http\Controllers\Teacher;

use App\Exceptions\SupabaseStorageException;
use App\Http\Controllers\Controller;
use App\Models\LearningMaterial;
use App\Models\SchoolClass;
use App\Services\GoogleDriveStorage;
use App\Services\LearningMaterialFileService;
use App\Services\SupabaseStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialController extends Controller
{
    public function index(Request $request): Response
    {
        $classIds = $request->user()->taughtClasses()->pluck('id');

        $materials = LearningMaterial::whereIn('school_class_id', $classIds)
            ->with(['schoolClass', 'subject'])
            ->latest()
            ->paginate(15);

        $classes = $request->user()->taughtClasses()->with('subject')->get();

        return Inertia::render('Teacher/Materials/Index', [
            'materials' => $materials,
            'classes' => $classes,
        ]);
    }

    public function store(Request $request, SupabaseStorage $storage): RedirectResponse
    {
        $data = $request->validate($this->validationRules());
        $class = $request->user()->taughtClasses()->find($data['school_class_id']);

        abort_unless(
            ($class && $class->subject_id == $data['subject_id'])
                || ($request->user()->isAdmin()
                    && SchoolClass::whereKey($data['school_class_id'])
                        ->where('subject_id', $data['subject_id'])
                        ->exists()),
            403,
        );

        $file = $request->file('file');
        try {
            $uploaded = $storage->upload('materials', $file);
        } catch (SupabaseStorageException $exception) {
            return back()->withInput()->withErrors(['file' => $exception->getMessage()]);
        }

        try {
            LearningMaterial::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'file_path' => $uploaded['path'],
                'storage_disk' => 'supabase',
                'original_file_name' => $uploaded['name'],
                'file_type' => $uploaded['mime_type'],
                'file_size' => $uploaded['size'],
                'subject_id' => $data['subject_id'],
                'school_class_id' => $data['school_class_id'],
                'uploaded_by' => $request->user()->id,
            ]);
        } catch (\Throwable $exception) {
            try {
                $storage->delete('materials', $uploaded['path']);
            } catch (SupabaseStorageException $cleanupException) {
                Log::error('Unable to remove an orphaned Supabase material after a database failure.', [
                    'path' => $uploaded['path'],
                    'exception' => $cleanupException,
                ]);
            }

            throw $exception;
        }

        return back()->with('success', 'Material uploaded.');
    }

    public function replace(Request $request, LearningMaterial $material, SupabaseStorage $storage): RedirectResponse
    {
        $this->authorizeMaterial($request, $material);
        $data = $request->validate($this->validationRules());
        abort_unless(
            (int) $material->school_class_id === (int) $data['school_class_id']
                && (int) $material->subject_id === (int) $data['subject_id'],
            422,
            'A replacement must remain in the same class and subject.',
        );

        try {
            $uploaded = $storage->upload('materials', $request->file('file'));
        } catch (SupabaseStorageException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        $oldFile = $material->only([
            'file_path', 'storage_disk', 'google_drive_file_id',
            'original_file_name', 'file_type', 'file_size',
        ]);

        try {
            $material->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'file_path' => $uploaded['path'],
                'storage_disk' => 'supabase',
                'google_drive_file_id' => null,
                'original_file_name' => $uploaded['name'],
                'file_type' => $uploaded['mime_type'],
                'file_size' => $uploaded['size'],
            ]);
        } catch (\Throwable $exception) {
            try {
                $storage->delete('materials', $uploaded['path']);
            } catch (SupabaseStorageException $cleanupException) {
                Log::error('Unable to remove an orphaned replacement from Supabase Storage.', [
                    'path' => $uploaded['path'],
                    'exception' => $cleanupException,
                ]);
            }

            throw $exception;
        }

        try {
            $oldMaterial = clone $material;
            $oldMaterial->forceFill($oldFile);
            $this->deleteMaterialFile($oldMaterial, $storage);
        } catch (\RuntimeException $exception) {
            try {
                $material->update($oldFile);
            } catch (\Throwable $restoreException) {
                Log::critical('Unable to restore a material record after file replacement cleanup failed.', [
                    'material_id' => $material->id,
                    'exception' => $restoreException,
                ]);
            }

            try {
                $storage->delete('materials', $uploaded['path']);
            } catch (SupabaseStorageException $cleanupException) {
                Log::error('Unable to clean up a failed replacement upload.', [
                    'material_id' => $material->id,
                    'path' => $uploaded['path'],
                    'exception' => $cleanupException,
                ]);
            }

            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return back()->with('success', 'Material replaced.');
    }

    public function destroy(
        Request $request,
        LearningMaterial $material,
        GoogleDriveStorage $drive,
        SupabaseStorage $storage,
    ): RedirectResponse {
        $this->authorizeMaterial($request, $material);

        $material->delete();
        try {
            $this->deleteMaterialFile($material, $storage, $drive);
        } catch (\RuntimeException $exception) {
            $material->restore();

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Material deleted.');
    }

    public function download(
        Request $request,
        LearningMaterial $material,
        LearningMaterialFileService $files,
    ): StreamedResponse {
        $this->authorizeMaterial($request, $material);

        return $files->response($material, $request->boolean('download', true));
    }

    private function authorizeMaterial(Request $request, LearningMaterial $material): void
    {
        abort_unless(
            $request->user()->isAdmin()
                || $request->user()->taughtClasses()->whereKey($material->school_class_id)->exists(),
            403,
        );
    }

    private function deleteMaterialFile(
        LearningMaterial $material,
        SupabaseStorage $storage,
        ?GoogleDriveStorage $drive = null,
    ): void {
        if ($material->storage_disk === 'supabase' && $material->file_path) {
            $storage->delete('materials', $material->file_path);
        } elseif ($material->google_drive_file_id) {
            ($drive ?? app(GoogleDriveStorage::class))->delete($material->google_drive_file_id);
        } elseif ($material->file_path && Storage::disk('local')->exists($material->file_path)) {
            if (! Storage::disk('local')->delete($material->file_path)) {
                throw new \RuntimeException('The material file could not be deleted from local storage.');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validationRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'school_class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,odt,rtf,txt,ppt,pptx,pps,ppsx,xls,xlsx,csv,mp4,mov,avi,webm,jpg,jpeg,png,gif,webp',
        ];
    }
}
