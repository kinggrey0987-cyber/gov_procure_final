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
    }
}

