<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $standardizedItems = [
            'Bond Paper' => ['default_unit' => 'box', 'pieces_per_box' => 5],
            'Ballpen' => ['default_unit' => 'box', 'pieces_per_box' => 50],
            'Folder' => ['default_unit' => 'pack', 'pieces_per_box' => 100],
            'Logbook' => ['default_unit' => 'box', 'pieces_per_box' => 10],
            'Staple Wire' => ['default_unit' => 'box', 'pieces_per_box' => 10000],
            'Heavy Duty Stapler' => ['default_unit' => 'box', 'pieces_per_box' => 1],
            'Flash Drive' => ['default_unit' => 'box', 'pieces_per_box' => 10],
            'Mouse' => ['default_unit' => 'box', 'pieces_per_box' => 10],
            'Printer Ink' => ['default_unit' => 'box', 'pieces_per_box' => 5],
            'Toner Cartridge' => ['default_unit' => 'cartridge', 'pieces_per_box' => 1],
            'HP 680 Tri-Color Ink' => ['default_unit' => 'box', 'pieces_per_box' => 5],
            'Rubbing Alcohol' => ['default_unit' => 'box', 'pieces_per_box' => 4],
            'Detergent Powder' => ['default_unit' => 'box', 'pieces_per_box' => 24],
            'Trash Bag' => ['default_unit' => 'box', 'pieces_per_box' => 10],
            'Safety Gloves' => ['default_unit' => 'box', 'pieces_per_box' => 12],
            'Utility Cutter' => ['default_unit' => 'box', 'pieces_per_box' => 12],
        ];

        foreach ($standardizedItems as $itemName => $specs) {
            DB::table('items')
                ->where('item_name', $itemName)
                ->update([
                    'default_unit' => $specs['default_unit'],
                    'pieces_per_box' => $specs['pieces_per_box'],
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed as packaging standards persist
    }
};
