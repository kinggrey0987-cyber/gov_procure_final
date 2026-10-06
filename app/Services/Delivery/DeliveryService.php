<?php

namespace App\Services\Delivery;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): Delivery
    {
        $this->assertGsoOfficer($actor);

        $purchaseOrder = PurchaseOrder::query()
            ->where('purchase_order_id', (int) $payload['purchase_order_id'])
            ->firstOrFail();

        if (! in_array((string) $purchaseOrder->status, ['PLACED', 'PARTIALLY_DELIVERED'], true)) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'Only PLACED or PARTIALLY_DELIVERED purchase orders can receive deliveries.',
            ]);
        }

        if ((int) $purchaseOrder->supplier_id !== (int) $payload['supplier_id']) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Delivery supplier must match the supplier linked to the purchase order.',
            ]);
        }

        return DB::transaction(function () use ($actor, $payload, $purchaseOrder): Delivery {
            // Generate delivery code if not provided
            $targetDate = !empty($payload['actual_delivery_date'])
                ? Carbon::parse($payload['actual_delivery_date'])
                : (!empty($payload['expected_arrival_date']) ? Carbon::parse($payload['expected_arrival_date']) : now());

            $deliveryCode = !empty($payload['delivery_code'])
                ? trim((string) $payload['delivery_code'])
                : $this->generateNextDeliveryCode($targetDate);

            // Compute batch tracking
            $existingBatchesCount = Delivery::query()
                ->where('purchase_order_id', (int) $purchaseOrder->purchase_order_id)
                ->count();
            $maxBatch = (int) Delivery::query()
                ->where('purchase_order_id', (int) $purchaseOrder->purchase_order_id)
                ->max('batch_number');

            $batchNumber = isset($payload['batch_number']) && (int) $payload['batch_number'] > 0
                ? (int) $payload['batch_number']
                : ($maxBatch > 0 ? $maxBatch + 1 : 1);

            $deliveryScheduleType = $payload['delivery_schedule_type']
                ?? ($existingBatchesCount > 0 || (isset($payload['total_batches']) && (int) $payload['total_batches'] > 1) ? 'STAGGERED_BATCH' : 'SINGLE_DELIVERY');

            $totalBatches = isset($payload['total_batches']) && (int) $payload['total_batches'] > 0
                ? (int) $payload['total_batches']
                : null;

            $isFinalBatch = isset($payload['is_final_batch'])
                ? (bool) $payload['is_final_batch']
                : ($deliveryScheduleType === 'SINGLE_DELIVERY' || ($totalBatches !== null && $batchNumber >= $totalBatches));

            $delivery = Delivery::query()->create([
                'delivery_code' => $deliveryCode,
                'purchase_order_id' => (int) $purchaseOrder->purchase_order_id,
                'supplier_id' => (int) $payload['supplier_id'],
                'batch_number' => $batchNumber,
                'total_batches' => $totalBatches,
                'delivery_schedule_type' => $deliveryScheduleType,
                'is_final_batch' => $isFinalBatch,
                'actual_delivery_date' => $payload['actual_delivery_date'] ?? null,
                'expected_arrival_date' => $payload['expected_arrival_date'] ?? null,
                'received_by' => null,
                'receipt_status' => 'PENDING',
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $payloadItems = collect($payload['items'] ?? [])->keyBy('purchase_order_item_id');

            $purchaseOrder->items()->each(function (PurchaseOrderItem $purchaseOrderItem) use ($delivery, $payloadItems): void {
                $poItemId = (int) $purchaseOrderItem->purchase_order_item_id;

                if ($payloadItems->has($poItemId)) {
                    $expectedQty = (float) $payloadItems->get($poItemId)['expected_quantity'];
                } else {
                    $priorReceivedQty = (float) DeliveryItem::query()
                        ->where('purchase_order_item_id', $poItemId)
                        ->whereHas('delivery', function ($q) use ($delivery) {
                            $q->where('purchase_order_id', (int) $delivery->purchase_order_id)
                                ->where('delivery_id', '!=', (int) $delivery->delivery_id);
                        })
                        ->sum('received_quantity');

                    $remainingQty = max(0.0, (float) $purchaseOrderItem->quantity_ordered - $priorReceivedQty);
                    $expectedQty = $priorReceivedQty > 0 ? $remainingQty : (float) $purchaseOrderItem->quantity_ordered;
                }

                $delivery->items()->create([
                    'purchase_order_item_id' => $poItemId,
                    'expected_quantity' => $expectedQty,
                    'received_quantity' => 0,
                    'damaged_quantity' => 0,
                    'missing_quantity' => 0,
                    'item_condition' => 'PENDING_RECEIPT',
                    'remarks' => sprintf('Batch #%d auto-populated item line for receiving inspection.', (int) $delivery->batch_number),
                ]);
            });

            $this->recordStatus($delivery, null, 'PENDING', $actor, sprintf('Delivery created (%s).', (string) $delivery->batch_label));
            $this->recordAudit($actor, 'CREATE', $delivery);

            $purchaseOrder->loadMissing('supplyRequest');
            if ($purchaseOrder->supplyRequest) {
                $this->notificationService->notifySupplyRequestOfficeRequester(
                    supplyRequest: $purchaseOrder->supplyRequest,
                    title: 'Delivery Intake Created',
                    message: sprintf('GSO created a delivery intake for your supply request %s.', (string) $purchaseOrder->supplyRequest->request_code),
                    notificationType: 'DELIVERY_CREATED',
                );
            }

            return $delivery->fresh(['purchaseOrder', 'supplier', 'receiver', 'items']) ?? $delivery;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateReceipt(Delivery $delivery, User $actor, array $payload): Delivery
    {
        $this->assertGsoOfficer($actor);

        if (array_key_exists('items', $payload)) {
            if (! array_key_exists('actual_delivery_date', $payload) || empty($payload['actual_delivery_date'])) {
                throw ValidationException::withMessages([
                    'actual_delivery_date' => 'Actual delivery date is required before completing receipt.',
                ]);
            }

            if ($delivery->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'items' => 'Delivery cannot be received without purchase order item lines.',
                ]);
            }
        }

        return DB::transaction(function () use ($delivery, $actor, $payload): Delivery {
            if (array_key_exists('items', $payload)) {
                foreach ((array) $payload['items'] as $itemPayload) {
                    $item = DeliveryItem::query()
                        ->where('delivery_id', (int) $delivery->delivery_id)
                        ->where('delivery_item_id', (int) ($itemPayload['delivery_item_id'] ?? 0))
                        ->first();

                    if (! $item instanceof DeliveryItem) {
                        continue;
                    }

                    $receivedQuantity = (int) ($itemPayload['received_quantity'] ?? 0);
                    $damagedQuantity = (int) ($itemPayload['damaged_quantity'] ?? 0);
                    $expectedQuantity = isset($itemPayload['expected_quantity']) && (float) $itemPayload['expected_quantity'] > 0
                        ? (float) $itemPayload['expected_quantity']
                        : (float) $item->expected_quantity;
                    $missingQuantity = max($expectedQuantity - $receivedQuantity, 0);

                    if ($receivedQuantity < 0 || $damagedQuantity < 0 || $receivedQuantity > $expectedQuantity || $damagedQuantity > $receivedQuantity) {
                        throw ValidationException::withMessages([
                            'items' => 'Received quantity cannot exceed expected quantity, and damaged quantity cannot exceed received quantity.',
                        ]);
                    }

                    $itemCondition = match (true) {
                        $damagedQuantity > 0 => 'DAMAGED',
                        $missingQuantity > 0 => 'MISSING',
                        default => 'GOOD',
                    };

                    $item->forceFill([
                        'expected_quantity' => $expectedQuantity,
                        'received_quantity' => $receivedQuantity,
                        'damaged_quantity' => $damagedQuantity,
                        'missing_quantity' => $missingQuantity,
                        'item_condition' => $itemCondition,
                    ])->save();
                }
            }

            $delivery->fill([
                'actual_delivery_date' => $payload['actual_delivery_date'] ?? $delivery->actual_delivery_date,
                'expected_arrival_date' => $payload['expected_arrival_date'] ?? $delivery->expected_arrival_date,
                'delivery_receipt_no' => $payload['delivery_receipt_no'] ?? $delivery->delivery_receipt_no,
                'sales_invoice_no' => !empty(trim((string) ($payload['sales_invoice_no'] ?? '')))
                    ? $payload['sales_invoice_no']
                    : (($delivery->purchaseOrder?->is_zero_cost_replacement ?? false) ? 'N/A (Warranty Replacement)' : $delivery->sales_invoice_no),
                'sales_invoice_date' => $payload['sales_invoice_date'] ?? $delivery->sales_invoice_date,
                'sales_invoice_amount' => ($delivery->purchaseOrder?->is_zero_cost_replacement ?? false)
                    ? 0.00
                    : (array_key_exists('sales_invoice_amount', $payload) && $payload['sales_invoice_amount'] !== null && $payload['sales_invoice_amount'] !== ''
                        ? (float) $payload['sales_invoice_amount']
                        : $delivery->sales_invoice_amount),
                'remarks' => $payload['remarks'] ?? $delivery->remarks,
            ])->save();

            $this->recordStatus($delivery, (string) $delivery->receipt_status, (string) $delivery->receipt_status, $actor, 'Delivery receipt updated.');
            $this->recordAudit($actor, 'UPDATE', $delivery);

            return $delivery->fresh(['purchaseOrder', 'supplier', 'receiver', 'items']) ?? $delivery;
        });
    }

    public function ensureItemsFromPurchaseOrder(Delivery $delivery): Delivery
    {
        $delivery->loadMissing('purchaseOrder.items');

        if (! $delivery->purchaseOrder instanceof PurchaseOrder) {
            return $delivery;
        }

        DB::transaction(function () use ($delivery): void {
            foreach ($delivery->purchaseOrder->items as $purchaseOrderItem) {
                if (! $purchaseOrderItem instanceof PurchaseOrderItem) {
                    continue;
                }

                $poItemId = (int) $purchaseOrderItem->purchase_order_item_id;

                $priorReceivedQty = (float) DeliveryItem::query()
                    ->where('purchase_order_item_id', $poItemId)
                    ->whereHas('delivery', function ($q) use ($delivery) {
                        $q->where('purchase_order_id', (int) $delivery->purchase_order_id)
                            ->where('delivery_id', '!=', (int) $delivery->delivery_id);
                    })
                    ->sum('received_quantity');

                $remainingQty = max(0.0, (float) $purchaseOrderItem->quantity_ordered - $priorReceivedQty);
                $expectedQty = $priorReceivedQty > 0 ? $remainingQty : (float) $purchaseOrderItem->quantity_ordered;

                $delivery->items()->firstOrCreate(
                    ['purchase_order_item_id' => $poItemId],
                    [
                        'expected_quantity' => $expectedQty,
                        'received_quantity' => 0,
                        'damaged_quantity' => 0,
                        'missing_quantity' => 0,
                        'item_condition' => 'PENDING_RECEIPT',
                        'remarks' => sprintf('Batch #%d auto-populated item line for receiving inspection.', (int) ($delivery->batch_number ?? 1)),
                    ],
                );
            }
        });

        return $delivery->fresh(['purchaseOrder', 'supplier', 'receiver', 'items.purchaseOrderItem.item']) ?? $delivery;
    }

    public function markReceived(Delivery $delivery, User $actor, ?string $remarks = null): Delivery
    {
        $this->assertGsoOfficer($actor);

        if ((string) $delivery->receipt_status !== 'PENDING') {
            throw ValidationException::withMessages([
                'receipt_status' => 'Only PENDING deliveries can be marked as received.',
            ]);
        }

        if (! $delivery->actual_delivery_date) {
            throw ValidationException::withMessages([
                'actual_delivery_date' => 'Actual delivery date is required before completing receipt.',
            ]);
        }

        if ($delivery->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => 'Delivery cannot be received without purchase order item lines.',
            ]);
        }

        return DB::transaction(function () use ($delivery, $actor, $remarks): Delivery {
            $oldStatus = (string) $delivery->receipt_status;

            $delivery->forceFill([
                'received_by' => (int) $actor->id,
                'receipt_status' => 'RECEIVED',
                'remarks' => $remarks ?? $delivery->remarks,
            ])->save();

            $this->recordStatus($delivery, $oldStatus, 'RECEIVED', $actor, 'Delivery marked as received.');
            $this->recordAudit($actor, 'MARK_RECEIVED', $delivery);

            return $delivery->fresh(['purchaseOrder', 'supplier', 'receiver', 'items']) ?? $delivery;
        });
    }

    public function generateNextDeliveryCode(?DateTimeInterface $date = null): string
    {



        $targetDate = $date ?? now();
        $prefix = sprintf('DEL-%s-', $targetDate->format('Y-m'));

        $codes = Delivery::query()
            ->where('delivery_code', 'like', $prefix.'%')
            ->pluck('delivery_code');

        $maxSequence = 0;
        foreach ($codes as $code) {
            if (preg_match('/^DEL-\d{4}-\d{2}-(\d+)$/', (string) $code, $matches) === 1) {
                $maxSequence = max($maxSequence, (int) $matches[1]);
            }
        }

        $nextSequence = $maxSequence + 1;
        do {
            $candidate = sprintf('%s%04d', $prefix, $nextSequence);
            $nextSequence++;
        } while (Delivery::query()->where('delivery_code', $candidate)->exists());

        return $candidate;
    }

    private function generateDeliveryCode(?DateTimeInterface $date = null): string
    {
        return $this->generateNextDeliveryCode($date);
    }

    private function assertGsoOfficer(User $actor): void
    {
        if (!$actor->isGsoOfficer()) {
            throw ValidationException::withMessages([
                'role' => 'Only GSO officers can manage deliveries.',
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
            moduleName: 'Delivery.Delivery',
            actionType: $actionType,
            referenceType: Delivery::class,
            referenceId: (int) $delivery->delivery_id,
            details: [
                'delivery_code' => $delivery->delivery_code,
                'purchase_order_id' => $delivery->purchase_order_id,
                'supplier_id' => $delivery->supplier_id,
                'batch_number' => $delivery->batch_number,
                'total_batches' => $delivery->total_batches,
                'delivery_schedule_type' => $delivery->delivery_schedule_type,
                'is_final_batch' => $delivery->is_final_batch,
                'receipt_status' => $delivery->receipt_status,
            ],
            logDomain: 'BUSINESS',
            officeId: $delivery->purchaseOrder?->supplyRequest?->office_id
                ?? $delivery->purchaseOrder?->purchaseRequest?->office_id
                ?? $delivery->purchaseOrder?->purchaseRequest?->supplyRequest?->office_id
                ?? null,
        );
    }
}
