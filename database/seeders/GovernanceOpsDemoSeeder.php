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

        $systemBackupService->createBackup($admin, [
            'backup_type' => 'database_backup',
            'notes' => 'Completed demo database backup generated during reseed.',
        ]);

        $systemBackupService->createBackup($admin, [
            'backup_type' => 'storage_backup',
            'notes' => 'Completed demo storage backup generated during reseed.',
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

