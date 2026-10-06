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
        private readonly \App\Services\Inventory\InventoryService $inventoryService,
        private readonly \App\Services\Procurement\ProcurementStatusSyncService $procurementStatusSyncService,
        private readonly \App\Services\AuditLogService $auditLogService,
        private readonly \App\Services\NotificationService $notificationService,
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

        $isCompleted = in_array((string) $request->status, ['COMPLETED', 'FULFILLED', 'RECEIVED'], true);

        foreach ($request->items as $item) {
            $itemId = (int) $item->item_id;
            $requested = (float) $item->quantity_requested;
            $availReleased = (float) ($releasedPool[$itemId] ?? 0.0);
            $released = min($requested, $availReleased);
            $releasedPool[$itemId] = max($availReleased - $released, 0.0);

            $remaining = max($requested - $released, 0.0);
            $available = 0.0;
            if (! $isCompleted && $remaining > 0.0001) {
                $available = $this->inventoryService->getAvailableStockForSupplyRequest($itemId, $request);
            }

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
        $isDiscrepancyChild = $request->purchaseRequests->contains(
            fn ($pr) => in_array((string) $pr->source_type, ['PARTIAL_DELIVERY_FOLLOW_UP', 'CLAIM_REPLACEMENT_FOLLOW_UP'], true)
                || (int) ($pr->source_delivery_id ?? 0) > 0
                || (int) ($pr->source_claim_id ?? 0) > 0
        );
        $isSubmitted = (string) $request->status === 'SUBMITTED';
        $canCreateInventoryDistribution = $isSubmitted && ! $isDiscrepancyChild && ($stockAssessment === 'STOCK_READY' || $hasAvailableStock) && ! $hasPurchaseRequest && $hasRemainingQuantity && ! $hasDraftDistribution;

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
            ->where('code', '!=', 'GSO')
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
        $isProcurementBacked = $supplyRequest->purchaseRequests()->exists() || (string) $supplyRequest->status === 'FOR_PROCUREMENT';
        $hasUnreleasedAvailableStock = false;

        $items = $supplyRequest->items
            ->map(function (SupplyRequestItem $item) use ($supplyRequest, $isProcurementBacked, &$releasedPool, &$requestedTotal, &$releasedTotal, &$shortageTotal, &$hasUnreleasedAvailableStock): array {
                $itemId = (int) $item->item_id;
                $requested = (float) $item->quantity_requested;
                $availReleased = (float) ($releasedPool->get($itemId) ?? 0.0);
                $released = min($requested, $availReleased);
                $releasedPool->put($itemId, max($availReleased - $released, 0.0));

                $remaining = max($requested - $released, 0.0);
                $available = $this->inventoryService->getAvailableStockForSupplyRequest($itemId, $supplyRequest);
                $shortage = max($remaining - $available, 0.0);

                if (! $isProcurementBacked && $remaining > 0.0001 && $available > 0.0001) {
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
     * @return array<int, array{purchase_request_id: int, pr_number: string}>
     */
    private function activeLowStockQueueByInventoryId(): array
    {
        $this->procurementStatusSyncService->syncCompletedProactiveAndRestockRequests();

        return PurchaseRequest::query()
            ->where(function ($query): void {
                $query->where('status', 'OPEN')
                    ->orWhere('status', 'PROCESSING');
            })
            ->where('remarks', 'like', '%LOW_STOCK_SIGNAL:inventory:%')
            ->get(['purchase_request_id', 'pr_number', 'remarks', 'created_at'])
            ->reduce(function (array $carry, PurchaseRequest $request): array {
                $remarks = (string) ($request->remarks ?? '');
                if (preg_match('/LOW_STOCK_SIGNAL:inventory:(\d+)/', $remarks, $matches) !== 1) {
                    return $carry;
                }

                $inventoryId = (int) ($matches[1] ?? 0);
                if ($inventoryId <= 0 || isset($carry[$inventoryId])) {
                    return $carry;
                }

                $carry[$inventoryId] = [
                    'purchase_request_id' => (int) $request->purchase_request_id,
                    'pr_number' => (string) $request->pr_number,
                ];

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
            $available = $this->inventoryService->getAvailableStockForSupplyRequest((int) $item->item_id, $request);

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
     * @param  array<int, mixed>  $activeLowStockQueueByInventoryId
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
     * @param  array<int, mixed>  $activeLowStockQueueByInventoryId
     * @return array<string, mixed>
     */
    private function lowStockItemRow(Inventory $inventory, array $activeLowStockQueueByInventoryId): array
    {
        $inventoryId = (int) $inventory->inventory_id;
        $deficit = round(max((float) $inventory->low_stock_threshold - (float) $inventory->available_stock, 0), 2);
        $queuedPr = $activeLowStockQueueByInventoryId[$inventoryId] ?? null;
        $activePurchaseRequestId = is_array($queuedPr)
            ? (int) ($queuedPr['purchase_request_id'] ?? 0)
            : (is_numeric($queuedPr) ? (int) $queuedPr : null);
        $activePrNumber = is_array($queuedPr)
            ? (string) ($queuedPr['pr_number'] ?? '')
            : ($activePurchaseRequestId ? sprintf('PR-%s', str_pad((string) $activePurchaseRequestId, 6, '0', STR_PAD_LEFT)) : null);

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
            'is_already_queued' => ! empty($activePurchaseRequestId),
            'active_purchase_request_id' => $activePurchaseRequestId ?: null,
            'active_pr_number' => $activePrNumber ?: null,
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

        if ((string) $supplyRequest->status === 'REJECTED') {
            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->with('error', 'Disapproved or denied supply requests cannot proceed to warehouse distribution.');
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

        $isFollowUp = $this->followUpService->isFollowUpSupplyRequest($supplyRequest);
        $deliveryIds = $this->followUpService->resolveDeliveryIdsForSupplyRequest($supplyRequest);
        $targetSrIds = $isFollowUp
            ? array_values(array_unique(array_filter(array_map('intval', array_merge(
                [(int) $supplyRequest->supply_request_id],
                $this->followUpService->resolveAllFollowUpChildSupplyRequestIds($supplyRequest)
            )))))
            : $this->followUpService->resolveLineageSupplyRequestIds($supplyRequest);

        $verifiedDelivery = Delivery::query()
            ->whereIn('receipt_status', ['VERIFIED', 'PARTIALLY_RECEIVED'])
            ->where(function ($query) use ($targetSrIds, $deliveryIds): void {
                $query->whereIn('delivery_id', $deliveryIds)
                    ->orWhereHas('purchaseOrder', function ($poQ) use ($targetSrIds): void {
                        $poQ->whereIn('supply_request_id', $targetSrIds)
                            ->orWhereHas('purchaseRequest', fn ($prQ) => $prQ->whereIn('supply_request_id', $targetSrIds));
                    });
            })
            ->whereDoesntHave('distributionRecords', fn ($q) => $q->where('status', 'DRAFT'))
            ->with(['items.purchaseOrderItem', 'distributionRecords.items'])
            ->latest('delivery_id')
            ->get()
            ->first(function (Delivery $delivery) use ($supplyRequest): bool {
                $deliveryAcceptedByItemId = $delivery->items
                    ->groupBy(fn ($item) => (int) ($item->purchaseOrderItem?->item_id ?? 0))
                    ->map(fn ($items): float => (float) $items->sum(
                        fn ($item): float => max((float) $item->received_quantity - (float) $item->damaged_quantity, 0)
                    ));

                $deliveryReleasedByItemId = $delivery->distributionRecords
                    ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                    ->flatMap(fn ($record) => $record->items)
                    ->groupBy(fn ($item) => (int) $item->item_id)
                    ->map(function ($items, int $itemId) use ($deliveryAcceptedByItemId): float {
                        $rawReleased = (float) $items->sum('quantity_released');
                        $bundled = (float) $items->sum('bundled_warehouse_quantity');
                        $deliveryPortion = max($rawReleased - $bundled, 0.0);
                        $accepted = (float) ($deliveryAcceptedByItemId->get($itemId) ?? 0.0);

                        return min($deliveryPortion, $accepted);
                    });

                foreach ($supplyRequest->items as $item) {
                    $itemId = (int) $item->item_id;
                    $accepted = (float) ($deliveryAcceptedByItemId->get($itemId) ?? 0.0);
                    $released = (float) ($deliveryReleasedByItemId->get($itemId) ?? 0.0);
                    if ($accepted - $released > 0.0001) {
                        return true;
                    }
                }

                return false;
            });

        $prs = app(\App\Services\Procurement\ConsolidationTraceabilityService::class)->getPurchaseRequestsForSupplyRequest($supplyRequest);
        $allPrs = $prs->merge($supplyRequest->purchaseRequests)->unique('purchase_request_id');
        $isDiscrepancyChild = $allPrs->contains(
            fn ($pr) => in_array((string) $pr->source_type, ['PARTIAL_DELIVERY_FOLLOW_UP', 'CLAIM_REPLACEMENT_FOLLOW_UP'], true)
                || (int) ($pr->source_delivery_id ?? 0) > 0
                || (int) ($pr->source_claim_id ?? 0) > 0
        );

        if ($isDiscrepancyChild && $verifiedDelivery === null) {
            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->with('error', 'This follow-up request is queued for procurement and awaits verified delivery before distribution release.');
        }

        $hasAvailableStock = $verifiedDelivery !== null;
        if (! $hasAvailableStock) {
            $releasedPoolForStock = $this->releasedQuantityByItemId($supplyRequest);
            foreach ($supplyRequest->items as $item) {
                $itemId = (int) $item->item_id;
                $requested = (float) $item->quantity_requested;
                $availReleased = (float) ($releasedPoolForStock[$itemId] ?? 0.0);
                $released = min($requested, $availReleased);
                $releasedPoolForStock[$itemId] = max($availReleased - $released, 0.0);

                $remaining = max($requested - $released, 0);
                $available = $this->inventoryService->getAvailableStockForSupplyRequest($itemId, $supplyRequest);
                if ($remaining > 0.0001 && $available > 0.0001) {
                    $hasAvailableStock = true;
                    break;
                }
            }
        }

        if (! $hasAvailableStock) {
            $isProcurementBacked = $supplyRequest->purchaseRequests->isNotEmpty()
                || ! empty($deliveryIds)
                || app(\App\Services\Procurement\ConsolidationTraceabilityService::class)->getPurchaseRequestsForSupplyRequest($supplyRequest)->isNotEmpty();

            if ($isProcurementBacked) {
                return redirect()
                    ->route('inventory.office-supply-requests.index', $request->query())
                    ->with('error', 'This request is procurement-backed and awaits verified delivery before distribution release.');
            }

            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->with('error', 'No available warehouse stock to release. Queue this request for procurement initiation instead.');
        }

        try {
            $payload = [
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'office_id' => (int) $supplyRequest->office_id,
            ];
            if ($verifiedDelivery instanceof Delivery) {
                $payload['delivery_id'] = (int) $verifiedDelivery->delivery_id;
            }

            $record = $this->distributionService->createRecordWithAutomaticRelease($request->user(), $payload);
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

    public function rejectOfficeSupplyRequest(Request $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.view-any'), 403);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'remarks.required' => 'Remarks explaining the disapproval reason are required.',
            'remarks.min' => 'Remarks must be at least 3 characters.',
        ]);

        $finalRemarks = trim((string) $validated['remarks']);

        if (in_array((string) $supplyRequest->status, ['REJECTED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This supply request has already been disapproved or cancelled.',
            ]);
        }

        if (in_array((string) $supplyRequest->status, ['RELEASED', 'COMPLETED', 'RECEIVED'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Completed or released supply requests cannot be disapproved.',
            ]);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $supplyRequest, $finalRemarks): void {
            $oldStatus = (string) $supplyRequest->status;

            $supplyRequest->forceFill([
                'status' => 'REJECTED',
                'review_notes' => $finalRemarks,
                'approved_by' => null,
                'approved_at' => null,
            ])->save();

            \App\Models\StatusHistory::query()->create([
                'reference_type' => SupplyRequest::class,
                'reference_id' => (int) $supplyRequest->supply_request_id,
                'old_status' => $oldStatus,
                'new_status' => 'REJECTED',
                'changed_by' => (int) $request->user()->id,
                'remarks' => $finalRemarks,
                'changed_at' => now(),
            ]);

            \App\Models\ApprovalHistory::query()->create([
                'reference_type' => SupplyRequest::class,
                'reference_id' => (int) $supplyRequest->supply_request_id,
                'approved_by' => (int) $request->user()->id,
                'status' => 'REJECTED',
                'remarks' => $finalRemarks,
                'approved_at' => now(),
            ]);

            $this->auditLogService->log(
                actor: $request->user(),
                moduleName: 'Inventory.OfficeSupplyRequest',
                actionType: 'DISAPPROVE',
                referenceType: SupplyRequest::class,
                referenceId: (int) $supplyRequest->supply_request_id,
                details: [
                    'request_code' => $supplyRequest->request_code,
                    'old_status' => $oldStatus,
                    'new_status' => 'REJECTED',
                    'remarks' => $finalRemarks,
                ],
                logDomain: 'BUSINESS',
                officeId: (int) $supplyRequest->office_id,
            );

            $this->notificationService->notifySupplyRequestOfficeRequester(
                supplyRequest: $supplyRequest,
                title: 'Supply Request Disapproved',
                message: sprintf('GSO disapproved your supply request %s: %s', (string) $supplyRequest->request_code, $finalRemarks),
                notificationType: 'OFFICE_SUPPLY_REQUEST_REJECTED',
            );
        });

        return redirect()
            ->route('inventory.office-supply-requests.index', $request->query())
            ->with('success', 'Supply request disapproved successfully.');
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

        if (in_array((string) $supplyRequest->status, ['REJECTED', 'CANCELLED'], true)) {
            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->with('error', 'Disapproved or denied supply requests cannot be queued for procurement.');
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
                'Consolidated purchase request %s created for %d supply request(s).',
                (string) $purchaseRequest->pr_number,
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
                'Proactive GSO purchase request %s created from statistical demand signal for %s (Qty %.2f).',
                (string) $queued->pr_number,
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
