<?php

namespace App\Http\Controllers\Inventory;

use App\Models\Inventory;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\Delivery;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Analytics\AlertService;
use App\Services\Inventory\DistributionRecordService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RequestWorkflowController
{
    public function __construct(
        private readonly PurchaseRequestService $purchaseRequestService,
        private readonly AlertService $alertService,
        private readonly DistributionRecordService $distributionService,
        private readonly \App\Services\Inventory\SupplyRequestFollowUpService $followUpService,
    ) {
    }

    public function officeSupplyRequests(Request $request): Response
    {
        abort_unless($request->user()?->can('inventory.view-any'), 403);

        $search = trim((string) $request->query('q', ''));
        $officeId = (int) $request->query('office_id', 0);
        $allRows = $this->officeSupplyRequestRows($search, $officeId);

        $shortageSourceRows = ($officeId > 0 || $search !== '')
            ? $this->officeSupplyRequestRows('', 0)
            : $allRows;

        // Compute global shortage groups across all offices and categories so the Consolidation Hub is always fully informed
        // Only show groups with at least 2 pending shortages in the same category eligible for consolidation
        $officeShortageGroups = $shortageSourceRows
            ->where('stock_assessment', 'SHORTAGE_REQUIRES_PROCUREMENT')
            ->where('status', 'SUBMITTED')
            ->where('has_purchase_request', false)
            ->groupBy(fn ($r) => ((int) ($r['office']['id'] ?? 0)) . '_' . ((int) ($r['item_category_id'] ?? 0)))
            ->filter(function ($group, $key) {
                [$officeId] = explode('_', (string) $key);
                return (int) $officeId > 0 && $group->count() >= 2;
            })
            ->map(function ($group, $key) {
                $first = $group->first();
                return [
                    'group_key' => (string) $key,
                    'id' => (int) $first['office']['id'],
                    'office_id' => (int) $first['office']['id'],
                    'name' => (string) $first['office']['name'],
                    'item_category_id' => $first['item_category_id'] ? (int) $first['item_category_id'] : null,
                    'category_name' => (string) ($first['category_name'] ?? 'General Supplies'),
                    'count' => $group->count(),
                    'requests' => $group->values()->all(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        $rows = $allRows;

        $stockAssessmentFilter = trim((string) $request->query('stock_assessment', ''));
        if ($stockAssessmentFilter !== '') {
            $rows = $rows->where('stock_assessment', $stockAssessmentFilter);
        }

        $workflowBucketFilter = trim((string) $request->query('workflow_bucket', ''));
        if ($workflowBucketFilter !== '') {
            $rows = $rows->where('workflow_bucket', $workflowBucketFilter);
        }

        // Pin Urgent requests to the top, then prioritize actionable shortage requests, then newest first
        $rows = $rows->sort(function ($a, $b) {
            $isUrgentA = strtoupper(trim((string) ($a['priority_level'] ?? ''))) === 'URGENT';
            $isUrgentB = strtoupper(trim((string) ($b['priority_level'] ?? ''))) === 'URGENT';

            $isCompletedA = in_array((string) ($a['status'] ?? ''), ['COMPLETED', 'FULFILLED', 'RECEIVED'], true);
            $isCompletedB = in_array((string) ($b['status'] ?? ''), ['COMPLETED', 'FULFILLED', 'RECEIVED'], true);

            // Active urgent requests are always pinned to the very top
            $activeUrgentA = $isUrgentA && ! $isCompletedA;
            $activeUrgentB = $isUrgentB && ! $isCompletedB;

            if ($activeUrgentA !== $activeUrgentB) {
                return $activeUrgentB <=> $activeUrgentA;
            }

            // Active requests come before completed/fulfilled requests
            if ($isCompletedA !== $isCompletedB) {
                return $isCompletedA <=> $isCompletedB;
            }

            // Among active requests, prioritize actionable shortages
            $shortageA = ($a['status'] === 'SUBMITTED' && $a['stock_assessment'] === 'SHORTAGE_REQUIRES_PROCUREMENT' && ! $a['has_purchase_request']) ? 2 : 1;
            $shortageB = ($b['status'] === 'SUBMITTED' && $b['stock_assessment'] === 'SHORTAGE_REQUIRES_PROCUREMENT' && ! $b['has_purchase_request']) ? 2 : 1;

            if ($shortageA !== $shortageB) {
                return $shortageB <=> $shortageA;
            }

            // If both are completed and one was urgent, keep urgent first among completed
            if ($isUrgentA !== $isUrgentB) {
                return $isUrgentB <=> $isUrgentA;
            }

            return (int) $b['supply_request_id'] <=> (int) $a['supply_request_id'];
        })->values();

        $requests = $this->paginateOfficeSupplyRows($rows, $request);

        return Inertia::render('Inventory/OfficeSupplyRequests/Index', [
            'requests' => $requests,
            'offices' => $this->officeOptions(),
            'officeShortageGroups' => $officeShortageGroups,
            'filters' => [
                'q' => $search,
                'office_id' => $officeId > 0 ? $officeId : null,
                'stock_assessment' => $stockAssessmentFilter,
                'workflow_bucket' => $workflowBucketFilter,
                'page' => $requests->currentPage(),
            ],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function officeSupplyRequestRows(string $search, int $officeId): Collection
    {
        $query = SupplyRequest::query()
            ->with([
                'office',
                'category',
                'requester',
                'items.item.inventory',
                'items.item.category',
                'purchaseRequests.sourceDelivery.purchaseOrder.supplyRequest',
                'purchaseRequests.sourceClaim.supplyRequest',
                'distributionRecords.items',
            ])
            ->whereHas('office', fn ($officeQuery) => $officeQuery->where('code', '!=', 'GSO'))
            ->withCount('distributionRecords')
            ->orderByRaw("CASE WHEN UPPER(priority_level) = 'URGENT' THEN 0 ELSE 1 END ASC")
            ->latest('supply_request_id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->whereLikeInsensitive('request_code', $search)
                    ->orWhereLikeInsensitive('purpose', $search)
                    ->orWhereHas('office', fn ($officeQuery) => $officeQuery->whereLikeInsensitive('name', $search));
            });
        }

        if ($officeId > 0) {
            $query->where('office_id', $officeId);
        }

        $distributionReadyLookup = $this->distributionReadySupplyRequestLookup();
        $supplyRequests = $query->get();
        $batchFollowUp = $this->followUpService->resolveBatchContext($supplyRequests);

        return $supplyRequests
            ->map(fn (SupplyRequest $request): array => $this->officeSupplyRequestRow(
                $request,
                $distributionReadyLookup,
                $batchFollowUp[(int) $request->supply_request_id] ?? null
            ));
    }

    /**
     * @return array<int, int>
     */
    private function distributionReadySupplyRequestLookup(): array
    {
        $ids = Delivery::query()
            ->verified()
            ->whereDoesntHave('distributionRecords')
            ->with('purchaseOrder:supply_request_id,purchase_order_id')
            ->get()
            ->map(fn (Delivery $delivery): ?int => $delivery->purchaseOrder?->supply_request_id)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return array_flip($ids);
    }

    /**
     * @param  array<int, int>  $distributionReadyLookup
     * @param  array<string, mixed>|null  $followUpContext
     * @return array<string, mixed>
     */
    private function officeSupplyRequestRow(SupplyRequest $request, array $distributionReadyLookup, ?array $followUpContext = null): array
    {
        $requestedTotal = 0.0;
        $shortageTotal = 0.0;
        $requestedItems = [];
        $releasedPool = $this->releasedQuantityByItemId($request);
        $hasRemainingQuantity = false;
        $hasAvailableStock = false;

        foreach ($request->items as $item) {
            $itemId = (int) $item->item_id;
            $requested = (float) $item->quantity_requested;
            $availReleased = (float) ($releasedPool[$itemId] ?? 0.0);
            $released = min($requested, $availReleased);
            $releasedPool[$itemId] = max($availReleased - $released, 0.0);

            $remaining = max($requested - $released, 0.0);
            $available = (float) ($item->item?->inventory?->available_stock ?? 0) + (float) ($item->item?->inventory?->reserved_quantity ?? 0);

            $requestedTotal += $requested;
            $shortageTotal += max($remaining - $available, 0.0);
            $hasRemainingQuantity = $hasRemainingQuantity || $remaining > 0.0001;
            if ($remaining > 0.0001 && $available > 0.0001) {
                $hasAvailableStock = true;
            }

            $requestedItems[] = [
                'item_name' => (string) ($item->item?->item_name ?? $item->item_name_snapshot ?? 'Unknown Item'),
                'quantity_requested' => round($requested, 2),
                'unit' => (string) ($item->item?->default_unit ?? 'UNIT'),
            ];
        }

        $hasPurchaseRequest = $request->purchaseRequests
            ->contains(fn (PurchaseRequest $purchaseRequest): bool => in_array((string) $purchaseRequest->status, ['OPEN', 'PROCESSING'], true));
        $isQueuedForProcurement = (string) $request->status === 'FOR_PROCUREMENT' || $hasPurchaseRequest;
        $stockAssessment = ($shortageTotal > 0 || $isQueuedForProcurement) ? 'SHORTAGE_REQUIRES_PROCUREMENT' : 'STOCK_READY';
        $distributionRecordsCount = (int) $request->distribution_records_count;
        $hasDraftDistribution = $request->distributionRecords->contains(fn ($record): bool => (string) $record->status === 'DRAFT');
        $isSubmitted = (string) $request->status === 'SUBMITTED';
        $canCreateInventoryDistribution = $isSubmitted && ($stockAssessment === 'STOCK_READY' || $hasAvailableStock) && ! $hasPurchaseRequest && $hasRemainingQuantity && ! $hasDraftDistribution;

        $categoryId = (int) ($request->item_category_id ?: ($request->items->first()?->item?->item_category_id ?? 0));
        $categoryName = (string) (
            $request->category?->category_name
            ?? $request->items->first()?->item?->category?->category_name
            ?? 'General Supplies'
        );

        return [
            'supply_request_id' => (int) $request->supply_request_id,
            'request_code' => (string) $request->request_code,
            'status' => (string) $request->status,
            'priority_level' => (string) $request->priority_level,
            'needed_by_date' => optional($request->needed_by_date)?->toDateString(),
            'item_category_id' => $categoryId > 0 ? $categoryId : null,
            'category_name' => $categoryName,
            'office' => $request->office ? [
                'id' => (int) $request->office->id,
                'name' => (string) $request->office->name,
            ] : null,
            'requester' => $request->requester ? [
                'name' => (string) $request->requester->name,
            ] : null,
            'item_count' => $request->items->count(),
            'requested_total' => round($requestedTotal, 2),
            'shortage_total' => round($shortageTotal, 2),
            'requested_items' => $requestedItems,
            'stock_assessment' => $stockAssessment,
            'has_purchase_request' => $hasPurchaseRequest,
            'has_distribution_candidate' => isset($distributionReadyLookup[(int) $request->supply_request_id]) || $canCreateInventoryDistribution,
            'distribution_records_count' => $distributionRecordsCount,
            'workflow_bucket' => $this->workflowBucket($hasPurchaseRequest, $stockAssessment, $canCreateInventoryDistribution, (string) $request->status),
            'follow_up_context' => $followUpContext ?? [
                'is_follow_up' => false,
                'type' => 'STANDARD',
                'label' => 'Standard Requisition',
                'parent' => null,
                'children' => [],
            ],
        ];
    }

    /**
     * @return array<int, float>
     */
    private function releasedQuantityByItemId(SupplyRequest $request): array
    {
        return $request->distributionRecords
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->flatMap(fn ($record) => $record->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($items): float => (float) $items->sum('quantity_released'))
            ->all();
    }

    private function workflowBucket(bool $hasPurchaseRequest, string $stockAssessment, bool $canCreateInventoryDistribution, string $status = ''): string
    {
        if (in_array($status, ['COMPLETED', 'FULFILLED', 'RECEIVED'], true)) {
            return 'COMPLETED';
        }

        if ($hasPurchaseRequest || $status === 'FOR_PROCUREMENT') {
            return 'QUEUED_FOR_PROCUREMENT';
        }

        if ($stockAssessment === 'STOCK_READY' && $canCreateInventoryDistribution) {
            return 'READY_FOR_DISTRIBUTION';
        }

        return $stockAssessment === 'STOCK_READY'
            ? 'STOCK_READY_AWAITING_RELEASE_CONTEXT'
            : 'UNDER_VALIDATION';
    }

    private function paginateOfficeSupplyRows(Collection $rows, Request $request): LengthAwarePaginator
    {
        $perPage = 5;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    private function officeOptions(): Collection
    {
        return Office::query()
            ->orderBy('name', 'asc')
            ->get(['id', 'name'])
            ->map(fn (Office $office): array => [
                'id' => (int) $office->id,
                'name' => (string) $office->name,
            ])
            ->values();
    }

    public function officeSupplyRequestShow(Request $request, SupplyRequest $supplyRequest): Response
    {
        abort_unless($request->user()?->can('inventory.view-any'), 403);

        $supplyRequest->load([
            'office',
            'requester',
            'items.item.inventory',
            'purchaseRequests',
            'distributionRecords',
        ]);

        if ((string) ($supplyRequest->office?->code ?? '') === 'GSO') {
            abort(404);
        }

        $supplyRequest->loadMissing(['category']);

        $allDistributions = $this->allFulfillmentDistributionRecords($supplyRequest)
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED']);
        $releasedPool = $allDistributions
            ->flatMap(fn ($record) => $record->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($group): float => (float) $group->sum('quantity_released'));

        $requestedTotal = 0.0;
        $releasedTotal = 0.0;
        $shortageTotal = 0.0;
        $hasUnreleasedAvailableStock = false;

        $items = $supplyRequest->items
            ->map(function (SupplyRequestItem $item) use (&$releasedPool, &$requestedTotal, &$releasedTotal, &$shortageTotal, &$hasUnreleasedAvailableStock): array {
                $itemId = (int) $item->item_id;
                $requested = (float) $item->quantity_requested;
                $availReleased = (float) ($releasedPool->get($itemId) ?? 0.0);
                $released = min($requested, $availReleased);
                $releasedPool->put($itemId, max($availReleased - $released, 0.0));

                $remaining = max($requested - $released, 0.0);
                $inv = $item->item?->inventory;
                $available = (float) ($inv?->available_stock ?? 0) + (float) ($inv?->reserved_quantity ?? 0);
                $shortage = max($remaining - $available, 0.0);

                if ($remaining > 0.0001 && $available > 0.0001) {
                    $hasUnreleasedAvailableStock = true;
                }

                $requestedTotal += $requested;
                $releasedTotal += $released;
                $shortageTotal += $shortage;

                return [
                    'supply_request_item_id' => (int) $item->supply_request_item_id,
                    'item_id' => $itemId,
                    'item_name' => (string) ($item->item?->item_name ?? $item->item_name_snapshot ?? 'Unknown Item'),
                    'unit' => (string) ($item->item?->default_unit ?? 'UNIT'),
                    'pieces_per_box' => (int) ($item->item?->pieces_per_box ?? 1),
                    'sub_unit' => (string) ($item->item?->sub_unit ?? 'piece'),
                    'quantity_requested' => round($requested, 2),
                    'quantity_released' => round($released, 2),
                    'quantity_remaining' => round($remaining, 2),
                    'available_stock' => round($available, 2),
                    'shortage' => round($shortage, 2),
                ];
            })
            ->values();

        return Inertia::render('Inventory/OfficeSupplyRequests/Show', [
            'request' => [
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'request_code' => (string) $supplyRequest->request_code,
                'status' => (string) $supplyRequest->status,
                'priority_level' => (string) $supplyRequest->priority_level,
                'purpose' => (string) $supplyRequest->purpose,
                'needed_by_date' => optional($supplyRequest->needed_by_date)?->toDateString(),
                'request_date' => optional($supplyRequest->request_date)?->toDateString(),
                'item_category_id' => $supplyRequest->item_category_id ? (int) $supplyRequest->item_category_id : null,
                'category_name' => (string) ($supplyRequest->category?->category_name ?? $supplyRequest->request_category ?? ''),
                'office' => $supplyRequest->office ? [
                    'id' => (int) $supplyRequest->office->id,
                    'name' => (string) $supplyRequest->office->name,
                ] : null,
                'requester' => $supplyRequest->requester ? [
                    'id' => (int) $supplyRequest->requester->id,
                    'name' => (string) $supplyRequest->requester->name,
                ] : null,
                'item_count' => $items->count(),
                'requested_total' => round($requestedTotal, 2),
                'released_total' => round($releasedTotal, 2),
                'shortage_total' => round($shortageTotal, 2),
                'has_unreleased_available_stock' => $hasUnreleasedAvailableStock,
                'has_purchase_request' => $supplyRequest->purchaseRequests->isNotEmpty(),
                'purchase_request_count' => $supplyRequest->purchaseRequests->count(),
                'distribution_records_count' => $supplyRequest->distributionRecords->count(),
                'items' => $items,
                'follow_up_context' => $this->followUpService->resolveContext($supplyRequest),
            ],
            'backFilters' => [
                'q' => trim((string) $request->query('q', '')),
                'office_id' => (int) $request->query('office_id', 0) > 0 ? (int) $request->query('office_id') : null,
                'stock_assessment' => trim((string) $request->query('stock_assessment', '')),
                'workflow_bucket' => trim((string) $request->query('workflow_bucket', '')),
                'page' => (int) $request->query('page', 0) > 0 ? (int) $request->query('page') : null,
            ],
        ]);
    }

    public function procurementRequests(Request $request): Response
    {
        abort_unless($request->user()?->can('inventory.view-any'), 403);
        $actor = $request->user();
        if ($actor instanceof User) {
            $this->alertService->syncLowStockInventoryAlerts($actor);
        }

        $focusInventoryId = (int) $request->query('focus_inventory_id', 0);

        return Inertia::render('Inventory/ProcurementRequests/Index', [
            'purchaseRequests' => $this->purchaseRequestRecords(),
            'shortageCandidates' => $this->shortageCandidates(),
            'lowStockItems' => $this->lowStockItems($this->activeLowStockQueueByInventoryId()),
            'atReorderItems' => $this->atReorderItems(),
            'focusInventoryId' => $focusInventoryId > 0 ? $focusInventoryId : null,
        ]);
    }

    public function procurementRequestShow(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        abort_unless($request->user()?->can('inventory.view-any'), 403);

        $purchaseRequest->load([
            'office',
            'requester',
            'supplyRequest.requester',
            'supplyRequest.items.item.inventory',
            'items.item.category',
            'items.item.specifications',
            'purchaseOrders.supplier',
            'purchaseOrders.deliveries.distributionRecords',
        ]);

        return Inertia::render('Inventory/ProcurementRequests/Show', [
            'purchaseRequest' => [
                'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                'pr_number' => (string) $purchaseRequest->pr_number,
                'status' => (string) $purchaseRequest->status,
                'remarks' => $purchaseRequest->remarks,
                'created_at' => optional($purchaseRequest->created_at)?->toIso8601String(),
                'office' => $purchaseRequest->office ? [
                    'name' => (string) $purchaseRequest->office->name,
                ] : null,
                'requester' => $purchaseRequest->requester ? [
                    'name' => (string) $purchaseRequest->requester->name,
                ] : null,
                'supply_request' => $purchaseRequest->supplyRequest ? [
                    'supply_request_id' => (int) $purchaseRequest->supplyRequest->supply_request_id,
                    'request_code' => (string) $purchaseRequest->supplyRequest->request_code,
                    'status' => (string) $purchaseRequest->supplyRequest->status,
                    'purpose' => (string) $purchaseRequest->supplyRequest->purpose,
                    'requester' => $purchaseRequest->supplyRequest->requester ? [
                        'name' => (string) $purchaseRequest->supplyRequest->requester->name,
                    ] : null,
                ] : null,
                'items' => $purchaseRequest->items
                    ->map(fn ($item): array => [
                        'purchase_request_item_id' => (int) $item->purchase_request_item_id,
                        'item_name' => (string) ($item->item?->item_name ?? 'Unknown Item'),
                        'category_name' => (string) ($item->item?->category?->category_name ?? 'Uncategorized'),
                        'quantity_requested' => (float) $item->quantity_requested,
                        'unit' => (string) ($item->item?->default_unit ?? 'UNIT'),
                        'pieces_per_box' => (int) ($item->item?->pieces_per_box ?? 1),
                        'sub_unit' => (string) ($item->item?->sub_unit ?? 'piece'),
                        'available_stock' => (float) ($item->item?->inventory?->available_stock ?? 0),
                        'specifications' => $item->item?->specifications->pluck('specification')->all() ?? [],
                        'remarks' => $item->remarks,
                    ])
                    ->values(),
                'purchase_orders' => $purchaseRequest->purchaseOrders
                    ->sortByDesc('purchase_order_id')
                    ->map(fn ($purchaseOrder): array => [
                        'purchase_order_id' => (int) $purchaseOrder->purchase_order_id,
                        'po_number' => (string) $purchaseOrder->po_number,
                        'status' => (string) $purchaseOrder->status,
                        'supplier_name' => (string) ($purchaseOrder->supplier?->supplier_name ?? '-'),
                        'total_amount' => (float) $purchaseOrder->total_amount,
                        'expected_delivery_date' => optional($purchaseOrder->expected_delivery_date)?->toDateString(),
                        'deliveries' => $purchaseOrder->deliveries
                            ->sortByDesc('delivery_id')
                            ->map(fn ($delivery): array => [
                                'delivery_id' => (int) $delivery->delivery_id,
                                'delivery_code' => (string) $delivery->delivery_code,
                                'receipt_status' => (string) $delivery->receipt_status,
                                'actual_delivery_date' => optional($delivery->actual_delivery_date)?->toDateString(),
                                'distribution_count' => (int) $delivery->distributionRecords->count(),
                            ])
                            ->values(),
                    ])
                    ->values(),
            ],
            'backFilters' => [
                'page' => (int) $request->query('page', 0) > 0 ? (int) $request->query('page') : null,
            ],
        ]);
    }

    private function purchaseRequestRecords()
    {
        return PurchaseRequest::query()
            ->with(['office', 'requester', 'supplyRequest'])
            ->latest('purchase_request_id')
            ->paginate(5)
            ->withQueryString();
    }

    /**
     * @return array<int, int>
     */
    private function activeLowStockQueueByInventoryId(): array
    {
        return PurchaseRequest::query()
            ->where(function ($query): void {
                $query->where('status', 'OPEN')
                    ->orWhere('status', 'PROCESSING');
            })
            ->where('remarks', 'like', '%LOW_STOCK_SIGNAL:inventory:%')
            ->get(['purchase_request_id', 'remarks'])
            ->reduce(function (array $carry, PurchaseRequest $request): array {
                $remarks = (string) ($request->remarks ?? '');
                if (preg_match('/LOW_STOCK_SIGNAL:inventory:(\d+)/', $remarks, $matches) !== 1) {
                    return $carry;
                }

                $inventoryId = (int) ($matches[1] ?? 0);
                if ($inventoryId <= 0 || isset($carry[$inventoryId])) {
                    return $carry;
                }

                $carry[$inventoryId] = (int) $request->purchase_request_id;

                return $carry;
            }, []);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function shortageCandidates(): Collection
    {
        return SupplyRequest::query()
            ->submitted()
            ->whereDoesntHave('purchaseRequests')
            ->with(['office', 'items.item.inventory'])
            ->latest('supply_request_id')
            ->limit(20)
            ->get()
            ->map(fn (SupplyRequest $request): array => $this->shortageCandidateRow($request))
            ->filter(fn (array $request): bool => $request['shortage_total'] > 0)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function shortageCandidateRow(SupplyRequest $request): array
    {
        $requestedTotal = 0.0;
        $shortageTotal = 0.0;

        foreach ($request->items as $item) {
            $requested = (float) $item->quantity_requested;
            $available = (float) ($item->item?->inventory?->available_stock ?? 0);

            $requestedTotal += $requested;
            $shortageTotal += max($requested - $available, 0);
        }

        return [
            'supply_request_id' => (int) $request->supply_request_id,
            'request_code' => (string) $request->request_code,
            'office' => $request->office ? [
                'name' => (string) $request->office->name,
            ] : null,
            'needed_by_date' => optional($request->needed_by_date)?->toDateString(),
            'requested_total' => round($requestedTotal, 2),
            'shortage_total' => round($shortageTotal, 2),
        ];
    }

    /**
     * @param  array<int, int>  $activeLowStockQueueByInventoryId
     * @return Collection<int, array<string, mixed>>
     */
    private function lowStockItems(array $activeLowStockQueueByInventoryId): Collection
    {
        return Inventory::query()
            ->with(['item.specifications'])
            ->whereColumn('available_stock', '<', 'low_stock_threshold')
            ->orderByRaw('(low_stock_threshold - available_stock) DESC')
            ->limit(12)
            ->get()
            ->map(fn (Inventory $inventory): array => $this->lowStockItemRow($inventory, $activeLowStockQueueByInventoryId))
            ->filter(fn (array $row): bool => $row['deficit'] > 0)
            ->values();
    }

    /**
     * @param  array<int, int>  $activeLowStockQueueByInventoryId
     * @return array<string, mixed>
     */
    private function lowStockItemRow(Inventory $inventory, array $activeLowStockQueueByInventoryId): array
    {
        $inventoryId = (int) $inventory->inventory_id;
        $deficit = round(max((float) $inventory->low_stock_threshold - (float) $inventory->available_stock, 0), 2);
        $activePurchaseRequestId = $activeLowStockQueueByInventoryId[$inventoryId] ?? null;

        return [
            'inventory_id' => $inventoryId,
            'item_name' => (string) ($inventory->item?->item_name ?? 'Unassigned Item'),
            'specifications' => $inventory->item?->specifications?->map(fn ($spec) => [
                'item_specification_id' => (int) $spec->item_specification_id,
                'specification' => (string) $spec->specification,
            ])->values()->all() ?? [],
            'unit' => (string) ($inventory->item?->default_unit ?? 'box'),
            'pieces_per_box' => (int) ($inventory->item?->pieces_per_box ?? 1),
            'sub_unit' => (string) ($inventory->item?->sub_unit ?? 'piece'),
            'available_stock' => (float) $inventory->available_stock,
            'low_stock_threshold' => (float) $inventory->low_stock_threshold,
            'deficit' => $deficit,
            'suggested_quantity' => $deficit,
            'is_already_queued' => $activePurchaseRequestId !== null,
            'active_purchase_request_id' => $activePurchaseRequestId,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function atReorderItems(): Collection
    {
        return Inventory::query()
            ->with(['item.specifications'])
            ->whereColumn('available_stock', '=', 'low_stock_threshold')
            ->orderByDesc('last_updated')
            ->limit(8)
            ->get()
            ->map(fn (Inventory $inventory): array => [
                'inventory_id' => (int) $inventory->inventory_id,
                'item_name' => (string) ($inventory->item?->item_name ?? 'Unassigned Item'),
                'specifications' => $inventory->item?->specifications?->map(fn ($spec) => [
                    'item_specification_id' => (int) $spec->item_specification_id,
                    'specification' => (string) $spec->specification,
                ])->values()->all() ?? [],
                'unit' => (string) ($inventory->item?->default_unit ?? 'box'),
                'pieces_per_box' => (int) ($inventory->item?->pieces_per_box ?? 1),
                'sub_unit' => (string) ($inventory->item?->sub_unit ?? 'piece'),
                'available_stock' => (float) $inventory->available_stock,
                'low_stock_threshold' => (float) $inventory->low_stock_threshold,
            ])
            ->values();
    }

    public function processDistribution(Request $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.view-any'), 403);

        $supplyRequest->load(['items.item.inventory', 'purchaseRequests', 'distributionRecords.items']);

        if ((string) ($supplyRequest->office?->code ?? '') === 'GSO') {
            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->with('error', 'GSO-origin supply requests are internal logistics records and are not eligible for office distribution release.');
        }

        if ((string) $supplyRequest->status !== 'SUBMITTED') {
            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->with('error', 'Only SUBMITTED supply requests can be processed for distribution. Ask the office to submit or resubmit this request first.');
        }

        $existingDraftRecord = $supplyRequest->distributionRecords
            ->firstWhere('status', 'DRAFT');

        if ($existingDraftRecord instanceof \App\Models\DistributionRecord) {
            return redirect()
                ->route('inventory.distribution-records.show', (int) $existingDraftRecord->distribution_record_id)
                ->with('success', 'An existing draft distribution record was found. Continue releasing items in that draft.');
        }

        $releasedPool = $this->releasedQuantityByItemId($supplyRequest);
        $hasRemainingQuantity = false;

        foreach ($supplyRequest->items as $item) {
            $itemId = (int) $item->item_id;
            $requested = (float) $item->quantity_requested;
            $availReleased = (float) ($releasedPool[$itemId] ?? 0.0);
            $released = min($requested, $availReleased);
            $releasedPool[$itemId] = max($availReleased - $released, 0.0);

            if ($requested - $released > 0.0001) {
                $hasRemainingQuantity = true;
                break;
            }
        }

        if (! $hasRemainingQuantity) {
            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->with('error', 'All requested quantities have already been released for this request.');
        }

        $hasAvailableStock = false;
        $releasedPoolForStock = $this->releasedQuantityByItemId($supplyRequest);
        foreach ($supplyRequest->items as $item) {
            $itemId = (int) $item->item_id;
            $requested = (float) $item->quantity_requested;
            $availReleased = (float) ($releasedPoolForStock[$itemId] ?? 0.0);
            $released = min($requested, $availReleased);
            $releasedPoolForStock[$itemId] = max($availReleased - $released, 0.0);

            $remaining = max($requested - $released, 0);
            $available = (float) ($item->item?->inventory?->available_stock ?? 0) + (float) ($item->item?->inventory?->reserved_quantity ?? 0);
            if ($remaining > 0.0001 && $available > 0.0001) {
                $hasAvailableStock = true;
                break;
            }
        }

        if (! $hasAvailableStock) {
            if ($supplyRequest->purchaseRequests->isNotEmpty()) {
                return redirect()
                    ->route('inventory.office-supply-requests.index', $request->query())
                    ->with('error', 'This request is already queued for procurement and has no available warehouse stock to release.');
            }

            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->with('error', 'No available warehouse stock to release. Queue this request for procurement initiation instead.');
        }

        try {
            $record = $this->distributionService->createRecordWithAutomaticRelease($request->user(), [
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'office_id' => (int) $supplyRequest->office_id,
            ]);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())
                ->flatten()
                ->first() ?: 'This supply request is not eligible for automatic distribution release.';

            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->withErrors($exception->errors())
                ->with('error', $message);
        }

        return redirect()
            ->route('inventory.distribution-records.show', (int) $record->distribution_record_id)
            ->with('success', 'Distribution record created and releasable items were released automatically.');
    }

    public function queueProcurement(Request $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.view-any'), 403);

        $supplyRequest->loadMissing('office');
        if ((string) ($supplyRequest->office?->code ?? '') === 'GSO') {
            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->with('error', 'GSO-origin supply requests are internal logistics records and cannot be queued through office procurement routing.');
        }

        try {
            $this->purchaseRequestService->queueFromSupplyRequest($request->user(), [
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'remarks' => 'Queued by GSO due to stock shortage.',
            ]);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())
                ->flatten()
                ->first() ?: 'This supply request is not eligible for procurement queueing.';

            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->withErrors($exception->errors())
                ->with('error', $message);
        }

        return redirect()
            ->route('inventory.office-supply-requests.index', $request->query())
            ->with('success', 'Supply request queued for procurement processing.');
    }

    public function queueConsolidatedProcurement(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.view-any'), 403);

        $validated = $request->validate([
            'office_id' => ['required', 'integer', 'exists:offices,id'],
            'supply_request_ids' => ['required', 'array', 'min:2'],
            'supply_request_ids.*' => ['required', 'integer', 'exists:supply_requests,supply_request_id'],
        ], [
            'supply_request_ids.min' => 'Consolidation requires at least 2 pending supply requests to merge.',
        ]);

        try {
            $purchaseRequest = $this->purchaseRequestService->queueConsolidatedFromOffice(
                actor: $request->user(),
                officeId: (int) $validated['office_id'],
                supplyRequestIds: array_map('intval', $validated['supply_request_ids']),
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())
                ->flatten()
                ->first() ?: 'Consolidated purchase request could not be created.';

            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->withErrors($exception->errors())
                ->with('error', $message);
        }

        return redirect()
            ->route('inventory.office-supply-requests.index', $request->query())
            ->with('success', sprintf(
                'Consolidated purchase request PR-%06d created for %d supply request(s).',
                (int) $purchaseRequest->purchase_request_id,
                count($validated['supply_request_ids']),
            ));
    }

    public function initiateLowStockProcurement(Request $request, Inventory $inventory): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.view-any'), 403);

        $validated = $request->validate([
            'quantity_requested' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $quantityRequested = isset($validated['quantity_requested'])
            ? (float) $validated['quantity_requested']
            : max((float) $inventory->low_stock_threshold - (float) $inventory->available_stock, 0);

        $queued = $this->purchaseRequestService->createFromLowStockSignal(
            actor: $request->user(),
            inventory: $inventory,
            quantityRequested: $quantityRequested
        );

        return redirect()
            ->route('inventory.procurement-requests.index', [
                'focus_inventory_id' => (int) $inventory->inventory_id,
            ])
            ->with('success', sprintf(
                'Proactive GSO purchase request PR-%06d created from statistical demand signal for %s (Qty %.2f).',
                (int) $queued->purchase_request_id,
                (string) ($inventory->item?->item_name ?? 'selected item'),
                $quantityRequested
            ));
    }

    /**
     * @return Collection<int, \App\Models\DistributionRecord>
     */
    private function allFulfillmentDistributionRecords(SupplyRequest $request): Collection
    {
        $request->loadMissing('distributionRecords.items');
        $directRecords = $request->distributionRecords;

        $childIds = $this->followUpService->resolveAllFollowUpChildSupplyRequestIds($request);
        if (empty($childIds)) {
            return $directRecords;
        }

        $childRecords = \App\Models\DistributionRecord::query()
            ->with('items')
            ->whereIn('supply_request_id', $childIds)
            ->get();

        return $directRecords->concat($childRecords);
    }

    private function computeItemShortage(SupplyRequest $supplyRequest, int $itemId): float
    {
        $requestedForItem = (float) $supplyRequest->items
            ->where('item_id', $itemId)
            ->sum('quantity_requested');

        if ($requestedForItem <= 0) {
            return 0.0;
        }

        $available = (float) ($supplyRequest->items
            ->firstWhere('item_id', $itemId)?->item?->inventory?->available_stock ?? 0);

        return max($requestedForItem - $available, 0);
    }
}
