<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('ppmp_id')->nullable()->after('requested_by');
        });

        // Backfill where existing records have a single clear PPMP source from line items.
        // Use database-agnostic approach
        $supplyCandidates = DB::table('supply_request_items')
            ->join('ppmp_items', 'ppmp_items.ppmp_item_id', '=', 'supply_request_items.ppmp_item_id')
            ->select('supply_request_items.supply_request_id', DB::raw('MIN(ppmp_items.ppmp_id) AS ppmp_id'))
            ->groupBy('supply_request_items.supply_request_id')
            ->havingRaw('COUNT(DISTINCT ppmp_items.ppmp_id) = 1')
            ->get();

        foreach ($supplyCandidates as $candidate) {
            DB::table('supply_requests')
                ->where('supply_request_id', $candidate->supply_request_id)
                ->whereNull('ppmp_id')
                ->update(['ppmp_id' => $candidate->ppmp_id]);
        }

        Schema::table('supply_requests', function (Blueprint $table) {
            $table->foreign('ppmp_id')
                ->references('ppmp_id')
                ->on('ppmp')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(['office_id', 'ppmp_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropForeign(['ppmp_id']);
            $table->dropIndex(['office_id', 'ppmp_id']);
            $table->dropColumn('ppmp_id');
        });
    }
};
