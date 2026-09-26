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
        if (Schema::hasTable('purchase_order_items') && ! Schema::hasColumn('purchase_order_items', 'source_request_item_ids')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->json('source_request_item_ids')->nullable()->after('supply_request_item_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_order_items') && Schema::hasColumn('purchase_order_items', 'source_request_item_ids')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropColumn('source_request_item_ids');
            });
        }
    }
};
