<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\DistributionRecord\StoreDistributionRecordRequest;
use App\Http\Requests\DistributionRecord\UpdateDistributionRecordRequest;
use App\Models\DistributionRecord;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\PurchaseRequest;
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
        private readonly \App\Services\Inventory\InventoryService $inventoryService,
        private readonly \App\Services\Procurement\ConsolidationTraceabilityService $traceabilityService,
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
        $coveredSupplyRequestIds = $procurementBackedRows
            ->pluck('supply_request_id')
            ->filter()
            ->unique()
            ->all();

        $inventoryBackedRows = $this->inventoryBackedReleaseRows($coveredSupplyRequestIds);

        /** @var Collection<int, array<string, mixed>> $eligibleRows */
        $eligibleRows = $inventoryBackedRows
            ->concat($procurementBackedRows)
            ->unique(fn (array $row): string => ($row['delivery_id'] ?? null) !== null ? 'del_' . $row['delivery_id'] . '_sr_' . $row['supply_request_id'] : 'inv_' . $row['supply_request_id'])
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
        $deliveries = Delivery::query()
            ->whereIn('receipt_status', ['VERIFIED', 'PARTIALLY_RECEIVED'])
            ->whereHas('purchaseOrder', function ($query): void {
                $query->where(function ($poQuery) {
                    $poQuery->whereNotNull('supply_request_id')
                        ->orWhereHas('purchaseRequest', fn ($prQ) => $prQ->whereNotNull('supply_request_id'))
                        ->orWhereHas('purchaseRequest', fn ($prQ) => $prQ->where('remarks', 'like', '%SR-%'));
                })
                ->where(function ($subQ) {
                    $subQ->whereHas('supplyRequest.office', fn ($officeQuery) => $officeQuery->where('code', '!=', 'GSO'))
                        ->orWhereHas('purchaseRequest.supplyRequest.office', fn ($officeQuery) => $officeQuery->where('code', '!=', 'GSO'))
                        ->orWhereHas('purchaseRequest.office', fn ($officeQuery) => $officeQuery->where('code', '!=', 'GSO'));
                });
            })
            ->whereDoesntHave('distributionRecords', fn ($q) => $q->where('status', 'DRAFT'))
            ->with([
                'purchaseOrder.supplyRequest.office',
                'purchaseOrder.purchaseRequest.supplyRequest.office',
                'purchaseOrder.purchaseRequest.office',
                'purchaseOrder.purchaseRequest.sourceDelivery.purchaseOrder',
                'items.purchaseOrderItem',
                'distributionRecords.items',
            ])
            ->latest('delivery_id')
            ->get();

        $deliveryIds = $deliveries->pluck('delivery_id')->filter()->map(fn ($id): int => (int) $id)->all();
        $downstreamDiscrepanciesByDeliveryId = [];
        if (! empty($deliveryIds)) {
            $prsWithSource = PurchaseRequest::query()
                ->where(function ($q) use ($deliveryIds) {
                    $q->whereIn('source_delivery_id', $deliveryIds)
                        ->orWhereHas('sourceClaim.distributionRecord', fn ($drQ) => $drQ->whereIn('delivery_id', $deliveryIds));
                })
                ->with('sourceClaim.distributionRecord')
                ->get(['purchase_request_id', 'source_delivery_id', 'source_claim_id', 'supply_request_id']);

            foreach ($prsWithSource as $pr) {
                $srId = (int) $pr->supply_request_id;
                if ($srId <= 0) {
                    continue;
                }
                if ((int) $pr->source_delivery_id > 0) {
                    $downstreamDiscrepanciesByDeliveryId[(int) $pr->source_delivery_id][] = $srId;
                }
                $claimDelId = (int) ($pr->sourceClaim?->distributionRecord?->delivery_id ?? 0);
                if ($claimDelId > 0) {
                    $downstreamDiscrepanciesByDeliveryId[$claimDelId][] = $srId;
                }
            }
        }

        return $deliveries
            ->flatMap(fn (Delivery $delivery): Collection => $this->releaseRowsFromVerifiedDelivery(
                $delivery,
                $downstreamDiscrepanciesByDeliveryId[(int) $delivery->delivery_id] ?? []
            ))
            ->filter()
            ->values();
    }

    /**
     * @param  array<int, int>  $preloadedDownstreamChildIds
     * @return Collection<int, array<string, mixed>>
     */
    private function releaseRowsFromVerifiedDelivery(Delivery $delivery, array $preloadedDownstreamChildIds = []): Collection
    {
        $acceptedTotal = (float) $delivery->items->sum(function ($item): float {
            return max((float) $item->received_quantity - (float) $item->damaged_quantity, 0);
        });

        $releasedTotal = (float) $delivery->distributionRecords
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
            ->flatMap(fn ($record) => $record->items)
            ->sum('quantity_released');

        if ($acceptedTotal - $releasedTotal <= 0.0001) {
            return collect();
        }

        $sourceSupplyRequests = collect();
        $isFollowUpDelivery = in_array((string) ($delivery->purchaseOrder?->purchaseRequest?->source_type ?? ''), ['PARTIAL_DELIVERY_FOLLOW_UP', 'CLAIM_REPLACEMENT_FOLLOW_UP'], true)
            || (int) ($delivery->purchaseOrder?->purchaseRequest?->source_delivery_id ?? 0) > 0
            || (int) ($delivery->purchaseOrder?->purchaseRequest?->source_claim_id ?? 0) > 0;

        if ($isFollowUpDelivery) {
            // A follow-up delivery fulfills its specific child supply request
            $targetChildSr = $delivery->purchaseOrder?->purchaseRequest?->supplyRequest
                ?? $delivery->purchaseOrder?->supplyRequest;
            if ($targetChildSr instanceof SupplyRequest) {
                $sourceSupplyRequests->push($targetChildSr);
            }
        } else {
            if ($delivery->purchaseOrder?->supplyRequest instanceof SupplyRequest) {
                $sourceSupplyRequests->push($delivery->purchaseOrder->supplyRequest);
            }
            if ($delivery->purchaseOrder?->purchaseRequest?->supplyRequest instanceof SupplyRequest) {
                $sourceSupplyRequests->push($delivery->purchaseOrder->purchaseRequest->supplyRequest);
            }
            if ($delivery->purchaseOrder) {
                $consolidated = $this->traceabilityService->getSourceSupplyRequests($delivery->purchaseOrder);
                $sourceSupplyRequests = $sourceSupplyRequests->merge($consolidated);
            }

            // Exclude any child follow-up requests that were created FROM this delivery discrepancy
            // (they are waiting for future deliveries, not this delivery where items were missing/damaged)
            $downstreamDiscrepancyChildIds = ! empty($preloadedDownstreamChildIds)
                ? $preloadedDownstreamChildIds
                : PurchaseRequest::query()
                    ->where(function ($q) use ($delivery) {
                        $q->where('source_delivery_id', (int) $delivery->delivery_id)
                            ->orWhereHas('sourceClaim.distributionRecord', fn ($drQ) => $drQ->where('delivery_id', (int) $delivery->delivery_id));
                    })
                    ->pluck('supply_request_id')
                    ->filter()
                    ->all();

            if (! empty($downstreamDiscrepancyChildIds)) {
                $sourceSupplyRequests = $sourceSupplyRequests->reject(
                    fn ($sr) => in_array((int) $sr->supply_request_id, $downstreamDiscrepancyChildIds, true)
                );
            }
        }

        // Never offer supply requests that are cancelled, rejected, or closed
        $sourceSupplyRequests = $sourceSupplyRequests->reject(function ($sr): bool {
            if (! $sr instanceof SupplyRequest) {
                return true;
            }

            return in_array(strtoupper((string) $sr->status), ['CLOSED', 'CANCELLED', 'REJECTED'], true);
        });

        $sourceSupplyRequests = $sourceSupplyRequests->unique('supply_request_id')->values();

        $rows = collect();

        foreach ($sourceSupplyRequests as $supplyRequest) {
            if (! $supplyRequest instanceof SupplyRequest) {
                continue;
            }

            if ((string) ($supplyRequest->office?->code ?? '') === 'GSO') {
                continue;
            }

            if (! $this->hasReleasableQuantityForDelivery($supplyRequest, $delivery)) {
                continue;
            }

            $rows->push([
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
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<int, int>  $excludeSupplyRequestIds
     * @return Collection<int, array<string, mixed>>
     */
    private function inventoryBackedReleaseRows(array $excludeSupplyRequestIds = []): Collection
    {
        return SupplyRequest::query()
            ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PARTIALLY_RECEIVED', 'FOR_PROCUREMENT'])
            ->whereHas('office', fn ($officeQuery) => $officeQuery->where('code', '!=', 'GSO'))
            ->whereDoesntHave('distributionRecords', fn ($query) => $query->where('status', 'DRAFT'))
            ->whereDoesntHave('purchaseRequests', function ($prQ) {
                $prQ->whereIn('source_type', ['PARTIAL_DELIVERY_FOLLOW_UP', 'CLAIM_REPLACEMENT_FOLLOW_UP'])
                    ->orWhereNotNull('source_delivery_id')
                    ->orWhereNotNull('source_claim_id');
            })
            ->when(! empty($excludeSupplyRequestIds), fn ($query) => $query->whereNotIn('supply_request_id', $excludeSupplyRequestIds))
            ->with(['office', 'items.item.inventory', 'distributionRecords.items', 'distributionRecords.delivery.items.purchaseOrderItem', 'purchaseRequests', 'purchaseOrders.items'])
            ->latest('supply_request_id')
            ->get()
            ->map(fn (SupplyRequest $request): ?array => $this->releaseRowFromAvailableInventory($request))
            ->filter()
            ->values();
    }

    private function releaseRowFromAvailableInventory(SupplyRequest $request): ?array
    {
        $prs = $this->traceabilityService->getPurchaseRequestsForSupplyRequest($request);
        $allPrs = $prs->merge($request->purchaseRequests)->unique('purchase_request_id');

        $isDiscrepancyChild = $allPrs->contains(
            fn ($pr) => in_array((string) $pr->source_type, ['PARTIAL_DELIVERY_FOLLOW_UP', 'CLAIM_REPLACEMENT_FOLLOW_UP'], true)
                || (int) ($pr->source_delivery_id ?? 0) > 0
                || (int) ($pr->source_claim_id ?? 0) > 0
        );

        if ($isDiscrepancyChild) {
            return null;
        }

        if (! $this->hasRemainingQuantity($request)) {
            return null;
        }

        $hasReleasableStock = false;
        $releasedPool = $this->releasedQuantityByItemId($request);
        $isProcurementBacked = $allPrs->isNotEmpty();
        $request->loadMissing('purchaseOrders.items');

        $hasPendingDeliveries = false;
        if ($isProcurementBacked) {
            $deliveryIds = $this->followUpService->resolveDeliveryIdsForSupplyRequest($request);
            $hasPendingDeliveries = Delivery::query()
                ->where(function ($q) use ($request, $deliveryIds) {
                    $q->whereIn('delivery_id', $deliveryIds)
                        ->orWhereHas('purchaseOrder', function ($poQ) use ($request) {
                            $lineageIds = $this->followUpService->resolveLineageSupplyRequestIds($request);
                            $poQ->whereIn('supply_request_id', $lineageIds);
                        });
                })
                ->where(function ($q) {
                    $q->whereNotIn('receipt_status', ['VERIFIED', 'PARTIALLY_RECEIVED'])
                        ->orWhereDoesntHave('distributionRecords', fn ($drQ) => $drQ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED']));
                })
                ->exists();
        }

        foreach ($request->items as $item) {
            $itemId = (int) $item->item_id;
            $requested = (float) $item->quantity_requested;
            $availReleased = (float) ($releasedPool[$itemId] ?? 0.0);
            $released = min($requested, $availReleased);
            $releasedPool[$itemId] = max($availReleased - $released, 0.0);

            $remaining = max($requested - $released, 0);
            $available = $this->inventoryService->getAvailableStockForSupplyRequest($itemId, $request);

            $releasableStock = min($remaining, $available);

            if ($isProcurementBacked) {
                $activePrRequested = (float) $allPrs
                    ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
                    ->flatMap(fn ($pr) => $pr->items)
                    ->filter(fn ($pri) => (int) $pri->item_id === $itemId)
                    ->sum('quantity_requested');

                $totalOrdered = (float) $allPrs
                    ->flatMap(fn ($pr) => $pr->purchaseOrders)
                    ->flatMap(fn ($po) => $po->items)
                    ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                    ->sum('quantity_ordered');
                if ($totalOrdered <= 0.0) {
                    $totalOrdered = (float) $request->purchaseOrders
                        ->flatMap(fn ($po) => $po->items)
                        ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                        ->sum('quantity_ordered');
                }
                $procuredPortion = max($activePrRequested, $totalOrdered);
                $inventoryPortion = max($requested - $procuredPortion, 0.0);
                $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($request, $itemId);
                $remainingInventoryPortion = max($inventoryPortion - $alreadyReleasedInventory, 0.0);

                $releasableStock = min(max($remainingInventoryPortion, 0.0), $available);
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
            $available = $this->inventoryService->getAvailableStockForSupplyRequest($itemId, $request);
            $shortageTotal += max($remaining - $available, 0);
        }

        return $shortageTotal;
    }

    private function hasReleasableQuantityForDelivery(SupplyRequest $supplyRequest, Delivery $delivery): bool
    {
        if (in_array(strtoupper((string) $supplyRequest->status), ['CLOSED', 'CANCELLED', 'REJECTED'], true)) {
            return false;
        }

        $deliveryAcceptedByItem = $delivery->items
            ->groupBy(fn ($item) => (int) ($item->purchaseOrderItem?->item_id ?? 0))
            ->map(fn ($items): float => (float) $items->sum(
                fn ($item): float => max((float) $item->received_quantity - (float) $item->damaged_quantity, 0)
            ));

        $deliveryReleasedByItem = $delivery->distributionRecords
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
            ->flatMap(fn ($record) => $record->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($items): float => (float) $items->sum('quantity_released'));

        $srReleasedByItem = $this->allFulfillmentDistributionRecords($supplyRequest)
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
            ->flatMap(fn ($record) => $record->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($items): float => (float) $items->sum('quantity_released'));

        foreach ($supplyRequest->items as $item) {
            $itemId = (int) $item->item_id;
            $accepted = (float) ($deliveryAcceptedByItem->get($itemId) ?? 0.0);
            $deliveryRel = (float) ($deliveryReleasedByItem->get($itemId) ?? 0.0);
            $deliveryAvail = max($accepted - $deliveryRel, 0.0);

            if ($deliveryAvail <= 0.0001) {
                continue;
            }

            $requested = (float) $item->quantity_requested;
            $srRel = (float) ($srReleasedByItem->get($itemId) ?? 0.0);
            $srNeeded = max($requested - $srRel, 0.0);

            if ($srNeeded > 0.0001) {
                return true;
            }
        }

        return false;
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

    private function undistributedDeliveryStock(int $itemId, array|int|null $excludeDeliveryId = null): float
    {
        return $this->inventoryService->getUndistributedDeliveryStock($itemId, $excludeDeliveryId);
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
                ->sum(fn ($di) => max((float) $di->received_quantity - (float) $di->damaged_quantity, 0.0));

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

        $prs = $this->traceabilityService->getPurchaseRequestsForSupplyRequest($request);
        $allPrs = $prs->merge($request->purchaseRequests)->unique('purchase_request_id');

        $isDiscrepancyChild = $allPrs->contains(
            fn ($pr) => in_array((string) $pr->source_type, ['PARTIAL_DELIVERY_FOLLOW_UP', 'CLAIM_REPLACEMENT_FOLLOW_UP'], true)
                || (int) ($pr->source_delivery_id ?? 0) > 0
                || (int) ($pr->source_claim_id ?? 0) > 0
        );

        if ($isDiscrepancyChild) {
            return 'This follow-up request is queued for procurement and awaits verified delivery before distribution release.';
        }

        $existingDraftRecord = $request->distributionRecords->firstWhere('status', 'DRAFT');
        if ($existingDraftRecord instanceof DistributionRecord) {
            return 'This request already has a draft distribution record. Continue releasing items in that draft.';
        }

        if (! $this->hasRemainingQuantity($request)) {
            return 'All requested quantities have already been released for this request.';
        }

        $hasPurchaseRequest = $allPrs->isNotEmpty();

        $shortageTotal = 0.0;
        $hasAvailableStock = false;
        $releasedPool = $this->releasedQuantityByItemId($request);

        $hasPendingDeliveries = false;
        if ($hasPurchaseRequest) {
            $deliveryIds = $this->followUpService->resolveDeliveryIdsForSupplyRequest($request);
            $hasPendingDeliveries = Delivery::query()
                ->where(function ($q) use ($request, $deliveryIds) {
                    $q->whereIn('delivery_id', $deliveryIds)
                        ->orWhereHas('purchaseOrder', function ($poQ) use ($request) {
                            $lineageIds = $this->followUpService->resolveLineageSupplyRequestIds($request);
                            $poQ->whereIn('supply_request_id', $lineageIds);
                        });
                })
                ->where(function ($q) {
                    $q->whereNotIn('receipt_status', ['VERIFIED', 'PARTIALLY_RECEIVED'])
                        ->orWhereDoesntHave('distributionRecords', fn ($drQ) => $drQ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED']));
                })
                ->exists();
        }

        foreach ($request->items as $item) {
            $itemId = (int) $item->item_id;
            $requested = (float) $item->quantity_requested;
            $availReleased = (float) ($releasedPool[$itemId] ?? 0.0);
            $released = min($requested, $availReleased);
            $releasedPool[$itemId] = max($availReleased - $released, 0.0);

            $remaining = max($requested - $released, 0);
            $available = $this->inventoryService->getAvailableStockForSupplyRequest($itemId, $request);
            $shortageTotal += max($remaining - $available, 0);

            $releasableStock = min($remaining, $available);

            if ($hasPurchaseRequest) {
                $activePrRequested = (float) $allPrs
                    ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
                    ->flatMap(fn ($pr) => $pr->items)
                    ->filter(fn ($pri) => (int) $pri->item_id === $itemId)
                    ->sum('quantity_requested');

                $totalOrdered = (float) $allPrs
                    ->flatMap(fn ($pr) => $pr->purchaseOrders)
                    ->flatMap(fn ($po) => $po->items)
                    ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                    ->sum('quantity_ordered');
                if ($totalOrdered <= 0.0) {
                    $totalOrdered = (float) $request->purchaseOrders
                        ->flatMap(fn ($po) => $po->items)
                        ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                        ->sum('quantity_ordered');
                }
                $procuredPortion = max($activePrRequested, $totalOrdered);
                $inventoryPortion = max($requested - $procuredPortion, 0.0);
                $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($request, $itemId);
                $remainingInventoryPortion = max($inventoryPortion - $alreadyReleasedInventory, 0.0);

                $releasableStock = min(max($remainingInventoryPortion, 0.0), $available);
            }

            if ($remaining > 0.0001 && $releasableStock > 0.0001) {
                $hasAvailableStock = true;
            }
        }

        if ($hasAvailableStock) {
            return null;
        }

        if ($hasPurchaseRequest) {
            $deliveryIds = $this->followUpService->resolveDeliveryIdsForSupplyRequest($request);
            $hasVerifiedDelivery = Delivery::query()
                ->whereIn('receipt_status', ['VERIFIED', 'PARTIALLY_RECEIVED'])
                ->where(function ($query) use ($request, $deliveryIds): void {
                    $query->whereIn('delivery_id', $deliveryIds)
                        ->orWhereHas('purchaseOrder', function ($poQ) use ($request) {
                            $lineageIds = $this->followUpService->resolveLineageSupplyRequestIds($request);
                            $poQ->whereIn('supply_request_id', $lineageIds);
                        });
                })
                ->whereDoesntHave('distributionRecords', fn ($q) => $q->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED']))
                ->with(['items'])
                ->get()
                ->contains(function (Delivery $del): bool {
                    $accepted = (float) $del->items->sum(fn ($i) => max((float) $i->received_quantity - (float) $i->damaged_quantity, 0));
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
                    fn ($item): float => max((float) $item->received_quantity - (float) $item->damaged_quantity, 0)
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
        $rootSr = $targetSr ? $this->followUpService->resolveRootSupplyRequest($targetSr) : null;
        $parentSr = ($rootSr && (int) $rootSr->supply_request_id !== (int) ($targetSr?->supply_request_id ?? 0)) ? $rootSr : null;

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
                $reservedStock = (float) ($inv?->reserved_quantity ?? 0);
                $availableStock = max((float) ($inv?->available_stock ?? 0) + $reservedStock - $undistributed, 0.0);

                if ($distribution_record->delivery_id !== null) {
                    $targetSr->loadMissing(['purchaseRequests.items', 'purchaseOrders.items', 'purchaseOrders.purchaseRequest']);
                    $orderedFromPr = (float) $targetSr->purchaseRequests
                        ->filter(fn ($pr) => empty($pr->source_type) || $pr->source_type === 'SHORTAGE')
                        ->flatMap(fn ($pr) => $pr->items)
                        ->where('item_id', $itemId)
                        ->sum('quantity_requested');
                    $orderedFromPo = (float) $targetSr->purchaseOrders
                        ->filter(fn ($po) => $po->purchaseRequest === null || empty($po->purchaseRequest->source_type) || $po->purchaseRequest->source_type === 'SHORTAGE')
                        ->flatMap(fn ($po) => $po->items)
                        ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                        ->sum('quantity_ordered');
                    $originalOrdered = max($orderedFromPr, $orderedFromPo);
                    $unprocuredPortion = $originalOrdered > 0 ? max($requestedQuantity - $originalOrdered, 0.0) : 0.0;

                    $earmarkedWarehouseStock = max($reservedStock, $unprocuredPortion);
                    $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($targetSr, $itemId);
                    $remainingWarehouseQuota = max($earmarkedWarehouseStock - $alreadyReleasedInventory, 0.0);

                    $releasableStock = min($requestRemaining, min($remainingWarehouseQuota, $availableStock));
                } else {
                    $releasableStock = min($requestRemaining, $availableStock);
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
                        'releasable_quantity' => $releasableStock,
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
                    $pReservedStock = (float) ($pInv?->reserved_quantity ?? 0);
                    $availableStock = max((float) ($pInv?->available_stock ?? 0) + $pReservedStock - $undistributed, 0.0);

                    if ($distribution_record->delivery_id !== null) {
                        $parentSr->loadMissing(['purchaseRequests.items', 'purchaseOrders.items', 'purchaseOrders.purchaseRequest']);
                        $orderedFromPr = (float) $parentSr->purchaseRequests
                            ->filter(fn ($pr) => empty($pr->source_type) || $pr->source_type === 'SHORTAGE')
                            ->flatMap(fn ($pr) => $pr->items)
                            ->where('item_id', $itemId)
                            ->sum('quantity_requested');
                        $orderedFromPo = (float) $parentSr->purchaseOrders
                            ->filter(fn ($po) => $po->purchaseRequest === null || empty($po->purchaseRequest->source_type) || $po->purchaseRequest->source_type === 'SHORTAGE')
                            ->flatMap(fn ($po) => $po->items)
                            ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                            ->sum('quantity_ordered');
                        $originalOrdered = max($orderedFromPr, $orderedFromPo);
                        $unprocuredPortion = $originalOrdered > 0 ? max($pRequested - $originalOrdered, 0.0) : 0.0;

                        $earmarkedWarehouseStock = max($pReservedStock, $unprocuredPortion);
                        $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($parentSr, $itemId);
                        $remainingWarehouseQuota = max($earmarkedWarehouseStock - $alreadyReleasedInventory, 0.0);

                        $releasableStock = min($pRemaining, min($remainingWarehouseQuota, $availableStock));
                    } else {
                        $releasableStock = min($pRemaining, $availableStock);
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
                            'releasable_quantity' => $releasableStock,
                            'is_parent' => true,
                            'request_code' => (string) $parentSr->request_code,
                        ];
                    }
                }
            }
        }

        $receivedTimestamp = $distribution_record->updated_at ?? $distribution_record->created_at;
        $receivedCarbon = $receivedTimestamp ? \Illuminate\Support\Carbon::parse($receivedTimestamp) : null;
        $isClaimExpired = $distribution_record->status === 'RECEIVED'
            && $receivedCarbon !== null
            && ($receivedCarbon->copy()->addHours(48)->isPast() || abs(now()->diffInHours($receivedCarbon)) > 48);

        return Inertia::render('Inventory/Distribution/Show', [
            'unreleased_stock_items' => $unreleasedStockItems,
            'record' => [
                'distribution_record_id' => (int) $distribution_record->distribution_record_id,
                'distribution_code' => (string) $distribution_record->distribution_code,
                'status' => (string) $distribution_record->status,
                'is_claim_expired' => $isClaimExpired,
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
                        $reservedStock = (float) ($inv?->reserved_quantity ?? 0);
                        $availableStock = max((float) ($inv?->available_stock ?? 0) + $reservedStock - $undistributed, 0.0);

                        if ($deliveryRemainingByItemId !== null) {
                            $deliveryRemaining = (float) ($deliveryRemainingByItemId->get($itemId) ?? 0.0);
                            $deliveryPortion = min($requestRemaining, $deliveryRemaining);
                            $unmetRequest = max($requestRemaining - $deliveryPortion, 0.0);

                            $distribution_record->supplyRequest->loadMissing(['purchaseRequests.items', 'purchaseOrders.items', 'purchaseOrders.purchaseRequest']);
                            $orderedFromPr = (float) $distribution_record->supplyRequest->purchaseRequests
                                ->filter(fn ($pr) => empty($pr->source_type) || $pr->source_type === 'SHORTAGE')
                                ->flatMap(fn ($pr) => $pr->items)
                                ->where('item_id', $itemId)
                                ->sum('quantity_requested');
                            $orderedFromPo = (float) $distribution_record->supplyRequest->purchaseOrders
                                ->filter(fn ($po) => $po->purchaseRequest === null || empty($po->purchaseRequest->source_type) || $po->purchaseRequest->source_type === 'SHORTAGE')
                                ->flatMap(fn ($po) => $po->items)
                                ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                                ->sum('quantity_ordered');
                            $originalOrdered = max($orderedFromPr, $orderedFromPo);
                            $unprocuredPortion = $originalOrdered > 0 ? max($requestedQuantity - $originalOrdered, 0.0) : 0.0;

                            $earmarkedWarehouseStock = max($reservedStock, $unprocuredPortion);
                            $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($distribution_record->supplyRequest, $itemId);
                            $remainingWarehouseQuota = max($earmarkedWarehouseStock - $alreadyReleasedInventory, 0.0);

                            $availableWarehouseStock = max($availableStock - $deliveryRemaining, 0.0);
                            $releasableInventory = min($unmetRequest, min($remainingWarehouseQuota, $availableWarehouseStock));

                            $maxReleasable = min($requestRemaining, $deliveryPortion + $releasableInventory);
                        } else {
                            $maxReleasable = min($requestRemaining, $availableStock);
                        }

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
            $reservedStock = (float) ($inv?->reserved_quantity ?? 0);
            $availableStock = max((float) ($inv?->available_stock ?? 0) + $reservedStock - $undistributed, 0.0);

            if ($distribution_record->delivery_id !== null) {
                $supplyRequest->loadMissing(['purchaseRequests.items', 'purchaseOrders.items', 'purchaseOrders.purchaseRequest']);
                $orderedFromPr = (float) $supplyRequest->purchaseRequests
                    ->filter(fn ($pr) => empty($pr->source_type) || $pr->source_type === 'SHORTAGE')
                    ->flatMap(fn ($pr) => $pr->items)
                    ->where('item_id', $itemId)
                    ->sum('quantity_requested');
                $orderedFromPo = (float) $supplyRequest->purchaseOrders
                    ->filter(fn ($po) => $po->purchaseRequest === null || empty($po->purchaseRequest->source_type) || $po->purchaseRequest->source_type === 'SHORTAGE')
                    ->flatMap(fn ($po) => $po->items)
                    ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                    ->sum('quantity_ordered');
                $originalOrdered = max($orderedFromPr, $orderedFromPo);
                $unprocuredPortion = $originalOrdered > 0 ? max($requested - $originalOrdered, 0.0) : 0.0;

                $earmarkedWarehouseStock = max($reservedStock, $unprocuredPortion);
                $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($supplyRequest, $itemId);
                $remainingWarehouseQuota = max($earmarkedWarehouseStock - $alreadyReleasedInventory, 0.0);

                $releasable = min($remaining, min($remainingWarehouseQuota, $availableStock));
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
