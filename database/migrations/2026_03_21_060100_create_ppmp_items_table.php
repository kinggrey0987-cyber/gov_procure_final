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
        Schema::create('ppmp_items', function (Blueprint $table) {
            $table->id('ppmp_item_id');
            $table->foreignId('ppmp_id')->constrained('ppmp', 'ppmp_id')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('item_category_id');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('estimated_budget', 15, 2);
            $table->string('procurement_schedule', 100);
            $table->string('fund_source', 100);
            $table->text('remarks')->nullable();
            $table->timestamps();

            if (Schema::hasTable('items')) {
                $table->foreign('item_id')->references('item_id')->on('items')->restrictOnDelete()->cascadeOnUpdate();
            }

            if (Schema::hasTable('item_categories')) {
                $table->foreign('item_category_id')->references('item_category_id')->on('item_categories')->restrictOnDelete()->cascadeOnUpdate();
            }

            $table->index(['ppmp_id', 'item_id']);
            $table->index('item_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppmp_items');
    }
};
