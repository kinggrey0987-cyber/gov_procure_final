<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_claim_id')->nullable()->after('source_delivery_id');

            $table->foreign('source_claim_id')
                ->references('claims_id')
                ->on('claims')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(['source_claim_id', 'status'], 'pr_source_claim_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropForeign(['source_claim_id']);
            $table->dropIndex('pr_source_claim_status_idx');
            $table->dropColumn('source_claim_id');
        });
    }
};
