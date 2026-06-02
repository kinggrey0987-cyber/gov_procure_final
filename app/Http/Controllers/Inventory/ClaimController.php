<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Claim\StoreClaimRequest;
use App\Http\Requests\Claim\UpdateClaimStatusRequest;
use App\Models\Claim;
use App\Models\DistributionRecord;
use App\Services\Documents\DocumentService;
use App\Services\History\HistoryTimelineService;
use App\Services\Inventory\ClaimService;
use Illuminate\Http\Request;
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

    public function index(Request $request): Response
    {
        $user = request()->user();

        abort_unless($user->can('claim.view-any'), 403);

        $status = strtoupper(trim((string) $request->input('status', '')));
        $year = trim((string) $request->input('year', ''));
        $month = trim((string) $request->input('month', ''));
        $search = trim((string) $request->input('search', ''));

        $yearOptions = Claim::query()
            ->where('created_at', '!=', null)
            ->latest('created_at')
            ->pluck('created_at')
            ->map(fn ($value): int => (int) $value->year)
            ->unique()
            ->values();

        $query = Claim::query()->with(['distributionRecord', 'claimant', 'office']);

        if ($user->isOfficeUser()) {
            $query->where('office_id', (int) $user->office_id);
        }

        $routePrefix = $user->isOfficeUser() ? 'office.claims' : 'inventory.claims';

        $claims = $query
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when(preg_match('/^\d{4}$/', $year) === 1, fn ($builder) => $builder->whereYear('created_at', (int) $year))
            ->when(preg_match('/^\d{1,2}$/', $month) === 1, fn ($builder) => $builder->whereMonth('created_at', (int) $month))
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($nested) use ($search): void {
                    $nested->whereLikeInsensitive('claim_code', $search)
                        ->orWhereLikeInsensitive('claim_type', $search)
                        ->orWhereHas('office', fn ($officeQuery) => $officeQuery->whereLikeInsensitive('name', $search));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(5)
            ->withQueryString();

        return Inertia::render('Inventory/Claim/Index', [
            'claims' => $claims,
            'filters' => [
                'status' => $status,
                'year' => $year,
                'month' => $month,
                'search' => $search,
            ],
            'filter_options' => [
                'years' => $yearOptions,
            ],
            'routes' => [
                'index' => $routePrefix.'.index',
                'create' => $routePrefix.'.create',
                'show' => $routePrefix.'.show',
            ],
            'permissions' => [
                'create' => $user->can('claim.create'),
                'process' => $user->isGsoOfficer(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = request()->user();

        abort_unless($user->can('claim.create'), 403);

        $parentClaim = null;
        $requestedParentClaimId = (int) $request->query('parent_claim_id', 0);
        if ($requestedParentClaimId > 0) {
            $candidateParentClaim = Claim::query()
                ->where('claims_id', $requestedParentClaimId)
                ->where('office_id', (int) $user->office_id)
                ->whereNotIn('status', ['RESOLVED', 'REJECTED'])
                ->firstOrFail();

            $parentClaim = $candidateParentClaim;
        }

        $distributionRecords = DistributionRecord::query()
            ->where('office_id', (int) $user->office_id)
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->with(['office', 'supplyRequest', 'items.item'])
            ->orderByDesc('release_date')
            ->orderByDesc('distribution_record_id')
            ->get();

        $requestedDistributionRecordId = (int) $request->query('distribution_record_id', 0);
        $requestedDistributionItemId = (int) $request->query('distribution_item_id', 0);
        $preselectedDistributionRecordId = $requestedDistributionRecordId > 0
            && $distributionRecords->contains(fn (DistributionRecord $record): bool => (int) $record->distribution_record_id === $requestedDistributionRecordId)
                ? $requestedDistributionRecordId
                : null;

        return Inertia::render('Inventory/Claim/Create', [
            'distributionRecords' => $distributionRecords->map(fn (DistributionRecord $record): array => [
                'distribution_record_id' => (int) $record->distribution_record_id,
                'distribution_code' => (string) $record->distribution_code,
                'office_id' => (int) $record->office_id,
                'office_name' => (string) ($record->office?->name ?? 'Unknown Office'),
                'release_date' => optional($record->release_date)?->toDateString(),
                'status' => (string) $record->status,
                'request_code' => (string) ($record->supplyRequest?->request_code ?? '-'),
                'items' => $record->items->map(fn ($item): array => [
                    'distribution_item_id' => (int) $item->distribution_item_id,
                    'item_id' => (int) $item->item_id,
                    'item_name' => (string) ($item->item?->item_name ?? $item->item_name_snapshot),
                    'quantity_released' => (float) $item->quantity_released,
                    'quantity_received' => (float) $item->quantity_received,
                    'quantity_damaged' => (float) $item->quantity_damaged,
                    'quantity_missing' => (float) $item->quantity_missing,
                    'unit' => (string) $item->unit,
                ])->values(),
            ])->values(),
            'preselectedDistributionRecordId' => $preselectedDistributionRecordId,
            'preselectedDistributionItemId' => $requestedDistributionItemId > 0 ? $requestedDistributionItemId : null,
            'parentClaim' => $parentClaim instanceof Claim ? [
                'claims_id' => (int) $parentClaim->claims_id,
                'claim_code' => (string) $parentClaim->claim_code,
                'status' => (string) $parentClaim->status,
                'distribution_item_id' => $parentClaim->distribution_item_id ? (int) $parentClaim->distribution_item_id : null,
            ] : null,
        ]);
    }

    public function store(StoreClaimRequest $request): RedirectResponse
    {
        abort_unless(request()->user()->can('claim.create'), 403);

        $validated = $request->validated();
        $distributionRecord = DistributionRecord::query()
            ->where('distribution_record_id', (int) $validated['distribution_record_id'])
            ->firstOrFail();

        $claim = $this->claimService->createClaim(request()->user(), array_merge($validated, [
            'distribution_record' => $distributionRecord,
        ]));

        if ($request->hasFile('attachment_file')) {
            $this->documentService->upload(
                request()->user(),
                'claims',
                (int) $claim->claims_id,
                [
                    'title' => (string) ($validated['attachment_title'] ?? ($validated['claim_type'].' Issue Attachment')),
                    'file' => $request->file('attachment_file'),
                ],
            );
        }

        $showRoute = request()->user()->isOfficeUser() ? 'office.claims.show' : 'inventory.claims.show';

        return Redirect::route($showRoute, $claim->claims_id)
            ->with('success', 'Claim recorded successfully.');
    }

    public function show(Claim $claim): Response
    {
        $user = request()->user();

        abort_unless($user->can('claim.view', $claim), 403);

        $history = $this->historyTimelineService->forRecord($user, $claim);
        $routePrefix = $user->isOfficeUser() ? 'office.claims' : 'inventory.claims';

        $claim->load(['distributionRecord.items.item.category', 'distributionRecord.office', 'distributionRecord.supplyRequest', 'distributionItem.item.inventory', 'claimant', 'office', 'parentClaim', 'followUpClaims']);

        $replacementAvailableStock = $claim->distributionItem?->item?->inventory
            ? (float) $claim->distributionItem->item->inventory->available_stock
            : null;

        $documents = $this->documentService
            ->listForRelated($user, 'claims', (int) $claim->claims_id)
            ->map(fn ($document): array => [
                'document_id' => (int) $document->document_id,
                'title' => (string) $document->title,
                'file_name' => (string) $document->file_name,
                'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
                'uploaded_by' => (int) $document->uploaded_by,
                'uploader_name' => $document->uploader?->name,
            ])->values();

        return Inertia::render('Inventory/Claim/Show', [
            'claim' => [
                'claims_id' => (int) $claim->claims_id,
                'claim_code' => (string) $claim->claim_code,
                'distribution_item_id' => $claim->distribution_item_id ? (int) $claim->distribution_item_id : null,
                'status' => (string) $claim->status,
                'claim_type' => (string) $claim->claim_type,
                'claim_date' => optional($claim->claim_date)?->toDateString(),
                'claim_location' => (string) $claim->claim_location,
                'quantity_disputed' => (float) $claim->quantity_disputed,
                'quantity_damaged' => (float) $claim->quantity_damaged,
                'quantity_missing' => (float) $claim->quantity_missing,
                'resolution_type' => $claim->resolution_type,
                'replacement_quantity' => (float) $claim->replacement_quantity,
                'replacement_available_stock' => $replacementAvailableStock,
                'remarks' => $claim->remarks,
                'parent_claim_id' => $claim->parent_claim_id ? (int) $claim->parent_claim_id : null,
                'source_type' => $claim->source_type,
            ],
            'parentClaim' => $claim->parentClaim ? [
                'claims_id' => (int) $claim->parentClaim->claims_id,
                'claim_code' => (string) $claim->parentClaim->claim_code,
                'status' => (string) $claim->parentClaim->status,
            ] : null,
            'followUpClaims' => $claim->followUpClaims->map(fn (Claim $followUp): array => [
                'claims_id' => (int) $followUp->claims_id,
                'claim_code' => (string) $followUp->claim_code,
                'status' => (string) $followUp->status,
            ])->values(),
            'linkedDistribution' => $claim->distributionRecord ? [
                'distribution_record_id' => (int) $claim->distributionRecord->distribution_record_id,
                'distribution_code' => (string) $claim->distributionRecord->distribution_code,
                'status' => (string) $claim->distributionRecord->status,
                'release_date' => optional($claim->distributionRecord->release_date)?->toDateString(),
                'office_name' => (string) ($claim->distributionRecord->office?->name ?? '-'),
                'request_code' => (string) ($claim->distributionRecord->supplyRequest?->request_code ?? '-'),
                'items' => $claim->distributionRecord->items->map(fn ($item): array => [
                    'distribution_item_id' => (int) $item->distribution_item_id,
                    'item_name' => (string) ($item->item?->item_name ?? $item->item_name_snapshot),
                    'category_name' => (string) ($item->item?->category?->category_name ?? ''),
                    'quantity_released' => (float) $item->quantity_released,
                    'quantity_received' => (float) $item->quantity_received,
                    'quantity_damaged' => (float) $item->quantity_damaged,
                    'quantity_missing' => (float) $item->quantity_missing,
                    'unit' => (string) $item->unit,
                ])->values(),
            ] : null,
            'attachments' => $documents,
            'attachmentPermissions' => [
                'upload' => $user->can('claim.attachments.manage', $claim),
                'delete' => $user->can('claim.attachments.manage', $claim),
            ],
            'routes' => [
                'index' => $routePrefix.'.index',
                'create' => $routePrefix.'.create',
                'show' => $routePrefix.'.show',
                'updateStatus' => 'inventory.claims.update-status',
            ],
            'permissions' => [
                'update' => $user->can('claim.update', $claim),
                'createFollowUp' => $user->can('claim.create')
                    && $user->isOfficeUser()
                    && ! in_array((string) $claim->status, ['RESOLVED', 'REJECTED'], true)
                    && $claim->distribution_record_id !== null
                    && $claim->distribution_item_id !== null,
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
        abort_unless(request()->user()->can('claim.update', $claim), 403);

        $this->claimService->updateClaimStatus($claim, request()->user(), $request->validated());

        $showRoute = request()->user()->isOfficeUser() ? 'office.claims.show' : 'inventory.claims.show';

        return Redirect::route($showRoute, $claim->claims_id)
            ->with('success', 'Claim status updated successfully.');
    }
}
