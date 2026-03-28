<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anomalies', function (Blueprint $table) {
            $table->id('anomaly_id');
            $table->string('anomaly_type', 120);
            $table->string('reference_type', 150);
            $table->unsignedBigInteger('reference_id');
            $table->unsignedBigInteger('item_id')->nullable();
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete()->cascadeOnUpdate();
            $table->decimal('detected_value', 15, 4)->nullable();
            $table->decimal('expected_value', 15, 4)->nullable();
            $table->decimal('threshold_value', 15, 4)->nullable();
            $table->decimal('z_score', 12, 4)->nullable();
            $table->string('detection_method', 120)->nullable();
            $table->string('severity', 40)->default('MEDIUM');
            $table->string('status', 50)->default('OPEN');
            $table->timestamp('detected_at')->useCurrent();
            $table->text('remarks')->nullable();
            $table->timestamps();

            if (Schema::hasTable('items')) {
                $table->foreign('item_id')->references('item_id')->on('items')->nullOnDelete()->cascadeOnUpdate();
            }

            $table->index(['anomaly_type', 'severity']);
            $table->index(['status', 'detected_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('office_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomalies');
    }
};
