<?php

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
        Schema::table('distribution_items', function (Blueprint $table) {
            if (! Schema::hasColumn('distribution_items', 'bundled_warehouse_quantity')) {
                $table->decimal('bundled_warehouse_quantity', 12, 2)->default(0.00)->after('quantity_released');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('distribution_items', function (Blueprint $table) {
            if (Schema::hasColumn('distribution_items', 'bundled_warehouse_quantity')) {
                $table->dropColumn('bundled_warehouse_quantity');
            }
        });
    }
};
