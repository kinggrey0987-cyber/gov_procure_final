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

        $distribution_record->load(['items.item', 'claims.claimant', 'supplyRequest', 'delivery', 'office', 'preparer', 'receiver']);

        return Inertia::render('Inventory/Distribution/Show', [
            'record' => $distribution_record,
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
