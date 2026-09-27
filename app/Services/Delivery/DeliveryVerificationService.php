<?php

namespace App\Services\Delivery;

use App\Models\Delivery;
use App\Models\DeliveryVerification;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StatusHistory;
use App\Models\SupplyRequest;
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

        if ((string) $payload['verification_status'] === 'REJECTED' && ! $hasDiscrepancy) {
            throw ValidationException::withMessages([
                'verification_status' => 'Deliveries without discrepancies (0 damaged, 0 missing) cannot be rejected. Please approve the delivery.',
            ]);
        }

        if ((string) $payload['verification_status'] === 'REJECTED' && empty(trim((string) ($payload['discrepancy_notes'] ?? '')))) {
            throw ValidationException::withMessages([
                'discrepancy_notes' => 'Discrepancy notes / findings are required when rejecting a delivery.',
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
                $this->completeOrderAndProcurementIfFullyDelivered($verifiedDelivery, $actor);
            }

            return $verification;
        });
    }

    private function resolveSourcePartialDeliveryIfFollowUpCompleted(Delivery $delivery, User $actor): void
    {
        $resolvedByDelivery = $delivery;
        $visitedDeliveryIds = [];

        while (true) {
            $resolvedByDelivery->loadMissing('purchaseOrder.purchaseRequest.sourceDelivery');

            $purchaseRequest = $resolvedByDelivery->purchaseOrder?->purchaseRequest;
            if (
                ! $purchaseRequest
                || (string) $purchaseRequest->source_type !== 'PARTIAL_DELIVERY_FOLLOW_UP'
                || (int) ($purchaseRequest->source_delivery_id ?? 0) <= 0
            ) {
                return;
            }

            $sourceDelivery = $purchaseRequest->sourceDelivery;
            if (! $sourceDelivery instanceof Delivery) {
                return;
            }

            $sourceDeliveryId = (int) $sourceDelivery->delivery_id;
            if (isset($visitedDeliveryIds[$sourceDeliveryId])) {
                return;
            }

            $visitedDeliveryIds[$sourceDeliveryId] = true;

            if ((string) $sourceDelivery->receipt_status !== 'PARTIALLY_RECEIVED') {
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

            // Cascading completion:
            // 1. Mark follow-up replacement PO as COMPLETED
            $followUpPo = $delivery->purchaseOrder;
            if ($followUpPo && !in_array((string) $followUpPo->status, ['COMPLETED', 'CLOSED'], true)) {
                $followUpPoOld = (string) $followUpPo->status;
                $followUpPo->forceFill(['status' => 'COMPLETED'])->save();
                $this->recordStatus($delivery, $followUpPoOld, 'COMPLETED', $actor, 'Replacement order completed.');
            }

            // 2. Mark follow-up PR as COMPLETED
            if ($purchaseRequest && !in_array((string) $purchaseRequest->status, ['COMPLETED'], true)) {
                $purchaseRequest->forceFill(['status' => 'COMPLETED'])->save();
            }

            // 3. Mark parent PO as COMPLETED if all its deliveries are now resolved
            $parentPo = $sourceDelivery->purchaseOrder;
            if ($parentPo && !in_array((string) $parentPo->status, ['COMPLETED', 'CLOSED'], true)) {
                $hasUnverifiedDeliveries = $parentPo->deliveries()
                    ->whereNotIn('receipt_status', ['VERIFIED', 'COMPLETED', 'CANCELLED'])
                    ->exists();

                if (!$hasUnverifiedDeliveries) {
                    $parentOld = (string) $parentPo->status;
                    $parentPo->forceFill(['status' => 'COMPLETED'])->save();
                    $this->recordStatus($sourceDelivery, $parentOld, 'COMPLETED', $actor, 'Parent order deliveries and replacement items verified.');
                }
            }

            $resolvedByDelivery = $sourceDelivery->fresh() ?? $sourceDelivery;
        }
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
            officeId: $delivery->purchaseOrder?->supplyRequest?->office_id
                ?? $delivery->purchaseOrder?->purchaseRequest?->office_id
                ?? $delivery->purchaseOrder?->purchaseRequest?->supplyRequest?->office_id
                ?? null,
        );
    }

    private function completeOrderAndProcurementIfFullyDelivered(Delivery $delivery, User $actor): void
    {
        $delivery->loadMissing([
            'purchaseOrder.items',
            'purchaseOrder.deliveries.items',
            'purchaseOrder.purchaseRequest.supplyRequest',
            'purchaseOrder.purchaseRequest.purchaseOrders',
        ]);

        $po = $delivery->purchaseOrder;
        if (! $po instanceof PurchaseOrder) {
            return;
        }

        $hasPendingDeliveries = $po->deliveries()
            ->whereNotIn('receipt_status', ['VERIFIED', 'COMPLETED', 'CANCELLED'])
            ->exists();

        if ($hasPendingDeliveries || $po->deliveries->isEmpty()) {
            return;
        }

        $verifiedDeliveries = $po->deliveries->where('receipt_status', 'VERIFIED');
        foreach ($po->items as $poItem) {
            $orderedQty = (float) $poItem->quantity_ordered;
            if ($orderedQty <= 0) {
                continue;
            }

            $receivedQty = (float) $verifiedDeliveries
                ->flatMap(fn (Delivery $del) => $del->items)
                ->where('purchase_order_item_id', (int) $poItem->purchase_order_item_id)
                ->sum('received_quantity');

            if ($receivedQty < $orderedQty) {
                return;
            }
        }

        if (! in_array((string) $po->status, ['COMPLETED', 'CLOSED'], true)) {
            $poOld = (string) $po->status;
            $po->forceFill(['status' => 'COMPLETED'])->save();
            $this->recordStatus($delivery, $poOld, 'COMPLETED', $actor, 'Purchase order deliveries fully verified and accepted into inventory.');
        }

        $pr = $po->purchaseRequest;
        if ($pr instanceof PurchaseRequest && ! in_array((string) $pr->status, ['COMPLETED', 'CLOSED'], true)) {
            $isLowStockSignal = str_contains((string) ($pr->remarks ?? ''), 'LOW_STOCK_SIGNAL:inventory:');
            $isClaimFollowUp = (string) ($pr->source_type ?? '') === 'CLAIM_REPLACEMENT_FOLLOW_UP' || (int) ($pr->source_claim_id ?? 0) > 0;

            $hasUnfinishedPo = $pr->purchaseOrders()
                ->whereNotIn('status', ['COMPLETED', 'CLOSED', 'CANCELLED'])
                ->exists();

            if (! $hasUnfinishedPo && ($isLowStockSignal || $isClaimFollowUp)) {
                $prOld = (string) $pr->status;
                $pr->forceFill(['status' => 'COMPLETED'])->save();

                StatusHistory::query()->create([
                    'reference_type' => PurchaseRequest::class,
                    'reference_id' => (int) $pr->purchase_request_id,
                    'old_status' => $prOld,
                    'new_status' => 'COMPLETED',
                    'changed_by' => (int) $actor->id,
                    'remarks' => $isLowStockSignal
                        ? 'Proactive stock replenishment received and verified in warehouse.'
                        : 'Claim replacement goods delivered and verified into warehouse inventory.',
                    'changed_at' => now(),
                ]);

                $sr = $pr->supplyRequest;
                if ($sr instanceof SupplyRequest && ! in_array((string) $sr->status, ['RECEIVED', 'COMPLETED'], true)) {
                    $sr->forceFill(['status' => 'RECEIVED'])->save();
                }
            }
        }
    }
}
