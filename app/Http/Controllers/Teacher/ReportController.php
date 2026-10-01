<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Quiz;
use App\Models\SchoolClass;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    public function index(Request $request): Response
    {
        $classIds = $request->user()->taughtClasses()->pluck('id');

        $classes = SchoolClass::whereIn('id', $classIds)
            ->withCount(['students', 'quizzes', 'assignments'])
            ->get();

        $quizzes = Quiz::whereIn('school_class_id', $classIds)
            ->with('schoolClass')
            ->latest()
            ->take(20)
            ->get();

        $assignments = Assignment::whereIn('school_class_id', $classIds)
            ->with('schoolClass')
            ->latest()
            ->take(20)
            ->get();

        return Inertia::render('Teacher/Reports/Index', [
            'classes' => $classes,
            'quizzes' => $quizzes,
            'assignments' => $assignments,
        ]);
    }

    public function exportQuiz(Quiz $quiz): StreamedResponse
    {
        abort_unless($quiz->teacher_id === auth()->id() || auth()->user()->isAdmin(), 403);

        $data = $this->reportService->quizReportData($quiz);

        return response()->streamDownload(function () use ($data) {
            echo $data['csv'];
        }, "quiz-{$quiz->id}-report.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportAssignment(Assignment $assignment): StreamedResponse
    {
        abort_unless($assignment->teacher_id === auth()->id() || auth()->user()->isAdmin(), 403);

        $data = $this->reportService->assignmentReportData($assignment);

        return response()->streamDownload(function () use ($data) {
            echo $data['csv'];
        }, "assignment-{$assignment->id}-report.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportClass(SchoolClass $class): StreamedResponse
    {
        abort_unless($class->teacher_id === auth()->id() || auth()->user()->isAdmin(), 403);

        $summary = $this->reportService->classQuizSummary($class);
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Quiz', 'Attempts', 'Graded']);

        foreach ($summary as $row) {
            fputcsv($handle, [$row['title'], $row['attempts'], $row['graded']]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, "class-{$class->id}-summary.csv", ['Content-Type' => 'text/csv']);
    }
}
