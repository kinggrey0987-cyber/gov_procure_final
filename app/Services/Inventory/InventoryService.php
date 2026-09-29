<?php

namespace App\Services\Inventory;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Inventory;
use App\Models\StatusHistory;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\Procurement\ConsolidationTraceabilityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /** @var array<string, float> */
    private array $undistributedStockCache = [];

    /** @var array<int, ?Inventory> */
    private array $inventoryByItemCache = [];

    /** @var array<int, bool> */
    private array $generalInventoryDeliveryCache = [];

    /**
     * Clear memoized cache for testing or batch operations.
     */
    public function clearCache(): void
    {
        $this->undistributedStockCache = [];
        $this->inventoryByItemCache = [];
        $this->generalInventoryDeliveryCache = [];
    }

    public function __construct(
        private readonly InventoryMovementService $movementService,
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
        private readonly SupplyRequestFollowUpService $followUpService,
        private readonly ConsolidationTraceabilityService $traceabilityService,
    ) {
    }

    /**
     * @return Collection<int, Inventory>
     */
    public function updateFromVerifiedDelivery(Delivery $delivery, User $actor): Collection
    {
        $this->assertGsoOfficer($actor);

        if (! in_array((string) $delivery->receipt_status, ['VERIFIED', 'PARTIALLY_RECEIVED'], true)) {
            throw ValidationException::withMessages([
                'delivery_id' => 'Only VERIFIED or PARTIALLY_RECEIVED deliveries can update inventory.',
            ]);
        }

        $this->clearCache();

        return DB::transaction(function () use ($delivery, $actor): Collection {
            $inventories = collect();

            $delivery->loadMissing('items.purchaseOrderItem', 'purchaseOrder.supplyRequest.requester');

            foreach ($delivery->items as $deliveryItem) {
                $itemId = (int) ($deliveryItem->purchaseOrderItem?->item_id ?? 0);
                if ($itemId <= 0) {
                    continue;
                }

                $inventory = $this->getOrCreateInventory($itemId);

                $alreadySynced = $inventory->movements()
                    ->where('movement_type', 'DELIVERY_RECEIPT')
                    ->where('reference_type', Delivery::class)
                    ->where('reference_id', (int) $delivery->delivery_id)
                    ->exists();

                if ($alreadySynced) {
                    continue;
                }

                $received = (float) $deliveryItem->received_quantity;
                $damaged = (float) $deliveryItem->damaged_quantity;
                $netQuantity = max($received - $damaged, 0);

                $inventory->forceFill([
                    'stock_on_hand' => (float) $inventory->stock_on_hand + $netQuantity,
                    'available_stock' => (float) $inventory->available_stock + $netQuantity,
                    'last_updated' => now(),
                ])->save();

                $this->movementService->record(
                    inventoryId: (int) $inventory->inventory_id,
                    movementType: 'DELIVERY_RECEIPT',
                    referenceType: Delivery::class,
                    referenceId: (int) $delivery->delivery_id,
                    quantity: $netQuantity,
                    actor: $actor,
                    remarks: (string) $delivery->receipt_status === 'PARTIALLY_RECEIVED'
                        ? 'Inventory updated from accepted quantity of partially received delivery.'
                        : 'Inventory updated from verified delivery.',
                );

                $this->recordStatus(
                    referenceType: Inventory::class,
                    referenceId: (int) $inventory->inventory_id,
                    oldStatus: null,
                    newStatus: ((float) $inventory->available_stock <= (float) $inventory->low_stock_threshold) ? 'LOW_STOCK' : 'IN_STOCK',
                    actor: $actor,
                    remarks: 'Inventory quantity changed from verified delivery.',
                );

                $this->recordAudit($actor, 'SYNC_FROM_DELIVERY', $inventory, [
                    'delivery_id' => (int) $delivery->delivery_id,
                    'item_id' => $itemId,
                    'quantity_added' => $netQuantity,
                    'receipt_status' => (string) $delivery->receipt_status,
                ]);

                $inventories->push($inventory->fresh());
            }

            $supplyRequest = $delivery->purchaseOrder?->supplyRequest;
            if ($supplyRequest) {
                $this->notificationService->notifySupplyRequestOfficeRequester(
                    supplyRequest: $supplyRequest,
                    title: 'Delivery Inventory Updated',
                    message: (string) $delivery->receipt_status === 'PARTIALLY_RECEIVED'
                        ? sprintf('GSO updated inventory from the accepted quantity of the partially received delivery for supply request %s.', (string) $supplyRequest->request_code)
                        : sprintf('GSO updated inventory from the verified delivery for supply request %s.', (string) $supplyRequest->request_code),
                    notificationType: 'INVENTORY_UPDATED',
                );
            }

            return $inventories;
        });
    }

    public function releaseStock(
        int $itemId,
        float $quantity,
        User $actor,
        string $referenceType,
        int $referenceId,
        string $remarks,
    ): Inventory {
        $this->assertGsoOfficer($actor);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity_released' => 'Released quantity must be greater than zero.',
            ]);
        }

        $this->clearCache();

        return DB::transaction(function () use ($itemId, $quantity, $actor, $referenceType, $referenceId, $remarks): Inventory {
            /** @var Inventory|null $inventory */
            $inventory = Inventory::query()->where('item_id', $itemId)->lockForUpdate()->first();
            if (!$inventory instanceof Inventory) {
                throw ValidationException::withMessages([
                    'item_id' => 'No inventory record found for the selected item.',
                ]);
            }

            $fromReserved = min($quantity, max((float) $inventory->reserved_quantity, 0.0));
            $fromAvailable = $quantity - $fromReserved;

            if ((float) $inventory->available_stock < $fromAvailable) {
                throw ValidationException::withMessages([
                    'quantity_released' => 'Released quantity cannot exceed available stock.',
                ]);
            }

            $inventory->forceFill([
                'stock_on_hand' => (float) $inventory->stock_on_hand - $quantity,
                'available_stock' => (float) $inventory->available_stock - $fromAvailable,
                'reserved_quantity' => (float) $inventory->reserved_quantity - $fromReserved,
                'released_quantity' => (float) $inventory->released_quantity + $quantity,
                'last_updated' => now(),
            ])->save();

            $this->movementService->record(
                inventoryId: (int) $inventory->inventory_id,
                movementType: 'RELEASE',
                referenceType: $referenceType,
                referenceId: $referenceId,
                quantity: $quantity,
                actor: $actor,
                remarks: $remarks,
            );

            $this->recordAudit($actor, 'RELEASE_STOCK', $inventory, [
                'item_id' => $itemId,
                'quantity' => $quantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            return $inventory->fresh() ?? $inventory;
        });
    }

    public function releaseClaimReplacementStock(
        int $itemId,
        float $quantity,
        User $actor,
        string $referenceType,
        int $referenceId,
        string $remarks,
    ): Inventory {
        $this->assertGsoOfficer($actor);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'replacement_quantity' => 'Replacement quantity must be greater than zero.',
            ]);
        }

        $this->clearCache();

        return DB::transaction(function () use ($itemId, $quantity, $actor, $referenceType, $referenceId, $remarks): Inventory {
            /** @var Inventory|null $inventory */
            $inventory = Inventory::query()->where('item_id', $itemId)->lockForUpdate()->first();
            if (!$inventory instanceof Inventory) {
                throw ValidationException::withMessages([
                    'replacement_quantity' => 'No inventory stock exists for the claim replacement item.',
                ]);
            }

            if ((float) $inventory->available_stock < $quantity) {
                throw ValidationException::withMessages([
                    'replacement_quantity' => sprintf(
                        'Replacement quantity cannot exceed available stock of %.2f.',
                        (float) $inventory->available_stock,
                    ),
                ]);
            }

            $inventory->forceFill([
                'stock_on_hand' => (float) $inventory->stock_on_hand - $quantity,
                'available_stock' => (float) $inventory->available_stock - $quantity,
                'released_quantity' => (float) $inventory->released_quantity + $quantity,
                'last_updated' => now(),
            ])->save();

            $this->movementService->record(
                inventoryId: (int) $inventory->inventory_id,
                movementType: 'CLAIM_REPLACEMENT',
                referenceType: $referenceType,
                referenceId: $referenceId,
                quantity: $quantity,
                actor: $actor,
                remarks: $remarks,
            );

            $this->recordAudit($actor, 'CLAIM_REPLACEMENT_STOCK', $inventory, [
                'item_id' => $itemId,
                'quantity' => $quantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            return $inventory->fresh() ?? $inventory;
        });
    }

    public function restoreReleasedStock(
        int $itemId,
        float $quantity,
        User $actor,
        string $referenceType,
        int $referenceId,
        string $remarks,
    ): Inventory {
        $this->assertGsoOfficer($actor);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity_released' => 'Restored quantity must be greater than zero.',
            ]);
        }

        $this->clearCache();

        return DB::transaction(function () use ($itemId, $quantity, $actor, $referenceType, $referenceId, $remarks): Inventory {
            /** @var Inventory $inventory */
            $inventory = Inventory::query()->where('item_id', $itemId)->lockForUpdate()->firstOrFail();

            $updatedReleased = max((float) $inventory->released_quantity - $quantity, 0);

            $inventory->forceFill([
                'stock_on_hand' => (float) $inventory->stock_on_hand + $quantity,
                'available_stock' => (float) $inventory->available_stock + $quantity,
                'released_quantity' => $updatedReleased,
                'last_updated' => now(),
            ])->save();

            $this->movementService->record(
                inventoryId: (int) $inventory->inventory_id,
                movementType: 'RELEASE_REVERSAL',
                referenceType: $referenceType,
                referenceId: $referenceId,
                quantity: $quantity,
                actor: $actor,
                remarks: $remarks,
            );

            $this->recordAudit($actor, 'RESTORE_STOCK', $inventory, [
                'item_id' => $itemId,
                'quantity' => $quantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            return $inventory->fresh() ?? $inventory;
        });
    }

    private function getOrCreateInventory(int $itemId): Inventory
    {
        return Inventory::query()->firstOrCreate(
            ['item_id' => $itemId],
            [
                'available_stock' => 0,
                'stock_on_hand' => 0,
                'reserved_quantity' => 0,
                'released_quantity' => 0,
                'low_stock_threshold' => 10,
                'last_updated' => now(),
            ],
        );
    }

    private function assertGsoOfficer(User $actor): void
    {
        if (!$actor->isGsoOfficer()) {
            throw ValidationException::withMessages([
                'role' => 'Only GSO officers can manage inventory operations.',
            ]);
        }
    }

    private function recordStatus(
        string $referenceType,
        int $referenceId,
        ?string $oldStatus,
        string $newStatus,
        User $actor,
        string $remarks,
    ): void {
        StatusHistory::query()->create([
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => (int) $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function recordAudit(User $actor, string $actionType, Inventory $inventory, array $details): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Inventory.Management',
            actionType: $actionType,
            referenceType: Inventory::class,
            referenceId: (int) $inventory->inventory_id,
            details: $details,
            logDomain: 'BUSINESS',
            officeId: $actor->office_id,
        );
    }

    /**
     * Determine whether a delivery represents general warehouse stock / proactive replenishment,
     * rather than an office-targeted delivery earmarked for delivery handover.
     */
    public function isGeneralInventoryDelivery(Delivery $delivery): bool
    {
        $deliveryId = (int) $delivery->delivery_id;
        if ($deliveryId > 0 && isset($this->generalInventoryDeliveryCache[$deliveryId])) {
            return $this->generalInventoryDeliveryCache[$deliveryId];
        }

        $po = $delivery->purchaseOrder;
        if (! $po) {
            if ($deliveryId > 0) {
                $this->generalInventoryDeliveryCache[$deliveryId] = true;
            }

            return true;
        }

        $pr = $po->purchaseRequest;
        if ($pr) {
            if ((string) ($pr->source_type ?? '') === 'LOW_STOCK') {
                if ($deliveryId > 0) {
                    $this->generalInventoryDeliveryCache[$deliveryId] = true;
                }

                return true;
            }
            $prRemarks = (string) ($pr->remarks ?? '');
            if (str_contains($prRemarks, 'LOW_STOCK_SIGNAL:inventory:') || str_contains($prRemarks, 'Proactive GSO Demand Forecast Signal')) {
                if ($deliveryId > 0) {
                    $this->generalInventoryDeliveryCache[$deliveryId] = true;
                }

                return true;
            }
        }

        $directSr = $po->supplyRequest ?? $pr?->supplyRequest;
        if ($directSr instanceof SupplyRequest) {
            $directSr->loadMissing('office');
            $officeCode = strtoupper(trim((string) ($directSr->office?->code ?? '')));
            $purpose = (string) ($directSr->purpose ?? '');
            if ($officeCode !== '' && $officeCode !== 'GSO' && ! str_contains($purpose, 'Proactive GSO Demand Forecast Signal')) {
                if ($deliveryId > 0) {
                    $this->generalInventoryDeliveryCache[$deliveryId] = false;
                }

                return false;
            }
        }

        $sourceSupplyRequests = collect();
        if ($po->supplyRequest instanceof SupplyRequest) {
            $sourceSupplyRequests->push($po->supplyRequest);
        }
        if ($pr?->supplyRequest instanceof SupplyRequest) {
            $sourceSupplyRequests->push($pr->supplyRequest);
        }

        $consolidated = $this->traceabilityService->getSourceSupplyRequests($po);
        if ($consolidated->isNotEmpty()) {
            $sourceSupplyRequests = $sourceSupplyRequests->merge($consolidated);
        } elseif ($pr) {
            $consolidatedPr = $this->traceabilityService->getSourceSupplyRequests($pr);
            if ($consolidatedPr->isNotEmpty()) {
                $sourceSupplyRequests = $sourceSupplyRequests->merge($consolidatedPr);
            }
        }

        $sourceSupplyRequests = $sourceSupplyRequests->unique('supply_request_id')->values();

        if ($sourceSupplyRequests->isEmpty()) {
            if ($deliveryId > 0) {
                $this->generalInventoryDeliveryCache[$deliveryId] = true;
            }

            return true;
        }

        $hasNonGsoClientRequest = false;
        foreach ($sourceSupplyRequests as $sr) {
            if (! $sr instanceof SupplyRequest) {
                continue;
            }
            $sr->loadMissing('office');
            $officeCode = strtoupper(trim((string) ($sr->office?->code ?? '')));
            $purpose = (string) ($sr->purpose ?? '');
            if ($officeCode === 'GSO' || str_contains($purpose, 'Proactive GSO Demand Forecast Signal')) {
                continue;
            }
            $hasNonGsoClientRequest = true;
            break;
        }

        $result = ! $hasNonGsoClientRequest;
        if ($deliveryId > 0) {
            $this->generalInventoryDeliveryCache[$deliveryId] = $result;
        }

        return $result;
    }

    /**
     * Get the total delivered stock earmarked for specific office distribution handovers.
     *
     * @param  int  $itemId
     * @param  array<int>|int|null  $excludeDeliveryIds  Delivery ID(s) to exclude
     * @return float
     */
    public function getUndistributedDeliveryStock(int $itemId, array|int|null $excludeDeliveryIds = null): float
    {
        $excludeIds = is_array($excludeDeliveryIds)
            ? array_filter(array_map('intval', $excludeDeliveryIds))
            : ($excludeDeliveryIds !== null ? [(int) $excludeDeliveryIds] : []);
        sort($excludeIds);
        $cacheKey = $itemId . '_' . implode(',', $excludeIds);

        if (isset($this->undistributedStockCache[$cacheKey])) {
            return $this->undistributedStockCache[$cacheKey];
        }

        $undistributed = (float) DeliveryItem::query()
            ->whereHas('purchaseOrderItem', fn ($q) => $q->where('item_id', $itemId))
            ->whereHas('delivery', fn ($q) => $q->whereIn('receipt_status', ['VERIFIED', 'PARTIALLY_RECEIVED']))
            ->when(! empty($excludeIds), fn ($q) => $q->whereNotIn('delivery_id', $excludeIds))
            ->with([
                'delivery.purchaseOrder.purchaseRequest.supplyRequest.office',
                'delivery.purchaseOrder.supplyRequest.office',
                'delivery.distributionRecords.items',
            ])
            ->get()
            ->sum(function (DeliveryItem $di) use ($itemId): float {
                $delivery = $di->delivery;
                if (! $delivery || $this->isGeneralInventoryDelivery($delivery)) {
                    return 0.0;
                }

                $accepted = max((float) $di->received_quantity - (float) $di->damaged_quantity, 0.0);
                $released = (float) ($delivery->distributionRecords ?? collect())
                    ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                    ->flatMap(fn ($dr) => $dr->items)
                    ->where('item_id', $itemId)
                    ->sum('quantity_released');

                $unreleasedInDelivery = max($accepted - $released, 0.0);
                if ($unreleasedInDelivery <= 0.0001) {
                    return 0.0;
                }

                $sourceSupplyRequests = collect();
                if ($delivery->purchaseOrder?->supplyRequest instanceof SupplyRequest) {
                    $sourceSupplyRequests->push($delivery->purchaseOrder->supplyRequest);
                }
                if ($delivery->purchaseOrder?->purchaseRequest?->supplyRequest instanceof SupplyRequest) {
                    $sourceSupplyRequests->push($delivery->purchaseOrder->purchaseRequest->supplyRequest);
                }
                if ($delivery->purchaseOrder) {
                    $consolidated = $this->traceabilityService->getSourceSupplyRequests($delivery->purchaseOrder);
                    if ($consolidated->isNotEmpty()) {
                        $sourceSupplyRequests = $sourceSupplyRequests->merge($consolidated);
                    }
                }
                $sourceSupplyRequests = $sourceSupplyRequests->unique('supply_request_id')->values();

                $totalOfficeRemainingNeeded = 0.0;
                foreach ($sourceSupplyRequests as $sr) {
                    if (! $sr instanceof SupplyRequest) {
                        continue;
                    }
                    $officeCode = strtoupper(trim((string) ($sr->office?->code ?? '')));
                    if ($officeCode === 'GSO') {
                        continue;
                    }
                    if (in_array((string) $sr->status, ['CANCELLED', 'REJECTED'], true)) {
                        continue;
                    }

                    $sr->loadMissing(['items', 'distributionRecords.items']);
                    $itemRequested = (float) $sr->items
                        ->where('item_id', $itemId)
                        ->sum('quantity_requested');

                    $alreadyReleasedToOffice = (float) $sr->distributionRecords
                        ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                        ->flatMap(fn ($dr) => $dr->items)
                        ->where('item_id', $itemId)
                        ->sum('quantity_released');

                    $totalOfficeRemainingNeeded += max($itemRequested - $alreadyReleasedToOffice, 0.0);
                }

                return min($unreleasedInDelivery, $totalOfficeRemainingNeeded);
            });

        $this->undistributedStockCache[$cacheKey] = $undistributed;

        return $undistributed;
    }

    /**
     * Get the true free available stock for an item in the warehouse,
     * deducting undistributed delivery stock from other requests.
     *
     * @param  int  $itemId
     * @param  SupplyRequest|null  $supplyRequest  If provided, exclude deliveries belonging to this supply request's lineage
     * @param  array<int>|int|null  $excludeDeliveryIds  Additional delivery ID(s) to exclude
     * @return float
     */
    public function getAvailableStockForSupplyRequest(
        int $itemId,
        ?SupplyRequest $supplyRequest = null,
        array|int|null $excludeDeliveryIds = null,
    ): float {
        $excludeIds = is_array($excludeDeliveryIds)
            ? array_filter(array_map('intval', $excludeDeliveryIds))
            : ($excludeDeliveryIds !== null ? [(int) $excludeDeliveryIds] : []);

        if ($supplyRequest instanceof SupplyRequest) {
            $lineageDeliveryIds = $this->followUpService->resolveDeliveryIdsForSupplyRequest($supplyRequest);
            $excludeIds = array_values(array_unique(array_merge($excludeIds, $lineageDeliveryIds)));
        }

        $undistributed = $this->getUndistributedDeliveryStock($itemId, $excludeIds);

        if (! array_key_exists($itemId, $this->inventoryByItemCache)) {
            $this->inventoryByItemCache[$itemId] = Inventory::query()->where('item_id', $itemId)->first();
        }
        $inventory = $this->inventoryByItemCache[$itemId];

        if (! $inventory) {
            return 0.0;
        }

        $reservedPortion = 0.0;
        $hasPr = $supplyRequest instanceof SupplyRequest && (
            $supplyRequest->relationLoaded('purchaseRequests')
                ? $supplyRequest->purchaseRequests->isNotEmpty()
                : $supplyRequest->purchaseRequests()->exists()
        );

        if ($hasPr) {
            $supplyRequest->load(['items', 'distributionRecords.items']);
            $itemRequested = (float) $supplyRequest->items
                ->where('item_id', $itemId)
                ->sum('quantity_requested');

            $alreadyReleasedInv = (float) $supplyRequest->distributionRecords
                ->whereNull('delivery_id')
                ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                ->flatMap(fn ($dr) => $dr->items)
                ->where('item_id', $itemId)
                ->sum('quantity_released');

            $remainingNeeded = max($itemRequested - $alreadyReleasedInv, 0.0);
            $reservedPortion = min($remainingNeeded, (float) $inventory->reserved_quantity);
        }

        $freeStock = max((float) $inventory->available_stock - $undistributed, 0.0);

        return $freeStock + $reservedPortion;
    }
}

