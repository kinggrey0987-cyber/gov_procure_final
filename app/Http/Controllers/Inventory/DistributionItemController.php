<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\DistributionItem\StoreDistributionItemRequest;
use App\Http\Requests\DistributionItem\UpdateDistributionItemRequest;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Services\Inventory\DistributionRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class DistributionItemController
{
    public function __construct(private readonly DistributionRecordService $distributionService)
    {
    }

    public function store(StoreDistributionItemRequest $request, DistributionRecord $distribution_record): RedirectResponse
    {
        abort_unless(auth()->user()->can('distribution.manage', $distribution_record), 403);

        $this->distributionService->addDistributionItem($distribution_record, auth()->user(), $request->validated());

        return Redirect::route('inventory.distribution-records.show', $distribution_record->distribution_record_id)
            ->with('success', 'Distribution item added successfully.');
    }

    public function update(
        UpdateDistributionItemRequest $request,
        DistributionRecord $distribution_record,
        DistributionItem $distribution_item,
    ): RedirectResponse {
        abort_unless(auth()->user()->can('distribution.manage', $distribution_record), 403);

        $this->distributionService->updateDistributionItem(
            record: $distribution_record,
            item: $distribution_item,
            actor: auth()->user(),
            payload: $request->validated(),
        );

        return Redirect::route('inventory.distribution-records.show', $distribution_record->distribution_record_id)
            ->with('success', 'Distribution item updated successfully.');
    }
}
