<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FUTURE CLEANUP MIGRATION - Run after confirming migration is stable
 *
 * This migration removes the fund_source column from ppmp_items table.
 * Only run this after:
 * 1. All PPMPs have been migrated with fund_source at header level
 * 2. System is stable with new structure
 * 3. No code references ppmp_items.fund_source
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ppmp_items', 'fund_source')) {
            return;
        }

        Schema::table('ppmp_items', function (Blueprint $table) {
            // Remove the fund_source column from ppmp_items
            $table->dropColumn('fund_source');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('ppmp_items', 'fund_source')) {
            return;
        }

        Schema::table('ppmp_items', function (Blueprint $table) {
            // Restore the fund_source column if rollback is needed
            $table->string('fund_source', 100)->nullable();
        });
    }
};
