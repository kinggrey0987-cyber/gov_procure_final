<?php

namespace App\Http\Controllers;

use App\Http\Requests\Document\DeleteDocumentRequest;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Models\Document;
use App\Models\PublicDisclosure;
use App\Services\AuditLogService;
use App\Services\Documents\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $service,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    private function publicDisk(): string
    {
        return (string) config('filesystems.public_disk', 'public');
    }

    public function index(Request $request, string $related_module, int $related_reference_id): JsonResponse
    {
        abort_unless($request->user(), 403);

        try {
            $documents = $this->service->listForRelated($request->user(), $related_module, $related_reference_id);
        } catch (ValidationException) {
            abort(403);
        }

        return response()->json([
            'documents' => $documents->map(fn (Document $document): array => $this->transformDocument($document))->values(),
        ]);
    }

    public function store(StoreDocumentRequest $request, string $related_module, int $related_reference_id): RedirectResponse
    {
        try {
            $this->service->upload($request->user(), $related_module, $related_reference_id, $request->validated());
        } catch (ValidationException) {
            abort(403);
        }

        return back()->with('success', 'Attachment uploaded successfully.');
    }

    public function show(Request $request, Document $document): StreamedResponse
    {
        try {
            $this->service->authorizeViewDocument($document, $request->user());
        } catch (ValidationException) {
            abort(403);
        }

        $path = $this->resolveExistingPublicDiskPath((string) $document->file_path);
        abort_unless($path !== null, 404);

        return $this->streamDiskDownload($path, (string) $document->file_name);
    }

    public function destroy(DeleteDocumentRequest $request, Document $document): RedirectResponse
    {
        try {
            $this->service->delete($request->user(), $document);
        } catch (ValidationException) {
            abort(403);
        }

        return back()->with('success', 'Attachment deleted successfully.');
    }

    public function downloadPublicDisclosureDocument(PublicDisclosure $public_disclosure, Document $document): StreamedResponse
    {
        abort_unless($this->belongsToPublicDisclosure($document, $public_disclosure), 404);

        $isPublished = strtoupper((string) ($public_disclosure->status ?? '')) === 'PUBLISHED'
            && (bool) $public_disclosure->published_at;
        abort_unless($isPublished, 404);

        $path = $this->resolveExistingPublicDiskPath((string) $document->file_path, [
            sprintf(
                'documents/public_disclosures/%d/%s',
                (int) $public_disclosure->disclosure_id,
                (string) $document->file_name,
            ),
            sprintf(
                'public_disclosures/%s',
                (string) $document->file_name,
            ),
        ]);
        if ($path === null) {
            $path = sprintf(
                'documents/public_disclosures/%d/rebuilt-%d.txt',
                (int) $public_disclosure->disclosure_id,
                (int) $document->document_id,
            );

            Storage::disk($this->publicDisk())->put(
                $path,
                implode(PHP_EOL, [
                    'GovSupply Connect Public Disclosure Attachment (Rebuilt)',
                    sprintf('Disclosure ID: %d', (int) $public_disclosure->disclosure_id),
                    sprintf('Document ID: %d', (int) $document->document_id),
                    sprintf('Title: %s', (string) $document->title),
                    sprintf('Original File Name: %s', (string) $document->file_name),
                    'Generated because original attachment file was unavailable in storage.',
                ]).PHP_EOL,
            );

            $document->forceFill([
                'file_path' => $path,
                'file_name' => basename($path),
            ])->save();

            $this->auditLogService->log(
                actor: request()->user(),
                moduleName: 'Public.DisclosureAttachmentDownload',
                actionType: 'REBUILD_MISSING_PUBLIC_ATTACHMENT_FILE',
                referenceType: Document::class,
                referenceId: (int) $document->document_id,
                details: [
                    'disclosure_id' => (int) $public_disclosure->disclosure_id,
                    'document_id' => (int) $document->document_id,
                    'rebuilt_file_path' => $path,
                    'request_context' => request()->user() ? 'authenticated' : 'public',
                    'rebuilt_at' => now()->toIso8601String(),
                ],
                logDomain: 'BUSINESS',
            );
        }

        return $this->streamDiskDownload($path, (string) $document->file_name);
    }

    private function normalizePublicDiskPath(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    /**
     * @param  array<int, string>  $extraCandidates
     */
    private function resolveExistingPublicDiskPath(string $rawPath, array $extraCandidates = []): ?string
    {
        $normalized = $this->normalizePublicDiskPath($rawPath);
        $candidates = [];

        if ($normalized !== '') {
            $candidates[] = $normalized;
        }

        if (Str::startsWith($normalized, 'storage/')) {
            $candidates[] = Str::after($normalized, 'storage/');
        }

        if (Str::startsWith($normalized, 'public/')) {
            $candidates[] = Str::after($normalized, 'public/');
        }

        foreach ($extraCandidates as $extraCandidate) {
            $clean = $this->normalizePublicDiskPath($extraCandidate);
            if ($clean !== '') {
                $candidates[] = $clean;
            }
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

    private function belongsToPublicDisclosure(Document $document, PublicDisclosure $publicDisclosure): bool
    {
        $module = strtolower(trim((string) $document->related_module));
        $allowedModules = [
            'public_disclosures',
            'public_disclosure',
            'disclosures',
            'disclosure',
        ];

        return in_array($module, $allowedModules, true)
            && (int) $document->related_reference_id === (int) $publicDisclosure->disclosure_id;
    }

    private function transformDocument(Document $document): array
    {
        return [
            'document_id' => (int) $document->document_id,
            'title' => (string) $document->title,
            'file_name' => (string) $document->file_name,
            'file_path' => (string) $document->file_path,
            'document_kind' => (string) ($document->document_kind ?? ''),
            'related_module' => (string) $document->related_module,
            'related_reference_id' => (int) $document->related_reference_id,
            'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
            'uploaded_by' => (int) $document->uploaded_by,
            'uploader_name' => $document->uploader?->name,
        ];
    }
}
