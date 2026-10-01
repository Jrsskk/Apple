<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $classes = $user->enrolledClasses()
            ->with(['subject', 'teacher:id,first_name,middle_name,last_name,email', 'materials'])
            ->get()
            ->map(fn (SchoolClass $class) => $this->formatClass($class));

        return $this->success($classes);
    }

    public function show(Request $request, SchoolClass $class): JsonResponse
    {
        $this->authorize('view', $class);

        $class->load(['subject', 'teacher', 'materials', 'quizzes', 'assignments']);

        return $this->success($class);
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_code' => 'required|string|max:20',
        ]);

        $class = SchoolClass::findByCode($data['class_code']);

        if (! $class) {
            return $this->error('Invalid class code.', 422);
        }

        $user = $request->user();

        if ($user->enrolledClasses()->where('school_classes.id', $class->id)->exists()) {
            return $this->error('You are already enrolled in this class.', 422);
        }

        $class->students()->syncWithoutDetaching([
            $user->id => ['enrolled_at' => now(), 'status' => 'enrolled'],
        ]);

        $class->load(['subject', 'teacher:id,first_name,middle_name,last_name,email', 'materials']);

        return $this->success($this->formatClass($class), 'Successfully joined class.');
    }

    private function formatClass(SchoolClass $class): array
    {
        return [
            'id' => $class->id,
            'name' => $class->name,
            'section' => $class->section,
            'grade_level' => $class->grade_level,
            'class_code' => $class->class_code,
            'schedule' => $class->schedule,
            'room' => $class->room,
            'status' => $class->status,
            'description' => $class->description ?? null,
            'subject' => $class->subject,
            'teacher' => [
                'id' => $class->teacher?->id,
                'name' => $class->teacher?->full_name,
                'email' => $class->teacher?->email,
            ],
            'materials' => $class->materials ?? [],
            'created_at' => $class->created_at?->toIso8601String(),
            'updated_at' => $class->updated_at?->toIso8601String(),
        ];
    }
}
