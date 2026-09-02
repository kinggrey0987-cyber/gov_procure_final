<?php

namespace App\Http\Controllers\Executive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Executive\StoreRecordArchiveRequest;
use App\Models\RecordArchive;
use App\Services\Executive\RecordArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecordArchiveController extends Controller
{
    public function __construct(private readonly RecordArchiveService $recordArchiveService)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('executive.record-archive.view-any'), 403);

        $records = RecordArchive::query()
            ->with('generator:id,name')
            ->orderByDesc('generated_at')
            ->orderByDesc('archive_id')
            ->paginate(5)
            ->withQueryString();

        $records->through(static fn (RecordArchive $record): array => [
            'archive_id' => (int) $record->archive_id,
            'archive_year' => (int) $record->archive_year,
            'archive_scope' => (string) $record->archive_scope,
            'file_name' => (string) $record->file_name,
            'file_size' => $record->file_size,
            'sha256_checksum' => $record->sha256_checksum,
            'record_count' => (int) ($record->record_count ?? 0),
            'manifest_summary' => $record->manifest_summary,
            'generated_at' => optional($record->generated_at)?->toIso8601String(),
            'generated_by' => $record->generator ? [
                'id' => (int) $record->generator->id,
                'name' => (string) $record->generator->name,
            ] : null,
            'notes' => $record->notes,
            'can_download' => Storage::disk('local')->exists((string) $record->file_path),
            'can_delete' => $request->user()?->can('executive.record-archive.delete', $record) ?? false,
        ]);

        $telemetry = $this->recordArchiveService->getArchiveStorageStats();

        return Inertia::render('Executive/RecordArchives/Index', [
            'records' => $records,
            'telemetry' => $telemetry,
            'archiveScopes' => [
                'annual_procurement_dossier',
                'coa_compliance_package',
                'governance_audit_trail',
                'financial_contracts',
            ],
            'yearOptions' => [2026, 2025, 2024],
        ]);
    }

    public function store(StoreRecordArchiveRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('executive.record-archive.manage'), 403);

        $archive = $this->recordArchiveService->generate(
            actor: $request->user(),
            payload: $request->validated(),
        );

        return redirect()
            ->route('executive.record-archives.index')
            ->with('success', sprintf('Record archive generated: %s', (string) $archive->file_name));
    }

    public function download(Request $request, RecordArchive $record_archive): BinaryFileResponse
    {
        abort_unless($request->user()?->can('executive.record-archive.download', $record_archive), 403);

        $path = (string) $record_archive->file_path;
        abort_unless($path !== '' && Storage::disk('local')->exists($path), 404);

        return response()->download(Storage::disk('local')->path($path), (string) $record_archive->file_name);
    }

    public function destroy(Request $request, RecordArchive $record_archive): RedirectResponse
    {
        abort_unless($request->user()?->can('executive.record-archive.delete', $record_archive), 403);

        $this->recordArchiveService->deleteArchive(
            actor: $request->user(),
            archive: $record_archive,
        );

        return redirect()
            ->route('executive.record-archives.index')
            ->with('success', 'Record archive deleted successfully.');
    }
}

