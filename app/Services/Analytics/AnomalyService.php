<?php

namespace App\Services\Analytics;

use App\Enums\UserRole;
use App\Models\Anomaly;
use App\Models\ApprovalHistory;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnomalyService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): Anomaly
    {
        $this->assertExecutive($actor);

        $referenceType = (string) $payload['reference_type'];
        $referenceId = (int) $payload['reference_id'];
        $this->assertReferenceExists($referenceType, $referenceId);

        return DB::transaction(function () use ($actor, $payload): Anomaly {
            $existing = $this->findExistingActiveAnomaly($payload, true);

            if ($existing instanceof Anomaly) {
                return $this->refreshExistingAnomaly($existing, $payload);
            }

            $anomaly = Anomaly::query()->create([
                'anomaly_type' => strtoupper((string) $payload['anomaly_type']),
                'reference_type' => (string) $payload['reference_type'],
                'reference_id' => (int) $payload['reference_id'],
                'item_id' => $payload['item_id'] ?? null,
                'office_id' => $payload['office_id'] ?? null,
                'detected_value' => $payload['detected_value'] ?? null,
                'expected_value' => $payload['expected_value'] ?? null,
                'threshold_value' => $payload['threshold_value'] ?? null,
                'z_score' => $payload['z_score'] ?? null,
                'detection_method' => $payload['detection_method'] ?? null,
                'severity' => strtoupper((string) ($payload['severity'] ?? 'MEDIUM')),
                'status' => 'OPEN',
                'detected_at' => $payload['detected_at'] ?? now(),
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $this->recordStatus($anomaly, null, 'OPEN', $actor, 'Anomaly created.');
            $this->recordAudit($actor, 'CREATE', $anomaly, []);

            return $anomaly;
        });
    }

    public function updateStatus(Anomaly $anomaly, User $actor, string $newStatus, ?string $remarks = null): Anomaly
    {
        $this->assertExecutive($actor);

        $currentStatus = (string) $anomaly->status;
        $newStatus = strtoupper($newStatus);

        $validTransitions = [
            'OPEN' => ['IN_REVIEW', 'RESOLVED', 'DISMISSED'],
            'IN_REVIEW' => ['RESOLVED', 'DISMISSED'],
            'RESOLVED' => [],
            'DISMISSED' => [],
        ];

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => sprintf('Invalid anomaly status transition from %s to %s.', $currentStatus, $newStatus),
            ]);
        }

        return DB::transaction(function () use ($anomaly, $actor, $newStatus, $currentStatus, $remarks): Anomaly {
            $anomaly->forceFill([
                'status' => $newStatus,
                'remarks' => $remarks ?? $anomaly->remarks,
            ])->save();

            $this->recordStatus($anomaly, $currentStatus, $newStatus, $actor, 'Anomaly status updated.');
            $this->recordApproval($anomaly, $actor, $newStatus, 'Executive anomaly review recorded.');
            $this->recordAudit($actor, 'UPDATE_STATUS', $anomaly, [
                'old_status' => $currentStatus,
                'new_status' => $newStatus,
            ]);

            if ($anomaly->office_id) {
                $recipients = User::query()->where('office_id', $anomaly->office_id)->get();
                $this->notificationService->notifyUsers(
                    users: $recipients,
                    title: 'Anomaly Status Updated',
                    message: sprintf('Anomaly #%d is now %s.', $anomaly->anomaly_id, $newStatus),
                    notificationType: 'ANOMALY_STATUS_UPDATED',
                    referenceType: Anomaly::class,
                    referenceId: (int) $anomaly->anomaly_id,
                );
            }

            return $anomaly->fresh() ?? $anomaly;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createOrUpdateSystemDetected(array $payload): Anomaly
    {
        $referenceType = (string) $payload['reference_type'];
        $referenceId = (int) $payload['reference_id'];
        $this->assertReferenceExists($referenceType, $referenceId);

        return DB::transaction(function () use ($payload): Anomaly {
            $anomalyType = strtoupper((string) $payload['anomaly_type']);
            $detectionMethod = $payload['detection_method'] ?? null;

            $existing = $this->findExistingActiveAnomaly($payload, true);

            if ($existing instanceof Anomaly) {
                return $this->refreshExistingAnomaly($existing, $payload);
            }

            $anomaly = Anomaly::query()->create([
                'anomaly_type' => $anomalyType,
                'reference_type' => (string) $payload['reference_type'],
                'reference_id' => (int) $payload['reference_id'],
                'item_id' => $payload['item_id'] ?? null,
                'office_id' => $payload['office_id'] ?? null,
                'detected_value' => $payload['detected_value'] ?? null,
                'expected_value' => $payload['expected_value'] ?? null,
                'threshold_value' => $payload['threshold_value'] ?? null,
                'z_score' => $payload['z_score'] ?? null,
                'detection_method' => $detectionMethod,
                'severity' => strtoupper((string) ($payload['severity'] ?? 'MEDIUM')),
                'status' => 'OPEN',
                'detected_at' => $payload['detected_at'] ?? now(),
                'remarks' => $payload['remarks'] ?? null,
            ]);

            StatusHistory::query()->create([
                'reference_type' => Anomaly::class,
                'reference_id' => (int) $anomaly->anomaly_id,
                'old_status' => null,
                'new_status' => 'OPEN',
                'changed_by' => null,
                'remarks' => 'System-detected anomaly created.',
                'changed_at' => now(),
            ]);

            $humanType = Anomaly::humanizeType($anomalyType);

            $this->notificationService->notifyRole(
                role: UserRole::EXECUTIVE_OFFICER,
                title: 'New Anomaly Detected',
                message: sprintf('%s was detected and needs executive review.', $humanType),
                notificationType: 'EXEC_ANOMALY_DETECTED',
                referenceType: Anomaly::class,
                referenceId: (int) $anomaly->anomaly_id,
            );

            return $anomaly;
        });
    }

    public function collapseActiveDuplicates(): int
    {
        $duplicateGroups = Anomaly::query()
            ->whereIn('status', ['OPEN', 'IN_REVIEW'])
            ->selectRaw('anomaly_type, reference_type, reference_id, item_id, office_id, detection_method, MAX(anomaly_id) as keep_id, COUNT(*) as total')
            ->groupBy('anomaly_type', 'reference_type', 'reference_id', 'item_id', 'office_id', 'detection_method')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $deduplicated = 0;

        foreach ($duplicateGroups as $group) {
            $query = Anomaly::query()
                ->where('anomaly_type', (string) $group->anomaly_type)
                ->where('reference_type', (string) $group->reference_type)
                ->where('reference_id', (int) $group->reference_id)
                ->whereIn('status', ['OPEN', 'IN_REVIEW']);

            $this->applyNullableConstraint($query, 'item_id', $group->item_id);
            $this->applyNullableConstraint($query, 'office_id', $group->office_id);
            $this->applyNullableConstraint($query, 'detection_method', $group->detection_method);

            $duplicateIds = (clone $query)
                ->where('anomaly_id', '!=', (int) $group->keep_id)
                ->pluck('anomaly_id')
                ->map(fn ($value): int => (int) $value)
                ->all();

            if ($duplicateIds === []) {
                continue;
            }

            Anomaly::query()
                ->whereIn('anomaly_id', $duplicateIds)
                ->update([
                    'status' => 'DISMISSED',
                    'remarks' => 'Auto-dismissed duplicate anomaly record after idempotency cleanup.',
                ]);

            foreach ($duplicateIds as $anomalyId) {
                StatusHistory::query()->create([
                    'reference_type' => Anomaly::class,
                    'reference_id' => $anomalyId,
                    'old_status' => 'OPEN',
                    'new_status' => 'DISMISSED',
                    'changed_by' => null,
                    'remarks' => 'Duplicate anomaly auto-dismissed by cleanup routine.',
                    'changed_at' => now(),
                ]);
            }

            $deduplicated += count($duplicateIds);
        }

        return $deduplicated;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findExistingActiveAnomaly(array $payload, bool $allowContextFallback): ?Anomaly
    {
        $anomalyType = strtoupper((string) $payload['anomaly_type']);
        $detectionMethod = $payload['detection_method'] ?? null;

        $exact = Anomaly::query()
            ->where('anomaly_type', $anomalyType)
            ->where('reference_type', (string) $payload['reference_type'])
            ->where('reference_id', (int) $payload['reference_id'])
            ->where('item_id', $payload['item_id'] ?? null)
            ->where('office_id', $payload['office_id'] ?? null)
            ->where('detection_method', $detectionMethod)
            ->whereIn('status', ['OPEN', 'IN_REVIEW'])
            ->latest('detected_at')
            ->latest('anomaly_id')
            ->first();

        if ($exact instanceof Anomaly) {
            return $exact;
        }

        if (! $allowContextFallback || $detectionMethod === null) {
            return null;
        }

        $fallback = Anomaly::query()
            ->where('anomaly_type', $anomalyType)
            ->where('reference_type', (string) $payload['reference_type'])
            ->where('detection_method', $detectionMethod)
            ->whereIn('status', ['OPEN', 'IN_REVIEW']);

        $this->applyNullableConstraint($fallback, 'item_id', $payload['item_id'] ?? null);
        $this->applyNullableConstraint($fallback, 'office_id', $payload['office_id'] ?? null);

        return $fallback
            ->latest('detected_at')
            ->latest('anomaly_id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function refreshExistingAnomaly(Anomaly $existing, array $payload): Anomaly
    {
        $existing->forceFill([
            'reference_type' => (string) ($payload['reference_type'] ?? $existing->reference_type),
            'reference_id' => (int) ($payload['reference_id'] ?? $existing->reference_id),
            'item_id' => $payload['item_id'] ?? $existing->item_id,
            'office_id' => $payload['office_id'] ?? $existing->office_id,
            'detected_value' => $payload['detected_value'] ?? $existing->detected_value,
            'expected_value' => $payload['expected_value'] ?? $existing->expected_value,
            'threshold_value' => $payload['threshold_value'] ?? $existing->threshold_value,
            'z_score' => $payload['z_score'] ?? $existing->z_score,
            'severity' => strtoupper((string) ($payload['severity'] ?? $existing->severity ?? 'MEDIUM')),
            'detected_at' => $payload['detected_at'] ?? now(),
            'remarks' => $payload['remarks'] ?? $existing->remarks,
        ])->save();

        return $existing->fresh() ?? $existing;
    }

    private function applyNullableConstraint(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        if ($value === null || $value === '') {
            $query->whereNull($column);

            return;
        }

        $query->where($column, $value);
    }

    private function assertExecutive(User $actor): void
    {
        if (!$actor->isExecutive()) {
            throw ValidationException::withMessages([
                'role' => 'Only EXEC_OFFICER can manage anomaly records.',
            ]);
        }
    }

    private function assertReferenceExists(string $referenceType, int $referenceId): void
    {
        if (!class_exists($referenceType) || !is_subclass_of($referenceType, Model::class)) {
            throw ValidationException::withMessages([
                'reference_type' => 'Invalid anomaly reference type.',
            ]);
        }

        /** @var Model $model */
        $model = new $referenceType();
        $keyName = $model->getKeyName();

        $exists = $referenceType::query()->where($keyName, $referenceId)->exists();
        if (!$exists) {
            throw ValidationException::withMessages([
                'reference_id' => 'Anomaly reference record does not exist.',
            ]);
        }
    }

    private function recordStatus(Anomaly $anomaly, ?string $oldStatus, string $newStatus, User $actor, string $remarks): void
    {
        StatusHistory::query()->create([
            'reference_type' => Anomaly::class,
            'reference_id' => (int) $anomaly->anomaly_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => (int) $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    private function recordApproval(Anomaly $anomaly, User $actor, string $status, string $remarks): void
    {
        ApprovalHistory::query()->create([
            'reference_type' => Anomaly::class,
            'reference_id' => (int) $anomaly->anomaly_id,
            'approved_by' => (int) $actor->id,
            'status' => $status,
            'remarks' => $remarks,
            'approved_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function recordAudit(User $actor, string $actionType, Anomaly $anomaly, array $details): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Executive.Anomaly',
            actionType: $actionType,
            referenceType: Anomaly::class,
            referenceId: (int) $anomaly->anomaly_id,
            details: array_merge([
                'anomaly_type' => $anomaly->anomaly_type,
                'severity' => $anomaly->severity,
                'status' => $anomaly->status,
            ], $details),
            logDomain: 'BUSINESS',
            officeId: $anomaly->office_id,
        );
    }
}
