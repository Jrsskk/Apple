<?php

namespace Tests\Feature;

use App\Enums\QuizStatus;
use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupabaseStorageIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_attachments_and_student_submissions_upload_download_and_replace_in_private_storage(): void
    {
        $this->fakeSupabaseStorage();
        [$teacher, $student, $class, $subject] = $this->createClassWithStudent();
        $assignment = Assignment::create([
            'title' => 'Research task',
            'description' => 'Submit your work.',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'status' => QuizStatus::Published,
            'max_score' => 100,
        ]);

        $this->actingAs($teacher)
            ->put(route('teacher.assignments.update', $assignment), [
                'title' => 'Research task',
                'description' => 'Submit your work.',
                'instructions' => 'Attach a document.',
                'max_score' => 100,
                'allow_resubmit' => true,
                'attachment' => UploadedFile::fake()->create('instructions.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame('supabase', $assignment->attachment_storage_disk);
        $this->assertNotNull($assignment->attachment_path);
        $this->assertSame('instructions.pdf', $assignment->attachment_file_name);
        $this->assertSame(10 * 1024, $assignment->attachment_file_size);
        $firstAttachmentPath = $assignment->attachment_path;

        $this->actingAs($teacher)
            ->put(route('teacher.assignments.update', $assignment), [
                'title' => 'Research task',
                'description' => 'Submit your work.',
                'instructions' => 'Attach a document.',
                'max_score' => 100,
                'allow_resubmit' => true,
                'attachment' => UploadedFile::fake()->create('instructions-updated.pdf', 12, 'application/pdf'),
            ])
            ->assertRedirect();

        $assignment->refresh();
        $this->assertNotSame($firstAttachmentPath, $assignment->attachment_path);
        $this->assertSame('instructions-updated.pdf', $assignment->attachment_file_name);

        $this->actingAs($student)
            ->get(route('student.assignments.attachment', $assignment))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/pdf');

        $this->post(route('student.assignments.submit', $assignment), [
            'text_response' => 'My first submission',
            'file' => UploadedFile::fake()->create('answer.pdf', 15, 'application/pdf'),
        ])->assertRedirect(route('student.activities.index'));

        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)->firstOrFail();
        $firstSubmissionPath = $submission->file_path;
        $this->assertSame('supabase', $submission->storage_disk);

        $this->post(route('student.assignments.submit', $assignment), [
            'text_response' => 'Updated submission',
            'file' => UploadedFile::fake()->create('answer-updated.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('student.activities.index'));

        $submission->refresh();
        $this->assertNotSame($firstSubmissionPath, $submission->file_path);
        $this->assertSame('supabase', $submission->storage_disk);
        $this->assertSame('answer-updated.pdf', $submission->file_name);
        $this->assertSame(20 * 1024, $submission->file_size);

        $this->actingAs($teacher)
            ->get(route('teacher.submissions.assignment.download', $submission))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        Http::assertSent(fn (ClientRequest $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/storage/v1/object/assignments')
            && json_decode($request->body(), true)['prefixes'][0] === $firstAttachmentPath);
        Http::assertSent(fn (ClientRequest $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/storage/v1/object/assignments')
            && json_decode($request->body(), true)['prefixes'][0] === $firstSubmissionPath);

        app(AssignmentService::class)->delete($assignment);
        $this->assertSoftDeleted('assignments', ['id' => $assignment->id]);
        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'file_path' => null,
            'storage_disk' => null,
        ]);
    }

    public function test_profile_photos_are_private_and_can_be_deleted(): void
    {
        $this->fakeSupabaseStorage();
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->post(route('teacher.profile.avatar'), [
                'avatar' => UploadedFile::fake()->createWithContent(
                    'profile.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/gZkAAAAASUVORK5CYII='),
                ),
            ])
            ->assertRedirect();

        $teacher->refresh();
        $this->assertSame('supabase', $teacher->profile_image_storage_disk);
        $this->assertGreaterThan(0, $teacher->profile_image_file_size);
        $firstPhotoPath = $teacher->profile_image;
        $this->actingAs($teacher)
            ->post(route('teacher.profile.avatar'), [
                'avatar' => UploadedFile::fake()->createWithContent(
                    'updated-profile.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/gZkAAAAASUVORK5CYII='),
                ),
            ])
            ->assertRedirect();
        $teacher->refresh();
        $this->assertNotSame($firstPhotoPath, $teacher->profile_image);
        $this->actingAs($teacher)
            ->get(route('profile.photo'))
            ->assertOk();

        $this->delete(route('teacher.profile.avatar.destroy'))->assertRedirect();
        $this->assertNull($teacher->fresh()->profile_image);
        $this->assertNull($teacher->fresh()->profile_image_storage_disk);
    }

    public function test_storage_setup_creates_missing_buckets_and_makes_existing_buckets_private(): void
    {
        config([
            'services.supabase.url' => 'https://supabase.test',
            'services.supabase.service_role_key' => 'test-service-role-key',
        ]);
        $createCount = 0;
        Http::fake(function (ClientRequest $request) use (&$createCount) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/storage/v1/bucket')) {
                $createCount++;

                return $createCount === 1
                    ? Http::response(['id' => 'materials'])
                    : Http::response(['message' => 'Bucket already exists'], 409);
            }
            if ($request->method() === 'PUT' && str_contains($request->url(), '/storage/v1/bucket/')) {
                return Http::response([]);
            }

            return Http::response(['message' => 'Unexpected test request.'], 500);
        });

        $this->artisan('supabase:storage-setup')->assertExitCode(0);

        foreach (['materials', 'assignments', 'profiles'] as $bucket) {
            Http::assertSent(fn (ClientRequest $request) => $request->method() === 'POST'
                && str_ends_with($request->url(), '/storage/v1/bucket')
                && data_get(json_decode($request->body(), true), 'id') === $bucket
                && data_get(json_decode($request->body(), true), 'public') === false);
        }
        foreach (['assignments', 'profiles'] as $bucket) {
            Http::assertSent(fn (ClientRequest $request) => $request->method() === 'PUT'
                && str_ends_with($request->url(), '/storage/v1/bucket/'.$bucket)
                && data_get(json_decode($request->body(), true), 'public') === false);
        }
    }

    public function test_storage_setup_accepts_supabase_resource_already_exists_response(): void
    {
        config([
            'services.supabase.url' => 'https://supabase.test',
            'services.supabase.service_role_key' => 'test-service-role-key',
        ]);
        Http::fake(function (ClientRequest $request) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/storage/v1/bucket')) {
                return Http::response(['message' => 'The resource already exists'], 400);
            }
            if ($request->method() === 'PUT' && str_contains($request->url(), '/storage/v1/bucket/')) {
                return Http::response([]);
            }

            return Http::response(['message' => 'Unexpected test request.'], 500);
        });

        $this->artisan('supabase:storage-setup')->assertExitCode(0);

        Http::assertSent(fn (ClientRequest $request) => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/storage/v1/bucket/materials')
            && data_get(json_decode($request->body(), true), 'public') === false);
    }

    /**
     * @return array{User, User, SchoolClass, Subject}
     */
    private function createClassWithStudent(): array
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $year = AcademicYear::factory()->create();
        $subject = Subject::factory()->create([
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
        ]);
        $class = SchoolClass::create([
            'name' => 'Biology 11',
            'section' => 'A',
            'grade_level' => '11',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
        ]);
        $student->enrolledClasses()->attach($class->id);

        return [$teacher, $student, $class, $subject];
    }

    private function fakeSupabaseStorage(): void
    {
        config([
            'services.supabase.url' => 'https://supabase.test',
            'services.supabase.key' => 'test-anon-key',
            'services.supabase.service_role_key' => 'test-service-role-key',
        ]);

        $objects = [];
        Http::fake(function (ClientRequest $request) use (&$objects) {
            $url = $request->url();
            if ($request->method() === 'POST' && str_ends_with($url, '/storage/v1/bucket')) {
                return Http::response(['message' => 'Bucket already exists'], 409);
            }
            if ($request->method() === 'PUT' && str_contains($url, '/storage/v1/bucket/')) {
                return Http::response([]);
            }

            if (preg_match('~/storage/v1/object/([^/]+)/(.+)$~', $url, $matches)) {
                $key = $matches[1].'/'.rawurldecode($matches[2]);
                if ($request->method() === 'POST') {
                    $objects[$key] = $request->body();

                    return Http::response(['Key' => $key]);
                }
                if ($request->method() === 'GET') {
                    return isset($objects[$key])
                        ? Http::response($objects[$key])
                        : Http::response(['message' => 'Not found'], 404);
                }
            }

            if ($request->method() === 'DELETE' && preg_match('~/storage/v1/object/([^/]+)$~', $url, $matches)) {
                foreach (json_decode($request->body(), true)['prefixes'] ?? [] as $path) {
                    unset($objects[$matches[1].'/'.$path]);
                }

                return Http::response([]);
            }

            return Http::response(['message' => 'Unexpected test storage request.'], 500);
        });
    }
}
