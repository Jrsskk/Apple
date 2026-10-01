<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\LearningMaterial;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClassJoinTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_create_class_generates_class_code(): void
    {
        $teacher = User::factory()->teacher()->create();
        $year = AcademicYear::factory()->create();
        $subject = Subject::factory()->create(['teacher_id' => $teacher->id, 'academic_year_id' => $year->id]);

        $response = $this->actingAs($teacher)->post(route('teacher.classes.store'), [
            'name' => 'Grade 8',
            'section' => 'B',
            'grade_level' => '8',
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
        ]);

        $class = SchoolClass::where('name', 'Grade 8')->first();

        $this->assertNotEmpty($class->class_code);
        $response->assertRedirect(route('teacher.classes.show', $class));
        $response->assertSessionHas('success');
    }

    public function test_new_class_gets_unique_class_code(): void
    {
        $teacher = User::factory()->teacher()->create();
        $year = AcademicYear::factory()->create();
        $subject = Subject::factory()->create(['teacher_id' => $teacher->id, 'academic_year_id' => $year->id]);

        $class = SchoolClass::create([
            'name' => 'Grade 7',
            'section' => 'A',
            'grade_level' => '7',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
        ]);

        $this->assertNotEmpty($class->class_code);
        $this->assertSame(6, strlen($class->class_code));
    }

    public function test_teacher_create_subject_generates_unique_code_without_manual_code(): void
    {
        $teacher = User::factory()->teacher()->create();
        $year = AcademicYear::factory()->create();

        $response = $this->actingAs($teacher)->post(route('teacher.subjects.store'), [
            'name' => 'Biology',
            'grade_level' => 'Grade 10',
            'academic_year_id' => $year->id,
            'description' => 'Science subject',
        ]);

        $subject = Subject::where('name', 'Biology')->first();

        $this->assertNotNull($subject);
        $this->assertNotEmpty($subject->code);
        $this->assertSame('BIOLOGY', $subject->code);
        $response->assertSessionHas('success');
    }

    public function test_student_can_join_class_with_valid_code(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $year = AcademicYear::factory()->create();
        $subject = Subject::factory()->create(['teacher_id' => $teacher->id, 'academic_year_id' => $year->id]);

        $class = SchoolClass::create([
            'name' => 'Grade 7',
            'section' => 'A',
            'grade_level' => '7',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
        ]);

        $response = $this->actingAs($student)->post(route('student.classes.join'), [
            'class_code' => strtolower($class->class_code),
        ]);

        $response->assertRedirect(route('student.classes.show', $class));
        $this->assertTrue($student->fresh()->enrolledClasses()->where('school_classes.id', $class->id)->exists());
    }

    public function test_student_cannot_join_with_invalid_code(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->from(route('student.classes.index'))->post(route('student.classes.join'), [
            'class_code' => 'INVALID',
        ]);

        $response->assertRedirect(route('student.classes.index'));
        $response->assertSessionHasErrors('class_code');
    }

    public function test_student_cannot_join_same_class_twice(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $year = AcademicYear::factory()->create();
        $subject = Subject::factory()->create(['teacher_id' => $teacher->id, 'academic_year_id' => $year->id]);

        $class = SchoolClass::create([
            'name' => 'Grade 7',
            'section' => 'A',
            'grade_level' => '7',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
        ]);

        $class->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'enrolled']);

        $response = $this->actingAs($student)->from(route('student.classes.index'))->post(route('student.classes.join'), [
            'class_code' => $class->class_code,
        ]);

        $response->assertRedirect(route('student.classes.index'));
        $response->assertSessionHasErrors('class_code');
    }

    public function test_teacher_cannot_access_student_join_route(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->post(route('student.classes.join'), [
            'class_code' => 'ABC123',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_student_can_join_class_via_api(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $year = AcademicYear::factory()->create();
        $subject = Subject::factory()->create(['teacher_id' => $teacher->id, 'academic_year_id' => $year->id]);

        $class = SchoolClass::create([
            'name' => 'Math 101',
            'section' => 'Section 1',
            'grade_level' => '10',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
        ]);

        $token = $student->createToken('test')->plainTextToken;

        // Verify initially 0 classes
        $this->withToken($token)->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonPath('data', []);

        // Join via API
        $response = $this->withToken($token)->postJson('/api/v1/classes/join', [
            'class_code' => $class->class_code,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.id', $class->id);
        $response->assertJsonPath('data.name', 'Math 101');
        $response->assertJsonPath('data.teacher.name', $teacher->full_name);
        $response->assertJsonPath('data.subject.name', $subject->name);

        // Fetch My Classes
        $classesResponse = $this->withToken($token)->getJson('/api/v1/classes')->assertOk();
        $classesResponse->assertJsonCount(1, 'data');
        $classesResponse->assertJsonPath('data.0.id', $class->id);
        $classesResponse->assertJsonPath('data.0.name', 'Math 101');
        $classesResponse->assertJsonPath('data.0.teacher.name', $teacher->full_name);
    }

    public function test_sync_pull_returns_quizzes_and_assignments_for_joined_class(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $year = AcademicYear::factory()->create();
        $subject = Subject::factory()->create(['teacher_id' => $teacher->id, 'academic_year_id' => $year->id]);

        $class = SchoolClass::create([
            'name' => 'Science 10',
            'section' => 'A',
            'grade_level' => '10',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
        ]);

        $quiz = Quiz::create([
            'title' => 'Physics Quiz 1',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'deadline' => now()->addDays(3),
        ]);

        $assignment = Assignment::create([
            'title' => 'Lab Report 1',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'deadline' => now()->addDays(5),
            'max_score' => 100,
        ]);

        $token = $student->createToken('test')->plainTextToken;

        // Student joins class
        $this->withToken($token)->postJson('/api/v1/classes/join', [
            'class_code' => $class->class_code,
        ])->assertOk();

        // Student syncs
        $syncResponse = $this->withToken($token)->getJson('/api/v1/sync')->assertOk();
        $syncResponse->assertJsonCount(1, 'data.classes');
        $syncResponse->assertJsonCount(1, 'data.quizzes');
        $syncResponse->assertJsonCount(1, 'data.assignments');
        $syncResponse->assertJsonPath('data.quizzes.0.title', 'Physics Quiz 1');
        $syncResponse->assertJsonPath('data.assignments.0.title', 'Lab Report 1');
    }

    public function test_sync_pull_returns_learning_materials_for_joined_class(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $year = AcademicYear::factory()->create();
        $subject = Subject::factory()->create(['teacher_id' => $teacher->id, 'academic_year_id' => $year->id]);

        $class = SchoolClass::create([
            'name' => 'Biology 11',
            'section' => 'B',
            'grade_level' => '11',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
        ]);

        $material = LearningMaterial::create([
            'title' => 'Cell Structure Notes',
            'description' => 'Study notes for the unit test.',
            'file_path' => 'materials/cell-structure.pdf',
            'file_type' => 'application/pdf',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'uploaded_by' => $teacher->id,
        ]);

        $token = $student->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/classes/join', [
            'class_code' => $class->class_code,
        ])->assertOk();

        $this->withToken($token)->getJson('/api/v1/materials')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $material->id)
            ->assertJsonPath('data.0.title', 'Cell Structure Notes');

        $syncResponse = $this->withToken($token)->getJson('/api/v1/sync')->assertOk();
        $syncResponse->assertJsonCount(1, 'data.classes');
        $syncResponse->assertJsonCount(1, 'data.materials');
        $syncResponse->assertJsonPath('data.materials.0.id', $material->id);
        $syncResponse->assertJsonPath('data.materials.0.title', 'Cell Structure Notes');
        $syncResponse->assertJsonPath('data.materials.0.file_type', 'application/pdf');
    }

    public function test_student_can_preview_and_download_enrolled_class_materials(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $year = AcademicYear::factory()->create();
        $subject = Subject::factory()->create(['teacher_id' => $teacher->id, 'academic_year_id' => $year->id]);

        $class = SchoolClass::create([
            'name' => 'Biology 11',
            'section' => 'A',
            'grade_level' => '11',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
        ]);

        $student->enrolledClasses()->attach($class->id);

        Storage::fake('local');
        Storage::disk('local')->put('materials/cell-structure.pdf', 'pdf-content');

        $material = LearningMaterial::create([
            'title' => 'Cell Structure Notes',
            'description' => 'Study notes for the unit test.',
            'file_path' => 'materials/cell-structure.pdf',
            'file_type' => 'application/pdf',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'uploaded_by' => $teacher->id,
        ]);

        $previewResponse = $this->actingAs($student)->get(route('student.materials.file', $material));
        $previewResponse->assertOk();
        $this->assertStringContainsString('inline', strtolower((string) $previewResponse->headers->get('Content-Disposition')));

        $downloadResponse = $this->actingAs($student)->get(route('student.materials.file', ['material' => $material, 'download' => 1]));
        $downloadResponse->assertOk();
        $this->assertStringContainsString('attachment', strtolower((string) $downloadResponse->headers->get('Content-Disposition')));
    }

    public function test_student_receives_updated_published_class_content_after_sync(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $year = AcademicYear::factory()->create();
        $subject = Subject::factory()->create(['teacher_id' => $teacher->id, 'academic_year_id' => $year->id]);

        $class = SchoolClass::create([
            'name' => 'Chemistry 12',
            'section' => 'A',
            'grade_level' => '12',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
        ]);

        $quiz = Quiz::create([
            'title' => 'Week 1 Quiz',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'deadline' => now()->addDay(),
        ]);

        $question = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'type' => 'multiple_choice',
            'question_text' => 'What is the atomic number of hydrogen?',
            'points' => 5,
            'order' => 1,
        ]);

        QuestionOption::create([
            'quiz_question_id' => $question->id,
            'option_text' => '1',
            'is_correct' => true,
            'order' => 1,
        ]);

        $assignment = Assignment::create([
            'title' => 'Lab Reflection',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'deadline' => now()->addDays(2),
            'max_score' => 100,
        ]);

        $material = LearningMaterial::create([
            'title' => 'Acid Base Notes',
            'description' => 'Reference sheet',
            'file_path' => 'materials/acid-base.pdf',
            'file_type' => 'application/pdf',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'uploaded_by' => $teacher->id,
        ]);

        $token = $student->createToken('test')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/classes/join', ['class_code' => $class->class_code])->assertOk();

        $beforeUpdate = now()->toIso8601String();
        $quiz->update(['title' => 'Week 1 Quiz Updated']);
        $assignment->update(['title' => 'Lab Reflection Updated']);
        $material->update(['title' => 'Acid Base Notes Updated']);

        $response = $this->withToken($token)->getJson('/api/v1/sync?since='.urlencode($beforeUpdate))->assertOk();

        $response->assertJsonPath('data.quizzes.0.id', $quiz->id);
        $response->assertJsonPath('data.quizzes.0.title', 'Week 1 Quiz Updated');
        $response->assertJsonPath('data.quizzes.0.questions.0.id', $question->id);
        $response->assertJsonPath('data.quizzes.0.questions.0.options.0.id', $question->options->first()->id);
        $response->assertJsonPath('data.assignments.0.id', $assignment->id);
        $response->assertJsonPath('data.assignments.0.title', 'Lab Reflection Updated');
        $response->assertJsonPath('data.materials.0.id', $material->id);
        $response->assertJsonPath('data.materials.0.title', 'Acid Base Notes Updated');
        $response->assertJsonPath('data.materials.0.file_url', $material->file_url);
    }
}
