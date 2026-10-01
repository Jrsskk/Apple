<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LearningMaterial;
use App\Services\LearningMaterialFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classIds = $user->enrolledClasses()->pluck('school_classes.id');

        $materials = LearningMaterial::withTrashed()
            ->whereIn('school_class_id', $classIds)
            ->with(['schoolClass.subject', 'subject', 'uploader:id,first_name,middle_name,last_name,email'])
            ->latest('updated_at')
            ->get()
            ->filter(fn (LearningMaterial $material) => $material->deleted_at === null)
            ->values();

        return $this->success($materials);
    }

    public function show(Request $request, LearningMaterial $material): JsonResponse
    {
        abort_unless(
            $request->user()->enrolledClasses()->where('school_classes.id', $material->school_class_id)->exists(),
            403,
        );

        $material->load(['schoolClass.subject', 'subject', 'uploader:id,first_name,middle_name,last_name,email']);

        return $this->success($material);
    }

    public function download(Request $request, LearningMaterial $material): JsonResponse
    {
        $this->authorizeAccess($request, $material);

        $material->load(['schoolClass.subject', 'subject', 'uploader:id,first_name,middle_name,last_name,email']);

        return $this->success([
            'material' => $material,
            'view_url' => $this->signedFileUrl($material, false),
            'download_url' => $this->signedFileUrl($material, true),
            'file_url' => $material->file_url,
            'downloaded_at' => now()->toIso8601String(),
        ], 'Material ready for offline use');
    }

    public function file(Request $request, LearningMaterial $material, LearningMaterialFileService $files): StreamedResponse
    {
        $this->authorizeAccess($request, $material);

        return $files->response($material, $request->boolean('download'));
    }

    private function authorizeAccess(Request $request, LearningMaterial $material): void
    {
        abort_unless(
            $request->user()->enrolledClasses()->where('school_classes.id', $material->school_class_id)->exists(),
            403,
        );
    }

    private function signedFileUrl(LearningMaterial $material, bool $download): string
    {
        return URL::temporarySignedRoute(
            'student.materials.secure-file',
            now()->addMinutes(10),
            ['material' => $material, 'student' => request()->user()->id, 'download' => $download ? 1 : 0],
        );
    }
}
