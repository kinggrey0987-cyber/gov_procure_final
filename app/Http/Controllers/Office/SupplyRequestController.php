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
                    $nested->whereLikeInsensitive('request_code', $search)
                        ->orWhereLikeInsensitive('status', $search)
                        ->orWhereLikeInsensitive('purpose', $search);
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

        $releasedByItemId = $distributionRecords
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->flatMap(fn ($record) => $record->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($items): float => (float) $items->sum('quantity_released'));
        $receivedByItemId = $distributionRecords
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

        $purchaseRequestStatuses = $supplyRequest->purchaseRequests
            ->pluck('status')
            ->map(fn ($status): string => strtoupper((string) $status));

        if ($baseStatus === 'REJECTED' && $purchaseRequestStatuses->contains('REJECTED')) {
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
        $sourceDeliveryIds = Delivery::query()
            ->whereHas('purchaseOrder', fn (Builder $query) => $query->where('supply_request_id', (int) $supplyRequest->supply_request_id))
            ->pluck('delivery_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($sourceDeliveryIds === []) {
            return [];
        }

        $visitedSupplyRequestIds = [(int) $supplyRequest->supply_request_id => true];
        $followUpSupplyRequestIds = [];
        $currentSourceDeliveryIds = $sourceDeliveryIds;

        while ($currentSourceDeliveryIds !== []) {
            $followUpPurchaseRequests = PurchaseRequest::query()
                ->where('source_type', 'PARTIAL_DELIVERY_FOLLOW_UP')
                ->whereIn('source_delivery_id', $currentSourceDeliveryIds)
                ->get(['purchase_request_id', 'supply_request_id']);

            $newSupplyRequestIds = $followUpPurchaseRequests
                ->pluck('supply_request_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0 && ! isset($visitedSupplyRequestIds[$id]))
                ->unique()
                ->values()
                ->all();

            if ($newSupplyRequestIds === []) {
                break;
            }

            foreach ($newSupplyRequestIds as $id) {
                $visitedSupplyRequestIds[$id] = true;
                $followUpSupplyRequestIds[] = $id;
            }

            $currentSourceDeliveryIds = Delivery::query()
                ->whereHas('purchaseOrder', fn (Builder $query) => $query->whereIn('supply_request_id', $newSupplyRequestIds))
                ->pluck('delivery_id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();
        }

        return $followUpSupplyRequestIds;
    }

    /**
     * @param  array{status?: \Illuminate\Support\Collection<int, \App\Models\StatusHistory>, approval?: \Illuminate\Support\Collection<int, \App\Models\ApprovalHistory>}  $history
     * @return array{mode: string, current_status: string, is_rejected: bool, stages: array<int, array<string, mixed>>}
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

        // 1. Resolve Stage 1 (Draft)
        $draftEntry = $statusHistory->firstWhere('new_status', 'DRAFT') ?? $statusHistory->last();
        $stage1Actor = $draftEntry?->changedBy?->name ?? $requester?->name ?? 'Requisitioning Officer';
        $stage1Role = $draftEntry?->changedBy?->role?->name ?? $requester?->role?->name ?? 'Office Requisitioner';
        $stage1Office = $draftEntry?->changedBy?->office?->name ?? $office?->name ?? 'End-User Office';
        $stage1Time = optional($draftEntry?->changed_at ?? $supplyRequest->created_at)?->toIso8601String();
        $stage1Summary = sprintf('Request %s drafted and item quantities configured.', (string) $supplyRequest->request_code);

        // 2. Resolve Stage 2 (Submitted to GSO)
        $submittedEntry = $statusHistory->firstWhere('new_status', 'SUBMITTED');
        $stage2Reached = $currentOrder >= 2;
        $stage2Actor = $submittedEntry?->changedBy?->name ?? ($stage2Reached ? ($requester?->name ?? 'Office Requisitioner') : null);
        $stage2Role = $submittedEntry?->changedBy?->role?->name ?? ($stage2Reached ? ($requester?->role?->name ?? 'Office Requisitioner') : 'Office Requisitioner');
        $stage2Office = $submittedEntry?->changedBy?->office?->name ?? ($stage2Reached ? ($office?->name ?? 'End-User Office') : $office?->name);
        $stage2Time = $stage2Reached ? optional($submittedEntry?->changed_at ?? $supplyRequest->request_date ?? $supplyRequest->created_at)?->toIso8601String() : null;
        $stage2Summary = $stage2Reached
            ? 'Submitted to GSO for warehouse stock verification and municipal allocation.'
            : 'Pending official submission by requesting office.';

        // 3. Resolve Stage 3 (GSO Validation)
        $gsoEntry = $statusHistory->first(fn ($e) => in_array($e->new_status, ['IN_REVIEW', 'FOR_PROCUREMENT', 'APPROVED', 'RELEASED', 'PARTIALLY_RELEASED', 'REJECTED']));
        $stage3Reached = $currentOrder >= 3;
        $stage3Actor = $gsoEntry?->changedBy?->name
            ?? ($stage3Reached ? ($primaryPr?->requester?->name ?? $primaryDist?->preparer?->name ?? 'GSO Property Officer') : null);
        $stage3Role = $gsoEntry?->changedBy?->role?->name
            ?? ($stage3Reached ? 'GSO Property Officer' : 'GSO Property Officer');
        $stage3Office = $gsoEntry?->changedBy?->office?->name ?? 'General Services Office';
        $stage3Time = $stage3Reached ? optional($gsoEntry?->changed_at ?? $primaryPr?->created_at ?? $primaryDist?->created_at)?->toIso8601String() : null;
        $stage3Summary = $stage3Reached
            ? ($requiresProcurement
                ? 'Warehouse stock verified: shortage identified and routed to BAC Procurement Unit.'
                : 'Warehouse stock verified: supplies available in stock and approved for direct release.')
            : 'Pending GSO warehouse inventory assessment and stock availability check.';

        if ($requiresProcurement) {
            // Stage 4: Procurement Unit
            $stage4Reached = $currentOrder >= 4;
            $stage4Actor = null;
            $stage4Role = 'BAC Procurement Unit';
            $stage4Office = 'Bids and Awards Committee';
            $stage4Time = null;
            $stage4Summary = 'Pending BAC procurement evaluation upon shortage routing.';

            if ($stage4Reached) {
                if ($primaryPo && in_array(strtoupper((string) $primaryPo->status), ['PLACED', 'RECEIVED', 'COMPLETED'])) {
                    $stage4Actor = $primaryPo->approver?->name ?? $primaryPo->creator?->name ?? 'BAC Procurement Secretariat';
                    $stage4Role = $primaryPo->approver ? 'Executive Approving Authority' : ($primaryPo->creator?->role?->name ?? 'BAC Procurement Officer');
                    $stage4Office = $primaryPo->approver ? 'Office of the Mayor / BAC' : 'Bids and Awards Committee';
                    $stage4Time = optional($primaryPo->date_approved ?? $primaryPo->created_at)?->toIso8601String();
                    $stage4Summary = sprintf('Purchase Order %s issued to %s (%s).', (string) $primaryPo->po_number, (string) ($primaryPo->supplier?->supplier_name ?? 'Contractor'), $primaryPo->status === 'COMPLETED' || $primaryPo->status === 'RECEIVED' ? 'Delivered & Inspected' : 'Awaiting Delivery');
                } elseif ($primaryPo) {
                    $stage4Actor = $primaryPo->creator?->name ?? 'BAC Procurement Officer';
                    $stage4Role = 'BAC Procurement Officer';
                    $stage4Office = 'Bids and Awards Committee';
                    $stage4Time = optional($primaryPo->created_at)?->toIso8601String();
                    $stage4Summary = sprintf('Purchase Order %s drafted; awaiting executive placement.', (string) $primaryPo->po_number);
                } elseif ($primaryPr) {
                    $stage4Actor = $primaryPr->requester?->name ?? 'BAC Procurement Secretariat';
                    $stage4Role = 'Procurement Officer';
                    $stage4Office = 'Bids and Awards Committee';
                    $stage4Time = optional($primaryPr->created_at)?->toIso8601String();
                    $stage4Summary = sprintf('Purchase Request PR-%06d queued in BAC procurement review.', (int) $primaryPr->purchase_request_id);
                } else {
                    $stage4Actor = 'BAC Procurement Secretariat';
                    $stage4Summary = 'In procurement review; evaluating supplier pricing and purchase requirements.';
                }
            }

            // Stage 5: Released for Office
            $stage5Reached = $currentOrder >= 5;
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
            $stage6Reached = $currentOrder >= 6;
            $stage6Actor = $primaryDist?->receiver?->name ?? ($stage6Reached ? ($requester?->name ?? 'Office Requisitioner') : null);
            $stage6Role = $primaryDist?->receiver?->role?->name ?? 'End-User Unit Recipient';
            $stage6Office = $office?->name ?? 'End-User Office';
            $stage6Time = $stage6Reached ? optional($primaryDist?->received_date ?? $primaryDist?->updated_at ?? $supplyRequest->updated_at)?->toIso8601String() : null;
            $stage6Summary = $stage6Reached
                ? 'Supplies claimed, inspected, and confirmed received by requesting office.'
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
            $stage4Reached = $currentOrder >= 4;
            $stage4Actor = $primaryDist?->preparer?->name ?? ($stage4Reached ? 'GSO Warehouse Custodian' : null);
            $stage4Role = $primaryDist?->preparer?->role?->name ?? 'GSO Property Custodian';
            $stage4Office = 'General Services Office';
            $stage4Time = $stage4Reached ? optional($primaryDist?->release_date ?? $primaryDist?->created_at)?->toIso8601String() : null;
            $stage4Summary = $stage4Reached
                ? ($primaryDist
                    ? sprintf('Supplies released directly from warehouse stock under %s (Storage: %s).', (string) $primaryDist->distribution_code, (string) ($primaryDist->storage_location ?? 'GSO Warehouse'))
                    : 'Direct stock supplies released for office collection.')
                : 'Pending GSO warehouse staging for office pickup.';

            $stage5Reached = $currentOrder >= 5;
            $stage5Actor = $primaryDist?->receiver?->name ?? ($stage5Reached ? ($requester?->name ?? 'Office Requisitioner') : null);
            $stage5Role = $primaryDist?->receiver?->role?->name ?? 'End-User Unit Recipient';
            $stage5Office = $office?->name ?? 'End-User Office';
            $stage5Time = $stage5Reached ? optional($primaryDist?->received_date ?? $primaryDist?->updated_at ?? $supplyRequest->updated_at)?->toIso8601String() : null;
            $stage5Summary = $stage5Reached
                ? 'Supplies claimed, inspected, and confirmed received by requesting office.'
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

        return [
            'mode' => $mode,
            'current_status' => $status,
            'is_rejected' => $status === 'REJECTED',
            'stages' => collect($stages)
                ->map(fn (array $stage): array => [
                    'key' => $stage['key'],
                    'label' => $stage['label'],
                    'owner' => $stage['owner'],
                    'actor_name' => $stage['actor_name'] ?? null,
                    'actor_role' => $stage['actor_role'] ?? null,
                    'actor_office' => $stage['actor_office'] ?? null,
                    'completed_at' => $stage['completed_at'] ?? null,
                    'action_summary' => $stage['action_summary'] ?? null,
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
