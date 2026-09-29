<?php

use App\Models\Claim;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StatusHistory;
use App\Models\SupplyRequest;
use App\Services\Inventory\SupplyRequestFollowUpService;
use App\Services\Procurement\ConsolidationTraceabilityService;
use App\Services\Procurement\ProcurementStatusSyncService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $traceability = app(ConsolidationTraceabilityService::class);
        $syncService = app(ProcurementStatusSyncService::class);
        $followUpService = app(SupplyRequestFollowUpService::class);

        // 1. Revert prematurely COMPLETED status on follow-up PRs (claims and delivery discrepancies)
        $completedFollowUpPrs = PurchaseRequest::query()
            ->whereIn('source_type', ['PARTIAL_DELIVERY_FOLLOW_UP', 'CLAIM_REPLACEMENT_FOLLOW_UP'])
            ->orWhereNotNull('source_claim_id')
            ->orWhereNotNull('source_delivery_id')
            ->where('status', 'COMPLETED')
            ->with(['purchaseOrders.deliveries.items', 'purchaseOrders.items', 'sourceClaim'])
            ->get();

        foreach ($completedFollowUpPrs as $pr) {
            if (! $syncService->canTransitionPurchaseRequestToCompleted($pr)) {
                $hasActivePo = $pr->purchaseOrders()
                    ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
                    ->exists();

                $targetStatus = $hasActivePo ? 'PROCESSING' : 'OPEN';
                $pr->forceFill(['status' => $targetStatus])->save();

                StatusHistory::query()->create([
                    'reference_type' => PurchaseRequest::class,
                    'reference_id' => (int) $pr->purchase_request_id,
                    'old_status' => 'COMPLETED',
                    'new_status' => $targetStatus,
                    'changed_by' => null,
                    'remarks' => 'Status reverted from COMPLETED: Procurement replenishment or claim resolution remains pending.',
                    'changed_at' => now(),
                ]);
            }
        }

        // 2. Rebind consolidated follow-up PRs whose stored parent is 100% fulfilled to the deficit constituent
        $followUpPrs = PurchaseRequest::query()
            ->whereNotNull('source_supply_request_id')
            ->with(['sourceDelivery.purchaseOrder', 'supplyRequest'])
            ->get();

        foreach ($followUpPrs as $pr) {
            $delivery = $pr->sourceDelivery;
            $po = $delivery?->purchaseOrder;
            if (! $po) {
                continue;
            }

            $candidateRequests = $traceability->getSourceSupplyRequests($po);
            if ($candidateRequests->count() > 1) {
                $storedParent = SupplyRequest::query()->find($pr->source_supply_request_id);
                if ($storedParent && $followUpService->isSupplyRequestFullyFulfilled($storedParent)) {
                    $correctParent = $traceability->resolveCandidateSupplyRequestWithDeficit($delivery, $candidateRequests);
                    if ($correctParent instanceof SupplyRequest && (int) $pr->source_supply_request_id !== (int) $correctParent->supply_request_id) {
                        $oldCode = $storedParent->request_code;
                        $pr->forceFill(['source_supply_request_id' => (int) $correctParent->supply_request_id])->save();

                        $childSr = $pr->supplyRequest;
                        if ($childSr instanceof SupplyRequest) {
                            $childSr->forceFill([
                                'office_id' => (int) $correctParent->office_id,
                                'review_notes' => str_replace((string) $oldCode, (string) $correctParent->request_code, (string) $childSr->review_notes),
                                'purpose' => str_replace((string) $oldCode, (string) $correctParent->request_code, (string) $childSr->purpose),
                            ])->save();
                        }
                    }
                }
            }
        }

        // 3. Revert prematurely RECEIVED status on child and claim replacement supply requests
        $receivedFollowUpSrs = SupplyRequest::query()
            ->where('status', 'RECEIVED')
            ->where(function ($q) {
                $q->whereHas('purchaseRequests', fn ($prQ) => $prQ->whereIn('source_type', ['PARTIAL_DELIVERY_FOLLOW_UP', 'CLAIM_REPLACEMENT_FOLLOW_UP'])
                    ->orWhereNotNull('source_claim_id')
                    ->orWhereNotNull('source_delivery_id')
                )->orWhere('review_notes', 'like', '%lineage was fully received%');
            })
            ->with(['distributionRecords.items'])
            ->get();

        foreach ($receivedFollowUpSrs as $sr) {
            if (! $syncService->isFullyReceived($sr)) {
                $totalReceived = (float) $sr->distributionRecords
                    ->where('status', 'RECEIVED')
                    ->flatMap->items
                    ->sum('quantity_received');

                $newStatus = $totalReceived > 0.0001 ? 'PARTIALLY_RECEIVED' : 'SUBMITTED';

                // Check if it's returned for revision
                $hasReturnedPr = $sr->purchaseRequests()->where('status', 'RETURNED_FOR_REVISION')->exists();
                if ($hasReturnedPr) {
                    $newStatus = 'DRAFT';
                }

                $sr->forceFill(['status' => $newStatus])->save();

                StatusHistory::query()->create([
                    'reference_type' => SupplyRequest::class,
                    'reference_id' => (int) $sr->supply_request_id,
                    'old_status' => 'RECEIVED',
                    'new_status' => $newStatus,
                    'changed_by' => null,
                    'remarks' => sprintf('Status reverted from RECEIVED to %s: Supply request fulfillment or claim replacement remains in progress.', $newStatus),
                    'changed_at' => now(),
                ]);
            }
        }

        // 4. Revert parent supply requests that have active unresolved claims from RECEIVED to PARTIALLY_RECEIVED
        $receivedSrsWithActiveClaims = SupplyRequest::query()
            ->where('status', 'RECEIVED')
            ->whereHas('distributionRecords.claims', fn ($q) => $q->whereNotIn('status', ['RESOLVED', 'REJECTED']))
            ->get();

        foreach ($receivedSrsWithActiveClaims as $sr) {
            $sr->forceFill(['status' => 'PARTIALLY_RECEIVED'])->save();

            StatusHistory::query()->create([
                'reference_type' => SupplyRequest::class,
                'reference_id' => (int) $sr->supply_request_id,
                'old_status' => 'RECEIVED',
                'new_status' => 'PARTIALLY_RECEIVED',
                'changed_by' => null,
                'remarks' => 'Status adjusted from RECEIVED to PARTIALLY_RECEIVED due to pending disputed items under active claim.',
                'changed_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-way repair migration
    }
};
