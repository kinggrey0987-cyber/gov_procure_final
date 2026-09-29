<?php

use App\Models\Claim;
use App\Models\DistributionRecord;
use App\Models\StatusHistory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $prematureClaims = Claim::query()
            ->where('status', 'RESOLVED')
            ->where('quantity_disputed', '>', 0)
            ->whereColumn('replacement_quantity', '<', 'quantity_disputed')
            ->where(function ($q) {
                $q->whereNull('resolution_type')
                    ->orWhere('resolution_type', '!=', 'REJECTED');
            })
            ->get();

        foreach ($prematureClaims as $claim) {
            $disputed = (float) $claim->quantity_disputed;
            $replaced = (float) $claim->replacement_quantity;

            $claim->forceFill([
                'status' => 'PARTIALLY_RESOLVED',
                'resolution_type' => 'PARTIAL_REPLACEMENT',
                'remarks' => sprintf('Corrected status from RESOLVED to PARTIALLY_RESOLVED (replaced %.2f of %.2f items).', $replaced, $disputed),
            ])->save();

            StatusHistory::query()->create([
                'reference_type' => Claim::class,
                'reference_id' => (int) $claim->claims_id,
                'old_status' => 'RESOLVED',
                'new_status' => 'PARTIALLY_RESOLVED',
                'changed_by' => (int) ($claim->claimed_by ?? 1),
                'remarks' => 'Migration correction: Partial claim replacement cannot be fully resolved until all disputed items are replaced.',
                'changed_at' => now(),
            ]);

            // If the original distribution record was prematurely marked RECEIVED while discrepancy remains, revert to RELEASED
            $origDist = $claim->distributionRecord;
            if ($origDist instanceof DistributionRecord && (string) $origDist->status === 'RECEIVED') {
                $origDist->loadMissing('items');
                $hasUnresolvedDiscrepancy = $origDist->items->contains(fn ($item): bool =>
                    (float) ($item->quantity_missing ?? 0) > 0.0001 || (float) ($item->quantity_damaged ?? 0) > 0.0001
                );

                if ($hasUnresolvedDiscrepancy) {
                    $origDist->forceFill([
                        'status' => 'RELEASED',
                    ])->save();

                    StatusHistory::query()->create([
                        'reference_type' => DistributionRecord::class,
                        'reference_id' => (int) $origDist->distribution_record_id,
                        'old_status' => 'RECEIVED',
                        'new_status' => 'RELEASED',
                        'changed_by' => (int) ($claim->claimed_by ?? 1),
                        'remarks' => 'Migration correction: Distribution record reverted to RELEASED because discrepancies are still pending resolution.',
                        'changed_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op rollback for data cleanup
    }
};
