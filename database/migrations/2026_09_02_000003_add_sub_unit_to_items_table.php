<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('items', 'sub_unit')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('sub_unit', 50)->default('piece')->after('pieces_per_box');
            });
        }

        $standardizedSubUnits = [
            'Bond Paper' => 'ream',
            'Rubbing Alcohol' => 'gallon',
            'Safety Gloves' => 'pair',
            'Logbook' => 'book',
            'Trash Bag' => 'roll',
            'Detergent Powder' => 'pack',
            'Ballpen' => 'piece',
            'Folder' => 'piece',
            'Staple Wire' => 'piece',
            'Heavy Duty Stapler' => 'piece',
            'Flash Drive' => 'piece',
            'Mouse' => 'piece',
            'Printer Ink' => 'cartridge',
            'Toner Cartridge' => 'cartridge',
            'HP 680 Tri-Color Ink' => 'cartridge',
            'Utility Cutter' => 'piece',
        ];

        foreach ($standardizedSubUnits as $itemName => $subUnit) {
            DB::table('items')
                ->where('item_name', $itemName)
                ->update(['sub_unit' => $subUnit]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('items', 'sub_unit')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('sub_unit');
            });
        }
    }
};
