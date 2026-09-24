<?php

namespace App\Services\Office;

use App\Models\ApprovalHistory;
use App\Enums\UserRole;
use App\Enums\FundSource;
use App\Models\Item;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PpmpService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): Ppmp
    {
        return DB::transaction(function () use ($actor, $payload): Ppmp {
            $ppmp = Ppmp::query()->create([
                'office_id' => $actor->office_id,
                'prepared_by' => $actor->id,
                'approved_by' => null,
                'fiscal_year' => (int) $payload['fiscal_year'],
                'fund_source' => $payload['fund_source'],
                'status' => 'DRAFT',
                'total_estimated_budget' => 0,
                'date_prepared' => $payload['date_prepared'],
                'date_approved' => null,
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $this->recordStatus($ppmp, null, 'DRAFT', $actor, 'PPMP created');
            $this->recordAudit($actor, 'CREATE', $ppmp);

            return $ppmp->fresh(['office', 'preparer', 'approver']) ?? $ppmp;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Ppmp $ppmp, User $actor, array $payload): Ppmp
    {
        $this->assertDraftAndOwned($ppmp, $actor);

        return DB::transaction(function () use ($ppmp, $actor, $payload): Ppmp {
            $oldStatus = (string) $ppmp->status;
            $oldFundSource = $ppmp->fund_source instanceof FundSource
                ? $ppmp->fund_source->value
                : (string) $ppmp->fund_source;
            $newFundSource = $payload['fund_source'] ?? $oldFundSource;

            $ppmp->fill([
                'fund_source' => $newFundSource,
                'remarks' => $payload['remarks'] ?? $ppmp->remarks,
            ])->save();

            $this->recomputeTotalBudget($ppmp);
            $this->recordStatus($ppmp, $oldStatus, 'DRAFT', $actor, 'PPMP updated');
            $this->recordAudit($actor, 'UPDATE', $ppmp, [
                'fund_source_changed' => $oldFundSource !== $newFundSource,
                'old_fund_source' => $oldFundSource !== $newFundSource ? $oldFundSource : null,
                'new_fund_source' => $oldFundSource !== $newFundSource ? $newFundSource : null,
            ]);

            return $ppmp->fresh(['items', 'office', 'preparer', 'approver']) ?? $ppmp;
        });
    }

    public function submit(Ppmp $ppmp, User $actor, ?string $remarks = null): Ppmp
    {
        $this->assertDraftAndOwned($ppmp, $actor);

        $currentYear = (int) now()->format('Y');
        if ((int) $ppmp->fiscal_year > $currentYear) {
            throw ValidationException::withMessages([
                'fiscal_year' => "PPMP for Fiscal Year {$ppmp->fiscal_year} is a projected plan and cannot be submitted for executive review until FY {$ppmp->fiscal_year} begins.",
            ]);
        }

        if (! $ppmp->items()->exists()) {
            throw ValidationException::withMessages([
                'items' => 'At least one PPMP item is required before submission.',
            ]);
        }

        return DB::transaction(function () use ($ppmp, $actor, $remarks): Ppmp {
            $oldStatus = (string) $ppmp->status;

            $ppmp->forceFill([
                'status' => 'SUBMITTED',
                'remarks' => $remarks ?? $ppmp->remarks,
            ])->save();

            $this->recordStatus($ppmp, $oldStatus, 'SUBMITTED', $actor, 'PPMP submitted');
            $this->recordAudit($actor, 'SUBMIT', $ppmp);

            $this->notificationService->notifyUser(
                user: $actor,
                title: 'PPMP Submitted',
                message: 'Your PPMP was submitted successfully.',
                notificationType: 'OFFICE_PPMP_SUBMITTED',
                referenceType: Ppmp::class,
                referenceId: (int) $ppmp->ppmp_id,
            );

            $this->notificationService->notifyRole(
                role: UserRole::EXECUTIVE_OFFICER,
                title: 'PPMP Pending Executive Approval',
                message: sprintf('PPMP #%d has been submitted for executive review.', (int) $ppmp->ppmp_id),
                notificationType: 'EXEC_PPMP_PENDING_APPROVAL',
                referenceType: Ppmp::class,
                referenceId: (int) $ppmp->ppmp_id,
            );

            return $ppmp->fresh(['items', 'office', 'preparer', 'approver']) ?? $ppmp;
        });
    }

    public function deleteDraft(Ppmp $ppmp, User $actor): void
    {
        $this->assertDeletableDraftAndOwned($ppmp, $actor);

        DB::transaction(function () use ($ppmp, $actor): void {
            $ppmp->loadMissing('items');
            $itemCount = $ppmp->items->count();

            $this->recordAudit($actor, 'DELETE_DRAFT', $ppmp, [
                'items_count' => $itemCount,
                'deleted_at' => now()->toIso8601String(),
            ]);

            $ppmp->items()->delete();
            $ppmp->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addItem(Ppmp $ppmp, User $actor, array $payload): PpmpItem
    {
        $this->assertDraftAndOwned($ppmp, $actor);
        $this->assertUniqueItemSchedule(
            $ppmp,
            (int) $payload['item_id'],
            (string) ($payload['remarks'] ?? ''),
            (string) $payload['procurement_schedule']
        );

        return DB::transaction(function () use ($ppmp, $actor, $payload): PpmpItem {
            $quantity = (float) $payload['quantity'];
            $unitCost = (float) $payload['unit_cost'];
            $estimatedBudget = round($quantity * $unitCost, 2);
            $categoryId = ! empty($payload['item_category_id'])
                ? (int) $payload['item_category_id']
                : (int) Item::where('item_id', $payload['item_id'])->value('item_category_id');

            $item = $ppmp->items()->create([
                'item_id' => (int) $payload['item_id'],
                'item_category_id' => $categoryId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'estimated_budget' => $estimatedBudget,
                'procurement_schedule' => $payload['procurement_schedule'],
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $this->recomputeTotalBudget($ppmp);
            $this->recordStatus($ppmp, 'DRAFT', 'DRAFT', $actor, 'PPMP item added');
            $this->recordAudit($actor, 'ADD_ITEM', $ppmp);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateItem(Ppmp $ppmp, PpmpItem $item, User $actor, array $payload): PpmpItem
    {
        $this->assertDraftAndOwned($ppmp, $actor);

        if ((int) $item->ppmp_id !== (int) $ppmp->ppmp_id) {
            throw ValidationException::withMessages([
                'ppmp_item_id' => 'Item does not belong to this PPMP.',
            ]);
        }

        $this->assertUniqueItemSchedule(
            $ppmp,
            (int) $payload['item_id'],
            (string) ($payload['remarks'] ?? $item->remarks ?? ''),
            (string) $payload['procurement_schedule'],
            (int) $item->ppmp_item_id
        );

        return DB::transaction(function () use ($ppmp, $item, $actor, $payload): PpmpItem {
            $quantity = (float) $payload['quantity'];
            $unitCost = (float) $payload['unit_cost'];
            $categoryId = ! empty($payload['item_category_id'])
                ? (int) $payload['item_category_id']
                : (int) ($item->item_category_id ?: Item::where('item_id', $payload['item_id'])->value('item_category_id'));

            $item->fill([
                'item_id' => (int) $payload['item_id'],
                'item_category_id' => $categoryId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'estimated_budget' => round($quantity * $unitCost, 2),
                'procurement_schedule' => $payload['procurement_schedule'],
                'remarks' => $payload['remarks'] ?? null,
            ])->save();

            $this->recomputeTotalBudget($ppmp);
            $this->recordStatus($ppmp, 'DRAFT', 'DRAFT', $actor, 'PPMP item updated');
            $this->recordAudit($actor, 'UPDATE_ITEM', $ppmp);

            return $item;
        });
    }

    public function removeItem(Ppmp $ppmp, PpmpItem $item, User $actor): void
    {
        $this->assertDraftAndOwned($ppmp, $actor);

        if ((int) $item->ppmp_id !== (int) $ppmp->ppmp_id) {
            throw ValidationException::withMessages([
                'ppmp_item_id' => 'Item does not belong to this PPMP.',
            ]);
        }

        DB::transaction(function () use ($ppmp, $item, $actor): void {
            $item->delete();
            $this->recomputeTotalBudget($ppmp);
            $this->recordStatus($ppmp, 'DRAFT', 'DRAFT', $actor, 'PPMP item removed');
            $this->recordAudit($actor, 'REMOVE_ITEM', $ppmp);
        });
    }

    private function assertDraftAndOwned(Ppmp $ppmp, User $actor): void
    {
        if ((int) $ppmp->office_id !== (int) $actor->office_id) {
            throw ValidationException::withMessages([
                'office_id' => 'You can only access PPMP records from your office.',
            ]);
        }

        if (! in_array((string) $ppmp->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only DRAFT or RETURNED_FOR_REVISION PPMP records can be modified.',
            ]);
        }
    }

    private function assertDeletableDraftAndOwned(Ppmp $ppmp, User $actor): void
    {
        if ((int) $ppmp->office_id !== (int) $actor->office_id) {
            throw ValidationException::withMessages([
                'office_id' => 'You can only delete PPMP records from your office.',
            ]);
        }

        if ((string) $ppmp->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'status' => 'Only DRAFT PPMP records can be deleted.',
            ]);
        }
    }

    private function assertUniqueItemSchedule(
        Ppmp $ppmp,
        int $itemId,
        string $specification,
        string $procurementSchedule,
        ?int $exceptPpmpItemId = null
    ): void {
        $normalizedSchedule = strtoupper(trim($procurementSchedule));
        $normalizedSpec = trim($specification);

        $duplicateExists = $ppmp->items()
            ->where('item_id', $itemId)
            ->where('remarks', $normalizedSpec)
            ->where('procurement_schedule', $normalizedSchedule)
            ->when($exceptPpmpItemId !== null, fn ($query) => $query->where('ppmp_item_id', '!=', $exceptPpmpItemId))
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'item_id' => 'This item with the same specification is already planned for the selected procurement quarter. Update the existing line instead.',
                'procurement_schedule' => 'Duplicate item, specification, and procurement schedule combinations are not allowed.',
            ]);
        }
    }

    private function recomputeTotalBudget(Ppmp $ppmp): void
    {
        $total = (float) $ppmp->items()->sum('estimated_budget');

        $ppmp->forceFill([
            'total_estimated_budget' => round($total, 2),
        ])->save();
    }

    private function recordStatus(Ppmp $ppmp, ?string $oldStatus, string $newStatus, User $actor, string $remarks): void
    {
        StatusHistory::query()->create([
            'reference_type' => Ppmp::class,
            'reference_id' => (int) $ppmp->ppmp_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    private function recordAudit(User $actor, string $actionType, Ppmp $ppmp, ?array $additionalDetails = null): void
    {
        $details = [
            'office_id' => $ppmp->office_id,
            'status' => $ppmp->status,
            'fiscal_year' => $ppmp->fiscal_year,
        ];

        if ($additionalDetails) {
            $details = array_merge($details, $additionalDetails);
        }

        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Office.PPMP',
            actionType: $actionType,
            referenceType: Ppmp::class,
            referenceId: (int) $ppmp->ppmp_id,
            details: $details,
            logDomain: 'BUSINESS',
            officeId: (int) $ppmp->office_id,
        );
    }
}
