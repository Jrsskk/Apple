<?php

namespace App\Services;

use App\Enums\QuizStatus;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizService
{
    public function __construct(private AuditLogService $auditLog) {}

    public function create(array $data, User $teacher): Quiz
    {
        return DB::transaction(function () use ($data, $teacher) {
            $status = $data['status'] ?? QuizStatus::Draft->value;
            $quiz = Quiz::create(array_merge($data, [
                'teacher_id' => $teacher->id,
                'status' => $status,
            ]));
            $this->syncQuestions($quiz, $data['questions'] ?? []);
            $quiz->update(['total_points' => $quiz->questions()->sum('points')]);
            $this->assertPublishedQuizIsComplete($quiz);
            $this->auditLog->log($teacher, 'create', 'quiz', "Created quiz \"{$quiz->title}\".");

            return $quiz->load('questions.options');
        });
    }

    public function update(Quiz $quiz, array $data): Quiz
    {
        return DB::transaction(function () use ($quiz, $data) {
            $quiz->update($data);
            if (isset($data['questions'])) {
                $quiz->questions()->each(fn ($q) => $q->options()->delete());
                $quiz->questions()->delete();
                $this->syncQuestions($quiz, $data['questions']);
            }
            $quiz->update(['total_points' => $quiz->questions()->sum('points')]);
            $this->assertPublishedQuizIsComplete($quiz);

            return $quiz->fresh()->load('questions.options');
        });
    }

    public function duplicate(Quiz $quiz, User $teacher): Quiz
    {
        $newQuiz = $quiz->replicate(['status']);
        $newQuiz->title = $quiz->title.' (Copy)';
        $newQuiz->status = 'draft';
        $newQuiz->teacher_id = $teacher->id;
        $newQuiz->save();

        foreach ($quiz->questions as $question) {
            $newQuestion = $question->replicate();
            $newQuestion->quiz_id = $newQuiz->id;
            $newQuestion->save();
            foreach ($question->options as $option) {
                $newOption = $option->replicate();
                $newOption->quiz_question_id = $newQuestion->id;
                $newOption->save();
            }
        }

        $newQuiz->update(['total_points' => $newQuiz->questions()->sum('points')]);
        $this->auditLog->log($teacher, 'duplicate', 'quiz', "Duplicated quiz \"{$quiz->title}\".");

        return $newQuiz->load('questions.options');
    }

    public function publish(Quiz $quiz, User $teacher): Quiz
    {
        $quiz->update(['status' => 'published']);
        $this->auditLog->log($teacher, 'publish', 'quiz', "Teacher {$teacher->full_name} published {$quiz->title}.");

        return $quiz;
    }

    /**
     * Return publish blockers using the same question data students will receive.
     * Drafts may be incomplete; publishing may not.
     *
     * @return array<string, string>
     */
    public function publishValidationErrors(Quiz $quiz): array
    {
        $quiz->loadMissing('questions.options');
        $errors = [];

        if ($quiz->questions->isEmpty()) {
            $errors['questions'] = 'Add at least one question before publishing.';
        }

        foreach ($quiz->questions as $index => $question) {
            $prefix = "questions.{$index}";
            $options = $question->options;
            $type = $question->type?->value ?? $question->type;

            if (blank($question->question_text)) {
                $errors["{$prefix}.question_text"] = 'Question text is required.';
            }

            if ((float) $question->points <= 0) {
                $errors["{$prefix}.points"] = 'Points must be greater than zero.';
            }

            if (in_array($type, ['multiple_choice', 'true_false', 'image_based'], true)) {
                if ($options->count() < 2 || $options->where('is_correct', true)->count() !== 1) {
                    $errors["{$prefix}.options"] = 'Provide at least two options and exactly one correct answer.';
                }
            } elseif ($type === 'multiple_selection') {
                if ($options->count() < 2 || $options->where('is_correct', true)->isEmpty()) {
                    $errors["{$prefix}.options"] = 'Provide at least two options and one or more correct answers.';
                }
            } elseif ($type === 'identification') {
                if (blank($options->first()?->option_text)) {
                    $errors["{$prefix}.options"] = 'Provide at least one correct answer.';
                }
            } elseif (in_array($type, ['sequencing', 'drag_drop'], true)) {
                if ($options->count() < 2 || $options->pluck('option_text')->contains(fn ($text) => blank($text))) {
                    $errors["{$prefix}.options"] = 'Provide at least two non-empty items in the correct order.';
                }
            } elseif ($type === 'matching') {
                $left = $options->where('is_correct', false);
                $right = $options->where('is_correct', true);
                if ($left->isEmpty() || $left->count() !== $right->count()
                    || $left->contains(fn ($option) => blank($option->option_text) || blank($option->match_key))
                    || $right->contains(fn ($option) => blank($option->option_text) || blank($option->match_key))) {
                    $errors["{$prefix}.options"] = 'Provide complete matching pairs with unique match keys.';
                }
            }
        }

        return $errors;
    }

    private function assertPublishedQuizIsComplete(Quiz $quiz): void
    {
        if (! $quiz->isPublished()) {
            return;
        }

        $errors = $this->publishValidationErrors($quiz);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function getQuestionsForAttempt(Quiz $quiz): \Illuminate\Support\Collection
    {
        $questions = $quiz->questions()->with('options')->get();

        if ($quiz->randomize_questions) {
            $questions = $questions->shuffle();
        }

        $questions = $questions->map(function ($q) use ($quiz) {
            $type = $q->type?->value ?? $q->type;

            if ($quiz->randomize_choices || in_array($type, ['matching', 'sequencing', 'drag_drop'], true)) {
                $q->setRelation('options', $q->options->shuffle());

            }

            return $q;
        });

        return $questions;
    }

    public function buildQuestionSet(Quiz $quiz, ?int $seed = null): \Illuminate\Support\Collection
    {
        $questions = $this->getQuestionsForAttempt($quiz);

        if ($seed !== null) {
            return $questions->sortBy(fn ($question) => crc32($seed.'-'.$question->id))->values();
        }

        return $questions;
    }

    public function buildStudentQuestionSet(Quiz $quiz, ?int $seed = null): \Illuminate\Support\Collection
    {
        return $this->buildQuestionSet($quiz, $seed)->map(fn ($question) => [
            'id' => $question->id,
            'quiz_id' => $question->quiz_id,
            'type' => $question->type?->value ?? $question->type,
            'question_text' => $question->question_text,
            'image_path' => $question->image_path,
            'points' => $question->points,
            'order' => $question->order,
            'options' => $question->options->map(fn ($option) => [
                'id' => $option->id,
                'quiz_question_id' => $option->quiz_question_id,
                'option_text' => $option->option_text,
                'image_path' => $option->image_path,
                'side' => ($question->type?->value ?? $question->type) === 'matching'
                    ? ($option->is_correct ? 'right' : 'left')
                    : null,
            ])->values(),
        ])->values();
    }

    private function syncQuestions(Quiz $quiz, array $questions): void
    {
        foreach ($questions as $index => $qData) {
            $question = $quiz->questions()->create([
                'type' => $qData['type'],
                'question_text' => $qData['question_text'],
                'image_path' => $qData['image_path'] ?? null,
                'points' => $qData['points'] ?? 1,
                'order' => $index,
                'explanation' => $qData['explanation'] ?? null,
            ]);

            foreach ($qData['options'] ?? [] as $oIndex => $option) {
                $question->options()->create([
                    'option_text' => $option['option_text'] ?? null,
                    'image_path' => $option['image_path'] ?? null,
                    'is_correct' => $option['is_correct'] ?? false,
                    'match_key' => $option['match_key'] ?? null,
                    'order' => $oIndex,
                ]);
            }
        }
    }
}
