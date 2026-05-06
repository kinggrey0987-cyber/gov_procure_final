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
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_delivery_id')->nullable()->after('requested_by');
            $table->string('source_type', 60)->nullable()->after('source_delivery_id');

            $table->foreign('source_delivery_id')
                ->references('delivery_id')
                ->on('deliveries')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(['source_delivery_id', 'status'], 'pr_source_delivery_status_idx');
        });

        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_delivery_item_id')->nullable()->after('item_id');

            $table->foreign('source_delivery_item_id')
                ->references('delivery_item_id')
                ->on('delivery_items')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('source_delivery_item_id', 'pri_source_delivery_item_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->dropForeign(['source_delivery_item_id']);
            $table->dropIndex('pri_source_delivery_item_idx');
            $table->dropColumn('source_delivery_item_id');
        });

        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropForeign(['source_delivery_id']);
            $table->dropIndex('pr_source_delivery_status_idx');
            $table->dropColumn(['source_delivery_id', 'source_type']);
        });
    }
};
