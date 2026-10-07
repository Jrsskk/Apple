<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\LearningMaterial;
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

    public function test_teacher_content_indexes_filter_out_items_misfiled_under_another_subject(): void
    {
        $this->seed(\Database\Seeders\EduSyncSeeder::class);

        $teacher = User::where('email', 'teacher@edusync.test')->firstOrFail();
        $classA = $teacher->taughtClasses()->firstOrFail();
        $subjectA = Subject::findOrFail($classA->subject_id);
        [$subjectB, $classB] = $this->createSubjectAndClass($teacher);

        foreach ([
            ['Quiz A', $subjectA->id, $classA->id],
            ['Quiz B', $subjectB->id, $classB->id],
            ['Misfiled Quiz', $subjectB->id, $classA->id],
        ] as [$title, $subjectId, $classId]) {
            Quiz::create([
                'title' => $title,
                'subject_id' => $subjectId,
                'school_class_id' => $classId,
                'teacher_id' => $teacher->id,
                'duration_minutes' => 30,
                'max_attempts' => 1,
                'passing_score' => 60,
                'status' => 'draft',
            ]);
        }

        foreach ([
            ['Assignment A', $subjectA->id, $classA->id],
            ['Assignment B', $subjectB->id, $classB->id],
            ['Misfiled Assignment', $subjectB->id, $classA->id],
        ] as [$title, $subjectId, $classId]) {
            Assignment::create([
                'title' => $title,
                'subject_id' => $subjectId,
                'school_class_id' => $classId,
                'teacher_id' => $teacher->id,
                'max_score' => 100,
                'status' => 'draft',
            ]);
        }

        foreach ([
            ['Material A', $subjectA->id, $classA->id],
            ['Material B', $subjectB->id, $classB->id],
            ['Misfiled Material', $subjectB->id, $classA->id],
        ] as [$title, $subjectId, $classId]) {
            LearningMaterial::create([
                'title' => $title,
                'file_path' => "materials/{$title}.pdf",
                'subject_id' => $subjectId,
                'school_class_id' => $classId,
                'uploaded_by' => $teacher->id,
            ]);
        }

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.index', ['subject_id' => $subjectB->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/Quizzes/Index')
                ->where('quizzes.total', 1)
                ->where('quizzes.data.0.title', 'Quiz B')
                ->where('quizzes.data.0.subject.name', $subjectB->name)
                ->where('quizzes.data.0.school_class.id', $classB->id));

        $this->actingAs($teacher)
            ->get(route('teacher.assignments.index', ['subject_id' => $subjectB->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/Assignments/Index')
                ->where('assignments.total', 1)
                ->where('assignments.data.0.title', 'Assignment B')
                ->where('assignments.data.0.subject.name', $subjectB->name)
                ->where('assignments.data.0.school_class.id', $classB->id));

        $this->actingAs($teacher)
            ->get(route('teacher.materials.index', ['subject_id' => $subjectB->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/Materials/Index')
                ->where('materials.total', 1)
                ->where('materials.data.0.title', 'Material B')
                ->where('materials.data.0.subject.id', $subjectB->id)
                ->where('materials.data.0.school_class.id', $classB->id));

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('quizzes.total', 3));

        $this->actingAs($teacher)
            ->get(route('teacher.assignments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('assignments.total', 3));

        $this->actingAs($teacher)
            ->get(route('teacher.materials.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('materials.total', 2));
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
