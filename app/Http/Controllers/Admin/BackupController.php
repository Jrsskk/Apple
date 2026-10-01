<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupLog;
use App\Services\AuditLogService;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(
        private BackupService $backups,
        private AuditLogService $auditLog,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Backups/Index', [
            'backups' => $this->backups->list(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $backup = $this->backups->create($request->user());
            $this->auditLog->log($request->user(), 'create', 'backups', "Created backup {$backup->filename}.");

            return back()->with('success', 'Backup created successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Backup failed: '.$e->getMessage());
        }
    }

    public function download(BackupLog $backup): BinaryFileResponse
    {
        return response()->download($this->backups->downloadPath($backup));
    }

    public function restore(BackupLog $backup): RedirectResponse
    {
        try {
            $this->backups->restore($backup);
            $this->auditLog->log(auth()->user(), 'restore', 'backups', "Restored from {$backup->filename}.");

            return back()->with('success', 'Database restored from backup.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Restore failed: '.$e->getMessage());
        }
    }

    public function destroy(BackupLog $backup): RedirectResponse
    {
        $this->backups->delete($backup);
        $this->auditLog->log(auth()->user(), 'delete', 'backups', "Deleted backup {$backup->filename}.");

        return back()->with('success', 'Backup deleted.');
    }
}
