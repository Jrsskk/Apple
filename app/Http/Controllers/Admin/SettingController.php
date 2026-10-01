<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        private SettingService $settings,
        private AuditLogService $auditLog,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => $this->settings->all(),
            'definitions' => $this->settings->definitions(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $definitions = collect($this->settings->definitions())->pluck('key')->all();
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string|max:1000',
        ]);

        $toUpdate = array_intersect_key($validated['settings'], array_flip($definitions));
        $this->settings->updateMany($toUpdate);
        $this->auditLog->log($request->user(), 'update', 'settings', 'Updated system settings.');

        return back()->with('success', 'Settings saved.');
    }
}
