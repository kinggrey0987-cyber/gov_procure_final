<?php

namespace App\Services\AdminTech;

use App\Models\SystemBackup;
use App\Models\User;
use App\Services\AuditLogService;
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

        $status = 'completed';
        $fileName = sprintf('%s_%s.json', $type, $timestamp->format('Ymd_His'));
        $filePath = sprintf('system-backups/%s/%s', $type, $fileName);
        $fileSize = null;

        try {
            if ($type === 'server_backup') {
                $status = 'pending';
                $fileName = sprintf('%s_%s.txt', $type, $timestamp->format('Ymd_His'));
                $filePath = sprintf('external://%s/%s', $type, Str::uuid()->toString());
                $notes = trim(((string) $notes).' External/manual server backup registration only.');
            } else {
                $snapshot = [
                    'backup_type' => $type,
                    'created_at' => $timestamp->toIso8601String(),
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

                Storage::disk('local')->put($filePath, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $fileSize = Storage::disk('local')->size($filePath);
            }
        } catch (\Throwable $throwable) {
            $status = 'failed';
            $fileName = sprintf('%s_%s_failed.txt', $type, $timestamp->format('Ymd_His'));
            $filePath = sprintf('system-backups/%s/%s', $type, $fileName);
            Storage::disk('local')->put($filePath, 'Backup generation failed: '.$throwable->getMessage());
            $fileSize = Storage::disk('local')->size($filePath);
            $notes = trim(((string) $notes).' Backup generation failed.');
        }

        $backup = SystemBackup::query()->create([
            'backup_type' => $type,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => $fileSize,
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
            ],
            logDomain: 'TECHNICAL',
        );

        return $backup;
    }
}

