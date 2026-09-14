<?php

use Carbon\Carbon;
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
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->string('pr_number', 50)->nullable()->unique()->after('purchase_request_id');
        });

        // Backfill existing purchase requests using their created_at date
        $records = DB::table('purchase_requests')
            ->orderBy('created_at')
            ->orderBy('purchase_request_id')
            ->get(['purchase_request_id', 'created_at']);

        $monthlyCounts = [];
        foreach ($records as $record) {
            $date = Carbon::parse($record->created_at ?? now());
            $ym = $date->format('Y-m');
            $monthlyCounts[$ym] = ($monthlyCounts[$ym] ?? 0) + 1;
            $prNumber = sprintf('PR-%s-%04d', $ym, $monthlyCounts[$ym]);

            DB::table('purchase_requests')
                ->where('purchase_request_id', $record->purchase_request_id)
                ->update(['pr_number' => $prNumber]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropColumn('pr_number');
        });
    }
};
