<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_archives', function (Blueprint $table) {
            $table->id('archive_id');
            $table->integer('archive_year');
            $table->string('archive_scope', 120);
            $table->string('file_name', 255);
            $table->text('file_path');
            $table->bigInteger('file_size')->nullable();
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamp('generated_at')->useCurrent();
            $table->text('notes')->nullable();

            $table->index(['archive_year', 'archive_scope']);
            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_archives');
    }
};

