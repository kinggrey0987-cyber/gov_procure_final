<?php

namespace App\Services\Delivery;

use App\Models\Delivery;
use App\Models\DeliveryVerification;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryVerificationService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly InventoryService $inventoryService,
    )
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
     public function create(Delivery $delivery, User $actor, array $payload): DeliveryVerification
     {
         $this->assertGsoOfficer($actor);

         if ((string) $delivery->receipt_status === 'PARTIALLY_RECEIVED') {
             throw ValidationException::withMessages([
                 'receipt_status' => 'This delivery was already verified with discrepancies and marked as PARTIALLY_RECEIVED. Re-verification is not allowed. Create a follow-up purchase request instead.',
             ]);
         }
 
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
                'verification_status' => 'Deliveries with damaged or missing quantities must be rejected for Procurement Unit follow-up.',
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

            $verifiedDelivery = $delivery->fresh() ?? $delivery;

            if (in_array($newStatus, ['VERIFIED', 'PARTIALLY_RECEIVED'], true)) {
                $this->inventoryService->updateFromVerifiedDelivery($verifiedDelivery, $actor);
            }

            if ($newStatus === 'PARTIALLY_RECEIVED') {
                $this->recordAudit($actor, 'DISCREPANCY_FOR_BAC_FOLLOW_UP', $delivery);
            }

            if ($newStatus === 'VERIFIED') {
                $this->resolveSourcePartialDeliveryIfFollowUpCompleted($verifiedDelivery, $actor);
            }

            return $verification;
        });
    }

    private function resolveSourcePartialDeliveryIfFollowUpCompleted(Delivery $delivery, User $actor): void
    {
        $delivery->loadMissing('purchaseOrder.purchaseRequest.sourceDelivery');

        $purchaseRequest = $delivery->purchaseOrder?->purchaseRequest;
        if (
            ! $purchaseRequest
            || (string) $purchaseRequest->source_type !== 'PARTIAL_DELIVERY_FOLLOW_UP'
            || (int) ($purchaseRequest->source_delivery_id ?? 0) <= 0
        ) {
            return;
        }

        $sourceDelivery = $purchaseRequest->sourceDelivery;
        if (! $sourceDelivery instanceof Delivery || (string) $sourceDelivery->receipt_status !== 'PARTIALLY_RECEIVED') {
            return;
        }

        $oldStatus = (string) $sourceDelivery->receipt_status;

        $sourceDelivery->forceFill([
            'receipt_status' => 'VERIFIED',
        ])->save();

        $this->recordStatus(
            $sourceDelivery,
            $oldStatus,
            'VERIFIED',
            $actor,
            sprintf(
                'Partial delivery discrepancy resolved by accepted follow-up delivery %s.',
                (string) $delivery->delivery_code
            )
        );

        $this->recordAudit($actor, 'RESOLVE_PARTIAL_DELIVERY_FROM_FOLLOW_UP', $sourceDelivery);
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
