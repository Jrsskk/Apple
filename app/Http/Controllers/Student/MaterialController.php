<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\LearningMaterial;
use App\Models\User;
use App\Services\LearningMaterialFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialController extends Controller
{
    public function index()
    {
        $materials = LearningMaterial::whereIn('school_class_id', auth()->user()->enrolledClasses()->pluck('school_classes.id'))
            ->with('schoolClass')
            ->latest()
            ->paginate(10);

        return view('student.materials.index', compact('materials'));
    }

    public function downloadJson(LearningMaterial $material): JsonResponse
    {
        $this->authorizeAccess($material);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $material->id,
                'title' => $material->title,
                'description' => $material->description,
                'file_path' => $material->file_path,
                'file_type' => $material->file_type,
                'view_url' => route('student.materials.file', $material),
                'download_url' => route('student.materials.file', ['material' => $material, 'download' => 1]),
            ],
        ]);
    }

    public function downloadFile(Request $request, LearningMaterial $material, LearningMaterialFileService $files): StreamedResponse
    {
        $this->authorizeAccess($material);

        return $files->response($material, $request->boolean('download'));
    }

    public function secureFile(Request $request, LearningMaterial $material, LearningMaterialFileService $files): StreamedResponse
    {
        $student = User::query()->findOrFail($request->integer('student'));
        abort_unless(
            $student->isStudent()
                && $student->enrolledClasses()->where('school_classes.id', $material->school_class_id)->exists(),
            403,
        );
        abort_if($request->user() && $request->user()->id !== $student->id, 403);

        return $files->response($material, $request->boolean('download'));
    }

    private function authorizeAccess(LearningMaterial $material): void
    {
        abort_unless(
            auth()->user()->enrolledClasses()->where('school_classes.id', $material->school_class_id)->exists(),
            403
        );
    }
}
