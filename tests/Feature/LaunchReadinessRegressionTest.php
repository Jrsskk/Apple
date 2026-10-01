<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\Announcement;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LaunchReadinessRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_student_cannot_obtain_an_api_token(): void
    {
        $student = User::factory()->student()->create([
            'email' => 'inactive@edusync.test',
            'status' => 'inactive',
        ]);

        $this->postJson('/api/v1/login', [
            'email' => $student->email,
            'password' => 'password',
            'device_name' => 'test-device',
        ])->assertForbidden();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $student->id,
        ]);
    }

    public function test_student_api_denies_draft_content_and_withdrawn_class_access(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $quiz = Quiz::firstOrFail();
        $assignment = Assignment::firstOrFail();
        $class = SchoolClass::findOrFail($quiz->school_class_id);
        $token = $student->createToken('test')->plainTextToken;

        $assignment->update(['status' => 'draft']);

        $this->withToken($token)
            ->getJson("/api/v1/assignments/{$assignment->id}")
            ->assertForbidden();

        $assignment->update(['status' => 'published']);
        $class->students()->updateExistingPivot($student->id, ['status' => 'withdrawn']);

        $this->withToken($token)
            ->getJson("/api/v1/classes/{$class->id}")
            ->assertForbidden();

        $this->withToken($token)
            ->getJson("/api/v1/quizzes/{$quiz->id}")
            ->assertForbidden();

        $this->withToken($token)
            ->getJson("/api/v1/assignments/{$assignment->id}")
            ->assertForbidden();
    }

    public function test_student_cannot_submit_an_unpublished_assignment_through_the_api(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $assignment = Assignment::firstOrFail();
        $assignment->update(['status' => 'draft']);
        $token = $student->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/submissions/assignment', [
                'assignment_id' => $assignment->id,
                'sync_uuid' => (string) Str::uuid(),
                'text_response' => 'Attempt to submit a draft.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_student_api_never_receives_quiz_answer_keys(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $quiz = Quiz::firstOrFail();
        $token = $student->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/quizzes/{$quiz->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.questions.0.options.0.is_correct')
            ->assertJsonMissingPath('data.questions.0.options.0.match_key')
            ->assertJsonMissingPath('data.questions.0.options.0.order');

        $this->withToken($token)
            ->getJson('/api/v1/sync')
            ->assertOk()
            ->assertJsonMissingPath('data.quizzes.0.questions.0.options.0.is_correct')
            ->assertJsonMissingPath('data.quizzes.0.questions.0.options.0.match_key')
            ->assertJsonMissingPath('data.quizzes.0.questions.0.options.0.order');
    }

    public function test_student_views_do_not_receive_teacher_only_class_announcements(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $teacher = User::where('email', 'teacher@edusync.test')->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $announcement = Announcement::create([
            'title' => 'Staff-only announcement',
            'message' => 'This message is for teachers only.',
            'target_audience' => 'teachers',
            'school_class_id' => $class->id,
            'published_at' => now(),
            'created_by' => $teacher->id,
        ]);

        $this->actingAs($student)
            ->get(route('student.announcements.index'))
            ->assertOk()
            ->assertDontSeeText($announcement->title);

        $announcements = app(\App\Services\DashboardService::class)->studentStats($student)['announcements'];
        $this->assertFalse($announcements->contains('id', $announcement->id));
    }

    public function test_generic_sync_endpoint_cannot_submit_draft_quiz_or_assignment(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $quiz = Quiz::firstOrFail();
        $assignment = Assignment::firstOrFail();
        $token = $student->createToken('test')->plainTextToken;

        $quiz->update(['status' => 'draft']);
        $this->withToken($token)
            ->postJson('/api/v1/sync', [
                'sync_uuid' => (string) Str::uuid(),
                'entity_type' => 'quiz_attempt',
                'entity_id' => $quiz->id,
                'action' => 'create',
                'payload' => ['quiz_id' => $quiz->id],
            ])
            ->assertForbidden();

        $assignment->update(['status' => 'draft']);
        $this->withToken($token)
            ->postJson('/api/v1/sync', [
                'sync_uuid' => (string) Str::uuid(),
                'entity_type' => 'assignment_submission',
                'entity_id' => $assignment->id,
                'action' => 'create',
                'payload' => ['assignment_id' => $assignment->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('sync_queue', 0);
        $this->assertDatabaseCount('quiz_attempts', 0);
        $this->assertDatabaseCount('assignment_submissions', 0);
    }

    public function test_sync_update_action_can_finalize_the_students_own_attempt(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $quiz = Quiz::firstOrFail();
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'sync_uuid' => (string) Str::uuid(),
            'started_at' => now(),
            'status' => 'in_progress',
        ]);
        $token = $student->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/sync', [
                'sync_uuid' => $attempt->sync_uuid,
                'entity_type' => 'quiz_attempt',
                'entity_id' => $quiz->id,
                'action' => 'update',
                'payload' => [
                    'quiz_id' => $quiz->id,
                    'attempt_number' => 1,
                    'started_at' => $attempt->started_at->toIso8601String(),
                    'submitted_at' => now()->toIso8601String(),
                    'completed_at' => now()->toIso8601String(),
                    'answers' => [],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('graded', $attempt->fresh()->status);
    }

    public function test_quiz_results_respect_result_and_review_settings(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $quiz = Quiz::firstOrFail();
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'sync_uuid' => (string) Str::uuid(),
            'started_at' => now(),
            'submitted_at' => now(),
            'status' => 'graded',
        ]);

        $quiz->update(['show_results' => false]);
        $this->actingAs($student)
            ->get(route('student.quizzes.result', [$quiz, $attempt]))
            ->assertForbidden();

        $quiz->update(['show_results' => true, 'allow_review' => false]);
        $this->get(route('student.quizzes.result', [$quiz, $attempt]))
            ->assertOk()
            ->assertDontSeeText('Review');
    }

    public function test_quiz_attempt_routes_cannot_be_reused_with_another_quiz(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $quiz = Quiz::firstOrFail();
        $otherQuiz = $quiz->replicate();
        $otherQuiz->title = 'Different quiz';
        $otherQuiz->save();

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'sync_uuid' => (string) Str::uuid(),
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $this->actingAs($student);

        $this->get(route('student.quizzes.take', [$otherQuiz, $attempt]))
            ->assertForbidden();

        $question = $quiz->questions()->firstOrFail();
        $this->postJson(route('student.quizzes.save-answer', [$otherQuiz, $attempt]), [
            'question_id' => $question->id,
            'selected_options' => [$question->options->first()->id],
        ])->assertForbidden();

        $this->assertDatabaseMissing('quiz_answers', [
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $question->id,
        ]);
    }

    public function test_in_progress_quiz_attempt_is_resumed_instead_of_blocking_the_student(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $quiz = Quiz::firstOrFail();
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'sync_uuid' => (string) Str::uuid(),
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $this->actingAs($student)
            ->get(route('student.quizzes.show', $quiz))
            ->assertRedirect(route('student.quizzes.take', [$quiz, $attempt]));

        $this->post(route('student.quizzes.start', $quiz))
            ->assertRedirect(route('student.quizzes.take', [$quiz, $attempt]));
    }

    public function test_published_quiz_without_deadline_is_listed_and_can_be_taken(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $quiz = Quiz::firstOrFail();
        $quiz->update(['deadline' => null]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText($quiz->title);

        $this->get(route('student.activities.index'))
            ->assertOk()
            ->assertSeeText($quiz->title);

        $this->get(route('student.quizzes.show', $quiz))
            ->assertOk()
            ->assertSeeText('Take Quiz');

        $response = $this->post(route('student.quizzes.start', $quiz));
        $attempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $response->assertRedirect(route('student.quizzes.take', [$quiz, $attempt]));
        $this->get(route('student.quizzes.take', [$quiz, $attempt]))
            ->assertOk()
            ->assertSeeText($quiz->title);
    }

    public function test_student_can_take_a_quiz_before_its_scheduled_start(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $quiz = Quiz::firstOrFail();
        $quiz->update(['starts_at' => now()->addDay()]);

        $this->actingAs($student)
            ->get(route('student.quizzes.show', $quiz))
            ->assertOk()
            ->assertSeeText('Take Quiz')
            ->assertDontSeeText('Quiz has not started yet.');

        $response = $this->post(route('student.quizzes.start', $quiz));
        $attempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $response->assertRedirect(route('student.quizzes.take', [$quiz, $attempt]));
        $this->get(route('student.quizzes.take', [$quiz, $attempt]))
            ->assertOk()
            ->assertSeeText($quiz->title);

        $this->get(route('student.quizzes.offline', $quiz))
            ->assertOk();
    }

    public function test_matching_and_sequencing_questions_can_be_answered_and_restored(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $quiz = Quiz::firstOrFail();
        $matching = $quiz->questions()->create([
            'type' => 'matching',
            'question_text' => 'Match each term.',
            'points' => 2,
            'order' => 3,
        ]);
        $left = $matching->options()->create([
            'option_text' => 'Term A',
            'is_correct' => false,
            'match_key' => 'pair_a',
            'order' => 0,
        ]);
        $right = $matching->options()->create([
            'option_text' => 'Definition A',
            'is_correct' => true,
            'match_key' => 'pair_a',
            'order' => 1,
        ]);

        $sequence = $quiz->questions()->create([
            'type' => 'sequencing',
            'question_text' => 'Order the steps.',
            'points' => 2,
            'order' => 4,
        ]);
        $firstStep = $sequence->options()->create([
            'option_text' => 'First step',
            'is_correct' => false,
            'order' => 0,
        ]);
        $secondStep = $sequence->options()->create([
            'option_text' => 'Second step',
            'is_correct' => false,
            'order' => 1,
        ]);

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'sync_uuid' => (string) Str::uuid(),
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $this->actingAs($student);

        $this->get(route('student.quizzes.offline', $quiz))
            ->assertOk()
            ->assertSeeText('Offline mode');

        $this->postJson(route('student.quizzes.save-answer', [$quiz, $attempt]), [
            'question_id' => $matching->id,
            'selected_options' => [$left->id => $right->id],
        ])->assertOk();

        $this->postJson(route('student.quizzes.save-answer', [$quiz, $attempt]), [
            'question_id' => $sequence->id,
            'selected_options' => [$firstStep->id, $secondStep->id],
        ])->assertOk();

        $this->get(route('student.quizzes.take', [$quiz, $attempt]))
            ->assertOk()
            ->assertSeeText('Choose a match')
            ->assertSeeText('Arrange the items in the correct order.')
            ->assertSeeText('Move up');

        $this->assertDatabaseHas('quiz_answers', [
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $matching->id,
        ]);
        $this->assertDatabaseHas('quiz_answers', [
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $sequence->id,
        ]);

        app(\App\Services\GradingService::class)->autoGradeAttempt($attempt);

        $this->assertDatabaseHas('quiz_answers', [
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $matching->id,
            'is_correct' => true,
        ]);
        $this->assertDatabaseHas('quiz_answers', [
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $sequence->id,
            'is_correct' => true,
        ]);
    }

    public function test_server_enforces_quiz_duration_and_grades_expired_submission(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $quiz = Quiz::firstOrFail();
        $quiz->update(['duration_minutes' => 1]);
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'sync_uuid' => (string) Str::uuid(),
            'started_at' => now()->subMinutes(2),
            'status' => 'in_progress',
        ]);

        $this->actingAs($student);

        $this->postJson(route('student.quizzes.save-answer', [$quiz, $attempt]), [
            'question_id' => $quiz->questions()->firstOrFail()->id,
            'selected_options' => [],
        ])->assertForbidden();

        $this->post(route('student.quizzes.submit', [$quiz, $attempt]))
            ->assertRedirect(route('student.quizzes.result', [$quiz, $attempt]));

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertEquals(
            $attempt->started_at->copy()->addMinute()->toDateTimeString(),
            $attempt->submitted_at->toDateTimeString()
        );
    }

    public function test_teacher_cannot_create_assignment_for_another_teachers_class(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $teacher = User::where('email', 'teacher@edusync.test')->firstOrFail();
        $otherTeacher = User::factory()->teacher()->create();
        $year = AcademicYear::firstOrFail();
        $foreignSubject = Subject::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'academic_year_id' => $year->id,
        ]);
        $foreignClass = SchoolClass::create([
            'name' => 'Other teacher class',
            'section' => 'A',
            'grade_level' => 'Grade 7',
            'teacher_id' => $otherTeacher->id,
            'subject_id' => $foreignSubject->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.assignments.store'), [
                'title' => 'Unauthorized assignment',
                'school_class_id' => $foreignClass->id,
                'subject_id' => $foreignSubject->id,
                'max_score' => 100,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('assignments', [
            'title' => 'Unauthorized assignment',
        ]);
    }

    public function test_teacher_cannot_create_quiz_for_another_teachers_class(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $teacher = User::where('email', 'teacher@edusync.test')->firstOrFail();
        $otherTeacher = User::factory()->teacher()->create();
        $year = AcademicYear::firstOrFail();
        $foreignSubject = Subject::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'academic_year_id' => $year->id,
        ]);
        $foreignClass = SchoolClass::create([
            'name' => 'Other teacher class',
            'section' => 'A',
            'grade_level' => 'Grade 7',
            'teacher_id' => $otherTeacher->id,
            'subject_id' => $foreignSubject->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.store'), [
                'title' => 'Unauthorized quiz',
                'subject_id' => $foreignSubject->id,
                'school_class_id' => $foreignClass->id,
                'duration_minutes' => 30,
                'max_attempts' => 1,
                'passing_score' => 60,
                'questions' => [[
                    'type' => 'multiple_choice',
                    'question_text' => 'Question',
                    'points' => 1,
                    'options' => [
                        ['option_text' => 'Correct', 'is_correct' => true],
                        ['option_text' => 'Incorrect', 'is_correct' => false],
                    ],
                ]],
            ])
            ->assertSessionHasErrors('school_class_id');

        $this->assertDatabaseMissing('quizzes', [
            'title' => 'Unauthorized quiz',
        ]);
    }

    public function test_teacher_content_stays_draft_until_explicitly_published(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $teacher = User::where('email', 'teacher@edusync.test')->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $this->actingAs($teacher);

        $this->post(route('teacher.assignments.store'), [
            'title' => 'Assignment draft',
            'school_class_id' => $class->id,
            'subject_id' => $class->subject_id,
            'max_score' => 100,
        ])->assertRedirect();

        $this->assertDatabaseHas('assignments', [
            'title' => 'Assignment draft',
            'status' => 'draft',
        ]);

        $this->post(route('teacher.quizzes.store'), [
            'title' => 'Quiz draft',
            'subject_id' => $class->subject_id,
            'school_class_id' => $class->id,
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'passing_score' => 60,
        ])->assertRedirect();

        $this->assertDatabaseHas('quizzes', [
            'title' => 'Quiz draft',
            'status' => 'draft',
        ]);
    }

    public function test_incomplete_quiz_cannot_be_created_as_published(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $teacher = User::where('email', 'teacher@edusync.test')->firstOrFail();
        $class = SchoolClass::firstOrFail();

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.store'), [
                'title' => 'Incomplete published quiz',
                'subject_id' => $class->subject_id,
                'school_class_id' => $class->id,
                'duration_minutes' => 30,
                'max_attempts' => 1,
                'passing_score' => 60,
                'status' => 'published',
            ])
            ->assertSessionHasErrors('questions');

        $this->assertDatabaseMissing('quizzes', [
            'title' => 'Incomplete published quiz',
        ]);
    }

    public function test_class_enrollment_rejects_non_student_accounts(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $teacher = User::where('email', 'teacher@edusync.test')->firstOrFail();
        $admin = User::where('email', 'admin@edusync.test')->firstOrFail();
        $class = SchoolClass::firstOrFail();

        $this->actingAs($teacher)
            ->post(route('teacher.classes.enroll', $class), [
                'student_ids' => [$admin->id],
            ])
            ->assertSessionHasErrors('student_ids.0');

        $this->assertDatabaseMissing('class_students', [
            'school_class_id' => $class->id,
            'student_id' => $admin->id,
        ]);
    }
}
