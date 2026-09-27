<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Requests\Office\StoreSupplyRequestRequest;
use App\Http\Requests\Office\SubmitSupplyRequestRequest;
use App\Http\Requests\Office\UpdateSupplyRequestRequest;
use App\Models\DistributionRecord;
use App\Models\Delivery;
use App\Models\Ppmp;
use App\Models\PurchaseRequest;
use App\Models\SupplyRequest;
use App\Services\History\HistoryTimelineService;
use App\Services\Office\SupplyRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SupplyRequestController extends Controller
{
    public function __construct(
        private readonly SupplyRequestService $service,
        private readonly HistoryTimelineService $historyTimelineService,
        private readonly \App\Services\Inventory\SupplyRequestFollowUpService $followUpService,
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

        $paginator = SupplyRequest::query()
            ->with([
                'items.item.inventory',
                'purchaseRequests.sourceDelivery.purchaseOrder.supplyRequest',
                'purchaseRequests.sourceClaim.supplyRequest',
                'purchaseOrders',
                'distributionRecords',
            ])
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
                    $nested->whereLikeInsensitive('request_code', $search)
                        ->orWhereLikeInsensitive('status', $search)
                        ->orWhereLikeInsensitive('purpose', $search);
                });
            })
            ->orderByRaw("CASE WHEN UPPER(priority_level) = 'URGENT' THEN 0 ELSE 1 END ASC")
            ->latest('supply_request_id')
            ->paginate(5);

        $batchFollowUp = $this->followUpService->resolveBatchContext($paginator->items());

        $supplyRequests = $paginator->through(function (SupplyRequest $supplyRequest) use ($batchFollowUp): array {
            $requiresProcurement = $this->requiresProcurement($supplyRequest);
            $trackingStatus = $this->resolveTrackingStatus($supplyRequest, $requiresProcurement);

            return [
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'request_code' => (string) $supplyRequest->request_code,
                'status' => $trackingStatus,
                'raw_status' => (string) $supplyRequest->status,
                'priority_level' => strtoupper((string) ($supplyRequest->priority_level ?? 'NORMAL')),
                'request_date' => optional($supplyRequest->request_date)?->toDateString(),
                'items_count' => (int) $supplyRequest->items->count(),
                'items_summary' => $supplyRequest->items->take(2)->pluck('item_name_snapshot')->implode(', '),
                'follow_up_context' => $batchFollowUp[(int) $supplyRequest->supply_request_id] ?? [
                    'is_follow_up' => false,
                    'type' => 'STANDARD',
                    'label' => 'Standard Requisition',
                    'parent' => null,
                    'children' => [],
                ],
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
            'PARTIALLY_RECEIVED' => $query->whereHas('distributionRecords', fn (Builder $distributionQuery) => $distributionQuery->where('status', 'RECEIVED')),
            'PARTIALLY_RELEASED' => $query->whereHas('distributionRecords', fn (Builder $distributionQuery) => $distributionQuery->whereIn('status', ['RELEASED', 'RECEIVED'])),
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
                ->where('status', 'REJECTED')
                ->whereDoesntHave('distributionRecords', fn (Builder $distributionQuery) => $distributionQuery->whereIn('status', ['RELEASED', 'RECEIVED']))
                ->whereDoesntHave('purchaseOrders', fn (Builder $purchaseOrderQuery) => $purchaseOrderQuery->whereIn('status', ['PLACED', 'DRAFT']))
                ->whereHas('purchaseRequests', fn (Builder $purchaseRequestQuery) => $purchaseRequestQuery->where('status', 'REJECTED')),
            'RETURNED_FOR_REVISION' => $query
                ->where('status', 'DRAFT')
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

        $categories = \App\Models\ItemCategory::query()
            ->where('status', 'ACTIVE')
            ->orderBy('category_name')
            ->get(['item_category_id', 'category_name']);

        return Inertia::render('Office/SupplyRequests/Create', [
            'ppmpOptions' => $ppmpOptions,
            'selectedPpmpId' => $selectedPpmpId > 0 ? $selectedPpmpId : null,
            'requestDate' => now()->toDateString(),
            'categories' => $categories,
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

        if ($request->boolean('submit_after_save')) {
            abort_unless($request->user()?->can('office.supply-request.submit', $supplyRequest), 403);

            $submittedRequest = $this->service->submit($supplyRequest, $request->user(), $payload['review_notes'] ?? null);

            return redirect()->route('office.supply-requests.show', $supplyRequest->supply_request_id)
                ->with('success', $this->submitSuccessMessage($submittedRequest));
        }

        return redirect()->route('office.supply-requests.show', $supplyRequest->supply_request_id)
            ->with('success', 'Supply request created successfully.');
    }

    public function show(Request $request, SupplyRequest $supplyRequest): Response
    {
        abort_unless($request->user()?->can('office.supply-request.view', $supplyRequest), 403);

        $supplyRequest->loadMissing([
            'items.ppmpItem.item.inventory',
            'office',
            'category',
            'requester.role',
            'requester.office',
            'approver.role',
            'purchaseRequests.requester.role',
            'purchaseRequests.purchaseOrders.creator.role',
            'purchaseRequests.purchaseOrders.approver.role',
            'purchaseRequests.purchaseOrders.supplier',
            'purchaseOrders.creator.role',
            'purchaseOrders.approver.role',
            'purchaseOrders.supplier',
            'distributionRecords.items',
            'distributionRecords.preparer.role',
            'distributionRecords.receiver.role',
        ]);

        $requiresProcurement = $this->requiresProcurement($supplyRequest);
        $trackingStatus = $this->resolveTrackingStatus($supplyRequest, $requiresProcurement);
        $history = $this->historyTimelineService->forRecord($request->user(), $supplyRequest);
        $supplyRequestData = $this->showPayload($supplyRequest, $trackingStatus, $requiresProcurement, $history);
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
                    'role' => (string) ($entry->changedBy->role?->name ?? $entry->changedBy->role?->code ?? 'Officer'),
                    'office' => (string) ($entry->changedBy->office?->name ?? $entry->changedBy->office?->code ?? ''),
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
                    'role' => (string) ($entry->approver->role?->name ?? $entry->approver->role?->code ?? 'Approver'),
                    'office' => (string) ($entry->approver->office?->name ?? $entry->approver->office?->code ?? ''),
                ] : null,
            ])->values(),
        ]);
    }

    private function showPayload(SupplyRequest $supplyRequest, string $trackingStatus, bool $requiresProcurement, array $history = []): array
    {
        $ppmpItemContext = $this->ppmpItemContext($supplyRequest);

        return [
            'supply_request_id' => (int) $supplyRequest->supply_request_id,
            'office_id' => (int) $supplyRequest->office_id,
            'ppmp_id' => $supplyRequest->ppmp_id ? (int) $supplyRequest->ppmp_id : null,
            'item_category_id' => $supplyRequest->item_category_id ? (int) $supplyRequest->item_category_id : null,
            'category' => $supplyRequest->category ? [
                'item_category_id' => (int) $supplyRequest->category->item_category_id,
                'category_name' => (string) $supplyRequest->category->category_name,
            ] : null,
            'request_code' => (string) $supplyRequest->request_code,
            'status' => $trackingStatus,
            'raw_status' => (string) $supplyRequest->status,
            'request_date' => optional($supplyRequest->request_date)?->toDateString(),
            'needed_by_date' => optional($supplyRequest->needed_by_date)?->toDateString(),
            'request_category' => (string) ($supplyRequest->category?->category_name ?? $supplyRequest->request_category),
            'purpose' => (string) $supplyRequest->purpose,
            'priority_level' => (string) $supplyRequest->priority_level,
            'review_notes' => $supplyRequest->review_notes,
            'follow_up_context' => $this->followUpService->resolveContext($supplyRequest),
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
            'processing_timeline' => $this->buildProcessingTimeline($supplyRequest, $trackingStatus, $requiresProcurement, $history),
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
        $distributionRecords = $this->fulfillmentDistributionRecords($supplyRequest);

        $releasedPool = $distributionRecords
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->flatMap(fn ($record) => $record->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($items): float => (float) $items->sum('quantity_released'));
        $receivedPool = $distributionRecords
            ->where('status', 'RECEIVED')
            ->flatMap(fn ($record) => $record->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($items): float => (float) $items->sum('quantity_received'));

        return $supplyRequest->items
            ->map(function ($item) use ($ppmpItemContext, &$releasedPool, &$receivedPool): array {
                $context = $ppmpItemContext->get((int) $item->ppmp_item_id, []);
                $itemId = (int) $item->item_id;
                $requested = (float) $item->quantity_requested;

                $availReleased = (float) ($releasedPool->get($itemId) ?? 0.0);
                $allocReleased = min($requested, $availReleased);
                $releasedPool->put($itemId, max($availReleased - $allocReleased, 0.0));

                $availReceived = (float) ($receivedPool->get($itemId) ?? 0.0);
                $allocReceived = min($requested, $availReceived);
                $receivedPool->put($itemId, max($availReceived - $allocReceived, 0.0));

                return [
                    'supply_request_item_id' => (int) $item->supply_request_item_id,
                    'ppmp_item_id' => (int) $item->ppmp_item_id,
                    'item_name_snapshot' => (string) ($item->item_name_snapshot ?? ''),
                    'quantity_requested' => $requested,
                    'quantity_released' => $allocReleased,
                    'quantity_received' => $allocReceived,
                    'default_unit' => isset($context['default_unit']) ? (string) $context['default_unit'] : (string) ($item->item?->default_unit ?? 'box'),
                    'pieces_per_box' => isset($context['pieces_per_box']) ? (int) $context['pieces_per_box'] : (int) ($item->item?->pieces_per_box ?? 1),
                    'sub_unit' => isset($context['sub_unit']) ? (string) $context['sub_unit'] : (string) ($item->item?->sub_unit ?? 'piece'),
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
        $followUpSupplyRequestIds = $this->linkedFollowUpSupplyRequestIds($supplyRequest);

        return DistributionRecord::query()
            ->whereIn('supply_request_id', array_merge([(int) $supplyRequest->supply_request_id], $followUpSupplyRequestIds))
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->latest('distribution_record_id')
            ->get(['distribution_record_id', 'supply_request_id', 'status', 'storage_location', 'release_date'])
            ->map(fn (DistributionRecord $record): array => [
                'distribution_record_id' => (int) $record->distribution_record_id,
                'supply_request_id' => (int) $record->supply_request_id,
                'is_follow_up_fulfillment' => (int) $record->supply_request_id !== (int) $supplyRequest->supply_request_id,
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
        $baseStatus = strtoupper((string) $supplyRequest->status);

        $progress = $this->distributionProgress($supplyRequest);

        if ($progress['received'] > 0) {
            return $progress['received'] + 0.0001 >= $progress['requested']
                ? 'RECEIVED'
                : 'PARTIALLY_RECEIVED';
        }

        if ($progress['released'] > 0) {
            return $progress['released'] + 0.0001 >= $progress['requested']
                ? 'RELEASED'
                : 'PARTIALLY_RELEASED';
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

        if ($baseStatus === 'REJECTED') {
            return 'REJECTED';
        }

        if ($baseStatus === 'CANCELLED') {
            return 'CANCELLED';
        }

        $purchaseRequestStatuses = $supplyRequest->purchaseRequests
            ->pluck('status')
            ->map(fn ($status): string => strtoupper((string) $status));

        if ($purchaseRequestStatuses->contains('REJECTED')) {
            return 'REJECTED';
        }

        if ($baseStatus === 'DRAFT' && $purchaseRequestStatuses->contains('RETURNED_FOR_REVISION')) {
            return 'RETURNED_FOR_REVISION';
        }

        if ($purchaseRequestStatuses->contains('PROCESSING')) {
            return 'PROCESSING';
        }

        if ($purchaseRequestStatuses->contains('OPEN')) {
            return 'IN_REVIEW';
        }

        if ($baseStatus === 'SUBMITTED' && ! $requiresProcurement) {
            return 'IN_REVIEW';
        }

        return $baseStatus;
    }

    /**
     * @return array{requested: float, released: float, received: float}
     */
    private function distributionProgress(SupplyRequest $supplyRequest): array
    {
        $distributionRecords = $this->fulfillmentDistributionRecords($supplyRequest);

        return [
            'requested' => (float) $supplyRequest->items->sum('quantity_requested'),
            'released' => (float) $distributionRecords
                ->whereIn('status', ['RELEASED', 'RECEIVED'])
                ->flatMap(fn ($record) => $record->items)
                ->sum('quantity_released'),
            'received' => (float) $distributionRecords
                ->where('status', 'RECEIVED')
                ->flatMap(fn ($record) => $record->items)
                ->sum('quantity_received'),
        ];
    }

    /**
     * @return Collection<int, DistributionRecord>
     */
    private function fulfillmentDistributionRecords(SupplyRequest $supplyRequest): Collection
    {
        $supplyRequest->loadMissing('distributionRecords.items');

        $directRecords = $supplyRequest->distributionRecords;
        $followUpSupplyRequestIds = $this->linkedFollowUpSupplyRequestIds($supplyRequest);

        if ($followUpSupplyRequestIds === []) {
            return $directRecords;
        }

        $followUpRecords = DistributionRecord::query()
            ->with('items')
            ->whereIn('supply_request_id', $followUpSupplyRequestIds)
            ->get();

        return $directRecords
            ->concat($followUpRecords)
            ->values();
    }

    /**
     * @return array<int, int>
     */
    private function linkedFollowUpSupplyRequestIds(SupplyRequest $supplyRequest): array
    {
        return $this->followUpService->resolveAllFollowUpChildSupplyRequestIds($supplyRequest);
    }

    /**
     * @param  array{status?: \Illuminate\Support\Collection<int, \App\Models\StatusHistory>, approval?: \Illuminate\Support\Collection<int, \App\Models\ApprovalHistory>}  $history
     * @return array{mode: string, current_status: string, workflow_status: string, is_completed: bool, is_rejected: bool, is_cancelled: bool, is_returned: bool, stages: array<int, array<string, mixed>>}
     */
    private function buildProcessingTimeline(SupplyRequest $supplyRequest, string $trackingStatus, bool $requiresProcurement, array $history = []): array
    {
        $mode = $requiresProcurement ? 'PROCUREMENT' : 'DIRECT_STOCK';
        $status = strtoupper($trackingStatus);

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
                'PARTIALLY_RELEASED' => 5,
                'PARTIALLY_RECEIVED' => 5,
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
                'PARTIALLY_RELEASED' => 4,
                'PARTIALLY_RECEIVED' => 4,
                'RELEASED' => 4,
                'RECEIVED' => 5,
                'COMPLETED' => 5,
            ];

        $currentOrder = $orderMap[$status] ?? 1;

        $isCompleted = in_array($status, ['RECEIVED', 'COMPLETED'], true);
        $isRejected = $status === 'REJECTED' || (string) $supplyRequest->status === 'REJECTED';
        $isCancelled = $status === 'CANCELLED' || (string) $supplyRequest->status === 'CANCELLED';
        $isReturned = $status === 'RETURNED_FOR_REVISION' || (string) $supplyRequest->status === 'RETURNED_FOR_REVISION';

        $statusHistory = collect($history['status'] ?? []);
        $requester = $supplyRequest->requester;
        $office = $supplyRequest->office;
        $primaryPr = $supplyRequest->purchaseRequests->sortByDesc('purchase_request_id')->first();
        $primaryPo = $supplyRequest->purchaseOrders->sortByDesc('purchase_order_id')->first()
            ?? $supplyRequest->purchaseRequests->flatMap->purchaseOrders->sortByDesc('purchase_order_id')->first();
        $primaryDist = $supplyRequest->distributionRecords
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->sortByDesc('distribution_record_id')
            ->first();

        // Check if rejection occurred in PR (Procurement) or SR (GSO Validation)
        $rejectionStageOrder = 3;
        $rejectionActor = null;
        $rejectionRole = null;
        $rejectionOffice = null;
        $rejectionTime = null;
        $rejectionSummary = null;

        if ($isRejected) {
            if ($requiresProcurement && $primaryPr && (string) $primaryPr->status === 'REJECTED') {
                $rejectionStageOrder = 4;
                $prRejectionStatus = $primaryPr->statusHistory()->where('new_status', 'REJECTED')->latest('id')->first();
                $rejectionActor = $prRejectionStatus?->changedBy?->name ?? 'BAC Procurement Officer';
                $rejectionRole = $prRejectionStatus?->changedBy?->role?->name ?? 'Procurement Officer';
                $rejectionOffice = $prRejectionStatus?->changedBy?->office?->name ?? 'Bids and Awards Committee';
                $rejectionTime = optional($prRejectionStatus?->changed_at ?? $primaryPr->updated_at)?->toIso8601String();
                $rejectionSummary = $primaryPr->remarks ?: ($prRejectionStatus?->remarks ?: 'Purchase request rejected during procurement evaluation.');
            } else {
                $srRejectionStatus = $statusHistory->firstWhere('new_status', 'REJECTED');
                $rejectionActor = $srRejectionStatus?->changedBy?->name ?? 'GSO Property Officer';
                $rejectionRole = $srRejectionStatus?->changedBy?->role?->name ?? 'GSO Property Officer';
                $rejectionOffice = $srRejectionStatus?->changedBy?->office?->name ?? 'General Services Office';
                $rejectionTime = optional($srRejectionStatus?->changed_at ?? $supplyRequest->updated_at)?->toIso8601String();
                $rejectionSummary = $srRejectionStatus?->remarks ?: ($supplyRequest->review_notes ?: 'Supply request disapproved during GSO warehouse validation.');
            }
        }

        // 1. Resolve Stage 1 (Draft)
        $draftEntry = $statusHistory->firstWhere('new_status', 'DRAFT') ?? $statusHistory->last();
        $stage1Actor = $draftEntry?->changedBy?->name ?? $requester?->name ?? 'Requisitioning Officer';
        $stage1Role = $draftEntry?->changedBy?->role?->name ?? $requester?->role?->name ?? 'Office Requisitioner';
        $stage1Office = $draftEntry?->changedBy?->office?->name ?? $office?->name ?? 'End-User Office';
        $stage1Time = optional($draftEntry?->changed_at ?? $supplyRequest->created_at)?->toIso8601String();
        $stage1Summary = sprintf('Request %s drafted and item quantities configured.', (string) $supplyRequest->request_code);

        // 2. Resolve Stage 2 (Submitted to GSO)
        $submittedEntry = $statusHistory->firstWhere('new_status', 'SUBMITTED');
        $stage2Reached = $isCompleted || $currentOrder >= 2;
        $stage2Actor = $submittedEntry?->changedBy?->name ?? ($stage2Reached ? ($requester?->name ?? 'Office Requisitioner') : null);
        $stage2Role = $submittedEntry?->changedBy?->role?->name ?? ($stage2Reached ? ($requester?->role?->name ?? 'Office Requisitioner') : 'Office Requisitioner');
        $stage2Office = $submittedEntry?->changedBy?->office?->name ?? ($stage2Reached ? ($office?->name ?? 'End-User Office') : $office?->name);
        $stage2Time = $stage2Reached ? optional($submittedEntry?->changed_at ?? $supplyRequest->request_date ?? $supplyRequest->created_at)?->toIso8601String() : null;
        $stage2Summary = $stage2Reached
            ? 'Submitted to GSO for warehouse stock verification and municipal allocation.'
            : 'Pending official submission by requesting office.';

        // 3. Resolve Stage 3 (GSO Validation)
        $gsoEntry = $statusHistory->first(fn ($e) => in_array($e->new_status, ['IN_REVIEW', 'FOR_PROCUREMENT', 'APPROVED', 'RELEASED', 'PARTIALLY_RELEASED', 'REJECTED']));
        $stage3Reached = $isCompleted || $currentOrder >= 3;
        $stage3Actor = ($isRejected && $rejectionStageOrder === 3)
            ? $rejectionActor
            : ($gsoEntry?->changedBy?->name ?? ($stage3Reached ? ($primaryPr?->requester?->name ?? $primaryDist?->preparer?->name ?? 'GSO Property Officer') : null));
        $stage3Role = ($isRejected && $rejectionStageOrder === 3)
            ? $rejectionRole
            : ($gsoEntry?->changedBy?->role?->name ?? ($stage3Reached ? 'GSO Property Officer' : 'GSO Property Officer'));
        $stage3Office = ($isRejected && $rejectionStageOrder === 3)
            ? $rejectionOffice
            : ($gsoEntry?->changedBy?->office?->name ?? 'General Services Office');
        $stage3Time = ($isRejected && $rejectionStageOrder === 3)
            ? $rejectionTime
            : ($stage3Reached ? optional($gsoEntry?->changed_at ?? $primaryPr?->created_at ?? $primaryDist?->created_at)?->toIso8601String() : null);
        $stage3Summary = ($isRejected && $rejectionStageOrder === 3)
            ? $rejectionSummary
            : ($stage3Reached
                ? ($requiresProcurement
                    ? 'Warehouse stock verified: shortage identified and routed to BAC Procurement Unit.'
                    : 'Warehouse stock verified: supplies available in stock and approved for direct release.')
                : 'Pending GSO warehouse inventory assessment and stock availability check.');

        if ($requiresProcurement) {
            // Stage 4: Procurement Unit
            $stage4Reached = $isCompleted || $currentOrder >= 4;
            $stage4Actor = null;
            $stage4Role = 'BAC Procurement Unit';
            $stage4Office = 'Bids and Awards Committee';
            $stage4Time = null;
            $stage4Summary = 'Pending BAC procurement evaluation upon shortage routing.';

            if ($isRejected && $rejectionStageOrder === 4) {
                $stage4Actor = $rejectionActor;
                $stage4Role = $rejectionRole;
                $stage4Office = $rejectionOffice;
                $stage4Time = $rejectionTime;
                $stage4Summary = $rejectionSummary;
            } elseif ($stage4Reached) {
                if ($primaryPo && in_array(strtoupper((string) $primaryPo->status), ['PLACED', 'RECEIVED', 'COMPLETED'])) {
                    // Avoid Executive (Mayor) attribution for procurement unit operational execution
                    $poActorUser = $primaryPo->creator;
                    if (! $poActorUser || str_contains(strtolower((string) ($poActorUser->role?->name ?? '')), 'executive')) {
                        $poActorUser = (! str_contains(strtolower((string) ($primaryPo->approver?->role?->name ?? '')), 'executive'))
                            ? $primaryPo->approver
                            : null;
                    }
                    $stage4Actor = $poActorUser?->name ?? 'BAC Procurement Officer';
                    $stage4Role = $poActorUser?->role?->name ?? 'BAC Procurement Officer';
                    $stage4Office = $poActorUser?->office?->name ?? 'Bids and Awards Committee';
                    $stage4Time = optional($primaryPo->date_approved ?? $primaryPo->created_at)?->toIso8601String();
                    $stage4Summary = sprintf('Purchase Order %s issued to %s (%s).', (string) $primaryPo->po_number, (string) ($primaryPo->supplier?->supplier_name ?? 'Contractor'), $primaryPo->status === 'COMPLETED' || $primaryPo->status === 'RECEIVED' ? 'Delivered & Inspected' : 'Awaiting Delivery');
                } elseif ($primaryPo) {
                    $poActorUser = (! str_contains(strtolower((string) ($primaryPo->creator?->role?->name ?? '')), 'executive')) ? $primaryPo->creator : null;
                    $stage4Actor = $poActorUser?->name ?? 'BAC Procurement Officer';
                    $stage4Role = $poActorUser?->role?->name ?? 'BAC Procurement Officer';
                    $stage4Office = $poActorUser?->office?->name ?? 'Bids and Awards Committee';
                    $stage4Time = optional($primaryPo->created_at)?->toIso8601String();
                    $stage4Summary = sprintf('Purchase Order %s drafted; awaiting procurement placement.', (string) $primaryPo->po_number);
                } elseif ($primaryPr) {
                    $stage4Actor = $primaryPr->requester?->name ?? 'BAC Procurement Secretariat';
                    $stage4Role = $primaryPr->requester?->role?->name ?? 'Procurement Officer';
                    $stage4Office = $primaryPr->requester?->office?->name ?? 'Bids and Awards Committee';
                    $stage4Time = optional($primaryPr->created_at)?->toIso8601String();
                    $stage4Summary = sprintf('Purchase Request PR-%06d queued in BAC procurement review.', (int) $primaryPr->purchase_request_id);
                } else {
                    $stage4Actor = 'BAC Procurement Secretariat';
                    $stage4Summary = 'In procurement review; evaluating supplier pricing and purchase requirements.';
                }
            }

            // Stage 5: Released for Office
            $stage5Reached = $isCompleted || $currentOrder >= 5;
            $stage5Actor = $primaryDist?->preparer?->name ?? ($stage5Reached ? 'GSO Warehouse Custodian' : null);
            $stage5Role = $primaryDist?->preparer?->role?->name ?? 'GSO Property Custodian';
            $stage5Office = 'General Services Office';
            $stage5Time = $stage5Reached ? optional($primaryDist?->release_date ?? $primaryDist?->created_at)?->toIso8601String() : null;
            $stage5Summary = $stage5Reached
                ? ($primaryDist
                    ? sprintf('Supplies prepared and released for pickup under %s (Storage: %s).', (string) $primaryDist->distribution_code, (string) ($primaryDist->storage_location ?? 'GSO Warehouse'))
                    : 'Supplies received from supplier and released for office collection.')
                : 'Pending procurement delivery and GSO warehouse staging for office pickup.';

            // Stage 6: Completed
            $stage6Reached = $isCompleted || $currentOrder >= 6;
            $stage6Actor = $primaryDist?->receiver?->name ?? ($stage6Reached ? ($requester?->name ?? 'Office Requisitioner') : null);
            $stage6Role = $primaryDist?->receiver?->role?->name ?? 'End-User Unit Recipient';
            $stage6Office = $office?->name ?? 'End-User Office';
            $stage6Time = $stage6Reached ? optional($primaryDist?->received_date ?? $primaryDist?->updated_at ?? $supplyRequest->updated_at)?->toIso8601String() : null;
            $stage6Summary = $stage6Reached
                ? 'Supplies claimed, inspected, and confirmed received by requesting office. Workflow successfully fulfilled.'
                : 'Office to confirm receipt and verify items upon physical pickup.';

            $stages = [
                [
                    'order' => 1,
                    'key' => 'DRAFT',
                    'label' => 'Stage 1: Draft Request',
                    'owner' => 'Office User',
                    'actor_name' => $stage1Actor,
                    'actor_role' => $stage1Role,
                    'actor_office' => $stage1Office,
                    'completed_at' => $stage1Time,
                    'action_summary' => $stage1Summary,
                ],
                [
                    'order' => 2,
                    'key' => 'SUBMITTED',
                    'label' => 'Stage 2: Submitted to GSO',
                    'owner' => 'GSO Officer',
                    'actor_name' => $stage2Actor,
                    'actor_role' => $stage2Role,
                    'actor_office' => $stage2Office,
                    'completed_at' => $stage2Time,
                    'action_summary' => $stage2Summary,
                ],
                [
                    'order' => 3,
                    'key' => 'IN_REVIEW',
                    'label' => 'Stage 3: GSO Validation',
                    'owner' => 'GSO Officer',
                    'actor_name' => $stage3Actor,
                    'actor_role' => $stage3Role,
                    'actor_office' => $stage3Office,
                    'completed_at' => $stage3Time,
                    'action_summary' => $stage3Summary,
                ],
                [
                    'order' => 4,
                    'key' => 'PROCESSING',
                    'label' => 'Stage 4: Procurement Unit',
                    'owner' => 'Procurement Unit',
                    'actor_name' => $stage4Actor,
                    'actor_role' => $stage4Role,
                    'actor_office' => $stage4Office,
                    'completed_at' => $stage4Time,
                    'action_summary' => $stage4Summary,
                ],
                [
                    'order' => 5,
                    'key' => 'RELEASED',
                    'label' => 'Stage 5: Released for Office',
                    'owner' => 'GSO Officer',
                    'actor_name' => $stage5Actor,
                    'actor_role' => $stage5Role,
                    'actor_office' => $stage5Office,
                    'completed_at' => $stage5Time,
                    'action_summary' => $stage5Summary,
                ],
                [
                    'order' => 6,
                    'key' => 'COMPLETED',
                    'label' => 'Stage 6: Completed',
                    'owner' => 'Office User',
                    'actor_name' => $stage6Actor,
                    'actor_role' => $stage6Role,
                    'actor_office' => $stage6Office,
                    'completed_at' => $stage6Time,
                    'action_summary' => $stage6Summary,
                ],
            ];
        } else {
            // Direct Stock mode
            $stage4Reached = $isCompleted || $currentOrder >= 4;
            $stage4Actor = $primaryDist?->preparer?->name ?? ($stage4Reached ? 'GSO Warehouse Custodian' : null);
            $stage4Role = $primaryDist?->preparer?->role?->name ?? 'GSO Property Custodian';
            $stage4Office = 'General Services Office';
            $stage4Time = $stage4Reached ? optional($primaryDist?->release_date ?? $primaryDist?->created_at)?->toIso8601String() : null;
            $stage4Summary = $stage4Reached
                ? ($primaryDist
                    ? sprintf('Supplies released directly from warehouse stock under %s (Storage: %s).', (string) $primaryDist->distribution_code, (string) ($primaryDist->storage_location ?? 'GSO Warehouse'))
                    : 'Direct stock supplies released for office collection.')
                : 'Pending GSO warehouse staging for office pickup.';

            $stage5Reached = $isCompleted || $currentOrder >= 5;
            $stage5Actor = $primaryDist?->receiver?->name ?? ($stage5Reached ? ($requester?->name ?? 'Office Requisitioner') : null);
            $stage5Role = $primaryDist?->receiver?->role?->name ?? 'End-User Unit Recipient';
            $stage5Office = $office?->name ?? 'End-User Office';
            $stage5Time = $stage5Reached ? optional($primaryDist?->received_date ?? $primaryDist?->updated_at ?? $supplyRequest->updated_at)?->toIso8601String() : null;
            $stage5Summary = $stage5Reached
                ? 'Supplies claimed, inspected, and confirmed received by requesting office. Workflow successfully fulfilled.'
                : 'Office to confirm receipt and verify items upon physical pickup.';

            $stages = [
                [
                    'order' => 1,
                    'key' => 'DRAFT',
                    'label' => 'Stage 1: Draft Request',
                    'owner' => 'Office User',
                    'actor_name' => $stage1Actor,
                    'actor_role' => $stage1Role,
                    'actor_office' => $stage1Office,
                    'completed_at' => $stage1Time,
                    'action_summary' => $stage1Summary,
                ],
                [
                    'order' => 2,
                    'key' => 'SUBMITTED',
                    'label' => 'Stage 2: Submitted to GSO',
                    'owner' => 'GSO Officer',
                    'actor_name' => $stage2Actor,
                    'actor_role' => $stage2Role,
                    'actor_office' => $stage2Office,
                    'completed_at' => $stage2Time,
                    'action_summary' => $stage2Summary,
                ],
                [
                    'order' => 3,
                    'key' => 'IN_REVIEW',
                    'label' => 'Stage 3: GSO Validation',
                    'owner' => 'GSO Officer',
                    'actor_name' => $stage3Actor,
                    'actor_role' => $stage3Role,
                    'actor_office' => $stage3Office,
                    'completed_at' => $stage3Time,
                    'action_summary' => $stage3Summary,
                ],
                [
                    'order' => 4,
                    'key' => 'RELEASED',
                    'label' => 'Stage 4: Released for Office',
                    'owner' => 'GSO Officer',
                    'actor_name' => $stage4Actor,
                    'actor_role' => $stage4Role,
                    'actor_office' => $stage4Office,
                    'completed_at' => $stage4Time,
                    'action_summary' => $stage4Summary,
                ],
                [
                    'order' => 5,
                    'key' => 'COMPLETED',
                    'label' => 'Stage 5: Completed',
                    'owner' => 'Office User',
                    'actor_name' => $stage5Actor,
                    'actor_role' => $stage5Role,
                    'actor_office' => $stage5Office,
                    'completed_at' => $stage5Time,
                    'action_summary' => $stage5Summary,
                ],
            ];
        }

        $finalOrder = $requiresProcurement ? 6 : 5;

        $processedStages = collect($stages)->map(function (array $stage) use ($status, $currentOrder, $isCompleted, $isRejected, $isCancelled, $isReturned, $rejectionStageOrder, $finalOrder): array {
            $stageOrder = (int) $stage['order'];

            $isStageCompleted = false;
            $isStageCurrent = false;
            $isStageRejected = false;
            $isStageCancelled = false;
            $isStageDiscontinued = false;
            $isStageReturned = false;
            $statusBadge = 'PENDING';
            $actionLabel = 'Designated Unit / Officer:';
            $reached = false;
            $actionSummary = $stage['action_summary'] ?? null;

            if ($isCompleted) {
                // All stages reached and completed!
                $reached = true;
                $isStageCompleted = true;
                $statusBadge = 'COMPLETED';
                $actionLabel = ($stageOrder === $finalOrder) ? 'Confirmed by:' : 'Executed by:';
            } elseif ($isRejected) {
                if ($stageOrder < $rejectionStageOrder) {
                    $reached = true;
                    $isStageCompleted = true;
                    $statusBadge = 'COMPLETED';
                    $actionLabel = 'Executed by:';
                } elseif ($stageOrder === $rejectionStageOrder) {
                    $reached = true;
                    $isStageRejected = true;
                    $statusBadge = 'REJECTED';
                    $actionLabel = 'Rejected by:';
                } else {
                    $reached = false;
                    $isStageDiscontinued = true;
                    $statusBadge = 'DISCONTINUED';
                    $actionLabel = 'Workflow Terminated';
                    $actionSummary = sprintf('Stage discontinued due to rejection at Stage %d.', $rejectionStageOrder);
                }
            } elseif ($isCancelled) {
                $cancelOrder = $currentOrder;
                if ($stageOrder < $cancelOrder) {
                    $reached = true;
                    $isStageCompleted = true;
                    $statusBadge = 'COMPLETED';
                    $actionLabel = 'Executed by:';
                } elseif ($stageOrder === $cancelOrder) {
                    $reached = true;
                    $isStageCancelled = true;
                    $statusBadge = 'CANCELLED';
                    $actionLabel = 'Cancelled by:';
                } else {
                    $reached = false;
                    $isStageDiscontinued = true;
                    $statusBadge = 'DISCONTINUED';
                    $actionLabel = 'Workflow Terminated';
                    $actionSummary = sprintf('Stage discontinued due to cancellation at Stage %d.', $cancelOrder);
                }
            } else {
                // In-flight active workflow
                if ($stageOrder < $currentOrder) {
                    $reached = true;
                    $isStageCompleted = true;
                    $statusBadge = 'COMPLETED';
                    $actionLabel = 'Executed by:';
                } elseif ($stageOrder === $currentOrder) {
                    $reached = true;
                    if ($isReturned) {
                        $isStageReturned = true;
                        $statusBadge = 'RETURNED';
                        $actionLabel = 'Returned by:';
                    } else {
                        $isStageCurrent = true;
                        $statusBadge = 'ACTIVE';
                        $actionLabel = 'Currently Handled By:';
                    }
                } else {
                    $reached = false;
                    $statusBadge = 'PENDING';
                    $actionLabel = 'Designated Unit / Officer:';
                }
            }

            return [
                'order' => $stageOrder,
                'key' => $stage['key'],
                'label' => $stage['label'],
                'owner' => $stage['owner'],
                'actor_name' => $stage['actor_name'] ?? null,
                'actor_role' => $stage['actor_role'] ?? null,
                'actor_office' => $stage['actor_office'] ?? null,
                'completed_at' => $stage['completed_at'] ?? null,
                'action_summary' => $actionSummary,
                'action_label' => $actionLabel,
                'reached' => $reached,
                'is_current' => $isStageCurrent,
                'is_completed' => $isStageCompleted,
                'is_rejected' => $isStageRejected,
                'is_cancelled' => $isStageCancelled,
                'is_discontinued' => $isStageDiscontinued,
                'is_returned' => $isStageReturned,
                'status_badge' => $statusBadge,
            ];
        })->values()->all();

        $workflowStatus = 'IN_PROGRESS';
        if ($isCompleted) {
            $workflowStatus = 'COMPLETED';
        } elseif ($isRejected) {
            $workflowStatus = 'REJECTED';
        } elseif ($isCancelled) {
            $workflowStatus = 'CANCELLED';
        } elseif ($isReturned) {
            $workflowStatus = 'RETURNED';
        }

        return [
            'mode' => $mode,
            'current_status' => $status,
            'workflow_status' => $workflowStatus,
            'is_completed' => $isCompleted,
            'is_rejected' => $isRejected,
            'is_cancelled' => $isCancelled,
            'is_returned' => $isReturned,
            'stages' => $processedStages,
        ];
    }

    public function edit(Request $request, SupplyRequest $supplyRequest): Response
    {
        abort_unless($request->user()?->can('office.supply-request.update', $supplyRequest), 403);

        $officeId = (int) $request->user()?->office_id;
        $selectedPpmpId = (int) ($supplyRequest->ppmp_id ?? 0);

        $categories = \App\Models\ItemCategory::query()
            ->where('status', 'ACTIVE')
            ->orderBy('category_name')
            ->get(['item_category_id', 'category_name']);

        return Inertia::render('Office/SupplyRequests/Edit', [
            'supplyRequest' => $supplyRequest->load(['items.ppmpItem', 'items.item', 'category']),
            'categories' => $categories,
            'ppmpOptions' => $this->service->ppmpOptionsForOffice($officeId),
            'selectedPpmpId' => $selectedPpmpId > 0 ? $selectedPpmpId : null,
            'ppmpItemOptions' => $selectedPpmpId > 0
                ? $this->service->ppmpItemOptionsForRequestContext($officeId, $selectedPpmpId, (int) ($supplyRequest->item_category_id ?? 0))
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

        $categoryId = $request->query('category_id') ? (int) $request->query('category_id') : null;

        return response()->json([
            'ppmp_item_options' => $this->service->ppmpItemOptionsForRequestContext($officeId, (int) $ppmp->ppmp_id, $categoryId),
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

        if ($request->input('redirect_to') === 'index') {
            return redirect()->back()
                ->with('success', $this->submitSuccessMessage($submittedRequest));
        }

        return redirect()->route('office.supply-requests.show', $supplyRequest->supply_request_id)
            ->with('success', $this->submitSuccessMessage($submittedRequest));
    }

    private function submitSuccessMessage(SupplyRequest $submittedRequest): string
    {
        $scheduleMismatchCount = (int) ($submittedRequest->getAttribute('ppmp_schedule_mismatch_count') ?? 0);

        if ($scheduleMismatchCount > 0) {
            return sprintf(
                'Supply request submitted successfully. %d out-of-schedule line(s) were flagged for executive review.',
                $scheduleMismatchCount,
            );
        }

        return 'Supply request submitted successfully.';
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

        $distributionRecords = DistributionRecord::query()
            ->with([
                'items',
                'supplyRequest.purchaseRequests.sourceClaim',
                'delivery.purchaseOrder.purchaseRequest.sourceClaim',
            ])
            ->forOffice($officeId)
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->latest('distribution_record_id')
            ->get()
            ->map(function (DistributionRecord $record): array {
                $sourceClaim = $record->delivery?->purchaseOrder?->purchaseRequest?->sourceClaim
                    ?? $record->supplyRequest?->purchaseRequests?->firstWhere('source_claim_id')?->sourceClaim;

                if (! $sourceClaim && ($record->supplyRequest?->purpose || $record->notes)) {
                    if (preg_match('/(CLM-\d{4}-\d{5})/', ($record->supplyRequest?->purpose ?? '') . ' ' . ($record->notes ?? ''), $matches)) {
                        $sourceClaim = \App\Models\Claim::query()->where('claim_code', $matches[1])->first();
                    }
                }

                $isClaimReplacement = $sourceClaim !== null
                    || (string) ($record->supplyRequest?->request_category ?? '') === 'REPLACEMENT'
                    || (string) ($record->delivery?->purchaseOrder?->purchaseRequest?->source_type ?? '') === 'CLAIM_REPLACEMENT_FOLLOW_UP'
                    || str_contains(strtolower((string) ($record->supplyRequest?->purpose ?? '')), 'claim replacement');

                $existingClaim = \App\Models\Claim::query()
                    ->where('distribution_record_id', (int) $record->distribution_record_id)
                    ->first();

                $receivedAt = $record->status === 'RECEIVED' ? ($record->updated_at ?? $record->created_at) : null;
                $isClaimExpired = $receivedAt !== null && ($receivedAt->copy()->addHours(48)->isPast() || abs(now()->diffInHours($receivedAt)) > 48);

                return [
                    'distribution_record_id' => (int) $record->distribution_record_id,
                    'request_id' => (string) ($record->supplyRequest?->request_code ?? 'N/A'),
                    'claim_code' => $sourceClaim?->claim_code,
                    'claim_id' => $sourceClaim?->claims_id,
                    'request_date' => optional($record->supplyRequest?->request_date)?->toDateString(),
                    'items_summary' => (string) ($record->items->take(2)->pluck('item_name_snapshot')->implode(', ') ?: 'No released items listed'),
                    'items_count' => (int) $record->items->count(),
                    'location' => (string) ($record->storage_location ?? 'GSO Storage'),
                    'ready_since' => optional($record->release_date)?->toDateString(),
                    'received_at' => optional($receivedAt)?->toIso8601String(),
                    'status' => (string) $record->status,
                    'supply_request_id' => (int) ($record->supply_request_id ?? 0),
                    'is_claim_replacement' => $isClaimReplacement,
                    'has_existing_claim' => $existingClaim !== null,
                    'existing_claim_code' => $existingClaim?->claim_code,
                    'existing_claim_id' => $existingClaim?->claims_id,
                    'is_claim_expired' => $isClaimExpired,
                ];
            });

        $claimReplacements = \App\Models\Claim::query()
            ->with(['distributionItem.item', 'distributionRecord.supplyRequest'])
            ->where('office_id', $officeId)
            ->whereIn('status', ['REPLACEMENT_RELEASED', 'RESOLVED'])
            ->latest('claims_id')
            ->get()
            ->map(function (\App\Models\Claim $claim): array {
                $itemName = (string) ($claim->distributionItem?->item?->item_name
                    ?? $claim->distributionItem?->item_name_snapshot
                    ?? 'Replacement Item');
                $qty = (int) ($claim->replacement_quantity > 0 ? $claim->replacement_quantity : $claim->quantity_disputed);
                $isReleased = (string) $claim->status === 'REPLACEMENT_RELEASED';

                return [
                    'distribution_record_id' => 0,
                    'request_id' => (string) ($claim->distributionRecord?->supplyRequest?->request_code ?? $claim->claim_code) . ' (Repl.)',
                    'claim_code' => (string) $claim->claim_code,
                    'claim_id' => (int) $claim->claims_id,
                    'request_date' => optional($claim->claim_date)?->toDateString(),
                    'items_summary' => sprintf('%d item(s) %s (Replacement for %s)', $qty, $itemName, $claim->claim_code),
                    'items_count' => 1,
                    'location' => (string) ($claim->claim_location ?: 'GSO Storage'),
                    'ready_since' => optional($claim->updated_at)?->toDateString(),
                    'received_at' => ! $isReleased ? optional($claim->updated_at)?->toIso8601String() : null,
                    'status' => $isReleased ? 'RELEASED' : 'RECEIVED',
                    'supply_request_id' => (int) ($claim->distributionRecord?->supply_request_id ?? 0),
                    'is_claim_replacement' => true,
                    'has_existing_claim' => false,
                    'existing_claim_code' => null,
                    'existing_claim_id' => null,
                    'is_claim_expired' => false,
                ];
            });

        $combined = $claimReplacements->concat($distributionRecords)
            ->sortBy([
                fn (array $a, array $b): int => ($a['status'] === 'RELEASED' ? 0 : 1) <=> ($b['status'] === 'RELEASED' ? 0 : 1),
                fn (array $a, array $b): int => ($b['ready_since'] ?? '') <=> ($a['ready_since'] ?? ''),
            ])
            ->values();

        $page = (int) $request->input('page', 1);
        $perPage = 5;
        $paginatedRecords = new \Illuminate\Pagination\LengthAwarePaginator(
            $combined->forPage($page, $perPage)->values(),
            $combined->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Office/SuppliesReadyForClaim/Index', [
            'records' => $paginatedRecords,
        ]);
    }

}
