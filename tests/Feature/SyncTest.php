<?php

namespace Tests\Feature;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_sync_uuid_is_idempotent(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);
        $student = User::where('email', 'student@edusync.test')->first();
        $quiz = \App\Models\Quiz::first();
        $uuid = Str::uuid()->toString();

        $payload = [
            'sync_uuid' => $uuid,
            'entity_type' => 'quiz_attempt',
            'action' => 'create',
            'payload' => [
                'quiz_id' => $quiz->id,
                'attempt_number' => 1,
                'started_at' => now()->toIso8601String(),
                'completed_at' => now()->toIso8601String(),
                'submitted_at' => now()->toIso8601String(),
                'answers' => $quiz->questions->map(fn ($q) => [
                    'question_id' => $q->id,
                    'selected_options' => $q->options->where('is_correct', true)->pluck('id')->values()->all(),
                    'answer_text' => $q->type->value === 'identification' ? '56' : null,
                ])->all(),
            ],
            'device_id' => 'test-device',
        ];

        $token = $student->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/sync', $payload)->assertOk();
        $this->withToken($token)->postJson('/api/v1/sync', $payload)->assertOk();

        $this->assertEquals(1, \App\Models\QuizAttempt::where('sync_uuid', $uuid)->count());
    }

    public function test_student_pull_returns_teacher_changes_and_new_grades(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);
        $student = User::where('email', 'student@edusync.test')->first();
        $class = $student->enrolledClasses()->first();
        $quiz = \App\Models\Quiz::first();
        $assignment = \App\Models\Assignment::first();
        $token = $student->createToken('test')->plainTextToken;

        $initial = $this->withToken($token)->getJson('/api/v1/sync')->assertOk();
        $since = $initial->json('data.synced_at');

        $this->travel(2)->seconds();
        $quiz->update(['title' => 'Edited quiz title']);
        $assignment->update(['deadline' => now()->addDays(20)]);
        $draftQuiz = $quiz->replicate();
        $draftQuiz->title = 'Draft quiz';
        $draftQuiz->status = 'draft';
        $draftQuiz->save();
        $draftQuiz->update(['status' => 'published']);
        $draftAssignment = $assignment->replicate();
        $draftAssignment->title = 'Draft assignment';
        $draftAssignment->status = 'draft';
        $draftAssignment->save();
        $draftAssignment->update(['status' => 'published']);
        \App\Models\Announcement::create([
            'title' => 'New announcement', 'message' => 'Read this update.',
            'target_audience' => 'students', 'published_at' => now(), 'created_by' => $quiz->teacher_id,
        ]);
        \App\Models\AssignmentSubmission::create([
            'assignment_id' => $assignment->id, 'student_id' => $student->id,
            'sync_uuid' => Str::uuid(), 'status' => 'graded', 'score' => 88,
            'feedback' => 'Good work.', 'submitted_at' => now(), 'version' => 1,
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/sync?since='.urlencode($since))->assertOk();
        $response->assertJsonPath('data.quizzes.0.title', 'Edited quiz title');
        $this->assertContains($draftQuiz->id, collect($response->json('data.quizzes'))->pluck('id')->all());
        $this->assertContains($draftAssignment->id, collect($response->json('data.assignments'))->pluck('id')->all());
        $this->assertContains('New announcement', collect($response->json('data.announcements'))->pluck('title')->all());
        $this->assertContains(88, collect($response->json('data.grades'))->pluck('score')->all());

        $nextSince = $response->json('data.synced_at');
        $this->travel(2)->seconds();
        $quiz->update(['title' => 'Edited quiz title again']);

        $nextResponse = $this->withToken($token)
            ->getJson('/api/v1/sync?since='.urlencode($nextSince))
            ->assertOk();
        $this->assertSame(
            'Edited quiz title again',
            collect($nextResponse->json('data.quizzes'))->firstWhere('id', $quiz->id)['title'],
        );
    }

    public function test_new_teacher_content_is_immediately_visible_to_students(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);
        $student = User::where('email', 'student@edusync.test')->first();
        $teacher = User::where('email', 'teacher@edusync.test')->first();
        $class = $student->enrolledClasses()->first();
        $token = $student->createToken('test')->plainTextToken;

        $quiz = app(\App\Services\QuizService::class)->create([
            'title' => 'New student quiz',
            'instructions' => 'Answer this question.',
            'subject_id' => $class->subject_id,
            'school_class_id' => $class->id,
            'starts_at' => now(),
            'deadline' => now()->addDay(),
            'duration_minutes' => 15,
            'max_attempts' => 1,
            'passing_score' => 60,
            'status' => 'published',
            'questions' => [
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Which number is 2 + 2?',
                    'points' => 1,
                    'order' => 1,
                    'options' => [
                        ['option_text' => '3', 'is_correct' => false, 'order' => 1],
                        ['option_text' => '4', 'is_correct' => true, 'order' => 2],
                    ],
                ],
            ],
        ], $teacher);

        $assignment = app(\App\Services\AssignmentService::class)->create($teacher, [
            'title' => 'New student assignment',
            'description' => 'Read the chapter.',
            'instructions' => 'Submit your summary.',
            'subject_id' => $class->subject_id,
            'school_class_id' => $class->id,
            'deadline' => now()->addDay(),
            'max_score' => 10,
            'allow_resubmit' => false,
            'status' => 'published',
        ]);

        \App\Models\LearningMaterial::create([
            'title' => 'New learning material',
            'description' => 'Student handout',
            'school_class_id' => $class->id,
            'subject_id' => $class->subject_id,
            'uploaded_by' => $teacher->id,
            'file_path' => 'materials/sample.pdf',
            'file_type' => 'application/pdf',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/sync')->assertOk();

        $this->assertContains($quiz->id, collect($response->json('data.quizzes'))->pluck('id')->all());
        $this->assertContains($assignment->id, collect($response->json('data.assignments'))->pluck('id')->all());
        $this->assertContains('New learning material', collect($response->json('data.materials'))->pluck('title')->all());

        $quiz->update(['title' => 'Updated student quiz']);
        $assignment->update(['title' => 'Updated student assignment']);

        $since = $response->json('data.synced_at');
        $updated = $this->withToken($token)->getJson('/api/v1/sync?since='.urlencode($since))->assertOk();

        $this->assertSame('Updated student quiz', collect($updated->json('data.quizzes'))->firstWhere('id', $quiz->id)['title']);
        $this->assertSame('Updated student assignment', collect($updated->json('data.assignments'))->firstWhere('id', $assignment->id)['title']);
    }
}
