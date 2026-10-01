<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'instructions' => fake()->paragraph(),
            'subject_id' => Subject::factory(),
            'school_class_id' => SchoolClass::factory(),
            'teacher_id' => User::factory()->teacher(),
            'attachment_path' => null,
            'posted_at' => now(),
            'deadline' => now()->addDays(7),
            'max_score' => 100,
            'allow_resubmit' => fake()->boolean(),
            'status' => 'published',
        ];
    }
}
