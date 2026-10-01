<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function __construct(private AuditLogService $auditLog) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Announcements/Index', [
            'announcements' => Announcement::with(['schoolClass', 'author'])->latest('published_at')->paginate(15),
            'classes' => \App\Models\SchoolClass::with('subject')->get(['id', 'name', 'section']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'target_audience' => 'required|in:all,students,teachers',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);

        Announcement::create([
            ...$data,
            'published_at' => $data['published_at'] ?? now(),
            'created_by' => $request->user()->id,
        ]);

        $this->auditLog->log($request->user(), 'create', 'announcements', "Posted announcement: {$data['title']}");

        return back()->with('success', 'Announcement posted.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();
        $this->auditLog->log(auth()->user(), 'delete', 'announcements', 'Deleted announcement.');

        return back()->with('success', 'Announcement deleted.');
    }
}
