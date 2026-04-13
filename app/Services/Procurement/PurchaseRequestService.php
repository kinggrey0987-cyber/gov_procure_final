<?php

namespace App\Services\Procurement;

use App\Models\ApprovalHistory;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\StatusHistory;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createFromSupplyRequest(User $actor, array $payload): PurchaseRequest
    {
        $this->assertProcurementActor($actor);

        $supplyRequest = SupplyRequest::query()
            ->with('items')
            ->where('supply_request_id', (int) $payload['supply_request_id'])
            ->firstOrFail();

        if ((string) $supplyRequest->status !== 'SUBMITTED') {
            throw ValidationException::withMessages([
                'supply_request_id' => 'Only SUBMITTED supply requests can become purchase requests.',
            ]);
        }

        if (! $supplyRequest->items->isNotEmpty()) {
            throw ValidationException::withMessages([
                'supply_request_id' => 'Supply request must contain at least one item before conversion.',
            ]);
        }

        if (PurchaseRequest::query()->where('supply_request_id', $supplyRequest->supply_request_id)->exists()) {
            throw ValidationException::withMessages([
                'supply_request_id' => 'A purchase request already exists for this supply request.',
            ]);
        }

        return DB::transaction(function () use ($actor, $payload, $supplyRequest): PurchaseRequest {
            $purchaseRequest = PurchaseRequest::query()->create([
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'office_id' => (int) $supplyRequest->office_id,
                'requested_by' => (int) $supplyRequest->requested_by,
                'status' => 'OPEN',
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $itemRows = [];
            foreach ($supplyRequest->items as $supplyRequestItem) {
                $itemRows[] = [
                    'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                    'item_id' => (int) $supplyRequestItem->item_id,
                    'quantity_requested' => (float) $supplyRequestItem->quantity_requested,
                    'remarks' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($itemRows !== []) {
                PurchaseRequestItem::query()->insert($itemRows);
            }

            $this->recordStatus($purchaseRequest, null, 'OPEN', $actor, 'Purchase request created from submitted supply request.');
            $this->recordAudit($actor, 'CREATE', $purchaseRequest);

            $requester = User::query()->find($purchaseRequest->requested_by);
            if ($requester instanceof User) {
                $this->notificationService->notifyUser(
                    user: $requester,
                    title: 'Purchase Request Created',
                    message: 'Procurement has created a purchase request from your submitted supply request.',
                    notificationType: 'PROCUREMENT_PURCHASE_REQUEST_CREATED',
                    referenceType: PurchaseRequest::class,
                    referenceId: (int) $purchaseRequest->purchase_request_id,
                );
            }

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest']) ?? $purchaseRequest;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function queueFromSupplyRequest(User $actor, array $payload): PurchaseRequest
    {
        $this->assertGsoActor($actor);

        $supplyRequest = SupplyRequest::query()
            ->with(['items.item.inventory'])
            ->where('supply_request_id', (int) $payload['supply_request_id'])
            ->firstOrFail();

        if ((string) $supplyRequest->status !== 'SUBMITTED') {
            throw ValidationException::withMessages([
                'supply_request_id' => 'Only SUBMITTED supply requests can be queued for procurement.',
            ]);
        }

        if (! $supplyRequest->items->isNotEmpty()) {
            throw ValidationException::withMessages([
                'supply_request_id' => 'Supply request must contain at least one item before queueing.',
            ]);
        }

        if (PurchaseRequest::query()->where('supply_request_id', $supplyRequest->supply_request_id)->exists()) {
            throw ValidationException::withMessages([
                'supply_request_id' => 'A purchase request already exists for this supply request.',
            ]);
        }

        $shortageTotal = 0.0;
        foreach ($supplyRequest->items as $supplyRequestItem) {
            $requested = (float) $supplyRequestItem->quantity_requested;
            $available = (float) ($supplyRequestItem->item?->inventory?->available_stock ?? 0);
            $shortageTotal += max($requested - $available, 0);
        }

        if ($shortageTotal <= 0) {
            throw ValidationException::withMessages([
                'supply_request_id' => 'Only shortage requests can be queued for procurement.',
            ]);
        }

        return DB::transaction(function () use ($actor, $payload, $supplyRequest): PurchaseRequest {
            $purchaseRequest = PurchaseRequest::query()->create([
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'office_id' => (int) $supplyRequest->office_id,
                'requested_by' => (int) $supplyRequest->requested_by,
                'status' => 'OPEN',
                'remarks' => $payload['remarks'] ?? 'Queued by GSO due to stock shortage.',
            ]);

            $itemRows = [];
            foreach ($supplyRequest->items as $supplyRequestItem) {
                $itemRows[] = [
                    'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                    'item_id' => (int) $supplyRequestItem->item_id,
                    'quantity_requested' => (float) $supplyRequestItem->quantity_requested,
                    'remarks' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($itemRows !== []) {
                PurchaseRequestItem::query()->insert($itemRows);
            }

            $this->recordStatus($purchaseRequest, null, 'OPEN', $actor, 'Purchase request queued by GSO due to stock shortage.');
            $this->recordAudit($actor, 'QUEUE', $purchaseRequest);

            $requester = User::query()->find($purchaseRequest->requested_by);
            if ($requester instanceof User) {
                $this->notificationService->notifyUser(
                    user: $requester,
                    title: 'Purchase Request Queued',
                    message: 'GSO has queued your supply request for procurement due to stock shortage.',
                    notificationType: 'PROCUREMENT_PURCHASE_REQUEST_CREATED',
                    referenceType: PurchaseRequest::class,
                    referenceId: (int) $purchaseRequest->purchase_request_id,
                );
            }

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest']) ?? $purchaseRequest;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(PurchaseRequest $purchaseRequest, User $actor, array $payload): PurchaseRequest
    {
        $this->assertProcurementActor($actor);
        $this->assertRequestIsMutable($purchaseRequest);

        return DB::transaction(function () use ($purchaseRequest, $actor, $payload): PurchaseRequest {
            $purchaseRequest->fill([
                'remarks' => $payload['remarks'] ?? $purchaseRequest->remarks,
            ])->save();

            $this->recordStatus($purchaseRequest, (string) $purchaseRequest->status, (string) $purchaseRequest->status, $actor, 'Purchase request updated.');
            $this->recordAudit($actor, 'UPDATE', $purchaseRequest);

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest']) ?? $purchaseRequest;
        });
    }

    public function process(PurchaseRequest $purchaseRequest, User $actor, ?string $remarks = null): PurchaseRequest
    {
        $this->assertProcurementActor($actor);

        if ((string) $purchaseRequest->status !== 'OPEN') {
            throw ValidationException::withMessages([
                'status' => 'Only OPEN purchase requests can be moved to PROCESSING.',
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest, $actor, $remarks): PurchaseRequest {
            $oldStatus = (string) $purchaseRequest->status;

            $purchaseRequest->forceFill([
                'status' => 'PROCESSING',
                'remarks' => $remarks ?? $purchaseRequest->remarks,
            ])->save();

            $this->recordStatus($purchaseRequest, $oldStatus, 'PROCESSING', $actor, 'Purchase request is now in procurement processing.');

            ApprovalHistory::query()->create([
                'reference_type' => PurchaseRequest::class,
                'reference_id' => (int) $purchaseRequest->purchase_request_id,
                'approved_by' => null,
                'status' => 'PROCESSING',
                'remarks' => 'Procurement processing stage recorded.',
                'approved_at' => null,
            ]);

            $this->recordAudit($actor, 'PROCESS', $purchaseRequest);

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest']) ?? $purchaseRequest;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addItem(PurchaseRequest $purchaseRequest, User $actor, array $payload): PurchaseRequestItem
    {
        $this->assertProcurementActor($actor);
        $this->assertRequestIsMutable($purchaseRequest);

        return DB::transaction(function () use ($purchaseRequest, $actor, $payload): PurchaseRequestItem {
            $item = $purchaseRequest->items()->create([
                'item_id' => (int) $payload['item_id'],
                'quantity_requested' => (float) $payload['quantity_requested'],
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $this->recordStatus($purchaseRequest, (string) $purchaseRequest->status, (string) $purchaseRequest->status, $actor, 'Purchase request item added.');
            $this->recordAudit($actor, 'ADD_ITEM', $purchaseRequest);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateItem(PurchaseRequest $purchaseRequest, PurchaseRequestItem $item, User $actor, array $payload): PurchaseRequestItem
    {
        $this->assertProcurementActor($actor);
        $this->assertRequestIsMutable($purchaseRequest);

        if ((int) $item->purchase_request_id !== (int) $purchaseRequest->purchase_request_id) {
            throw ValidationException::withMessages([
                'purchase_request_item_id' => 'Item does not belong to this purchase request.',
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest, $item, $actor, $payload): PurchaseRequestItem {
            $item->fill([
                'item_id' => (int) $payload['item_id'],
                'quantity_requested' => (float) $payload['quantity_requested'],
                'remarks' => $payload['remarks'] ?? null,
            ])->save();

            $this->recordStatus($purchaseRequest, (string) $purchaseRequest->status, (string) $purchaseRequest->status, $actor, 'Purchase request item updated.');
            $this->recordAudit($actor, 'UPDATE_ITEM', $purchaseRequest);

            return $item;
        });
    }

    private function assertProcurementActor(User $actor): void
    {
        if (! $actor->isProcurement()) {
            throw ValidationException::withMessages([
                'role' => 'Only procurement officers can perform this action.',
            ]);
        }
    }

    private function assertGsoActor(User $actor): void
    {
        if (! $actor->isGsoOfficer()) {
            throw ValidationException::withMessages([
                'role' => 'Only GSO officers can queue procurement requests from inventory.',
            ]);
        }
    }

    private function assertRequestIsMutable(PurchaseRequest $purchaseRequest): void
    {
        if (! in_array((string) $purchaseRequest->status, ['OPEN', 'PROCESSING'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This purchase request can no longer be modified.',
            ]);
        }
    }

    private function recordStatus(PurchaseRequest $purchaseRequest, ?string $oldStatus, string $newStatus, User $actor, string $remarks): void
    {
        StatusHistory::query()->create([
            'reference_type' => PurchaseRequest::class,
            'reference_id' => (int) $purchaseRequest->purchase_request_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => (int) $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    private function recordAudit(User $actor, string $actionType, PurchaseRequest $purchaseRequest): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Procurement.PurchaseRequest',
            actionType: $actionType,
            referenceType: PurchaseRequest::class,
            referenceId: (int) $purchaseRequest->purchase_request_id,
            details: [
                'supply_request_id' => $purchaseRequest->supply_request_id,
                'office_id' => $purchaseRequest->office_id,
                'status' => $purchaseRequest->status,
            ],
            logDomain: 'BUSINESS',
            officeId: (int) $purchaseRequest->office_id,
        );
    }
}
