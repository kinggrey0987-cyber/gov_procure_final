<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_items', function (Blueprint $table): void {
            $table->decimal('quantity_received', 12, 2)->default(0)->after('quantity_released');
            $table->decimal('quantity_damaged', 12, 2)->default(0)->after('quantity_received');
            $table->decimal('quantity_missing', 12, 2)->default(0)->after('quantity_damaged');
            $table->string('receipt_condition', 50)->nullable()->after('quantity_missing');
            $table->text('receipt_notes')->nullable()->after('receipt_condition');
        });

        Schema::table('claims', function (Blueprint $table): void {
            $table->unsignedBigInteger('distribution_item_id')->nullable()->after('distribution_record_id');
            $table->decimal('quantity_disputed', 12, 2)->default(0)->after('claim_location');
            $table->string('resolution_type', 80)->nullable()->after('status');

            $table->foreign('distribution_item_id')
                ->references('distribution_item_id')
                ->on('distribution_items')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('distribution_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table): void {
            $table->dropForeign(['distribution_item_id']);
            $table->dropIndex(['distribution_item_id']);
            $table->dropColumn(['distribution_item_id', 'quantity_disputed', 'resolution_type']);
        });

        Schema::table('distribution_items', function (Blueprint $table): void {
            $table->dropColumn(['quantity_received', 'quantity_damaged', 'quantity_missing', 'receipt_condition', 'receipt_notes']);
        });
    }
};
