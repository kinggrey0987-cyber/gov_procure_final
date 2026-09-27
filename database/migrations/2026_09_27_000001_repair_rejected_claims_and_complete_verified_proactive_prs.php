<?php

use App\Models\Claim;
use App\Models\StatusHistory;
use App\Services\Procurement\ProcurementStatusSyncService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Sync any completed proactive replenishment PRs and POs whose deliveries were verified
        try {
            app(ProcurementStatusSyncService::class)->syncCompletedProactiveAndRestockRequests();
        } catch (\Throwable) {
            // Ignore if tables or schema are not yet fully populated
        }

        // 2. Restore any claim that had replacement stock released from inventory but was mistakenly set to REJECTED
        try {
            $claimsToRepair = Claim::query()
                ->where('status', 'REJECTED')
                ->where('replacement_quantity', '>', 0)
                ->get();

            foreach ($claimsToRepair as $claim) {
                $claim->forceFill([
                    'status' => 'REPLACEMENT_RELEASED',
                    'resolution_type' => 'REPLACEMENT_RELEASED',
                ])->save();

                StatusHistory::query()->create([
                    'reference_type' => Claim::class,
                    'reference_id' => (int) $claim->claims_id,
                    'old_status' => 'REJECTED',
                    'new_status' => 'REPLACEMENT_RELEASED',
                    'changed_by' => $claim->claimed_by,
                    'remarks' => 'Restored to REPLACEMENT_RELEASED: replacement stock was already released from warehouse inventory and awaits office receipt verification.',
                    'changed_at' => now(),
                ]);
            }
        } catch (\Throwable) {
            // Ignore if tables or schema are not yet fully populated
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse action needed for state reconciliation.
    }
};
