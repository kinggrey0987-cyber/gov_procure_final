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
        Schema::create('supply_request_items', function (Blueprint $table) {
            $table->id('supply_request_item_id');
            $table->foreignId('supply_request_id')->constrained('supply_requests', 'supply_request_id')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('ppmp_item_id');
            $table->string('item_name_snapshot', 200);
            $table->decimal('quantity_requested', 12, 2);
            $table->decimal('estimated_unit_cost', 15, 2);
            $table->decimal('estimated_total_cost', 15, 2);
            $table->timestamps();

            $table->foreign('ppmp_item_id')->references('ppmp_item_id')->on('ppmp_items')->restrictOnDelete()->cascadeOnUpdate();

            if (Schema::hasTable('items')) {
                $table->foreign('item_id')->references('item_id')->on('items')->restrictOnDelete()->cascadeOnUpdate();
            }

            $table->index(['supply_request_id', 'item_id']);
            $table->index('ppmp_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supply_request_items');
    }
};
