<?php

use App\Models\PurchaseRequest;
use App\Models\SupplyRequest;
use App\Services\Procurement\ConsolidationTraceabilityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_requests', 'source_supply_request_id')) {
                $table->unsignedBigInteger('source_supply_request_id')->nullable()->after('source_delivery_id');

                $table->foreign('source_supply_request_id')
                    ->references('supply_request_id')
                    ->on('supply_requests')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();

                $table->index(['source_supply_request_id', 'status'], 'pr_source_sr_status_idx');
            }
        });

        // Backfill and repair parent supply request linkage for existing delivery discrepancy follow-up PRs
        $followUpPrs = PurchaseRequest::query()
            ->where('source_type', 'PARTIAL_DELIVERY_FOLLOW_UP')
            ->whereNotNull('source_delivery_id')
            ->with(['sourceDelivery.purchaseOrder', 'supplyRequest'])
            ->get();

        $traceability = app(ConsolidationTraceabilityService::class);

        foreach ($followUpPrs as $pr) {
            $delivery = $pr->sourceDelivery;
            $po = $delivery?->purchaseOrder;
            if (! $po) {
                continue;
            }

            $candidateRequests = $traceability->getSourceSupplyRequests($po);
            if ($candidateRequests->isEmpty() && $po->purchaseRequest) {
                $candidateRequests = $traceability->getSourceSupplyRequests($po->purchaseRequest);
            }

            $targetParent = null;
            if ($candidateRequests->count() > 1) {
                // Find candidate request with actual deficit / unfulfilled quantity
                $targetParent = $candidateRequests->first(function (SupplyRequest $sr): bool {
                    $requested = (float) $sr->items()->sum('quantity_requested');
                    $released = (float) $sr->distributionRecords()
                        ->whereIn('status', ['RELEASED', 'RECEIVED'])
                        ->with('items')
                        ->get()
                        ->flatMap->items
                        ->sum('quantity_released');

                    return $requested > ($released + 0.0001);
                });
            }

            if (! $targetParent instanceof SupplyRequest) {
                $targetParent = $candidateRequests->first()
                    ?? $po->supplyRequest
                    ?? $po->purchaseRequest?->supplyRequest;
            }

            if ($targetParent instanceof SupplyRequest) {
                $pr->forceFill([
                    'source_supply_request_id' => (int) $targetParent->supply_request_id,
                ])->save();

                // If child supply request exists, align its remarks/notes
                if ($pr->supplyRequest instanceof SupplyRequest) {
                    $childSr = $pr->supplyRequest;
                    $notes = (string) ($childSr->review_notes ?? '');
                    if (str_contains($notes, 'discrepancy follow-up') && ! str_contains($notes, (string) $targetParent->request_code)) {
                        $childSr->forceFill([
                            'review_notes' => trim($notes . ' Replenishing parent requisition ' . $targetParent->request_code . '.'),
                        ])->save();
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_requests', 'source_supply_request_id')) {
                $table->dropForeign(['source_supply_request_id']);
                $table->dropIndex('pr_source_sr_status_idx');
                $table->dropColumn('source_supply_request_id');
            }
        });
    }
};
