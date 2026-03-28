<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id('delivery_item_id');
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->decimal('expected_quantity', 10, 2);
            $table->decimal('received_quantity', 10, 2)->default(0);
            $table->decimal('damaged_quantity', 10, 2)->default(0);
            $table->decimal('missing_quantity', 10, 2)->default(0);
            $table->string('item_condition', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('delivery_id')->references('delivery_id')->on('deliveries')->onDelete('cascade');
            $table->foreign('purchase_order_item_id')->references('purchase_order_item_id')->on('purchase_order_items')->onDelete('cascade');

            $table->index('delivery_id');
            $table->index('purchase_order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
    }
};
