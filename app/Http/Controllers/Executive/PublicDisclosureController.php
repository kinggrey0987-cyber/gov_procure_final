<?php

namespace App\Http\Controllers\Executive;

use App\Http\Requests\PublicDisclosure\PublishPublicDisclosureRequest;
use App\Http\Requests\PublicDisclosure\StorePublicDisclosureRequest;
use App\Models\PublicDisclosure;
use App\Services\Analytics\PublicDisclosureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class PublicDisclosureController
{
    public function __construct(private readonly PublicDisclosureService $disclosureService)
    {
    }

    public function index(): Response
    {
        abort_unless(auth()->user()->can('public-disclosure.manage'), 403);

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
        abort_unless(auth()->user()->can('public-disclosure.manage'), 403);

        $public_disclosure->load(['publisher', 'statusHistory.changedBy']);

        return Inertia::render('Executive/Disclosures/Show', [
            'disclosure' => $public_disclosure,
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
