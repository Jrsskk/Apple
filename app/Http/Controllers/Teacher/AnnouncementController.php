<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $classIds = $request->user()->taughtClasses()->pluck('id');

        $announcements = Announcement::where('created_by', $request->user()->id)
            ->orWhereIn('school_class_id', $classIds)
            ->with(['schoolClass'])
            ->latest('published_at')
            ->paginate(15);

        $classes = $request->user()->taughtClasses()->get(['id', 'name', 'section']);

        return Inertia::render('Teacher/Announcements/Index', [
            'announcements' => $announcements,
            'classes' => $classes,
        ]);
    }

    public function store(Request $request, NotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'target_audience' => 'required|in:all,students,teachers',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
        ]);

        if ($data['school_class_id']) {
            abort_unless(
                $request->user()->taughtClasses()->where('id', $data['school_class_id'])->exists(),
                403
            );
        }

        $announcement = Announcement::create([
            ...$data,
            'published_at' => $data['published_at'] ?? now(),
            'created_by' => $request->user()->id,
        ]);
        $announcement->load('schoolClass');
        $notifications->notifyAnnouncementStudents($announcement);

        return back()->with('success', 'Announcement posted.');
    }

    public function update(Request $request, Announcement $announcement, NotificationService $notifications): RedirectResponse
    {
        $this->authorizeAnnouncement($announcement);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'target_audience' => 'required|in:all,students,teachers',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'expires_at' => 'nullable|date',
        ]);

        $announcement->update($data);
        $announcement->load('schoolClass');
        $notifications->notifyAnnouncementStudents($announcement);

        return back()->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->authorizeAnnouncement($announcement);
        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }

    private function authorizeAnnouncement(Announcement $announcement): void
    {
        abort_unless(
            $announcement->created_by === auth()->id() || auth()->user()->isAdmin(),
            403
        );
    }
}
