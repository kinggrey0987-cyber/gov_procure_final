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
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id('purchase_request_id');
            $table->foreignId('supply_request_id')->constrained('supply_requests', 'supply_request_id')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('status', 50)->default('OPEN');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['office_id', 'status']);
            $table->index(['supply_request_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
