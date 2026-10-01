<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private AdminReportService $reports) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Reports/Index', [
            'systemUsage' => $this->reports->systemUsage(),
            'academicSummary' => $this->reports->academicSummary(),
        ]);
    }

    public function exportSystem(): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print ($this->reports->systemReportCsv()),
            'system-report-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    public function exportAcademic(): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print ($this->reports->enrollmentReportCsv()),
            'academic-report-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    public function exportUsers(Request $request): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print ($this->reports->userReportCsv($request->role)),
            'users-report-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    public function exportSync(): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print ($this->reports->syncReportCsv()),
            'sync-report-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv']
        );
    }
}
