<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use App\Models\SyncQueue;
use Illuminate\View\View;

class SyncMonitorController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        return view('student.sync', [
            'serverPending' => SyncQueue::where('user_id', $user->id)->where('status', 'pending')->count(),
            'serverFailed' => SyncQueue::where('user_id', $user->id)->where('status', 'failed')->count(),
            'recentLogs' => SyncLog::where('user_id', $user->id)->latest()->take(20)->get(),
        ]);
    }
}
