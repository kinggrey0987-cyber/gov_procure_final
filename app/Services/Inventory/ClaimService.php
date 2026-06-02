<?php

namespace App\Services\Inventory;

use App\Models\ApprovalHistory;
use App\Models\Claim;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClaimService
{
    private const ACTIVE_STATUSES = ['OPEN', 'IN_REVIEW', 'PENDING_REPLACEMENT', 'PARTIALLY_RESOLVED'];

    private const FOLLOW_UP_SOURCE_TYPE = 'CLAIM_REPLACEMENT_FOLLOW_UP';

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
        private readonly PurchaseRequestService $purchaseRequestService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createClaim(User $actor, array $payload): Claim
    {
        if (!$actor->isOfficeUser()) {
            throw ValidationException::withMessages([
                'role' => 'Only office users can file claims for their own distributed supplies.',
            ]);
        }

        $distributionRecord = $payload['distribution_record'];

        if (!$distributionRecord instanceof DistributionRecord) {
            throw ValidationException::withMessages([
                'distribution_record_id' => 'Distribution record context is required.',
            ]);
        }

        if ((int) $distributionRecord->office_id !== (int) $payload['office_id']) {
            throw ValidationException::withMessages([
                'office_id' => 'Claim office must match the distribution office.',
            ]);
        }

        $parentClaim = $this->resolveParentClaim($payload, $distributionRecord);

        $distributionItem = null;
        if (! empty($payload['distribution_item_id'])) {
            $distributionItem = $distributionRecord->items()
                ->where('distribution_item_id', (int) $payload['distribution_item_id'])
                ->first();

            if (! $distributionItem) {
                throw ValidationException::withMessages([
                    'distribution_item_id' => 'Selected distribution item does not belong to the chosen distribution record.',
                ]);
            }
        }

        $submittedClaimType = (string) ($payload['claim_type'] ?? '');
        $submittedDisputedQuantity = max((float) ($payload['quantity_disputed'] ?? 0), 0);
        $damagedQuantity = max((float) ($payload['quantity_damaged'] ?? 0), 0);
        $missingQuantity = max((float) ($payload['quantity_missing'] ?? 0), 0);
        $disputedQuantity = $damagedQuantity + $missingQuantity;

        if ($submittedDisputedQuantity > $disputedQuantity) {
            $disputedQuantity = $submittedDisputedQuantity;

            if ($submittedClaimType === 'DAMAGED_ITEM' && $damagedQuantity <= 0 && $missingQuantity <= 0) {
                $damagedQuantity = $disputedQuantity;
            } elseif (in_array($submittedClaimType, ['MISSING_ITEM', 'SHORTAGE'], true) && $damagedQuantity <= 0 && $missingQuantity <= 0) {
                $missingQuantity = $disputedQuantity;
            }
        }

        if ($distributionItem !== null) {
            if ($disputedQuantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity_disputed' => 'Enter at least one issue quantity for this claim.',
                ]);
            }

            $releasedQuantity = (float) $distributionItem->quantity_released;
            if ($disputedQuantity - $releasedQuantity > 0.0001) {
                throw ValidationException::withMessages([
                    'quantity_disputed' => sprintf('Disputed quantity cannot exceed the released quantity of %.2f.', $releasedQuantity),
                ]);
            }

            $this->ensureNoActiveDuplicateItemClaim($distributionItem->distribution_item_id, $parentClaim);
        }

        if ((int) $actor->office_id !== (int) $payload['office_id']) {
            throw ValidationException::withMessages([
                'office_id' => 'Office users can only file claims for their own office.',
            ]);
        }

        if (!(in_array((string) $distributionRecord->status, ['RELEASED', 'RECEIVED'], true))) {
            throw ValidationException::withMessages([
                'distribution_record_id' => 'Claims can only be filed for RELEASED or RECEIVED distribution records.',
            ]);
        }

        $claimCode = $payload['claim_code'] ?? $this->generateClaimCode();

        $claimType = $this->resolveClaimType($submittedClaimType, $damagedQuantity, $missingQuantity);

        return DB::transaction(function () use ($actor, $payload, $claimCode, $claimType, $disputedQuantity, $damagedQuantity, $missingQuantity, $parentClaim): Claim {
            $claim = Claim::query()->create([
                'claim_code' => $claimCode,
                'distribution_record_id' => (int) $payload['distribution_record_id'],
                'distribution_item_id' => ! empty($payload['distribution_item_id']) ? (int) $payload['distribution_item_id'] : null,
                'parent_claim_id' => $parentClaim?->claims_id,
                'source_type' => $parentClaim instanceof Claim ? self::FOLLOW_UP_SOURCE_TYPE : null,
                'claimed_by' => (int) $actor->id,
                'office_id' => (int) $payload['office_id'],
                'claim_type' => $claimType,
                'claim_date' => $payload['claim_date'],
                'claim_location' => $payload['claim_location'],
                'quantity_disputed' => $disputedQuantity,
                'quantity_damaged' => $damagedQuantity,
                'quantity_missing' => $missingQuantity,
                'remarks' => $payload['remarks'] ?? null,
                'status' => 'OPEN',
                'resolution_type' => null,
            ]);

            $this->recordStatus($claim, null, 'OPEN', $actor, 'Claim filed.');
            $this->recordAudit($actor, 'CREATE', $claim, []);

            $record = $claim->distributionRecord;
            if ($record?->preparer instanceof User) {
                $this->notificationService->notifyUser(
                    user: $record->preparer,
                    title: 'New Distribution Claim',
                    message: 'A claim was filed for a distribution record you prepared.',
                    notificationType: 'CLAIM_FILED',
                    referenceType: Claim::class,
                    referenceId: (int) $claim->claims_id,
                );
            }

            return $claim->fresh(['distributionRecord', 'claimant', 'office', 'parentClaim']) ?? $claim;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateClaimStatus(Claim $claim, User $actor, array $payload): Claim
    {
        if (!$actor->isGsoOfficer()) {
            throw ValidationException::withMessages([
                'role' => 'Only GSO officers can process claim statuses.',
            ]);
        }

        $newStatus = (string) $payload['status'];
        $currentStatus = (string) $claim->status;

        $validTransitions = [
            'OPEN' => ['IN_REVIEW', 'REJECTED', 'RESOLVED', 'PENDING_REPLACEMENT', 'PARTIALLY_RESOLVED'],
            'IN_REVIEW' => ['REJECTED', 'RESOLVED', 'PENDING_REPLACEMENT', 'PARTIALLY_RESOLVED'],
            'PENDING_REPLACEMENT' => ['REJECTED', 'RESOLVED', 'PARTIALLY_RESOLVED'],
            'PARTIALLY_RESOLVED' => ['REJECTED', 'RESOLVED', 'PENDING_REPLACEMENT', 'PARTIALLY_RESOLVED'],
            'REJECTED' => [],
            'RESOLVED' => [],
        ];

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => sprintf('Invalid claim status transition from %s to %s.', $currentStatus, $newStatus),
            ]);
        }

        return DB::transaction(function () use ($claim, $actor, $payload, $currentStatus, $newStatus): Claim {
            $resolutionType = isset($payload['resolution_type']) ? strtoupper((string) $payload['resolution_type']) : null;
            $replacementQuantity = round((float) ($payload['replacement_quantity'] ?? 0), 2);
            $appliedReplacementQuantity = in_array($resolutionType, ['REPLACEMENT_RELEASED', 'PARTIAL_REPLACEMENT'], true)
                ? $replacementQuantity
                : 0.0;

            if (in_array($resolutionType, ['REPLACEMENT_RELEASED', 'PARTIAL_REPLACEMENT'], true)) {
                $this->releaseClaimReplacement($claim, $actor, $resolutionType, $replacementQuantity, (string) ($payload['remarks'] ?? ''));
            }

            if ($newStatus === 'PENDING_REPLACEMENT' && $this->claimNeedsReplacementProcurement($claim)) {
                $this->purchaseRequestService->createFromPendingClaimReplacement($claim, $actor, (string) ($payload['remarks'] ?? ''));
            }

            $claim->forceFill([
                'status' => $newStatus,
                'resolution_type' => $resolutionType ?: $claim->resolution_type,
                'replacement_quantity' => (float) $claim->replacement_quantity + $appliedReplacementQuantity,
                'remarks' => $payload['remarks'] ?? $claim->remarks,
            ])->save();

            $this->recordStatus($claim, $currentStatus, $newStatus, $actor, 'Claim status updated.');
            $this->recordApproval($claim, $actor, $newStatus, 'Claim status decision recorded.');
            $this->recordAudit($actor, 'UPDATE_STATUS', $claim, [
                'old_status' => $currentStatus,
                'new_status' => $newStatus,
            ]);

            if ($newStatus === 'RESOLVED') {
                $this->resolveParentClaimFromFollowUp($claim, $actor, (string) ($payload['remarks'] ?? ''));
            }

            if ($claim->claimant instanceof User) {
                $this->notificationService->notifyUser(
                    user: $claim->claimant,
                    title: 'Claim Status Updated',
                    message: sprintf('Your claim %s is now %s.', $claim->claim_code, $newStatus),
                    notificationType: 'CLAIM_STATUS_UPDATED',
                    referenceType: Claim::class,
                    referenceId: (int) $claim->claims_id,
                );
            }

            return $claim->fresh(['distributionRecord', 'claimant', 'office', 'parentClaim']) ?? $claim;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveParentClaim(array $payload, DistributionRecord $distributionRecord): ?Claim
    {
        $parentClaimId = (int) ($payload['parent_claim_id'] ?? 0);
        $sourceType = strtoupper(trim((string) ($payload['source_type'] ?? '')));

        if ($parentClaimId <= 0) {
            if ($sourceType === self::FOLLOW_UP_SOURCE_TYPE) {
                throw ValidationException::withMessages([
                    'parent_claim_id' => 'A follow-up claim must reference the original unresolved claim.',
                ]);
            }

            return null;
        }

        $parentClaim = Claim::query()->find($parentClaimId);

        if (! $parentClaim instanceof Claim) {
            throw ValidationException::withMessages([
                'parent_claim_id' => 'Original claim was not found.',
            ]);
        }

        if (in_array((string) $parentClaim->status, ['RESOLVED', 'REJECTED'], true)) {
            throw ValidationException::withMessages([
                'parent_claim_id' => 'Follow-up claims can only reference unresolved claims.',
            ]);
        }

        if ((int) $parentClaim->office_id !== (int) $distributionRecord->office_id) {
            throw ValidationException::withMessages([
                'parent_claim_id' => 'Follow-up claim must belong to the same office as the original claim.',
            ]);
        }

        if (! empty($payload['distribution_item_id'])
            && $parentClaim->distribution_item_id !== null
            && (int) $parentClaim->distribution_item_id !== (int) $payload['distribution_item_id']
        ) {
            throw ValidationException::withMessages([
                'distribution_item_id' => 'Follow-up claim must reference the same item line as the original claim.',
            ]);
        }

        if ($sourceType !== '' && $sourceType !== self::FOLLOW_UP_SOURCE_TYPE) {
            throw ValidationException::withMessages([
                'source_type' => 'Claim follow-up source type is invalid.',
            ]);
        }

        return $parentClaim;
    }

    private function ensureNoActiveDuplicateItemClaim(int $distributionItemId, ?Claim $parentClaim): void
    {
        if ($parentClaim instanceof Claim) {
            return;
        }

        $hasActiveClaim = Claim::query()
            ->where('distribution_item_id', $distributionItemId)
            ->whereNull('parent_claim_id')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();

        if ($hasActiveClaim) {
            throw ValidationException::withMessages([
                'quantity_disputed' => 'An active claim already exists for this item line. Resolve it before filing another claim, or file a follow-up claim from the existing claim.',
            ]);
        }
    }

    private function resolveParentClaimFromFollowUp(Claim $claim, User $actor, string $remarks): void
    {
        if ($claim->parent_claim_id === null || (string) $claim->source_type !== self::FOLLOW_UP_SOURCE_TYPE) {
            return;
        }

        $parentClaim = Claim::query()->find((int) $claim->parent_claim_id);

        if (! $parentClaim instanceof Claim || in_array((string) $parentClaim->status, ['RESOLVED', 'REJECTED'], true)) {
            return;
        }

        $oldStatus = (string) $parentClaim->status;
        $parentRemarks = trim($remarks) !== ''
            ? $remarks
            : sprintf('Resolved through follow-up claim %s.', (string) $claim->claim_code);

        $parentClaim->forceFill([
            'status' => 'RESOLVED',
            'resolution_type' => 'FOLLOW_UP_RESOLVED',
            'remarks' => $parentRemarks,
        ])->save();

        $this->recordStatus($parentClaim, $oldStatus, 'RESOLVED', $actor, sprintf('Parent claim resolved through follow-up claim %s.', (string) $claim->claim_code));
        $this->recordApproval($parentClaim, $actor, 'RESOLVED', 'Parent claim resolution cascaded from resolved follow-up claim.');
        $this->recordAudit($actor, 'UPDATE_STATUS', $parentClaim, [
            'old_status' => $oldStatus,
            'new_status' => 'RESOLVED',
            'resolved_by_follow_up_claim_id' => (int) $claim->claims_id,
        ]);
    }

    private function claimNeedsReplacementProcurement(Claim $claim): bool
    {
        $claim->loadMissing('distributionItem');

        $distributionItem = $claim->distributionItem;
        if ($distributionItem === null) {
            return true;
        }

        $remainingDisputed = max((float) $claim->quantity_disputed - (float) $claim->replacement_quantity, 0);
        if ($remainingDisputed <= 0) {
            return false;
        }

        $availableStock = (float) (Inventory::query()
            ->where('item_id', (int) $distributionItem->item_id)
            ->value('available_stock') ?? 0);

        return $availableStock < $remainingDisputed;
    }

    private function releaseClaimReplacement(Claim $claim, User $actor, string $resolutionType, float $replacementQuantity, string $remarks): void
    {
        $claim->loadMissing('distributionItem.item.inventory');

        $distributionItem = $claim->distributionItem;
        if ($distributionItem === null) {
            throw ValidationException::withMessages([
                'replacement_quantity' => 'Replacement release requires a linked claimed distribution item.',
            ]);
        }

        $disputedQuantity = (float) $claim->quantity_disputed;
        $alreadyReplaced = (float) $claim->replacement_quantity;
        $remainingDisputed = max($disputedQuantity - $alreadyReplaced, 0);

        if ($replacementQuantity - $remainingDisputed > 0.0001) {
            throw ValidationException::withMessages([
                'replacement_quantity' => sprintf('Replacement quantity cannot exceed the remaining disputed quantity of %.2f.', $remainingDisputed),
            ]);
        }

        if ($resolutionType === 'REPLACEMENT_RELEASED' && abs($replacementQuantity - $remainingDisputed) > 0.0001) {
            throw ValidationException::withMessages([
                'replacement_quantity' => 'Full replacement resolution must cover the remaining disputed quantity.',
            ]);
        }

        if ($resolutionType === 'PARTIAL_REPLACEMENT' && $replacementQuantity >= $remainingDisputed) {
            throw ValidationException::withMessages([
                'replacement_quantity' => 'Use resolved replacement when the replacement covers the full remaining disputed quantity.',
            ]);
        }

        $itemId = (int) $distributionItem->item_id;
        $availableStock = (float) (Inventory::query()->where('item_id', $itemId)->value('available_stock') ?? 0);

        if ($availableStock < $replacementQuantity) {
            throw ValidationException::withMessages([
                'replacement_quantity' => sprintf('Only %.2f replacement stock is available. Use partial or pending replacement instead.', $availableStock),
            ]);
        }

        $this->inventoryService->releaseClaimReplacementStock(
            itemId: $itemId,
            quantity: $replacementQuantity,
            actor: $actor,
            referenceType: Claim::class,
            referenceId: (int) $claim->claims_id,
            remarks: trim($remarks) !== '' ? $remarks : 'Replacement stock released for claim resolution.',
        );
    }

    private function generateClaimCode(): string
    {
        $last = Claim::query()->latest('claims_id')->first();
        $next = ((int) ($last?->claims_id ?? 0)) + 1;

        return sprintf('CLM-%s-%05d', date('Y'), $next);
    }

    private function resolveClaimType(string $submittedType, float $damagedQuantity, float $missingQuantity): string
    {
        if (in_array($submittedType, ['MULTIPLE_ISSUES', 'WRONG_ITEM', 'QUANTITY_MISMATCH'], true)) {
            return $submittedType;
        }

        if ($damagedQuantity > 0 && $missingQuantity > 0) {
            return 'MISSING_AND_DAMAGED';
        }

        if ($damagedQuantity > 0) {
            return 'DAMAGED_ITEM';
        }

        if ($missingQuantity > 0) {
            return 'MISSING_ITEM';
        }

        return $submittedType;
    }

    private function recordStatus(Claim $claim, ?string $oldStatus, string $newStatus, User $actor, string $remarks): void
    {
        StatusHistory::query()->create([
            'reference_type' => Claim::class,
            'reference_id' => (int) $claim->claims_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => (int) $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    private function recordApproval(Claim $claim, User $actor, string $status, string $remarks): void
    {
        ApprovalHistory::query()->create([
            'reference_type' => Claim::class,
            'reference_id' => (int) $claim->claims_id,
            'approved_by' => (int) $actor->id,
            'status' => $status,
            'remarks' => $remarks,
            'approved_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function recordAudit(User $actor, string $actionType, Claim $claim, array $details): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Distribution.Claim',
            actionType: $actionType,
            referenceType: Claim::class,
            referenceId: (int) $claim->claims_id,
            details: array_merge([
                'claim_code' => $claim->claim_code,
                'status' => $claim->status,
                'distribution_record_id' => $claim->distribution_record_id,
            ], $details),
            logDomain: 'BUSINESS',
            officeId: $claim->office_id,
        );
    }
}
