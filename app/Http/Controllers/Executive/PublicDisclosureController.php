<?php

namespace App\Http\Controllers\Executive;

use App\Http\Requests\PublicDisclosure\PublishPublicDisclosureRequest;
use App\Http\Requests\PublicDisclosure\StorePublicDisclosureRequest;
use App\Http\Requests\PublicDisclosure\UpdatePublicDisclosureRequest;
use App\Models\PublicDisclosure;
use App\Services\Analytics\PublicDisclosureService;
use App\Services\Documents\DocumentService;
use App\Services\Executive\DisclosureEligibilityService;
use App\Services\History\HistoryTimelineService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicDisclosureController
{
    public function __construct(
        private readonly PublicDisclosureService $disclosureService,
        private readonly HistoryTimelineService $historyTimelineService,
        private readonly DisclosureEligibilityService $eligibilityService,
        private readonly DocumentService $documentService,
    ) {
    }

    public function index(): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.view'), 403);

        // Keep the legacy endpoint but route runtime usage to the active disclosure management surface.
        return Redirect::route('executive.transparency-monitoring');
    }

    public function create(): Response
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.manage'), 403);

        return Inertia::render('Executive/Disclosures/Create', [
            'eligibleSources' => $this->eligibilityService->listEligibleRecords(),
        ]);
    }

    public function edit(PublicDisclosure $public_disclosure): Response
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.manage'), 403);

        abort_unless((string) $public_disclosure->status === 'DRAFT', 403);

        return Inertia::render('Executive/Disclosures/Edit', [
            'disclosure' => $public_disclosure,
            'eligibleSources' => $this->eligibilityService->listEligibleRecords(),
        ]);
    }

    public function store(StorePublicDisclosureRequest $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.manage'), 403);

        $payload = $request->validated();

        if ($request->hasFile('main_file')) {
            /** @var UploadedFile $mainFile */
            $mainFile = $request->file('main_file');
            $storedFileName = Str::uuid()->toString().'_'.$mainFile->getClientOriginalName();
            $payload['file_path'] = $mainFile->storeAs('public_disclosures', $storedFileName, 'public');
        }

        $disclosure = $this->disclosureService->createFromPayload($user, $payload);

        $supportingFiles = $request->file('supporting_files', []);
        foreach ($supportingFiles as $supportingFile) {
            if (! $supportingFile instanceof UploadedFile) {
                continue;
            }

            $this->documentService->upload($user, 'public_disclosures', (int) $disclosure->disclosure_id, [
                'title' => pathinfo($supportingFile->getClientOriginalName(), PATHINFO_FILENAME),
                'file' => $supportingFile,
            ]);
        }

        return Redirect::route('executive.disclosures.show', $disclosure->disclosure_id)
            ->with('success', (string) $disclosure->status === 'PUBLISHED' ? 'Disclosure published successfully.' : 'Disclosure draft created successfully.');
    }

    public function update(UpdatePublicDisclosureRequest $request, PublicDisclosure $public_disclosure): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.manage'), 403);

        $payload = $request->validated();

        $updated = $this->disclosureService->updateDraft($public_disclosure, $user, $payload);

        if (strtoupper((string) ($payload['status'] ?? 'DRAFT')) === 'PUBLISHED') {
            $updated = $this->disclosureService->publish(
                disclosure: $updated,
                actor: $user,
                remarks: $payload['remarks'] ?? null,
            );
        }

        return Redirect::route('executive.disclosures.show', $public_disclosure->disclosure_id)
            ->with('success', (string) $updated->status === 'PUBLISHED' ? 'Disclosure published successfully.' : 'Disclosure draft updated successfully.');
    }

    public function show(PublicDisclosure $public_disclosure): Response
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.view'), 403);

        $history = $this->historyTimelineService->forRecord($user, $public_disclosure);

        $attachments = $this->documentService
            ->listForRelated($user, 'public_disclosures', (int) $public_disclosure->disclosure_id)
            ->map(fn ($document): array => [
                'document_id' => (int) $document->document_id,
                'title' => (string) $document->title,
                'file_name' => (string) $document->file_name,
                'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
                'uploader_name' => $document->uploader?->name,
            ])->values();

        $public_disclosure->load(['publisher', 'statusHistory.changedBy']);

        return Inertia::render('Executive/Disclosures/Show', [
            'disclosure' => $public_disclosure,
            'attachments' => $attachments,
            'attachmentPermissions' => [
                'upload' => (string) $public_disclosure->status === 'DRAFT',
                'delete' => (string) $public_disclosure->status === 'DRAFT',
            ],
            'statusHistory' => $history['status']->map(fn ($entry): array => [
                'id' => (int) $entry->id,
                'old_status' => $entry->old_status,
                'new_status' => (string) $entry->new_status,
                'remarks' => $entry->remarks,
                'changed_at' => optional($entry->changed_at)?->toIso8601String(),
                'changed_by' => $entry->changedBy ? [
                    'id' => (int) $entry->changedBy->id,
                    'name' => (string) $entry->changedBy->name,
                ] : null,
            ])->values(),
            'approvalHistory' => $history['approval']->map(fn ($entry): array => [
                'id' => (int) $entry->id,
                'status' => (string) $entry->status,
                'remarks' => $entry->remarks,
                'approved_at' => optional($entry->approved_at)?->toIso8601String(),
                'approver' => $entry->approver ? [
                    'id' => (int) $entry->approver->id,
                    'name' => (string) $entry->approver->name,
                ] : null,
            ])->values(),
        ]);
    }

    public function publish(PublishPublicDisclosureRequest $request, PublicDisclosure $public_disclosure): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->can('public-disclosure.publish', $public_disclosure), 403);

        $this->disclosureService->publish(
            disclosure: $public_disclosure,
            actor: $user,
            remarks: $request->validated('remarks'),
        );

        return Redirect::route('executive.disclosures.show', $public_disclosure->disclosure_id)
            ->with('success', 'Disclosure published successfully.');
    }
}
