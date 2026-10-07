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

    public function test_submitted_quizzes_are_removed_from_student_available_quizzes(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $quiz = Quiz::firstOrFail();
        $quiz->update(['deadline' => null]);

        $availableQuiz = $quiz->replicate();
        $availableQuiz->title = 'Another available quiz';
        $availableQuiz->save();

        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'sync_uuid' => Str::uuid(),
            'started_at' => now()->subMinutes(5),
            'submitted_at' => now(),
            'completed_at' => now(),
            'status' => 'graded',
        ]);

        QuizAttempt::create([
            'quiz_id' => $availableQuiz->id,
            'student_id' => $student->id,
            'sync_uuid' => Str::uuid(),
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $upcomingQuizzes = app(\App\Services\DashboardService::class)
            ->studentStats($student)['upcoming_quizzes'];

        $this->assertFalse($upcomingQuizzes->contains('id', $quiz->id));
        $this->assertTrue($upcomingQuizzes->contains('id', $availableQuiz->id));

        $token = $student->createToken('test')->plainTextToken;
        $apiQuizIds = collect($this->withToken($token)->getJson('/api/v1/quizzes')->assertOk()->json('data'))
            ->pluck('id');
        $this->assertFalse($apiQuizIds->contains($quiz->id));
        $this->assertTrue($apiQuizIds->contains($availableQuiz->id));

        $this->actingAs($student)
            ->get(route('student.classes.show', $quiz->school_class_id))
            ->assertOk()
            ->assertDontSeeText($quiz->title)
            ->assertSeeText($availableQuiz->title);
    }

    public function test_teacher_quiz_creation_requires_an_explicit_subject_selection(): void
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

        $response->assertSessionHasErrors('subject_id');
        $this->assertDatabaseMissing('quizzes', ['title' => 'Biology Quiz']);

        $payload['subject_id'] = $subject->id;
        $response = $this->post(route('teacher.quizzes.store'), $payload);
        $response->assertRedirect();
        $this->assertDatabaseHas('quizzes', ['title' => 'Biology Quiz', 'teacher_id' => $teacher->id]);
    }

    public function test_teacher_quiz_edits_persist_and_are_returned_by_the_edit_page(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);
        $quiz = Quiz::with('questions')->firstOrFail();
        $teacher = $quiz->teacher;
        $previousQuestionIds = $quiz->questions->modelKeys();

        $payload = [
            'title' => 'Updated quiz title',
            'instructions' => 'Updated instructions',
            'subject_id' => $quiz->subject_id,
            'school_class_id' => $quiz->school_class_id,
            'starts_at' => $quiz->starts_at?->format('Y-m-d H:i:s'),
            'deadline' => $quiz->deadline?->format('Y-m-d H:i:s'),
            'duration_minutes' => 45,
            'max_attempts' => 2,
            'passing_score' => 80,
            'randomize_questions' => true,
            'randomize_choices' => false,
            'show_results' => true,
            'allow_review' => true,
            'status' => 'draft',
            'questions' => [[
                'type' => 'multiple_choice',
                'question_text' => 'Updated question text?',
                'points' => 4,
                'explanation' => 'Updated explanation',
                'options' => [
                    ['option_text' => 'Updated correct answer', 'is_correct' => true],
                    ['option_text' => 'Updated incorrect answer', 'is_correct' => false],
                ],
            ]],
        ];

        $this->actingAs($teacher)
            ->put(route('teacher.quizzes.update', $quiz), $payload)
            ->assertRedirect(route('teacher.quizzes.edit', $quiz))
            ->assertSessionHas('success', 'Quiz updated.');

        $quiz->refresh();
        $this->assertSame('Updated quiz title', $quiz->title);
        $this->assertSame('Updated instructions', $quiz->instructions);
        $this->assertSame(45, $quiz->duration_minutes);
        $this->assertSame(2, $quiz->max_attempts);
        $this->assertSame(80.0, $quiz->passing_score);
        $this->assertTrue($quiz->randomize_questions);
        $this->assertDatabaseHas('quiz_questions', [
            'quiz_id' => $quiz->id,
            'question_text' => 'Updated question text?',
            'points' => 4,
        ]);
        $this->assertDatabaseHas('question_options', [
            'option_text' => 'Updated correct answer',
            'is_correct' => true,
        ]);
        foreach ($previousQuestionIds as $questionId) {
            $this->assertDatabaseMissing('quiz_questions', ['id' => $questionId]);
        }

        $this->get(route('teacher.quizzes.edit', $quiz))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Teacher/Quizzes/Edit')
                ->where('quiz.title', 'Updated quiz title')
                ->where('quiz.questions.0.question_text', 'Updated question text?')
                ->where('quiz.questions.0.options.0.option_text', 'Updated correct answer'));
    }
}
