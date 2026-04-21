<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoDataResetService
{
    /**
     * @return array<int, string>
     */
    public function resetTransactionalData(): array
    {
        $tables = [
            'audit_logs',
            'approval_history',
            'status_history',
            'notifications',
            'alerts',
            'anomalies',
            'claims',
            'distribution_items',
            'distribution_records',
            'delivery_verifications',
            'delivery_items',
            'deliveries',
            'inventory_movements',
            'inventory',
            'purchase_order_items',
            'purchase_orders',
            'purchase_request_items',
            'purchase_requests',
            'supply_request_items',
            'supply_requests',
            'ppmp_items',
            'ppmp',
            'public_disclosures',
            'documents',
            'two_factor_challenges',
        ];

        DB::transaction(function () use ($tables): void {
            $driver = DB::getDriverName();

            if ($driver === 'pgsql') {
                DB::statement('TRUNCATE TABLE '.implode(', ', $tables).' RESTART IDENTITY CASCADE');
            } else {
                foreach ($tables as $table) {
                    DB::table($table)->delete();
                }
            }
        });

        Storage::disk('public')->deleteDirectory('public_disclosures');

        return $tables;
    }
}