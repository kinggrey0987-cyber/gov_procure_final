<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppmp', function (Blueprint $table) {
            $table->string('fund_source', 100)->nullable()->after('fiscal_year');
            $table->index(['fund_source']);
        });
    }

    public function down(): void
    {
        Schema::table('ppmp', function (Blueprint $table) {
            $table->dropIndex(['fund_source']);
            $table->dropColumn('fund_source');
        });
    }
};
