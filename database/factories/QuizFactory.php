<?php

namespace Database\Factories;

use App\Enums\QuizStatus;
use App\Models\Quiz;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    protected $model = Quiz::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'instructions' => fake()->paragraph(),
            'subject_id' => Subject::factory(),
            'school_class_id' => SchoolClass::factory(),
            'teacher_id' => User::factory()->teacher(),
            'starts_at' => now(),
            'deadline' => now()->addWeek(),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'total_points' => 100,
            'max_attempts' => fake()->numberBetween(1, 3),
            'passing_score' => 75,
            'randomize_questions' => fake()->boolean(),
            'randomize_choices' => fake()->boolean(),
            'show_results' => true,
            'allow_review' => true,
            'status' => QuizStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuizStatus::Published,
        ]);
    }
}
