<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id('document_id');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('title', 200);
            $table->string('file_name', 255);
            $table->string('file_path', 255);
            $table->string('related_module', 80);
            $table->unsignedBigInteger('related_reference_id');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['related_module', 'related_reference_id']);
            $table->index('uploaded_by');
            $table->index('uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
