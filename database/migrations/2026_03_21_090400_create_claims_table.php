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
        Schema::create('claims', function (Blueprint $table) {
            $table->id('claims_id');
            $table->string('claim_code', 80)->unique();
            $table->foreignId('distribution_record_id')->constrained('distribution_records', 'distribution_record_id')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('claimed_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('claim_type', 80);
            $table->date('claim_date');
            $table->string('claim_location', 150);
            $table->text('remarks')->nullable();
            $table->string('status', 50)->default('OPEN');
            $table->timestamps();

            $table->index(['office_id', 'status']);
            $table->index('distribution_record_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
