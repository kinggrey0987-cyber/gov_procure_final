<?php

namespace App\Http\Controllers\Executive;

use App\Services\Analytics\ExecutiveMonitoringService;
use Illuminate\Http\Request;
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
            'pr_status' => ['nullable', 'string', 'max:60'],
            'po_status' => ['nullable', 'string', 'max:60'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
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
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        return Inertia::render('Executive/Monitoring/AnomaliesAlerts', $this->monitoringService->anomaliesMonitoring($filters));
    }

    public function reports(): Response
    {
        $this->authorizeView();

        return Inertia::render('Executive/Reports/Index', $this->monitoringService->reports());
    }

    public function transparencyMonitoring(Request $request): Response
    {
        $this->authorizeView();

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:60'],
            'type' => ['nullable', 'string', 'max:80'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        return Inertia::render('Executive/Transparency/Index', $this->monitoringService->transparencyMonitoring($filters));
    }

    private function authorizeView(): void
    {
        abort_unless(auth()->user()?->can('executive.monitoring.view'), 403);
    }
}
