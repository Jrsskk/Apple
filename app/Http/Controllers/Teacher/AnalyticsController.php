<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\TeacherAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(private readonly TeacherAnalyticsService $analyticsService) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'subject_id' => ['nullable', 'integer'],
            'school_class_id' => ['nullable', 'integer'],
            'quiz_id' => ['nullable', 'integer'],
            'assignment_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'academic_year_id' => ['nullable', 'integer'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        return Inertia::render('Teacher/Analytics/Index', $this->analyticsService->dashboard($request->user(), $filters));
    }
}
