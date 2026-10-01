<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AuditLog::with('user')
            ->when($request->module, fn ($q, $m) => $q->where('module', $m))
            ->when($request->action, fn ($q, $a) => $q->where('action', $a))
            ->when($request->search, fn ($q, $s) => $q->where('description', 'like', "%{$s}%"))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $modules = AuditLog::distinct()->pluck('module');
        $actions = AuditLog::distinct()->pluck('action');

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['module', 'action', 'search']),
            'modules' => $modules,
            'actions' => $actions,
        ]);
    }
}
