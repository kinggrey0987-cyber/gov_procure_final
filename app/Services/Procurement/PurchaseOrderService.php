<?php

namespace App\Services\Procurement;

use App\Models\ApprovalHistory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\SupplyRequestItem;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): PurchaseOrder
    {
        $this->assertProcurementActor($actor);

        $purchaseRequest = PurchaseRequest::query()->where('purchase_request_id', (int) $payload['purchase_request_id'])->firstOrFail();

        if ((string) $purchaseRequest->status !== 'PROCESSING') {
            throw ValidationException::withMessages([
                'purchase_request_id' => 'Only PROCESSING purchase requests can be used for purchase order creation.',
            ]);
        }

        $supplier = Supplier::query()->where('supplier_id', (int) $payload['supplier_id'])->firstOrFail();
        $this->assertSupplierIsAllowed($supplier);

        return DB::transaction(function () use ($actor, $payload, $purchaseRequest): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()->create([
                'purchase_request_id' => (int) $payload['purchase_request_id'],
                'supply_request_id' => (int) $purchaseRequest->supply_request_id,
                'supplier_id' => (int) $payload['supplier_id'],
                'created_by' => (int) $actor->id,
                'po_number' => $this->generateNextPoNumber(),
                'procurement_method' => $payload['procurement_method'],
                'status' => 'DRAFT',
                'expected_delivery_date' => $payload['expected_delivery_date'] ?? null,
                'total_amount' => 0,
                'terms_notes' => $payload['terms_notes'] ?? null,
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $this->recordStatus($purchaseOrder, null, 'DRAFT', $actor, 'Purchase order created.');
            $this->recordAudit($actor, 'CREATE', $purchaseOrder);

            $requester = User::query()->find($purchaseRequest->requested_by);
            if ($requester instanceof User) {
                $this->notificationService->notifyUser(
                    user: $requester,
                    title: 'Purchase Order Drafted',
                    message: 'Procurement drafted a purchase order for your request.',
                    notificationType: 'PROCUREMENT_PURCHASE_ORDER_DRAFTED',
                    referenceType: PurchaseOrder::class,
                    referenceId: (int) $purchaseOrder->purchase_order_id,
                );
            }

            return $purchaseOrder->fresh(['supplier', 'creator', 'purchaseRequest', 'supplyRequest', 'items']) ?? $purchaseOrder;
        });
    }

    public function previewNextPoNumber(): string
    {
        return $this->generateNextPoNumber();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(PurchaseOrder $purchaseOrder, User $actor, array $payload): PurchaseOrder
    {
        $this->assertProcurementActor($actor);
        $this->assertOrderIsMutable($purchaseOrder);

        $supplier = Supplier::query()->where('supplier_id', (int) $payload['supplier_id'])->firstOrFail();
        $this->assertSupplierIsAllowed($supplier);

        return DB::transaction(function () use ($purchaseOrder, $actor, $payload): PurchaseOrder {
            $purchaseOrder->fill([
                'supplier_id' => (int) $payload['supplier_id'],
                'po_number' => $payload['po_number'],
                'procurement_method' => $payload['procurement_method'],
                'expected_delivery_date' => $payload['expected_delivery_date'] ?? null,
                'terms_notes' => $payload['terms_notes'] ?? null,
                'remarks' => $payload['remarks'] ?? $purchaseOrder->remarks,
            ])->save();

            $this->recordStatus($purchaseOrder, (string) $purchaseOrder->status, (string) $purchaseOrder->status, $actor, 'Purchase order updated.');
            $this->recordAudit($actor, 'UPDATE', $purchaseOrder);

            return $purchaseOrder->fresh(['supplier', 'creator', 'purchaseRequest', 'supplyRequest', 'items']) ?? $purchaseOrder;
        });
    }

    public function place(PurchaseOrder $purchaseOrder, User $actor, ?string $remarks = null): PurchaseOrder
    {
        $this->assertProcurementActor($actor);

        if ((string) $purchaseOrder->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'status' => 'Only DRAFT purchase orders can be placed.',
            ]);
        }

        if (! $purchaseOrder->items()->exists()) {
            throw ValidationException::withMessages([
                'items' => 'At least one purchase order item is required before placing.',
            ]);
        }

        return DB::transaction(function () use ($purchaseOrder, $actor, $remarks): PurchaseOrder {
            $oldStatus = (string) $purchaseOrder->status;

            $purchaseOrder->forceFill([
                'status' => 'PLACED',
                'remarks' => $remarks ?? $purchaseOrder->remarks,
            ])->save();

            $this->recordStatus($purchaseOrder, $oldStatus, 'PLACED', $actor, 'Purchase order placed.');
            $this->recordAudit($actor, 'PLACE', $purchaseOrder);

            ApprovalHistory::query()->create([
                'reference_type' => PurchaseOrder::class,
                'reference_id' => (int) $purchaseOrder->purchase_order_id,
                'approved_by' => null,
                'status' => 'PLACED',
                'remarks' => 'Purchase order placement recorded.',
                'approved_at' => null,
            ]);

            return $purchaseOrder->fresh(['supplier', 'creator', 'purchaseRequest', 'supplyRequest', 'items']) ?? $purchaseOrder;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addItem(PurchaseOrder $purchaseOrder, User $actor, array $payload): PurchaseOrderItem
    {
        $this->assertProcurementActor($actor);
        $this->assertOrderIsMutable($purchaseOrder);

        $incomingSupplyRequestItemId = (int) $payload['supply_request_item_id'];

        $alreadyExists = $purchaseOrder->items()
            ->where('supply_request_item_id', $incomingSupplyRequestItemId)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'supply_request_item_id' => 'This source request item is already encoded in the purchase order. Update the existing line instead of adding a duplicate.',
            ]);
        }

        $supplyRequestItem = SupplyRequestItem::query()
            ->where('supply_request_item_id', $incomingSupplyRequestItemId)
            ->firstOrFail();

        $this->assertItemMatchesOrderContext($purchaseOrder, $supplyRequestItem, (int) $payload['item_id']);

        return DB::transaction(function () use ($purchaseOrder, $actor, $payload): PurchaseOrderItem {
            $quantity = (float) $payload['quantity_ordered'];
            $unitPrice = (float) $payload['unit_price'];

            $item = $purchaseOrder->items()->create([
                'supply_request_item_id' => (int) $payload['supply_request_item_id'],
                'item_id' => (int) $payload['item_id'],
                'item_name_snapshot' => $payload['item_name_snapshot'],
                'specification' => $payload['specification'] ?? null,
                'quantity_ordered' => $quantity,
                'unit' => $payload['unit'],
                'unit_price' => $unitPrice,
                'total_price' => round($quantity * $unitPrice, 2),
            ]);

            $this->recomputeTotalAmount($purchaseOrder);
            $this->recordStatus($purchaseOrder, (string) $purchaseOrder->status, (string) $purchaseOrder->status, $actor, 'Purchase order item added.');
            $this->recordAudit($actor, 'ADD_ITEM', $purchaseOrder);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateItem(PurchaseOrder $purchaseOrder, PurchaseOrderItem $item, User $actor, array $payload): PurchaseOrderItem
    {
        $this->assertProcurementActor($actor);
        $this->assertOrderIsMutable($purchaseOrder);

        if ((int) $item->purchase_order_id !== (int) $purchaseOrder->purchase_order_id) {
            throw ValidationException::withMessages([
                'purchase_order_item_id' => 'Item does not belong to this purchase order.',
            ]);
        }

        $supplyRequestItem = SupplyRequestItem::query()
            ->where('supply_request_item_id', (int) $payload['supply_request_item_id'])
            ->firstOrFail();

        $this->assertItemMatchesOrderContext($purchaseOrder, $supplyRequestItem, (int) $payload['item_id']);

        return DB::transaction(function () use ($purchaseOrder, $item, $actor, $payload): PurchaseOrderItem {
            $quantity = (float) $payload['quantity_ordered'];
            $unitPrice = (float) $payload['unit_price'];

            $item->fill([
                'supply_request_item_id' => (int) $payload['supply_request_item_id'],
                'item_id' => (int) $payload['item_id'],
                'item_name_snapshot' => $payload['item_name_snapshot'],
                'specification' => $payload['specification'] ?? null,
                'quantity_ordered' => $quantity,
                'unit' => $payload['unit'],
                'unit_price' => $unitPrice,
                'total_price' => round($quantity * $unitPrice, 2),
            ])->save();

            $this->recomputeTotalAmount($purchaseOrder);
            $this->recordStatus($purchaseOrder, (string) $purchaseOrder->status, (string) $purchaseOrder->status, $actor, 'Purchase order item updated.');
            $this->recordAudit($actor, 'UPDATE_ITEM', $purchaseOrder);

            return $item;
        });
    }

    public function removeItem(PurchaseOrder $purchaseOrder, PurchaseOrderItem $item, User $actor): void
    {
        $this->assertProcurementActor($actor);
        $this->assertOrderIsMutable($purchaseOrder);

        if ((int) $item->purchase_order_id !== (int) $purchaseOrder->purchase_order_id) {
            throw ValidationException::withMessages([
                'purchase_order_item_id' => 'Item does not belong to this purchase order.',
            ]);
        }

        DB::transaction(function () use ($purchaseOrder, $item, $actor): void {
            $item->delete();

            $this->recomputeTotalAmount($purchaseOrder);
            $this->recordStatus($purchaseOrder, (string) $purchaseOrder->status, (string) $purchaseOrder->status, $actor, 'Purchase order item removed.');
            $this->recordAudit($actor, 'REMOVE_ITEM', $purchaseOrder);
        });
    }

    private function recomputeTotalAmount(PurchaseOrder $purchaseOrder): void
    {
        $total = (float) $purchaseOrder->items()->sum('total_price');

        $purchaseOrder->forceFill([
            'total_amount' => round($total, 2),
        ])->save();
    }

    private function assertProcurementActor(User $actor): void
    {
        if (! $actor->isProcurement()) {
            throw ValidationException::withMessages([
                'role' => 'Only procurement officers can perform this action.',
            ]);
        }
    }

    private function assertOrderIsMutable(PurchaseOrder $purchaseOrder): void
    {
        if ((string) $purchaseOrder->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'status' => 'Only DRAFT purchase orders can be modified.',
            ]);
        }
    }

    private function assertSupplierIsAllowed(Supplier $supplier): void
    {
        if ((string) $supplier->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'supplier_id' => 'Only ACTIVE suppliers can be used for purchase orders.',
            ]);
        }
    }

    private function assertItemMatchesOrderContext(PurchaseOrder $purchaseOrder, SupplyRequestItem $supplyRequestItem, int $itemId): void
    {
        if ((int) $supplyRequestItem->supply_request_id !== (int) $purchaseOrder->supply_request_id) {
            throw ValidationException::withMessages([
                'supply_request_item_id' => 'Supply request item does not belong to the selected supply request.',
            ]);
        }

        if ((int) $supplyRequestItem->item_id !== $itemId) {
            throw ValidationException::withMessages([
                'item_id' => 'Item must match the selected supply request item.',
            ]);
        }
    }

    private function generateNextPoNumber(): string
    {
        $latest = PurchaseOrder::query()
            ->where('po_number', 'like', 'PO-'.date('Y').'-%')
            ->latest('purchase_order_id')
            ->value('po_number');

        $nextSequence = 1;

        if (is_string($latest) && preg_match('/^PO-\d{4}-(\d+)$/', $latest, $matches) === 1) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        return sprintf('PO-%s-%05d', date('Y'), $nextSequence);
    }

    private function recordStatus(PurchaseOrder $purchaseOrder, ?string $oldStatus, string $newStatus, User $actor, string $remarks): void
    {
        StatusHistory::query()->create([
            'reference_type' => PurchaseOrder::class,
            'reference_id' => (int) $purchaseOrder->purchase_order_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => (int) $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    private function recordAudit(User $actor, string $actionType, PurchaseOrder $purchaseOrder): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Procurement.PurchaseOrder',
            actionType: $actionType,
            referenceType: PurchaseOrder::class,
            referenceId: (int) $purchaseOrder->purchase_order_id,
            details: [
                'purchase_request_id' => $purchaseOrder->purchase_request_id,
                'supply_request_id' => $purchaseOrder->supply_request_id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'status' => $purchaseOrder->status,
                'total_amount' => $purchaseOrder->total_amount,
            ],
            logDomain: 'BUSINESS',
            officeId: $actor->office_id,
        );
    }
}
