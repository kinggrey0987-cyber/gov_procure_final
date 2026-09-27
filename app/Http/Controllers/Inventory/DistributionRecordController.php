<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\DistributionRecord\StoreDistributionRecordRequest;
use App\Http\Requests\DistributionRecord\UpdateDistributionRecordRequest;
use App\Models\DistributionRecord;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\SupplyRequest;
use Illuminate\Support\Collection;
use App\Services\Inventory\DistributionRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class DistributionRecordController
{
    public function __construct(
        private readonly DistributionRecordService $distributionService,
        private readonly \App\Services\Inventory\SupplyRequestFollowUpService $followUpService,
    )
    {
    }

    public function index(Request $request): Response
    {
        abort_unless(request()->user()->can('distribution.view-any'), 403);

        $status = strtoupper(trim((string) $request->input('status', '')));
        $year = trim((string) $request->input('year', ''));
        $month = trim((string) $request->input('month', ''));
        $search = trim((string) $request->input('search', ''));

        $yearOptions = DistributionRecord::query()
            ->where('created_at', '!=', null)
            ->latest('created_at')
            ->pluck('created_at')
            ->map(fn ($value): int => (int) $value->year)
            ->unique()
            ->values();

        $records = DistributionRecord::query()
            ->with(['supplyRequest', 'delivery', 'office', 'preparer', 'receiver'])
            ->withCount(['items', 'claims'])
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when(preg_match('/^\d{4}$/', $year) === 1, fn ($query) => $query->whereYear('created_at', (int) $year))
            ->when(preg_match('/^\d{1,2}$/', $month) === 1, fn ($query) => $query->whereMonth('created_at', (int) $month))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->whereLikeInsensitive('distribution_code', $search)
                        ->orWhereLikeInsensitive('status', $search)
                        ->orWhereHas('supplyRequest', fn ($supplyRequestQuery) => $supplyRequestQuery->whereLikeInsensitive('request_code', $search))
                        ->orWhereHas('office', fn ($officeQuery) => $officeQuery->whereLikeInsensitive('name', $search));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(5)
            ->withQueryString();

        return Inertia::render('Inventory/Distribution/Index', [
            'records' => $records,
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

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('distribution.manage'), 403);

        $focusSupplyRequestId = (int) $request->query('supply_request_id', 0);

        $procurementBackedRows = $this->procurementBackedReleaseRows();
        $inventoryBackedRows = $this->inventoryBackedReleaseRows();

        /** @var Collection<int, array<string, mixed>> $eligibleRows */
        $eligibleRows = $inventoryBackedRows
            ->concat($procurementBackedRows)
            ->unique('supply_request_id')
            ->sortByDesc(fn (array $row): int => (int) $row['sort_timestamp'])
            ->values();

        $focusedReason = null;
        if ($focusSupplyRequestId > 0) {
            $focusedRequest = SupplyRequest::query()
                ->with(['items.item.inventory', 'purchaseRequests', 'distributionRecords.items'])
                ->where('supply_request_id', $focusSupplyRequestId)
                ->first();

            $focusedReason = $this->resolveFocusReason($focusedRequest);
        }

        if ($focusSupplyRequestId > 0) {
            $eligibleRows = $eligibleRows
                ->sortByDesc(fn (array $row): int => (int) $row['supply_request_id'] === $focusSupplyRequestId ? 1 : 0)
                ->values();
        }

        $paginatedRows = $this->paginateEligibleRows($eligibleRows, $request);

        return Inertia::render('Inventory/Distribution/Create', [
            'eligibleRows' => $paginatedRows,
            'focusSupplyRequestId' => $focusSupplyRequestId > 0 ? $focusSupplyRequestId : null,
            'focusReleasableReason' => $focusedReason,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function procurementBackedReleaseRows(): Collection
    {
        return Delivery::query()
            ->verified()
            ->whereHas('purchaseOrder', function ($query): void {
                $query->where('supply_request_id', '!=', null)
                    ->whereHas('supplyRequest.office', fn ($officeQuery) => $officeQuery->where('code', '!=', 'GSO'));
            })
            ->whereDoesntHave('distributionRecords')
            ->with(['purchaseOrder.supplyRequest.office', 'items.purchaseOrderItem', 'distributionRecords.items'])
            ->latest('delivery_id')
            ->get()
            ->map(fn (Delivery $delivery): ?array => $this->releaseRowFromVerifiedDelivery($delivery))
            ->filter()
            ->values();
    }

    private function releaseRowFromVerifiedDelivery(Delivery $delivery): ?array
    {
        $supplyRequest = $delivery->purchaseOrder?->supplyRequest;

        if (! $supplyRequest instanceof SupplyRequest) {
            return null;
        }

        $acceptedTotal = (float) $delivery->items->sum(function ($item): float {
            return max((float) $item->received_quantity - (float) $item->damaged_quantity - (float) $item->missing_quantity, 0);
        });

        $releasedTotal = (float) $delivery->distributionRecords
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
            ->flatMap(fn ($record) => $record->items)
            ->sum('quantity_released');

        if ($acceptedTotal - $releasedTotal <= 0.0001) {
            return null;
        }

        return [
            'supply_request_id' => (int) $supplyRequest->supply_request_id,
            'request_code' => (string) $supplyRequest->request_code,
            'office_id' => (int) $supplyRequest->office_id,
            'office_name' => (string) ($supplyRequest->office?->name ?? 'Unknown Office'),
            'release_basis' => 'VERIFIED_DELIVERY',
            'release_basis_label' => 'From Verified Delivery',
            'delivery_id' => (int) $delivery->delivery_id,
            'delivery_code' => (string) $delivery->delivery_code,
            'receipt_status' => (string) $delivery->receipt_status,
            'releasable_reason' => null,
            'sort_timestamp' => (int) ($delivery->created_at?->getTimestamp() ?? $delivery->delivery_id),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function inventoryBackedReleaseRows(): Collection
    {
        return SupplyRequest::query()
            ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PARTIALLY_RECEIVED'])
            ->whereHas('office', fn ($officeQuery) => $officeQuery->where('code', '!=', 'GSO'))
            ->whereDoesntHave('distributionRecords', fn ($query) => $query->where('status', 'DRAFT'))
            ->with(['office', 'items.item.inventory', 'distributionRecords.items'])
            ->latest('supply_request_id')
            ->get()
            ->map(fn (SupplyRequest $request): ?array => $this->releaseRowFromAvailableInventory($request))
            ->filter()
            ->values();
    }

    private function releaseRowFromAvailableInventory(SupplyRequest $request): ?array
    {
        if (! $this->hasRemainingQuantity($request)) {
            return null;
        }

        $hasReleasableStock = false;
        $releasedPool = $this->releasedQuantityByItemId($request);
        $isProcurementBacked = $request->purchaseRequests()->exists();
        $request->loadMissing('purchaseOrders.items');

        foreach ($request->items as $item) {
            $itemId = (int) $item->item_id;
            $requested = (float) $item->quantity_requested;
            $availReleased = (float) ($releasedPool[$itemId] ?? 0.0);
            $released = min($requested, $availReleased);
            $releasedPool[$itemId] = max($availReleased - $released, 0.0);

            $remaining = max($requested - $released, 0);
            $inv = $item->item?->inventory;
            $available = (float) ($inv?->available_stock ?? 0) + (float) ($inv?->reserved_quantity ?? 0);

            $releasableStock = $available;
            if ($isProcurementBacked) {
                $totalOrdered = (float) $request->purchaseOrders
                    ->flatMap(fn ($po) => $po->items)
                    ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                    ->sum('quantity_ordered');
                $inventoryPortion = max($requested - $totalOrdered, 0.0);
                $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($request, $itemId);
                $remainingInventoryPortion = max($inventoryPortion - $alreadyReleasedInventory, 0.0);
                $releasableStock = min($remainingInventoryPortion, $available);
            }

            if ($remaining > 0.0001 && $releasableStock > 0.0001) {
                $hasReleasableStock = true;
                break;
            }
        }

        if (! $hasReleasableStock) {
            return null;
        }

        return [
            'supply_request_id' => (int) $request->supply_request_id,
            'request_code' => (string) $request->request_code,
            'office_id' => (int) $request->office_id,
            'office_name' => (string) ($request->office?->name ?? 'Unknown Office'),
            'release_basis' => 'AVAILABLE_INVENTORY',
            'release_basis_label' => 'From Available Inventory',
            'delivery_id' => null,
            'delivery_code' => null,
            'receipt_status' => null,
            'releasable_reason' => null,
            'sort_timestamp' => (int) ($request->created_at?->getTimestamp() ?? $request->supply_request_id),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateEligibleRows(Collection $rows, Request $request): LengthAwarePaginator
    {
        $perPage = 5;
        $page = max((int) $request->query('page', 1), 1);

        return new LengthAwarePaginator(
            items: $rows->forPage($page, $perPage)->values(),
            total: $rows->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    private function supplyRequestShortageTotal(SupplyRequest $request): float
    {
        $shortageTotal = 0.0;
        $releasedPool = $this->releasedQuantityByItemId($request);

        foreach ($request->items as $item) {
            $itemId = (int) $item->item_id;
            $requested = (float) $item->quantity_requested;
            $availReleased = (float) ($releasedPool[$itemId] ?? 0.0);
            $released = min($requested, $availReleased);
            $releasedPool[$itemId] = max($availReleased - $released, 0.0);

            $remaining = max($requested - $released, 0);
            $inv = $item->item?->inventory;
            $available = (float) ($inv?->available_stock ?? 0) + (float) ($inv?->reserved_quantity ?? 0);
            $shortageTotal += max($remaining - $available, 0);
        }

        return $shortageTotal;
    }

    private function hasRemainingQuantity(SupplyRequest $request): bool
    {
        $releasedPool = $this->releasedQuantityByItemId($request);

        foreach ($request->items as $item) {
            $itemId = (int) $item->item_id;
            $requested = (float) $item->quantity_requested;
            $availReleased = (float) ($releasedPool[$itemId] ?? 0.0);
            $released = min($requested, $availReleased);
            $releasedPool[$itemId] = max($availReleased - $released, 0.0);

            if ($requested - $released > 0.0001) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, float>
     */
    private function releasedQuantityByItemId(SupplyRequest $request): array
    {
        return $this->allFulfillmentDistributionRecords($request)
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->flatMap(fn ($record) => $record->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($items): float => (float) $items->sum('quantity_released'))
            ->all();
    }

    /**
     * @return Collection<int, DistributionRecord>
     */
    private function allFulfillmentDistributionRecords(SupplyRequest $request): Collection
    {
        $request->loadMissing('distributionRecords.items', 'distributionRecords.delivery.items.purchaseOrderItem');
        $directRecords = $request->distributionRecords;
        $directRecords->loadMissing('items', 'delivery.items.purchaseOrderItem');

        $childIds = $this->followUpService->resolveAllFollowUpChildSupplyRequestIds($request);
        if (empty($childIds)) {
            return $directRecords;
        }

        $childRecords = DistributionRecord::query()
            ->with(['items', 'delivery.items.purchaseOrderItem'])
            ->whereIn('supply_request_id', $childIds)
            ->get();

        return $directRecords->concat($childRecords)->values();
    }

    private function undistributedDeliveryStock(int $itemId, ?int $excludeDeliveryId = null): float
    {
        return (float) DeliveryItem::query()
            ->whereHas('purchaseOrderItem', fn ($q) => $q->where('item_id', $itemId))
            ->whereHas('delivery', fn ($q) => $q->whereIn('receipt_status', ['VERIFIED', 'PARTIALLY_RECEIVED']))
            ->when($excludeDeliveryId !== null, fn ($q) => $q->where('delivery_id', '!=', $excludeDeliveryId))
            ->with(['delivery.distributionRecords.items'])
            ->get()
            ->sum(function (DeliveryItem $di) use ($itemId): float {
                $accepted = max((float) $di->received_quantity - (float) $di->damaged_quantity - (float) $di->missing_quantity, 0.0);
                $released = (float) ($di->delivery?->distributionRecords ?? collect())
                    ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                    ->flatMap(fn ($dr) => $dr->items)
                    ->where('item_id', $itemId)
                    ->sum('quantity_released');

                return max($accepted - $released, 0.0);
            });
    }

    private function calculateAlreadyReleasedInventory(SupplyRequest $supplyRequest, int $itemId): float
    {
        $allDistributions = $this->allFulfillmentDistributionRecords($supplyRequest)
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED']);

        return (float) $allDistributions->sum(function (DistributionRecord $dr) use ($itemId): float {
            $releasedInDr = (float) $dr->items
                ->filter(fn ($item): bool => (int) $item->item_id === $itemId)
                ->sum('quantity_released');

            if ($releasedInDr <= 0.0001) {
                return 0.0;
            }

            if ($dr->delivery_id === null || ! $dr->delivery) {
                return $releasedInDr;
            }

            $acceptedInDelivery = (float) $dr->delivery->items
                ->filter(fn ($di) => (int) ($di->purchaseOrderItem?->item_id ?? 0) === $itemId)
                ->sum(fn ($di) => max((float) $di->received_quantity - (float) $di->damaged_quantity - (float) $di->missing_quantity, 0.0));

            return max($releasedInDr - $acceptedInDelivery, 0.0);
        });
    }

    private function resolveFocusReason(?SupplyRequest $request): ?string
    {
        if (! $request instanceof SupplyRequest) {
            return 'Supply request context was not found.';
        }

        if ((string) ($request->office?->code ?? '') === 'GSO') {
            return 'GSO-origin supply requests are internal logistics records and are not eligible for office distribution release.';
        }

        $existingDraftRecord = $request->distributionRecords->firstWhere('status', 'DRAFT');
        if ($existingDraftRecord instanceof DistributionRecord) {
            return 'This request already has a draft distribution record. Continue releasing items in that draft.';
        }

        if (! $this->hasRemainingQuantity($request)) {
            return 'All requested quantities have already been released for this request.';
        }

        $hasPurchaseRequest = $request->purchaseRequests->isNotEmpty();

        $shortageTotal = 0.0;
        $hasAvailableStock = false;
        $releasedPool = $this->releasedQuantityByItemId($request);
        foreach ($request->items as $item) {
            $itemId = (int) $item->item_id;
            $requested = (float) $item->quantity_requested;
            $availReleased = (float) ($releasedPool[$itemId] ?? 0.0);
            $released = min($requested, $availReleased);
            $releasedPool[$itemId] = max($availReleased - $released, 0.0);

            $remaining = max($requested - $released, 0);
            $inv = $item->item?->inventory;
            $undistributed = $this->undistributedDeliveryStock($itemId);
            $available = max((float) ($inv?->available_stock ?? 0) + (float) ($inv?->reserved_quantity ?? 0) - $undistributed, 0.0);
            $shortageTotal += max($remaining - $available, 0);

            $releasableStock = $available;
            if ($hasPurchaseRequest) {
                $request->loadMissing('purchaseOrders.items');
                $totalOrdered = (float) $request->purchaseOrders
                    ->flatMap(fn ($po) => $po->items)
                    ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                    ->sum('quantity_ordered');
                $inventoryPortion = max($requested - $totalOrdered, 0.0);
                $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($request, $itemId);
                $remainingInventoryPortion = max($inventoryPortion - $alreadyReleasedInventory, 0.0);
                $releasableStock = min($remainingInventoryPortion, $available);
            }

            if ($remaining > 0.0001 && $releasableStock > 0.0001) {
                $hasAvailableStock = true;
            }
        }

        if ($hasAvailableStock) {
            return null;
        }

        if ($hasPurchaseRequest && $shortageTotal > 0) {
            $hasVerifiedDelivery = Delivery::query()
                ->verified()
                ->whereHas('purchaseOrder', function ($query) use ($request): void {
                    $query->where('supply_request_id', (int) $request->supply_request_id);
                })
                ->whereDoesntHave('distributionRecords')
                ->with(['items'])
                ->get()
                ->contains(function (Delivery $del): bool {
                    $accepted = (float) $del->items->sum(fn ($i) => max((float) $i->received_quantity - (float) $i->damaged_quantity - (float) $i->missing_quantity, 0));
                    return $accepted > 0.0001;
                });

            if (! $hasVerifiedDelivery) {
                return 'This request is procurement-backed and awaits verified delivery before distribution release.';
            }

            return null;
        }

        if ($shortageTotal > 0) {
            return 'This request has stock shortages and must be queued for procurement instead of direct inventory release.';
        }

        return null;
    }

    public function store(StoreDistributionRecordRequest $request): RedirectResponse
    {
        abort_unless(request()->user()->can('distribution.manage'), 403);

        $record = $this->distributionService->createRecordWithAutomaticRelease(request()->user(), $request->validated());

        return Redirect::route('inventory.distribution-records.show', $record->distribution_record_id)
            ->with('success', 'Distribution record created and releasable items were released automatically.');
    }

    public function show(DistributionRecord $distribution_record): Response
    {
        abort_unless(request()->user()->can('distribution.view', $distribution_record), 403);

        $distribution_record->load([
            'items.item.category',
            'claims.claimant',
            'supplyRequest.office',
            'supplyRequest.items.item.category',
            'supplyRequest.items.item.inventory',
            'supplyRequest.distributionRecords.items',
            'delivery',
            'delivery.items.purchaseOrderItem',
            'delivery.distributionRecords.items',
            'office',
            'preparer',
            'receiver',
        ]);

        $currentRecordReleasedByItemId = $distribution_record->items
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($group) => (float) $group->sum('quantity_released'));

        $requestReleasedByItemId = $distribution_record->supplyRequest
            ? $this->allFulfillmentDistributionRecords($distribution_record->supplyRequest)
                ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                ->flatMap(fn ($record) => $record->items)
                ->groupBy(fn ($item) => (int) $item->item_id)
                ->map(fn ($group) => (float) $group->sum('quantity_released'))
            : collect();

        $deliveryRemainingByItemId = null;
        if ($distribution_record->delivery_id !== null && $distribution_record->delivery !== null) {
            $deliveryAcceptedByItemId = $distribution_record->delivery->items
                ->groupBy(fn ($item) => (int) ($item->purchaseOrderItem?->item_id ?? 0))
                ->map(fn ($group): float => (float) $group->sum(
                    fn ($item): float => max((float) $item->received_quantity - (float) $item->damaged_quantity - (float) $item->missing_quantity, 0)
                ));

            $deliveryReleasedByItemId = $distribution_record->delivery->distributionRecords
                ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                ->flatMap(fn ($record) => $record->items)
                ->groupBy(fn ($item) => (int) $item->item_id)
                ->map(fn ($group) => (float) $group->sum('quantity_released'));

            $deliveryRemainingByItemId = $deliveryAcceptedByItemId
                ->map(fn (float $accepted, int $itemId): float => max($accepted - (float) ($deliveryReleasedByItemId->get($itemId) ?? 0.0), 0));
        }

        $unreleasedStockItems = [];
        $targetSr = $distribution_record->supplyRequest;
        $parentSr = $targetSr ? $this->followUpService->resolveParentSupplyRequest($targetSr) : null;

        if ($targetSr) {
            $isProcurementBacked = $targetSr->purchaseRequests()->exists();
            $targetSr->loadMissing('purchaseOrders.items', 'distributionRecords.items');

            foreach ($targetSr->items as $item) {
                $itemId = (int) $item->item_id;
                $requestedQuantity = (float) $item->quantity_requested;
                $releasedQuantity = (float) ($requestReleasedByItemId->get($itemId) ?? 0.0);
                $requestRemaining = max($requestedQuantity - $releasedQuantity, 0);
                $inv = $item->item?->inventory;
                $undistributed = $this->undistributedDeliveryStock($itemId, $distribution_record->delivery_id ? (int) $distribution_record->delivery_id : null);
                $availableStock = max((float) ($inv?->available_stock ?? 0) + (float) ($inv?->reserved_quantity ?? 0) - $undistributed, 0.0);

                $releasableStock = $availableStock;
                if ($isProcurementBacked) {
                    $totalOrdered = (float) $targetSr->purchaseOrders
                        ->flatMap(fn ($po) => $po->items)
                        ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                        ->sum('quantity_ordered');
                    $inventoryPortion = max($requestedQuantity - $totalOrdered, 0.0);
                    $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($targetSr, $itemId);
                    $remainingInventoryPortion = max($inventoryPortion - $alreadyReleasedInventory, 0.0);
                    $releasableStock = min($remainingInventoryPortion, $availableStock);
                }

                if ($requestRemaining > 0.0001 && $releasableStock > 0.0001) {
                    $unreleasedStockItems[] = [
                        'supply_request_id' => (int) $targetSr->supply_request_id,
                        'supply_request_item_id' => (int) $item->supply_request_item_id,
                        'item_id' => $itemId,
                        'item_name' => (string) ($item->item?->item_name ?? $item->item_name_snapshot),
                        'category_name' => (string) ($item->item?->category?->category_name ?? '-'),
                        'unit' => (string) ($item->item?->default_unit ?? 'UNIT'),
                        'quantity_requested' => $requestedQuantity,
                        'quantity_remaining' => $requestRemaining,
                        'available_stock' => $availableStock,
                        'releasable_quantity' => min($requestRemaining, $releasableStock),
                        'is_parent' => false,
                        'request_code' => (string) $targetSr->request_code,
                    ];
                }
            }

            if (empty($unreleasedStockItems) && $parentSr instanceof SupplyRequest) {
                $parentSr->loadMissing('items.item.inventory', 'purchaseOrders.items', 'distributionRecords.items');
                $parentReleasedByItemId = $this->allFulfillmentDistributionRecords($parentSr)
                    ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                    ->flatMap(fn ($record) => $record->items)
                    ->groupBy(fn ($item) => (int) $item->item_id)
                    ->map(fn ($group) => (float) $group->sum('quantity_released'));

                $isParentProcurementBacked = $parentSr->purchaseRequests()->exists();

                foreach ($parentSr->items as $item) {
                    $itemId = (int) $item->item_id;
                    $pRequested = (float) $item->quantity_requested;
                    $pReleased = (float) ($parentReleasedByItemId->get($itemId) ?? 0.0);
                    $pRemaining = max($pRequested - $pReleased, 0);
                    $pInv = $item->item?->inventory;
                    $undistributed = $this->undistributedDeliveryStock($itemId, $distribution_record->delivery_id ? (int) $distribution_record->delivery_id : null);
                    $availableStock = max((float) ($pInv?->available_stock ?? 0) + (float) ($pInv?->reserved_quantity ?? 0) - $undistributed, 0.0);

                    $releasableStock = $availableStock;
                    if ($isParentProcurementBacked) {
                        $totalOrdered = (float) $parentSr->purchaseOrders
                            ->flatMap(fn ($po) => $po->items)
                            ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                            ->sum('quantity_ordered');
                        $inventoryPortion = max($pRequested - $totalOrdered, 0.0);
                        $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($parentSr, $itemId);
                        $remainingInventoryPortion = max($inventoryPortion - $alreadyReleasedInventory, 0.0);
                        $releasableStock = min($remainingInventoryPortion, $availableStock);
                    }

                    if ($pRemaining > 0.0001 && $releasableStock > 0.0001) {
                        $unreleasedStockItems[] = [
                            'supply_request_id' => (int) $parentSr->supply_request_id,
                            'supply_request_item_id' => (int) $item->supply_request_item_id,
                            'item_id' => $itemId,
                            'item_name' => (string) ($item->item?->item_name ?? $item->item_name_snapshot),
                            'category_name' => (string) ($item->item?->category?->category_name ?? '-'),
                            'unit' => (string) ($item->item?->default_unit ?? 'UNIT'),
                            'quantity_requested' => $pRequested,
                            'quantity_remaining' => $pRemaining,
                            'available_stock' => $availableStock,
                            'releasable_quantity' => min($pRemaining, $releasableStock),
                            'is_parent' => true,
                            'request_code' => (string) $parentSr->request_code,
                        ];
                    }
                }
            }
        }

        return Inertia::render('Inventory/Distribution/Show', [
            'unreleased_stock_items' => $unreleasedStockItems,
            'record' => [
                'distribution_record_id' => (int) $distribution_record->distribution_record_id,
                'distribution_code' => (string) $distribution_record->distribution_code,
                'status' => (string) $distribution_record->status,
                'is_claim_expired' => $distribution_record->status === 'RECEIVED' && ($distribution_record->updated_at ?? $distribution_record->created_at) && (($distribution_record->updated_at ?? $distribution_record->created_at)->copy()->addHours(48)->isPast() || abs(now()->diffInHours($distribution_record->updated_at ?? $distribution_record->created_at)) > 48),
                'release_date' => optional($distribution_record->release_date)?->toDateString(),
                'storage_location' => $distribution_record->storage_location,
                'notes' => $distribution_record->notes,
                'office' => $distribution_record->office ? [
                    'id' => (int) $distribution_record->office->id,
                    'name' => (string) $distribution_record->office->name,
                ] : null,
                'supply_request' => $distribution_record->supplyRequest ? [
                    'supply_request_id' => (int) $distribution_record->supplyRequest->supply_request_id,
                    'request_code' => (string) $distribution_record->supplyRequest->request_code,
                    'status' => (string) $distribution_record->supplyRequest->status,
                    'items' => $distribution_record->supplyRequest->items->map(function ($item) use ($distribution_record, $currentRecordReleasedByItemId, $requestReleasedByItemId, $deliveryRemainingByItemId): array {
                        $itemId = (int) $item->item_id;
                        $requestedQuantity = (float) $item->quantity_requested;
                        $releasedQuantity = (float) ($currentRecordReleasedByItemId->get($itemId) ?? 0.0);
                        $requestRemaining = max($requestedQuantity - (float) ($requestReleasedByItemId->get($itemId) ?? 0.0), 0);
                        $inv = $item->item?->inventory;
                        $undistributed = $this->undistributedDeliveryStock($itemId, $distribution_record->delivery_id ? (int) $distribution_record->delivery_id : null);
                        $availableStock = max((float) ($inv?->available_stock ?? 0) + (float) ($inv?->reserved_quantity ?? 0) - $undistributed, 0.0);

                        $inventoryPortion = 0.0;
                        $remainingInventoryPortion = 0.0;
                        if ($distribution_record->supplyRequest) {
                            $distribution_record->supplyRequest->loadMissing('purchaseOrders.items');
                            $totalOrdered = (float) $distribution_record->supplyRequest->purchaseOrders
                                ->flatMap(fn ($po) => $po->items)
                                ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                                ->sum('quantity_ordered');
                            $isProcurementBacked = $totalOrdered > 0 || $distribution_record->supplyRequest->purchaseRequests()->exists();
                            if ($isProcurementBacked) {
                                $inventoryPortion = max($requestedQuantity - $totalOrdered, 0.0);
                                $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($distribution_record->supplyRequest, $itemId);
                                $remainingInventoryPortion = max($inventoryPortion - $alreadyReleasedInventory, 0.0);
                            } else {
                                $remainingInventoryPortion = $requestRemaining;
                            }
                        }

                        $releasableInventory = min($remainingInventoryPortion, $availableStock);

                        $deliveryRemaining = $deliveryRemainingByItemId !== null ? (float) ($deliveryRemainingByItemId->get($itemId) ?? 0.0) : 0.0;
                        $maxReleasable = $deliveryRemainingByItemId !== null
                            ? min($requestRemaining, $deliveryRemaining + $releasableInventory)
                            : min($requestRemaining, min($remainingInventoryPortion, $availableStock));

                        return [
                            'supply_request_item_id' => (int) $item->supply_request_item_id,
                            'item_id' => $itemId,
                            'item_name' => (string) ($item->item?->item_name ?? $item->item_name_snapshot),
                            'category_name' => (string) ($item->item?->category?->category_name ?? '-'),
                            'quantity_requested' => $requestedQuantity,
                            'quantity_released' => $releasedQuantity,
                            'quantity_remaining' => $requestRemaining,
                            'unit' => (string) ($item->item?->default_unit ?? 'UNIT'),
                            'available_stock' => $availableStock,
                            'max_releasable' => max($maxReleasable, 0),
                        ];
                    })->values(),
                ] : null,
                'delivery' => $distribution_record->delivery ? [
                    'delivery_id' => (int) $distribution_record->delivery->delivery_id,
                    'delivery_code' => (string) $distribution_record->delivery->delivery_code,
                ] : null,
                'preparer' => $distribution_record->preparer ? [
                    'id' => (int) $distribution_record->preparer->id,
                    'name' => (string) $distribution_record->preparer->name,
                ] : null,
                'receiver' => $distribution_record->receiver ? [
                    'id' => (int) $distribution_record->receiver->id,
                    'name' => (string) $distribution_record->receiver->name,
                ] : null,
                'items' => $distribution_record->items->map(fn ($item): array => [
                    'distribution_item_id' => (int) $item->distribution_item_id,
                    'item_id' => (int) $item->item_id,
                    'item_name_snapshot' => (string) $item->item_name_snapshot,
                    'quantity_released' => (float) $item->quantity_released,
                    'unit' => (string) $item->unit,
                    'remarks' => $item->remarks,
                    'item' => $item->item ? [
                        'item_name' => (string) $item->item->item_name,
                        'category_name' => (string) ($item->item->category?->category_name ?? ''),
                    ] : null,
                ])->values(),
                'claims' => $distribution_record->claims->map(fn ($claim): array => [
                    'claims_id' => (int) $claim->claims_id,
                    'claim_code' => (string) $claim->claim_code,
                    'status' => (string) $claim->status,
                    'claim_type' => (string) $claim->claim_type,
                    'claim_date' => optional($claim->claim_date)?->toDateString(),
                    'claimant' => $claim->claimant ? [
                        'name' => (string) $claim->claimant->name,
                    ] : null,
                ])->values(),
            ],
        ]);
    }

    public function manageItems(DistributionRecord $distribution_record): Response
    {
        abort_unless(request()->user()->can('distribution.manage', $distribution_record), 403);

        $distribution_record->load('items');

        return Inertia::render('Inventory/Distribution/Items', [
            'record' => $distribution_record,
        ]);
    }

    public function edit(DistributionRecord $distribution_record): Response
    {
        abort_unless(request()->user()->can('distribution.manage', $distribution_record), 403);

        return Inertia::render('Inventory/Distribution/Edit', [
            'record' => $distribution_record,
        ]);
    }

    public function update(UpdateDistributionRecordRequest $request, DistributionRecord $distribution_record): RedirectResponse
    {
        abort_unless(request()->user()->can('distribution.manage', $distribution_record), 403);

        $this->distributionService->updateRecord($distribution_record, request()->user(), $request->validated());

        return Redirect::route('inventory.distribution-records.show', $distribution_record->distribution_record_id)
            ->with('success', 'Distribution record updated successfully.');
    }

    public function receive(DistributionRecord $distribution_record): RedirectResponse
    {
        abort_unless(request()->user()->can('distribution.receive', $distribution_record), 403);

        $reconciliations = $distribution_record->items()
            ->get()
            ->map(fn ($item): array => [
                'distribution_item_id' => (int) $item->distribution_item_id,
                'quantity_received' => (int) $item->quantity_released,
                'quantity_damaged' => 0,
                'quantity_missing' => 0,
                'receipt_condition' => 'GOOD',
                'receipt_notes' => null,
            ])
            ->all();

        $this->distributionService->reconcileOfficeReceipt($distribution_record, request()->user(), $reconciliations);

        $routeName = request()->user()->isOfficeUser()
            ? 'office.distribution-records.show'
            : 'inventory.distribution-records.show';

        return Redirect::route($routeName, $distribution_record->distribution_record_id)
            ->with('success', 'Distribution record marked as received.');
    }

    public function releaseAvailableStock(DistributionRecord $distribution_record): RedirectResponse
    {
        abort_unless(request()->user()->can('distribution.manage', $distribution_record), 403);

        if ((string) $distribution_record->status === 'RECEIVED') {
            return back()->with('error', 'Cannot add items to an already received distribution record.');
        }

        $distribution_record->loadMissing([
            'supplyRequest.items.item.inventory',
            'supplyRequest.distributionRecords.items',
        ]);

        $supplyRequest = $distribution_record->supplyRequest;
        if (! $supplyRequest) {
            return back()->with('error', 'No linked supply request found for this distribution.');
        }

        $allDistributions = $this->allFulfillmentDistributionRecords($supplyRequest)
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED']);
        $requestReleasedByItemId = $allDistributions
            ->flatMap(fn ($record) => $record->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($group) => (float) $group->sum('quantity_released'));

        $addedCount = 0;
        $user = request()->user();

        foreach ($supplyRequest->items as $requestItem) {
            $itemId = (int) $requestItem->item_id;
            $requested = (float) $requestItem->quantity_requested;
            $released = (float) ($requestReleasedByItemId->get($itemId) ?? 0.0);
            $remaining = max($requested - $released, 0);
            $inv = $requestItem->item?->inventory;
            $undistributed = $this->undistributedDeliveryStock($itemId, $distribution_record->delivery_id ? (int) $distribution_record->delivery_id : null);
            $availableStock = max((float) ($inv?->available_stock ?? 0) + (float) ($inv?->reserved_quantity ?? 0) - $undistributed, 0.0);

            $supplyRequest->loadMissing('purchaseOrders.items');
            $totalOrdered = (float) $supplyRequest->purchaseOrders
                ->flatMap(fn ($po) => $po->items)
                ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                ->sum('quantity_ordered');

            if ($totalOrdered > 0 || $supplyRequest->purchaseRequests()->exists()) {
                $inventoryPortion = max($requested - $totalOrdered, 0.0);
                $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($supplyRequest, $itemId);
                $remainingInventoryPortion = max($inventoryPortion - $alreadyReleasedInventory, 0.0);
                $releasable = min($remainingInventoryPortion, $availableStock);
            } else {
                $releasable = min($remaining, $availableStock);
            }
            if ($releasable > 0.0001) {
                $this->distributionService->addDistributionItem(
                    $distribution_record,
                    $user,
                    [
                        'item_id' => $itemId,
                        'item_name_snapshot' => (string) ($requestItem->item?->item_name ?? $requestItem->item_name_snapshot),
                        'quantity_released' => round($releasable, 2),
                        'unit' => (string) ($requestItem->item?->default_unit ?? 'UNIT'),
                        'remarks' => sprintf('Released from available warehouse stock for %s', $supplyRequest->request_code),
                    ]
                );
                $addedCount++;
                $requestReleasedByItemId->put($itemId, $released + $releasable);
            }
        }

        if ($addedCount > 0) {
            return Redirect::route('inventory.distribution-records.show', $distribution_record->distribution_record_id)
                ->with('success', sprintf('Successfully released %d item(s) from warehouse stock into this distribution.', $addedCount));
        }

        return back()->with('info', 'No additional warehouse stock is available to release for this request.');
    }
}
