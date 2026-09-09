<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('delivery_receipt_no', 100)->nullable()->after('expected_arrival_date');
            $table->string('sales_invoice_no', 100)->nullable()->after('delivery_receipt_no');
            $table->date('sales_invoice_date')->nullable()->after('sales_invoice_no');
            $table->decimal('sales_invoice_amount', 15, 2)->nullable()->after('sales_invoice_date');

            $table->index('delivery_receipt_no');
            $table->index('sales_invoice_no');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex(['delivery_receipt_no']);
            $table->dropIndex(['sales_invoice_no']);

            $table->dropColumn([
                'delivery_receipt_no',
                'sales_invoice_no',
                'sales_invoice_date',
                'sales_invoice_amount',
            ]);
        });
    }
};
