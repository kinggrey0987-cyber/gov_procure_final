<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_backups', function (Blueprint $table) {
            $table->id('backup_id');
            $table->string('backup_type', 80);
            $table->string('file_name', 255);
            $table->text('file_path');
            $table->bigInteger('file_size')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamp('created_at')->useCurrent();
            $table->string('backup_status', 30);
            $table->text('notes')->nullable();

            $table->index(['backup_type', 'backup_status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_backups');
    }
};

