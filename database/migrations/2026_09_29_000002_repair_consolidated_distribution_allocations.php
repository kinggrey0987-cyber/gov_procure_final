<?php

use App\Models\DistributionRecord;
use App\Models\SupplyRequest;
use App\Services\Procurement\ConsolidationTraceabilityService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Safe data repair for any existing consolidated supply requests
        // that were mistakenly processed with delivery_id = null
        $traceability = app(ConsolidationTraceabilityService::class);

        $records = DistributionRecord::query()
            ->whereNull('delivery_id')
            ->whereIn('status', ['DRAFT', 'RELEASED'])
            ->with(['supplyRequest.items', 'items'])
            ->get();

        foreach ($records as $record) {
            $sr = $record->supplyRequest;
            if (! $sr instanceof SupplyRequest) {
                continue;
            }

            $prs = $traceability->getPurchaseRequestsForSupplyRequest($sr);
            if ($prs->isEmpty()) {
                continue;
            }

            $delivery = \App\Models\Delivery::query()
                ->whereIn('receipt_status', ['VERIFIED', 'PARTIALLY_RECEIVED'])
                ->whereHas('purchaseOrder', function ($q) use ($prs) {
                    $q->whereIn('purchase_request_id', $prs->pluck('purchase_request_id'));
                })
                ->latest('delivery_id')
                ->first();

            if ($delivery instanceof \App\Models\Delivery) {
                $record->forceFill([
                    'delivery_id' => $delivery->delivery_id,
                ])->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive data migration
    }
};
