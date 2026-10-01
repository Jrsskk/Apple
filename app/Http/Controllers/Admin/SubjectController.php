<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Subject;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function __construct(private AuditLogService $auditLog) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Subjects/Index', [
            'subjects' => Subject::with(['teacher', 'academicYear'])->latest()->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Subjects/Create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'grade_level' => 'required|in:Grade 7,Grade 8,Grade 9,Grade 10,Grade 11,Grade 12',
            'teacher_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', UserRole::Teacher->value)->where('status', 'active'),
            ],
            'academic_year_id' => 'required|exists:academic_years,id',
        ]);

        $data['code'] = Subject::generateUniqueCode($data['name']);
        Subject::create($data);
        $this->auditLog->log(auth()->user(), 'create', 'subjects', "Created subject {$data['name']}.");

        return redirect()->route('admin.subjects.index')->with('success', 'Subject created.');
    }

    public function edit(Subject $subject): Response
    {
        return Inertia::render('Admin/Subjects/Edit', array_merge(['subject' => $subject], $this->formData()));
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'grade_level' => 'required|in:Grade 7,Grade 8,Grade 9,Grade 10,Grade 11,Grade 12',
            'teacher_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', UserRole::Teacher->value)->where('status', 'active'),
            ],
            'academic_year_id' => 'required|exists:academic_years,id',
            'status' => 'required|in:active,inactive',
        ]);

        if (blank($subject->code)) {
            $data['code'] = Subject::generateUniqueCode($data['name']);
        }

        $subject->update($data);

        return back()->with('success', 'Subject updated.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->delete();
        $this->auditLog->log(auth()->user(), 'delete', 'subjects', "Deleted subject {$subject->name}.");

        return redirect()->route('admin.subjects.index')->with('success', 'Subject deleted.');
    }

    private function formData(): array
    {
        return [
            'teachers' => User::where('role', UserRole::Teacher)->where('status', 'active')->get(['id', 'first_name', 'last_name']),
            'years' => AcademicYear::orderByDesc('is_active')->get(),
        ];
    }
}
