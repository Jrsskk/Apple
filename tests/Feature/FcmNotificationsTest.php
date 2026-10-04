<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\Announcement;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FcmNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_register_update_and_remove_an_encrypted_fcm_token(): void
    {
        $student = User::factory()->student()->create();
        $apiToken = $student->createToken('student-device')->plainTextToken;
        $fcmToken = Str::random(180);

        $this->withToken($apiToken)
            ->postJson('/api/v1/device-tokens', [
                'token' => $fcmToken,
                'platform' => 'android',
                'device_name' => 'Test phone',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $device = $student->fcmDeviceTokens()->sole();
        $this->assertSame($fcmToken, $device->token);
        $this->assertNotSame($fcmToken, DB::table('fcm_device_tokens')->value('token'));
        $this->assertSame(hash('sha256', $fcmToken), $device->token_hash);

        $otherStudent = User::factory()->student()->create();
        $otherApiToken = $otherStudent->createToken('other-device')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otherApiToken)
            ->postJson('/api/v1/device-tokens', [
                'token' => $fcmToken,
                'platform' => 'web',
                'device_name' => 'Shared browser',
            ])
            ->assertCreated();

        $this->assertDatabaseCount('fcm_device_tokens', 1);
        $this->assertSame($otherStudent->id, $device->fresh()->user_id);
        $this->assertSame('web', $device->fresh()->platform);

        $this->app['auth']->forgetGuards();
        $this->withToken($apiToken)
            ->deleteJson('/api/v1/device-tokens', ['token' => $fcmToken])
            ->assertOk();
        $this->assertDatabaseCount('fcm_device_tokens', 1);

        $this->app['auth']->forgetGuards();
        $this->withToken($otherApiToken)
            ->deleteJson('/api/v1/device-tokens', ['token' => $fcmToken])
            ->assertOk();
        $this->assertDatabaseCount('fcm_device_tokens', 0);
    }

    public function test_class_announcement_notifies_only_active_enrolled_students_in_its_class_and_subject(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $withdrawnStudent = User::factory()->student()->create();
        $otherClassStudent = User::factory()->student()->create();
        $year = AcademicYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-06-01',
            'end_date' => '2027-03-31',
            'is_active' => true,
            'status' => 'active',
        ]);
        $subject = Subject::create([
            'code' => 'FCM-101',
            'name' => 'Science',
            'grade_level' => 'Grade 7',
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);
        $schoolClass = SchoolClass::create([
            'name' => 'Science',
            'section' => 'A',
            'grade_level' => 'Grade 7',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);
        $otherClass = SchoolClass::create([
            'name' => 'Science',
            'section' => 'B',
            'grade_level' => 'Grade 7',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);
        $schoolClass->students()->attach([
            $student->id => ['enrolled_at' => now(), 'status' => 'enrolled'],
            $withdrawnStudent->id => ['enrolled_at' => now(), 'status' => 'withdrawn'],
        ]);
        $otherClass->students()->attach($otherClassStudent->id, [
            'enrolled_at' => now(),
            'status' => 'enrolled',
        ]);

        $announcement = Announcement::create([
            'title' => 'Science lab schedule',
            'message' => 'The laboratory schedule is now available.',
            'target_audience' => 'students',
            'school_class_id' => $schoolClass->id,
            'published_at' => now(),
            'created_by' => $teacher->id,
        ]);
        $announcement->load('schoolClass');

        app(NotificationService::class)->notifyAnnouncementStudents($announcement);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $student->id,
            'data->title' => 'Science lab schedule',
        ]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $withdrawnStudent->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $otherClassStudent->id]);
    }

    public function test_publishing_an_assignment_creates_notifications_for_its_class_students(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $teacher = User::where('email', 'teacher@edusync.test')->firstOrFail();
        $teacher->forceFill(['email_verified_at' => now()])->save();
        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $outsider = User::factory()->student()->create();
        $assignment = Assignment::firstOrFail();

        $this->actingAs($teacher)
            ->post(route('teacher.assignments.publish', $assignment))
            ->assertRedirect();

        $this->assertSame(1, $student->notifications()->where('data->assignment_id', $assignment->id)->count());
        $this->assertSame(0, $outsider->notifications()->count());
    }

    public function test_student_can_mark_one_or_all_database_notifications_as_read(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $student->notify(new \App\Notifications\SyncNotification('Sync completed', 'Your work is synced.'));
        $otherStudent->notify(new \App\Notifications\SyncNotification('Other user', 'Private message.'));
        $notification = $student->notifications()->firstOrFail();

        $this->actingAs($student)
            ->post(route('student.notifications.read', $notification->id))
            ->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);

        $student->notify(new \App\Notifications\SyncNotification('Another update', 'A second message.'));
        $this->actingAs($student)
            ->post(route('student.notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $student->unreadNotifications()->count());
        $this->assertSame(1, $otherStudent->unreadNotifications()->count());
    }

    public function test_student_notification_page_shows_read_state_and_timestamp(): void
    {
        $student = User::factory()->student()->create();
        $student->notify(new \App\Notifications\SyncNotification('Sync completed', 'Your work is synced.'));

        $this->actingAs($student)
            ->get(route('student.notifications'))
            ->assertOk()
            ->assertSee('Sync completed')
            ->assertSee('Unread')
            ->assertSee('Mark as Read')
            ->assertSee('datetime=');
    }

    public function test_firebase_service_worker_uses_only_public_web_configuration(): void
    {
        config([
            'services.firebase.web' => [
                'api_key' => 'public-api-key',
                'auth_domain' => 'edusync-test.firebaseapp.com',
                'project_id' => 'edusync-test',
                'messaging_sender_id' => '123456789',
                'app_id' => '1:123456789:web:abcdef',
                'vapid_key' => 'public-vapid-key',
            ],
            'services.firebase.credentials_json' => '{"private_key":"server-only"}',
        ]);

        $this->get(route('firebase.messaging.service-worker'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->assertSee('edusync-test')
            ->assertDontSee('server-only');
    }
}
