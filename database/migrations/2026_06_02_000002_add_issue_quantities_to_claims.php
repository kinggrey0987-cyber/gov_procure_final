<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table): void {
            $table->decimal('quantity_damaged', 12, 2)->default(0)->after('quantity_disputed');
            $table->decimal('quantity_missing', 12, 2)->default(0)->after('quantity_damaged');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table): void {
            $table->dropColumn(['quantity_damaged', 'quantity_missing']);
        });
    }
};
