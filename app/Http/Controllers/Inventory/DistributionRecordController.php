<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\DistributionRecord\StoreDistributionRecordRequest;
use App\Http\Requests\DistributionRecord\UpdateDistributionRecordRequest;
use App\Models\DistributionRecord;
use App\Models\Delivery;
use App\Models\SupplyRequest;
use Illuminate\Support\Collection;
use App\Services\Inventory\DistributionRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class DistributionRecordController
{
    public function __construct(private readonly DistributionRecordService $distributionService)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless(request()->user()->can('distribution.view-any'), 403);

        $status = strtoupper(trim((string) $request->input('status', '')));
        $year = trim((string) $request->input('year', ''));
        $month = trim((string) $request->input('month', ''));
        $search = trim((string) $request->input('search', ''));

        $yearOptions = DistributionRecord::query()
            ->where('created_at', '!=', null)
            ->latest('created_at')
            ->pluck('created_at')
            ->map(fn ($value): int => (int) $value->year)
            ->unique()
            ->values();

        $records = DistributionRecord::query()
            ->with(['supplyRequest', 'delivery', 'office', 'preparer', 'receiver'])
            ->withCount(['items', 'claims'])
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when(preg_match('/^\d{4}$/', $year) === 1, fn ($query) => $query->whereYear('created_at', (int) $year))
            ->when(preg_match('/^\d{1,2}$/', $month) === 1, fn ($query) => $query->whereMonth('created_at', (int) $month))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('distribution_code', 'like', '%'.$search.'%')
                        ->orWhere('status', 'like', '%'.$search.'%')
                        ->orWhereHas('supplyRequest', fn ($supplyRequestQuery) => $supplyRequestQuery->where('request_code', 'like', '%'.$search.'%'))
                        ->orWhereHas('office', fn ($officeQuery) => $officeQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(5)
            ->withQueryString();

        return Inertia::render('Inventory/Distribution/Index', [
            'records' => $records,
            'filters' => [
                'status' => $status,
                'year' => $year,
                'month' => $month,
                'search' => $search,
            ],
            'filter_options' => [
                'years' => $yearOptions,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('distribution.manage'), 403);

        $focusSupplyRequestId = (int) $request->query('supply_request_id', 0);

        $procurementBackedRows = $this->procurementBackedReleaseRows();
        $inventoryBackedRows = $this->inventoryBackedReleaseRows();

        /** @var Collection<int, array<string, mixed>> $eligibleRows */
        $eligibleRows = $inventoryBackedRows
            ->concat($procurementBackedRows)
            ->unique('supply_request_id')
            ->sortByDesc(fn (array $row): int => (int) $row['sort_timestamp'])
            ->values();

        $focusedReason = null;
        if ($focusSupplyRequestId > 0) {
            $focusedRequest = SupplyRequest::query()
                ->with(['items.item.inventory', 'purchaseRequests', 'distributionRecords.items'])
                ->where('supply_request_id', $focusSupplyRequestId)
                ->first();

            $focusedReason = $this->resolveFocusReason($focusedRequest);
        }

        if ($focusSupplyRequestId > 0) {
            $eligibleRows = $eligibleRows
                ->sortByDesc(fn (array $row): int => (int) $row['supply_request_id'] === $focusSupplyRequestId ? 1 : 0)
                ->values();
        }

        $paginatedRows = $this->paginateEligibleRows($eligibleRows, $request);

        return Inertia::render('Inventory/Distribution/Create', [
            'eligibleRows' => $paginatedRows,
            'focusSupplyRequestId' => $focusSupplyRequestId > 0 ? $focusSupplyRequestId : null,
            'focusReleasableReason' => $focusedReason,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function procurementBackedReleaseRows(): Collection
    {
        return Delivery::query()
            ->verified()
            ->whereHas('purchaseOrder', function ($query): void {
                $query->where('supply_request_id', '!=', null)
                    ->whereHas('supplyRequest.office', fn ($officeQuery) => $officeQuery->where('code', '!=', 'GSO'));
            })
            ->whereDoesntHave('distributionRecords')
            ->with(['purchaseOrder.supplyRequest.office'])
            ->latest('delivery_id')
            ->get()
            ->map(fn (Delivery $delivery): ?array => $this->releaseRowFromVerifiedDelivery($delivery))
            ->filter()
            ->values();
    }

    private function releaseRowFromVerifiedDelivery(Delivery $delivery): ?array
    {
        $supplyRequest = $delivery->purchaseOrder?->supplyRequest;

        if (!$supplyRequest instanceof SupplyRequest) {
            return null;
        }

        return [
            'supply_request_id' => (int) $supplyRequest->supply_request_id,
            'request_code' => (string) $supplyRequest->request_code,
            'office_id' => (int) $supplyRequest->office_id,
            'office_name' => (string) ($supplyRequest->office?->name ?? 'Unknown Office'),
            'release_basis' => 'VERIFIED_DELIVERY',
            'release_basis_label' => 'From Verified Delivery',
            'delivery_id' => (int) $delivery->delivery_id,
            'delivery_code' => (string) $delivery->delivery_code,
            'receipt_status' => (string) $delivery->receipt_status,
            'releasable_reason' => null,
            'sort_timestamp' => (int) ($delivery->created_at?->getTimestamp() ?? $delivery->delivery_id),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function inventoryBackedReleaseRows(): Collection
    {
        return SupplyRequest::query()
            ->submitted()
            ->whereHas('office', fn ($officeQuery) => $officeQuery->where('code', '!=', 'GSO'))
            ->whereDoesntHave('distributionRecords', fn ($query) => $query->where('status', 'DRAFT'))
            ->with(['office', 'items.item.inventory', 'distributionRecords.items'])
            ->latest('supply_request_id')
            ->get()
            ->map(fn (SupplyRequest $request): ?array => $this->releaseRowFromAvailableInventory($request))
            ->filter()
            ->values();
    }

    private function releaseRowFromAvailableInventory(SupplyRequest $request): ?array
    {
        if (! $this->hasRemainingQuantity($request)) {
            return null;
        }

        if ($this->supplyRequestShortageTotal($request) > 0) {
            return null;
        }

        return [
            'supply_request_id' => (int) $request->supply_request_id,
            'request_code' => (string) $request->request_code,
            'office_id' => (int) $request->office_id,
            'office_name' => (string) ($request->office?->name ?? 'Unknown Office'),
            'release_basis' => 'AVAILABLE_INVENTORY',
            'release_basis_label' => 'From Available Inventory',
            'delivery_id' => null,
            'delivery_code' => null,
            'receipt_status' => null,
            'releasable_reason' => null,
            'sort_timestamp' => (int) ($request->created_at?->getTimestamp() ?? $request->supply_request_id),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateEligibleRows(Collection $rows, Request $request): LengthAwarePaginator
    {
        $perPage = 5;
        $page = max((int) $request->query('page', 1), 1);

        return new LengthAwarePaginator(
            items: $rows->forPage($page, $perPage)->values(),
            total: $rows->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    private function supplyRequestShortageTotal(SupplyRequest $request): float
    {
        $shortageTotal = 0.0;
        $releasedByItemId = $this->releasedQuantityByItemId($request);

        foreach ($request->items as $item) {
            $requested = (float) $item->quantity_requested;
            $released = (float) ($releasedByItemId[(int) $item->item_id] ?? 0.0);
            $remaining = max($requested - $released, 0);
            $available = (float) ($item->item?->inventory?->available_stock ?? 0);
            $shortageTotal += max($remaining - $available, 0);
        }

        return $shortageTotal;
    }

    private function hasRemainingQuantity(SupplyRequest $request): bool
    {
        $releasedByItemId = $this->releasedQuantityByItemId($request);

        foreach ($request->items as $item) {
            $requested = (float) $item->quantity_requested;
            $released = (float) ($releasedByItemId[(int) $item->item_id] ?? 0.0);

            if ($requested - $released > 0.0001) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, float>
     */
    private function releasedQuantityByItemId(SupplyRequest $request): array
    {
        return $request->distributionRecords
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->flatMap(fn ($record) => $record->items)
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($items): float => (float) $items->sum('quantity_released'))
            ->all();
    }

    private function resolveFocusReason(?SupplyRequest $request): ?string
    {
        if (! $request instanceof SupplyRequest) {
            return 'Supply request context was not found.';
        }

        if ((string) ($request->office?->code ?? '') === 'GSO') {
            return 'GSO-origin supply requests are internal logistics records and are not eligible for office distribution release.';
        }

        $existingDraftRecord = $request->distributionRecords->firstWhere('status', 'DRAFT');
        if ($existingDraftRecord instanceof DistributionRecord) {
            return 'This request already has a draft distribution record. Continue releasing items in that draft.';
        }

        if (! $this->hasRemainingQuantity($request)) {
            return 'All requested quantities have already been released for this request.';
        }

        $hasPurchaseRequest = $request->purchaseRequests->isNotEmpty();

        $shortageTotal = 0.0;
        $releasedByItemId = $this->releasedQuantityByItemId($request);
        foreach ($request->items as $item) {
            $requested = (float) $item->quantity_requested;
            $released = (float) ($releasedByItemId[(int) $item->item_id] ?? 0.0);
            $remaining = max($requested - $released, 0);
            $available = (float) ($item->item?->inventory?->available_stock ?? 0);
            $shortageTotal += max($remaining - $available, 0);
        }

        if ($hasPurchaseRequest && $shortageTotal > 0) {
            $hasVerifiedDelivery = Delivery::query()
                ->verified()
                ->whereHas('purchaseOrder', function ($query) use ($request): void {
                    $query->where('supply_request_id', (int) $request->supply_request_id);
                })
                ->whereDoesntHave('distributionRecords')
                ->exists();

            if (! $hasVerifiedDelivery) {
                return 'This request is procurement-backed and awaits verified delivery before distribution release.';
            }

            return null;
        }

        if ($shortageTotal > 0) {
            return 'This request has stock shortages and must be queued for procurement instead of direct inventory release.';
        }

        return null;
    }

    public function store(StoreDistributionRecordRequest $request): RedirectResponse
    {
        abort_unless(request()->user()->can('distribution.manage'), 403);

        $record = $this->distributionService->createRecord(request()->user(), $request->validated());

        return Redirect::route('inventory.distribution-records.show', $record->distribution_record_id)
            ->with('success', 'Distribution record created successfully.');
    }

    public function show(DistributionRecord $distribution_record): Response
    {
        abort_unless(request()->user()->can('distribution.view', $distribution_record), 403);

        $distribution_record->load([
            'items.item.category',
            'claims.claimant',
            'supplyRequest.office',
            'supplyRequest.items.item.category',
            'delivery',
            'office',
            'preparer',
            'receiver',
        ]);

        $releasedByItemId = $distribution_record->items
            ->groupBy(fn ($item) => (int) $item->item_id)
            ->map(fn ($group) => (float) $group->sum('quantity_released'));

        return Inertia::render('Inventory/Distribution/Show', [
            'record' => [
                'distribution_record_id' => (int) $distribution_record->distribution_record_id,
                'distribution_code' => (string) $distribution_record->distribution_code,
                'status' => (string) $distribution_record->status,
                'release_date' => optional($distribution_record->release_date)?->toDateString(),
                'storage_location' => $distribution_record->storage_location,
                'notes' => $distribution_record->notes,
                'office' => $distribution_record->office ? [
                    'id' => (int) $distribution_record->office->id,
                    'name' => (string) $distribution_record->office->name,
                ] : null,
                'supply_request' => $distribution_record->supplyRequest ? [
                    'supply_request_id' => (int) $distribution_record->supplyRequest->supply_request_id,
                    'request_code' => (string) $distribution_record->supplyRequest->request_code,
                    'status' => (string) $distribution_record->supplyRequest->status,
                    'items' => $distribution_record->supplyRequest->items->map(function ($item) use ($releasedByItemId): array {
                        $itemId = (int) $item->item_id;
                        $requestedQuantity = (float) $item->quantity_requested;
                        $releasedQuantity = (float) ($releasedByItemId->get($itemId) ?? 0.0);

                        return [
                            'supply_request_item_id' => (int) $item->supply_request_item_id,
                            'item_id' => $itemId,
                            'item_name' => (string) ($item->item?->item_name ?? $item->item_name_snapshot),
                            'category_name' => (string) ($item->item?->category?->category_name ?? '-'),
                            'quantity_requested' => $requestedQuantity,
                            'quantity_released' => $releasedQuantity,
                            'quantity_remaining' => max($requestedQuantity - $releasedQuantity, 0),
                            'unit' => (string) ($item->item?->default_unit ?? 'UNIT'),
                            'available_stock' => (float) ($item->item?->inventory?->available_stock ?? 0),
                        ];
                    })->values(),
                ] : null,
                'delivery' => $distribution_record->delivery ? [
                    'delivery_id' => (int) $distribution_record->delivery->delivery_id,
                    'delivery_code' => (string) $distribution_record->delivery->delivery_code,
                ] : null,
                'preparer' => $distribution_record->preparer ? [
                    'id' => (int) $distribution_record->preparer->id,
                    'name' => (string) $distribution_record->preparer->name,
                ] : null,
                'receiver' => $distribution_record->receiver ? [
                    'id' => (int) $distribution_record->receiver->id,
                    'name' => (string) $distribution_record->receiver->name,
                ] : null,
                'items' => $distribution_record->items->map(fn ($item): array => [
                    'distribution_item_id' => (int) $item->distribution_item_id,
                    'item_id' => (int) $item->item_id,
                    'item_name_snapshot' => (string) $item->item_name_snapshot,
                    'quantity_released' => (float) $item->quantity_released,
                    'unit' => (string) $item->unit,
                    'remarks' => $item->remarks,
                    'item' => $item->item ? [
                        'item_name' => (string) $item->item->item_name,
                        'category_name' => (string) ($item->item->category?->category_name ?? ''),
                    ] : null,
                ])->values(),
                'claims' => $distribution_record->claims->map(fn ($claim): array => [
                    'claims_id' => (int) $claim->claims_id,
                    'claim_code' => (string) $claim->claim_code,
                    'status' => (string) $claim->status,
                    'claim_type' => (string) $claim->claim_type,
                    'claim_date' => optional($claim->claim_date)?->toDateString(),
                    'claimant' => $claim->claimant ? [
                        'name' => (string) $claim->claimant->name,
                    ] : null,
                ])->values(),
            ],
        ]);
    }

    public function manageItems(DistributionRecord $distribution_record): Response
    {
        abort_unless(request()->user()->can('distribution.manage', $distribution_record), 403);

        $distribution_record->load('items');

        return Inertia::render('Inventory/Distribution/Items', [
            'record' => $distribution_record,
        ]);
    }

    public function edit(DistributionRecord $distribution_record): Response
    {
        abort_unless(request()->user()->can('distribution.manage', $distribution_record), 403);

        return Inertia::render('Inventory/Distribution/Edit', [
            'record' => $distribution_record,
        ]);
    }

    public function update(UpdateDistributionRecordRequest $request, DistributionRecord $distribution_record): RedirectResponse
    {
        abort_unless(request()->user()->can('distribution.manage', $distribution_record), 403);

        $this->distributionService->updateRecord($distribution_record, request()->user(), $request->validated());

        return Redirect::route('inventory.distribution-records.show', $distribution_record->distribution_record_id)
            ->with('success', 'Distribution record updated successfully.');
    }

    public function receive(DistributionRecord $distribution_record): RedirectResponse
    {
        abort_unless(request()->user()->can('distribution.receive', $distribution_record), 403);

        $reconciliations = $distribution_record->items()
            ->get()
            ->map(fn ($item): array => [
                'distribution_item_id' => (int) $item->distribution_item_id,
                'quantity_received' => (int) $item->quantity_released,
                'quantity_damaged' => 0,
                'quantity_missing' => 0,
                'receipt_condition' => 'GOOD',
                'receipt_notes' => null,
            ])
            ->all();

        $this->distributionService->reconcileOfficeReceipt($distribution_record, request()->user(), $reconciliations);

        $routeName = request()->user()->isOfficeUser()
            ? 'office.distribution-records.show'
            : 'inventory.distribution-records.show';

        return Redirect::route($routeName, $distribution_record->distribution_record_id)
            ->with('success', 'Distribution record marked as received.');
    }
}
