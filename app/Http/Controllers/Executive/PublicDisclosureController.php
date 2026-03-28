<?php

namespace App\Http\Controllers\Executive;

use App\Http\Requests\PublicDisclosure\PublishPublicDisclosureRequest;
use App\Http\Requests\PublicDisclosure\StorePublicDisclosureRequest;
use App\Models\PublicDisclosure;
use App\Services\Analytics\PublicDisclosureService;
use App\Services\History\HistoryTimelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class PublicDisclosureController
{
    public function __construct(
        private readonly PublicDisclosureService $disclosureService,
        private readonly HistoryTimelineService $historyTimelineService,
    ) {
    }

    public function index(): Response
    {
        abort_unless(auth()->user()->can('public-disclosure.view'), 403);

        $disclosures = PublicDisclosure::query()
            ->with('publisher')
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Executive/Disclosures/Index', [
            'disclosures' => $disclosures,
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()->can('public-disclosure.manage'), 403);

        return Inertia::render('Executive/Disclosures/Create');
    }

    public function store(StorePublicDisclosureRequest $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('public-disclosure.manage'), 403);

        $disclosure = $this->disclosureService->createDraft(auth()->user(), $request->validated());

        return Redirect::route('executive.disclosures.show', $disclosure->disclosure_id)
            ->with('success', 'Disclosure draft created successfully.');
    }

    public function show(PublicDisclosure $public_disclosure): Response
    {
        abort_unless(auth()->user()->can('public-disclosure.view'), 403);

        $history = $this->historyTimelineService->forRecord(auth()->user(), $public_disclosure);

        $public_disclosure->load(['publisher', 'statusHistory.changedBy']);

        return Inertia::render('Executive/Disclosures/Show', [
            'disclosure' => $public_disclosure,
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
        abort_unless(auth()->user()->can('public-disclosure.publish', $public_disclosure), 403);

        $this->disclosureService->publish(
            disclosure: $public_disclosure,
            actor: auth()->user(),
            remarks: $request->validated('remarks'),
        );

        return Redirect::route('executive.disclosures.show', $public_disclosure->disclosure_id)
            ->with('success', 'Disclosure published successfully.');
    }
}
