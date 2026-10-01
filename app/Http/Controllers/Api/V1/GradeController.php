<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AssignmentSubmission;
use App\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $quizGrades = QuizAttempt::where('student_id', $user->id)
            ->where('status', 'graded')
            ->with(['quiz.schoolClass.subject'])
            ->latest('submitted_at')
            ->get()
            ->map(fn (QuizAttempt $attempt) => [
                'type' => 'quiz',
                'id' => $attempt->id,
                'title' => $attempt->quiz->title,
                'class' => $attempt->quiz->schoolClass?->display_name,
                'score' => $attempt->score,
                'max_score' => $attempt->total_points,
                'graded_at' => $attempt->submitted_at,
            ]);

        $assignmentGrades = AssignmentSubmission::where('student_id', $user->id)
            ->whereNotNull('score')
            ->with(['assignment.schoolClass.subject'])
            ->latest('submitted_at')
            ->get()
            ->map(fn (AssignmentSubmission $submission) => [
                'type' => 'assignment',
                'id' => $submission->id,
                'title' => $submission->assignment->title,
                'class' => $submission->assignment->schoolClass?->display_name,
                'score' => $submission->score,
                'max_score' => $submission->assignment->max_score,
                'graded_at' => $submission->submitted_at,
                'feedback' => $submission->feedback,
            ]);

        $grades = $quizGrades->concat($assignmentGrades)
            ->sortByDesc('graded_at')
            ->values();

        return $this->success($grades);
    }
}
