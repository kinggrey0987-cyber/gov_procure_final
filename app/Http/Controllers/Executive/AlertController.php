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

        $search = trim((string) $request->input('search', ''));
        $year = trim((string) $request->input('year', ''));

        $query = Alert::query()->with(['anomaly', 'office', 'reviewer']);

        if (auth()->user()->isGsoOfficer()) {
            $query->whereNull('anomaly_id')
                ->whereIn('alert_type', ['LOW_STOCK', 'STOCK_RISK', 'DELAYED_DELIVERY']);
        }

        if (auth()->user()->isProcurement()) {
            $query->whereNull('anomaly_id')
                ->whereIn('alert_type', ['PROCUREMENT_DELAY', 'SUPPLIER_RISK', 'WORKFLOW_BLOCKER']);
        }

        $alerts = $query
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', strtoupper((string) $status)))
            ->when($request->string('severity')->toString(), fn ($q, $severity) => $q->where('severity', strtoupper((string) $severity)))
            ->when(preg_match('/^\d{4}$/', $year) === 1, fn ($q) => $q->whereYear('detected_at', (int) $year))
            ->when(preg_match('/^\d{1,2}$/', (string) $request->input('month', '')) === 1, function ($q) use ($request): void {
                $month = (int) $request->input('month');
                $q->whereMonth('detected_at', $month);
            })
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($nested) use ($search): void {
                    $nested->where('alert_type', 'like', '%'.$search.'%')
                        ->orWhere('severity', 'like', '%'.$search.'%')
                        ->orWhere('status', 'like', '%'.$search.'%')
                        ->orWhereHas('office', fn ($officeQuery) => $officeQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderByDesc('detected_at')
            ->paginate(5)
            ->withQueryString();

        return Inertia::render('Executive/Alerts/Index', [
            'alerts' => $alerts,
            'permissions' => [
                'review' => auth()->user()->isExecutive(),
            ],
            'filters' => [
                ...$request->only(['status', 'severity', 'month']),
                'year' => $year,
                'search' => $search,
            ],
            'filter_options' => [
                'years' => Alert::query()
                    ->select('detected_at')
                    ->whereNotNull('detected_at')
                    ->distinct()
                    ->orderByDesc('detected_at')
                    ->get()
                    ->map(fn (Alert $alert): int => (int) \Illuminate\Support\Carbon::parse($alert->detected_at)->format('Y'))
                    ->unique()
                    ->values(),
            ],
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
