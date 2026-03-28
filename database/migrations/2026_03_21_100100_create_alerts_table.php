<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id('alerts_id');
            $table->string('alert_type', 120);
            $table->string('severity', 40)->default('MEDIUM');
            $table->foreignId('anomaly_id')->nullable()->constrained('anomalies', 'anomaly_id')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('supply_request_id')->nullable()->constrained('supply_requests', 'supply_request_id')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders', 'purchase_order_id')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers', 'supplier_id')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete()->cascadeOnUpdate();
            $table->text('alert_message');
            $table->timestamp('detected_at')->useCurrent();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('status', 50)->default('OPEN');
            $table->timestamps();

            $table->index(['alert_type', 'severity']);
            $table->index(['status', 'detected_at']);
            $table->index('anomaly_id');
            $table->index('office_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
