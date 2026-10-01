<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;

class GradingService
{
    public function autoGradeAttempt(QuizAttempt $attempt): QuizAttempt
    {
        $attempt->load(['answers.question.options', 'quiz']);

        $correct = 0;
        $incorrect = 0;
        $earned = 0;
        $total = 0;

        foreach ($attempt->quiz->questions()->with('options')->get() as $question) {
            $answer = $attempt->answers->firstWhere('quiz_question_id', $question->id);
            if (! $answer) {
                $answer = new QuizAnswer([
                    'quiz_attempt_id' => $attempt->id,
                    'quiz_question_id' => $question->id,
                ]);
                $answer->save();
            }
            $total += (float) $question->points;
            $result = $this->gradeAnswer($question, $answer);

            $answer->update([
                'is_correct' => $result['is_correct'],
                'points_earned' => $result['points'],
            ]);

            if ($result['is_correct']) {
                $correct++;
                $earned += $result['points'];
            } else {
                $incorrect++;
            }
        }

        $percentage = $total > 0 ? round(($earned / $total) * 100, 2) : 0;
        $passed = $percentage >= (float) $attempt->quiz->passing_score;

        $attempt->update([
            'score' => $earned,
            'percentage' => $percentage,
            'correct_count' => $correct,
            'incorrect_count' => $incorrect,
            'total_points' => $total,
            'passed' => $passed,
            'status' => 'graded',
            'completed_at' => $attempt->completed_at ?? now(),
            'submitted_at' => $attempt->submitted_at ?? now(),
        ]);

        return $attempt->fresh();
    }

    public function gradeAnswer(QuizQuestion $question, QuizAnswer $answer): array
    {
        return match ($question->type) {
            QuestionType::MultipleChoice, QuestionType::TrueFalse, QuestionType::ImageBased => $this->gradeSingleChoice($question, $answer),
            QuestionType::MultipleSelection => $this->gradeMultipleSelection($question, $answer),
            QuestionType::Identification => $this->gradeIdentification($question, $answer),
            QuestionType::Matching => $this->gradeMatching($question, $answer),
            QuestionType::Sequencing, QuestionType::DragDrop => $this->gradeSequencing($question, $answer),
            default => ['is_correct' => false, 'points' => 0],
        };
    }

    private function gradeSingleChoice(QuizQuestion $question, QuizAnswer $answer): array
    {
        $selected = collect($answer->selected_options ?? [])->map(fn ($v) => (int) $v);
        $correctIds = $question->options->where('is_correct', true)->pluck('id')->map(fn ($v) => (int) $v);
        $isCorrect = $selected->count() === 1 && $correctIds->contains($selected->first());

        return ['is_correct' => $isCorrect, 'points' => $isCorrect ? (float) $question->points : 0];
    }

    private function gradeMultipleSelection(QuizQuestion $question, QuizAnswer $answer): array
    {
        $selected = collect($answer->selected_options ?? [])->sort()->values();
        $correct = $question->options->where('is_correct', true)->pluck('id')->sort()->values();
        $isCorrect = $selected->toArray() === $correct->toArray();

        return ['is_correct' => $isCorrect, 'points' => $isCorrect ? (float) $question->points : 0];
    }

    private function gradeIdentification(QuizQuestion $question, QuizAnswer $answer): array
    {
        $expected = collect(explode(',', $question->options->first()?->option_text ?? ''))
            ->map(fn ($value) => strtolower(trim($value)))
            ->filter();
        $given = strtolower(trim($answer->answer_text ?? ''));
        $isCorrect = $given !== '' && $expected->contains($given);

        return ['is_correct' => $isCorrect, 'points' => $isCorrect ? (float) $question->points : 0];
    }

    private function gradeMatching(QuizQuestion $question, QuizAnswer $answer): array
    {
        $pairs = collect($answer->selected_options ?? [])->mapWithKeys(
            fn ($value, $key) => [(string) $key => (string) $value]
        );
        $leftOptions = $question->options->where('is_correct', false);
        $rightMatchKeys = $question->options
            ->where('is_correct', true)
            ->mapWithKeys(fn ($option) => [(string) $option->id => (string) $option->match_key]);
        $allCorrect = $leftOptions->every(
            fn ($option) => $rightMatchKeys->get($pairs->get((string) $option->id)) === (string) $option->match_key
        );
        $isCorrect = $allCorrect && $pairs->isNotEmpty();

        return ['is_correct' => $isCorrect, 'points' => $isCorrect ? (float) $question->points : 0];
    }

    private function gradeSequencing(QuizQuestion $question, QuizAnswer $answer): array
    {
        $given = collect($answer->selected_options ?? [])->values();
        $expected = $question->options->sortBy('order')->pluck('id')->values();
        $isCorrect = $given->toArray() === $expected->toArray();

        return ['is_correct' => $isCorrect, 'points' => $isCorrect ? (float) $question->points : 0];
    }
}
