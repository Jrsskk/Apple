<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function index(): View
    {
        $classes = auth()->user()->enrolledClasses()->with(['subject', 'teacher'])->get();

        return view('student.classes.index', compact('classes'));
    }

    public function show(SchoolClass $class): View
    {
        abort_unless(auth()->user()->enrolledClasses()->where('school_classes.id', $class->id)->exists(), 403);
        $class->load(['subject', 'teacher', 'quizzes', 'assignments', 'materials']);

        return view('student.classes.show', compact('class'));
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_code' => 'required|string|max:20',
        ]);

        $class = SchoolClass::findByCode($data['class_code']);

        if (! $class) {
            return back()->withErrors(['class_code' => 'Invalid class code. Please check and try again.']);
        }

        $user = auth()->user();

        if ($user->enrolledClasses()->where('school_classes.id', $class->id)->exists()) {
            return back()->withErrors(['class_code' => 'You are already enrolled in this class.']);
        }

        $class->students()->syncWithoutDetaching([
            $user->id => ['enrolled_at' => now(), 'status' => 'enrolled'],
        ]);

        return redirect()->route('student.classes.show', $class)
            ->with('success', "You have joined {$class->display_name}.");
    }
}
