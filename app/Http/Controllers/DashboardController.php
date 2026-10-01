<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    public function __invoke(Request $request): View|Response
    {
        $user = $request->user();

        return match ($user->role->value ?? $user->role) {
            'admin' => Inertia::render('Admin/Dashboard', $this->dashboardService->adminStats()),
            'teacher' => Inertia::render('Teacher/Dashboard', $this->dashboardService->teacherStats($user)),
            default => view('student.dashboard', $this->dashboardService->studentStats($user)),
        };
    }
}
