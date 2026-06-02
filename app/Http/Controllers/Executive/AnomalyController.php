<?php

namespace App\Http\Controllers\Executive;

use App\Http\Requests\Anomaly\UpdateAnomalyStatusRequest;
use App\Models\Alert;
use App\Models\Anomaly;
use App\Services\Analytics\AlertService;
use App\Services\Analytics\AnomalyDetectionService;
use App\Services\Analytics\AnomalyEvidenceService;
use App\Services\Analytics\AnomalyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class AnomalyController
{
    public function __construct(
        private readonly AnomalyService $anomalyService,
        private readonly AnomalyDetectionService $detectionService,
        private readonly AlertService $alertService,
        private readonly AnomalyEvidenceService $anomalyEvidenceService,
    ) {
    }

    public function index(Request $request): Response
    {
        abort_unless(auth()->user()->can('anomaly.view-any'), 403);

        $search = trim((string) $request->input('search', ''));
        $year = trim((string) $request->input('year', ''));

        $anomalies = Anomaly::query()
            ->with(['office', 'item'])
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', strtoupper((string) $status)))
            ->when($request->string('severity')->toString(), fn ($query, $severity) => $query->where('severity', strtoupper((string) $severity)))
            ->when($request->string('anomaly_type')->toString(), fn ($query, $type) => $query->where('anomaly_type', strtoupper((string) $type)))
            ->when(preg_match('/^\d{4}$/', $year) === 1, fn ($query) => $query->whereYear('detected_at', (int) $year))
            ->when(preg_match('/^\d{1,2}$/', (string) $request->input('month', '')) === 1, function ($query) use ($request): void {
                $month = (int) $request->input('month');
                $query->whereMonth('detected_at', $month);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->whereLikeInsensitive('anomaly_type', $search)
                        ->orWhereLikeInsensitive('severity', $search)
                        ->orWhereLikeInsensitive('status', $search)
                        ->orWhereHas('office', fn ($officeQuery) => $officeQuery->whereLikeInsensitive('name', $search));
                });
            })
            ->orderByDesc('detected_at')
            ->paginate(5)
            ->withQueryString();

        return Inertia::render('Executive/Anomalies/Index', [
            'anomalies' => $anomalies,
            'filters' => [
                ...$request->only(['status', 'severity', 'anomaly_type', 'month']),
                'year' => $year,
                'search' => $search,
            ],
            'filter_options' => [
                'anomaly_types' => Anomaly::query()
                    ->select('anomaly_type')
                    ->distinct()
                    ->orderBy('anomaly_type')
                    ->pluck('anomaly_type')
                    ->values(),
                'years' => Anomaly::query()
                    ->select('detected_at')
                    ->whereNotNull('detected_at')
                    ->distinct()
                    ->orderByDesc('detected_at')
                    ->get()
                    ->map(fn (Anomaly $anomaly): int => (int) \Illuminate\Support\Carbon::parse($anomaly->detected_at)->format('Y'))
                    ->unique()
                    ->values(),
            ],
        ]);
    }

    public function runDetections(): RedirectResponse
    {
        abort_unless(auth()->user()->can('anomaly.view-any'), 403);

        $anomalies = $this->detectionService->runExecutiveDetections(auth()->user());

        foreach ($anomalies as $anomaly) {
            $alertAlreadyOpen = Alert::query()
                ->where('alert_type', 'ANOMALY_DETECTED')
                ->where('anomaly_id', (int) $anomaly->anomaly_id)
                ->whereIn('status', ['OPEN', 'IN_REVIEW'])
                ->exists();

            if ($alertAlreadyOpen) {
                continue;
            }

            $this->alertService->create(auth()->user(), [
                'alert_type' => 'ANOMALY_DETECTED',
                'severity' => $anomaly->severity,
                'anomaly_id' => $anomaly->anomaly_id,
                'office_id' => $anomaly->office_id,
                'alert_message' => sprintf('Anomaly %s detected (ID: %d).', $anomaly->anomaly_type, $anomaly->anomaly_id),
            ]);
        }

        return Redirect::route('executive.anomalies.index')
            ->with('success', sprintf('Detection run completed. %d anomaly record(s) generated.', $anomalies->count()));
    }

    public function show(Anomaly $anomaly): Response
    {
        abort_unless(auth()->user()->can('anomaly.view', $anomaly), 403);

        $anomaly->load(['office', 'item', 'alerts', 'statusHistory.changedBy']);

        return Inertia::render('Executive/Anomalies/Show', [
            'anomaly' => $anomaly,
            'evidence' => $this->anomalyEvidenceService->build($anomaly),
        ]);
    }

    public function updateStatus(UpdateAnomalyStatusRequest $request, Anomaly $anomaly): RedirectResponse
    {
        abort_unless(auth()->user()->can('anomaly.review', $anomaly), 403);

        $this->anomalyService->updateStatus(
            anomaly: $anomaly,
            actor: auth()->user(),
            newStatus: (string) $request->validated('status'),
            remarks: $request->validated('remarks'),
        );

        return Redirect::route('executive.anomalies.show', $anomaly->anomaly_id)
            ->with('success', 'Anomaly status updated successfully.');
    }
}
