<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Claim\StoreClaimRequest;
use App\Http\Requests\Claim\UpdateClaimStatusRequest;
use App\Models\Claim;
use App\Models\DistributionRecord;
use App\Services\Documents\DocumentService;
use App\Services\History\HistoryTimelineService;
use App\Services\Inventory\ClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ClaimController
{
    public function __construct(
        private readonly ClaimService $claimService,
        private readonly DocumentService $documentService,
        private readonly HistoryTimelineService $historyTimelineService,
    ) {
    }

    public function index(): Response
    {
        abort_unless(auth()->user()->can('claim.view-any'), 403);

        $query = Claim::query()->with(['distributionRecord', 'claimant', 'office']);

        if (auth()->user()->isOfficeUser()) {
            $query->where('office_id', (int) auth()->user()->office_id);
        }

        $claims = $query->orderByDesc('created_at')->paginate(20);

        return Inertia::render('Inventory/Claim/Index', [
            'claims' => $claims,
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()->can('claim.create'), 403);

        $distributionRecords = DistributionRecord::query()
            ->when(auth()->user()->isOfficeUser(), fn ($query) => $query->where('office_id', auth()->user()->office_id))
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->get();

        return Inertia::render('Inventory/Claim/Create', [
            'distributionRecords' => $distributionRecords,
        ]);
    }

    public function store(StoreClaimRequest $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('claim.create'), 403);

        $validated = $request->validated();
        $distributionRecord = DistributionRecord::query()
            ->where('distribution_record_id', (int) $validated['distribution_record_id'])
            ->firstOrFail();

        $claim = $this->claimService->createClaim(auth()->user(), array_merge($validated, [
            'distribution_record' => $distributionRecord,
        ]));

        if ($request->hasFile('attachment_file')) {
            $this->documentService->upload(
                auth()->user(),
                'claims',
                (int) $claim->claims_id,
                [
                    'title' => (string) ($validated['attachment_title'] ?? ($validated['claim_type'].' Issue Attachment')),
                    'file' => $request->file('attachment_file'),
                ],
            );
        }

        $showRoute = auth()->user()->isOfficeUser() ? 'office.claims.show' : 'inventory.claims.show';

        return Redirect::route($showRoute, $claim->claims_id)
            ->with('success', 'Claim recorded successfully.');
    }

    public function show(Claim $claim): Response
    {
        abort_unless(auth()->user()->can('claim.view', $claim), 403);

        $history = $this->historyTimelineService->forRecord(auth()->user(), $claim);

        $claim->load(['distributionRecord.items', 'claimant', 'office']);

        $documents = $this->documentService
            ->listForRelated(auth()->user(), 'claims', (int) $claim->claims_id)
            ->map(fn ($document): array => [
                'document_id' => (int) $document->document_id,
                'title' => (string) $document->title,
                'file_name' => (string) $document->file_name,
                'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
                'uploaded_by' => (int) $document->uploaded_by,
                'uploader_name' => $document->uploader?->name,
            ])->values();

        return Inertia::render('Inventory/Claim/Show', [
            'claim' => $claim,
            'attachments' => $documents,
            'attachmentPermissions' => [
                'upload' => auth()->user()->can('claim.view', $claim),
                'delete' => auth()->user()->can('claim.view', $claim),
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

    public function updateStatus(UpdateClaimStatusRequest $request, Claim $claim): RedirectResponse
    {
        abort_unless(auth()->user()->can('claim.update', $claim), 403);

        $this->claimService->updateClaimStatus($claim, auth()->user(), $request->validated());

        $showRoute = auth()->user()->isOfficeUser() ? 'office.claims.show' : 'inventory.claims.show';

        return Redirect::route($showRoute, $claim->claims_id)
            ->with('success', 'Claim status updated successfully.');
    }
}
