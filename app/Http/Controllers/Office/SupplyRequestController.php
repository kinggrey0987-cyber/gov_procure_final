<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Requests\Office\StoreSupplyRequestRequest;
use App\Http\Requests\Office\SubmitSupplyRequestRequest;
use App\Http\Requests\Office\UpdateSupplyRequestRequest;
use App\Models\DistributionRecord;
use App\Models\Ppmp;
use App\Models\SupplyRequest;
use App\Services\History\HistoryTimelineService;
use App\Services\Office\SupplyRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplyRequestController extends Controller
{
    public function __construct(
        private readonly SupplyRequestService $service,
        private readonly HistoryTimelineService $historyTimelineService,
    ) {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('office.supply-request.view-any'), 403);

        $supplyRequests = SupplyRequest::query()
            ->with('items')
            ->forOffice((int) $request->user()?->office_id)
            ->latest('supply_request_id')
            ->paginate(15)
            ->through(fn (SupplyRequest $supplyRequest): array => [
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'request_code' => (string) $supplyRequest->request_code,
                'status' => (string) $supplyRequest->status,
                'request_date' => optional($supplyRequest->request_date)?->toDateString(),
                'items_count' => (int) $supplyRequest->items->count(),
                'items_summary' => $supplyRequest->items->take(2)->pluck('item_name_snapshot')->implode(', '),
            ]);

        return Inertia::render('Office/SupplyRequests/Index', [
            'supplyRequests' => $supplyRequests,
        ]);
    }

    public function create(Request $request): Response
    {
        $officeId = (int) $request->user()?->office_id;
        $ppmpOptions = $this->service->ppmpOptionsForOffice($officeId);
        $selectedPpmpId = (int) $request->query('ppmp_id', 0);
        $isAllowedSelection = collect($ppmpOptions)
            ->contains(fn (array $option): bool => (int) $option['ppmp_id'] === $selectedPpmpId);

        if (! $isAllowedSelection) {
            $selectedPpmpId = 0;
        }

        return Inertia::render('Office/SupplyRequests/Create', [
            'ppmpOptions' => $ppmpOptions,
            'selectedPpmpId' => $selectedPpmpId > 0 ? $selectedPpmpId : null,
            'ppmpItemOptions' => $selectedPpmpId > 0
                ? $this->service->ppmpItemOptionsForRequestContext($officeId, $selectedPpmpId)
                : [],
        ]);
    }

    public function store(StoreSupplyRequestRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $supplyRequest = $this->service->create($request->user(), $payload);

        $items = $payload['items'] ?? [];
        foreach ($items as $item) {
            $this->service->addItem($supplyRequest, $request->user(), $item);
        }

        return redirect()->route('office.supply-requests.show', $supplyRequest->supply_request_id)
            ->with('success', 'Supply request created successfully.');
    }

    public function show(Request $request, SupplyRequest $supplyRequest): Response
    {
        abort_unless($request->user()?->can('office.supply-request.view', $supplyRequest), 403);

        $supplyRequest->load(['items.ppmpItem.item', 'office', 'requester', 'approver']);

        $ppmpItemContext = (int) $supplyRequest->ppmp_id > 0
            ? collect($this->service->ppmpItemOptionsForRequestContext((int) $supplyRequest->office_id, (int) $supplyRequest->ppmp_id))
                ->keyBy('ppmp_item_id')
            : collect();

        $supplyRequestData = [
            'supply_request_id' => (int) $supplyRequest->supply_request_id,
            'office_id' => (int) $supplyRequest->office_id,
            'ppmp_id' => $supplyRequest->ppmp_id ? (int) $supplyRequest->ppmp_id : null,
            'request_code' => (string) $supplyRequest->request_code,
            'status' => (string) $supplyRequest->status,
            'request_date' => optional($supplyRequest->request_date)?->toDateString(),
            'needed_by_date' => optional($supplyRequest->needed_by_date)?->toDateString(),
            'request_category' => (string) $supplyRequest->request_category,
            'purpose' => (string) $supplyRequest->purpose,
            'priority_level' => (string) $supplyRequest->priority_level,
            'review_notes' => $supplyRequest->review_notes,
            'items' => $supplyRequest->items->map(function ($item) use ($ppmpItemContext): array {
                $context = $ppmpItemContext->get((int) $item->ppmp_item_id, []);

                return [
                    'supply_request_item_id' => (int) $item->supply_request_item_id,
                    'ppmp_item_id' => (int) $item->ppmp_item_id,
                    'item_name_snapshot' => (string) ($item->item_name_snapshot ?? ''),
                    'quantity_requested' => (float) $item->quantity_requested,
                    'planned_quantity' => isset($context['planned_quantity']) ? (float) $context['planned_quantity'] : null,
                    'already_requested_quantity' => isset($context['requested_quantity']) ? (float) $context['requested_quantity'] : null,
                    'remaining_quantity' => isset($context['remaining_quantity']) ? (float) $context['remaining_quantity'] : null,
                ];
            })->values()->all(),
        ];

        $history = $this->historyTimelineService->forRecord($request->user(), $supplyRequest);

        $distributionRecords = DistributionRecord::query()
            ->where('supply_request_id', (int) $supplyRequest->supply_request_id)
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->latest('distribution_record_id')
            ->get(['distribution_record_id', 'status', 'storage_location', 'release_date'])
            ->map(fn (DistributionRecord $record): array => [
                'distribution_record_id' => (int) $record->distribution_record_id,
                'status' => (string) $record->status,
                'storage_location' => (string) ($record->storage_location ?? ''),
                'release_date' => optional($record->release_date)?->toDateString(),
            ])
            ->values();

        $canEdit = $request->user()?->can('office.supply-request.update', $supplyRequest) ?? false;
        $canSubmit = $request->user()?->can('office.supply-request.submit', $supplyRequest) ?? false;

        return Inertia::render('Office/SupplyRequests/Show', [
            'supplyRequest' => $supplyRequestData,
            'canEdit' => $canEdit,
            'canSubmit' => $canSubmit,
            'distributionRecords' => $distributionRecords,
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

    public function edit(Request $request, SupplyRequest $supplyRequest): Response
    {
        abort_unless($request->user()?->can('office.supply-request.update', $supplyRequest), 403);

        $officeId = (int) $request->user()?->office_id;
        $selectedPpmpId = (int) ($supplyRequest->ppmp_id ?? 0);

        return Inertia::render('Office/SupplyRequests/Edit', [
            'supplyRequest' => $supplyRequest->load('items'),
            'ppmpOptions' => $this->service->ppmpOptionsForOffice($officeId),
            'selectedPpmpId' => $selectedPpmpId > 0 ? $selectedPpmpId : null,
            'ppmpItemOptions' => $selectedPpmpId > 0
                ? $this->service->ppmpItemOptionsForRequestContext($officeId, $selectedPpmpId)
                : [],
        ]);
    }

    public function ppmpItemsForPpmp(Request $request, Ppmp $ppmp): JsonResponse
    {
        abort_unless($request->user()?->can('office.supply-request.create'), 403);

        $officeId = (int) $request->user()?->office_id;
        if ((int) $ppmp->office_id !== $officeId) {
            abort(403);
        }

        return response()->json([
            'ppmp_item_options' => $this->service->ppmpItemOptionsForRequestContext($officeId, (int) $ppmp->ppmp_id),
        ]);
    }

    public function update(UpdateSupplyRequestRequest $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        $this->service->update($supplyRequest, $request->user(), $request->validated());

        return redirect()->route('office.supply-requests.show', $supplyRequest->supply_request_id)
            ->with('success', 'Supply request updated successfully.');
    }

    public function submit(SubmitSupplyRequestRequest $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        $this->service->submit($supplyRequest, $request->user(), $request->input('review_notes'));

        return redirect()->route('office.supply-requests.show', $supplyRequest->supply_request_id)
            ->with('success', 'Supply request submitted successfully.');
    }

    public function suppliesReadyForClaim(Request $request): Response
    {
        abort_unless($request->user()?->can('office.supply-request.view-any'), 403);

        $officeId = (int) $request->user()?->office_id;

        $records = DistributionRecord::query()
            ->with(['supplyRequest.items'])
            ->forOffice($officeId)
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->latest('distribution_record_id')
            ->paginate(15)
            ->through(fn (DistributionRecord $record): array => [
                'distribution_record_id' => (int) $record->distribution_record_id,
                'request_id' => (string) ($record->supplyRequest?->request_code ?? 'N/A'),
                'request_date' => optional($record->supplyRequest?->request_date)?->toDateString(),
                'items_summary' => (string) ($record->supplyRequest?->items->take(2)->pluck('item_name_snapshot')->implode(', ') ?: 'No items listed'),
                'items_count' => (int) ($record->supplyRequest?->items->count() ?? 0),
                'location' => (string) ($record->storage_location ?? 'GSO Storage'),
                'ready_since' => optional($record->release_date)?->toDateString(),
                'status' => (string) $record->status,
                'supply_request_id' => (int) ($record->supply_request_id ?? 0),
            ]);

        return Inertia::render('Office/SuppliesReadyForClaim/Index', [
            'records' => $records,
        ]);
    }

}
