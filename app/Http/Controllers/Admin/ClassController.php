<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClassController extends Controller
{
    public function __construct(private AuditLogService $auditLog) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Classes/Index', [
            'classes' => SchoolClass::with(['subject', 'teacher', 'academicYear'])->withCount('students')->latest()->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Classes/Create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'section' => 'required|string',
            'grade_level' => 'required|string',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', UserRole::Teacher->value)->where('status', 'active'),
            ],
            'academic_year_id' => 'required|exists:academic_years,id',
            'schedule' => 'nullable|string',
            'room' => 'nullable|string',
        ]);

        $subject = Subject::findOrFail($data['subject_id']);
        $teacherId = (int) $data['teacher_id'];

        if (! blank($subject->teacher_id) && (int) $subject->teacher_id !== $teacherId) {
            return back()->withErrors([
                'subject_id' => 'The selected subject is assigned to a different teacher.',
            ])->withInput();
        }

        if (blank($subject->teacher_id)) {
            $subject->update(['teacher_id' => $teacherId]);
        }

        $class = SchoolClass::create([
            ...$data,
            'class_code' => SchoolClass::generateUniqueClassCode(),
        ]);
        $this->auditLog->log(auth()->user(), 'create', 'classes', "Created class {$class->display_name}.");

        return redirect()->route('admin.classes.show', $class)
            ->with('success', "Class created. Class code: {$class->class_code}");
    }

    public function show(SchoolClass $class): Response
    {
        $class->ensureClassCode()->load(['students', 'subject', 'teacher', 'academicYear']);
        $students = User::where('role', UserRole::Student)->where('status', 'active')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email']);

        return Inertia::render('Admin/Classes/Show', [
            'class' => $class,
            'availableStudents' => $students->whereNotIn('id', $class->students->pluck('id'))->values(),
        ]);
    }

    public function update(Request $request, SchoolClass $class): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'section' => 'required|string',
            'grade_level' => 'required|string',
            'teacher_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', UserRole::Teacher->value)->where('status', 'active'),
            ],
            'schedule' => 'nullable|string',
            'room' => 'nullable|string',
        ]);
        $class->update($data);

        return back()->with('success', 'Class updated.');
    }

    public function destroy(SchoolClass $class): RedirectResponse
    {
        $class->delete();
        $this->auditLog->log(auth()->user(), 'delete', 'classes', "Deleted class {$class->display_name}.");

        return redirect()->route('admin.classes.index')->with('success', 'Class deleted.');
    }

    public function enroll(Request $request, SchoolClass $class): RedirectResponse
    {
        $data = $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => [
                'required',
                Rule::exists('users', 'id')->where('role', UserRole::Student->value)->where('status', 'active'),
            ],
        ]);
        $class->students()->syncWithoutDetaching(
            collect($data['student_ids'])->mapWithKeys(fn ($id) => [$id => ['enrolled_at' => now(), 'status' => 'enrolled']])->all()
        );

        return back()->with('success', 'Students enrolled.');
    }

    public function unenroll(Request $request, SchoolClass $class): RedirectResponse
    {
        $data = $request->validate(['student_id' => 'required|exists:users,id']);
        $class->students()->detach($data['student_id']);

        return back()->with('success', 'Student removed.');
    }

    private function formData(): array
    {
        return [
            'subjects' => Subject::where('status', 'active')->get(),
            'teachers' => User::where('role', UserRole::Teacher)->where('status', 'active')->get(['id', 'first_name', 'last_name']),
            'years' => AcademicYear::orderByDesc('is_active')->get(),
        ];
    }
}
