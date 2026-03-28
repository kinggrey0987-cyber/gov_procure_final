<?php

namespace App\Services\Executive;

use App\Models\ApprovalHistory;
use App\Models\Ppmp;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PpmpApprovalService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function updateStatus(Ppmp $ppmp, User $actor, string $newStatus, ?string $remarks = null): Ppmp
    {
        $this->assertExecutive($actor);

        $currentStatus = (string) $ppmp->status;
        $newStatus = strtoupper($newStatus);

        $validTransitions = [
            'SUBMITTED' => ['APPROVED', 'REJECTED', 'RETURNED_FOR_REVISION'],
            'APPROVED' => [],
            'REJECTED' => [],
            'RETURNED_FOR_REVISION' => [],
            'DRAFT' => [],
        ];

        if (! in_array($newStatus, $validTransitions[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => sprintf('Invalid PPMP status transition from %s to %s.', $currentStatus, $newStatus),
            ]);
        }

        return DB::transaction(function () use ($ppmp, $actor, $newStatus, $currentStatus, $remarks): Ppmp {
            $approvalDate = $newStatus === 'APPROVED' ? now()->toDateString() : null;
            $approverId = in_array($newStatus, ['APPROVED', 'REJECTED'], true) ? (int) $actor->id : null;

            $ppmp->forceFill([
                'status' => $newStatus,
                'approved_by' => $approverId,
                'date_approved' => $approvalDate,
                'remarks' => $remarks ?? $ppmp->remarks,
            ])->save();

            $this->recordStatus($ppmp, $currentStatus, $newStatus, $actor, $remarks);
            $this->recordApproval($ppmp, $newStatus, $actor, $remarks);
            $this->recordAudit($ppmp, $currentStatus, $newStatus, $actor);
            $this->notifyPreparer($ppmp, $newStatus);

            return $ppmp->fresh(['office', 'preparer', 'approver', 'items']) ?? $ppmp;
        });
    }

    private function assertExecutive(User $actor): void
    {
        if (! $actor->isExecutive()) {
            throw ValidationException::withMessages([
                'role' => 'Only EXEC_OFFICER can perform PPMP executive signoff.',
            ]);
        }
    }

    private function recordStatus(Ppmp $ppmp, string $oldStatus, string $newStatus, User $actor, ?string $remarks): void
    {
        StatusHistory::query()->create([
            'reference_type' => Ppmp::class,
            'reference_id' => (int) $ppmp->ppmp_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => (int) $actor->id,
            'remarks' => $remarks ?: 'Executive PPMP signoff recorded.',
            'changed_at' => now(),
        ]);
    }

    private function recordApproval(Ppmp $ppmp, string $status, User $actor, ?string $remarks): void
    {
        ApprovalHistory::query()->create([
            'reference_type' => Ppmp::class,
            'reference_id' => (int) $ppmp->ppmp_id,
            'approved_by' => (int) $actor->id,
            'status' => $status,
            'remarks' => $remarks ?: 'Executive PPMP signoff recorded.',
            'approved_at' => now(),
        ]);
    }

    private function recordAudit(Ppmp $ppmp, string $oldStatus, string $newStatus, User $actor): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Executive.PPMP',
            actionType: 'SIGNOFF',
            referenceType: Ppmp::class,
            referenceId: (int) $ppmp->ppmp_id,
            details: [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'office_id' => (int) $ppmp->office_id,
                'fiscal_year' => (int) $ppmp->fiscal_year,
            ],
            logDomain: 'BUSINESS',
            officeId: (int) $ppmp->office_id,
        );
    }

    private function notifyPreparer(Ppmp $ppmp, string $status): void
    {
        if (! $ppmp->prepared_by) {
            return;
        }

        $preparer = User::query()->find((int) $ppmp->prepared_by);
        if (! $preparer) {
            return;
        }

        $message = match ($status) {
            'APPROVED' => 'Your PPMP has been approved by the Executive Office.',
            'REJECTED' => 'Your PPMP has been rejected by the Executive Office.',
            'RETURNED_FOR_REVISION' => 'Your PPMP was returned for revision by the Executive Office.',
            default => 'Your PPMP status has been updated by the Executive Office.',
        };

        $this->notificationService->notifyUser(
            user: $preparer,
            title: 'PPMP Executive Decision',
            message: $message,
            notificationType: 'EXEC_PPMP_SIGNOFF',
            referenceType: Ppmp::class,
            referenceId: (int) $ppmp->ppmp_id,
        );
    }
}
