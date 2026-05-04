<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ppmp_items', 'fund_source')) {
            return;
        }

        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->dropColumn('fund_source');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('ppmp_items', 'fund_source')) {
            return;
        }

        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->string('fund_source', 100)->nullable()->after('procurement_schedule');
        });
    }
};
