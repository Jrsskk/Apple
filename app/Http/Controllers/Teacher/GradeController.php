<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class GradeController extends Controller
{
    public function index(Request $request): Response
    {
        $classIds = $request->user()->taughtClasses()->pluck('id');
        $subjectId = $request->integer('subject_id') ?: null;
        $schoolClassId = $request->integer('school_class_id') ?: null;
        $subjects = Subject::whereIn(
            'id',
            SchoolClass::whereIn('id', $classIds)->select('subject_id')
        )->orderBy('name')->get(['id', 'name']);
        $classes = $request->user()->taughtClasses()
            ->with('subject:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'section', 'subject_id']);

        $quizGradeQuery = QuizAttempt::whereHas(
            'quiz',
            fn ($q) => $q->whereIn('school_class_id', $classIds)
                ->whereHas('schoolClass', fn ($classQuery) => $classQuery
                    ->whereColumn('school_classes.subject_id', 'quizzes.subject_id'))
                ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
                ->when($schoolClassId, fn ($query) => $query->where('school_class_id', $schoolClassId))
        )
            ->where('status', 'graded')
            ->with(['student', 'quiz.schoolClass', 'quiz.subject']);

        $assignmentGradeQuery = AssignmentSubmission::whereHas(
            'assignment',
            fn ($q) => $q->whereIn('school_class_id', $classIds)
                ->whereHas('schoolClass', fn ($classQuery) => $classQuery
                    ->whereColumn('school_classes.subject_id', 'assignments.subject_id'))
                ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
                ->when($schoolClassId, fn ($query) => $query->where('school_class_id', $schoolClassId))
        )
            ->whereIn('status', ['graded', 'returned'])
            ->with(['student', 'assignment.schoolClass', 'assignment.subject']);

        $summaryQuizGrades = (clone $quizGradeQuery)->get();
        $summaryAssignmentGrades = (clone $assignmentGradeQuery)->get();

        $quizGrades = $quizGradeQuery
            ->latest('submitted_at')
            ->paginate(15, ['*'], 'quizzes_page')
            ->withQueryString();
        $assignmentGrades = $assignmentGradeQuery
            ->latest('submitted_at')
            ->paginate(15, ['*'], 'assignments_page')
            ->withQueryString();

        return Inertia::render('Teacher/Grades/Index', [
            'quizGrades' => $quizGrades,
            'assignmentGrades' => $assignmentGrades,
            'gradeSummary' => $this->buildGradeSummary($summaryQuizGrades, $summaryAssignmentGrades),
            'subjects' => $subjects,
            'classes' => $classes,
            'filters' => [
                'subject_id' => $subjectId,
                'school_class_id' => $schoolClassId,
            ],
        ]);
    }

    private function buildGradeSummary(Collection $quizGrades, Collection $assignmentGrades): array
    {
        $summary = [];

        foreach ($quizGrades as $grade) {
            $this->addGradeToSummary(
                $summary,
                $grade->student,
                $grade->quiz->subject,
                $grade->score,
                $grade->total_points ?? $grade->quiz->total_points
            );
        }

        foreach ($assignmentGrades as $grade) {
            $this->addGradeToSummary(
                $summary,
                $grade->student,
                $grade->assignment->subject,
                $grade->score,
                $grade->assignment->max_score
            );
        }

        return collect($summary)->map(function (array $row): array {
            $row['percentage'] = $row['total_score'] > 0
                ? round(($row['score'] / $row['total_score']) * 100, 1)
                : null;

            return $row;
        })->sortBy([['subject_name', 'asc'], ['student_name', 'asc']])->values()->all();
    }

    private function addGradeToSummary(
        array &$summary,
        $student,
        $subject,
        mixed $score,
        mixed $totalScore
    ): void {
        if (! $student || ! $subject || $score === null || $totalScore === null || (float) $totalScore <= 0) {
            return;
        }

        $key = $subject->id.':'.$student->id;
        $summary[$key] ??= [
            'subject_id' => $subject->id,
            'subject_name' => $subject->name,
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'score' => 0,
            'total_score' => 0,
            'activity_count' => 0,
        ];
        $summary[$key]['score'] += (float) $score;
        $summary[$key]['total_score'] += (float) $totalScore;
        $summary[$key]['activity_count']++;
    }
}
