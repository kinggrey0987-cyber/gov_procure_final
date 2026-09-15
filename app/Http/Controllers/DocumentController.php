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
            $uploaded = $this->service->uploadMultiple($request->user(), $related_module, $related_reference_id, $request->validated());
        } catch (ValidationException) {
            abort(403);
        }

        $count = $uploaded->count();
        $message = $count > 1
            ? "Successfully uploaded and archived {$count} supporting documents (images converted to official PDF)."
            : 'Attachment uploaded and archived successfully.';

        return back()->with('success', $message);
    }

    public function show(Request $request, Document $document): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $this->service->authorizeViewDocument($document, $request->user());
        } catch (ValidationException) {
            abort(403);
        }

        $isDownload = $request->boolean('download') || $request->input('mode') === 'download';
        $disposition = $isDownload ? 'attachment' : 'inline';

        $response = $this->tryStreamDocumentFile(
            (string) $document->file_path,
            (string) $document->file_name,
            $disposition,
            [
                sprintf('documents/%s/%d/%s', (string) $document->related_module, (int) $document->related_reference_id, (string) $document->file_name),
                (string) $document->file_name,
            ]
        );

        if ($response instanceof \Symfony\Component\HttpFoundation\Response) {
            return $response;
        }

        abort(404, 'The requested document file could not be found in storage.');
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

    public function downloadPublicDisclosureDocument(PublicDisclosure $public_disclosure, Document $document): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($this->belongsToPublicDisclosure($document, $public_disclosure), 404);

        $isPublished = strtoupper((string) ($public_disclosure->status ?? '')) === 'PUBLISHED'
            && (bool) $public_disclosure->published_at;
        abort_unless($isPublished, 404);

        // 1. Try to stream existing file across disks
        $streamResponse = $this->tryStreamDocumentFile(
            (string) $document->file_path,
            (string) $document->file_name,
            'attachment',
            [
                sprintf('documents/public_disclosures/%d/%s', (int) $public_disclosure->disclosure_id, (string) $document->file_name),
                sprintf('public_disclosures/%s', (string) $document->file_name),
            ]
        );

        if ($streamResponse instanceof \Symfony\Component\HttpFoundation\Response) {
            return $streamResponse;
        }

        // 2. If it's the official package, dynamically re-generate the PDF!
        if ($document->document_kind === 'OFFICIAL_DISCLOSURE_PACKAGE') {
            try {
                $packageService = app(\App\Services\Executive\PublicDisclosurePackageService::class);
                $pdfContent = $packageService->buildSnapshotPdf($public_disclosure);

                return response($pdfContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => sprintf('attachment; filename="%s"', (string) ($document->file_name ?: 'official-disclosure-package.pdf')),
                ]);
            } catch (\Throwable) {
                // Fall through to fallback text
            }
        }

        // 3. Fallback: text reconstruction
        $fallbackText = implode(PHP_EOL, [
            'GovSupply Connect Public Disclosure Attachment',
            sprintf('Disclosure ID: %d', (int) $public_disclosure->disclosure_id),
            sprintf('Document ID: %d', (int) $document->document_id),
            sprintf('Title: %s', (string) $document->title),
            sprintf('Original File Name: %s', (string) $document->file_name),
            'Generated from system database metadata.',
        ]).PHP_EOL;

        return response($fallbackText, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => sprintf('attachment; filename="%s"', (string) ($document->file_name ?: 'document.txt')),
        ]);
    }

    private function normalizePublicDiskPath(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    private function resolveMimeType(string $fileName): string
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }

    /**
     * @param  array<int, string>  $extraCandidates
     */
    private function tryStreamDocumentFile(string $rawPath, string $downloadName, string $disposition = 'inline', array $extraCandidates = []): ?\Symfony\Component\HttpFoundation\Response
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

        $candidates = array_values(array_unique(array_filter($candidates)));
        $disks = array_unique([$this->publicDisk(), 'public', 'local']);
        $mime = $this->resolveMimeType($downloadName);

        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                foreach ($candidates as $candidate) {
                    if ($disk->exists($candidate)) {
                        $stream = $disk->readStream($candidate);
                        if (is_resource($stream)) {
                            $headers = [
                                'Content-Type' => $mime,
                                'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, addslashes($downloadName)),
                                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                                'Pragma' => 'no-cache',
                            ];

                            if ($disposition === 'inline') {
                                return response()->stream(
                                    static function () use ($stream): void {
                                        fpassthru($stream);
                                        if (is_resource($stream)) {
                                            fclose($stream);
                                        }
                                    },
                                    200,
                                    $headers
                                );
                            }

                            return response()->streamDownload(
                                static function () use ($stream): void {
                                    fpassthru($stream);
                                    if (is_resource($stream)) {
                                        fclose($stream);
                                    }
                                },
                                $downloadName,
                                $headers
                            );
                        }
                    }
                }
            } catch (\Throwable) {
                // Ignore disk failure and try next available storage disk
                continue;
            }
        }

        // Direct local filesystem check
        foreach ($candidates as $candidate) {
            foreach ([
                storage_path('app/public/'.$candidate),
                storage_path('app/'.$candidate),
                public_path('storage/'.$candidate),
            ] as $localPath) {
                if (file_exists($localPath) && is_readable($localPath)) {
                    return response()->file($localPath, [
                        'Content-Type' => $mime,
                        'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, addslashes($downloadName)),
                    ]);
                }
            }
        }

        return null;
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
