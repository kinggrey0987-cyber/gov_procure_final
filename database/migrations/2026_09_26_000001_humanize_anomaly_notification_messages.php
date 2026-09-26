<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $replacements = [
            'PRICE_Z_SCORE' => 'Price Anomaly',
            'EXCESSIVE_QUANTITY' => 'Excessive Quantity Anomaly',
            'FREQUENT_REQUEST_WINDOW' => 'Frequent Request Spike',
            'PPMP_OVER_REQUEST' => 'PPMP Over-Request',
            'PPMP_EARLY_DEPLETION' => 'PPMP Early Depletion',
            'PPMP_FREQUENCY_IRREGULARITY' => 'PPMP Frequency Irregularity',
            'PPMP_SCHEDULE_MISMATCH' => 'PPMP Schedule Mismatch',
        ];

        foreach ($replacements as $raw => $human) {
            DB::table('notifications')
                ->where('message', 'like', "%Anomaly {$raw} was detected%")
                ->update([
                    'message' => DB::raw("REPLACE(message, 'Anomaly {$raw} was detected', '{$human} was detected')"),
                ]);
        }
    }

    public function down(): void
    {
        // Cosmetic normalization does not require rollback
    }
};
