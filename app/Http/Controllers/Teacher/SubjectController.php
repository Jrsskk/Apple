<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(Request $request): Response
    {
        $subjects = Subject::where('teacher_id', $request->user()->id)
            ->with(['academicYear'])
            ->withCount('classes')
            ->latest()
            ->get();

        $academicYears = \App\Models\AcademicYear::orderByDesc('is_active')->get();

        return Inertia::render('Teacher/Subjects/Index', [
            'subjects' => $subjects,
            'academicYears' => $academicYears,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'grade_level' => 'required|string|max:50',
            'academic_year_id' => 'required|exists:academic_years,id',
        ]);

        $data['code'] = Subject::generateUniqueCode($data['name']);

        Subject::create(array_merge($data, [
            'teacher_id' => $request->user()->id,
            'status' => 'active',
        ]));

        return back()->with('success', 'Subject created.');
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        abort_unless($subject->teacher_id === $request->user()->id, 403);

        $data = $request->validate([
            'code' => 'required|string|max:20|unique:subjects,code,'.$subject->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'grade_level' => 'required|string|max:50',
        ]);

        $subject->update($data);

        return back()->with('success', 'Subject updated.');
    }
}
