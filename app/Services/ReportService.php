<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use Illuminate\Support\Collection;

class ReportService
{
    public function quizReportData(Quiz $quiz): array
    {
        $attempts = QuizAttempt::with('student')
            ->where('quiz_id', $quiz->id)
            ->whereNotNull('submitted_at')
            ->get();

        $headers = ['Student', 'Email', 'Score', 'Max Score', 'Percentage', 'Submitted At', 'Status'];

        $rows = $attempts->map(function (QuizAttempt $attempt) {
            $maxScore = $attempt->total_points ?? 0;
            $percentage = $maxScore > 0
                ? round(($attempt->score / $maxScore) * 100, 2)
                : 0;

            return [
                $attempt->student->full_name,
                $attempt->student->email,
                $attempt->score,
                $maxScore,
                $percentage,
                $attempt->submitted_at?->toDateTimeString(),
                is_string($attempt->status) ? $attempt->status : $attempt->status->value ?? $attempt->status,
            ];
        });

        return [
            'headers' => $headers,
            'rows' => $rows->values()->all(),
            'csv' => $this->toCsv($headers, $rows),
        ];
    }

    public function assignmentReportData(Assignment $assignment): array
    {
        $submissions = $assignment->submissions()->with('student')->get();

        $headers = ['Student', 'Email', 'Score', 'Max Score', 'Submitted At', 'Status', 'Feedback'];

        $rows = $submissions->map(function ($submission) use ($assignment) {
            return [
                $submission->student->full_name,
                $submission->student->email,
                $submission->score,
                $assignment->max_score,
                $submission->submitted_at?->toDateTimeString(),
                $submission->status->value ?? $submission->status,
                $submission->feedback,
            ];
        });

        return [
            'headers' => $headers,
            'rows' => $rows->values()->all(),
            'csv' => $this->toCsv($headers, $rows),
        ];
    }

    public function classQuizSummary(SchoolClass $class): array
    {
        return $class->quizzes()->withCount([
            'attempts',
            'attempts as graded_count' => fn ($q) => $q->where('status', 'graded'),
        ])->get()->map(fn (Quiz $quiz) => [
            'quiz_id' => $quiz->id,
            'title' => $quiz->title,
            'attempts' => $quiz->attempts_count,
            'graded' => $quiz->graded_count,
        ])->all();
    }

    private function toCsv(array $headers, Collection $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }
}
