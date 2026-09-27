<?php

namespace App\Http\Controllers\AdminTech;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminTech\StoreSystemBackupRequest;
use App\Models\SystemBackup;
use App\Services\AdminTech\SystemBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SystemBackupController extends Controller
{
    public function __construct(private readonly SystemBackupService $systemBackupService)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('admin.system-backup.view-any'), 403);

        $records = SystemBackup::query()
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('backup_id')
            ->paginate(15)
            ->withQueryString();

        $records->through(static fn (SystemBackup $record): array => [
            'backup_id' => (int) $record->backup_id,
            'backup_type' => (string) $record->backup_type,
            'file_name' => (string) $record->file_name,
            'file_size' => $record->file_size,
            'sha256_checksum' => $record->sha256_checksum,
            'table_metrics' => $record->table_metrics,
            'backup_status' => (string) $record->backup_status,
            'created_at' => optional($record->created_at)?->toIso8601String(),
            'created_by' => $record->creator ? [
                'id' => (int) $record->creator->id,
                'name' => (string) $record->creator->name,
            ] : null,
            'notes' => $record->notes,
            'can_download' => (string) $record->backup_status === 'completed'
                && (
                    Storage::disk('local')->exists((string) $record->file_path)
                    || (is_array($record->payload) && array_key_exists('content', $record->payload))
                ),
            'can_delete' => $request->user()?->can('admin.system-backup.delete', $record) ?? false,
        ]);

        $telemetry = $this->systemBackupService->getBackupStorageStats();

        return Inertia::render('AdminTech/SystemBackups/Index', [
            'records' => $records,
            'telemetry' => $telemetry,
            'backupTypes' => ['database_backup', 'storage_backup'],
            'backupStatuses' => ['completed', 'failed', 'pending'],
        ]);
    }

    public function store(StoreSystemBackupRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('admin.system-backup.manage'), 403);

        $backup = $this->systemBackupService->createBackup(
            actor: $request->user(),
            payload: $request->validated(),
        );

        return redirect()
            ->route('admin-tech.system-backups.index')
            ->with('success', sprintf('System backup recorded: %s (%s)', (string) $backup->file_name, (string) $backup->backup_status));
    }

    public function download(Request $request, SystemBackup $system_backup)
    {
        abort_unless($request->user()?->can('admin.system-backup.download', $system_backup), 403);
        abort_unless((string) $system_backup->backup_status === 'completed', 404);

        $path = (string) $system_backup->file_path;
        if ($path !== '' && Storage::disk('local')->exists($path)) {
            return response()->download(Storage::disk('local')->path($path), (string) $system_backup->file_name);
        }

        $payload = $system_backup->payload;

        if (is_array($payload) && array_key_exists('content', $payload)) {
            $format = (string) ($payload['format'] ?? 'json');
            $content = $payload['content'];

            $downloadContent = $format === 'text'
                ? (string) $content
                : (json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return response()->streamDownload(static function () use ($downloadContent): void {
                echo $downloadContent;
            }, (string) $system_backup->file_name);
        }

        abort(404);
    }

    public function destroy(Request $request, SystemBackup $system_backup): RedirectResponse
    {
        abort_unless($request->user()?->can('admin.system-backup.delete', $system_backup), 403);

        $this->systemBackupService->deleteBackup(
            actor: $request->user(),
            backup: $system_backup,
        );

        return redirect()
            ->route('admin-tech.system-backups.index')
            ->with('success', 'System backup deleted successfully.');
    }
}

