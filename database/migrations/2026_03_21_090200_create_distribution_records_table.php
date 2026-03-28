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
        Schema::create('distribution_records', function (Blueprint $table) {
            $table->id('distribution_record_id');
            $table->string('distribution_code', 80)->unique();
            $table->foreignId('supply_request_id')->constrained('supply_requests', 'supply_request_id')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('delivery_id')->constrained('deliveries', 'delivery_id')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->date('release_date')->nullable();
            $table->string('storage_location', 150)->nullable();
            $table->string('status', 50)->default('DRAFT');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['office_id', 'status']);
            $table->index(['supply_request_id', 'delivery_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribution_records');
    }
};
