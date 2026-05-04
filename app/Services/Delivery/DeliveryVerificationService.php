<?php

namespace App\Services\Delivery;

use App\Models\Delivery;
use App\Models\DeliveryVerification;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryVerificationService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Delivery $delivery, User $actor, array $payload): DeliveryVerification
    {
        $this->assertGsoOfficer($actor);

        if ((string) $delivery->receipt_status !== 'RECEIVED') {
            throw ValidationException::withMessages([
                'receipt_status' => 'Only RECEIVED deliveries can be verified.',
            ]);
        }

        if ($delivery->verifications()->exists()) {
            throw ValidationException::withMessages([
                'delivery_id' => 'Delivery has already been verified. Re-verification is not allowed.',
            ]);
        }

        if ($delivery->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => 'Delivery cannot be verified without received item lines.',
            ]);
        }

        $hasDiscrepancy = $delivery->items()
            ->where(function ($query): void {
                $query->where('damaged_quantity', '>', 0)
                    ->orWhere('missing_quantity', '>', 0);
            })
            ->exists();

        if ((string) $payload['verification_status'] === 'APPROVED' && $hasDiscrepancy) {
            throw ValidationException::withMessages([
                'verification_status' => 'Deliveries with damaged or missing quantities must be rejected for BAC/procurement follow-up.',
            ]);
        }

        if ((string) $payload['verification_status'] === 'REJECTED' && ! $hasDiscrepancy && empty($payload['discrepancy_notes'])) {
            throw ValidationException::withMessages([
                'discrepancy_notes' => 'Discrepancy notes are required when rejecting a delivery without item quantity discrepancies.',
            ]);
        }

        return DB::transaction(function () use ($delivery, $actor, $payload): DeliveryVerification {
            $verification = DeliveryVerification::query()->create([
                'delivery_id' => (int) $delivery->delivery_id,
                'verified_by' => (int) $actor->id,
                'verification_date' => $payload['verification_date'] ?? now()->toDateString(),
                'verification_status' => $payload['verification_status'],
                'discrepancy_notes' => $payload['discrepancy_notes'] ?? null,
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $oldStatus = (string) $delivery->receipt_status;
            $newStatus = $payload['verification_status'] === 'APPROVED' ? 'VERIFIED' : 'PARTIALLY_RECEIVED';

            $delivery->forceFill(['receipt_status' => $newStatus])->save();

            $this->recordStatus($delivery, $oldStatus, $newStatus, $actor, 'Delivery verified.');
            $this->recordAudit($actor, 'CREATE_VERIFICATION', $delivery);

            if ($newStatus === 'PARTIALLY_RECEIVED') {
                $this->recordAudit($actor, 'DISCREPANCY_FOR_BAC_FOLLOW_UP', $delivery);
            }

            return $verification;
        });
    }

    private function assertGsoOfficer(User $actor): void
    {
        if (!$actor->isGsoOfficer()) {
            throw ValidationException::withMessages([
                'role' => 'Only GSO officers can verify deliveries.',
            ]);
        }
    }

    private function recordStatus(Delivery $delivery, ?string $oldStatus, string $newStatus, User $actor, string $remarks): void
    {
        StatusHistory::query()->create([
            'reference_type' => Delivery::class,
            'reference_id' => (int) $delivery->delivery_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => (int) $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    private function recordAudit(User $actor, string $actionType, Delivery $delivery): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Delivery.Verification',
            actionType: $actionType,
            referenceType: Delivery::class,
            referenceId: (int) $delivery->delivery_id,
            details: [
                'delivery_code' => $delivery->delivery_code,
                'verification_status' => $delivery->receipt_status,
            ],
            logDomain: 'BUSINESS',
            officeId: $delivery->purchaseOrder->supplyRequest->office_id ?? null,
        );
    }
}
