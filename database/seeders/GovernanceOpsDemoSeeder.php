<?php

namespace Database\Seeders;

use App\Models\SystemBackup;
use App\Models\User;
use App\Services\AdminTech\SystemBackupService;
use App\Services\Executive\RecordArchiveService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GovernanceOpsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $executive = User::query()
            ->whereHas('role', fn ($query) => $query->where('code', 'EXEC_OFFICER'))
            ->first();

        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('code', 'ADMIN'))
            ->first();

        if (! $executive instanceof User || ! $admin instanceof User) {
            $this->command?->warn('GovernanceOpsDemoSeeder skipped: required EXEC_OFFICER and ADMIN users were not found.');

            return;
        }

        /** @var RecordArchiveService $recordArchiveService */
        $recordArchiveService = app(RecordArchiveService::class);
        /** @var SystemBackupService $systemBackupService */
        $systemBackupService = app(SystemBackupService::class);

        $archiveYear = (int) now()->year;

        if (Schema::hasTable('record_archives')) {
            $recordArchiveService->generate($executive, [
                'archive_year' => $archiveYear,
                'archive_scope' => 'governance_yearly',
                'notes' => 'Demo reseed governance archive package for executive module presentation.',
            ]);
        }

        if (! Schema::hasTable('system_backups')) {
            $this->command?->warn('GovernanceOpsDemoSeeder: system_backups table is unavailable, backup demo rows skipped.');

            return;
        }

        $dbBackupPath = sprintf('system-backups/database_backup/database_backup_%s.json', now()->format('Ymd_His'));
        Storage::disk('local')->put($dbBackupPath, json_encode([
            'backup_type' => 'database_backup',
            'created_at' => now()->toIso8601String(),
            'database' => ['default_connection' => config('database.default')],
            'status' => 'completed',
            'notes' => 'Completed demo database backup generated during reseed.',
        ], JSON_PRETTY_PRINT));

        SystemBackup::query()->create([
            'backup_type' => 'database_backup',
            'file_name' => basename($dbBackupPath),
            'file_path' => $dbBackupPath,
            'file_size' => 2097152,
            'sha256_checksum' => hash('sha256', 'demo_database_backup'),
            'table_metrics' => [
                'users' => 10,
                'offices' => 8,
                'items' => 15,
                'ppmp' => 12,
                'supply_requests' => 39,
                'purchase_requests' => 35,
                'purchase_orders' => 34,
                'deliveries' => 33,
                'distribution_records' => 34,
            ],
            'created_by' => (int) $admin->id,
            'created_at' => now(),
            'backup_status' => 'completed',
            'notes' => 'Completed demo database backup generated during reseed.',
        ]);

        $storageBackupPath = sprintf('system-backups/storage_backup/storage_backup_%s.json', now()->format('Ymd_His'));
        Storage::disk('local')->put($storageBackupPath, json_encode([
            'backup_type' => 'storage_backup',
            'created_at' => now()->toIso8601String(),
            'total_files' => 24,
            'status' => 'completed',
        ], JSON_PRETTY_PRINT));

        SystemBackup::query()->create([
            'backup_type' => 'storage_backup',
            'file_name' => basename($storageBackupPath),
            'file_path' => $storageBackupPath,
            'file_size' => 1048576,
            'sha256_checksum' => hash('sha256', 'demo_storage_backup'),
            'created_by' => (int) $admin->id,
            'created_at' => now(),
            'backup_status' => 'completed',
            'notes' => 'Completed demo storage backup registered for governance demonstration.',
        ]);

        SystemBackup::query()->create([
            'backup_type' => 'database_backup',
            'file_name' => sprintf('database_backup_%s_pending.txt', now()->format('Ymd_His')),
            'file_path' => 'external://database_backup/'.Str::uuid()->toString(),
            'file_size' => null,
            'payload' => null,
            'created_by' => (int) $admin->id,
            'created_at' => now(),
            'backup_status' => 'pending',
            'notes' => 'Pending demo backup registration for queue visibility.',
        ]);

        $failedPath = sprintf('system-backups/storage_backup/%s', 'missing_failed_'.Str::lower(Str::random(8)).'.txt');

        if (Storage::disk('local')->exists($failedPath)) {
            Storage::disk('local')->delete($failedPath);
        }

        SystemBackup::query()->create([
            'backup_type' => 'storage_backup',
            'file_name' => basename($failedPath),
            'file_path' => $failedPath,
            'file_size' => null,
            'payload' => null,
            'created_by' => (int) $admin->id,
            'created_at' => now(),
            'backup_status' => 'failed',
            'notes' => 'Failed demo backup record for access-state and filtering validation.',
        ]);

        $this->seedBusinessAuditLogs($executive);
    }

    private function seedBusinessAuditLogs(User $executive): void
    {
        $procUser = User::query()->whereHas('role', fn ($q) => $q->where('code', 'PROC_OFFICER'))->first() ?? $executive;
        $gsoUser = User::query()->whereHas('role', fn ($q) => $q->where('code', 'GSO_OFFICER'))->first() ?? $executive;

        // 1. Supply Requests (Department Users)
        if (Schema::hasTable('supply_requests')) {
            \App\Models\SupplyRequest::query()->each(function (\App\Models\SupplyRequest $sr): void {
                \App\Models\AuditLog::query()->firstOrCreate([
                    'reference_type' => \App\Models\SupplyRequest::class,
                    'reference_id' => (int) $sr->supply_request_id,
                    'action_type' => 'SUBMIT',
                ], [
                    'user_id' => $sr->requested_by,
                    'office_id' => $sr->office_id,
                    'log_domain' => 'BUSINESS',
                    'module_name' => 'Office.SupplyRequest',
                    'details' => [
                        'status' => $sr->status,
                        'request_code' => $sr->request_code,
                    ],
                    'created_at' => $sr->created_at ?? now(),
                    'updated_at' => $sr->created_at ?? now(),
                ]);
            });
        }

        // 2. Purchase Requests (Procurement Officers / Department Requisitioners)
        if (Schema::hasTable('purchase_requests')) {
            \App\Models\PurchaseRequest::query()->each(function (\App\Models\PurchaseRequest $pr) use ($procUser): void {
                \App\Models\AuditLog::query()->firstOrCreate([
                    'reference_type' => \App\Models\PurchaseRequest::class,
                    'reference_id' => (int) $pr->purchase_request_id,
                    'action_type' => 'CREATE',
                ], [
                    'user_id' => $pr->requested_by ?? $procUser->id,
                    'office_id' => $pr->office_id,
                    'log_domain' => 'BUSINESS',
                    'module_name' => 'Procurement.PurchaseRequest',
                    'details' => [
                        'status' => $pr->status,
                        'pr_number' => $pr->pr_number ?? sprintf('PR-%d', $pr->purchase_request_id),
                    ],
                    'created_at' => $pr->created_at ?? now(),
                    'updated_at' => $pr->created_at ?? now(),
                ]);
            });
        }

        // 3. Purchase Orders (Procurement Officers)
        if (Schema::hasTable('purchase_orders')) {
            \App\Models\PurchaseOrder::query()->with('purchaseRequest')->each(function (\App\Models\PurchaseOrder $po) use ($procUser): void {
                \App\Models\AuditLog::query()->firstOrCreate([
                    'reference_type' => \App\Models\PurchaseOrder::class,
                    'reference_id' => (int) $po->purchase_order_id,
                    'action_type' => 'ISSUE',
                ], [
                    'user_id' => $po->created_by ?? $procUser->id,
                    'office_id' => $po->purchaseRequest?->office_id ?? $procUser->office_id,
                    'log_domain' => 'BUSINESS',
                    'module_name' => 'Procurement.PurchaseOrder',
                    'details' => [
                        'status' => $po->status,
                        'po_number' => $po->po_number ?? sprintf('PO-%d', $po->purchase_order_id),
                        'total_amount' => $po->total_amount,
                    ],
                    'created_at' => $po->created_at ?? now(),
                    'updated_at' => $po->created_at ?? now(),
                ]);
            });
        }

        // 4. Deliveries (GSO Officers)
        if (Schema::hasTable('deliveries')) {
            \App\Models\Delivery::query()->each(function (\App\Models\Delivery $del) use ($gsoUser): void {
                \App\Models\AuditLog::query()->firstOrCreate([
                    'reference_type' => \App\Models\Delivery::class,
                    'reference_id' => (int) $del->delivery_id,
                    'action_type' => 'VERIFY',
                ], [
                    'user_id' => $del->received_by ?? $gsoUser->id,
                    'office_id' => $gsoUser->office_id,
                    'log_domain' => 'BUSINESS',
                    'module_name' => 'Inventory.Delivery',
                    'details' => [
                        'status' => $del->status,
                        'delivery_order_number' => $del->delivery_order_number ?? sprintf('DEL-%d', $del->delivery_id),
                    ],
                    'created_at' => $del->created_at ?? now(),
                    'updated_at' => $del->created_at ?? now(),
                ]);
            });
        }

        // 5. Distribution Records (GSO Officers)
        if (Schema::hasTable('distribution_records')) {
            \App\Models\DistributionRecord::query()->each(function (\App\Models\DistributionRecord $dist) use ($gsoUser): void {
                \App\Models\AuditLog::query()->firstOrCreate([
                    'reference_type' => \App\Models\DistributionRecord::class,
                    'reference_id' => (int) $dist->distribution_record_id,
                    'action_type' => 'DISTRIBUTE',
                ], [
                    'user_id' => $dist->released_by ?? $gsoUser->id,
                    'office_id' => $dist->office_id,
                    'log_domain' => 'BUSINESS',
                    'module_name' => 'Inventory.Distribution',
                    'details' => [
                        'status' => $dist->status,
                        'control_no' => $dist->control_no ?? sprintf('DIS-%d', $dist->distribution_record_id),
                    ],
                    'created_at' => $dist->created_at ?? now(),
                    'updated_at' => $dist->created_at ?? now(),
                ]);
            });
        }

        // 6. Claims (Department Staff)
        if (Schema::hasTable('claims')) {
            \App\Models\Claim::query()->each(function (\App\Models\Claim $claim): void {
                \App\Models\AuditLog::query()->firstOrCreate([
                    'reference_type' => \App\Models\Claim::class,
                    'reference_id' => (int) $claim->claim_id,
                    'action_type' => 'CLAIM',
                ], [
                    'user_id' => $claim->claimed_by,
                    'office_id' => $claim->office_id,
                    'log_domain' => 'BUSINESS',
                    'module_name' => 'Inventory.Claim',
                    'details' => [
                        'status' => $claim->status,
                    ],
                    'created_at' => $claim->created_at ?? now(),
                    'updated_at' => $claim->created_at ?? now(),
                ]);
            });
        }

        // 7. PPMPs (Executive / Offices)
        if (Schema::hasTable('ppmps')) {
            \App\Models\Ppmp::query()->where('status', 'APPROVED')->each(function (\App\Models\Ppmp $ppmp) use ($executive): void {
                \App\Models\AuditLog::query()->firstOrCreate([
                    'reference_type' => \App\Models\Ppmp::class,
                    'reference_id' => (int) $ppmp->ppmp_id,
                    'action_type' => 'APPROVE',
                ], [
                    'user_id' => $ppmp->approved_by ?? $executive->id,
                    'office_id' => $ppmp->office_id,
                    'log_domain' => 'BUSINESS',
                    'module_name' => 'Executive.PPMP',
                    'details' => [
                        'fiscal_year' => $ppmp->fiscal_year,
                        'status' => $ppmp->status,
                        'total_budget' => $ppmp->total_estimated_budget,
                    ],
                    'created_at' => $ppmp->date_approved ?? $ppmp->created_at ?? now(),
                    'updated_at' => $ppmp->date_approved ?? $ppmp->created_at ?? now(),
                ]);
            });
        }
    }
}

