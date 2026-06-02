<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table): void {
            $table->unsignedBigInteger('parent_claim_id')->nullable()->after('distribution_item_id');
            $table->string('source_type', 60)->nullable()->after('parent_claim_id');

            $table->foreign('parent_claim_id')
                ->references('claims_id')
                ->on('claims')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('parent_claim_id');
            $table->index(['distribution_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table): void {
            $table->dropForeign(['parent_claim_id']);
            $table->dropIndex(['parent_claim_id']);
            $table->dropIndex(['distribution_item_id', 'status']);
            $table->dropColumn(['parent_claim_id', 'source_type']);
        });
    }
};
