<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_verifications', function (Blueprint $table) {
            $table->id('delivery_verification_id');
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedBigInteger('verified_by');
            $table->date('verification_date');
            $table->string('verification_status', 50);
            $table->text('discrepancy_notes')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('delivery_id')->references('delivery_id')->on('deliveries')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('cascade');

            $table->index('delivery_id');
            $table->index('verified_by');
            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_verifications');
    }
};
