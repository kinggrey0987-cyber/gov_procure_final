<?php

namespace App\Http\Controllers\Governance;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogViewerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogViewerService $service)
    {
    }

    public function technical(Request $request): Response
    {
        abort_unless($request->user()?->can('audit.technical.view'), 403);

        $filters = $request->validate([
            'module_name' => ['nullable', 'string', 'max:100'],
            'action_type' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $logs = $this->service->technical($request->user(), $filters);

        return Inertia::render('AdminTech/Audit/Technical', [
            'auditLogs' => $logs,
            'filters' => $filters,
        ]);
    }

    public function business(Request $request): Response
    {
        abort_unless($request->user()?->can('audit.business.view'), 403);

        $filters = $request->validate([
            'module_name' => ['nullable', 'string', 'max:100'],
            'action_type' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $logs = $this->service->business($request->user(), $filters);
        $analytics = $this->service->businessAnalytics($request->user(), $filters);
        $filterOptions = $this->service->businessFilterOptions($request->user());

        return Inertia::render('Executive/Audit/Business', [
            'auditLogs' => $logs,
            'filters' => $filters,
            'analytics' => $analytics,
            'filter_options' => $filterOptions,
        ]);
    }
}
