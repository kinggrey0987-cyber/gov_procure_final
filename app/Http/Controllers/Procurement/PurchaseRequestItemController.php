<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StorePurchaseRequestItemRequest;
use App\Http\Requests\Procurement\UpdatePurchaseRequestItemRequest;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestItemController extends Controller
{
    public function __construct(private readonly PurchaseRequestService $service)
    {
    }

    public function index(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-request.view', $purchaseRequest), 403);

        return Inertia::render('Procurement/PurchaseRequestItems/Index', [
            'purchaseRequest' => $purchaseRequest,
            'items' => $purchaseRequest->items()->latest('purchase_request_item_id')->paginate(20),
        ]);
    }

    public function store(StorePurchaseRequestItemRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.purchase-request.update', $purchaseRequest), 403);

        $this->service->addItem($purchaseRequest, $request->user(), $request->validated());

        return redirect()->route('procurement.purchase-requests.items.index', $purchaseRequest->purchase_request_id)
            ->with('success', 'Purchase request item added successfully.');
    }

    public function update(
        UpdatePurchaseRequestItemRequest $request,
        PurchaseRequest $purchaseRequest,
        PurchaseRequestItem $purchaseRequestItem
    ): RedirectResponse {
        abort_unless($request->user()?->can('procurement.purchase-request.update', $purchaseRequest), 403);

        $this->service->updateItem($purchaseRequest, $purchaseRequestItem, $request->user(), $request->validated());

        return redirect()->route('procurement.purchase-requests.items.index', $purchaseRequest->purchase_request_id)
            ->with('success', 'Purchase request item updated successfully.');
    }
}
