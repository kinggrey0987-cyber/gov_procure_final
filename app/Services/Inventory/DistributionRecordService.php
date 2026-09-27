<?php

namespace App\Services\Inventory;

use App\Models\ApprovalHistory;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\Procurement\ProcurementStatusSyncService;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributionRecordService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
        private readonly ProcurementStatusSyncService $procurementStatusSyncService,
        private readonly SupplyRequestFollowUpService $followUpService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createRecord(User $actor, array $payload): DistributionRecord
    {
        $this->assertGsoOfficer($actor);

        $releaseDate = !empty($payload['release_date'])
            ? Carbon::parse($payload['release_date'])
            : now();

        $distributionCode = !empty($payload['distribution_code'])
            ? trim((string) $payload['distribution_code'])
            : $this->generateNextDistributionCode($releaseDate);

        return DB::transaction(function () use ($actor, $payload, $distributionCode): DistributionRecord {
            $record = DistributionRecord::query()->create([
                'distribution_code' => $distributionCode,
                'supply_request_id' => (int) $payload['supply_request_id'],
                'delivery_id' => isset($payload['delivery_id']) ? (int) $payload['delivery_id'] : null,
                'office_id' => (int) $payload['office_id'],
                'prepared_by' => (int) $actor->id,
                'received_by' => $payload['received_by'] ?? null,
                'release_date' => $payload['release_date'] ?? null,
                'storage_location' => $payload['storage_location'] ?? null,
                'status' => 'DRAFT',
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->assertContextConsistency($record);

            $this->recordStatus($record, null, 'DRAFT', $actor, 'Distribution record created.');
            $this->recordAudit($actor, 'CREATE', $record, []);

            return $record->fresh(['supplyRequest', 'delivery', 'office', 'preparer', 'receiver']) ?? $record;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createRecordWithAutomaticRelease(User $actor, array $payload): DistributionRecord
    {
        return DB::transaction(function () use ($actor, $payload): DistributionRecord {
            $record = $this->createRecord($actor, $payload);
            $releaseItems = $this->automaticReleaseItems($record);

            if ($releaseItems === []) {
                throw ValidationException::withMessages([
                    'supply_request_id' => 'No requested item currently has a releasable quantity.',
                ]);
            }

            foreach ($releaseItems as $itemPayload) {
                $this->addDistributionItem($record->fresh() ?? $record, $actor, $itemPayload);
            }

            return $record->fresh(['supplyRequest', 'delivery', 'office', 'items.item']) ?? $record;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateRecord(DistributionRecord $record, User $actor, array $payload): DistributionRecord
    {
        $this->assertGsoOfficer($actor);

        return DB::transaction(function () use ($record, $actor, $payload): DistributionRecord {
            $record->fill([
                'received_by' => $payload['received_by'] ?? $record->received_by,
                'release_date' => $payload['release_date'] ?? $record->release_date,
                'storage_location' => $payload['storage_location'] ?? $record->storage_location,
                'notes' => $payload['notes'] ?? $record->notes,
            ])->save();

            $this->recordAudit($actor, 'UPDATE', $record, []);

            return $record->fresh(['supplyRequest', 'delivery', 'office', 'preparer', 'receiver']) ?? $record;
        });
    }

    /**
     * @param array<int, array<string, mixed>> $reconciliations
     */
    public function reconcileOfficeReceipt(DistributionRecord $record, User $actor, array $reconciliations, ?string $remarks = null): DistributionRecord
    {
        if ($actor->isOfficeUser() && (int) $record->office_id !== (int) $actor->office_id) {
            throw ValidationException::withMessages([
                'office_id' => 'You can only reconcile distribution records for your own office.',
            ]);
        }

        if (! $actor->isOfficeUser() && ! $actor->isGsoOfficer()) {
            throw ValidationException::withMessages([
                'role' => 'Only office users or GSO officers can reconcile office receipt discrepancies.',
            ]);
        }

        if ((string) $record->status !== 'RELEASED') {
            throw ValidationException::withMessages([
                'status' => 'Only RELEASED distribution records can be reconciled during office receipt.',
            ]);
        }

        return DB::transaction(function () use ($record, $actor, $reconciliations, $remarks): DistributionRecord {
            foreach ($reconciliations as $row) {
                $item = DistributionItem::query()
                    ->where('distribution_record_id', (int) $record->distribution_record_id)
                    ->where('distribution_item_id', (int) $row['distribution_item_id'])
                    ->firstOrFail();

                $released = (float) $item->quantity_released;
                $received = (int) ($row['quantity_received'] ?? 0);
                $damaged = (int) ($row['quantity_damaged'] ?? 0);
                $missing = (int) ($row['quantity_missing'] ?? 0);

                if (round($received + $damaged + $missing, 2) !== round($released, 2)) {
                    throw ValidationException::withMessages([
                        'item_reconciliations' => 'Office reconciliation quantities must total the released quantity for each item.',
                    ]);
                }

                $item->forceFill([
                    'received_by' => (int) $actor->id,
                    'quantity_received' => $received,
                    'quantity_damaged' => $damaged,
                    'quantity_missing' => $missing,
                    'receipt_condition' => $row['receipt_condition'] ?? ($damaged > 0 || $missing > 0 ? 'DISCREPANCY' : 'GOOD'),
                    'receipt_notes' => $row['receipt_notes'] ?? null,
                ])->save();
            }

            $oldStatus = (string) $record->status;
            $record->forceFill([
                'status' => 'RECEIVED',
                'received_by' => (int) $actor->id,
                'notes' => $remarks ?? $record->notes,
            ])->save();

            $this->recordStatus($record, $oldStatus, 'RECEIVED', $actor, 'Distribution record reconciled and acknowledged by office.');
            $this->recordApproval($record, $actor, 'RECEIVED', 'Office receipt reconciliation recorded.');
            $this->recordAudit($actor, 'RECONCILE_RECEIPT', $record, []);
            $this->procurementStatusSyncService->syncCompletedStatusesForReceivedSupplyRequest($record->supplyRequest()->firstOrFail(), $actor);

            return $record->fresh(['office', 'receiver', 'items.item']) ?? $record;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addDistributionItem(DistributionRecord $record, User $actor, array $payload): DistributionItem
    {
        $this->assertGsoOfficer($actor);

        return DB::transaction(function () use ($record, $actor, $payload): DistributionItem {
            $releasedQuantity = (float) $payload['quantity_released'];
            $this->assertDistributionQuantityIsReleasable($record, (int) $payload['item_id'], $releasedQuantity);

            $this->inventoryService->releaseStock(
                itemId: (int) $payload['item_id'],
                quantity: $releasedQuantity,
                actor: $actor,
                referenceType: DistributionRecord::class,
                referenceId: (int) $record->distribution_record_id,
                remarks: 'Stock released for distribution.',
            );

            $item = $record->items()->create([
                'item_id' => (int) $payload['item_id'],
                'received_by' => $payload['received_by'] ?? $record->received_by,
                'item_name_snapshot' => $payload['item_name_snapshot'],
                'quantity_released' => $releasedQuantity,
                'unit' => $payload['unit'],
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $releasedNow = false;

            if ((string) $record->status === 'DRAFT') {
                $oldStatus = (string) $record->status;
                $record->forceFill([
                    'status' => 'RELEASED',
                    'release_date' => $record->release_date ?? now()->toDateString(),
                ])->save();

                $this->recordStatus($record, $oldStatus, 'RELEASED', $actor, 'Distribution record released.');
                $this->recordApproval($record, $actor, 'RELEASED', 'Distribution released by GSO.');
                $releasedNow = true;
            }

            $this->recordAudit($actor, 'ADD_ITEM', $record, [
                'distribution_item_id' => (int) $item->distribution_item_id,
                'item_id' => (int) $item->item_id,
                'quantity_released' => (float) $item->quantity_released,
            ]);

            if ($releasedNow) {
                $this->notificationService->notifyDistributionOfficeRecipient(
                    record: $record,
                    title: 'Supplies Released for Distribution',
                    message: sprintf('GSO released supplies for %s. They are ready for your office to claim.', (string) $record->supplyRequest?->request_code),
                    notificationType: 'DISTRIBUTION_RELEASED',
                );
            }

            return $item->fresh(['distributionRecord']) ?? $item;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateDistributionItem(
        DistributionRecord $record,
        DistributionItem $item,
        User $actor,
        array $payload,
    ): DistributionItem {
        $this->assertGsoOfficer($actor);

        if ((int) $item->distribution_record_id !== (int) $record->distribution_record_id) {
            throw ValidationException::withMessages([
                'distribution_item_id' => 'Distribution item does not belong to the selected record.',
            ]);
        }

        return DB::transaction(function () use ($record, $item, $actor, $payload): DistributionItem {
            $currentQuantity = (float) $item->quantity_released;
            $newQuantity = (float) ($payload['quantity_released'] ?? $item->quantity_released);
            $delta = $newQuantity - $currentQuantity;
            $this->assertDistributionQuantityIsReleasable(
                $record,
                (int) $item->item_id,
                $newQuantity,
                (int) $item->distribution_item_id,
            );

            if ($delta > 0) {
                $this->inventoryService->releaseStock(
                    itemId: (int) $item->item_id,
                    quantity: $delta,
                    actor: $actor,
                    referenceType: DistributionRecord::class,
                    referenceId: (int) $record->distribution_record_id,
                    remarks: 'Additional release after distribution item update.',
                );
            }

            if ($delta < 0) {
                $this->inventoryService->restoreReleasedStock(
                    itemId: (int) $item->item_id,
                    quantity: abs($delta),
                    actor: $actor,
                    referenceType: DistributionRecord::class,
                    referenceId: (int) $record->distribution_record_id,
                    remarks: 'Stock restored after distribution item quantity reduction.',
                );
            }

            $item->fill([
                'received_by' => $payload['received_by'] ?? $item->received_by,
                'item_name_snapshot' => $payload['item_name_snapshot'] ?? $item->item_name_snapshot,
                'quantity_released' => $newQuantity,
                'unit' => $payload['unit'] ?? $item->unit,
                'remarks' => $payload['remarks'] ?? $item->remarks,
            ])->save();

            $this->recordAudit($actor, 'UPDATE_ITEM', $record, [
                'distribution_item_id' => (int) $item->distribution_item_id,
                'old_quantity' => $currentQuantity,
                'new_quantity' => $newQuantity,
            ]);

            return $item->fresh(['distributionRecord']) ?? $item;
        });
    }

    public function markReceivedByOffice(DistributionRecord $record, User $actor, ?string $remarks = null): DistributionRecord
    {
        if ($actor->isOfficeUser() && (int) $record->office_id !== (int) $actor->office_id) {
            throw ValidationException::withMessages([
                'office_id' => 'You can only receive distribution records for your own office.',
            ]);
        }

        if (!$actor->isOfficeUser() && !$actor->isGsoOfficer()) {
            throw ValidationException::withMessages([
                'role' => 'Only office users (own office) or GSO officers can mark distributions as received.',
            ]);
        }

        if ((string) $record->status !== 'RELEASED') {
            throw ValidationException::withMessages([
                'status' => 'Only RELEASED distribution records can be marked as received.',
            ]);
        }

        return DB::transaction(function () use ($record, $actor, $remarks): DistributionRecord {
            $oldStatus = (string) $record->status;

            $record->forceFill([
                'status' => 'RECEIVED',
                'received_by' => (int) $actor->id,
                'notes' => $remarks ?? $record->notes,
            ])->save();

            $record->loadMissing('items');
            foreach ($record->items as $item) {
                if ((float) $item->quantity_received <= 0) {
                    $item->forceFill([
                        'quantity_received' => (float) $item->quantity_released,
                        'received_by' => (int) $actor->id,
                        'receipt_condition' => $item->receipt_condition ?? 'GOOD',
                    ])->save();
                }
            }

            $this->recordStatus($record, $oldStatus, 'RECEIVED', $actor, 'Distribution record acknowledged as received.');
            $this->recordApproval($record, $actor, 'RECEIVED', 'Distribution received confirmation recorded.');
            $this->recordAudit($actor, 'MARK_RECEIVED', $record, []);
            $this->procurementStatusSyncService->syncCompletedStatusesForReceivedSupplyRequest($record->supplyRequest()->firstOrFail(), $actor);

            return $record->fresh(['office', 'receiver', 'items']) ?? $record;
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\DistributionRecord>
     */
    private function allFulfillmentDistributionRecords(\App\Models\SupplyRequest $supplyRequest): \Illuminate\Support\Collection
    {
        $supplyRequest->loadMissing('distributionRecords.items', 'distributionRecords.delivery.items.purchaseOrderItem');
        $directRecords = $supplyRequest->distributionRecords;
        $childIds = $this->followUpService->resolveAllFollowUpChildSupplyRequestIds($supplyRequest);

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
        return (float) \App\Models\DeliveryItem::query()
            ->whereHas('purchaseOrderItem', fn ($q) => $q->where('item_id', $itemId))
            ->whereHas('delivery', fn ($q) => $q->where('status', 'VERIFIED'))
            ->when($excludeDeliveryId !== null, fn ($q) => $q->where('delivery_id', '!=', $excludeDeliveryId))
            ->with(['delivery.distributionRecords.items'])
            ->get()
            ->sum(function (\App\Models\DeliveryItem $di) use ($itemId): float {
                $accepted = max((float) $di->received_quantity - (float) $di->damaged_quantity - (float) $di->missing_quantity, 0.0);
                $released = (float) ($di->delivery?->distributionRecords ?? collect())
                    ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                    ->flatMap(fn ($dr) => $dr->items)
                    ->where('item_id', $itemId)
                    ->sum('quantity_released');

                return max($accepted - $released, 0.0);
            });
    }

    private function calculateAlreadyReleasedInventory(\App\Models\SupplyRequest $supplyRequest, int $itemId, ?int $ignoreDistributionItemId = null): float
    {
        $allDistributions = $this->allFulfillmentDistributionRecords($supplyRequest)
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED']);

        return (float) $allDistributions->sum(function (DistributionRecord $dr) use ($itemId, $ignoreDistributionItemId): float {
            $releasedInDr = (float) $dr->items
                ->filter(function ($item) use ($itemId, $ignoreDistributionItemId): bool {
                    if ((int) $item->item_id !== $itemId) {
                        return false;
                    }

                    return $ignoreDistributionItemId === null
                        || (int) $item->distribution_item_id !== $ignoreDistributionItemId;
                })
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

    private function assertContextConsistency(DistributionRecord $record): void
    {
        $record->loadMissing('supplyRequest.office', 'supplyRequest.purchaseRequests', 'supplyRequest.distributionRecords.items', 'delivery.purchaseOrder');

        if ((string) ($record->supplyRequest->office?->code ?? '') === 'GSO') {
            throw ValidationException::withMessages([
                'supply_request_id' => 'GSO-origin supply requests are internal logistics records and are not eligible for office distribution release.',
            ]);
        }

        $isProcurementBacked = $record->supplyRequest->purchaseRequests->isNotEmpty();
        $hasDeliveryContext = $record->delivery_id !== null;
        $shortageTotal = 0.0;
        $releasedPool = $this->allFulfillmentDistributionRecords($record->supplyRequest)
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
            ->flatMap(fn ($distributionRecord) => $distributionRecord->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($items): float => (float) $items->sum('quantity_released'));
        $hasRemainingQuantity = false;
        $hasAvailableWarehouseStock = false;

        if ($isProcurementBacked && ! $hasDeliveryContext) {
            $record->supplyRequest->loadMissing('purchaseOrders.items');
        }

        foreach ($record->supplyRequest->items as $item) {
            $itemId = (int) $item->item_id;
            $requested = (float) $item->quantity_requested;
            $availReleased = (float) ($releasedPool->get($itemId) ?? 0.0);
            $released = min($requested, $availReleased);
            $releasedPool->put($itemId, max($availReleased - $released, 0.0));

            $remaining = max($requested - $released, 0.0);
            $inv = $item->item?->inventory;
            $undistributed = $this->undistributedDeliveryStock($itemId, $record->delivery_id);
            $available = max((float) ($inv?->available_stock ?? 0) + (float) ($inv?->reserved_quantity ?? 0) - $undistributed, 0.0);
            $shortageTotal += max($remaining - $available, 0.0);
            $hasRemainingQuantity = $hasRemainingQuantity || $remaining > 0.0001;

            $releasableStock = $available;
            if ($isProcurementBacked && ! $hasDeliveryContext) {
                $totalOrdered = (float) $record->supplyRequest->purchaseOrders
                    ->flatMap(fn ($po) => $po->items)
                    ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                    ->sum('quantity_ordered');
                $inventoryPortion = max($requested - $totalOrdered, 0.0);
                $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($record->supplyRequest, $itemId);
                $remainingInventoryPortion = max($inventoryPortion - $alreadyReleasedInventory, 0.0);
                $releasableStock = min($remainingInventoryPortion, $available);
            }

            if ($remaining > 0.0001 && $releasableStock > 0.0001) {
                $hasAvailableWarehouseStock = true;
            }
        }

        if (! $hasRemainingQuantity) {
            throw ValidationException::withMessages([
                'supply_request_id' => 'All requested quantities have already been released for this request.',
            ]);
        }

        if (! $hasDeliveryContext && ! $hasAvailableWarehouseStock) {
            throw ValidationException::withMessages([
                'supply_request_id' => 'This request has no available warehouse stock to release. Queue this request for procurement instead.',
            ]);
        }

        if (! $hasDeliveryContext) {
            if ((int) $record->supplyRequest->office_id !== (int) $record->office_id) {
                throw ValidationException::withMessages([
                    'office_id' => 'Distribution office must match the linked supply request office.',
                ]);
            }

            return;
        }

        $deliverySupplyRequestId = (int) ($record->delivery?->purchaseOrder?->supply_request_id ?? 0);
        if ($deliverySupplyRequestId !== (int) $record->supply_request_id) {
            throw ValidationException::withMessages([
                'supply_request_id' => 'Distribution record supply request must match the delivery purchase order context.',
            ]);
        }

        if ((int) $record->supplyRequest->office_id !== (int) $record->office_id) {
            throw ValidationException::withMessages([
                'office_id' => 'Distribution office must match the linked supply request office.',
            ]);
        }
    }

    private function assertDistributionQuantityIsReleasable(
        DistributionRecord $record,
        int $itemId,
        float $quantity,
        ?int $ignoreDistributionItemId = null,
    ): void {
        $record->load([
            'supplyRequest.items',
            'supplyRequest.distributionRecords.items',
            'delivery.items.purchaseOrderItem',
            'delivery.distributionRecords.items',
        ]);

        $requestedQuantity = (float) $record->supplyRequest->items
            ->where('item_id', $itemId)
            ->sum('quantity_requested');

        if ($requestedQuantity <= 0) {
            throw ValidationException::withMessages([
                'item_id' => 'Selected item is not part of the linked supply request.',
            ]);
        }

        $releasedForRequest = $this->allFulfillmentDistributionRecords($record->supplyRequest)
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
            ->flatMap(fn ($distributionRecord) => $distributionRecord->items)
            ->filter(function ($distributionItem) use ($itemId, $ignoreDistributionItemId): bool {
                if ((int) $distributionItem->item_id !== $itemId) {
                    return false;
                }

                return $ignoreDistributionItemId === null
                    || (int) $distributionItem->distribution_item_id !== $ignoreDistributionItemId;
            })
            ->sum(fn ($distributionItem): float => (float) $distributionItem->quantity_released);

        $maxReleasable = max($requestedQuantity - (float) $releasedForRequest, 0);
        $undistributed = $this->undistributedDeliveryStock($itemId, $record->delivery_id ? (int) $record->delivery_id : null);

        if ($record->delivery_id !== null && $record->delivery !== null) {
            $isItemInDelivery = $record->delivery->items->contains(
                fn ($deliveryItem): bool => (int) ($deliveryItem->purchaseOrderItem?->item_id ?? 0) === $itemId
            );

            if ($isItemInDelivery) {
                $acceptedFromDelivery = $record->delivery->items
                    ->filter(fn ($deliveryItem): bool => (int) ($deliveryItem->purchaseOrderItem?->item_id ?? 0) === $itemId)
                    ->sum(fn ($deliveryItem): float => max((float) $deliveryItem->received_quantity - (float) $deliveryItem->damaged_quantity - (float) $deliveryItem->missing_quantity, 0));

                $releasedForDelivery = $record->delivery->distributionRecords
                    ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                    ->flatMap(fn ($distributionRecord) => $distributionRecord->items)
                    ->filter(function ($distributionItem) use ($itemId, $ignoreDistributionItemId): bool {
                        if ((int) $distributionItem->item_id !== $itemId) {
                            return false;
                        }

                        return $ignoreDistributionItemId === null
                            || (int) $distributionItem->distribution_item_id !== $ignoreDistributionItemId;
                    })
                    ->sum(fn ($distributionItem): float => (float) $distributionItem->quantity_released);

                $deliveryRemaining = max((float) $acceptedFromDelivery - (float) $releasedForDelivery, 0);

                $record->supplyRequest->loadMissing('purchaseOrders.items');
                $totalOrdered = (float) $record->supplyRequest->purchaseOrders
                    ->flatMap(fn ($po) => $po->items)
                    ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                    ->sum('quantity_ordered');

                $inventoryPortion = max($requestedQuantity - $totalOrdered, 0.0);
                $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($record->supplyRequest, $itemId, $ignoreDistributionItemId);
                $remainingInventoryPortion = max($inventoryPortion - $alreadyReleasedInventory, 0.0);

                $inv = Inventory::query()->where('item_id', $itemId)->first();
                $availableWarehouseStock = max((float) ($inv?->available_stock ?? 0) + (float) ($inv?->reserved_quantity ?? 0) - $undistributed, 0.0);
                $releasableInventory = min($remainingInventoryPortion, $availableWarehouseStock);

                $maxReleasable = min($maxReleasable, $deliveryRemaining + $releasableInventory);
            }
        } else {
            $inv = Inventory::query()->where('item_id', $itemId)->first();
            $availableWarehouseStock = max((float) ($inv?->available_stock ?? 0) + (float) ($inv?->reserved_quantity ?? 0) - $undistributed, 0.0);
            $maxReleasable = min($maxReleasable, $availableWarehouseStock);
        }

        if ($quantity - $maxReleasable > 0.0001) {
            throw ValidationException::withMessages([
                'quantity_released' => sprintf(
                    'Quantity released cannot exceed the remaining releasable quantity of %.2f for this request%s.',
                    $maxReleasable,
                    $record->delivery_id !== null ? ' and verified delivery/warehouse stock' : ' and available warehouse stock'
                ),
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function automaticReleaseItems(DistributionRecord $record): array
    {
        $record->load([
            'supplyRequest.items.item.inventory',
            'supplyRequest.distributionRecords.items',
            'supplyRequest.purchaseOrders.items',
            'delivery.items.purchaseOrderItem',
            'delivery.distributionRecords.items',
        ]);

        $requestReleasedByItemId = $this->allFulfillmentDistributionRecords($record->supplyRequest)
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
            ->flatMap(fn ($distributionRecord) => $distributionRecord->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($items): float => (float) $items->sum('quantity_released'));

        $deliveryRemainingByItemId = collect();
        if ($record->delivery_id !== null && $record->delivery !== null) {
            $deliveryAcceptedByItemId = $record->delivery->items
                ->groupBy(fn ($item) => (int) ($item->purchaseOrderItem?->item_id ?? 0))
                ->map(fn ($items): float => (float) $items->sum(
                    fn ($item): float => max((float) $item->received_quantity - (float) $item->damaged_quantity - (float) $item->missing_quantity, 0)
                ));

            $deliveryReleasedByItemId = $record->delivery->distributionRecords
                ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                ->flatMap(fn ($distributionRecord) => $distributionRecord->items)
                ->groupBy(fn ($item) => (int) $item->item_id)
                ->map(fn ($items): float => (float) $items->sum('quantity_released'));

            $deliveryRemainingByItemId = $deliveryAcceptedByItemId
                ->map(fn (float $accepted, int $itemId): float => max($accepted - (float) ($deliveryReleasedByItemId->get($itemId) ?? 0.0), 0));
        }

        return $record->supplyRequest->items
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(function ($items, int $itemId) use ($requestReleasedByItemId, $deliveryRemainingByItemId, $record): ?array {
                $first = $items->first();
                $requested = (float) $items->sum('quantity_requested');
                $requestRemaining = max($requested - (float) ($requestReleasedByItemId->get($itemId) ?? 0.0), 0);
                $availableStock = (float) ($first?->item?->inventory?->available_stock ?? 0);
                $reservedStock = (float) ($first?->item?->inventory?->reserved_quantity ?? 0);
                $releasableStock = $availableStock + $reservedStock;
                $quantity = min($requestRemaining, $releasableStock);

                $undistributed = $this->undistributedDeliveryStock($itemId, $record->delivery_id ? (int) $record->delivery_id : null);

                if ($record->delivery_id !== null && $record->delivery !== null) {
                    $isItemInDelivery = $record->delivery->items->contains(
                        fn ($di) => (int) ($di->purchaseOrderItem?->item_id ?? 0) === $itemId
                    );

                    if ($isItemInDelivery) {
                        $deliveryRemaining = (float) ($deliveryRemainingByItemId->get($itemId) ?? 0.0);
                        $deliveryPortion = min($requestRemaining, $deliveryRemaining);

                        $record->supplyRequest->loadMissing('purchaseOrders.items');
                        $totalOrdered = (float) $record->supplyRequest->purchaseOrders
                            ->flatMap(fn ($po) => $po->items)
                            ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                            ->sum('quantity_ordered');
                        $inventoryPortion = max($requested - $totalOrdered, 0.0);

                        $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($record->supplyRequest, $itemId);
                        $remainingInventoryPortion = max($inventoryPortion - $alreadyReleasedInventory, 0.0);

                        $availableWarehouseStock = max((float) $releasableStock - (float) $deliveryRemaining - $undistributed, 0.0);
                        $releasableInventory = min($remainingInventoryPortion, $availableWarehouseStock);

                        $quantity = min($requestRemaining, $deliveryPortion + $releasableInventory);
                    }
                } elseif ($record->delivery_id === null && $record->supplyRequest->purchaseRequests->isNotEmpty()) {
                    $record->supplyRequest->loadMissing('purchaseOrders.items');
                    $totalOrdered = (float) $record->supplyRequest->purchaseOrders
                        ->flatMap(fn ($po) => $po->items)
                        ->filter(fn ($poi) => (int) $poi->item_id === $itemId)
                        ->sum('quantity_ordered');
                    $inventoryPortion = max($requested - $totalOrdered, 0.0);
                    $alreadyReleasedInventory = $this->calculateAlreadyReleasedInventory($record->supplyRequest, $itemId);
                    $remainingInventoryPortion = max($inventoryPortion - $alreadyReleasedInventory, 0.0);

                    $availableWarehouseStock = max((float) $releasableStock - $undistributed, 0.0);
                    $quantity = min($requestRemaining, min($remainingInventoryPortion, $availableWarehouseStock));
                }

                if ($quantity <= 0.0001) {
                    return null;
                }

                return [
                    'item_id' => $itemId,
                    'item_name_snapshot' => (string) ($first?->item?->item_name ?? $first?->item_name_snapshot ?? 'Unknown Item'),
                    'quantity_released' => $quantity,
                    'unit' => (string) ($first?->item?->default_unit ?? 'UNIT'),
                    'remarks' => sprintf('Automatically released against %s.', (string) $record->supplyRequest->request_code),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function generateNextDistributionCode(?DateTimeInterface $date = null): string
    {



        $targetDate = $date ?? now();
        $prefix = sprintf('DIST-%s-', $targetDate->format('Y-m'));

        $codes = DistributionRecord::query()
            ->where('distribution_code', 'like', $prefix.'%')
            ->pluck('distribution_code');

        $maxSequence = 0;
        foreach ($codes as $code) {
            if (preg_match('/^DIST-\d{4}-\d{2}-(\d+)$/', (string) $code, $matches) === 1) {
                $maxSequence = max($maxSequence, (int) $matches[1]);
            }
        }

        $nextSequence = $maxSequence + 1;
        do {
            $candidate = sprintf('%s%04d', $prefix, $nextSequence);
            $nextSequence++;
        } while (DistributionRecord::query()->where('distribution_code', $candidate)->exists());

        return $candidate;
    }

    private function generateDistributionCode(?DateTimeInterface $date = null): string
    {
        return $this->generateNextDistributionCode($date);
    }

    private function assertGsoOfficer(User $actor): void
    {
        if (!$actor->isGsoOfficer()) {
            throw ValidationException::withMessages([
                'role' => 'Only GSO officers can manage distribution operations.',
            ]);
        }
    }

    private function recordStatus(
        DistributionRecord $record,
        ?string $oldStatus,
        string $newStatus,
        User $actor,
        string $remarks,
    ): void {
        StatusHistory::query()->create([
            'reference_type' => DistributionRecord::class,
            'reference_id' => (int) $record->distribution_record_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => (int) $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    private function recordApproval(DistributionRecord $record, User $actor, string $status, string $remarks): void
    {
        ApprovalHistory::query()->create([
            'reference_type' => DistributionRecord::class,
            'reference_id' => (int) $record->distribution_record_id,
            'approved_by' => (int) $actor->id,
            'status' => $status,
            'remarks' => $remarks,
            'approved_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function recordAudit(User $actor, string $actionType, DistributionRecord $record, array $details): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Distribution.Record',
            actionType: $actionType,
            referenceType: DistributionRecord::class,
            referenceId: (int) $record->distribution_record_id,
            details: array_merge([
                'distribution_code' => $record->distribution_code,
                'status' => $record->status,
                'office_id' => $record->office_id,
            ], $details),
            logDomain: 'BUSINESS',
            officeId: $record->office_id,
        );
    }
}
