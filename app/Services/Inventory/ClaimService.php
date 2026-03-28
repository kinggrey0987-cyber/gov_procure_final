<?php

namespace App\Services\Inventory;

use App\Models\ApprovalHistory;
use App\Models\Claim;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClaimService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createClaim(User $actor, array $payload): Claim
    {
        $distributionRecord = $payload['distribution_record'];

        if (!$distributionRecord instanceof \App\Models\DistributionRecord) {
            throw ValidationException::withMessages([
                'distribution_record_id' => 'Distribution record context is required.',
            ]);
        }

        if ((int) $distributionRecord->office_id !== (int) $payload['office_id']) {
            throw ValidationException::withMessages([
                'office_id' => 'Claim office must match the distribution office.',
            ]);
        }

        if ($actor->isOfficeUser() && (int) $actor->office_id !== (int) $payload['office_id']) {
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

        return DB::transaction(function () use ($actor, $payload, $claimCode): Claim {
            $claim = Claim::query()->create([
                'claim_code' => $claimCode,
                'distribution_record_id' => (int) $payload['distribution_record_id'],
                'claimed_by' => (int) $actor->id,
                'office_id' => (int) $payload['office_id'],
                'claim_type' => $payload['claim_type'],
                'claim_date' => $payload['claim_date'],
                'claim_location' => $payload['claim_location'],
                'remarks' => $payload['remarks'] ?? null,
                'status' => 'OPEN',
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
            'OPEN' => ['IN_REVIEW', 'REJECTED', 'RESOLVED'],
            'IN_REVIEW' => ['REJECTED', 'RESOLVED'],
            'REJECTED' => [],
            'RESOLVED' => [],
        ];

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => sprintf('Invalid claim status transition from %s to %s.', $currentStatus, $newStatus),
            ]);
        }

        return DB::transaction(function () use ($claim, $actor, $payload, $currentStatus, $newStatus): Claim {
            $claim->forceFill([
                'status' => $newStatus,
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

    private function generateClaimCode(): string
    {
        $last = Claim::query()->latest('claims_id')->first();
        $next = ((int) ($last?->claims_id ?? 0)) + 1;

        return sprintf('CLM-%s-%05d', date('Y'), $next);
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
