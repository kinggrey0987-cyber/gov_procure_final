<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('ppmp')
            ->where('fiscal_year', '<', 2026)
            ->where('status', '!=', 'CLOSED')
            ->update(['status' => 'CLOSED']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ppmp')
            ->where('fiscal_year', '<', 2026)
            ->where('status', 'CLOSED')
            ->update(['status' => 'APPROVED']);
    }
};
