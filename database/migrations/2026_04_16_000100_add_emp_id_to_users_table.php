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
     * Phase 1: Add emp_id support with nullable column and partial unique index.
     * Allows existing users to continue without emp_id while supporting uniqueness
     * for newly assigned employee IDs. Phase 2 will enforce NOT NULL.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add emp_id after email (logical placement near top of table)
            $table->string('emp_id')->nullable()->after('email');
        });

        // Add partial unique index (PostgreSQL: UNIQUE WHERE emp_id IS NOT NULL)
        // This allows multiple NULL values while enforcing uniqueness on non-NULL values
        DB::statement(
            'CREATE UNIQUE INDEX idx_users_emp_id_not_null ON users(emp_id) WHERE emp_id IS NOT NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_users_emp_id_not_null');

        Schema::table('users', function (Blueprint $table) {
            // Drop the column
            $table->dropColumn('emp_id');
        });
    }
};
