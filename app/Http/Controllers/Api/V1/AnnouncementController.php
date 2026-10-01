<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classIds = $user->enrolledClasses()->pluck('school_classes.id');

        $announcements = Announcement::query()
            ->where(function ($query) use ($classIds) {
                $query->whereNull('school_class_id')
                    ->orWhereIn('school_class_id', $classIds);
            })
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['author:id,first_name,last_name', 'schoolClass'])
            ->latest('published_at')
            ->get()
            ->filter(fn (Announcement $announcement) => $user->can('view', $announcement))
            ->values();

        return $this->success($announcements);
    }

    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('view', $announcement);

        $announcement->load(['author', 'schoolClass']);

        return $this->success($announcement);
    }
}
