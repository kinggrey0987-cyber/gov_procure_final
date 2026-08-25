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

    public function download(PublicDisclosure $public_disclosure): StreamedResponse
    {
        abort_unless($this->isPublished($public_disclosure), 404);
        abort_unless(is_string($public_disclosure->file_path) && $public_disclosure->file_path !== '', 404);

        $path = $this->resolveExistingPublicDiskPath((string) $public_disclosure->file_path);
        if ($path === null) {
            $path = sprintf('reports/public_disclosures/disclosure-%d-rebuilt.txt', (int) $public_disclosure->disclosure_id);

            Storage::disk($this->publicDisk())->put(
                $path,
                implode(PHP_EOL, [
                    'GovSupply Connect Public Disclosure Report (Rebuilt)',
                    sprintf('Disclosure ID: %d', (int) $public_disclosure->disclosure_id),
                    sprintf('Title: %s', (string) $public_disclosure->title),
                    sprintf('Type: %s', (string) $public_disclosure->type),
                    sprintf('Published At: %s', optional($public_disclosure->published_at)?->toIso8601String() ?? 'N/A'),
                    'Generated because original file was unavailable in storage.',
                ]).PHP_EOL,
            );

            $public_disclosure->forceFill([
                'file_path' => $path,
            ])->save();

            $this->auditLogService->log(
                actor: Auth::user(),
                moduleName: 'Public.DisclosureDownload',
                actionType: 'REBUILD_MISSING_DISCLOSURE_FILE',
                referenceType: PublicDisclosure::class,
                referenceId: (int) $public_disclosure->disclosure_id,
                details: [
                    'disclosure_id' => (int) $public_disclosure->disclosure_id,
                    'rebuilt_file_path' => $path,
                    'request_context' => Auth::check() ? 'authenticated' : 'public',
                    'rebuilt_at' => now()->toIso8601String(),
                ],
                logDomain: 'BUSINESS',
            );
        }

        return $this->streamDiskDownload($path, basename($path));
    }

    private function isPublished(PublicDisclosure $disclosure): bool
    {
        return (string) $disclosure->status === 'PUBLISHED' && (bool) $disclosure->published_at;
    }

    private function normalizePublicDiskPath(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    private function resolveExistingPublicDiskPath(string $rawPath): ?string
    {
        $normalized = $this->normalizePublicDiskPath($rawPath);
        if ($normalized === '') {
            return null;
        }

        $candidates = [
            $normalized,
        ];

        if (Str::startsWith($normalized, 'storage/')) {
            $candidates[] = Str::after($normalized, 'storage/');
        }

        if (Str::startsWith($normalized, 'public/')) {
            $candidates[] = Str::after($normalized, 'public/');
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if ($candidate !== '' && Storage::disk($this->publicDisk())->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function streamDiskDownload(string $path, string $downloadName): StreamedResponse
    {
        $disk = Storage::disk($this->publicDisk());
        $stream = $disk->readStream($path);

        abort_unless(is_resource($stream), 404);

        return response()->streamDownload(
            static function () use ($stream): void {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            $downloadName,
        );
    }

}
