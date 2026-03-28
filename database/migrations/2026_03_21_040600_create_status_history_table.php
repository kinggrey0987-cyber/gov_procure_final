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
        Schema::create('status_history', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 150);
            $table->unsignedBigInteger('reference_id');
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->text('remarks')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reference_type', 'reference_id']);
            $table->index(['new_status', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_history');
    }
};
