<?php

use App\Models\Claim;
use App\Models\DistributionRecord;
use App\Models\PurchaseRequest;
use App\Models\StatusHistory;
use App\Services\Inventory\DistributionRecordService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Link orphan follow-up purchase requests to their parent claim
        $followUpPrs = PurchaseRequest::query()
            ->whereNull('source_claim_id')
            ->where(function ($q) {
                $q->whereNotNull('source_delivery_id')
                    ->orWhereNotNull('source_supply_request_id');
            })
            ->with(['sourceDelivery.purchaseOrder.purchaseRequest', 'sourceSupplyRequest.purchaseRequests'])
            ->get();

        foreach ($followUpPrs as $pr) {
            $claimId = $pr->sourceDelivery?->purchaseOrder?->purchaseRequest?->source_claim_id
                ?? $pr->sourceSupplyRequest?->purchaseRequests?->firstWhere('source_claim_id')?->source_claim_id;

            if ($claimId) {
                $pr->forceFill([
                    'source_claim_id' => (int) $claimId,
                    'source_type' => 'CLAIM_REPLACEMENT_FOLLOW_UP',
                ])->save();
            }
        }

        // 2. Resolve claims whose replacements are fully released/received
        $distService = app(DistributionRecordService::class);
        $claims = Claim::query()
            ->whereIn('status', ['OPEN', 'IN_REVIEW', 'PENDING_REPLACEMENT', 'REPLACEMENT_RELEASED', 'PARTIALLY_RESOLVED'])
            ->where('quantity_disputed', '>', 0)
            ->get();

        foreach ($claims as $claim) {
            $cumulativeReplaced = $distService->calculateCumulativeReplacementQuantity($claim);
            $disputed = (float) $claim->quantity_disputed;

            if ($cumulativeReplaced > 0) {
                $newReplaced = max((float) $claim->replacement_quantity, $cumulativeReplaced);
                $isFullyResolved = $newReplaced >= ($disputed - 0.0001);

                if ($isFullyResolved) {
                    $oldStatus = (string) $claim->status;
                    $claim->forceFill([
                        'status' => 'RESOLVED',
                        'resolution_type' => 'REPLACEMENT_RELEASED',
                        'replacement_quantity' => $newReplaced,
                        'remarks' => sprintf('Replacement verified and completed (%.2f of %.2f items delivered).', $newReplaced, $disputed),
                    ])->save();

                    StatusHistory::query()->create([
                        'reference_type' => Claim::class,
                        'reference_id' => (int) $claim->claims_id,
                        'old_status' => $oldStatus,
                        'new_status' => 'RESOLVED',
                        'changed_by' => (int) ($claim->claimed_by ?? 1),
                        'remarks' => 'Claim resolved following full receipt and distribution of replacement supplies.',
                        'changed_at' => now(),
                    ]);
                } elseif ($newReplaced > (float) $claim->replacement_quantity) {
                    $claim->forceFill([
                        'replacement_quantity' => $newReplaced,
                    ])->save();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op rollback
    }
};
