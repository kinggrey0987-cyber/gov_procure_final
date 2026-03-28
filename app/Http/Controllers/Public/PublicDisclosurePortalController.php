<?php

namespace App\Http\Controllers\Public;

use App\Models\PublicDisclosure;
use App\Services\Documents\DocumentService;
use Inertia\Inertia;
use Inertia\Response;

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
        abort_unless((string) $public_disclosure->status === 'PUBLISHED' && $public_disclosure->published_at, 404);

        $documents = $this->documentService
            ->listForRelated(auth()->user(), 'public_disclosures', (int) $public_disclosure->disclosure_id)
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
}
