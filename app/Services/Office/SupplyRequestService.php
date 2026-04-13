<?php

namespace App\Services\Office;

use App\Models\ApprovalHistory;
use App\Models\Ppmp;
use App\Models\PpmpItem;
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
        $this->resolveOfficePpmp((int) $payload['ppmp_id'], $actor);

        return DB::transaction(function () use ($actor, $payload): SupplyRequest {
            $supplyRequest = SupplyRequest::query()->create([
                'request_code' => $this->buildRequestCode((int) $actor->office_id),
                'office_id' => $actor->office_id,
                'requested_by' => $actor->id,
                'ppmp_id' => (int) $payload['ppmp_id'],
                'request_date' => now()->toDateString(),
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

            return $supplyRequest->fresh(['office', 'requester', 'approver', 'ppmp']) ?? $supplyRequest;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(SupplyRequest $supplyRequest, User $actor, array $payload): SupplyRequest
    {
        $this->assertDraftAndOwned($supplyRequest, $actor);
        $ppmp = $this->resolveOfficePpmp((int) $payload['ppmp_id'], $actor);

        if ((int) $supplyRequest->ppmp_id !== (int) $ppmp->ppmp_id) {
            $hasMismatchedItems = $supplyRequest->items()
                ->whereHas('ppmpItem', function ($query) use ($ppmp): void {
                    $query->where('ppmp_id', '!=', (int) $ppmp->ppmp_id);
                })
                ->exists();

            if ($hasMismatchedItems) {
                throw ValidationException::withMessages([
                    'ppmp_id' => 'Cannot change PPMP while the request still contains items from another PPMP.',
                ]);
            }
        }

        return DB::transaction(function () use ($supplyRequest, $actor, $payload): SupplyRequest {
            $oldStatus = (string) $supplyRequest->status;

            $supplyRequest->fill([
                'ppmp_id' => (int) $payload['ppmp_id'],
                'needed_by_date' => $payload['needed_by_date'],
                'request_category' => $payload['request_category'],
                'purpose' => $payload['purpose'],
                'priority_level' => $payload['priority_level'],
                'review_notes' => $payload['review_notes'] ?? $supplyRequest->review_notes,
            ])->save();

            $this->recordStatus($supplyRequest, $oldStatus, 'DRAFT', $actor, 'Supply request updated');
            $this->recordAudit($actor, 'UPDATE', $supplyRequest);

            return $supplyRequest->fresh(['items', 'office', 'requester', 'approver', 'ppmp']) ?? $supplyRequest;
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

        if (! $supplyRequest->ppmp_id) {
            throw ValidationException::withMessages([
                'ppmp_id' => 'Select a PPMP on the supply request before adding items.',
            ]);
        }

        $ppmpItem = $this->resolvePpmpItemForRequest($supplyRequest, (int) $payload['ppmp_item_id']);
        $payloadItemId = isset($payload['item_id']) ? (int) $payload['item_id'] : null;
        if ($payloadItemId && $payloadItemId !== (int) $ppmpItem->item_id) {
            throw ValidationException::withMessages([
                'item_id' => 'Selected item must match the selected PPMP item.',
            ]);
        }

        return DB::transaction(function () use ($supplyRequest, $actor, $payload, $ppmpItem): SupplyRequestItem {
            $quantity = (float) $payload['quantity_requested'];

            $consumption = $this->consumptionForPpmpItem(
                ppmpItemId: (int) $ppmpItem->ppmp_item_id,
                officeId: (int) $supplyRequest->office_id,
                ppmpId: (int) $supplyRequest->ppmp_id,
            );

            if ($quantity > $consumption['remaining_quantity']) {
                throw ValidationException::withMessages([
                    'quantity_requested' => sprintf(
                        'Requested quantity exceeds PPMP remaining quantity (planned %.2f, already requested %.2f, remaining %.2f).',
                        $consumption['planned_quantity'],
                        $consumption['requested_quantity'],
                        max($consumption['remaining_quantity'], 0)
                    ),
                ]);
            }

            $item = $supplyRequest->items()->create([
                'item_id' => (int) $ppmpItem->item_id,
                'ppmp_item_id' => (int) $payload['ppmp_item_id'],
                'item_name_snapshot' => $this->itemNameSnapshotForPpmpItem($ppmpItem),
                'quantity_requested' => $quantity,
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

        if (! $supplyRequest->ppmp_id) {
            throw ValidationException::withMessages([
                'ppmp_id' => 'Select a PPMP on the supply request before updating items.',
            ]);
        }

        $ppmpItem = $this->resolvePpmpItemForRequest($supplyRequest, (int) $payload['ppmp_item_id']);
        $payloadItemId = isset($payload['item_id']) ? (int) $payload['item_id'] : null;
        if ($payloadItemId && $payloadItemId !== (int) $ppmpItem->item_id) {
            throw ValidationException::withMessages([
                'item_id' => 'Selected item must match the selected PPMP item.',
            ]);
        }

        return DB::transaction(function () use ($supplyRequest, $item, $actor, $payload, $ppmpItem): SupplyRequestItem {
            $quantity = (float) $payload['quantity_requested'];

            $consumption = $this->consumptionForPpmpItem(
                ppmpItemId: (int) $ppmpItem->ppmp_item_id,
                officeId: (int) $supplyRequest->office_id,
                ppmpId: (int) $supplyRequest->ppmp_id,
                excludeSupplyRequestItemId: (int) $item->supply_request_item_id,
            );

            if ($quantity > $consumption['remaining_quantity']) {
                throw ValidationException::withMessages([
                    'quantity_requested' => sprintf(
                        'Requested quantity exceeds PPMP remaining quantity (planned %.2f, already requested %.2f, remaining %.2f).',
                        $consumption['planned_quantity'],
                        $consumption['requested_quantity'],
                        max($consumption['remaining_quantity'], 0)
                    ),
                ]);
            }

            $item->fill([
                'item_id' => (int) $ppmpItem->item_id,
                'ppmp_item_id' => (int) $payload['ppmp_item_id'],
                'item_name_snapshot' => $this->itemNameSnapshotForPpmpItem($ppmpItem),
                'quantity_requested' => $quantity,
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function ppmpOptionsForOffice(int $officeId): array
    {
        return Ppmp::query()
            ->where('office_id', $officeId)
            ->where('status', 'APPROVED')
            ->latest('ppmp_id')
            ->get(['ppmp_id', 'fiscal_year', 'status', 'date_prepared'])
            ->map(fn (Ppmp $ppmp): array => [
                'ppmp_id' => (int) $ppmp->ppmp_id,
                'label' => sprintf('PPMP-%d-%04d', (int) $ppmp->fiscal_year, (int) $ppmp->ppmp_id),
                'status' => (string) $ppmp->status,
                'date_prepared' => optional($ppmp->date_prepared)?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function ppmpItemOptionsForRequestContext(int $officeId, int $ppmpId): array
    {
        $ppmp = Ppmp::query()
            ->where('office_id', $officeId)
            ->where('ppmp_id', $ppmpId)
            ->first();

        if (! $ppmp) {
            return [];
        }

        $ppmpItems = PpmpItem::query()
            ->with('item:item_id,item_name')
            ->where('ppmp_id', $ppmpId)
            ->orderBy('ppmp_item_id')
            ->get(['ppmp_item_id', 'ppmp_id', 'item_id', 'quantity', 'unit_cost']);

        $consumptionRows = SupplyRequestItem::query()
            ->selectRaw('ppmp_item_id, COALESCE(SUM(quantity_requested), 0) as requested_quantity')
            ->whereIn('ppmp_item_id', $ppmpItems->pluck('ppmp_item_id')->all())
            ->whereHas('supplyRequest', function ($query) use ($officeId, $ppmpId): void {
                $query->where('office_id', $officeId)->where('ppmp_id', $ppmpId);
            })
            ->groupBy('ppmp_item_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->ppmp_item_id => (float) $row->requested_quantity]);

        return $ppmpItems->map(function (PpmpItem $ppmpItem) use ($consumptionRows): array {
            $plannedQuantity = (float) $ppmpItem->quantity;
            $requestedQuantity = (float) ($consumptionRows[(int) $ppmpItem->ppmp_item_id] ?? 0.0);
            $remainingQuantity = round($plannedQuantity - $requestedQuantity, 2);
            $overRequestedQuantity = max(0, round($requestedQuantity - $plannedQuantity, 2));

            return [
                'ppmp_item_id' => (int) $ppmpItem->ppmp_item_id,
                'ppmp_id' => (int) $ppmpItem->ppmp_id,
                'item_id' => (int) $ppmpItem->item_id,
                'item_name' => (string) ($ppmpItem->item?->item_name ?? 'Unknown Item'),
                'planned_quantity' => $plannedQuantity,
                'requested_quantity' => $requestedQuantity,
                'remaining_quantity' => $remainingQuantity,
                'over_requested_quantity' => $overRequestedQuantity,
                'suggested_unit_cost' => (float) $ppmpItem->unit_cost,
            ];
        })->values()->all();
    }

    /**
     * @return array{planned_quantity: float, requested_quantity: float, remaining_quantity: float, over_requested_quantity: float}
     */
    public function consumptionForPpmpItem(
        int $ppmpItemId,
        int $officeId,
        int $ppmpId,
        ?int $excludeSupplyRequestItemId = null,
    ): array {
        $ppmpItem = PpmpItem::query()
            ->where('ppmp_item_id', $ppmpItemId)
            ->where('ppmp_id', $ppmpId)
            ->firstOrFail(['ppmp_item_id', 'quantity']);

        $requestedQuery = SupplyRequestItem::query()
            ->where('ppmp_item_id', $ppmpItemId)
            ->whereHas('supplyRequest', function ($query) use ($officeId, $ppmpId): void {
                $query->where('office_id', $officeId)->where('ppmp_id', $ppmpId);
            });

        if ($excludeSupplyRequestItemId) {
            $requestedQuery->where('supply_request_item_id', '!=', $excludeSupplyRequestItemId);
        }

        $requestedQuantity = (float) ($requestedQuery->sum('quantity_requested') ?? 0);
        $plannedQuantity = (float) $ppmpItem->quantity;
        $remainingQuantity = round($plannedQuantity - $requestedQuantity, 2);

        return [
            'planned_quantity' => $plannedQuantity,
            'requested_quantity' => $requestedQuantity,
            'remaining_quantity' => $remainingQuantity,
            'over_requested_quantity' => max(0, round($requestedQuantity - $plannedQuantity, 2)),
        ];
    }

    private function resolveOfficePpmp(int $ppmpId, User $actor): Ppmp
    {
        /** @var Ppmp|null $ppmp */
        $ppmp = Ppmp::query()
            ->where('ppmp_id', $ppmpId)
            ->where('office_id', (int) $actor->office_id)
            ->where('status', 'APPROVED')
            ->first();

        if (! $ppmp) {
            throw ValidationException::withMessages([
                'ppmp_id' => 'Selected PPMP is not approved and cannot be used for a supply request.',
            ]);
        }

        return $ppmp;
    }

    private function resolvePpmpItemForRequest(SupplyRequest $supplyRequest, int $ppmpItemId): PpmpItem
    {
        $ppmpItem = PpmpItem::query()
            ->with('item:item_id,item_name')
            ->where('ppmp_item_id', $ppmpItemId)
            ->first();

        if (! $ppmpItem || (int) $ppmpItem->ppmp_id !== (int) $supplyRequest->ppmp_id) {
            throw ValidationException::withMessages([
                'ppmp_item_id' => 'Selected PPMP item does not belong to the supply request PPMP.',
            ]);
        }

        return $ppmpItem;
    }

    private function itemNameSnapshotForPpmpItem(PpmpItem $ppmpItem): string
    {
        return (string) ($ppmpItem->item?->item_name ?? 'Unknown Item');
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
