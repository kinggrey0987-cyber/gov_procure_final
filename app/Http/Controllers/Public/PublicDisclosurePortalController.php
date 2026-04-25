<?php

namespace App\Http\Controllers\Public;

use App\Models\PublicDisclosure;
use App\Services\Documents\DocumentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicDisclosurePortalController
{
    public function __construct(private readonly DocumentService $documentService)
    {
    }

    private function publicDisk(): string
    {
        return (string) config('filesystems.public_disk', 'public');
    }

    public function index(): Response
    {
        $disclosures = PublicDisclosure::query()
            ->published()
            ->orderByDesc('published_at')
            ->paginate(20);

        return Inertia::render('Public/Disclosures/Index', [
            'disclosures' => $disclosures,
        ]);
    }

    public function show(PublicDisclosure $public_disclosure): Response
    {
        abort_unless($this->isPublished($public_disclosure), 404);

        $documents = $this->documentService
            ->listForRelated(Auth::user(), 'public_disclosures', (int) $public_disclosure->disclosure_id)
            ->map(fn ($document): array => [
                'document_id' => (int) $document->document_id,
                'title' => (string) $document->title,
                'file_name' => (string) $document->file_name,
                'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
            ])->values();

        return Inertia::render('Public/Disclosures/Show', [
            'disclosure' => $public_disclosure,
            'attachments' => $documents,
            'backPage' => (int) request()->query('page', 0) > 0 ? (int) request()->query('page') : null,
        ]);
    }

    public function download(PublicDisclosure $public_disclosure): StreamedResponse
    {
        abort_unless($this->isPublished($public_disclosure), 404);
        abort_unless(is_string($public_disclosure->file_path) && $public_disclosure->file_path !== '', 404);

        $path = $this->resolveExistingPublicDiskPath((string) $public_disclosure->file_path);
        abort_unless($path !== null, 404);

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
