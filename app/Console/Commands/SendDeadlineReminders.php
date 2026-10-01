<?php

namespace App\Console\Commands;

use App\Jobs\SendDeadlineReminderJob;
use App\Models\Assignment;
use App\Models\Quiz;
use Illuminate\Console\Command;

class SendDeadlineReminders extends Command
{
    protected $signature = 'edusync:send-deadline-reminders';

    protected $description = 'Send deadline reminders for quizzes and assignments due within 24 hours';

    public function handle(): int
    {
        $windowStart = now();
        $windowEnd = now()->addDay();

        $quizzes = Quiz::where('status', 'published')
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [$windowStart, $windowEnd])
            ->pluck('id');

        foreach ($quizzes as $quizId) {
            SendDeadlineReminderJob::dispatch('quiz', $quizId);
        }

        $assignments = Assignment::whereNotNull('deadline')
            ->whereBetween('deadline', [$windowStart, $windowEnd])
            ->pluck('id');

        foreach ($assignments as $assignmentId) {
            SendDeadlineReminderJob::dispatch('assignment', $assignmentId);
        }

        $this->info("Queued {$quizzes->count()} quiz and {$assignments->count()} assignment reminders.");

        return self::SUCCESS;
    }
}
