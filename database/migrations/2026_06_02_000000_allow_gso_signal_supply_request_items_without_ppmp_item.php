<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supply_request_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('ppmp_item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('supply_request_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('ppmp_item_id')->nullable(false)->change();
        });
    }
};
