<?php

namespace App\Services\Procurement;

use App\Enums\UserRole;
use App\Models\ApprovalHistory;
use App\Models\Delivery;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\SupplyRequestItem;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\Analytics\AnomalyService;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
        private readonly AnomalyService $anomalyService,
        private readonly ConsolidationTraceabilityService $traceabilityService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): PurchaseOrder
    {
        $this->assertProcurementActor($actor);

        $supplier = Supplier::query()->where('supplier_id', (int) $payload['supplier_id'])->firstOrFail();
        $this->assertSupplierIsAllowed($supplier);

        return DB::transaction(function () use ($actor, $payload): PurchaseOrder {
            $purchaseRequest = PurchaseRequest::query()
                ->lockForUpdate()
                ->with(['items.item', 'supplyRequest.items', 'sourceDelivery.purchaseOrder', 'sourceDelivery.supplier'])
                ->where('purchase_request_id', (int) $payload['purchase_request_id'])
                ->firstOrFail();

            $this->assertPurchaseRequestCanCreatePurchaseOrder($purchaseRequest);

            $isReplacement = (string) $purchaseRequest->source_type === 'PARTIAL_DELIVERY_FOLLOW_UP'
                || (int) ($purchaseRequest->source_delivery_id ?? 0) > 0;

            $supplierId = (int) $payload['supplier_id'];
            if ($isReplacement && $purchaseRequest->sourceDelivery) {
                $supplierId = (int) (
                    $purchaseRequest->sourceDelivery->supplier_id
                    ?? $purchaseRequest->sourceDelivery->purchaseOrder?->supplier_id
                    ?? $supplierId
                );
            }

            $remarks = $payload['remarks'] ?? null;
            if ($isReplacement && empty($remarks)) {
                $delCode = $purchaseRequest->sourceDelivery?->delivery_code ?? 'N/A';
                $parentPo = $purchaseRequest->sourceDelivery?->purchaseOrder?->po_number ?? 'N/A';
                $remarks = sprintf('Zero-cost warranty replacement purchase order for delivery %s (PO %s).', $delCode, $parentPo);
            }

            $procurementMethod = $payload['procurement_method'] ?? ($isReplacement ? 'DIRECT_CONTRACTING' : 'SHOPPING');

            $purchaseOrder = PurchaseOrder::query()->create([
                'purchase_request_id' => (int) $payload['purchase_request_id'],
                'supply_request_id' => (int) $purchaseRequest->supply_request_id,
                'supplier_id' => $supplierId,
                'created_by' => (int) $actor->id,
                'po_number' => $this->generateNextPoNumber(),
                'procurement_method' => $procurementMethod,
                'status' => 'DRAFT',
                'expected_delivery_date' => $payload['expected_delivery_date'] ?? null,
                'total_amount' => 0,
                'terms_notes' => $payload['terms_notes'] ?? ($isReplacement ? 'Zero-cost supplier warranty rectification under RA 9184.' : null),
                'remarks' => $remarks,
            ]);

            // If this is a warranty replacement follow-up, auto-populate the items at ₱0.00
            if ($isReplacement && $purchaseRequest->items->isNotEmpty()) {
                foreach ($purchaseRequest->items as $prItem) {
                    $qty = max((int) $prItem->quantity_requested, 1);
                    $catalogItem = $prItem->item;
                    $itemName = (string) ($catalogItem?->item_name ?? 'Replacement Item');
                    $unit = (string) ($catalogItem?->default_unit ?? 'piece');

                    $supplyRequestItem = $purchaseRequest->supplyRequest?->items
                        ?->firstWhere('item_id', $prItem->item_id);

                    $purchaseOrder->items()->create([
                        'supply_request_item_id' => (int) ($supplyRequestItem?->supply_request_item_id ?? 1),
                        'item_id' => (int) $prItem->item_id,
                        'item_name_snapshot' => $itemName,
                        'specification' => 'Replacement item under supplier warranty rectification',
                        'quantity_ordered' => $qty,
                        'unit' => $unit,
                        'unit_price' => 0.00,
                        'total_price' => 0.00,
                    ]);
                }

                $purchaseOrder->forceFill(['total_amount' => 0.00])->save();
            }

            $this->recordStatus($purchaseOrder, null, 'DRAFT', $actor, $isReplacement ? 'Zero-cost replacement purchase order created.' : 'Purchase order created.');
            $this->recordAudit($actor, 'CREATE', $purchaseOrder);

            $purchaseRequest->loadMissing('supplyRequest');
            if ($purchaseRequest->supplyRequest) {
                $this->notificationService->notifySupplyRequestOfficeRequester(
                    supplyRequest: $purchaseRequest->supplyRequest,
                    title: 'Purchase Order Drafted',
                    message: sprintf('Procurement drafted PO %s for your supply request %s.', (string) $purchaseOrder->po_number, (string) $purchaseRequest->supplyRequest->request_code),
                    notificationType: 'PROCUREMENT_PURCHASE_ORDER_DRAFTED',
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

        $sourceSupplyRequests = $this->traceabilityService->getSourceSupplyRequests($purchaseOrder);
        if ($sourceSupplyRequests->isEmpty() && $purchaseOrder->supplyRequest) {
            $sourceSupplyRequests = collect([$purchaseOrder->supplyRequest]);
        }

        $purchaseRequestItems = $purchaseOrder->purchaseRequest?->items ?? collect();
        $validItemIds = $purchaseRequestItems->pluck('item_id')->map(fn ($id) => (int) $id)->all();

        $allSourceItemIds = collect();
        foreach ($sourceSupplyRequests as $sr) {
            $sr->loadMissing('items');
            foreach ($sr->items ?? [] as $srItem) {
                if ($validItemIds !== [] && ! in_array((int) $srItem->item_id, $validItemIds, true)) {
                    continue;
                }
                $allSourceItemIds->push((int) $srItem->supply_request_item_id);
            }
        }

        if ($purchaseOrder->is_zero_cost_replacement) {
            if ($purchaseOrder->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'items' => 'Warranty replacement purchase order must have items to place.',
                ]);
            }
        } elseif ($purchaseOrder->purchaseRequest && $purchaseOrder->purchaseRequest->items()->exists()) {
            $requiredItemIds = $purchaseOrder->purchaseRequest->items()->pluck('item_id')->map(fn ($id) => (int) $id)->unique();
            $encodedItemIds = $purchaseOrder->items()->pluck('item_id')->map(fn ($id) => (int) $id)->unique();
            $missingItemIds = $requiredItemIds->diff($encodedItemIds);

            if ($missingItemIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => sprintf(
                        'All %d approved item(s) from the purchase request must be encoded before issuing the purchase order (%d item line(s) remaining).',
                        $requiredItemIds->count(),
                        $missingItemIds->count()
                    ),
                ]);
            }
        } elseif ($allSourceItemIds->isNotEmpty()) {
            $encodedItemIds = $purchaseOrder->items()
                ->whereNotNull('supply_request_item_id')
                ->where('supply_request_item_id', '>', 0)
                ->pluck('supply_request_item_id')
                ->map(fn ($id) => (int) $id)
                ->unique();

            $missingItemIds = $allSourceItemIds->diff($encodedItemIds);

            if ($missingItemIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => sprintf(
                        'All %d source request items must be encoded before issuing the purchase order (%d item line(s) remaining).',
                        $allSourceItemIds->count(),
                        $missingItemIds->count()
                    ),
                ]);
            }
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
                'approved_by' => (int) $actor->id,
                'date_approved' => now()->toDateString(),
                'remarks' => $remarks ?? $purchaseOrder->remarks,
            ])->save();

            $this->recordStatus($purchaseOrder, $oldStatus, 'PLACED', $actor, 'Purchase order placed.');
            $this->recordAudit($actor, 'PLACE', $purchaseOrder);

            $delivery = Delivery::query()
                ->where('purchase_order_id', (int) $purchaseOrder->purchase_order_id)
                ->first();

            if (! $delivery instanceof Delivery) {
                $delivery = Delivery::query()->create([
                    'delivery_code' => $this->generateAutoDeliveryCode(),
                    'purchase_order_id' => (int) $purchaseOrder->purchase_order_id,
                    'supplier_id' => (int) $purchaseOrder->supplier_id,
                    'actual_delivery_date' => null,
                    'expected_arrival_date' => $purchaseOrder->expected_delivery_date,
                    'received_by' => null,
                    'receipt_status' => 'PENDING',
                    'remarks' => 'Auto-created when purchase order was placed.',
                ]);

                StatusHistory::query()->create([
                    'reference_type' => Delivery::class,
                    'reference_id' => (int) $delivery->delivery_id,
                    'old_status' => null,
                    'new_status' => 'PENDING',
                    'changed_by' => (int) $actor->id,
                    'remarks' => 'Delivery intake auto-created from placed purchase order.',
                    'changed_at' => now(),
                ]);

                $gsoOfficers = User::query()
                    ->whereHas('role', fn ($query) => $query->where('code', UserRole::GSO_OFFICER->value))
                    ->get();

                if ($gsoOfficers->isNotEmpty()) {
                    $this->notificationService->notifyUsers(
                        users: $gsoOfficers,
                        title: 'New Delivery Intake Ready',
                        message: sprintf('PO %s was placed and is now queued for receiving.', (string) $purchaseOrder->po_number),
                        notificationType: 'DELIVERY_INTAKE_READY',
                        referenceType: Delivery::class,
                        referenceId: (int) $delivery->delivery_id,
                    );
                }
            }

            $sourceSupplyRequests = $this->traceabilityService->getSourceSupplyRequests($purchaseOrder);
            if ($sourceSupplyRequests->isNotEmpty()) {
                foreach ($sourceSupplyRequests as $sr) {
                    $this->notificationService->notifySupplyRequestOfficeRequester(
                        supplyRequest: $sr,
                        title: 'Purchase Order Placed',
                        message: sprintf('PO %s for your supply request %s was placed and queued for GSO receiving.', (string) $purchaseOrder->po_number, (string) $sr->request_code),
                        notificationType: 'PROCUREMENT_PURCHASE_ORDER_PLACED',
                    );
                }
            } else {
                $purchaseOrder->loadMissing('supplyRequest');
                if ($purchaseOrder->supplyRequest) {
                    $this->notificationService->notifySupplyRequestOfficeRequester(
                        supplyRequest: $purchaseOrder->supplyRequest,
                        title: 'Purchase Order Placed',
                        message: sprintf('PO %s for your supply request %s was placed and queued for GSO receiving.', (string) $purchaseOrder->po_number, (string) $purchaseOrder->supplyRequest->request_code),
                        notificationType: 'PROCUREMENT_PURCHASE_ORDER_PLACED',
                    );
                }
            }

            $purchaseOrder->loadMissing('items');
            foreach ($purchaseOrder->items as $purchaseOrderItem) {
                if (! $purchaseOrderItem instanceof PurchaseOrderItem) {
                    continue;
                }

                $delivery->items()->firstOrCreate(
                    ['purchase_order_item_id' => (int) $purchaseOrderItem->purchase_order_item_id],
                    [
                        'expected_quantity' => (float) $purchaseOrderItem->quantity_ordered,
                        'received_quantity' => 0,
                        'damaged_quantity' => 0,
                        'missing_quantity' => 0,
                        'item_condition' => 'PENDING_RECEIPT',
                        'remarks' => 'Auto-populated from purchase order line for receiving inspection.',
                    ],
                );
            }

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

        if ($purchaseOrder->is_zero_cost_replacement) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'Cannot add items to a zero-cost warranty replacement purchase order. Items are auto-populated from the delivery discrepancy.',
            ]);
        }

        $supplyRequestItem = SupplyRequestItem::query()
            ->with('ppmpItem:ppmp_item_id,remarks')
            ->where('supply_request_item_id', $incomingSupplyRequestItemId)
            ->firstOrFail();

        $this->assertItemMatchesOrderContext($purchaseOrder, $supplyRequestItem);

        return DB::transaction(function () use ($purchaseOrder, $actor, $payload, $supplyRequestItem): PurchaseOrderItem {
            $purchaseRequestItem = $purchaseOrder->purchaseRequest?->items
                ?->firstWhere('item_id', $supplyRequestItem->item_id);
            $approvedShortage = $purchaseRequestItem ? (float) $purchaseRequestItem->quantity_requested : null;

            $quantity = isset($payload['quantity_ordered']) && (float) $payload['quantity_ordered'] > 0
                ? (float) $payload['quantity_ordered']
                : ($approvedShortage ?? max((int) $supplyRequestItem->quantity_requested, 1));

            $unitPrice = (float) $payload['unit_price'];
            $unit = trim((string) ($payload['unit'] ?? ($supplyRequestItem->item?->default_unit ?? '')));

            $item = $purchaseOrder->items()->create([
                'supply_request_item_id' => (int) $supplyRequestItem->supply_request_item_id,
                'item_id' => (int) $supplyRequestItem->item_id,
                'item_name_snapshot' => $supplyRequestItem->item_name_snapshot ?: ($supplyRequestItem->item?->item_name ?? 'Unknown item'),
                'specification' => $this->resolveItemSpecification($payload, $supplyRequestItem),
                'quantity_ordered' => $quantity,
                'unit' => $unit !== '' ? $unit : 'piece',
                'unit_price' => $unitPrice,
                'total_price' => round($quantity * $unitPrice, 2),
            ]);

            $this->recomputeTotalAmount($purchaseOrder);
            $this->recordStatus($purchaseOrder, (string) $purchaseOrder->status, (string) $purchaseOrder->status, $actor, 'Purchase order item added.');
            $this->recordAudit($actor, 'ADD_ITEM', $purchaseOrder);
            $this->createOrUpdatePriceZScoreAnomaly($item);

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

        if ($purchaseOrder->is_zero_cost_replacement) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'Cannot update items on a zero-cost warranty replacement purchase order.',
            ]);
        }

        $supplyRequestItem = SupplyRequestItem::query()
            ->with('ppmpItem:ppmp_item_id,remarks')
            ->where('supply_request_item_id', (int) $payload['supply_request_item_id'])
            ->firstOrFail();

        $this->assertItemMatchesOrderContext($purchaseOrder, $supplyRequestItem);

        return DB::transaction(function () use ($purchaseOrder, $item, $actor, $payload, $supplyRequestItem): PurchaseOrderItem {
            $purchaseRequestItem = $purchaseOrder->purchaseRequest?->items
                ?->firstWhere('item_id', $supplyRequestItem->item_id);
            $approvedShortage = $purchaseRequestItem ? (float) $purchaseRequestItem->quantity_requested : null;

            $quantity = isset($payload['quantity_ordered']) && (float) $payload['quantity_ordered'] > 0
                ? (float) $payload['quantity_ordered']
                : ($approvedShortage ?? max((int) $supplyRequestItem->quantity_requested, 1));

            $unitPrice = (float) $payload['unit_price'];
            $unit = trim((string) ($payload['unit'] ?? ($supplyRequestItem->item?->default_unit ?? '')));

            $item->fill([
                'supply_request_item_id' => (int) $supplyRequestItem->supply_request_item_id,
                'item_id' => (int) $supplyRequestItem->item_id,
                'item_name_snapshot' => $supplyRequestItem->item_name_snapshot ?: ($supplyRequestItem->item?->item_name ?? 'Unknown item'),
                'specification' => $this->resolveItemSpecification($payload, $supplyRequestItem),
                'quantity_ordered' => $quantity,
                'unit' => $unit !== '' ? $unit : 'piece',
                'unit_price' => $unitPrice,
                'total_price' => round($quantity * $unitPrice, 2),
            ])->save();

            $this->recomputeTotalAmount($purchaseOrder);
            $this->recordStatus($purchaseOrder, (string) $purchaseOrder->status, (string) $purchaseOrder->status, $actor, 'Purchase order item updated.');
            $this->recordAudit($actor, 'UPDATE_ITEM', $purchaseOrder);
            $this->createOrUpdatePriceZScoreAnomaly($item);

            return $item;
        });
    }

    public function removeItem(PurchaseOrder $purchaseOrder, PurchaseOrderItem $item, User $actor): void
    {
        $this->assertProcurementActor($actor);
        $this->assertOrderIsMutable($purchaseOrder);

        if ($purchaseOrder->is_zero_cost_replacement) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'Cannot remove items from a zero-cost warranty replacement purchase order.',
            ]);
        }

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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveItemSpecification(array $payload, SupplyRequestItem $supplyRequestItem): string
    {
        return trim((string) ($supplyRequestItem->ppmpItem?->remarks ?? ''));
    }

    private function recomputeTotalAmount(PurchaseOrder $purchaseOrder): void
    {
        $total = (float) $purchaseOrder->items()->sum('total_price');

        $purchaseOrder->forceFill([
            'total_amount' => round($total, 2),
        ])->save();
    }

    private function createOrUpdatePriceZScoreAnomaly(PurchaseOrderItem $purchaseOrderItem): void
    {
        if (! $purchaseOrderItem->item_id || (float) $purchaseOrderItem->unit_price <= 0) {
            return;
        }

        $rows = PurchaseOrderItem::query()
            ->with(['purchaseOrder.purchaseRequest:purchase_request_id,office_id', 'purchaseOrder.supplyRequest:supply_request_id,office_id'])
            ->where('item_id', (int) $purchaseOrderItem->item_id)
            ->whereHas('purchaseOrder')
            ->get(['purchase_order_item_id', 'purchase_order_id', 'item_id', 'unit_price']);

        $prices = $rows->pluck('unit_price')->map(fn ($value): float => (float) $value);

        if ($prices->count() < 3) {
            return;
        }

        $mean = $prices->avg();
        $variance = $prices->map(fn (float $price): float => ($price - $mean) ** 2)->avg();
        $std = sqrt((float) $variance);

        if ($std <= 0) {
            return;
        }

        $latest = $rows->sortByDesc('purchase_order_item_id')->first();

        if (! $latest instanceof PurchaseOrderItem) {
            return;
        }

        $latestPrice = (float) $latest->unit_price;
        $zScore = ($latestPrice - $mean) / $std;

        if (abs($zScore) < 2.0) {
            return;
        }

        $purchaseOrder = $latest->purchaseOrder;
        $officeId = (int) ($purchaseOrder?->purchaseRequest?->office_id ?? $purchaseOrder?->supplyRequest?->office_id ?? 0) ?: null;

        $this->anomalyService->createOrUpdateSystemDetected([
            'anomaly_type' => 'PRICE_Z_SCORE',
            'reference_type' => PurchaseOrderItem::class,
            'reference_id' => (int) $latest->purchase_order_item_id,
            'item_id' => (int) $latest->item_id,
            'office_id' => $officeId,
            'detected_value' => $latestPrice,
            'expected_value' => $mean,
            'threshold_value' => 2.0,
            'z_score' => round($zScore, 4),
            'detection_method' => 'Z_SCORE',
            'severity' => abs($zScore) >= 3 ? 'HIGH' : 'MEDIUM',
            'detected_at' => now(),
            'remarks' => 'Detected procurement-side price outlier when purchase order item pricing was encoded.',
        ]);
    }

    private function assertProcurementActor(User $actor): void
    {
        if (! $actor->isProcurement()) {
            throw ValidationException::withMessages([
                'role' => 'Only Procurement Unit users can perform this action.',
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

    private function assertPurchaseRequestCanCreatePurchaseOrder(PurchaseRequest $purchaseRequest): void
    {
        if ((string) $purchaseRequest->status !== 'PROCESSING') {
            throw ValidationException::withMessages([
                'purchase_request_id' => 'Only PROCESSING purchase requests can be used for purchase order creation.',
            ]);
        }

        $hasExistingPurchaseOrder = PurchaseOrder::query()
            ->where('purchase_request_id', (int) $purchaseRequest->purchase_request_id)
            ->exists();

        if ($hasExistingPurchaseOrder) {
            throw ValidationException::withMessages([
                'purchase_request_id' => 'This purchase request already has a purchase order and cannot be selected again.',
            ]);
        }
    }

    private function assertItemMatchesOrderContext(PurchaseOrder $purchaseOrder, SupplyRequestItem $supplyRequestItem): void
    {
        if ((int) $supplyRequestItem->supply_request_id === (int) $purchaseOrder->supply_request_id) {
            return;
        }

        $allowedSrIds = $this->traceabilityService
            ->getSourceSupplyRequests($purchaseOrder)
            ->pluck('supply_request_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! in_array((int) $supplyRequestItem->supply_request_id, $allowedSrIds, true)) {
            throw ValidationException::withMessages([
                'supply_request_item_id' => 'Supply request item does not belong to the selected supply request.',
            ]);
        }
    }

    public function generateNextPoNumber(?\DateTimeInterface $date = null): string
    {
        $targetDate = $date ?? now();
        $prefix = sprintf('PO-%s-', $targetDate->format('Y-m'));

        $latest = PurchaseOrder::query()
            ->where('po_number', 'like', $prefix.'%')
            ->orderByDesc('po_number')
            ->value('po_number');

        $nextSequence = 1;

        if (is_string($latest) && preg_match('/^PO-\d{4}-\d{2}-(\d+)$/', $latest, $matches) === 1) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = sprintf('%s%04d', $prefix, $nextSequence);
            $nextSequence++;
        } while (PurchaseOrder::query()->where('po_number', $candidate)->exists());

        return $candidate;
    }

    private function generateAutoDeliveryCode(): string
    {
        $lastDelivery = Delivery::query()->latest('delivery_id')->first();
        $nextNumber = ((int) ($lastDelivery?->delivery_id ?? 0)) + 1;

        return sprintf('DEL-%s-%05d', date('Y'), $nextNumber);
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
