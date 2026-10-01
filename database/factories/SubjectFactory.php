<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SUB-###')),
            'name' => fake()->randomElement(['Mathematics', 'Science', 'English', 'History', 'Filipino']),
            'description' => fake()->sentence(),
            'grade_level' => fake()->randomElement(['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10']),
            'teacher_id' => User::factory()->teacher(),
            'academic_year_id' => AcademicYear::factory(),
            'status' => 'active',
        ];
    }
}
