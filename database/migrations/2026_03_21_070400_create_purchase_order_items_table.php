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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id('purchase_order_item_id');
            $table->foreignId('purchase_order_id')->constrained('purchase_orders', 'purchase_order_id')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('supply_request_item_id')->constrained('supply_request_items', 'supply_request_item_id')->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('item_id');
            $table->string('item_name_snapshot', 200);
            $table->text('specification')->nullable();
            $table->decimal('quantity_ordered', 12, 2);
            $table->string('unit', 50);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->timestamps();

            if (Schema::hasTable('items')) {
                $table->foreign('item_id')->references('item_id')->on('items')->restrictOnDelete()->cascadeOnUpdate();
            }

            $table->index(['purchase_order_id', 'item_id']);
            $table->index('supply_request_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
