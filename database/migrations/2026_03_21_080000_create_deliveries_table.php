<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id('delivery_id');
            $table->string('delivery_code', 100)->unique();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('supplier_id');
            $table->date('actual_delivery_date')->nullable();
            $table->date('expected_arrival_date')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->string('receipt_status', 50)->default('PENDING');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('purchase_order_id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('supplier_id')->references('supplier_id')->on('suppliers')->onDelete('cascade');
            $table->foreign('received_by')->references('id')->on('users')->onDelete('set null');

            $table->index('purchase_order_id');
            $table->index('supplier_id');
            $table->index('received_by');
            $table->index('receipt_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
