<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $classIds = auth()->user()->enrolledClasses()->pluck('school_classes.id');

        $announcements = Announcement::whereIn('target_audience', ['all', 'students'])
            ->where(fn ($q) => $q->whereNull('school_class_id')->orWhereIn('school_class_id', $classIds))
            ->where('published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->latest('published_at')
            ->paginate(20);

        return view('student.announcements', compact('announcements'));
    }
}
