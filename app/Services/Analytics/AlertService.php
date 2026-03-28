<?php

namespace App\Services\Analytics;

use App\Models\Alert;
use App\Models\ApprovalHistory;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AlertService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): Alert
    {
        return DB::transaction(function () use ($actor, $payload): Alert {
            $alert = Alert::query()->create([
                'alert_type' => strtoupper((string) $payload['alert_type']),
                'severity' => strtoupper((string) ($payload['severity'] ?? 'MEDIUM')),
                'anomaly_id' => $payload['anomaly_id'] ?? null,
                'supply_request_id' => $payload['supply_request_id'] ?? null,
                'purchase_order_id' => $payload['purchase_order_id'] ?? null,
                'supplier_id' => $payload['supplier_id'] ?? null,
                'office_id' => $payload['office_id'] ?? null,
                'alert_message' => (string) $payload['alert_message'],
                'detected_at' => $payload['detected_at'] ?? now(),
                'reviewed_by' => null,
                'status' => 'OPEN',
            ]);

            $this->recordStatus($alert, null, 'OPEN', $actor, 'Alert created.');
            $this->recordAudit($actor, 'CREATE', $alert, []);

            $this->notifyRelevantActors($alert);

            return $alert;
        });
    }

    public function updateStatus(Alert $alert, User $actor, string $newStatus, ?string $remarks = null): Alert
    {
        if (!$actor->isExecutive()) {
            throw ValidationException::withMessages([
                'role' => 'Only EXEC_OFFICER can review alert statuses.',
            ]);
        }

        $currentStatus = (string) $alert->status;
        $newStatus = strtoupper($newStatus);

        $validTransitions = [
            'OPEN' => ['IN_REVIEW', 'RESOLVED', 'DISMISSED'],
            'IN_REVIEW' => ['RESOLVED', 'DISMISSED'],
            'RESOLVED' => [],
            'DISMISSED' => [],
        ];

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => sprintf('Invalid alert status transition from %s to %s.', $currentStatus, $newStatus),
            ]);
        }

        return DB::transaction(function () use ($alert, $actor, $newStatus, $currentStatus, $remarks): Alert {
            $alert->forceFill([
                'status' => $newStatus,
                'reviewed_by' => (int) $actor->id,
                'alert_message' => $remarks ? ($alert->alert_message.' | '.$remarks) : $alert->alert_message,
            ])->save();

            $this->recordStatus($alert, $currentStatus, $newStatus, $actor, 'Alert status updated.');
            $this->recordApproval($alert, $actor, $newStatus, 'Executive alert review recorded.');
            $this->recordAudit($actor, 'UPDATE_STATUS', $alert, [
                'old_status' => $currentStatus,
                'new_status' => $newStatus,
            ]);

            return $alert->fresh(['anomaly', 'reviewer']) ?? $alert;
        });
    }

    private function notifyRelevantActors(Alert $alert): void
    {
        if (in_array((string) $alert->alert_type, ['LOW_STOCK', 'STOCK_RISK', 'DELAYED_DELIVERY'], true)) {
            $gsoUsers = User::query()->whereHas('role', fn ($q) => $q->where('code', 'GSO_OFFICER'))->get();
            $this->notificationService->notifyUsers(
                users: $gsoUsers,
                title: 'Operational Alert',
                message: $alert->alert_message,
                notificationType: 'OPERATIONAL_ALERT',
                referenceType: Alert::class,
                referenceId: (int) $alert->alerts_id,
            );
        }

        if (in_array((string) $alert->alert_type, ['PROCUREMENT_DELAY', 'SUPPLIER_RISK', 'WORKFLOW_BLOCKER'], true)) {
            $procUsers = User::query()->whereHas('role', fn ($q) => $q->where('code', 'PROC_OFFICER'))->get();
            $this->notificationService->notifyUsers(
                users: $procUsers,
                title: 'Procurement Alert',
                message: $alert->alert_message,
                notificationType: 'PROCUREMENT_ALERT',
                referenceType: Alert::class,
                referenceId: (int) $alert->alerts_id,
            );
        }

        $execUsers = User::query()->whereHas('role', fn ($q) => $q->where('code', 'EXEC_OFFICER'))->get();
        $this->notificationService->notifyUsers(
            users: $execUsers,
            title: 'Executive Alert Review',
            message: $alert->alert_message,
            notificationType: 'EXEC_ALERT_REVIEW',
            referenceType: Alert::class,
            referenceId: (int) $alert->alerts_id,
        );
    }

    private function recordStatus(Alert $alert, ?string $oldStatus, string $newStatus, User $actor, string $remarks): void
    {
        StatusHistory::query()->create([
            'reference_type' => Alert::class,
            'reference_id' => (int) $alert->alerts_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => (int) $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    private function recordApproval(Alert $alert, User $actor, string $status, string $remarks): void
    {
        ApprovalHistory::query()->create([
            'reference_type' => Alert::class,
            'reference_id' => (int) $alert->alerts_id,
            'approved_by' => (int) $actor->id,
            'status' => $status,
            'remarks' => $remarks,
            'approved_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function recordAudit(User $actor, string $actionType, Alert $alert, array $details): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Executive.Alert',
            actionType: $actionType,
            referenceType: Alert::class,
            referenceId: (int) $alert->alerts_id,
            details: array_merge([
                'alert_type' => $alert->alert_type,
                'severity' => $alert->severity,
                'status' => $alert->status,
            ], $details),
            logDomain: 'BUSINESS',
            officeId: $alert->office_id,
        );
    }
}
