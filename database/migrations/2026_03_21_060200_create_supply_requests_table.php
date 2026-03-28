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
        Schema::create('supply_requests', function (Blueprint $table) {
            $table->id('supply_request_id');
            $table->string('request_code', 50)->unique();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->date('request_date');
            $table->date('needed_by_date');
            $table->string('request_category', 50);
            $table->text('purpose');
            $table->string('priority_level', 30);
            $table->string('status', 50)->default('DRAFT');
            $table->text('review_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['office_id', 'request_date']);
            $table->index(['requested_by', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supply_requests');
    }
};
