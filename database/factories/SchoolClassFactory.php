<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Mathematics', 'Science', 'English']),
            'section' => fake()->randomElement(['A', 'B', 'C', 'D']),
            'grade_level' => fake()->randomElement(['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10']),
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory()->teacher(),
            'academic_year_id' => AcademicYear::factory(),
            'schedule' => fake()->randomElement(['MWF 8:00-9:00', 'TTh 10:00-11:30', 'MWF 1:00-2:00']),
            'room' => 'Room '.fake()->numberBetween(101, 305),
            'status' => 'active',
        ];
    }
}
