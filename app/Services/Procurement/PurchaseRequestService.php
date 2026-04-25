<?php

namespace App\Services\Procurement;

use App\Models\Inventory;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\ApprovalHistory;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\StatusHistory;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Analytics\AnomalyService;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
        private readonly AnomalyService $anomalyService,
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
            ->with(['office', 'items.item.inventory'])
            ->where('supply_request_id', (int) $payload['supply_request_id'])
            ->firstOrFail();

        if ((string) ($supplyRequest->office?->code ?? '') === 'GSO') {
            throw ValidationException::withMessages([
                'supply_request_id' => 'GSO-origin supply requests are internal logistics records and cannot be queued through office procurement routing.',
            ]);
        }

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

    public function createFromLowStockSignal(User $actor, Inventory $inventory, float $quantityRequested): PurchaseRequest
    {
        $this->assertGsoActor($actor);

        if ((int) $inventory->item_id <= 0) {
            throw ValidationException::withMessages([
                'inventory_id' => 'Inventory record is not linked to a valid catalog item.',
            ]);
        }

        if ($quantityRequested <= 0) {
            throw ValidationException::withMessages([
                'quantity_requested' => 'Low-stock purchase quantity must be greater than zero.',
            ]);
        }

        $officeId = (int) ($actor->office_id ?? 0);
        if ($officeId <= 0) {
            throw ValidationException::withMessages([
                'office_id' => 'GSO officer must belong to an office before creating low-stock purchase requests.',
            ]);
        }

        $signalMarker = sprintf('LOW_STOCK_SIGNAL:inventory:%d', (int) $inventory->inventory_id);
        $governanceLabel = 'GSO Replenishment Basis - internal stock replenishment planning context only; not an office-approved PPMP and not a procurement approval.';

        $alreadyQueued = PurchaseRequest::query()
            ->whereIn('status', ['OPEN', 'PROCESSING'])
            ->where('remarks', 'like', '%'.$signalMarker.'%')
            ->exists();

        if ($alreadyQueued) {
            throw ValidationException::withMessages([
                'inventory_id' => 'An active purchase request is already queued from this low-stock signal.',
            ]);
        }

        return DB::transaction(function () use ($actor, $inventory, $quantityRequested, $officeId, $signalMarker, $governanceLabel): PurchaseRequest {
            $inventory->loadMissing('item');

            $item = $inventory->item;
            $itemCategoryId = (int) ($item?->item_category_id ?? 0);

            if ($itemCategoryId <= 0) {
                throw ValidationException::withMessages([
                    'inventory_id' => 'Inventory item must have a valid category for low-stock purchase request initiation.',
                ]);
            }

            $ppmp = Ppmp::query()->create([
                'office_id' => $officeId,
                'prepared_by' => (int) $actor->id,
                'approved_by' => null,
                'fiscal_year' => (int) now()->format('Y'),
                'status' => 'SUBMITTED',
                'total_estimated_budget' => 0,
                'date_prepared' => now()->toDateString(),
                'date_approved' => null,
                'remarks' => $governanceLabel,
            ]);

            $ppmpItem = PpmpItem::query()->create([
                'ppmp_id' => (int) $ppmp->ppmp_id,
                'item_id' => (int) $inventory->item_id,
                'item_category_id' => $itemCategoryId,
                'quantity' => round($quantityRequested, 2),
                'unit_cost' => 0,
                'estimated_budget' => 0,
                'procurement_schedule' => 'IMMEDIATE',
                'fund_source' => 'General Fund',
                'remarks' => $governanceLabel,
            ]);

            $supplyRequest = SupplyRequest::query()->create([
                'request_code' => sprintf('SR-LS-%s-%04d', now()->format('YmdHis'), random_int(1000, 9999)),
                'office_id' => $officeId,
                'requested_by' => (int) $actor->id,
                'ppmp_id' => (int) $ppmp->ppmp_id,
                'request_date' => now()->toDateString(),
                'needed_by_date' => now()->addDays(7)->toDateString(),
                'request_category' => 'GENERAL',
                'purpose' => sprintf(
                    'GSO Replenishment Basis for %s (inventory #%d). Internal stock replenishment planning context only; BAC/procurement must still process any PR/PO.',
                    (string) ($inventory->item?->item_name ?? 'Unassigned Item'),
                    (int) $inventory->inventory_id
                ),
                'priority_level' => 'HIGH',
                'status' => 'SUBMITTED',
                'review_notes' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'item_id' => (int) $inventory->item_id,
                'ppmp_item_id' => (int) $ppmpItem->ppmp_item_id,
                'item_name_snapshot' => (string) ($item?->item_name ?? 'Unassigned Item'),
                'quantity_requested' => round($quantityRequested, 2),
            ]);

            $purchaseRequest = PurchaseRequest::query()->create([
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'office_id' => $officeId,
                'requested_by' => (int) $actor->id,
                'status' => 'OPEN',
                'remarks' => sprintf(
                    '%s | GSO Replenishment Basis for %s. Internal stock replenishment planning context only; this is not automated PPMP approval, procurement approval, or PO approval.',
                    $signalMarker,
                    (string) ($inventory->item?->item_name ?? 'Unassigned Item')
                ),
            ]);

            PurchaseRequestItem::query()->create([
                'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                'item_id' => (int) $inventory->item_id,
                'quantity_requested' => round($quantityRequested, 2),
                'remarks' => 'Quantity proposed from low-stock deficit under GSO Replenishment Basis; BAC/procurement processing remains required.',
            ]);

            $this->recordStatus($purchaseRequest, null, 'OPEN', $actor, 'Purchase request queued from GSO Replenishment Basis; BAC/procurement processing and PO creation remain required.');
            $this->recordAudit($actor, 'AUTO_QUEUE_LOW_STOCK', $purchaseRequest);

            $deficit = max((float) $inventory->low_stock_threshold - (float) $inventory->available_stock, 0);
            $anomalyThreshold = max($deficit * 3, (float) $inventory->low_stock_threshold * 2, 1);

            if ($quantityRequested > $anomalyThreshold) {
                $severity = $quantityRequested > ($anomalyThreshold * 1.5) ? 'HIGH' : 'MEDIUM';

                $this->anomalyService->createOrUpdateSystemDetected([
                    'anomaly_type' => 'EXCESSIVE_QUANTITY',
                    'reference_type' => PurchaseRequest::class,
                    'reference_id' => (int) $purchaseRequest->purchase_request_id,
                    'item_id' => (int) $inventory->item_id,
                    'office_id' => $officeId,
                    'detected_value' => round($quantityRequested, 2),
                    'expected_value' => round($deficit, 2),
                    'threshold_value' => round($anomalyThreshold, 2),
                    'detection_method' => 'LOW_STOCK_SIGNAL_QUANTITY_GUARD',
                    'severity' => $severity,
                    'remarks' => 'Low-stock initiated quantity exceeds configured threshold guardrail.',
                ]);
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

        if (! in_array((string) $purchaseRequest->status, ['OPEN', 'RETURNED_FOR_REVISION'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only OPEN or RETURNED_FOR_REVISION purchase requests can be moved to PROCESSING.',
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

    public function returnForRevision(PurchaseRequest $purchaseRequest, User $actor, string $remarks): PurchaseRequest
    {
        $this->assertProcurementActor($actor);

        if (! in_array((string) $purchaseRequest->status, ['OPEN', 'RETURNED_FOR_REVISION'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only OPEN or RETURNED_FOR_REVISION purchase requests can be returned for revision.',
            ]);
        }

        $finalRemarks = trim($remarks);
        if ($finalRemarks === '') {
            throw ValidationException::withMessages([
                'remarks' => 'Remarks are required when returning for revision.',
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest, $actor, $finalRemarks): PurchaseRequest {
            $oldStatus = (string) $purchaseRequest->status;

            $purchaseRequest->forceFill([
                'status' => 'RETURNED_FOR_REVISION',
                'remarks' => $finalRemarks,
            ])->save();

            $this->recordStatus($purchaseRequest, $oldStatus, 'RETURNED_FOR_REVISION', $actor, 'Purchase request returned for revision by procurement.');

            ApprovalHistory::query()->create([
                'reference_type' => PurchaseRequest::class,
                'reference_id' => (int) $purchaseRequest->purchase_request_id,
                'approved_by' => null,
                'status' => 'RETURNED_FOR_REVISION',
                'remarks' => $finalRemarks,
                'approved_at' => null,
            ]);

            $this->recordAudit($actor, 'RETURN_FOR_REVISION', $purchaseRequest);

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest']) ?? $purchaseRequest;
        });
    }

    public function reject(PurchaseRequest $purchaseRequest, User $actor, string $remarks): PurchaseRequest
    {
        $this->assertProcurementActor($actor);

        if (! in_array((string) $purchaseRequest->status, ['OPEN', 'RETURNED_FOR_REVISION'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only OPEN or RETURNED_FOR_REVISION purchase requests can be rejected.',
            ]);
        }

        $finalRemarks = trim($remarks);
        if ($finalRemarks === '') {
            throw ValidationException::withMessages([
                'remarks' => 'Remarks are required when rejecting a request.',
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest, $actor, $finalRemarks): PurchaseRequest {
            $oldStatus = (string) $purchaseRequest->status;

            $purchaseRequest->forceFill([
                'status' => 'REJECTED',
                'remarks' => $finalRemarks,
            ])->save();

            $this->recordStatus($purchaseRequest, $oldStatus, 'REJECTED', $actor, 'Purchase request rejected by procurement.');

            ApprovalHistory::query()->create([
                'reference_type' => PurchaseRequest::class,
                'reference_id' => (int) $purchaseRequest->purchase_request_id,
                'approved_by' => null,
                'status' => 'REJECTED',
                'remarks' => $finalRemarks,
                'approved_at' => null,
            ]);

            $this->recordAudit($actor, 'REJECT', $purchaseRequest);

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
        if (! in_array((string) $purchaseRequest->status, ['OPEN', 'PROCESSING', 'RETURNED_FOR_REVISION'], true)) {
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
