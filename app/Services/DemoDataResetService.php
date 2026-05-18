<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DemoDataResetService
{
    /**
     * Workflow/transactional tables that this reset is allowed to delete.
     *
     * Auth, users, roles, offices, permissions, item catalog, suppliers,
     * system backups, and technical audit setup are intentionally excluded.
     *
     * @return array<int, string>
     */
    public function workflowTablesInDeletionOrder(): array
    {
        return [
            'documents',
            'public_disclosure_sources',
            'public_disclosures',
            'record_archives',
            'notifications',
            'approval_history',
            'status_history',
            'alerts',
            'anomalies',
            'claims',
            'delivery_verifications',
            'distribution_items',
            'distribution_records',
            'delivery_items',
            'deliveries',
            'inventory_movements',
            'purchase_order_items',
            'purchase_orders',
            'purchase_request_items',
            'purchase_requests',
            'supply_request_items',
            'supply_requests',
            'ppmp_items',
            'ppmp',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function preservedTables(): array
    {
        return [
            'users',
            'roles',
            'offices',
            'password_reset_tokens',
            'sessions',
            'two_factor_challenges',
            'item_categories',
            'items',
            'suppliers',
            'inventory',
            'audit_logs',
            'system_backups',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
        ];
    }

    private function publicDisk(): string
    {
        return (string) config('filesystems.public_disk', 'public');
    }

    /**
     * @return array<int, string>
     */
    public function resetTransactionalData(): array
    {
        $existingTables = array_values(array_filter($this->workflowTablesInDeletionOrder(), static fn (string $table): bool => Schema::hasTable($table)));

        DB::transaction(function () use ($existingTables): void {
            if ($existingTables === []) {
                return;
            }

            foreach ($existingTables as $table) {
                DB::table($table)->delete();
            }
        });

        Storage::disk($this->publicDisk())->deleteDirectory('public_disclosures');
        Storage::disk($this->publicDisk())->deleteDirectory('documents/public_disclosures');
        Storage::disk($this->publicDisk())->deleteDirectory('reports/public_disclosures');
        Storage::disk('local')->deleteDirectory('record-archives');
        Storage::disk('local')->deleteDirectory('system-backups');

        return $existingTables;
    }
}
