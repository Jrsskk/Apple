<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeacherSubjectGradesTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_submission_and_grade_pages_filter_by_subject_and_class_relationships(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $teacher = User::where('email', 'teacher@edusync.test')->firstOrFail();
        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $classA = $teacher->taughtClasses()->firstOrFail();
        [$subjectB, $classB] = $this->createSubjectAndClass($teacher);

        $this->actingAs($teacher)
            ->post(route('teacher.assignments.store'), [
                'title' => 'Wrong Subject Assignment',
                'subject_id' => $subjectB->id,
                'school_class_id' => $classA->id,
                'max_score' => 10,
            ])
            ->assertSessionHasErrors('subject_id');
        $this->assertDatabaseMissing('assignments', ['title' => 'Wrong Subject Assignment']);

        $this->post(route('teacher.quizzes.store'), [
            'title' => 'Wrong Subject Quiz',
            'subject_id' => $subjectB->id,
            'school_class_id' => $classA->id,
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'passing_score' => 60,
            'questions' => [],
        ])->assertSessionHasErrors('subject_id');
        $this->assertDatabaseMissing('quizzes', ['title' => 'Wrong Subject Quiz']);

        $quizB = Quiz::create([
            'title' => 'Science Quiz',
            'subject_id' => $subjectB->id,
            'school_class_id' => $classB->id,
            'teacher_id' => $teacher->id,
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'passing_score' => 60,
            'total_points' => 20,
            'status' => 'published',
        ]);
        $assignmentB = Assignment::create([
            'title' => 'Science Assignment',
            'subject_id' => $subjectB->id,
            'school_class_id' => $classB->id,
            'teacher_id' => $teacher->id,
            'max_score' => 100,
            'status' => 'published',
        ]);

        $this->createQuizAttempt($quizB, $student, 18, 20);
        $this->createAssignmentSubmission($assignmentB, $student, 80);

        $assignmentA = Assignment::where('title', 'Algebra Problem Set')->firstOrFail();
        $quizA = Quiz::where('title', 'Mathematics Quiz #1')->firstOrFail();
        $this->createQuizAttempt($quizA, $student, 3, 4);
        $this->createAssignmentSubmission($assignmentA, $student, 75);

        $incorrectAssignment = Assignment::create([
            'title' => 'Misfiled Assignment',
            'subject_id' => $subjectB->id,
            'school_class_id' => $classA->id,
            'teacher_id' => $teacher->id,
            'max_score' => 10,
            'status' => 'published',
        ]);
        $this->createAssignmentSubmission($incorrectAssignment, $student, 9);
        $incorrectQuiz = Quiz::create([
            'title' => 'Misfiled Quiz',
            'subject_id' => $subjectB->id,
            'school_class_id' => $classA->id,
            'teacher_id' => $teacher->id,
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'passing_score' => 60,
            'total_points' => 10,
            'status' => 'published',
        ]);
        $this->createQuizAttempt($incorrectQuiz, $student, 9, 10);

        $this->actingAs($teacher)
            ->get(route('teacher.submissions.index', [
                'subject_id' => $subjectB->id,
                'school_class_id' => $classB->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/Submissions/Index')
                ->where('assignmentSubmissions.total', 1)
                ->where('assignmentSubmissions.data.0.assignment.subject.name', $subjectB->name)
                ->where('quizAttempts.total', 1)
                ->where('quizAttempts.data.0.quiz.subject.name', $subjectB->name));

        $this->actingAs($teacher)
            ->get(route('teacher.grades.index', [
                'subject_id' => $subjectB->id,
                'school_class_id' => $classB->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/Grades/Index')
                ->where('quizGrades.total', 1)
                ->where('assignmentGrades.total', 1)
                ->has('gradeSummary', 1)
                ->where('gradeSummary.0.subject_id', $subjectB->id)
                ->where('gradeSummary.0.student_id', $student->id)
                ->where('gradeSummary.0.activity_count', 2)
                ->where('gradeSummary.0.score', 98)
                ->where('gradeSummary.0.total_score', 120));
    }

    private function createSubjectAndClass(User $teacher): array
    {
        $subject = Subject::create([
            'code' => 'SCI-7',
            'name' => 'Science 7',
            'description' => 'Science',
            'grade_level' => 'Grade 7',
            'teacher_id' => $teacher->id,
            'academic_year_id' => AcademicYear::firstOrFail()->id,
            'status' => 'active',
        ]);
        $schoolClass = SchoolClass::create([
            'name' => 'Grade 7',
            'section' => 'B',
            'grade_level' => 'Grade 7',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $subject->academic_year_id,
            'status' => 'active',
        ]);

        return [$subject, $schoolClass];
    }

    private function createQuizAttempt(Quiz $quiz, User $student, float $score, float $total): void
    {
        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'sync_uuid' => (string) Str::uuid(),
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'score' => $score,
            'total_points' => $total,
            'status' => 'graded',
        ]);
    }

    private function createAssignmentSubmission(Assignment $assignment, User $student, float $score): void
    {
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'sync_uuid' => (string) Str::uuid(),
            'submitted_at' => now(),
            'score' => $score,
            'status' => 'graded',
        ]);
    }
}
