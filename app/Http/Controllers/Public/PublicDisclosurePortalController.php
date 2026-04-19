<?php

namespace App\Http\Controllers\Public;

use App\Models\PublicDisclosure;
use App\Services\Documents\DocumentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicDisclosurePortalController
{
    public function __construct(private readonly DocumentService $documentService)
    {
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
        ]);
    }

    public function download(PublicDisclosure $public_disclosure): BinaryFileResponse
    {
        abort_unless($this->isPublished($public_disclosure), 404);
        abort_unless(is_string($public_disclosure->file_path) && $public_disclosure->file_path !== '', 404);

        $path = ltrim((string) $public_disclosure->file_path, '/');
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->download(Storage::disk('public')->path($path), basename($path));
    }

    private function isPublished(PublicDisclosure $disclosure): bool
    {
        return (string) $disclosure->status === 'PUBLISHED' && (bool) $disclosure->published_at;
    }
}
