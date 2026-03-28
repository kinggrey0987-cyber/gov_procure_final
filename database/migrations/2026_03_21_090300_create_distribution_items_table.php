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
        Schema::create('distribution_items', function (Blueprint $table) {
            $table->id('distribution_item_id');
            $table->foreignId('distribution_record_id')->constrained('distribution_records', 'distribution_record_id')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('item_id');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('item_name_snapshot', 200);
            $table->decimal('quantity_released', 12, 2);
            $table->string('unit', 50);
            $table->text('remarks')->nullable();
            $table->timestamps();

            if (Schema::hasTable('items')) {
                $table->foreign('item_id')->references('item_id')->on('items')->restrictOnDelete()->cascadeOnUpdate();
            }

            $table->index(['distribution_record_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribution_items');
    }
};
