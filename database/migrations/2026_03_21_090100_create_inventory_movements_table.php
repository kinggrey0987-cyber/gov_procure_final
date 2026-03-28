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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id('movement_id');
            $table->foreignId('inventory_id')->constrained('inventory', 'inventory_id')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('movement_type', 80);
            $table->string('reference_type', 150);
            $table->unsignedBigInteger('reference_id');
            $table->decimal('quantity', 12, 2);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('last_updated')->useCurrent();
            $table->date('movement_date');
            $table->text('remarks')->nullable();

            $table->index(['inventory_id', 'movement_date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('movement_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
