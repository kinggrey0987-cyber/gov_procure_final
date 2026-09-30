<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Any follow-up purchase request generated from a delivery discrepancy (intake shortage/damage)
        // must be PARTIAL_DELIVERY_FOLLOW_UP (Zero-Cost Supplier Warranty), even if the parent PO fulfilled a claim.
        DB::table('purchase_requests')
            ->whereNotNull('source_delivery_id')
            ->where(function ($q) {
                $q->where('remarks', 'like', 'Follow-up procurement generated from delivery%')
                    ->orWhere('remarks', 'like', 'Follow-up procurement for partially received delivery%');
            })
            ->where('source_type', 'CLAIM_REPLACEMENT_FOLLOW_UP')
            ->update([
                'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
            ]);
    }

    public function down(): void
    {
        // No rollback needed for data normalization
    }
};
