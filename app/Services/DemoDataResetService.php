<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DemoDataResetService
{
    /**
     * @return array<int, string>
     */
    public function resetTransactionalData(): array
    {
        // Child-first delete order for non-PostgreSQL drivers; PostgreSQL uses CASCADE truncate.
        $tables = [
            'delivery_verifications',
            'delivery_items',
            'distribution_items',
            'distribution_records',
            'deliveries',
            'purchase_order_items',
            'purchase_orders',
            'purchase_request_items',
            'purchase_requests',
            'supply_request_items',
            'supply_requests',
            'ppmp_items',
            'ppmp',
            'claims',
            'inventory_movements',
            'inventory',
            'anomalies',
            'alerts',
            'documents',
            'public_disclosures',
            'notifications',
            'approval_history',
            'status_history',
            'audit_logs',
            'record_archives',
            'system_backups',
        ];

        $existingTables = array_values(array_filter($tables, static fn (string $table): bool => Schema::hasTable($table)));

        DB::transaction(function () use ($existingTables): void {
            if ($existingTables === []) {
                return;
            }

            $driver = DB::getDriverName();

            if ($driver === 'pgsql') {
                DB::statement('TRUNCATE TABLE '.implode(', ', $existingTables).' RESTART IDENTITY CASCADE');
            } else {
                foreach ($existingTables as $table) {
                    DB::table($table)->delete();
                }
            }
        });

        Storage::disk('public')->deleteDirectory('public_disclosures');
        Storage::disk('public')->deleteDirectory('documents/public_disclosures');
        Storage::disk('public')->deleteDirectory('reports/public_disclosures');
        Storage::disk('local')->deleteDirectory('record-archives');
        Storage::disk('local')->deleteDirectory('system-backups');

        return $existingTables;
    }
}
