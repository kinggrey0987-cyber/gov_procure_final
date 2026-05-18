<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_disclosure_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disclosure_id')->constrained('public_disclosures', 'disclosure_id')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('source_type', 150);
            $table->unsignedBigInteger('source_id');
            $table->string('source_role', 80)->default('SUPPORTING');
            $table->string('source_label', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('snapshot_json')->nullable();
            $table->timestamps();

            $table->index(['disclosure_id', 'sort_order']);
            $table->index(['source_type', 'source_id']);
            $table->unique(['disclosure_id', 'source_type', 'source_id', 'source_role'], 'public_disclosure_sources_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_disclosure_sources');
    }
};
