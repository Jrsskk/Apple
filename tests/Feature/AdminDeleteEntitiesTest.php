<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDeleteEntitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_subject_without_code(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();
        $year = AcademicYear::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'name' => 'Research 2',
            'description' => 'Capstone research course.',
            'grade_level' => 'Grade 12',
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $this->assertDatabaseHas('subjects', [
            'name' => 'Research 2',
            'grade_level' => 'Grade 12',
        ]);
        $this->assertDatabaseHas('subjects', [
            'name' => 'Research 2',
            'code' => 'RESEARCH-2',
        ]);
    }

    public function test_admin_cannot_create_class_for_subject_taught_by_different_teacher(): void
    {
        $admin = User::factory()->admin()->create();
        $teacherA = User::factory()->teacher()->create();
        $teacherB = User::factory()->teacher()->create();
        $year = AcademicYear::factory()->create();
        $subject = Subject::factory()->create([
            'teacher_id' => $teacherA->id,
            'academic_year_id' => $year->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.classes.store'), [
            'name' => 'Mathematics',
            'section' => 'A',
            'grade_level' => 'Grade 8',
            'subject_id' => $subject->id,
            'teacher_id' => $teacherB->id,
            'academic_year_id' => $year->id,
            'schedule' => 'Mon/Wed 9:00',
            'room' => 'Room 1',
        ]);

        $response->assertSessionHasErrors('subject_id');
        $this->assertDatabaseMissing('school_classes', ['name' => 'Mathematics']);
    }

    public function test_admin_can_delete_subject(): void
    {
        $admin = User::factory()->admin()->create();
        $year = AcademicYear::factory()->create();
        $subject = Subject::factory()->create([
            'teacher_id' => User::factory()->teacher()->create()->id,
            'academic_year_id' => $year->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.subjects.destroy', $subject));

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('subjects', ['id' => $subject->id]);
    }

    public function test_admin_can_delete_class(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();
        $year = AcademicYear::factory()->create();
        $subject = Subject::factory()->create([
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
        ]);
        $class = SchoolClass::create([
            'name' => 'Mathematics',
            'section' => 'A',
            'grade_level' => 'Grade 8',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'class_code' => 'ABC123',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.classes.destroy', $class));

        $response->assertRedirect(route('admin.classes.index'));
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('school_classes', ['id' => $class->id]);
    }
}
