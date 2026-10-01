<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClassController extends Controller
{
    public function index(Request $request): Response
    {
        $classes = $request->user()->taughtClasses()
            ->with(['subject', 'academicYear'])
            ->withCount(['students', 'quizzes', 'assignments'])
            ->get();

        $subjects = \App\Models\Subject::where('teacher_id', $request->user()->id)->get();
        $academicYears = \App\Models\AcademicYear::orderByDesc('is_active')->get();

        return Inertia::render('Teacher/Classes/Index', [
            'classes' => $classes,
            'subjects' => $subjects,
            'academicYears' => $academicYears,
        ]);
    }

    public function create(Request $request): Response
    {
        $subjects = \App\Models\Subject::where('teacher_id', $request->user()->id)->get();
        $academicYears = \App\Models\AcademicYear::orderByDesc('is_active')->get();

        return Inertia::render('Teacher/Classes/Create', [
            'subjects' => $subjects,
            'academicYears' => $academicYears,
        ]);
    }

    public function show(Request $request, SchoolClass $class): Response
    {
        $this->authorizeClass($class);

        $class->ensureClassCode()->load([
            'subject',
            'academicYear',
            'students',
            'quizzes' => fn ($q) => $q->latest()->take(5),
            'assignments' => fn ($q) => $q->latest()->take(5),
            'materials' => fn ($q) => $q->latest()->take(5),
        ]);

        $availableStudents = User::where('role', UserRole::Student)
            ->where('status', 'active')
            ->whereNotIn('id', $class->students->pluck('id'))
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'username']);

        return Inertia::render('Teacher/Classes/Show', [
            'class' => $class,
            'availableStudents' => $availableStudents,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'section' => 'required|string|max:50',
            'grade_level' => 'required|string|max:50',
            'subject_id' => 'required|exists:subjects,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'schedule' => 'nullable|string|max:255',
            'room' => 'nullable|string|max:100',
        ]);

        abort_unless(
            \App\Models\Subject::where('id', $data['subject_id'])->where('teacher_id', $request->user()->id)->exists(),
            403
        );

        $class = $request->user()->taughtClasses()->create([
            ...$data,
            'class_code' => SchoolClass::generateUniqueClassCode(),
        ]);

        return redirect()->route('teacher.classes.show', $class)
            ->with('success', "Class created. Share this code with students: {$class->class_code}");
    }

    public function update(Request $request, SchoolClass $class): RedirectResponse
    {
        $this->authorizeClass($class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'section' => 'required|string|max:50',
            'grade_level' => 'required|string|max:50',
            'schedule' => 'nullable|string|max:255',
            'room' => 'nullable|string|max:100',
        ]);

        $class->update($data);

        return back()->with('success', 'Class updated.');
    }

    public function enroll(Request $request, SchoolClass $class): RedirectResponse
    {
        $this->authorizeClass($class);

        $data = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => [
                'required',
                Rule::exists('users', 'id')->where('role', UserRole::Student->value)->where('status', 'active'),
            ],
        ]);

        $class->students()->syncWithoutDetaching(
            collect($data['student_ids'])->mapWithKeys(fn ($id) => [
                $id => ['enrolled_at' => now(), 'status' => 'enrolled'],
            ])->all()
        );

        return back()->with('success', 'Students enrolled.');
    }

    public function unenroll(Request $request, SchoolClass $class): RedirectResponse
    {
        $this->authorizeClass($class);

        $data = $request->validate(['student_id' => 'required|exists:users,id']);
        $class->students()->detach($data['student_id']);

        return back()->with('success', 'Student removed from class.');
    }

    private function authorizeClass(SchoolClass $class): void
    {
        abort_unless($class->teacher_id === auth()->id() || auth()->user()->isAdmin(), 403);
    }
}
