<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeacherAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_analytics_uses_real_scores_and_scopes_data_to_selected_class(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $teacher = User::where('email', 'teacher@edusync.test')->firstOrFail();
        $year = AcademicYear::firstOrFail();
        $subject = Subject::create([
            'code' => 'ANALYTICS-1',
            'name' => 'Analytics Mathematics',
            'grade_level' => 'Grade 7',
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);
        $class = SchoolClass::create([
            'name' => 'Grade 7',
            'section' => 'Analytics',
            'grade_level' => 'Grade 7',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);
        $student = User::factory()->student()->create();
        $class->students()->attach($student->id, ['status' => 'enrolled', 'enrolled_at' => now()]);

        $quiz = Quiz::create([
            'title' => 'Analytics Quiz',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'starts_at' => now()->subDay(),
            'deadline' => now()->addDay(),
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'passing_score' => 60,
            'total_points' => 10,
            'status' => 'published',
        ]);
        $question = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'type' => 'multiple_choice',
            'question_text' => 'What is 2 + 2?',
            'points' => 10,
            'order' => 0,
        ]);
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'sync_uuid' => (string) Str::uuid(),
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'score' => 8,
            'percentage' => 80,
            'total_points' => 10,
            'status' => 'graded',
        ]);
        QuizAnswer::create([
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $question->id,
            'answer_text' => '4',
            'is_correct' => true,
            'points_earned' => 8,
        ]);

        $assignment = Assignment::create([
            'title' => 'Analytics Assignment',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'max_score' => 100,
            'status' => 'published',
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'sync_uuid' => (string) Str::uuid(),
            'submitted_at' => now(),
            'score' => 50,
            'status' => 'graded',
        ]);
        Quiz::create([
            'title' => 'Overdue Analytics Quiz',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'deadline' => now()->subDay(),
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'total_points' => 10,
            'status' => 'published',
        ]);
        Assignment::create([
            'title' => 'Pending Analytics Assignment',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'deadline' => now()->addDay(),
            'max_score' => 100,
            'status' => 'published',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.analytics.index', ['school_class_id' => $class->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/Analytics/Index')
                ->where('stats.students', 1)
                ->where('stats.average_score', 65)
                ->where('stats.quiz_completion_rate', 50)
                ->where('stats.assignment_completion_rate', 50)
                ->where('stats.submission_rate', 50)
                ->where('stats.participation_rate', 100)
                ->where('stats.completed_activities', 2)
                ->where('stats.pending_activities', 1)
                ->where('stats.overdue_activities', 1)
                ->where('charts.questionPerformance.byQuestion.0.accuracy', 100)
                ->where('charts.questionPerformance.highest.type', 'Multiple Choice')
                ->where('students.0.average_score', 65));
    }

    public function test_teacher_cannot_filter_analytics_to_another_teachers_class(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $teacher = User::where('email', 'teacher@edusync.test')->firstOrFail();
        $otherTeacher = User::factory()->teacher()->create();
        $year = AcademicYear::firstOrFail();
        $subject = Subject::create([
            'code' => 'OTHER-TEACHER',
            'name' => 'Other Teacher Subject',
            'grade_level' => 'Grade 7',
            'teacher_id' => $otherTeacher->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);
        $class = SchoolClass::create([
            'name' => 'Grade 7',
            'section' => 'Other',
            'grade_level' => 'Grade 7',
            'subject_id' => $subject->id,
            'teacher_id' => $otherTeacher->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.analytics.index', ['school_class_id' => $class->id]))
            ->assertSessionHasErrors('filters');
    }
}
