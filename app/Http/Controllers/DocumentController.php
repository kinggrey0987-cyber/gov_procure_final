<?php

namespace App\Http\Controllers;

use App\Http\Requests\Document\DeleteDocumentRequest;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Models\Document;
use App\Models\PublicDisclosure;
use App\Services\Documents\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $service)
    {
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

    public function show(Request $request, Document $document): BinaryFileResponse
    {
        try {
            $this->service->authorizeViewDocument($document, $request->user());
        } catch (ValidationException) {
            abort(403);
        }

        $path = $this->resolveExistingPublicDiskPath((string) $document->file_path);
        abort_unless($path !== null, 404);

        return response()->download(Storage::disk('public')->path($path), (string) $document->file_name);
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

    public function downloadPublicDisclosureDocument(PublicDisclosure $public_disclosure, Document $document): BinaryFileResponse
    {
        abort_unless($document->isRelatedTo('public_disclosures', (int) $public_disclosure->disclosure_id), 404);

        $isPublished = strtoupper((string) ($public_disclosure->status ?? '')) === 'PUBLISHED'
            && (bool) $public_disclosure->published_at;
        abort_unless($isPublished, 404);

        $path = $this->resolveExistingPublicDiskPath((string) $document->file_path);
        abort_unless($path !== null, 404);

        return response()->download(Storage::disk('public')->path($path), (string) $document->file_name);
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
            if ($candidate !== '' && Storage::disk('public')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function transformDocument(Document $document): array
    {
        return [
            'document_id' => (int) $document->document_id,
            'title' => (string) $document->title,
            'file_name' => (string) $document->file_name,
            'file_path' => (string) $document->file_path,
            'related_module' => (string) $document->related_module,
            'related_reference_id' => (int) $document->related_reference_id,
            'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
            'uploaded_by' => (int) $document->uploaded_by,
            'uploader_name' => $document->uploader?->name,
        ];
    }
}
