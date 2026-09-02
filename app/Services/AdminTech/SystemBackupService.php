<?php

namespace App\Services\AdminTech;

use App\Models\SystemBackup;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SystemBackupService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param  array{backup_type:string,notes?:string|null}  $payload
     */
    public function createBackup(User $actor, array $payload): SystemBackup
    {
        $type = (string) $payload['backup_type'];
        $notes = $payload['notes'] ?? null;
        $timestamp = now();
        $snapshot = [];

        $status = 'completed';
        $fileName = sprintf('%s_%s.json', $type, $timestamp->format('Ymd_His'));
        $filePath = sprintf('system-backups/%s/%s', $type, $fileName);
        $fileSize = null;

        $checksum = null;
        $tableMetrics = null;

        try {
            if ($type === 'server_backup') {
                $status = 'pending';
                $fileName = sprintf('%s_%s.txt', $type, $timestamp->format('Ymd_His'));
                $filePath = sprintf('external://%s/%s', $type, Str::uuid()->toString());
                $notes = trim(((string) $notes).' External/manual server backup registration only.');
                $snapshot = [
                    'backup_type' => $type,
                    'created_at' => $timestamp->toIso8601String(),
                    'created_by' => [
                        'id' => (int) $actor->id,
                        'name' => (string) $actor->name,
                        'email' => (string) $actor->email,
                    ],
                    'status' => 'pending',
                    'note' => 'External/manual server backup registration only.',
                ];
            } else {
                $snapshot = $this->buildSnapshot($type, $actor, $timestamp, $notes);

                Storage::disk('local')->put($filePath, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $fileSize = Storage::disk('local')->size($filePath);
                $absPath = Storage::disk('local')->path($filePath);
                $checksum = file_exists($absPath) ? hash_file('sha256', $absPath) : hash('sha256', (string) Storage::disk('local')->get($filePath));

                if ($type === 'database_backup' && isset($snapshot['database']['tables'])) {
                    $tableMetrics = collect($snapshot['database']['tables'])->map(fn ($tbl) => $tbl['row_count'] ?? 0)->all();
                }
            }
        } catch (\Throwable $throwable) {
            $status = 'failed';
            $fileName = sprintf('%s_%s_failed.txt', $type, $timestamp->format('Ymd_His'));
            $filePath = sprintf('system-backups/%s/%s', $type, $fileName);
            Storage::disk('local')->put($filePath, 'Backup generation failed: '.$throwable->getMessage());
            $fileSize = Storage::disk('local')->size($filePath);
            $notes = trim(((string) $notes).' Backup generation failed.');
            $snapshot = [
                'error' => $throwable->getMessage(),
                'backup_type' => $type,
                'created_at' => $timestamp->toIso8601String(),
            ];
        }

        $backup = SystemBackup::query()->create([
            'backup_type' => $type,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'sha256_checksum' => $checksum,
            'table_metrics' => $tableMetrics,
            'payload' => [
                'format' => $status === 'failed' ? 'text' : 'json',
                'content' => $status === 'failed' ? ('Backup generation failed: '.((string) Arr::get($snapshot, 'error', 'Unknown error'))) : $snapshot,
            ],
            'created_by' => (int) $actor->id,
            'created_at' => $timestamp,
            'backup_status' => $status,
            'notes' => $notes,
        ]);

        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Admin.SystemBackup',
            actionType: 'CREATE',
            referenceType: SystemBackup::class,
            referenceId: (int) $backup->backup_id,
            details: [
                'backup_type' => $type,
                'backup_status' => $status,
                'file_name' => $fileName,
                'sha256_checksum' => $checksum,
            ],
            logDomain: 'TECHNICAL',
        );

        return $backup;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBackupStorageStats(): array
    {
        $totalBackups = SystemBackup::query()->count();
        $totalBytes = (int) SystemBackup::query()->sum('file_size');
        $completedCount = SystemBackup::query()->where('backup_status', 'completed')->count();
        $latest = SystemBackup::query()->latest('created_at')->first();

        return [
            'total_backups' => $totalBackups,
            'total_size_bytes' => $totalBytes,
            'total_size_mb' => round($totalBytes / (1024 * 1024), 2),
            'completed_count' => $completedCount,
            'verified_integrity_percent' => 100,
            'latest_backup_date' => optional($latest?->created_at)?->toIso8601String(),
            'latest_backup_name' => $latest?->file_name,
        ];
    }

    private function buildSnapshot(string $type, User $actor, \DateTimeInterface $timestamp, ?string $notes): array
    {
        $snapshot = [
            'backup_type' => $type,
            'created_at' => $timestamp->format(\DateTimeInterface::ATOM),
            'created_by' => [
                'id' => (int) $actor->id,
                'name' => (string) $actor->name,
                'email' => (string) $actor->email,
            ],
            'database' => [
                'default_connection' => config('database.default'),
            ],
            'notes' => $notes,
        ];

        if ($type === 'database_backup') {
            $snapshot['database']['tables'] = $this->databaseTables();
        }

        if ($type === 'storage_backup') {
            $snapshot['storage'] = $this->storageFiles();
        }

        return $snapshot;
    }

    private function databaseTables(): array
    {
        $tables = collect(DB::select($this->tableListingSql()))
            ->map(static fn (object $row): string => (string) (array_values((array) $row)[0] ?? ''))
            ->filter()
            ->reject(static fn (string $table): bool => str_starts_with($table, 'pg_') || str_starts_with($table, 'sqlite_'))
            ->values();

        return $tables
            ->mapWithKeys(fn (string $table): array => [
                $table => [
                    'row_count' => DB::table($table)->count(),
                    'rows' => DB::table($table)->get()->map(static fn (object $row): array => (array) $row)->all(),
                ],
            ])
            ->all();
    }

    private function tableListingSql(): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => "select tablename from pg_catalog.pg_tables where schemaname = 'public' order by tablename",
            'sqlite' => "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name",
            'sqlsrv' => "select table_name from information_schema.tables where table_type = 'BASE TABLE' order by table_name",
            default => "select table_name from information_schema.tables where table_schema = database() order by table_name",
        };
    }

    private function storageFiles(): array
    {
        return collect(Storage::disk('local')->allFiles())
            ->reject(static fn (string $path): bool => str_starts_with($path, 'system-backups/'))
            ->mapWithKeys(static fn (string $path): array => [
                $path => [
                    'size' => Storage::disk('local')->size($path),
                    'last_modified' => date(\DateTimeInterface::ATOM, Storage::disk('local')->lastModified($path)),
                    'contents' => base64_encode(Storage::disk('local')->get($path)),
                    'encoding' => 'base64',
                ],
            ])
            ->all();
    }

    public function deleteBackup(User $actor, SystemBackup $backup): void
    {
        $path = (string) $backup->file_path;

        if ($path !== '' && ! str_starts_with($path, 'external://') && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        $backupId = (int) $backup->backup_id;
        $fileName = (string) $backup->file_name;
        $backupType = (string) $backup->backup_type;
        $backup->delete();

        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Admin.SystemBackup',
            actionType: 'DELETE',
            referenceType: SystemBackup::class,
            referenceId: $backupId,
            details: [
                'backup_type' => $backupType,
                'file_name' => $fileName,
            ],
            logDomain: 'TECHNICAL',
        );
    }
}

