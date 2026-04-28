<?php

namespace App\Services\Analytics;

use App\Models\Alert;
use App\Models\Anomaly;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PublicDisclosure;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Support\DashboardStatusGroups;
use Throwable;

class ExecutiveMonitoringService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'open_anomaly_count' => Anomaly::query()->whereIn('status', DashboardStatusGroups::executiveOpenIncidentStatuses())->count(),
            'in_review_anomaly_count' => Anomaly::query()->whereIn('status', DashboardStatusGroups::executiveInReviewIncidentStatuses())->count(),
            'high_severity_anomaly_count' => Anomaly::query()->bySeverity('HIGH')->count(),
            'open_alert_count' => Alert::query()->whereIn('status', DashboardStatusGroups::executiveOpenIncidentStatuses())->count(),
            'in_review_alert_count' => Alert::query()->whereIn('status', DashboardStatusGroups::executiveInReviewIncidentStatuses())->count(),
            'high_severity_alert_count' => Alert::query()->bySeverity('HIGH')->count(),
            'cross_office_anomaly_count' => Anomaly::query()->whereNotNull('office_id')->distinct('office_id')->count('office_id'),
            'office_breakdown' => Office::query()
                ->select('offices.id', 'offices.name')
                ->withCount(['anomalies as anomaly_count', 'alerts as alert_count'])
                ->orderBy('offices.name')
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $startedAt = microtime(true);

        try {
            $summary = $this->summary();

            return [
                'summary' => $summary,
                'procurement' => [
                    'purchase_request_count' => PurchaseRequest::query()->count(),
                    'purchase_order_count' => PurchaseOrder::query()->count(),
                    'total_purchase_order_amount' => (float) PurchaseOrder::query()->sum('total_amount'),
                    'monthly_trends' => [
                        'purchase_requests' => $this->buildMonthlyTrend(PurchaseRequest::query()->get(['created_at'])),
                        'purchase_orders' => $this->buildMonthlyTrend(PurchaseOrder::query()->get(['created_at'])),
                    ],
                ],
                'transparency' => [
                    'published_disclosure_count' => PublicDisclosure::query()->published()->count(),
                    'draft_disclosure_count' => PublicDisclosure::query()->byStatus('DRAFT')->count(),
                    'status_breakdown' => PublicDisclosure::query()
                        ->selectRaw('status, COUNT(*) as total')
                        ->whereNotNull('status')
                        ->groupBy('status')
                        ->orderBy('status')
                        ->get(),
                ],
                'approvals' => [
                    'pending_ppmp_approvals' => Ppmp::query()
                        ->whereIn('status', DashboardStatusGroups::executivePendingPpmpStatuses())
                        ->count(),
                    'ppmp_workflow_total' => Ppmp::query()
                        ->whereIn('status', DashboardStatusGroups::executivePpmpWorkflowStatuses())
                        ->count(),
                    'returned_for_revision' => Ppmp::query()->where('status', 'RETURNED_FOR_REVISION')->count(),
                    'recently_approved' => Ppmp::query()->where('status', 'APPROVED')->whereDate('updated_at', '>=', now()->subDays(30)->toDateString())->count(),
                    'status_mix' => Ppmp::query()
                        ->whereIn('status', DashboardStatusGroups::executivePpmpWorkflowStatuses())
                        ->selectRaw('status, COUNT(*) as total')
                        ->groupBy('status')
                        ->orderBy('status')
                        ->get(),
                ],
                'anomaly_type_breakdown' => Anomaly::query()
                    ->selectRaw('anomaly_type as label, COUNT(*) as total')
                    ->whereNotNull('anomaly_type')
                    ->groupBy('anomaly_type')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get(),
                'alert_severity_breakdown' => Alert::query()
                    ->selectRaw('severity as label, COUNT(*) as total')
                    ->whereNotNull('severity')
                    ->groupBy('severity')
                    ->orderByDesc('total')
                    ->get(),
                'recent_anomalies' => Anomaly::query()
                    ->select(['anomaly_id', 'anomaly_type', 'severity', 'status', 'office_id', 'detected_at', 'created_at', 'reference_type', 'reference_id', 'item_id'])
                    ->with('office:id,name')
                    ->latest('detected_at')
                    ->limit(5)
                    ->get(),
                'recent_alerts' => Alert::query()
                    ->select(['alerts_id', 'alert_type', 'severity', 'status', 'office_id', 'detected_at', 'created_at'])
                    ->with('office:id,name')
                    ->latest('detected_at')
                    ->limit(5)
                    ->get(),
                'recent_disclosures' => PublicDisclosure::query()
                    ->select(['disclosure_id', 'title', 'type', 'status', 'published_at', 'created_at'])
                    ->latest('published_at')
                    ->limit(5)
                    ->get(),
                'recent_business_logs' => AuditLog::query()
                    ->business()
                    ->with(['user:id,name', 'office:id,name'])
                    ->latest('created_at')
                    ->limit(5)
                    ->get(['id', 'user_id', 'office_id', 'module_name', 'action_type', 'created_at']),
            ];
        } catch (Throwable $throwable) {
            report($throwable);

            Log::warning('executive.dashboard.payload_failed', [
                'error' => $throwable->getMessage(),
            ]);

            return $this->emptyDashboardPayload();
        } finally {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($durationMs >= 1200) {
                Log::warning('executive.dashboard.payload_slow', [
                    'duration_ms' => $durationMs,
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboardPayload(): array
    {
        return [
            'summary' => [
                'open_anomaly_count' => 0,
                'in_review_anomaly_count' => 0,
                'high_severity_anomaly_count' => 0,
                'open_alert_count' => 0,
                'in_review_alert_count' => 0,
                'high_severity_alert_count' => 0,
                'cross_office_anomaly_count' => 0,
                'office_breakdown' => [],
            ],
            'procurement' => [
                'purchase_request_count' => 0,
                'purchase_order_count' => 0,
                'total_purchase_order_amount' => 0.0,
                'monthly_trends' => [
                    'purchase_requests' => collect(),
                    'purchase_orders' => collect(),
                ],
            ],
            'transparency' => [
                'published_disclosure_count' => 0,
                'draft_disclosure_count' => 0,
                'status_breakdown' => collect(),
            ],
            'approvals' => [
                'pending_ppmp_approvals' => 0,
                'ppmp_workflow_total' => 0,
                'returned_for_revision' => 0,
                'recently_approved' => 0,
                'status_mix' => collect(),
            ],
            'anomaly_type_breakdown' => collect(),
            'alert_severity_breakdown' => collect(),
            'recent_anomalies' => collect(),
            'recent_alerts' => collect(),
            'recent_disclosures' => collect(),
            'recent_business_logs' => collect(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function procurementAnalytics(array $filters = []): array
    {
        $purchaseRequestQuery = PurchaseRequest::query()->with('office:id,name');
        $purchaseOrderQuery = PurchaseOrder::query()->with('purchaseRequest.office:id,name');
        $poPage = max(1, (int) ($filters['po_page'] ?? 1));

        if (! empty($filters['office_id'])) {
            $officeId = (int) $filters['office_id'];
            $purchaseRequestQuery->where('office_id', $officeId);
            $purchaseOrderQuery->whereHas('purchaseRequest', static function (Builder $query) use ($officeId): void {
                $query->where('office_id', $officeId);
            });
        }

        $statusFilter = $filters['status'] ?? null;

        if (! empty($statusFilter)) {
            $purchaseRequestQuery->where('status', $statusFilter);
            $purchaseOrderQuery->where('status', $statusFilter);
        } elseif (! empty($filters['pr_status'])) {
            $purchaseRequestQuery->where('status', $filters['pr_status']);
        }

        if (empty($statusFilter) && ! empty($filters['po_status'])) {
            $purchaseOrderQuery->where('status', $filters['po_status']);
        }

        $this->applyMonth($purchaseRequestQuery, $filters['month'] ?? null, 'created_at');
        $this->applyMonth($purchaseOrderQuery, $filters['month'] ?? null, 'created_at');

        $this->applyDateRange($purchaseRequestQuery, $filters['from_date'] ?? null, $filters['to_date'] ?? null, 'created_at');
        $this->applyDateRange($purchaseOrderQuery, $filters['from_date'] ?? null, $filters['to_date'] ?? null, 'created_at');

        $purchaseRequestBase = clone $purchaseRequestQuery;
        $purchaseOrderBase = clone $purchaseOrderQuery;

        $purchaseRequests = (clone $purchaseRequestBase)->latest()->limit(10)->get();
        $purchaseOrders = (clone $purchaseOrderBase)
            ->latest('purchase_order_id')
            ->paginate(5, ['*'], 'po_page', $poPage)
            ->withQueryString();

        return [
            'filters' => [
                'office_id' => $filters['office_id'] ?? null,
                'status' => $statusFilter,
                'pr_status' => $filters['pr_status'] ?? null,
                'po_status' => $filters['po_status'] ?? null,
                'month' => $filters['month'] ?? null,
                'from_date' => $filters['from_date'] ?? null,
                'to_date' => $filters['to_date'] ?? null,
                'po_page' => $poPage,
            ],
            'filter_options' => [
                'offices' => Office::query()->select(['id', 'name'])->orderBy('name')->get(),
                'statuses' => $this->distinctValues([PurchaseRequest::query(), PurchaseOrder::query()], 'status'),
                'pr_statuses' => PurchaseRequest::query()->select('status')->whereNotNull('status')->distinct()->orderBy('status')->pluck('status')->values(),
                'po_statuses' => PurchaseOrder::query()->select('status')->whereNotNull('status')->distinct()->orderBy('status')->pluck('status')->values(),
            ],
            'totals' => [
                'purchase_request_count' => (clone $purchaseRequestBase)->count(),
                'purchase_order_count' => (clone $purchaseOrderBase)->count(),
                'purchase_order_total_amount' => (float) (clone $purchaseOrderBase)->sum('total_amount'),
            ],
            'status_breakdown' => [
                'purchase_requests' => (clone $purchaseRequestBase)
                    ->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->orderBy('status')
                    ->get(),
                'purchase_orders' => (clone $purchaseOrderBase)
                    ->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->orderBy('status')
                    ->get(),
            ],
            'monthly_trends' => [
                'purchase_requests' => $this->buildMonthlyTrend((clone $purchaseRequestBase)->get(['created_at'])),
                'purchase_orders' => $this->buildMonthlyTrend((clone $purchaseOrderBase)->get(['created_at'])),
            ],
            'office_comparison' => $this->buildProcurementOfficeComparison($purchaseRequestBase, $purchaseOrderBase),
            'recent' => [
                'purchase_requests' => $purchaseRequests,
                'purchase_orders' => $this->toPaginatorPayload($purchaseOrders),
            ],
        ];
    }

    /**
     * @param  LengthAwarePaginator<mixed>  $paginator
     * @return array<string, mixed>
     */
    private function toPaginatorPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();

        return [
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'links' => $payload['links'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function anomaliesMonitoring(array $filters = []): array
    {
        $anomalyQuery = Anomaly::query()->with('office:id,name');
        $alertQuery = Alert::query()->with('office:id,name');

        if (! empty($filters['office_id'])) {
            $officeId = (int) $filters['office_id'];
            $anomalyQuery->where('office_id', $officeId);
            $alertQuery->where('office_id', $officeId);
        }

        if (! empty($filters['severity'])) {
            $anomalyQuery->where('severity', $filters['severity']);
            $alertQuery->where('severity', $filters['severity']);
        }

        if (! empty($filters['status'])) {
            $anomalyQuery->where('status', $filters['status']);
            $alertQuery->where('status', $filters['status']);
        }

        if (! empty($filters['anomaly_type'])) {
            $anomalyQuery->where('anomaly_type', $filters['anomaly_type']);
        }

        $this->applyMonth($anomalyQuery, $filters['month'] ?? null, 'detected_at');
        $this->applyMonth($alertQuery, $filters['month'] ?? null, 'detected_at');

        $this->applyDateRange($anomalyQuery, $filters['from_date'] ?? null, $filters['to_date'] ?? null, 'detected_at');
        $this->applyDateRange($alertQuery, $filters['from_date'] ?? null, $filters['to_date'] ?? null, 'detected_at');

        $anomalyBase = clone $anomalyQuery;
        $alertBase = clone $alertQuery;

        return [
            'filters' => [
                'office_id' => $filters['office_id'] ?? null,
                'severity' => $filters['severity'] ?? null,
                'status' => $filters['status'] ?? null,
                'anomaly_type' => $filters['anomaly_type'] ?? null,
                'month' => $filters['month'] ?? null,
                'from_date' => $filters['from_date'] ?? null,
                'to_date' => $filters['to_date'] ?? null,
            ],
            'filter_options' => [
                'offices' => Office::query()->select(['id', 'name'])->orderBy('name')->get(),
                'severities' => $this->distinctValues([Anomaly::query(), Alert::query()], 'severity'),
                'statuses' => $this->distinctValues([Anomaly::query(), Alert::query()], 'status'),
                'anomaly_types' => collect(array_merge(
                    Anomaly::intendedTypes(),
                    Anomaly::query()
                        ->select('anomaly_type')
                        ->whereNotNull('anomaly_type')
                        ->distinct()
                        ->orderBy('anomaly_type')
                        ->pluck('anomaly_type')
                        ->all(),
                ))
                    ->filter(fn ($value): bool => is_string($value) && $value !== '')
                    ->map(fn ($value): string => strtoupper((string) $value))
                    ->unique()
                    ->values(),
            ],
            'totals' => [
                'anomaly_count' => (clone $anomalyBase)->count(),
                'alert_count' => (clone $alertBase)->count(),
                'high_severity_anomaly_count' => (clone $anomalyBase)->where('severity', 'HIGH')->count(),
                'high_severity_alert_count' => (clone $alertBase)->where('severity', 'HIGH')->count(),
            ],
            'status_breakdown' => [
                'anomalies' => (clone $anomalyBase)
                    ->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->orderBy('status')
                    ->get(),
                'alerts' => (clone $alertBase)
                    ->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->orderBy('status')
                    ->get(),
            ],
            'anomaly_type_breakdown' => (clone $anomalyBase)
                ->selectRaw('anomaly_type as label, COUNT(*) as total')
                ->whereNotNull('anomaly_type')
                ->groupBy('anomaly_type')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'anomaly_severity_breakdown' => (clone $anomalyBase)
                ->selectRaw('severity as label, COUNT(*) as total')
                ->whereNotNull('severity')
                ->groupBy('severity')
                ->orderByDesc('total')
                ->get(),
            'alert_severity_breakdown' => (clone $alertBase)
                ->selectRaw('severity as label, COUNT(*) as total')
                ->whereNotNull('severity')
                ->groupBy('severity')
                ->orderByDesc('total')
                ->get(),
            'office_comparison' => $this->buildIncidentOfficeComparison($anomalyBase, $alertBase),
            'monthly_trends' => [
                'anomalies' => $this->buildMonthlyTrendByColumn((clone $anomalyBase)->get(['detected_at']), 'detected_at'),
                'alerts' => $this->buildMonthlyTrendByColumn((clone $alertBase)->get(['detected_at']), 'detected_at'),
            ],
            'recent' => [
                'anomalies' => (clone $anomalyBase)
                    ->latest('detected_at')
                    ->limit(10)
                    ->get([
                        'anomaly_id',
                        'anomaly_type',
                        'severity',
                        'status',
                        'office_id',
                        'detected_at',
                        'created_at',
                        'reference_type',
                        'reference_id',
                        'item_id',
                        'detected_value',
                        'expected_value',
                        'threshold_value',
                    ]),
                'alerts' => (clone $alertBase)
                    ->latest('detected_at')
                    ->limit(10)
                    ->get([
                        'alerts_id',
                        'alert_type',
                        'severity',
                        'status',
                        'office_id',
                        'detected_at',
                        'created_at',
                        'anomaly_id',
                        'supply_request_id',
                        'purchase_order_id',
                        'supplier_id',
                        'alert_message',
                    ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reports(): array
    {
        return [
            'counts' => [
                'business_audit_entries' => AuditLog::query()->business()->count(),
                'anomalies' => Anomaly::query()->count(),
                'alerts' => Alert::query()->count(),
                'published_disclosures' => PublicDisclosure::query()->published()->count(),
            ],
            'audit_modules' => AuditLog::query()
                ->business()
                ->selectRaw('module_name, COUNT(*) as total')
                ->groupBy('module_name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'audit_actions' => AuditLog::query()
                ->business()
                ->selectRaw('action_type, COUNT(*) as total')
                ->whereNotNull('action_type')
                ->groupBy('action_type')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'monthly_business_trend' => $this->buildMonthlyTrend(AuditLog::query()->business()->get(['created_at'])),
            'latest_business_logs' => AuditLog::query()
                ->business()
                ->with(['user:id,name', 'office:id,name'])
                ->latest()
                ->limit(10)
                ->get(['id', 'user_id', 'office_id', 'module_name', 'action_type', 'created_at']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function transparencyMonitoring(array $filters = []): array
    {
        $query = PublicDisclosure::query()->with('publisher:id,name');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['office_id'])) {
            $officeId = (int) $filters['office_id'];
            $query->whereHas('publisher', static function (Builder $publisherQuery) use ($officeId): void {
                $publisherQuery->where('office_id', $officeId);
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $this->applyMonth($query, $filters['month'] ?? null, 'published_at');

        $this->applyDateRange($query, $filters['from_date'] ?? null, $filters['to_date'] ?? null, 'published_at');

        $base = clone $query;

        return [
            'filters' => [
                'status' => $filters['status'] ?? null,
                'office_id' => $filters['office_id'] ?? null,
                'type' => $filters['type'] ?? null,
                'month' => $filters['month'] ?? null,
                'from_date' => $filters['from_date'] ?? null,
                'to_date' => $filters['to_date'] ?? null,
                'tab' => $filters['tab'] ?? 'overview',
            ],
            'filter_options' => [
                'offices' => Office::query()->select(['id', 'name'])->orderBy('name')->get(),
                'statuses' => PublicDisclosure::query()->select('status')->whereNotNull('status')->distinct()->orderBy('status')->pluck('status')->values(),
                'types' => PublicDisclosure::query()->select('type')->whereNotNull('type')->distinct()->orderBy('type')->pluck('type')->values(),
            ],
            'totals' => [
                'disclosure_count' => (clone $base)->count(),
                'published_count' => (clone $base)->where('status', 'PUBLISHED')->count(),
                'draft_count' => (clone $base)->where('status', 'DRAFT')->count(),
            ],
            'status_breakdown' => (clone $base)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
            'type_breakdown' => (clone $base)
                ->selectRaw('type, COUNT(*) as total')
                ->groupBy('type')
                ->orderBy('type')
                ->get(),
            'office_publication_mix' => PublicDisclosure::query()
                ->published()
                ->leftJoin('users', 'users.id', '=', 'public_disclosures.published_by')
                ->leftJoin('offices', 'offices.id', '=', 'users.office_id')
                ->selectRaw('COALESCE(offices.name, ?) as label, COUNT(*) as total', ['Unassigned'])
                ->groupBy('offices.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'monthly_publication_trend' => $this->buildMonthlyTrendByColumn((clone $base)->get(['published_at', 'created_at']), 'published_at'),
            'recent_disclosures' => (clone $base)
                ->latest('published_at')
                ->limit(10)
                ->get(['disclosure_id', 'title', 'type', 'status', 'published_at', 'published_by', 'created_at']),
            'published_disclosures' => PublicDisclosure::query()
                ->with('publisher:id,name')
                ->published()
                ->latest('published_at')
                ->limit(25)
                ->get(['disclosure_id', 'title', 'type', 'status', 'published_at', 'published_by', 'created_at', 'file_path']),
            'draft_disclosures' => PublicDisclosure::query()
                ->with('publisher:id,name')
                ->where('status', 'DRAFT')
                ->latest('updated_at')
                ->limit(25)
                ->get(['disclosure_id', 'title', 'type', 'status', 'published_at', 'published_by', 'created_at', 'updated_at', 'file_path']),
        ];
    }

    private function applyDateRange(Builder $query, ?string $fromDate, ?string $toDate, string $column): void
    {
        if (! empty($fromDate)) {
            $query->whereDate($column, '>=', $fromDate);
        }

        if (! empty($toDate)) {
            $query->whereDate($column, '<=', $toDate);
        }
    }

    private function applyMonth(Builder $query, mixed $monthValue, string $column): void
    {
        if (empty($monthValue) || ! is_string($monthValue)) {
            return;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $monthValue) !== 1) {
            return;
        }

        [$year, $month] = array_map('intval', explode('-', $monthValue));

        $query->whereYear($column, $year)->whereMonth($column, $month);
    }

    /**
     * @param  list<Builder>  $queries
     * @return Collection<int, string>
     */
    private function distinctValues(array $queries, string $column): Collection
    {
        $values = collect();

        foreach ($queries as $query) {
            $values = $values->merge(
                $query->select($column)
                    ->whereNotNull($column)
                    ->distinct()
                    ->pluck($column)
            );
        }

        return $values
            ->filter(static fn ($value) => is_string($value) && $value !== '')
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function buildMonthlyTrend(Collection $rows): Collection
    {
        $trend = $rows
            ->groupBy(static fn ($row) => Carbon::parse($row->created_at)->format('Y-m'))
            ->map(static fn (Collection $group, string $month) => [
                'month' => $month,
                'total' => $group->count(),
            ])
            ->sortBy('month')
            ->values();

        return $trend;
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function buildMonthlyTrendByColumn(Collection $rows, string $column): Collection
    {
        return $rows
            ->groupBy(static function ($row) use ($column): string {
                $date = $row->{$column} ?? $row->created_at ?? null;

                return Carbon::parse($date ?? now())->format('Y-m');
            })
            ->map(static fn (Collection $group, string $month) => [
                'month' => $month,
                'total' => $group->count(),
            ])
            ->sortBy('month')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildProcurementOfficeComparison(Builder $purchaseRequestBase, Builder $purchaseOrderBase): Collection
    {
        $prByOffice = (clone $purchaseRequestBase)
            ->selectRaw('office_id, COUNT(*) as total')
            ->whereNotNull('office_id')
            ->groupBy('office_id')
            ->pluck('total', 'office_id');

        $poByOffice = (clone $purchaseOrderBase)
            ->join('purchase_requests', 'purchase_requests.purchase_request_id', '=', 'purchase_orders.purchase_request_id')
            ->selectRaw('purchase_requests.office_id as office_id, COUNT(*) as order_total, COALESCE(SUM(purchase_orders.total_amount), 0) as amount_total')
            ->whereNotNull('purchase_requests.office_id')
            ->groupBy('purchase_requests.office_id')
            ->get()
            ->keyBy('office_id');

        return Office::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(static function (Office $office) use ($prByOffice, $poByOffice): array {
                $poData = $poByOffice->get($office->id);

                return [
                    'office_id' => $office->id,
                    'office_name' => $office->name,
                    'purchase_request_count' => (int) ($prByOffice[$office->id] ?? 0),
                    'purchase_order_count' => (int) ($poData?->order_total ?? 0),
                    'purchase_order_total_amount' => (float) ($poData?->amount_total ?? 0),
                ];
            })
            ->filter(static fn (array $row): bool => ($row['purchase_request_count'] + $row['purchase_order_count']) > 0)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildIncidentOfficeComparison(Builder $anomalyBase, Builder $alertBase): Collection
    {
        $anomalyByOffice = (clone $anomalyBase)
            ->selectRaw('office_id, COUNT(*) as total')
            ->whereNotNull('office_id')
            ->groupBy('office_id')
            ->pluck('total', 'office_id');

        $alertByOffice = (clone $alertBase)
            ->selectRaw('office_id, COUNT(*) as total')
            ->whereNotNull('office_id')
            ->groupBy('office_id')
            ->pluck('total', 'office_id');

        return Office::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(static function (Office $office) use ($anomalyByOffice, $alertByOffice): array {
                return [
                    'office_id' => $office->id,
                    'office_name' => $office->name,
                    'anomaly_count' => (int) ($anomalyByOffice[$office->id] ?? 0),
                    'alert_count' => (int) ($alertByOffice[$office->id] ?? 0),
                ];
            })
            ->filter(static fn (array $row): bool => ($row['anomaly_count'] + $row['alert_count']) > 0)
            ->values();
    }
}
