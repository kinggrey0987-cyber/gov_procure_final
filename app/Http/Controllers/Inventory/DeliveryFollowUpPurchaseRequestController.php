<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Delivery\CreateDeliveryFollowUpPurchaseRequest;
use App\Models\Delivery;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class DeliveryFollowUpPurchaseRequestController
{
    public function __construct(private readonly PurchaseRequestService $purchaseRequestService)
    {
    }

    public function store(CreateDeliveryFollowUpPurchaseRequest $request, Delivery $delivery): RedirectResponse
    {
        abort_unless($request->user()->can('delivery.follow-up-pr.create', $delivery), 403);

        $purchaseRequest = $this->purchaseRequestService->createFromPartialDelivery(
            delivery: $delivery,
            actor: $request->user(),
            remarks: $request->validated('remarks'),
        );

        return Redirect::route('inventory.deliveries.show', $delivery->delivery_id)
            ->with('success', sprintf('Follow-up purchase request PR-%06d created from delivery discrepancy and queued for Procurement Unit review.', (int) $purchaseRequest->purchase_request_id));
    }
}
