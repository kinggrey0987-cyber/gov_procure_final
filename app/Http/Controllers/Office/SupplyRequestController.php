<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Requests\Office\StoreSupplyRequestRequest;
use App\Http\Requests\Office\SubmitSupplyRequestRequest;
use App\Http\Requests\Office\UpdateSupplyRequestRequest;
use App\Models\DistributionRecord;
use App\Models\Ppmp;
use App\Models\SupplyRequest;
use App\Services\History\HistoryTimelineService;
use App\Services\Office\SupplyRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplyRequestController extends Controller
{
    public function __construct(
        private readonly SupplyRequestService $service,
        private readonly HistoryTimelineService $historyTimelineService,
    ) {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('office.supply-request.view-any'), 403);

        $status = strtoupper(trim((string) $request->input('status', '')));
        $year = trim((string) $request->input('year', ''));
        $month = trim((string) $request->input('month', ''));
        $search = trim((string) $request->input('search', ''));

        $yearOptions = SupplyRequest::query()
            ->forOffice((int) $request->user()?->office_id)
            ->where('request_date', '!=', null)
            ->latest('request_date')
            ->pluck('request_date')
            ->map(fn ($value): int => (int) $value->year)
            ->unique()
            ->values();

        $supplyRequests = SupplyRequest::query()
            ->with(['items.item.inventory', 'purchaseRequests', 'purchaseOrders', 'distributionRecords'])
            ->forOffice((int) $request->user()?->office_id)
            ->when($status !== '', function ($query) use ($status): void {
                $this->applyTrackingStatusFilter($query, $status);
            })
            ->when($status === 'SUBMITTED', function ($query): void {
                $query->whereDoesntHave('purchaseRequests')
                    ->whereDoesntHave('purchaseOrders')
                    ->whereDoesntHave('distributionRecords');
            })
            ->when(preg_match('/^\d{4}$/', $year) === 1, fn ($query) => $query->whereYear('request_date', (int) $year))
            ->when(preg_match('/^\d{1,2}$/', $month) === 1, fn ($query) => $query->whereMonth('request_date', (int) $month))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('request_code', 'like', '%'.$search.'%')
                        ->orWhere('status', 'like', '%'.$search.'%')
                        ->orWhere('purpose', 'like', '%'.$search.'%');
                });
            })
            ->latest('supply_request_id')
            ->paginate(5)
            ->through(function (SupplyRequest $supplyRequest): array {
                $requiresProcurement = $this->requiresProcurement($supplyRequest);
                $trackingStatus = $this->resolveTrackingStatus($supplyRequest, $requiresProcurement);

                return [
                    'supply_request_id' => (int) $supplyRequest->supply_request_id,
                    'request_code' => (string) $supplyRequest->request_code,
                    'status' => $trackingStatus,
                    'raw_status' => (string) $supplyRequest->status,
                    'request_date' => optional($supplyRequest->request_date)?->toDateString(),
                    'items_count' => (int) $supplyRequest->items->count(),
                    'items_summary' => $supplyRequest->items->take(2)->pluck('item_name_snapshot')->implode(', '),
                ];
            })
            ->withQueryString();

        return Inertia::render('Office/SupplyRequests/Index', [
            'supplyRequests' => $supplyRequests,
            'filters' => [
                'status' => $status,
                'year' => $year,
                'month' => $month,
                'search' => $search,
            ],
            'filter_options' => [
                'years' => $yearOptions,
            ],
        ]);
    }

    private function applyTrackingStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'RECEIVED' => $query->whereHas('distributionRecords', fn (Builder $distributionQuery) => $distributionQuery->where('status', 'RECEIVED')),
            'RELEASED' => $query
                ->whereDoesntHave('distributionRecords', fn (Builder $distributionQuery) => $distributionQuery->where('status', 'RECEIVED'))
                ->whereHas('distributionRecords', fn (Builder $distributionQuery) => $distributionQuery->where('status', 'RELEASED')),
            'PROCUREMENT_PLACED' => $query
                ->whereDoesntHave('distributionRecords', fn (Builder $distributionQuery) => $distributionQuery->whereIn('status', ['RELEASED', 'RECEIVED']))
                ->whereHas('purchaseOrders', fn (Builder $purchaseOrderQuery) => $purchaseOrderQuery->where('status', 'PLACED')),
            'PROCUREMENT_PO_DRAFT' => $query
                ->whereDoesntHave('distributionRecords', fn (Builder $distributionQuery) => $distributionQuery->whereIn('status', ['RELEASED', 'RECEIVED']))
                ->whereDoesntHave('purchaseOrders', fn (Builder $purchaseOrderQuery) => $purchaseOrderQuery->where('status', 'PLACED'))
                ->whereHas('purchaseOrders', fn (Builder $purchaseOrderQuery) => $purchaseOrderQuery->where('status', 'DRAFT')),
            'REJECTED' => $query
                ->whereDoesntHave('distributionRecords', fn (Builder $distributionQuery) => $distributionQuery->whereIn('status', ['RELEASED', 'RECEIVED']))
                ->whereDoesntHave('purchaseOrders', fn (Builder $purchaseOrderQuery) => $purchaseOrderQuery->whereIn('status', ['PLACED', 'DRAFT']))
                ->whereHas('purchaseRequests', fn (Builder $purchaseRequestQuery) => $purchaseRequestQuery->where('status', 'REJECTED')),
            'RETURNED_FOR_REVISION' => $query
                ->whereDoesntHave('distributionRecords', fn (Builder $distributionQuery) => $distributionQuery->whereIn('status', ['RELEASED', 'RECEIVED']))
                ->whereDoesntHave('purchaseOrders', fn (Builder $purchaseOrderQuery) => $purchaseOrderQuery->whereIn('status', ['PLACED', 'DRAFT']))
                ->whereDoesntHave('purchaseRequests', fn (Builder $purchaseRequestQuery) => $purchaseRequestQuery->where('status', 'REJECTED'))
                ->whereHas('purchaseRequests', fn (Builder $purchaseRequestQuery) => $purchaseRequestQuery->where('status', 'RETURNED_FOR_REVISION')),
            'PROCESSING' => $query
                ->whereDoesntHave('distributionRecords', fn (Builder $distributionQuery) => $distributionQuery->whereIn('status', ['RELEASED', 'RECEIVED']))
                ->whereDoesntHave('purchaseOrders', fn (Builder $purchaseOrderQuery) => $purchaseOrderQuery->whereIn('status', ['PLACED', 'DRAFT']))
                ->whereDoesntHave('purchaseRequests', fn (Builder $purchaseRequestQuery) => $purchaseRequestQuery->whereIn('status', ['REJECTED', 'RETURNED_FOR_REVISION']))
                ->whereHas('purchaseRequests', fn (Builder $purchaseRequestQuery) => $purchaseRequestQuery->where('status', 'PROCESSING')),
            'IN_REVIEW' => $query->where(function (Builder $trackingQuery): void {
                $trackingQuery
                    ->where(function (Builder $directStockQuery): void {
                        $directStockQuery
                            ->where('status', 'SUBMITTED')
                            ->whereDoesntHave('purchaseRequests')
                            ->whereDoesntHave('purchaseOrders')
                            ->whereDoesntHave('distributionRecords')
                            ->whereDoesntHave('items', function (Builder $itemQuery): void {
                                $itemQuery->whereHas('item.inventory', function (Builder $inventoryQuery): void {
                                    $inventoryQuery->whereColumn('supply_request_items.quantity_requested', '>', 'inventory.available_stock');
                                });
                            });
                    })
                    ->orWhere(function (Builder $procurementQuery): void {
                        $procurementQuery
                            ->whereDoesntHave('distributionRecords', fn (Builder $distributionQuery) => $distributionQuery->whereIn('status', ['RELEASED', 'RECEIVED']))
                            ->whereDoesntHave('purchaseOrders', fn (Builder $purchaseOrderQuery) => $purchaseOrderQuery->whereIn('status', ['PLACED', 'DRAFT']))
                            ->whereDoesntHave('purchaseRequests', fn (Builder $purchaseRequestQuery) => $purchaseRequestQuery->whereIn('status', ['REJECTED', 'RETURNED_FOR_REVISION', 'PROCESSING']))
                            ->whereHas('purchaseRequests', fn (Builder $purchaseRequestQuery) => $purchaseRequestQuery->where('status', 'OPEN'));
                    });
            }),
            default => $query->where('status', $status),
        };
    }

    public function create(Request $request): Response
    {
        $officeId = (int) $request->user()?->office_id;
        $ppmpOptions = $this->service->ppmpOptionsForOffice($officeId);
        $selectedPpmpId = (int) $request->query('ppmp_id', 0);
        $isAllowedSelection = collect($ppmpOptions)
            ->contains(fn (array $option): bool => (int) $option['ppmp_id'] === $selectedPpmpId);

        if (! $isAllowedSelection) {
            $selectedPpmpId = 0;
        }

        return Inertia::render('Office/SupplyRequests/Create', [
            'ppmpOptions' => $ppmpOptions,
            'selectedPpmpId' => $selectedPpmpId > 0 ? $selectedPpmpId : null,
            'requestDate' => now()->toDateString(),
            'ppmpItemOptions' => $selectedPpmpId > 0
                ? $this->service->ppmpItemOptionsForRequestContext($officeId, $selectedPpmpId)
                : [],
        ]);
    }

    public function store(StoreSupplyRequestRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $supplyRequest = $this->service->create($request->user(), $payload);

        $items = $payload['items'] ?? [];
        foreach ($items as $item) {
            $this->service->addItem($supplyRequest, $request->user(), $item);
        }

        return redirect()->route('office.supply-requests.show', $supplyRequest->supply_request_id)
            ->with('success', 'Supply request created successfully.');
    }

    public function show(Request $request, SupplyRequest $supplyRequest): Response
    {
        abort_unless($request->user()?->can('office.supply-request.view', $supplyRequest), 403);

        $supplyRequest->load([
            'items.ppmpItem.item.inventory',
            'office',
            'requester',
            'approver',
            'purchaseRequests',
            'purchaseOrders',
            'distributionRecords.items',
        ]);

        $requiresProcurement = $this->requiresProcurement($supplyRequest);
        $trackingStatus = $this->resolveTrackingStatus($supplyRequest, $requiresProcurement);
        $history = $this->historyTimelineService->forRecord($request->user(), $supplyRequest);
        $supplyRequestData = $this->showPayload($supplyRequest, $trackingStatus, $requiresProcurement);
        $distributionRecords = $this->releasedDistributionRecords($supplyRequest);

        $canEdit = $request->user()?->can('office.supply-request.update', $supplyRequest) ?? false;
        $canSubmit = $request->user()?->can('office.supply-request.submit', $supplyRequest) ?? false;

        return Inertia::render('Office/SupplyRequests/Show', [
            'supplyRequest' => $supplyRequestData,
            'canEdit' => $canEdit,
            'canSubmit' => $canSubmit,
            'distributionRecords' => $distributionRecords,
            'statusHistory' => $history['status']->map(fn ($entry): array => [
                'id' => (int) $entry->id,
                'old_status' => $entry->old_status,
                'new_status' => (string) $entry->new_status,
                'remarks' => $entry->remarks,
                'changed_at' => optional($entry->changed_at)?->toIso8601String(),
                'changed_by' => $entry->changedBy ? [
                    'id' => (int) $entry->changedBy->id,
                    'name' => (string) $entry->changedBy->name,
                ] : null,
            ])->values(),
            'approvalHistory' => $history['approval']->map(fn ($entry): array => [
                'id' => (int) $entry->id,
                'status' => (string) $entry->status,
                'remarks' => $entry->remarks,
                'approved_at' => optional($entry->approved_at)?->toIso8601String(),
                'approver' => $entry->approver ? [
                    'id' => (int) $entry->approver->id,
                    'name' => (string) $entry->approver->name,
                ] : null,
            ])->values(),
        ]);
    }

    private function showPayload(SupplyRequest $supplyRequest, string $trackingStatus, bool $requiresProcurement): array
    {
        $ppmpItemContext = $this->ppmpItemContext($supplyRequest);

        return [
            'supply_request_id' => (int) $supplyRequest->supply_request_id,
            'office_id' => (int) $supplyRequest->office_id,
            'ppmp_id' => $supplyRequest->ppmp_id ? (int) $supplyRequest->ppmp_id : null,
            'request_code' => (string) $supplyRequest->request_code,
            'status' => $trackingStatus,
            'raw_status' => (string) $supplyRequest->status,
            'request_date' => optional($supplyRequest->request_date)?->toDateString(),
            'needed_by_date' => optional($supplyRequest->needed_by_date)?->toDateString(),
            'request_category' => (string) $supplyRequest->request_category,
            'purpose' => (string) $supplyRequest->purpose,
            'priority_level' => (string) $supplyRequest->priority_level,
            'review_notes' => $supplyRequest->review_notes,
            'purchase_requests' => $supplyRequest->purchaseRequests
                ->sortByDesc('purchase_request_id')
                ->map(fn ($purchaseRequest): array => [
                    'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                    'status' => (string) $purchaseRequest->status,
                    'remarks' => $purchaseRequest->remarks,
                    'created_at' => optional($purchaseRequest->created_at)?->toIso8601String(),
                    'updated_at' => optional($purchaseRequest->updated_at)?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'items' => $this->mappedSupplyRequestItems($supplyRequest, $ppmpItemContext),
            'processing_timeline' => $this->buildProcessingTimeline($trackingStatus, $requiresProcurement),
        ];
    }

    private function ppmpItemContext(SupplyRequest $supplyRequest)
    {
        if ((int) $supplyRequest->ppmp_id <= 0) {
            return collect();
        }

        return collect($this->service->ppmpItemOptionsForRequestContext((int) $supplyRequest->office_id, (int) $supplyRequest->ppmp_id))
            ->keyBy('ppmp_item_id');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $ppmpItemContext
     */
    private function mappedSupplyRequestItems(SupplyRequest $supplyRequest, $ppmpItemContext): array
    {
        $releasedByItemId = $supplyRequest->distributionRecords
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->flatMap(fn ($record) => $record->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($items): float => (float) $items->sum('quantity_released'));
        $receivedByItemId = $supplyRequest->distributionRecords
            ->where('status', 'RECEIVED')
            ->flatMap(fn ($record) => $record->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($items): float => (float) $items->sum('quantity_received'));

        return $supplyRequest->items
            ->map(function ($item) use ($ppmpItemContext, $releasedByItemId, $receivedByItemId): array {
                $context = $ppmpItemContext->get((int) $item->ppmp_item_id, []);
                $itemId = (int) $item->item_id;

                return [
                    'supply_request_item_id' => (int) $item->supply_request_item_id,
                    'ppmp_item_id' => (int) $item->ppmp_item_id,
                    'item_name_snapshot' => (string) ($item->item_name_snapshot ?? ''),
                    'quantity_requested' => (float) $item->quantity_requested,
                    'quantity_released' => (float) ($releasedByItemId->get($itemId) ?? 0.0),
                    'quantity_received' => (float) ($receivedByItemId->get($itemId) ?? 0.0),
                    'planned_quantity' => isset($context['planned_quantity']) ? (float) $context['planned_quantity'] : null,
                    'already_requested_quantity' => isset($context['requested_quantity']) ? (float) $context['requested_quantity'] : null,
                    'remaining_quantity' => isset($context['remaining_quantity']) ? (float) $context['remaining_quantity'] : null,
                    'item_specification' => isset($context['item_specification']) ? (string) $context['item_specification'] : null,
                    'over_requested_quantity' => isset($context['over_requested_quantity']) ? (float) $context['over_requested_quantity'] : null,
                    'exceeds_ppmp_allocation' => isset($context['over_requested_quantity'])
                        ? (float) $context['over_requested_quantity'] > 0
                        : false,
                ];
            })
            ->values()
            ->all();
    }

    private function releasedDistributionRecords(SupplyRequest $supplyRequest)
    {
        return DistributionRecord::query()
            ->where('supply_request_id', (int) $supplyRequest->supply_request_id)
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->latest('distribution_record_id')
            ->get(['distribution_record_id', 'status', 'storage_location', 'release_date'])
            ->map(fn (DistributionRecord $record): array => [
                'distribution_record_id' => (int) $record->distribution_record_id,
                'status' => (string) $record->status,
                'storage_location' => (string) ($record->storage_location ?? ''),
                'release_date' => optional($record->release_date)?->toDateString(),
            ])
            ->values();
    }

    private function requiresProcurement(SupplyRequest $supplyRequest): bool
    {
        if ($supplyRequest->purchaseRequests->isNotEmpty() || $supplyRequest->purchaseOrders->isNotEmpty()) {
            return true;
        }

        foreach ($supplyRequest->items as $item) {
            $requested = (float) $item->quantity_requested;
            $available = (float) ($item->item?->inventory?->available_stock ?? 0);

            if ($requested > $available) {
                return true;
            }
        }

        return false;
    }

    private function resolveTrackingStatus(SupplyRequest $supplyRequest, bool $requiresProcurement): string
    {
        $distributionStatuses = $supplyRequest->distributionRecords
            ->pluck('status')
            ->map(fn ($status): string => strtoupper((string) $status));

        if ($distributionStatuses->contains('RECEIVED')) {
            return 'RECEIVED';
        }

        if ($distributionStatuses->contains('RELEASED')) {
            return 'RELEASED';
        }

        $purchaseOrderStatuses = $supplyRequest->purchaseOrders
            ->pluck('status')
            ->map(fn ($status): string => strtoupper((string) $status));

        if ($purchaseOrderStatuses->contains('PLACED')) {
            return 'PROCUREMENT_PLACED';
        }

        if ($purchaseOrderStatuses->contains('DRAFT')) {
            return 'PROCUREMENT_PO_DRAFT';
        }

        $purchaseRequestStatuses = $supplyRequest->purchaseRequests
            ->pluck('status')
            ->map(fn ($status): string => strtoupper((string) $status));

        if ($purchaseRequestStatuses->contains('REJECTED')) {
            return 'REJECTED';
        }

        if ($purchaseRequestStatuses->contains('RETURNED_FOR_REVISION')) {
            return 'RETURNED_FOR_REVISION';
        }

        if ($purchaseRequestStatuses->contains('PROCESSING')) {
            return 'PROCESSING';
        }

        if ($purchaseRequestStatuses->contains('OPEN')) {
            return 'IN_REVIEW';
        }

        $baseStatus = strtoupper((string) $supplyRequest->status);

        if ($baseStatus === 'SUBMITTED' && ! $requiresProcurement) {
            return 'IN_REVIEW';
        }

        return $baseStatus;
    }

    /**
     * @return array{mode: string, current_status: string, is_rejected: bool, stages: array<int, array<string, mixed>>}
     */
    private function buildProcessingTimeline(string $trackingStatus, bool $requiresProcurement): array
    {
        $mode = $requiresProcurement ? 'PROCUREMENT' : 'DIRECT_STOCK';
        $status = strtoupper($trackingStatus);

        $stages = $requiresProcurement
            ? [
                ['order' => 1, 'key' => 'DRAFT', 'label' => 'Stage 1: Draft Request', 'owner' => 'Office User'],
                ['order' => 2, 'key' => 'SUBMITTED', 'label' => 'Stage 2: Submitted to GSO', 'owner' => 'GSO Officer'],
                ['order' => 3, 'key' => 'IN_REVIEW', 'label' => 'Stage 3: GSO Validation', 'owner' => 'GSO Officer'],
                ['order' => 4, 'key' => 'PROCESSING', 'label' => 'Stage 4: Procurement Unit', 'owner' => 'Procurement Unit'],
                ['order' => 5, 'key' => 'RELEASED', 'label' => 'Stage 5: Released for Office', 'owner' => 'GSO Officer'],
                ['order' => 6, 'key' => 'COMPLETED', 'label' => 'Stage 6: Completed', 'owner' => 'Office User'],
            ]
            : [
                ['order' => 1, 'key' => 'DRAFT', 'label' => 'Stage 1: Draft Request', 'owner' => 'Office User'],
                ['order' => 2, 'key' => 'SUBMITTED', 'label' => 'Stage 2: Submitted to GSO', 'owner' => 'GSO Officer'],
                ['order' => 3, 'key' => 'IN_REVIEW', 'label' => 'Stage 3: GSO Validation', 'owner' => 'GSO Officer'],
                ['order' => 4, 'key' => 'RELEASED', 'label' => 'Stage 4: Released for Office', 'owner' => 'GSO Officer'],
                ['order' => 5, 'key' => 'COMPLETED', 'label' => 'Stage 5: Completed', 'owner' => 'Office User'],
            ];

        $orderMap = $requiresProcurement
            ? [
                'DRAFT' => 1,
                'SUBMITTED' => 2,
                'IN_REVIEW' => 3,
                'OPEN' => 3,
                'RETURNED_FOR_REVISION' => 3,
                'PROCESSING' => 4,
                'PROCUREMENT_PO_DRAFT' => 4,
                'PROCUREMENT_PLACED' => 4,
                'RELEASED' => 5,
                'RECEIVED' => 6,
                'COMPLETED' => 6,
                'REJECTED' => 3,
            ]
            : [
                'DRAFT' => 1,
                'SUBMITTED' => 2,
                'IN_REVIEW' => 3,
                'RETURNED_FOR_REVISION' => 3,
                'REJECTED' => 3,
                'RELEASED' => 4,
                'RECEIVED' => 5,
                'COMPLETED' => 5,
            ];

        $currentOrder = $orderMap[$status] ?? 1;

        return [
            'mode' => $mode,
            'current_status' => $status,
            'is_rejected' => $status === 'REJECTED',
            'stages' => collect($stages)
                ->map(fn (array $stage): array => [
                    'key' => $stage['key'],
                    'label' => $stage['label'],
                    'owner' => $stage['owner'],
                    'reached' => (int) $stage['order'] <= $currentOrder,
                    'is_current' => (int) $stage['order'] === $currentOrder,
                ])
                ->values()
                ->all(),
        ];
    }

    public function edit(Request $request, SupplyRequest $supplyRequest): Response
    {
        abort_unless($request->user()?->can('office.supply-request.update', $supplyRequest), 403);

        $officeId = (int) $request->user()?->office_id;
        $selectedPpmpId = (int) ($supplyRequest->ppmp_id ?? 0);

        return Inertia::render('Office/SupplyRequests/Edit', [
            'supplyRequest' => $supplyRequest->load('items'),
            'ppmpOptions' => $this->service->ppmpOptionsForOffice($officeId),
            'selectedPpmpId' => $selectedPpmpId > 0 ? $selectedPpmpId : null,
            'ppmpItemOptions' => $selectedPpmpId > 0
                ? $this->service->ppmpItemOptionsForRequestContext($officeId, $selectedPpmpId)
                : [],
        ]);
    }

    public function ppmpItemsForPpmp(Request $request, Ppmp $ppmp): JsonResponse
    {
        abort_unless($request->user()?->can('office.supply-request.create'), 403);

        $officeId = (int) $request->user()?->office_id;
        if ((int) $ppmp->office_id !== $officeId || (string) $ppmp->status !== 'APPROVED') {
            abort(403);
        }

        return response()->json([
            'ppmp_item_options' => $this->service->ppmpItemOptionsForRequestContext($officeId, (int) $ppmp->ppmp_id),
        ]);
    }

    public function update(UpdateSupplyRequestRequest $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        $this->service->update($supplyRequest, $request->user(), $request->validated());

        return redirect()->route('office.supply-requests.show', $supplyRequest->supply_request_id)
            ->with('success', 'Supply request updated successfully.');
    }

    public function submit(SubmitSupplyRequestRequest $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        $submittedRequest = $this->service->submit($supplyRequest, $request->user(), $request->input('review_notes'));
        $overRequestCount = (int) ($submittedRequest->getAttribute('ppmp_over_request_count') ?? 0);
        $scheduleMismatchCount = (int) ($submittedRequest->getAttribute('ppmp_schedule_mismatch_count') ?? 0);

        $successMessage = 'Supply request submitted successfully.';

        if ($overRequestCount > 0 || $scheduleMismatchCount > 0) {
            $successMessage = sprintf(
                'Supply request submitted successfully. %d PPMP over-allocation line(s) and %d out-of-schedule line(s) were flagged for executive review.',
                $overRequestCount,
                $scheduleMismatchCount,
            );
        }

        return redirect()->route('office.supply-requests.show', $supplyRequest->supply_request_id)
            ->with('success', $successMessage);
    }

    public function destroy(Request $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        abort_unless($request->user()?->can('office.supply-request.delete', $supplyRequest), 403);

        $this->service->deleteDraft($supplyRequest, $request->user());

        return redirect()->route('office.supply-requests.index')
            ->with('success', 'Draft supply request deleted successfully.');
    }

    public function suppliesReadyForClaim(Request $request): Response
    {
        abort_unless($request->user()?->can('office.supply-request.view-any'), 403);

        $officeId = (int) $request->user()?->office_id;

        $records = DistributionRecord::query()
            ->with(['items', 'supplyRequest'])
            ->forOffice($officeId)
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->latest('distribution_record_id')
            ->paginate(5)
            ->through(fn (DistributionRecord $record): array => [
                'distribution_record_id' => (int) $record->distribution_record_id,
                'request_id' => (string) ($record->supplyRequest?->request_code ?? 'N/A'),
                'request_date' => optional($record->supplyRequest?->request_date)?->toDateString(),
                'items_summary' => (string) ($record->items->take(2)->pluck('item_name_snapshot')->implode(', ') ?: 'No released items listed'),
                'items_count' => (int) $record->items->count(),
                'location' => (string) ($record->storage_location ?? 'GSO Storage'),
                'ready_since' => optional($record->release_date)?->toDateString(),
                'status' => (string) $record->status,
                'supply_request_id' => (int) ($record->supply_request_id ?? 0),
            ])
            ->withQueryString();

        return Inertia::render('Office/SuppliesReadyForClaim/Index', [
            'records' => $records,
        ]);
    }

}
