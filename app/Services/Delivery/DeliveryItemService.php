<?php

namespace App\Services\Delivery;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\PurchaseOrderItem;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryItemService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addItem(Delivery $delivery, User $actor, array $payload): DeliveryItem
    {
        $this->assertGsoOfficer($actor);

        $purchaseOrderItem = PurchaseOrderItem::query()
            ->where('purchase_order_item_id', (int) $payload['purchase_order_item_id'])
            ->firstOrFail();

        if ((int) $purchaseOrderItem->purchase_order_id !== (int) $delivery->purchase_order_id) {
            throw ValidationException::withMessages([
                'purchase_order_item_id' => 'Purchase order item does not belong to the selected purchase order.',
            ]);
        }

        return DB::transaction(function () use ($delivery, $actor, $payload, $purchaseOrderItem): DeliveryItem {
            $expectedQuantity = (float) $purchaseOrderItem->quantity_ordered;
            $receivedQuantity = (float) ($payload['received_quantity'] ?? 0);
            $damagedQuantity = (float) ($payload['damaged_quantity'] ?? 0);
            $missingQuantity = (float) ($payload['missing_quantity'] ?? 0);

            $this->assertValidQuantities(
                expectedQuantity: $expectedQuantity,
                receivedQuantity: $receivedQuantity,
                damagedQuantity: $damagedQuantity,
                missingQuantity: $missingQuantity,
            );

            $item = $delivery->items()->create([
                'purchase_order_item_id' => (int) $purchaseOrderItem->purchase_order_item_id,
                'expected_quantity' => $expectedQuantity,
                'received_quantity' => $receivedQuantity,
                'damaged_quantity' => $damagedQuantity,
                'missing_quantity' => $missingQuantity,
                'item_condition' => $payload['item_condition'] ?? null,
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $this->recordStatus($delivery, (string) $delivery->receipt_status, (string) $delivery->receipt_status, $actor, 'Delivery item added.');
            $this->recordAudit($actor, 'ADD_ITEM', $delivery);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateItem(
        Delivery $delivery,
        DeliveryItem $item,
        User $actor,
        array $payload
    ): DeliveryItem {
        $this->assertGsoOfficer($actor);

        if ((int) $item->delivery_id !== (int) $delivery->delivery_id) {
            throw ValidationException::withMessages([
                'delivery_item_id' => 'Item does not belong to this delivery.',
            ]);
        }

        return DB::transaction(function () use ($delivery, $item, $actor, $payload): DeliveryItem {
            $expectedQuantity = (float) $item->expected_quantity;
            $receivedQuantity = (float) ($payload['received_quantity'] ?? $item->received_quantity);
            $damagedQuantity = (float) ($payload['damaged_quantity'] ?? $item->damaged_quantity);
            $missingQuantity = (float) ($payload['missing_quantity'] ?? $item->missing_quantity);

            $this->assertValidQuantities(
                expectedQuantity: $expectedQuantity,
                receivedQuantity: $receivedQuantity,
                damagedQuantity: $damagedQuantity,
                missingQuantity: $missingQuantity,
            );

            $item->fill([
                'received_quantity' => $receivedQuantity,
                'damaged_quantity' => $damagedQuantity,
                'missing_quantity' => $missingQuantity,
                'item_condition' => $payload['item_condition'] ?? $item->item_condition,
                'remarks' => $payload['remarks'] ?? $item->remarks,
            ])->save();

            $this->recordStatus($delivery, (string) $delivery->receipt_status, (string) $delivery->receipt_status, $actor, 'Delivery item updated.');
            $this->recordAudit($actor, 'UPDATE_ITEM', $delivery);

            return $item;
        });
    }

    private function assertGsoOfficer(User $actor): void
    {
        if (!$actor->isGsoOfficer()) {
            throw ValidationException::withMessages([
                'role' => 'Only GSO officers can manage delivery items.',
            ]);
        }
    }

    private function assertValidQuantities(
        float $expectedQuantity,
        float $receivedQuantity,
        float $damagedQuantity,
        float $missingQuantity,
    ): void {
        if ($receivedQuantity < 0 || $damagedQuantity < 0 || $missingQuantity < 0) {
            throw ValidationException::withMessages([
                'quantities' => 'Received, damaged, and missing quantities must be non-negative.',
            ]);
        }

        if (($receivedQuantity + $damagedQuantity + $missingQuantity) > $expectedQuantity) {
            throw ValidationException::withMessages([
                'quantities' => 'The sum of received, damaged, and missing quantities cannot exceed expected quantity.',
            ]);
        }
    }

    private function recordStatus(Delivery $delivery, ?string $oldStatus, string $newStatus, User $actor, string $remarks): void
    {
        StatusHistory::query()->create([
            'reference_type' => Delivery::class,
            'reference_id' => (int) $delivery->delivery_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => (int) $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    private function recordAudit(User $actor, string $actionType, Delivery $delivery): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Delivery.Item',
            actionType: $actionType,
            referenceType: Delivery::class,
            referenceId: (int) $delivery->delivery_id,
            details: [
                'delivery_code' => $delivery->delivery_code,
                'item_count' => $delivery->items()->count(),
            ],
            logDomain: 'BUSINESS',
            officeId: $delivery->purchaseOrder?->supplyRequest?->office_id
                ?? $delivery->purchaseOrder?->purchaseRequest?->office_id
                ?? $delivery->purchaseOrder?->purchaseRequest?->supplyRequest?->office_id
                ?? null,
        );
    }
}
