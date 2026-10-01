<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\AssignmentFileService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
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

        $assignmentSubmissions = AssignmentSubmission::whereHas(
            'assignment',
            fn ($q) => $q->whereIn('school_class_id', $classIds)
                ->whereHas('schoolClass', fn ($classQuery) => $classQuery
                    ->whereColumn('school_classes.subject_id', 'assignments.subject_id'))
                ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
                ->when($schoolClassId, fn ($query) => $query->where('school_class_id', $schoolClassId))
        )
            ->with(['student', 'assignment.schoolClass', 'assignment.subject'])
            ->whereIn('status', ['submitted', 'late', 'graded', 'returned'])
            ->latest('submitted_at')
            ->paginate(10, ['*'], 'assignments_page')
            ->withQueryString();

        $quizAttempts = QuizAttempt::whereHas(
            'quiz',
            fn ($q) => $q->whereIn('school_class_id', $classIds)
                ->whereHas('schoolClass', fn ($classQuery) => $classQuery
                    ->whereColumn('school_classes.subject_id', 'quizzes.subject_id'))
                ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
                ->when($schoolClassId, fn ($query) => $query->where('school_class_id', $schoolClassId))
        )
            ->with(['student', 'quiz.schoolClass', 'quiz.subject'])
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->paginate(10, ['*'], 'quizzes_page')
            ->withQueryString();

        return Inertia::render('Teacher/Submissions/Index', [
            'assignmentSubmissions' => $assignmentSubmissions,
            'quizAttempts' => $quizAttempts,
            'subjects' => $subjects,
            'classes' => $classes,
            'filters' => [
                'subject_id' => $subjectId,
                'school_class_id' => $schoolClassId,
            ],
        ]);
    }

    public function showQuizAttempt(Request $request, QuizAttempt $attempt): Response
    {
        $this->authorizeQuizAttempt($attempt);

        $attempt->load(['student', 'quiz.questions.options', 'answers.question']);

        return Inertia::render('Teacher/Submissions/QuizAttempt', [
            'attempt' => $attempt,
        ]);
    }

    public function showAssignmentSubmission(Request $request, AssignmentSubmission $submission): Response
    {
        $this->authorizeAssignmentSubmission($submission);

        $submission->load(['student', 'assignment']);

        return Inertia::render('Teacher/Submissions/AssignmentSubmission', [
            'submission' => $submission,
        ]);
    }

    public function gradeAssignment(Request $request, AssignmentSubmission $submission): RedirectResponse
    {
        $this->authorizeAssignmentSubmission($submission);

        $data = $request->validate([
            'score' => 'required|numeric|min:0|max:'.$submission->assignment->max_score,
            'feedback' => 'nullable|string',
            'status' => 'required|in:graded,returned',
        ]);

        $submission->update($data);
        app(NotificationService::class)->notifyGradeReleased(
            $submission->student,
            $submission->assignment->title
        );

        return back()->with('success', 'Submission graded.');
    }

    public function downloadSubmissionFile(
        AssignmentSubmission $submission,
        AssignmentFileService $files,
    ): StreamedResponse {
        $this->authorizeAssignmentSubmission($submission);
        abort_unless($submission->file_path, 404);

        return $files->response(
            $submission->file_path,
            $submission->storage_disk,
            $submission->file_name ?: basename($submission->file_path),
            true,
            $submission->file_type,
        );
    }

    private function authorizeQuizAttempt(QuizAttempt $attempt): void
    {
        abort_unless(
            $attempt->quiz->teacher_id === auth()->id() || auth()->user()->isAdmin(),
            403
        );
    }

    private function authorizeAssignmentSubmission(AssignmentSubmission $submission): void
    {
        abort_unless(
            $submission->assignment->teacher_id === auth()->id() || auth()->user()->isAdmin(),
            403
        );
    }
}
