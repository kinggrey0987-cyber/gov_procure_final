<?php

namespace App\Services\Office;

use App\Models\ApprovalHistory;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplyRequestService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): SupplyRequest
    {
        return DB::transaction(function () use ($actor, $payload): SupplyRequest {
            $supplyRequest = SupplyRequest::query()->create([
                'request_code' => $this->buildRequestCode((int) $actor->office_id),
                'office_id' => $actor->office_id,
                'requested_by' => $actor->id,
                'request_date' => $payload['request_date'],
                'needed_by_date' => $payload['needed_by_date'],
                'request_category' => $payload['request_category'],
                'purpose' => $payload['purpose'],
                'priority_level' => $payload['priority_level'],
                'status' => 'DRAFT',
                'review_notes' => $payload['review_notes'] ?? null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            $this->recordStatus($supplyRequest, null, 'DRAFT', $actor, 'Supply request created');
            $this->recordAudit($actor, 'CREATE', $supplyRequest);

            return $supplyRequest->fresh(['office', 'requester', 'approver']) ?? $supplyRequest;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(SupplyRequest $supplyRequest, User $actor, array $payload): SupplyRequest
    {
        $this->assertDraftAndOwned($supplyRequest, $actor);

        return DB::transaction(function () use ($supplyRequest, $actor, $payload): SupplyRequest {
            $oldStatus = (string) $supplyRequest->status;

            $supplyRequest->fill([
                'request_date' => $payload['request_date'],
                'needed_by_date' => $payload['needed_by_date'],
                'request_category' => $payload['request_category'],
                'purpose' => $payload['purpose'],
                'priority_level' => $payload['priority_level'],
                'review_notes' => $payload['review_notes'] ?? $supplyRequest->review_notes,
            ])->save();

            $this->recordStatus($supplyRequest, $oldStatus, 'DRAFT', $actor, 'Supply request updated');
            $this->recordAudit($actor, 'UPDATE', $supplyRequest);

            return $supplyRequest->fresh(['items', 'office', 'requester', 'approver']) ?? $supplyRequest;
        });
    }

    public function submit(SupplyRequest $supplyRequest, User $actor, ?string $reviewNotes = null): SupplyRequest
    {
        $this->assertDraftAndOwned($supplyRequest, $actor);

        if (! $supplyRequest->items()->exists()) {
            throw ValidationException::withMessages([
                'items' => 'At least one supply request item is required before submission.',
            ]);
        }

        return DB::transaction(function () use ($supplyRequest, $actor, $reviewNotes): SupplyRequest {
            $oldStatus = (string) $supplyRequest->status;

            $supplyRequest->forceFill([
                'status' => 'SUBMITTED',
                'review_notes' => $reviewNotes ?? $supplyRequest->review_notes,
            ])->save();

            $this->recordStatus($supplyRequest, $oldStatus, 'SUBMITTED', $actor, 'Supply request submitted');
            $this->recordAudit($actor, 'SUBMIT', $supplyRequest);

            $this->notificationService->notifyUser(
                user: $actor,
                title: 'Supply Request Submitted',
                message: 'Your supply request was submitted successfully.',
                notificationType: 'OFFICE_SUPPLY_REQUEST_SUBMITTED',
                referenceType: SupplyRequest::class,
                referenceId: (int) $supplyRequest->supply_request_id,
            );

            return $supplyRequest->fresh(['items', 'office', 'requester', 'approver']) ?? $supplyRequest;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addItem(SupplyRequest $supplyRequest, User $actor, array $payload): SupplyRequestItem
    {
        $this->assertDraftAndOwned($supplyRequest, $actor);

        return DB::transaction(function () use ($supplyRequest, $actor, $payload): SupplyRequestItem {
            $quantity = (float) $payload['quantity_requested'];
            $unitCost = (float) $payload['estimated_unit_cost'];

            $item = $supplyRequest->items()->create([
                'item_id' => (int) $payload['item_id'],
                'ppmp_item_id' => (int) $payload['ppmp_item_id'],
                'item_name_snapshot' => $payload['item_name_snapshot'],
                'quantity_requested' => $quantity,
                'estimated_unit_cost' => $unitCost,
                'estimated_total_cost' => round($quantity * $unitCost, 2),
            ]);

            $this->recordStatus($supplyRequest, 'DRAFT', 'DRAFT', $actor, 'Supply request item added');
            $this->recordAudit($actor, 'ADD_ITEM', $supplyRequest);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateItem(
        SupplyRequest $supplyRequest,
        SupplyRequestItem $item,
        User $actor,
        array $payload
    ): SupplyRequestItem {
        $this->assertDraftAndOwned($supplyRequest, $actor);

        if ((int) $item->supply_request_id !== (int) $supplyRequest->supply_request_id) {
            throw ValidationException::withMessages([
                'supply_request_item_id' => 'Item does not belong to this supply request.',
            ]);
        }

        return DB::transaction(function () use ($supplyRequest, $item, $actor, $payload): SupplyRequestItem {
            $quantity = (float) $payload['quantity_requested'];
            $unitCost = (float) $payload['estimated_unit_cost'];

            $item->fill([
                'item_id' => (int) $payload['item_id'],
                'ppmp_item_id' => (int) $payload['ppmp_item_id'],
                'item_name_snapshot' => $payload['item_name_snapshot'],
                'quantity_requested' => $quantity,
                'estimated_unit_cost' => $unitCost,
                'estimated_total_cost' => round($quantity * $unitCost, 2),
            ])->save();

            $this->recordStatus($supplyRequest, 'DRAFT', 'DRAFT', $actor, 'Supply request item updated');
            $this->recordAudit($actor, 'UPDATE_ITEM', $supplyRequest);

            return $item;
        });
    }

    public function removeItem(SupplyRequest $supplyRequest, SupplyRequestItem $item, User $actor): void
    {
        $this->assertDraftAndOwned($supplyRequest, $actor);

        if ((int) $item->supply_request_id !== (int) $supplyRequest->supply_request_id) {
            throw ValidationException::withMessages([
                'supply_request_item_id' => 'Item does not belong to this supply request.',
            ]);
        }

        DB::transaction(function () use ($supplyRequest, $item, $actor): void {
            $item->delete();
            $this->recordStatus($supplyRequest, 'DRAFT', 'DRAFT', $actor, 'Supply request item removed');
            $this->recordAudit($actor, 'REMOVE_ITEM', $supplyRequest);
        });
    }

    private function assertDraftAndOwned(SupplyRequest $supplyRequest, User $actor): void
    {
        if ((int) $supplyRequest->office_id !== (int) $actor->office_id) {
            throw ValidationException::withMessages([
                'office_id' => 'You can only access supply requests from your office.',
            ]);
        }

        if ((string) $supplyRequest->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'status' => 'Only DRAFT supply requests can be modified.',
            ]);
        }
    }

    private function recordStatus(
        SupplyRequest $supplyRequest,
        ?string $oldStatus,
        string $newStatus,
        User $actor,
        string $remarks
    ): void {
        StatusHistory::query()->create([
            'reference_type' => SupplyRequest::class,
            'reference_id' => (int) $supplyRequest->supply_request_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);

        if ($newStatus === 'SUBMITTED') {
            ApprovalHistory::query()->create([
                'reference_type' => SupplyRequest::class,
                'reference_id' => (int) $supplyRequest->supply_request_id,
                'approved_by' => null,
                'status' => 'SUBMITTED',
                'remarks' => 'Office-side submission recorded.',
                'approved_at' => null,
            ]);
        }
    }

    private function recordAudit(User $actor, string $actionType, SupplyRequest $supplyRequest): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Office.SupplyRequest',
            actionType: $actionType,
            referenceType: SupplyRequest::class,
            referenceId: (int) $supplyRequest->supply_request_id,
            details: [
                'office_id' => $supplyRequest->office_id,
                'status' => $supplyRequest->status,
                'request_code' => $supplyRequest->request_code,
            ],
            logDomain: 'BUSINESS',
            officeId: (int) $supplyRequest->office_id,
        );
    }

    private function buildRequestCode(int $officeId): string
    {
        return sprintf('SR-%d-%s', $officeId, now()->format('YmdHisv'));
    }
}
