<?php

namespace App\Http\Controllers\Executive;

use App\Http\Requests\Alert\UpdateAlertStatusRequest;
use App\Models\Alert;
use App\Services\Analytics\AnomalyEvidenceService;
use App\Services\Analytics\AlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class AlertController
{
    public function __construct(
        private readonly AlertService $alertService,
        private readonly AnomalyEvidenceService $anomalyEvidenceService,
    ) {
    }

    public function index(Request $request): Response
    {
        abort_unless(auth()->user()->can('alert.view-any'), 403);

        $query = Alert::query()->with(['anomaly', 'office', 'reviewer']);

        if (auth()->user()->isGsoOfficer()) {
            $query->whereIn('alert_type', ['LOW_STOCK', 'STOCK_RISK', 'DELAYED_DELIVERY']);
        }

        if (auth()->user()->isProcurement()) {
            $query->whereIn('alert_type', ['PROCUREMENT_DELAY', 'SUPPLIER_RISK', 'WORKFLOW_BLOCKER']);
        }

        $alerts = $query
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', strtoupper((string) $status)))
            ->when($request->string('severity')->toString(), fn ($q, $severity) => $q->where('severity', strtoupper((string) $severity)))
            ->orderByDesc('detected_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Executive/Alerts/Index', [
            'alerts' => $alerts,
            'filters' => $request->only(['status', 'severity']),
        ]);
    }

    public function show(Alert $alert): Response
    {
        abort_unless(auth()->user()->can('alert.view', $alert), 403);

        $alert->load(['anomaly', 'supplyRequest', 'purchaseOrder', 'supplier', 'office', 'reviewer']);

        $anomalyEvidence = null;
        if ($alert->anomaly) {
            $anomalyEvidence = $this->anomalyEvidenceService->build($alert->anomaly);
        }

        return Inertia::render('Executive/Alerts/Show', [
            'alert' => $alert,
            'anomaly_evidence' => $anomalyEvidence,
        ]);
    }

    public function updateStatus(UpdateAlertStatusRequest $request, Alert $alert): RedirectResponse
    {
        abort_unless(auth()->user()->can('alert.review', $alert), 403);

        $this->alertService->updateStatus(
            alert: $alert,
            actor: auth()->user(),
            newStatus: (string) $request->validated('status'),
            remarks: $request->validated('remarks'),
        );

        return Redirect::route('executive.alerts.show', $alert->alerts_id)
            ->with('success', 'Alert status updated successfully.');
    }
}
