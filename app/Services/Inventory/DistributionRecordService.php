<?php

namespace App\Services\Inventory;

use App\Models\ApprovalHistory;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributionRecordService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createRecord(User $actor, array $payload): DistributionRecord
    {
        $this->assertGsoOfficer($actor);

        $distributionCode = $payload['distribution_code'] ?? $this->generateDistributionCode();

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

            if ((string) $record->status === 'DRAFT') {
                $oldStatus = (string) $record->status;
                $record->forceFill([
                    'status' => 'RELEASED',
                    'release_date' => $record->release_date ?? now()->toDateString(),
                ])->save();

                $this->recordStatus($record, $oldStatus, 'RELEASED', $actor, 'Distribution record released.');
                $this->recordApproval($record, $actor, 'RELEASED', 'Distribution released by GSO.');
            }

            $this->recordAudit($actor, 'ADD_ITEM', $record, [
                'distribution_item_id' => (int) $item->distribution_item_id,
                'item_id' => (int) $item->item_id,
                'quantity_released' => (float) $item->quantity_released,
            ]);

            $receiver = $record->receiver;
            if ($receiver instanceof User) {
                $this->notificationService->notifyUser(
                    user: $receiver,
                    title: 'Items Released',
                    message: 'Items were released for your office distribution record.',
                    notificationType: 'DISTRIBUTION_RELEASED',
                    referenceType: DistributionRecord::class,
                    referenceId: (int) $record->distribution_record_id,
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

            $this->recordStatus($record, $oldStatus, 'RECEIVED', $actor, 'Distribution record acknowledged as received.');
            $this->recordApproval($record, $actor, 'RECEIVED', 'Distribution received confirmation recorded.');
            $this->recordAudit($actor, 'MARK_RECEIVED', $record, []);

            return $record->fresh(['office', 'receiver']) ?? $record;
        });
    }

    private function assertContextConsistency(DistributionRecord $record): void
    {
        $record->loadMissing('supplyRequest.office', 'supplyRequest.purchaseRequests', 'delivery.purchaseOrder');

        if ((string) ($record->supplyRequest->office?->code ?? '') === 'GSO') {
            throw ValidationException::withMessages([
                'supply_request_id' => 'GSO-origin supply requests are internal logistics records and are not eligible for office distribution release.',
            ]);
        }

        $isProcurementBacked = $record->supplyRequest->purchaseRequests->isNotEmpty();
        $hasDeliveryContext = $record->delivery_id !== null;
        $shortageTotal = 0.0;

        foreach ($record->supplyRequest->items as $item) {
            $requested = (float) $item->quantity_requested;
            $available = (float) ($item->item?->inventory?->available_stock ?? 0);
            $shortageTotal += max($requested - $available, 0);
        }

        if (! $hasDeliveryContext && $isProcurementBacked && $shortageTotal > 0) {
            throw ValidationException::withMessages([
                'delivery_id' => 'Procurement-backed requests require a verified delivery context before distribution release.',
            ]);
        }

        if (! $hasDeliveryContext) {
            if ($shortageTotal > 0) {
                throw ValidationException::withMessages([
                    'supply_request_id' => 'This request has stock shortages and must be queued for procurement instead of direct inventory release.',
                ]);
            }

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

    private function generateDistributionCode(): string
    {
        $last = DistributionRecord::query()->latest('distribution_record_id')->first();
        $next = ((int) ($last?->distribution_record_id ?? 0)) + 1;

        return sprintf('DIST-%s-%05d', date('Y'), $next);
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
