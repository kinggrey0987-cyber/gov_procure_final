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
        Schema::create('item_specifications', function (Blueprint $table) {
            $table->id('item_specification_id');
            $table->unsignedBigInteger('item_id');
            $table->string('specification', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('item_id')
                ->references('item_id')
                ->on('items')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->index(['item_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_specifications');
    }
};
