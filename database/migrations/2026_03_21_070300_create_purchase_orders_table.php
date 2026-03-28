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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id('purchase_order_id');
            $table->foreignId('purchase_request_id')->constrained('purchase_requests', 'purchase_request_id')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('supply_request_id')->constrained('supply_requests', 'supply_request_id')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('supplier_id')->constrained('suppliers', 'supplier_id')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('po_number', 80)->unique();
            $table->string('procurement_method', 120);
            $table->string('status', 50)->default('DRAFT');
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('terms_notes')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['purchase_request_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
