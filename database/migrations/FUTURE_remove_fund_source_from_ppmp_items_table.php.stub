<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->dropColumn('fund_source');
        });
    }

    public function down(): void
    {
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->string('fund_source', 100)->nullable()->after('procurement_schedule');
        });
    }
};
