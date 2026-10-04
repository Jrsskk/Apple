<?php

namespace App\Services;

use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\LearningMaterial;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SyncLog;
use App\Models\SyncQueue;
use App\Models\User;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function adminStats(): array
    {
        $reportService = app(AdminReportService::class);

        return [
            'stats' => [
                'students' => User::where('role', UserRole::Student)->count(),
                'teachers' => User::where('role', UserRole::Teacher)->count(),
                'subjects' => Subject::count(),
                'classes' => SchoolClass::count(),
                'quizzes' => Quiz::count(),
                'assignments' => Assignment::count(),
                'submissions' => AssignmentSubmission::whereIn('status', ['submitted', 'graded', 'late'])->count(),
                'active_users' => User::where('status', 'active')->count(),
            ],
            'system_usage' => $reportService->systemUsage(),
            'recent_activities' => AuditLog::with('user')->latest()->take(10)->get(),
            'recent_sync' => SyncLog::with('user')->latest()->take(10)->get(),
            'enrollment_chart' => $this->enrollmentChartData(),
            'completion_chart' => $this->completionChartData(),
        ];
    }

    public function teacherStats(User $teacher): array
    {
        $classIds = $teacher->taughtClasses()->pluck('id');

        return [
            'classes' => $teacher->taughtClasses()->withCount('students')->get(),
            'stats' => [
                'students' => SchoolClass::whereIn('id', $classIds)->withCount('students')->get()->sum('students_count'),
                'active_quizzes' => Quiz::whereIn('school_class_id', $classIds)->where('status', QuizStatus::Published)->count(),
                'active_assignments' => Assignment::whereIn('school_class_id', $classIds)->where('status', QuizStatus::Published)->count(),
                'pending_submissions' => AssignmentSubmission::whereHas('assignment', fn ($q) => $q->whereIn('school_class_id', $classIds))->where('status', 'submitted')->count(),
                'pending_grading' => AssignmentSubmission::whereHas('assignment', fn ($q) => $q->whereIn('school_class_id', $classIds))->whereIn('status', ['submitted', 'late'])->count(),
            ],
            'upcoming_deadlines' => $this->upcomingDeadlines($classIds),
            'recent_activity' => QuizAttempt::whereHas('quiz', fn ($q) => $q->whereIn('school_class_id', $classIds))->with(['student', 'quiz'])->latest()->take(5)->get(),
        ];
    }

    public function studentStats(User $student): array
    {
        $classIds = $student->enrolledClasses()->pluck('school_classes.id');

        return [
            'upcoming_quizzes' => Quiz::whereIn('school_class_id', $classIds)
                ->where('status', QuizStatus::Published)
                ->whereDoesntHave('attempts', fn ($query) => $query
                    ->where('student_id', $student->id)
                    ->whereNotNull('submitted_at'))
                ->where(fn ($query) => $query->whereNull('deadline')->orWhere('deadline', '>=', now()))
                ->orderBy('deadline')
                ->take(5)->get(),
            'upcoming_assignments' => Assignment::whereIn('school_class_id', $classIds)
                ->where('status', QuizStatus::Published)
                ->where(fn ($query) => $query->whereNull('deadline')->orWhere('deadline', '>=', now()))
                ->orderBy('deadline')
                ->take(5)->get(),
            'pending_count' => $this->pendingActivitiesCount($student, $classIds),
            'recent_grades' => QuizAttempt::where('student_id', $student->id)->where('status', 'graded')->latest()->take(5)->get(),
            'materials' => LearningMaterial::whereIn('school_class_id', $classIds)
                ->with('schoolClass')
                ->latest()->take(5)->get(),
            'announcements' => Announcement::whereIn('target_audience', ['all', 'students'])
                ->where(fn ($q) => $q->whereNull('school_class_id')->orWhereIn('school_class_id', $classIds))
                ->where('published_at', '<=', now())
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
                ->latest('published_at')->take(5)->get(),
            'sync_pending' => SyncQueue::where('user_id', $student->id)->where('status', 'pending')->count(),
        ];
    }

    private function enrollmentChartData(): array
    {
        $data = collect(range(5, 0))->map(function ($i) {
            $date = now()->subMonths($i);

            return [
                'label' => $date->format('M Y'),
                'count' => User::where('role', UserRole::Student)
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
            ];
        });

        return ['labels' => $data->pluck('label'), 'values' => $data->pluck('count')];
    }

    private function completionChartData(): array
    {
        $quizCompleted = QuizAttempt::where('status', 'graded')->count();
        $quizTotal = max(QuizAttempt::count(), 1);
        $assignCompleted = AssignmentSubmission::whereIn('status', ['graded', 'submitted'])->count();
        $assignTotal = max(AssignmentSubmission::count(), 1);

        return [
            'labels' => ['Quizzes', 'Assignments'],
            'values' => [round(($quizCompleted / $quizTotal) * 100), round(($assignCompleted / $assignTotal) * 100)],
        ];
    }

    private function upcomingDeadlines($classIds): array
    {
        $quizzes = Quiz::whereIn('school_class_id', $classIds)->where('deadline', '>=', now())->orderBy('deadline')->take(3)->get();
        $assignments = Assignment::whereIn('school_class_id', $classIds)->where('deadline', '>=', now())->orderBy('deadline')->take(3)->get();

        return $quizzes->concat($assignments)->sortBy('deadline')->take(5)->values()->all();
    }

    private function pendingActivitiesCount(User $student, $classIds): int
    {
        $quizzes = Quiz::whereIn('school_class_id', $classIds)->where('status', QuizStatus::Published)->pluck('id');
        $submitted = QuizAttempt::where('student_id', $student->id)
            ->whereIn('quiz_id', $quizzes)
            ->whereNotNull('submitted_at')
            ->pluck('quiz_id');
        $pendingQuizzes = $quizzes->diff($submitted)->count();

        $assignments = Assignment::whereIn('school_class_id', $classIds)->where('status', QuizStatus::Published)->pluck('id');
        $submitted = AssignmentSubmission::where('student_id', $student->id)->whereIn('assignment_id', $assignments)->whereNotIn('status', ['not_started'])->pluck('assignment_id');
        $pendingAssignments = $assignments->diff($submitted)->count();

        return $pendingQuizzes + $pendingAssignments;
    }
}
