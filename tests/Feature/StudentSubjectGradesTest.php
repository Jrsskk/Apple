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
use Tests\TestCase;

class StudentSubjectGradesTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_grades_are_grouped_by_subject_and_inconsistent_subject_records_are_excluded(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $student = User::where('email', 'student@edusync.test')->firstOrFail();
        $teacher = User::where('email', 'teacher@edusync.test')->firstOrFail();
        $mathClass = $teacher->taughtClasses()->firstOrFail();
        $science = Subject::create([
            'code' => 'SCI7',
            'name' => 'Science 7',
            'description' => 'Science',
            'grade_level' => 'Grade 7',
            'teacher_id' => $teacher->id,
            'academic_year_id' => AcademicYear::firstOrFail()->id,
            'status' => 'active',
        ]);
        $scienceClass = SchoolClass::create([
            'name' => 'Grade 7',
            'section' => 'B',
            'grade_level' => 'Grade 7',
            'subject_id' => $science->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $science->academic_year_id,
            'status' => 'active',
        ]);
        $scienceClass->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'enrolled']);

        $mathQuiz = Quiz::where('title', 'Mathematics Quiz #1')->firstOrFail();
        $scienceQuiz = $this->createQuiz($teacher, $science, $scienceClass, 'Science Quiz', 10);
        $this->createQuizAttempt($student, $mathQuiz, 3, 4);
        $this->createQuizAttempt($student, $scienceQuiz, 8, 10);

        $mathAssignment = Assignment::where('title', 'Algebra Problem Set')->firstOrFail();
        $scienceAssignment = $this->createAssignment($teacher, $science, $scienceClass, 'Science Report', 7, 10);
        $this->createAssignmentSubmission($student, $mathAssignment, 75);
        $this->createAssignmentSubmission($student, $scienceAssignment, 7);

        $misfiledQuiz = $this->createQuiz($teacher, $science, $mathClass, 'Misfiled Quiz', 10);
        $this->createQuizAttempt($student, $misfiledQuiz, 9, 10);
        $misfiledAssignment = $this->createAssignment($teacher, $science, $mathClass, 'Misfiled Assignment', 9, 10);
        $this->createAssignmentSubmission($student, $misfiledAssignment, 9);

        $this->actingAs($student)
            ->get(route('student.grades.index'))
            ->assertOk()
            ->assertSeeText('Mathematics 7')
            ->assertSeeText('Science 7')
            ->assertSeeText('Science Quiz')
            ->assertSeeText('Science Report')
            ->assertSeeText('75%')
            ->assertDontSeeText('Misfiled Quiz')
            ->assertDontSeeText('Misfiled Assignment');
    }

    private function createQuiz(User $teacher, Subject $subject, SchoolClass $schoolClass, string $title, float $total): Quiz
    {
        $quiz = Quiz::create([
            'title' => $title,
            'subject_id' => $subject->id,
            'school_class_id' => $schoolClass->id,
            'teacher_id' => $teacher->id,
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'passing_score' => 60,
            'total_points' => $total,
            'status' => 'published',
        ]);

        return $quiz;
    }

    private function createQuizAttempt(User $student, Quiz $quiz, float $score, float $total): void
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

    private function createAssignment(User $teacher, Subject $subject, SchoolClass $schoolClass, string $title, float $score, float $total): Assignment
    {
        return Assignment::create([
            'title' => $title,
            'subject_id' => $subject->id,
            'school_class_id' => $schoolClass->id,
            'teacher_id' => $teacher->id,
            'max_score' => $total,
            'status' => 'published',
        ]);
    }

    private function createAssignmentSubmission(User $student, Assignment $assignment, float $score): void
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
