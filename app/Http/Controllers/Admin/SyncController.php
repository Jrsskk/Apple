<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use App\Models\SyncQueue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SyncController extends Controller
{
    public function index(Request $request): Response
    {
        $queue = SyncQueue::with('user')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15, ['*'], 'queue_page')
            ->withQueryString();

        $logs = SyncLog::with('user')
            ->when($request->log_status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15, ['*'], 'logs_page')
            ->withQueryString();

        return Inertia::render('Admin/Sync/Index', [
            'queue' => $queue,
            'logs' => $logs,
            'stats' => [
                'pending' => SyncQueue::where('status', 'pending')->count(),
                'syncing' => SyncQueue::where('status', 'syncing')->count(),
                'synced' => SyncQueue::where('status', 'synced')->count(),
                'failed' => SyncQueue::where('status', 'failed')->count(),
            ],
            'filters' => $request->only(['status', 'log_status']),
        ]);
    }
}
