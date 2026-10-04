<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\LearningMaterial;
use App\Models\User;
use App\Services\LearningMaterialFileService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'subject_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $enrolledClasses = $request->user()
            ->enrolledClasses()
            ->with('subject:id,name')
            ->get(['school_classes.id', 'school_classes.subject_id']);
        $classIds = $enrolledClasses->pluck('id');
        $subjects = $enrolledClasses
            ->pluck('subject')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $query = LearningMaterial::query()
            ->whereIn('learning_materials.school_class_id', $classIds)
            ->whereHas('schoolClass', function (Builder $classQuery) {
                $classQuery
                    ->whereColumn('school_classes.subject_id', 'learning_materials.subject_id')
                    ->whereHas('subject');
            })
            ->with(['schoolClass.subject', 'uploader']);

        if (filled($filters['subject_id'] ?? null)) {
            $query->whereIn('learning_materials.subject_id', $subjects->pluck('id'))
                ->where('learning_materials.subject_id', $filters['subject_id']);
        }

        if (filled($filters['search'] ?? null)) {
            $search = trim($filters['search']);
            $like = '%'.$search.'%';

            $query->where(function (Builder $materialQuery) use ($like) {
                $materialQuery
                    ->where('learning_materials.title', 'like', $like)
                    ->orWhere('learning_materials.description', 'like', $like)
                    ->orWhere('learning_materials.file_type', 'like', $like)
                    ->orWhereHas('schoolClass.subject', fn (Builder $subjectQuery) => $subjectQuery->where('name', 'like', $like))
                    ->orWhereHas('schoolClass', fn (Builder $classQuery) => $classQuery
                        ->where('name', 'like', $like)
                        ->orWhere('section', 'like', $like))
                    ->orWhereHas('uploader', fn (Builder $teacherQuery) => $teacherQuery
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like));
            });
        }

        $materials = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();
        $materialsBySubject = $materials->getCollection()->groupBy(
            fn (LearningMaterial $material) => $material->schoolClass->subject->id,
        );

        return view('student.materials.index', [
            'materials' => $materials,
            'materialsBySubject' => $materialsBySubject,
            'subjects' => $subjects,
            'selectedSubjectId' => $filters['subject_id'] ?? '',
            'search' => $filters['search'] ?? '',
        ]);
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
