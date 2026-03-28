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
        Schema::create('approval_history', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 150);
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('status', 50);
            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index(['status', 'approved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_history');
    }
};
