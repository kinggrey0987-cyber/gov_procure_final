<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Requests\Office\StoreSupplyRequestItemRequest;
use App\Http\Requests\Office\UpdateSupplyRequestItemRequest;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Services\Office\SupplyRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplyRequestItemController extends Controller
{
    public function __construct(private readonly SupplyRequestService $service)
    {
    }

    public function index(Request $request, SupplyRequest $supplyRequest): Response
    {
        abort_unless($request->user()?->can('office.supply-request.view', $supplyRequest), 403);

        return Inertia::render('Office/SupplyRequestItems/Index', [
            'supplyRequest' => $supplyRequest,
            'items' => $supplyRequest->items()->latest('supply_request_item_id')->paginate(5)->withQueryString(),
        ]);
    }

    public function store(StoreSupplyRequestItemRequest $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        $this->service->addItem($supplyRequest, $request->user(), $request->validated());

        return redirect()->route('office.supply-requests.items.index', $supplyRequest->supply_request_id)
            ->with('success', 'Supply request item added.');
    }

    public function update(
        UpdateSupplyRequestItemRequest $request,
        SupplyRequest $supplyRequest,
        SupplyRequestItem $supplyRequestItem
    ): RedirectResponse {
        $this->service->updateItem($supplyRequest, $supplyRequestItem, $request->user(), $request->validated());

        return redirect()->route('office.supply-requests.items.index', $supplyRequest->supply_request_id)
            ->with('success', 'Supply request item updated.');
    }

    public function destroy(Request $request, SupplyRequest $supplyRequest, SupplyRequestItem $supplyRequestItem): RedirectResponse
    {
        abort_unless($request->user()?->can('office.supply-request.update', $supplyRequest), 403);

        $this->service->removeItem($supplyRequest, $supplyRequestItem, $request->user());

        return redirect()->route('office.supply-requests.show', $supplyRequest->supply_request_id)
            ->with('success', 'Supply request item removed.');
    }
}
