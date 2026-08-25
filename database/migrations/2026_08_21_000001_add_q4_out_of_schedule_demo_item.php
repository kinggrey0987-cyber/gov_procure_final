<?php

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure an active item exists for out-of-schedule demonstration
        $category = ItemCategory::query()->firstOrCreate(
            ['category_name' => 'Office Supplies'],
            [
                'standard_delivery_days' => 7,
                'description' => 'General office supplies and consumables.',
                'status' => 'active',
            ]
        );

        $item = Item::query()->firstOrCreate(
            ['item_name' => 'HP 680 Tri-Color Ink'],
            [
                'item_category_id' => $category->item_category_id,
                'description' => 'Original HP 680 Tri-color Ink Cartridge for office printers.',
                'default_unit' => 'cart',
                'status' => 'active',
            ]
        );

        // Find or attach to all approved PPMPs
        $approvedPpmps = Ppmp::query()->where('status', 'APPROVED')->get();

        foreach ($approvedPpmps as $ppmp) {
            PpmpItem::query()->updateOrCreate(
                [
                    'ppmp_id' => $ppmp->ppmp_id,
                    'item_id' => $item->item_id,
                ],
                [
                    'item_category_id' => $category->item_category_id,
                    'quantity' => 15,
                    'unit_cost' => 450.00,
                    'estimated_budget' => 6750.00,
                    'procurement_schedule' => 'Q4', // Explicitly scheduled for Q4 to demonstrate out-of-schedule / early requisition
                    'fund_source' => 'General Fund',
                    'remarks' => 'Scheduled for Q4 year-end replenishment.',
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $item = Item::query()->where('item_name', 'HP 680 Tri-Color Ink')->first();
        if ($item) {
            PpmpItem::query()->where('item_id', $item->item_id)->where('procurement_schedule', 'Q4')->delete();
        }
    }
};
