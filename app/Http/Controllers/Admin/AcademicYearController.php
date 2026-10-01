<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function __construct(private AuditLogService $auditLog) {}

    public function index(): Response
    {
        return Inertia::render('Admin/AcademicYears/Index', [
            'years' => AcademicYear::latest()->paginate(10),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/AcademicYears/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        AcademicYear::create($data);
        $this->auditLog->log(auth()->user(), 'create', 'academic_years', "Created academic year {$data['name']}.");

        return redirect()->route('admin.academic-years.index')->with('success', 'Academic year created.');
    }

    public function edit(AcademicYear $academicYear): Response
    {
        return Inertia::render('Admin/AcademicYears/Edit', ['year' => $academicYear]);
    }

    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'required|in:open,closed',
        ]);
        $academicYear->update($data);

        return back()->with('success', 'Academic year updated.');
    }

    public function activate(AcademicYear $academicYear): RedirectResponse
    {
        AcademicYear::query()->update(['is_active' => false]);
        $academicYear->update(['is_active' => true, 'status' => 'open']);
        $this->auditLog->log(auth()->user(), 'activate', 'academic_years', "Activated {$academicYear->name}.");

        return back()->with('success', 'Academic year activated.');
    }

    public function close(AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->update(['is_active' => false, 'status' => 'closed']);

        return back()->with('success', 'Academic year closed.');
    }
}
