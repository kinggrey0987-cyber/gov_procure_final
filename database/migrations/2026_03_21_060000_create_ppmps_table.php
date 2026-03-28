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
        Schema::create('ppmp', function (Blueprint $table) {
            $table->id('ppmp_id');
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('status', 50)->default('DRAFT');
            $table->decimal('total_estimated_budget', 15, 2)->default(0);
            $table->date('date_prepared');
            $table->date('date_approved')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['office_id', 'fiscal_year']);
            $table->index(['prepared_by', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppmp');
    }
};
