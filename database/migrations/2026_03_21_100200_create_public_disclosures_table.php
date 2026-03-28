<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_disclosures', function (Blueprint $table) {
            $table->id('disclosure_id');
            $table->string('title', 200);
            $table->string('type', 80);
            $table->string('reference_type', 150)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('published_at')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('status', 50)->default('DRAFT');
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_disclosures');
    }
};
