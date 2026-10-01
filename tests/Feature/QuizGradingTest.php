<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\GradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuizGradingTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_grading_calculates_score(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->first();
        $quiz = Quiz::first();

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'sync_uuid' => Str::uuid(),
            'started_at' => now(),
            'status' => 'submitted',
        ]);

        foreach ($quiz->questions as $question) {
            $correctOption = $question->options->firstWhere('is_correct', true);
            $attempt->answers()->create([
                'quiz_question_id' => $question->id,
                'selected_options' => $correctOption ? [$correctOption->id] : null,
                'answer_text' => $question->type->value === 'identification' ? '56' : null,
            ]);
        }

        app(GradingService::class)->autoGradeAttempt($attempt);

        $attempt->refresh();
        $this->assertEquals('graded', $attempt->status);
        $this->assertGreaterThanOrEqual(60, $attempt->percentage);
    }

    public function test_student_grade_page_renders_when_quiz_status_is_a_plain_string(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->first();
        $quiz = Quiz::first();

        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'sync_uuid' => Str::uuid(),
            'started_at' => now(),
            'submitted_at' => now(),
            'status' => 'graded',
        ]);

        $this->actingAs($student);

        $response = $this->get(route('student.grades.index'));

        $response->assertOk();
        $response->assertSeeText('My Grades');
    }

    public function test_teacher_can_create_a_quiz_without_a_subject_hidden_field(): void
    {
        $academicYear = AcademicYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-06-01',
            'end_date' => '2027-03-31',
            'is_active' => true,
            'status' => 'active',
        ]);

        $teacher = User::create([
            'first_name' => 'Quiz',
            'last_name' => 'Teacher',
            'email' => 'quizteacher@example.com',
            'username' => 'quizteacher',
            'password' => bcrypt('password123'),
            'role' => UserRole::Teacher,
            'status' => 'active',
        ]);

        $subject = Subject::create([
            'code' => 'SCI101',
            'name' => 'Science',
            'description' => 'General Science',
            'grade_level' => 'Grade 7',
            'teacher_id' => $teacher->id,
            'academic_year_id' => $academicYear->id,
            'status' => 'active',
        ]);

        $schoolClass = SchoolClass::create([
            'name' => 'Science 7',
            'section' => 'A',
            'grade_level' => 'Grade 7',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $academicYear->id,
            'status' => 'active',
        ]);

        $this->actingAs($teacher);

        $payload = [
            'title' => 'Biology Quiz',
            'instructions' => 'Answer the questions below.',
            'school_class_id' => $schoolClass->id,
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'passing_score' => 60,
            'questions' => [[
                'type' => 'multiple_choice',
                'question_text' => 'Which organ pumps blood?',
                'points' => 5,
                'options' => [
                    ['option_text' => 'Heart', 'is_correct' => true],
                    ['option_text' => 'Liver', 'is_correct' => false],
                ],
            ]],
        ];

        $response = $this->post(route('teacher.quizzes.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('quizzes', ['title' => 'Biology Quiz', 'teacher_id' => $teacher->id]);
    }
}
