<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EduSyncSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'employee_number' => 'ADM001',
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => 'admin@edusync.test',
            'username' => 'admin',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $teacher = User::create([
            'employee_number' => 'TCH001',
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
            'last_name' => 'Reyes',
            'email' => 'teacher@edusync.test',
            'username' => 'teacher',
            'password' => Hash::make('password'),
            'role' => UserRole::Teacher,
            'status' => UserStatus::Active,
        ]);

        $student = User::create([
            'employee_number' => 'STU001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'student@edusync.test',
            'username' => 'student',
            'password' => Hash::make('password'),
            'role' => UserRole::Student,
            'status' => UserStatus::Active,
        ]);

        for ($i = 2; $i <= 6; $i++) {
            User::create([
                'employee_number' => 'STU00'.$i,
                'first_name' => 'Student',
                'last_name' => "Number {$i}",
                'email' => "student{$i}@edusync.test",
                'username' => "student{$i}",
                'password' => Hash::make('password'),
                'role' => UserRole::Student,
                'status' => UserStatus::Active,
            ]);
        }

        $year = AcademicYear::create([
            'name' => '2026–2027',
            'start_date' => '2026-06-01',
            'end_date' => '2027-03-31',
            'is_active' => true,
            'status' => 'open',
        ]);

        $grade11Subjects = [
            'Life and Career Skills',
            'General Science',
            'General Mathematics',
            'Effective Communication',
            'Mabisang Komunikasyon',
            'Elective 1 Broad Band Installation',
            'Elective 1 Creative Composition 2',
            'Pag-aaral ng Kasaysayan at Lipunang Pilipino',
        ];

        $grade12Subjects = [
            'Computer Systems Servicing',
            'Discipline and Ideas in the Applied Social Sciences',
            'Physical Education and Health',
            'Introduction to Philosophy',
            'Research 2',
            'Creative Nonfiction',
            'Trends, Networks, and Critical Thinking in the 21st Century',
        ];

        foreach (array_merge($grade11Subjects, $grade12Subjects) as $subjectName) {
            Subject::create([
                'code' => Subject::generateUniqueCode($subjectName),
                'name' => $subjectName,
                'description' => 'Academic track subject for '. (in_array($subjectName, $grade11Subjects, true) ? 'Grade 11' : 'Grade 12') .'.',
                'grade_level' => in_array($subjectName, $grade11Subjects, true) ? 'Grade 11' : 'Grade 12',
                'teacher_id' => $teacher->id,
                'academic_year_id' => $year->id,
            ]);
        }

        $subject = Subject::create([
            'code' => 'MATH7',
            'name' => 'Mathematics 7',
            'description' => 'Grade 7 Mathematics',
            'grade_level' => 'Grade 7',
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
        ]);

        $class = SchoolClass::create([
            'name' => 'Grade 7',
            'section' => 'A',
            'grade_level' => '7',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'schedule' => 'MWF 8:00-9:00 AM',
            'room' => 'Room 201',
        ]);

        $students = User::where('role', UserRole::Student)->pluck('id');
        $class->students()->attach($students->mapWithKeys(fn ($id) => [$id => ['enrolled_at' => now(), 'status' => 'enrolled']]));

        $quiz = Quiz::create([
            'title' => 'Mathematics Quiz #1',
            'instructions' => 'Answer all questions. Good luck!',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'starts_at' => now()->subDay(),
            'deadline' => now()->addWeek(),
            'duration_minutes' => 30,
            'max_attempts' => 2,
            'passing_score' => 60,
            'status' => 'published',
        ]);

        $q1 = QuizQuestion::create(['quiz_id' => $quiz->id, 'type' => 'multiple_choice', 'question_text' => 'What is the capital of the Philippines?', 'points' => 2, 'order' => 0]);
        foreach (['Cebu', 'Manila', 'Davao', 'Bacolod'] as $i => $city) {
            $q1->options()->create(['option_text' => $city, 'is_correct' => $city === 'Manila', 'order' => $i]);
        }

        $q2 = QuizQuestion::create(['quiz_id' => $quiz->id, 'type' => 'true_false', 'question_text' => 'The Philippines has 7,641 islands.', 'points' => 1, 'order' => 1]);
        $q2->options()->createMany([
            ['option_text' => 'True', 'is_correct' => true, 'order' => 0],
            ['option_text' => 'False', 'is_correct' => false, 'order' => 1],
        ]);

        $q3 = QuizQuestion::create(['quiz_id' => $quiz->id, 'type' => 'identification', 'question_text' => 'What is 7 × 8?', 'points' => 1, 'order' => 2]);
        $q3->options()->create(['option_text' => '56', 'is_correct' => true, 'order' => 0]);

        $quiz->update(['total_points' => 4]);

        Assignment::create([
            'title' => 'Algebra Problem Set',
            'description' => 'Complete exercises 1-10',
            'instructions' => 'Show your solution for each problem.',
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'posted_at' => now(),
            'deadline' => now()->addDays(10),
            'max_score' => 100,
            'status' => 'published',
        ]);

        Announcement::create([
            'title' => 'Welcome to EduSync!',
            'message' => 'Welcome to the new school year. Check your classes and upcoming activities regularly.',
            'target_audience' => 'students',
            'published_at' => now(),
            'created_by' => $admin->id,
        ]);
    }
}
