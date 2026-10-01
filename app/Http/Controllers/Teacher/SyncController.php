<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\QuizAttempt;
use App\Models\SyncQueue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SyncController extends Controller
{
    public function index(Request $request): Response
    {
        $classIds = $request->user()->taughtClasses()->pluck('id');
        $studentIds = \App\Models\SchoolClass::whereIn('id', $classIds)
            ->with('students')
            ->get()
            ->flatMap(fn ($c) => $c->students->pluck('id'))
            ->unique();

        $pendingQueue = SyncQueue::whereIn('user_id', $studentIds)
            ->where('status', 'pending')
            ->with('user')
            ->latest()
            ->take(20)
            ->get();

        $recentSynced = SyncQueue::whereIn('user_id', $studentIds)
            ->where('status', 'synced')
            ->with('user')
            ->latest('synced_at')
            ->take(20)
            ->get();

        $failedQueue = SyncQueue::whereIn('user_id', $studentIds)
            ->where('status', 'failed')
            ->with('user')
            ->latest()
            ->take(20)
            ->get();

        $pendingQuizAttempts = QuizAttempt::whereHas(
            'quiz',
            fn ($q) => $q->whereIn('school_class_id', $classIds)
        )
            ->whereNotNull('submitted_at')
            ->whereNull('synced_at')
            ->with(['student', 'quiz'])
            ->latest()
            ->take(20)
            ->get();

        $pendingAssignmentSubmissions = AssignmentSubmission::whereHas(
            'assignment',
            fn ($q) => $q->whereIn('school_class_id', $classIds)
        )
            ->whereIn('status', ['submitted', 'late'])
            ->whereNull('synced_at')
            ->with(['student', 'assignment'])
            ->latest()
            ->take(20)
            ->get();

        $syncedQuizAttempts = QuizAttempt::whereHas(
            'quiz',
            fn ($q) => $q->whereIn('school_class_id', $classIds)
        )
            ->whereNotNull('synced_at')
            ->with(['student', 'quiz'])
            ->latest('synced_at')
            ->take(20)
            ->get();

        return Inertia::render('Teacher/Sync/Index', [
            'stats' => [
                'pending_queue' => $pendingQueue->count(),
                'failed_queue' => $failedQueue->count(),
                'pending_quiz_submissions' => $pendingQuizAttempts->count(),
                'pending_assignment_submissions' => $pendingAssignmentSubmissions->count(),
            ],
            'pendingQueue' => $pendingQueue,
            'failedQueue' => $failedQueue,
            'pendingQuizAttempts' => $pendingQuizAttempts,
            'pendingAssignmentSubmissions' => $pendingAssignmentSubmissions,
            'syncedQuizAttempts' => $syncedQuizAttempts,
        ]);
    }
}
