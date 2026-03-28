<?php

namespace App\Http\Controllers\Executive;

use App\Services\Analytics\ExecutiveMonitoringService;
use Inertia\Inertia;
use Inertia\Response;

class ExecutiveMonitoringController
{
    public function __construct(private readonly ExecutiveMonitoringService $monitoringService)
    {
    }

    public function summary(): Response
    {
        abort_unless(auth()->user()->can('executive.monitoring.view'), 403);

        return Inertia::render('Executive/Monitoring/Summary', [
            'summary' => $this->monitoringService->summary(),
        ]);
    }
}
