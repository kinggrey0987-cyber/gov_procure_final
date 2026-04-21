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
        Schema::table('distribution_records', function (Blueprint $table) {
            $table->dropForeign(['delivery_id']);
        });

        Schema::table('distribution_records', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_id')->nullable()->change();
        });

        Schema::table('distribution_records', function (Blueprint $table) {
            $table->foreign('delivery_id')
                ->references('delivery_id')
                ->on('deliveries')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('distribution_records', function (Blueprint $table) {
            $table->dropForeign(['delivery_id']);
        });

        Schema::table('distribution_records', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_id')->nullable(false)->change();
        });

        Schema::table('distribution_records', function (Blueprint $table) {
            $table->foreign('delivery_id')
                ->references('delivery_id')
                ->on('deliveries')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }
};

