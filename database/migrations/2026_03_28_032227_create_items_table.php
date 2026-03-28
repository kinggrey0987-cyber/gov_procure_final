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
        Schema::create('items', function (Blueprint $table) {
            $table->id('item_id');
            $table->unsignedBigInteger('item_category_id');
            $table->string('item_name', 200);
            $table->text('description')->nullable();
            $table->string('default_unit', 50)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->foreign('item_category_id')
                ->references('item_category_id')
                ->on('item_categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->index(['item_category_id', 'item_name']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
