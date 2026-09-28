<?php

use App\Models\Item;
use App\Models\PpmpItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\SupplyRequestItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            DB::transaction(function () {
                // Find all purchase orders linked to a claim replacement follow-up PR
                $claimReplacementPos = PurchaseOrder::query()
                    ->whereHas('purchaseRequest', function ($q) {
                        $q->where('source_type', 'CLAIM_REPLACEMENT_FOLLOW_UP')
                            ->orWhereNotNull('source_claim_id');
                    })
                    ->with(['items', 'purchaseRequest.supplyRequest.items.ppmpItem'])
                    ->get();

                foreach ($claimReplacementPos as $po) {
                    $termsNotes = (string) ($po->terms_notes ?? '');
                    if (str_contains($termsNotes, 'Zero-cost supplier warranty rectification')) {
                        $po->terms_notes = null;
                    }

                    foreach ($po->items as $poItem) {
                        $sourceItem = ($poItem->supply_request_item_id ? SupplyRequestItem::find($poItem->supply_request_item_id) : null)
                            ?? $po->purchaseRequest?->supplyRequest?->items?->first(fn ($si) => (int) $si->item_id === (int) $poItem->item_id);

                        // If specification was set to the warranty text, clear or revert to source spec
                        if ($poItem->specification === 'Replacement item under supplier warranty rectification') {
                            $sourceSpec = $sourceItem?->ppmpItem?->remarks
                                ?? $po->purchaseRequest?->items?->first(fn ($pi) => (int) $pi->item_id === (int) $poItem->item_id)?->remarks;
                            $poItem->specification = $sourceSpec;
                        }

                        // If unit_price is 0, resolve normal unit cost
                        if ((float) $poItem->unit_price == 0.0) {
                            $unitCost = 0.0;

                            // 1. Try supply request item's PPMP item
                            if ($sourceItem && $sourceItem->ppmpItem && (float) $sourceItem->ppmpItem->unit_cost > 0) {
                                $unitCost = (float) $sourceItem->ppmpItem->unit_cost;
                            }

                            // 2. Try any PPMP item for this item_id
                            if ($unitCost <= 0.0) {
                                $latestPpmpCost = PpmpItem::query()
                                    ->where('item_id', $poItem->item_id)
                                    ->where('unit_cost', '>', 0)
                                    ->latest('ppmp_item_id')
                                    ->value('unit_cost');
                                if ($latestPpmpCost && (float) $latestPpmpCost > 0) {
                                    $unitCost = (float) $latestPpmpCost;
                                }
                            }

                            // 3. Try latest non-zero purchase order item
                            if ($unitCost <= 0.0) {
                                $latestPoPrice = PurchaseOrderItem::query()
                                    ->where('item_id', $poItem->item_id)
                                    ->where('unit_price', '>', 0)
                                    ->latest('purchase_order_item_id')
                                    ->value('unit_price');
                                if ($latestPoPrice && (float) $latestPoPrice > 0) {
                                    $unitCost = (float) $latestPoPrice;
                                }
                            }

                            if ($unitCost > 0.0) {
                                $qty = max((float) $poItem->quantity_ordered, 1.0);
                                $poItem->unit_price = $unitCost;
                                $poItem->total_price = round($qty * $unitCost, 2);
                            }
                        }

                        $poItem->save();
                    }

                    // Recalculate PO total_amount
                    $po->total_amount = (float) $po->items()->sum('total_price');
                    $po->save();
                }
            });
        } catch (\Throwable $e) {
            // Log or ignore gracefully if table structure isn't ready
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive data reconciliation.
    }
};
