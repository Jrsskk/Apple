<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    protected $model = QuizQuestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'type' => fake()->randomElement(QuestionType::cases()),
            'question_text' => fake()->sentence().'?',
            'image_path' => null,
            'points' => fake()->randomElement([1, 2, 5, 10]),
            'order' => fake()->numberBetween(1, 20),
            'explanation' => fake()->optional()->sentence(),
        ];
    }
}
