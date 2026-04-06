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
        if (! Schema::hasTable('supply_request_items')) {
            return;
        }

        $columnsToDrop = [];

        if (Schema::hasColumn('supply_request_items', 'estimated_unit_cost')) {
            $columnsToDrop[] = 'estimated_unit_cost';
        }

        if (Schema::hasColumn('supply_request_items', 'estimated_total_cost')) {
            $columnsToDrop[] = 'estimated_total_cost';
        }

        if ($columnsToDrop !== []) {
            Schema::table('supply_request_items', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('supply_request_items')) {
            return;
        }

        Schema::table('supply_request_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('supply_request_items', 'estimated_unit_cost')) {
                $table->decimal('estimated_unit_cost', 15, 2)->default(0)->after('quantity_requested');
            }

            if (! Schema::hasColumn('supply_request_items', 'estimated_total_cost')) {
                $table->decimal('estimated_total_cost', 15, 2)->default(0)->after('estimated_unit_cost');
            }
        });
    }
};
