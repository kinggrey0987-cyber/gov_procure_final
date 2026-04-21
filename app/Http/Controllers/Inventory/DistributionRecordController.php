<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\DistributionRecord\StoreDistributionRecordRequest;
use App\Http\Requests\DistributionRecord\UpdateDistributionRecordRequest;
use App\Models\DistributionRecord;
use App\Models\Delivery;
use App\Models\SupplyRequest;
use App\Services\Inventory\DistributionRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class DistributionRecordController
{
    public function __construct(private readonly DistributionRecordService $distributionService)
    {
    }

    public function index(): Response
    {
        abort_unless(auth()->user()->can('distribution.view-any'), 403);

        $records = DistributionRecord::query()
            ->with(['supplyRequest', 'delivery', 'office', 'preparer', 'receiver'])
            ->withCount(['items', 'claims'])
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Inventory/Distribution/Index', [
            'records' => $records,
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()->can('distribution.manage'), 403);

        $eligibleRows = Delivery::query()
            ->verified()
            ->whereHas('purchaseOrder', fn ($query) => $query->whereNotNull('supply_request_id'))
            ->whereDoesntHave('distributionRecords')
            ->with(['purchaseOrder.supplyRequest.office'])
            ->latest('delivery_id')
            ->get()
            ->map(function (Delivery $delivery): ?array {
                $supplyRequest = $delivery->purchaseOrder?->supplyRequest;

                if (!$supplyRequest instanceof SupplyRequest) {
                    return null;
                }

                return [
                    'supply_request_id' => (int) $supplyRequest->supply_request_id,
                    'request_code' => (string) $supplyRequest->request_code,
                    'office_id' => (int) $supplyRequest->office_id,
                    'office_name' => (string) ($supplyRequest->office?->name ?? 'Unknown Office'),
                    'delivery_id' => (int) $delivery->delivery_id,
                    'delivery_code' => (string) $delivery->delivery_code,
                    'receipt_status' => (string) $delivery->receipt_status,
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('Inventory/Distribution/Create', [
            'eligibleRows' => $eligibleRows,
        ]);
    }

    public function store(StoreDistributionRecordRequest $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('distribution.manage'), 403);

        $record = $this->distributionService->createRecord(auth()->user(), $request->validated());

        return Redirect::route('inventory.distribution-records.show', $record->distribution_record_id)
            ->with('success', 'Distribution record created successfully.');
    }

    public function show(DistributionRecord $distribution_record): Response
    {
        abort_unless(auth()->user()->can('distribution.view', $distribution_record), 403);

        $distribution_record->load([
            'items.item.category',
            'claims.claimant',
            'supplyRequest.office',
            'delivery',
            'office',
            'preparer',
            'receiver',
        ]);

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
        abort_unless(auth()->user()->can('distribution.manage', $distribution_record), 403);

        $distribution_record->load('items');

        return Inertia::render('Inventory/Distribution/Items', [
            'record' => $distribution_record,
        ]);
    }

    public function edit(DistributionRecord $distribution_record): Response
    {
        abort_unless(auth()->user()->can('distribution.manage', $distribution_record), 403);

        return Inertia::render('Inventory/Distribution/Edit', [
            'record' => $distribution_record,
        ]);
    }

    public function update(UpdateDistributionRecordRequest $request, DistributionRecord $distribution_record): RedirectResponse
    {
        abort_unless(auth()->user()->can('distribution.manage', $distribution_record), 403);

        $this->distributionService->updateRecord($distribution_record, auth()->user(), $request->validated());

        return Redirect::route('inventory.distribution-records.show', $distribution_record->distribution_record_id)
            ->with('success', 'Distribution record updated successfully.');
    }

    public function receive(DistributionRecord $distribution_record): RedirectResponse
    {
        abort_unless(auth()->user()->can('distribution.receive', $distribution_record), 403);

        $this->distributionService->markReceivedByOffice($distribution_record, auth()->user());

        $routeName = auth()->user()->isOfficeUser()
            ? 'office.distribution-records.show'
            : 'inventory.distribution-records.show';

        return Redirect::route($routeName, $distribution_record->distribution_record_id)
            ->with('success', 'Distribution record marked as received.');
    }
}
