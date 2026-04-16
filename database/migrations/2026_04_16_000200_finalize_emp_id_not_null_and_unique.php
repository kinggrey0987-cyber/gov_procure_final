<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 2: Finalize emp_id as required and unique.
     * Now that all production users have been backfilled with real emp_id values,
     * enforce NOT NULL and replace partial unique index with standard unique constraint.
     */
    public function up(): void
    {
        // Drop the Phase 1 partial unique index (no longer needed post-NOT NULL)
        DB::statement('DROP INDEX IF EXISTS idx_users_emp_id_not_null');

        // Alter column to NOT NULL and add unique constraint
        Schema::table('users', function (Blueprint $table) {
            $table->string('emp_id')->nullable(false)->change();
            $table->unique('emp_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique(['emp_id']);
            // Revert to nullable
            $table->string('emp_id')->nullable()->change();
        });

        // Restore the Phase 1 partial unique index
        DB::statement(
            'CREATE UNIQUE INDEX idx_users_emp_id_not_null ON users(emp_id) WHERE emp_id IS NOT NULL'
        );
    }
};
