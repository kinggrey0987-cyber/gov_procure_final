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
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id('purchase_request_item_id');
            $table->foreignId('purchase_request_id')->constrained('purchase_requests', 'purchase_request_id')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity_requested', 12, 2);
            $table->text('remarks')->nullable();
            $table->timestamps();

            if (Schema::hasTable('items')) {
                $table->foreign('item_id')->references('item_id')->on('items')->restrictOnDelete()->cascadeOnUpdate();
            }

            $table->index(['purchase_request_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
