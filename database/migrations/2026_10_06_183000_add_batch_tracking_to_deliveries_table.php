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
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->unsignedInteger('batch_number')->default(1)->after('purchase_order_id');
            $table->unsignedInteger('total_batches')->nullable()->after('batch_number');
            $table->string('delivery_schedule_type', 40)->default('SINGLE_DELIVERY')->after('total_batches');
            $table->boolean('is_final_batch')->default(false)->after('delivery_schedule_type');

            $table->index(['purchase_order_id', 'batch_number'], 'del_po_batch_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropIndex('del_po_batch_idx');
            $table->dropColumn([
                'batch_number',
                'total_batches',
                'delivery_schedule_type',
                'is_final_batch',
            ]);
        });
    }
};
