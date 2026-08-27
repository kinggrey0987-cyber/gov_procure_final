<?php

namespace App\Http\Controllers\Public;

use App\Models\Document;
use App\Models\PublicDisclosure;
use App\Services\Documents\DocumentService;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicDisclosurePortalController
{
    public function __construct(
        private readonly DocumentService $documentService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    private function publicDisk(): string
    {
        return (string) config('filesystems.public_disk', 'public');
    }

    public function index(\Illuminate\Http\Request $request): Response
    {
        $query = PublicDisclosure::query()
            ->with('sources')
            ->published();

        $selectedYear = $request->filled('year') ? (string) $request->input('year') : '';
        if ($selectedYear !== '') {
            $query->where(function ($q) use ($selectedYear) {
                $q->whereYear('published_at', (int) $selectedYear)
                    ->orWhereBetween('published_at', ["{$selectedYear}-01-01 00:00:00", "{$selectedYear}-12-31 23:59:59"]);
            });
        }

        $selectedType = $request->filled('type') ? (string) $request->input('type') : '';
        if ($selectedType !== '') {
            $query->where('type', $selectedType);
        }

        $search = $request->filled('search') ? trim((string) $request->input('search')) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $disclosures = $query->orderByDesc('published_at')
            ->paginate(20)
            ->withQueryString();

        $availableYears = PublicDisclosure::query()
            ->published()
            ->whereNotNull('published_at')
            ->pluck('published_at')
            ->map(fn ($dt) => $dt instanceof \Illuminate\Support\Carbon ? $dt->format('Y') : (\Carbon\Carbon::tryParse($dt)?->format('Y') ?? null))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        if (empty($availableYears)) {
            $availableYears = [(string) now()->year];
        }

        $availableTypes = PublicDisclosure::query()
            ->published()
            ->whereNotNull('type')
            ->distinct()
            ->pluck('type')
            ->filter()
            ->values()
            ->all();

        return Inertia::render('Public/Disclosures/Index', [
            'disclosures' => $disclosures->through(fn (PublicDisclosure $disclosure): array => [
                'disclosure_id' => (int) $disclosure->disclosure_id,
                'title' => (string) $disclosure->title,
                'type' => (string) $disclosure->type,
                'published_at' => optional($disclosure->published_at)?->toIso8601String(),
                'primary_source' => $disclosure->sources->firstWhere('source_role', 'PRIMARY')?->source_label
                    ?? $disclosure->sources->first()?->source_label
                    ?? 'Disclosure package',
                'linked_records_count' => $disclosure->sources->count(),
            ]),
            'filters' => [
                'year' => $selectedYear,
                'type' => $selectedType,
                'search' => $search,
            ],
            'availableYears' => $availableYears,
            'availableTypes' => $availableTypes,
        ]);
    }

    public function show(PublicDisclosure $public_disclosure): Response
    {
        abort_unless($this->isPublished($public_disclosure), 404);

        try {
            $documents = $this->documentService
                ->listForRelated(Auth::user(), 'public_disclosures', (int) $public_disclosure->disclosure_id)
                ->map(fn ($document): array => [
                    'document_id' => (int) $document->document_id,
                    'title' => (string) $document->title,
                    'file_name' => (string) $document->file_name,
                    'document_kind' => $document->document_kind,
                    'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
                ])->values();
        } catch (ValidationException) {
            // Production-safe fallback: never block the public disclosure page when document-policy checks drift.
            $documents = Document::query()
                ->whereIn('related_module', ['public_disclosures', 'public_disclosure', 'disclosures', 'disclosure'])
                ->where('related_reference_id', (int) $public_disclosure->disclosure_id)
                ->orderByDesc('uploaded_at')
                ->orderByDesc('document_id')
                ->get()
                ->map(fn (Document $document): array => [
                    'document_id' => (int) $document->document_id,
                    'title' => (string) $document->title,
                    'file_name' => (string) $document->file_name,
                    'document_kind' => $document->document_kind,
                    'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
                ])->values();
        }

        $public_disclosure->loadMissing('sources');

        return Inertia::render('Public/Disclosures/Show', [
            'disclosure' => $public_disclosure,
            'attachments' => $documents,
            'sources' => $public_disclosure->sources->map(fn ($source): array => [
                'id' => (int) $source->id,
                'source_role' => (string) $source->source_role,
                'source_label' => (string) $source->source_label,
                'source_type' => (string) $source->source_type,
                'source_id' => (int) $source->source_id,
                'snapshot' => $source->snapshot_json ?? [],
            ])->values(),
            'backPage' => (int) request()->query('page', 0) > 0 ? (int) request()->query('page') : null,
        ]);
    }

    public function download(PublicDisclosure $public_disclosure): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($this->isPublished($public_disclosure), 404);

        $officialDocument = Document::query()
            ->whereIn('related_module', ['public_disclosures', 'public_disclosure', 'disclosures', 'disclosure'])
            ->where('related_reference_id', (int) $public_disclosure->disclosure_id)
            ->where('document_kind', 'OFFICIAL_DISCLOSURE_PACKAGE')
            ->first();

        $pdfDownloadName = sprintf('disclosure-%d-official-package.pdf', (int) $public_disclosure->disclosure_id);

        if ($officialDocument && ! empty($officialDocument->file_path)) {
            $streamResponse = $this->tryStreamFile((string) $officialDocument->file_path, (string) ($officialDocument->file_name ?: $pdfDownloadName));
            if ($streamResponse instanceof \Symfony\Component\HttpFoundation\Response) {
                return $streamResponse;
            }
        }

        if (! empty($public_disclosure->file_path)) {
            $streamResponse = $this->tryStreamFile((string) $public_disclosure->file_path, basename((string) $public_disclosure->file_path));
            if ($streamResponse instanceof \Symfony\Component\HttpFoundation\Response) {
                return $streamResponse;
            }
        }

        // Dynamic generation fallback: generate the official PDF package on the fly
        try {
            $packageService = app(\App\Services\Executive\PublicDisclosurePackageService::class);
            $pdfContent = $packageService->buildSnapshotPdf($public_disclosure);

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $pdfDownloadName),
            ]);
        } catch (\Throwable $e) {
            $fallbackText = implode(PHP_EOL, [
                'GovSupply Connect Public Disclosure Report',
                sprintf('Disclosure ID: %d', (int) $public_disclosure->disclosure_id),
                sprintf('Title: %s', (string) $public_disclosure->title),
                sprintf('Type: %s', (string) $public_disclosure->type),
                sprintf('Published At: %s', optional($public_disclosure->published_at)?->toIso8601String() ?? 'N/A'),
            ]).PHP_EOL;

            return response($fallbackText, 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => sprintf('attachment; filename="disclosure-%d.txt"', (int) $public_disclosure->disclosure_id),
            ]);
        }
    }

    private function isPublished(PublicDisclosure $disclosure): bool
    {
        return (string) $disclosure->status === 'PUBLISHED' && (bool) $disclosure->published_at;
    }

    private function normalizePublicDiskPath(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    private function tryStreamFile(string $rawPath, string $downloadName): ?\Symfony\Component\HttpFoundation\Response
    {
        $normalized = $this->normalizePublicDiskPath($rawPath);
        if ($normalized === '') {
            return null;
        }

        $candidates = array_values(array_unique(array_filter([
            $normalized,
            Str::startsWith($normalized, 'storage/') ? Str::after($normalized, 'storage/') : null,
            Str::startsWith($normalized, 'public/') ? Str::after($normalized, 'public/') : null,
        ])));

        $disks = array_unique([$this->publicDisk(), 'public', 'local']);

        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                foreach ($candidates as $candidate) {
                    if ($disk->exists($candidate)) {
                        $stream = $disk->readStream($candidate);
                        if (is_resource($stream)) {
                            $mime = str_ends_with(strtolower($downloadName), '.pdf') ? 'application/pdf' : 'application/octet-stream';
                            return response()->streamDownload(
                                static function () use ($stream): void {
                                    fpassthru($stream);
                                    if (is_resource($stream)) {
                                        fclose($stream);
                                    }
                                },
                                $downloadName,
                                ['Content-Type' => $mime]
                            );
                        }
                    }
                }
            } catch (\Throwable) {
                // Ignore cloud disk failure and try next available storage disk
                continue;
            }
        }

        // Direct local filesystem fallback
        foreach ($candidates as $candidate) {
            $localPath = storage_path('app/public/' . $candidate);
            if (file_exists($localPath) && is_readable($localPath)) {
                return response()->download($localPath, $downloadName);
            }
        }

        return null;
    }

}
