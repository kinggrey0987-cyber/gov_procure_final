<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->date('date_approved')->nullable()->after('expected_delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approved_by', 'date_approved']);
        });
    }
};
