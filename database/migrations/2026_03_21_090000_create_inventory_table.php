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
        Schema::create('inventory', function (Blueprint $table) {
            $table->id('inventory_id');
            $table->unsignedBigInteger('item_id')->unique();
            $table->decimal('available_stock', 12, 2)->default(0);
            $table->decimal('stock_on_hand', 12, 2)->default(0);
            $table->decimal('reserved_quantity', 12, 2)->default(0);
            $table->decimal('released_quantity', 12, 2)->default(0);
            $table->decimal('low_stock_threshold', 12, 2)->default(0);
            $table->timestamp('last_updated')->useCurrent();
            $table->timestamps();

            if (Schema::hasTable('items')) {
                $table->foreign('item_id')->references('item_id')->on('items')->restrictOnDelete()->cascadeOnUpdate();
            }

            $table->index(['item_id', 'available_stock']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
