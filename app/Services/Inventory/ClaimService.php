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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClaimService
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

        $damagedQuantity = max((float) ($payload['quantity_damaged'] ?? 0), 0);
        $missingQuantity = max((float) ($payload['quantity_missing'] ?? 0), 0);
        $disputedQuantity = $damagedQuantity + $missingQuantity;

        if ($disputedQuantity <= 0 && (float) ($payload['quantity_disputed'] ?? 0) > 0) {
            $disputedQuantity = (float) $payload['quantity_disputed'];
            $claimType = (string) ($payload['claim_type'] ?? '');
            if ($claimType === 'DAMAGED_ITEM') {
                $damagedQuantity = $disputedQuantity;
            } else {
                $missingQuantity = $disputedQuantity;
            }
        }

        if ($distributionItem !== null) {
            if ($disputedQuantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity_disputed' => 'Enter at least one missing or damaged quantity for this claim.',
                ]);
            }

            $releasedQuantity = (float) $distributionItem->quantity_released;
            if ($disputedQuantity - $releasedQuantity > 0.0001) {
                throw ValidationException::withMessages([
                    'quantity_disputed' => sprintf('Disputed quantity cannot exceed the released quantity of %.2f.', $releasedQuantity),
                ]);
            }

            $alreadyClaimedQuantity = (float) Claim::query()
                ->where('distribution_item_id', (int) $distributionItem->distribution_item_id)
                ->where('status', '!=', 'REJECTED')
                ->sum('quantity_disputed');
            $remainingClaimableQuantity = max($releasedQuantity - $alreadyClaimedQuantity, 0);

            if ($disputedQuantity - $remainingClaimableQuantity > 0.0001) {
                throw ValidationException::withMessages([
                    'quantity_disputed' => sprintf('Only %.2f unit(s) remain claimable for this released item after existing active claims.', $remainingClaimableQuantity),
                ]);
            }
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

        $claimType = $this->resolveClaimType((string) $payload['claim_type'], $damagedQuantity, $missingQuantity);

        return DB::transaction(function () use ($actor, $payload, $claimCode, $claimType, $disputedQuantity, $damagedQuantity, $missingQuantity): Claim {
            $claim = Claim::query()->create([
                'claim_code' => $claimCode,
                'distribution_record_id' => (int) $payload['distribution_record_id'],
                'distribution_item_id' => ! empty($payload['distribution_item_id']) ? (int) $payload['distribution_item_id'] : null,
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

            return $claim->fresh(['distributionRecord', 'claimant', 'office']) ?? $claim;
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

            return $claim->fresh(['distributionRecord', 'claimant', 'office']) ?? $claim;
        });
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
