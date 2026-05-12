<?php

namespace App\Http\Controllers\Executive;

use App\Services\Analytics\ExecutiveMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExecutiveMonitoringController
{
    public function __construct(private readonly ExecutiveMonitoringService $monitoringService)
    {
    }

    public function summary(): Response
    {
        return $this->dashboard();
    }

    public function dashboard(): Response
    {
        $this->authorizeView();

        return Inertia::render('Executive/Dashboard/Index', $this->monitoringService->dashboard());
    }

    public function procurementAnalytics(Request $request): Response
    {
        $this->authorizeView();

        $filters = $request->validate([
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'status' => ['nullable', 'string', 'max:60'],
            'pr_status' => ['nullable', 'string', 'max:60'],
            'po_status' => ['nullable', 'string', 'max:60'],
            'record_type' => ['nullable', 'in:all,pr,po'],
            'year' => ['nullable', 'digits:4'],
            'month' => ['nullable', 'string', 'regex:/^(?:0?[1-9]|1[0-2]|\d{4}-\d{2})$/'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'records_page' => ['nullable', 'integer', 'min:1'],
            'po_page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Executive/ProcurementAnalytics/Index', $this->monitoringService->procurementAnalytics($filters));
    }

    public function anomaliesMonitoring(Request $request): Response
    {
        $this->authorizeView();

        $filters = $request->validate([
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'severity' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'string', 'max:60'],
            'anomaly_type' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'digits:4'],
            'month' => ['nullable', 'string', 'regex:/^(?:0?[1-9]|1[0-2]|\d{4}-\d{2})$/'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        return Inertia::render('Executive/Monitoring/AnomaliesAlerts', $this->monitoringService->anomaliesMonitoring($filters));
    }

    public function reports(): RedirectResponse
    {
        $this->authorizeView();

        return redirect()->route('executive.dashboard');
    }

    public function transparencyMonitoring(Request $request): Response
    {
        $this->authorizeView();

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:60'],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'type' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'digits:4'],
            'month' => ['nullable', 'string', 'regex:/^(?:0?[1-9]|1[0-2]|\d{4}-\d{2})$/'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'tab' => ['nullable', 'in:overview,published,drafts'],
        ]);

        return Inertia::render('Executive/Transparency/Index', $this->monitoringService->transparencyMonitoring($filters));
    }

    private function authorizeView(): void
    {
        abort_unless(Auth::user()?->can('executive.monitoring.view'), 403);
    }
}
