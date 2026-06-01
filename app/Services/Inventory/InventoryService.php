<?php

namespace App\Services\Inventory;

use App\Models\Delivery;
use App\Models\Inventory;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        private readonly InventoryMovementService $movementService,
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
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

            $requester = $delivery->purchaseOrder?->supplyRequest?->requester;
            if ($requester instanceof User) {
                $this->notificationService->notifyUser(
                    user: $requester,
                    title: 'Inventory Updated',
                    message: (string) $delivery->receipt_status === 'PARTIALLY_RECEIVED'
                        ? 'Inventory has been updated from the accepted quantity of your partially received delivery.'
                        : 'Inventory has been updated from your verified delivery context.',
                    notificationType: 'INVENTORY_UPDATED',
                    referenceType: Delivery::class,
                    referenceId: (int) $delivery->delivery_id,
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

        return DB::transaction(function () use ($itemId, $quantity, $actor, $referenceType, $referenceId, $remarks): Inventory {
            /** @var Inventory|null $inventory */
            $inventory = Inventory::query()->where('item_id', $itemId)->lockForUpdate()->first();
            if (!$inventory instanceof Inventory) {
                throw ValidationException::withMessages([
                    'item_id' => 'No inventory record found for the selected item.',
                ]);
            }

            if ((float) $inventory->available_stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity_released' => 'Released quantity cannot exceed available stock.',
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
}
