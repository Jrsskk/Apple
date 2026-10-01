<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SyncLog;
use App\Models\SyncQueue;
use App\Models\User;
use Illuminate\Support\Collection;

class AdminReportService
{
    public function systemUsage(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'students' => User::where('role', UserRole::Student)->count(),
            'teachers' => User::where('role', UserRole::Teacher)->count(),
            'subjects' => Subject::count(),
            'classes' => SchoolClass::count(),
            'quizzes' => Quiz::count(),
            'assignments' => Assignment::count(),
            'quiz_attempts' => QuizAttempt::count(),
            'assignment_submissions' => AssignmentSubmission::count(),
            'pending_sync' => SyncQueue::where('status', 'pending')->count(),
            'failed_sync' => SyncQueue::where('status', 'failed')->count(),
        ];
    }

    public function academicSummary(): array
    {
        return SchoolClass::with(['subject', 'teacher'])
            ->withCount('students')
            ->get()
            ->map(fn (SchoolClass $class) => [
                'class' => $class->display_name,
                'subject' => $class->subject?->name,
                'teacher' => $class->teacher?->full_name,
                'students' => $class->students_count,
                'quizzes' => $class->quizzes()->count(),
                'assignments' => $class->assignments()->count(),
            ])
            ->all();
    }

    public function enrollmentReportCsv(): string
    {
        $headers = ['Class', 'Subject', 'Teacher', 'Students', 'Quizzes', 'Assignments'];
        $rows = collect($this->academicSummary())->map(fn ($r) => [
            $r['class'], $r['subject'], $r['teacher'], $r['students'], $r['quizzes'], $r['assignments'],
        ]);

        return $this->toCsv($headers, $rows);
    }

    public function systemReportCsv(): string
    {
        $usage = $this->systemUsage();
        $headers = ['Metric', 'Value'];
        $rows = collect($usage)->map(fn ($v, $k) => [str_replace('_', ' ', ucfirst($k)), $v]);

        return $this->toCsv($headers, $rows);
    }

    public function userReportCsv(?string $role = null): string
    {
        $users = User::when($role, fn ($q) => $q->where('role', $role))->get();
        $headers = ['Name', 'Email', 'Username', 'Role', 'Status', 'Created'];
        $rows = $users->map(fn (User $u) => [
            $u->full_name, $u->email, $u->username,
            $u->role->value ?? $u->role,
            $u->status->value ?? $u->status,
            $u->created_at?->toDateTimeString(),
        ]);

        return $this->toCsv($headers, $rows);
    }

    public function syncReportCsv(): string
    {
        $logs = SyncLog::with('user')->latest()->take(500)->get();
        $headers = ['User', 'Action', 'Status', 'Message', 'Date'];
        $rows = $logs->map(fn (SyncLog $log) => [
            $log->user?->full_name,
            $log->action,
            $log->status,
            $log->message,
            $log->created_at?->toDateTimeString(),
        ]);

        return $this->toCsv($headers, $rows);
    }

    private function toCsv(array $headers, Collection $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }
}
