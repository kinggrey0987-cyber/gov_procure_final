<?php

namespace App\Services\Office;

use App\Models\ApprovalHistory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\Analytics\AnomalyService;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplyRequestService
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
    public function create(User $actor, array $payload): SupplyRequest
    {
        $this->resolveOfficePpmp((int) $payload['ppmp_id'], $actor);
        $office = Office::query()->findOrFail((int) $actor->office_id);

        return DB::transaction(function () use ($actor, $payload, $office): SupplyRequest {
            $supplyRequest = SupplyRequest::query()->create([
                'request_code' => $this->buildRequestCode((int) $actor->office_id, $office),
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

            $overRequestCount = $this->createOrUpdatePpmpOverRequestAnomalies($supplyRequest);
            $scheduleMismatchCount = $this->createOrUpdatePpmpScheduleMismatchAnomalies($supplyRequest);

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

            $freshSupplyRequest = $supplyRequest->fresh(['items', 'office', 'requester', 'approver']) ?? $supplyRequest;
            $freshSupplyRequest->setAttribute('ppmp_over_request_count', $overRequestCount);
            $freshSupplyRequest->setAttribute('ppmp_schedule_mismatch_count', $scheduleMismatchCount);

            return $freshSupplyRequest;
        });
    }

    public function deleteDraft(SupplyRequest $supplyRequest, User $actor): void
    {
        $this->assertDraftAndOwned($supplyRequest, $actor);

        DB::transaction(function () use ($supplyRequest, $actor): void {
            $supplyRequest->loadMissing('items');
            $itemCount = $supplyRequest->items->count();

            $this->recordAudit($actor, 'DELETE_DRAFT', $supplyRequest, [
                'items_count' => $itemCount,
                'deleted_at' => now()->toIso8601String(),
            ]);

            $supplyRequest->items()->delete();
            $supplyRequest->delete();
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
        $this->assertNoDuplicatePpmpItemLine($supplyRequest, (int) $ppmpItem->ppmp_item_id);
        $payloadItemId = isset($payload['item_id']) ? (int) $payload['item_id'] : null;
        if ($payloadItemId && $payloadItemId !== (int) $ppmpItem->item_id) {
            throw ValidationException::withMessages([
                'item_id' => 'Selected item must match the selected PPMP item.',
            ]);
        }

        return DB::transaction(function () use ($supplyRequest, $actor, $payload, $ppmpItem): SupplyRequestItem {
            $quantity = (float) $payload['quantity_requested'];

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
        $this->assertNoDuplicatePpmpItemLine($supplyRequest, (int) $ppmpItem->ppmp_item_id, (int) $item->supply_request_item_id);
        $payloadItemId = isset($payload['item_id']) ? (int) $payload['item_id'] : null;
        if ($payloadItemId && $payloadItemId !== (int) $ppmpItem->item_id) {
            throw ValidationException::withMessages([
                'item_id' => 'Selected item must match the selected PPMP item.',
            ]);
        }

        return DB::transaction(function () use ($supplyRequest, $item, $actor, $payload, $ppmpItem): SupplyRequestItem {
            $quantity = (float) $payload['quantity_requested'];

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
            ->with('item:item_id,item_name,status,default_unit')
            ->where('ppmp_id', $ppmpId)
            ->whereHas('item', fn ($query) => $query->whereRaw('LOWER(status) = ?', ['active']))
            ->orderBy('ppmp_item_id')
            ->get(['ppmp_item_id', 'ppmp_id', 'item_id', 'quantity', 'unit_cost', 'procurement_schedule', 'remarks']);

        $consumptionRows = SupplyRequestItem::query()
            ->selectRaw('ppmp_item_id, COALESCE(SUM(quantity_requested), 0) as requested_quantity')
            ->whereIn('ppmp_item_id', $ppmpItems->pluck('ppmp_item_id')->all())
            ->whereHas('supplyRequest', fn ($query) => $this->scopePpmpConsumptionSupplyRequests($query, $officeId, $ppmpId))
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
                'default_unit' => (string) ($ppmpItem->item?->default_unit ?? ''),
                'planned_quantity' => $plannedQuantity,
                'requested_quantity' => $requestedQuantity,
                'remaining_quantity' => $remainingQuantity,
                'over_requested_quantity' => $overRequestedQuantity,
                'suggested_unit_cost' => (float) $ppmpItem->unit_cost,
                'procurement_schedule' => (string) ($ppmpItem->procurement_schedule ?? ''),
                'item_specification' => (string) ($ppmpItem->remarks ?? ''),
                'is_schedule_mismatch' => $this->isScheduleEarlierThanPlanned(now(), (string) ($ppmpItem->procurement_schedule ?? '')),
                'current_quarter' => 'Q'.now()->quarter,
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
            ->whereHas('supplyRequest', fn ($query) => $this->scopePpmpConsumptionSupplyRequests($query, $officeId, $ppmpId));

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
            ->with('item:item_id,item_name,status')
            ->where('ppmp_item_id', $ppmpItemId)
            ->first();

        if (! $ppmpItem || (int) $ppmpItem->ppmp_id !== (int) $supplyRequest->ppmp_id) {
            throw ValidationException::withMessages([
                'ppmp_item_id' => 'Selected PPMP item does not belong to the supply request PPMP.',
            ]);
        }

        $itemStatus = $ppmpItem->item?->status;
        if ($itemStatus !== null && (string) $itemStatus !== 'active') {
            throw ValidationException::withMessages([
                'ppmp_item_id' => 'This item is currently inactive and cannot be requested.',
            ]);
        }

        return $ppmpItem;
    }

    private function itemNameSnapshotForPpmpItem(PpmpItem $ppmpItem): string
    {
        return (string) ($ppmpItem->item?->item_name ?? 'Unknown Item');
    }

    private function assertNoDuplicatePpmpItemLine(
        SupplyRequest $supplyRequest,
        int $ppmpItemId,
        ?int $excludeSupplyRequestItemId = null,
    ): void {
        $query = $supplyRequest->items()->where('ppmp_item_id', $ppmpItemId);

        if ($excludeSupplyRequestItemId !== null) {
            $query->where('supply_request_item_id', '!=', $excludeSupplyRequestItemId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'ppmp_item_id' => 'This PPMP item line is already included in the supply request. Update the existing row quantity instead.',
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

    private function buildRequestCode(int $officeId, Office $office): string
    {
        $countThisMonth = SupplyRequest::query()
            ->where('office_id', $officeId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $nextSequence = $countThisMonth + 1;

        return sprintf(
            'SR-%s-%s-%02d',
            (string) $office->code,
            now()->format('Ym'),
            $nextSequence
        );
    }

    private function createOrUpdatePpmpOverRequestAnomalies(SupplyRequest $supplyRequest): int
    {
        $requestItems = $supplyRequest->items()
            ->with('ppmpItem:ppmp_item_id,item_id,quantity')
            ->get(['supply_request_item_id', 'supply_request_id', 'ppmp_item_id', 'item_id', 'item_name_snapshot', 'quantity_requested']);

        if ($requestItems->isEmpty() || ! $supplyRequest->ppmp_id) {
            return 0;
        }

        $ppmpItemIds = $requestItems
            ->pluck('ppmp_item_id')
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        $consumptionTotals = SupplyRequestItem::query()
            ->selectRaw('ppmp_item_id, COALESCE(SUM(quantity_requested), 0) as total_requested')
            ->whereIn('ppmp_item_id', $ppmpItemIds)
            ->whereHas('supplyRequest', fn ($query) => $this->scopePpmpConsumptionSupplyRequests(
                $query,
                (int) $supplyRequest->office_id,
                (int) $supplyRequest->ppmp_id,
            ))
            ->groupBy('ppmp_item_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->ppmp_item_id => (float) $row->total_requested]);

        $anomalyCount = 0;

        foreach ($requestItems->unique('ppmp_item_id') as $requestItem) {
            $ppmpItemId = (int) $requestItem->ppmp_item_id;
            $plannedQuantity = (float) ($requestItem->ppmpItem?->quantity ?? 0);
            $totalRequestedQuantity = (float) ($consumptionTotals[$ppmpItemId] ?? 0);
            $overRequestedQuantity = max(0, round($totalRequestedQuantity - $plannedQuantity, 2));

            if ($overRequestedQuantity <= 0) {
                continue;
            }

            $latestItem = SupplyRequestItem::query()
                ->where('ppmp_item_id', $ppmpItemId)
                ->whereHas('supplyRequest', fn ($query) => $this->scopePpmpConsumptionSupplyRequests(
                    $query,
                    (int) $supplyRequest->office_id,
                    (int) $supplyRequest->ppmp_id,
                ))
                ->latest('supply_request_item_id')
                ->first(['supply_request_item_id', 'item_id']);

            if (! $latestItem instanceof SupplyRequestItem) {
                continue;
            }

            $this->anomalyService->createOrUpdateSystemDetected([
                'anomaly_type' => 'PPMP_OVER_REQUEST',
                'reference_type' => SupplyRequestItem::class,
                'reference_id' => (int) $latestItem->supply_request_item_id,
                'item_id' => (int) ($requestItem->ppmpItem?->item_id ?: $latestItem->item_id),
                'office_id' => (int) $supplyRequest->office_id,
                'detected_value' => $totalRequestedQuantity,
                'expected_value' => $plannedQuantity,
                'threshold_value' => $plannedQuantity,
                'detection_method' => 'PPMP_PLAN_CONSUMPTION',
                'severity' => $this->severityForPpmpOverRequest($plannedQuantity, $overRequestedQuantity),
                'detected_at' => now(),
                'remarks' => sprintf(
                    'Supply request %s exceeded PPMP item #%d by %.2f units (requested %.2f vs planned %.2f).',
                    (string) $supplyRequest->request_code,
                    $ppmpItemId,
                    $overRequestedQuantity,
                    $totalRequestedQuantity,
                    $plannedQuantity
                ),
            ]);

            $anomalyCount++;
        }

        return $anomalyCount;
    }

    private function scopePpmpConsumptionSupplyRequests($query, int $officeId, int $ppmpId): void
    {
        $query->where('office_id', $officeId)
            ->where('ppmp_id', $ppmpId)
            ->whereDoesntHave('purchaseRequests', function ($purchaseRequestQuery): void {
                $purchaseRequestQuery->where('source_type', 'PARTIAL_DELIVERY_FOLLOW_UP');
            });
    }

    private function createOrUpdatePpmpScheduleMismatchAnomalies(SupplyRequest $supplyRequest): int
    {
        $requestDate = $supplyRequest->request_date instanceof Carbon
            ? $supplyRequest->request_date
            : Carbon::parse((string) $supplyRequest->request_date);

        $requestItems = $supplyRequest->items()
            ->with('ppmpItem:ppmp_item_id,item_id,procurement_schedule')
            ->get(['supply_request_item_id', 'supply_request_id', 'ppmp_item_id', 'item_id', 'item_name_snapshot', 'quantity_requested']);

        $anomalyCount = 0;

        foreach ($requestItems as $requestItem) {
            $schedule = (string) ($requestItem->ppmpItem?->procurement_schedule ?? '');
            if (! $this->isScheduleEarlierThanPlanned($requestDate, $schedule)) {
                continue;
            }

            $plannedQuarter = $this->quarterNumberFromSchedule($schedule);
            if ($plannedQuarter === null) {
                continue;
            }

            $this->anomalyService->createOrUpdateSystemDetected([
                'anomaly_type' => 'PPMP_SCHEDULE_MISMATCH',
                'reference_type' => SupplyRequestItem::class,
                'reference_id' => (int) $requestItem->supply_request_item_id,
                'item_id' => (int) ($requestItem->ppmpItem?->item_id ?: $requestItem->item_id),
                'office_id' => (int) $supplyRequest->office_id,
                'detected_value' => (float) $requestDate->quarter,
                'expected_value' => (float) $plannedQuarter,
                'threshold_value' => (float) $plannedQuarter,
                'detection_method' => 'PPMP_SCHEDULE_ALIGNMENT',
                'severity' => $plannedQuarter - (int) $requestDate->quarter >= 2 ? 'HIGH' : 'MEDIUM',
                'detected_at' => now(),
                'remarks' => sprintf(
                    'Supply request %s requested %s in Q%d while PPMP item #%d is planned for %s. GSO may still issue from stock or create a PR if stock is unavailable, but the early consumption is flagged for executive review.',
                    (string) $supplyRequest->request_code,
                    (string) ($requestItem->item_name_snapshot ?: 'an item'),
                    (int) $requestDate->quarter,
                    (int) $requestItem->ppmp_item_id,
                    strtoupper($schedule),
                ),
            ]);

            $anomalyCount++;
        }

        return $anomalyCount;
    }

    private function isScheduleEarlierThanPlanned(Carbon $requestDate, string $procurementSchedule): bool
    {
        $plannedQuarter = $this->quarterNumberFromSchedule($procurementSchedule);

        return $plannedQuarter !== null && (int) $requestDate->quarter < $plannedQuarter;
    }

    private function quarterNumberFromSchedule(string $procurementSchedule): ?int
    {
        $schedule = strtoupper(trim($procurementSchedule));
        if (preg_match('/Q([1-4])/', $schedule, $matches) === 1) {
            return (int) $matches[1];
        }

        return match ($schedule) {
            'JANUARY', 'FEBRUARY', 'MARCH' => 1,
            'APRIL', 'MAY', 'JUNE' => 2,
            'JULY', 'AUGUST', 'SEPTEMBER' => 3,
            'OCTOBER', 'NOVEMBER', 'DECEMBER' => 4,
            default => null,
        };
    }

    private function severityForPpmpOverRequest(float $plannedQuantity, float $overRequestedQuantity): string
    {
        if ($plannedQuantity <= 0) {
            return 'HIGH';
        }

        $overagePercent = ($overRequestedQuantity / $plannedQuantity) * 100;

        if ($overagePercent <= 10.0) {
            return 'LOW';
        }

        if ($overagePercent <= 25.0) {
            return 'MEDIUM';
        }

        return 'HIGH';
    }
}
