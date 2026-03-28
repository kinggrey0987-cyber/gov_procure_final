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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete()->cascadeOnUpdate();
            $table->string('log_domain', 30)->default('TECHNICAL');
            $table->string('module_name', 100);
            $table->string('action_type', 100);
            $table->string('reference_type', 150);
            $table->unsignedBigInteger('reference_id');
            $table->jsonb('details')->nullable();
            $table->timestamps();

            $table->index(['log_domain', 'created_at']);
            $table->index(['module_name', 'action_type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
