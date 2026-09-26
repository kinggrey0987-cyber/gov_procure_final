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
        // Deactivate duplicate test items with trailing hyphens created during variant testing
        DB::table('items')
            ->where('item_name', 'LIKE', '% -')
            ->orWhere('item_name', 'LIKE', '% - -')
            ->orWhere('item_name', 'LIKE', '% - - -')
            ->update(['status' => 'inactive']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op rollback
    }
};
