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

        try {
            $purchaseRequest = $this->purchaseRequestService->createFromPartialDelivery(
                delivery: $delivery,
                actor: $request->user(),
                remarks: $request->validated('remarks'),
            );

            return Redirect::route('inventory.deliveries.show', $delivery->delivery_id)
                ->with('success', sprintf('Follow-up purchase request %s created from delivery discrepancy and queued for Procurement Unit review.', (string) $purchaseRequest->pr_number));
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first() ?? 'Unable to create follow-up purchase request.';

            return Redirect::route('inventory.deliveries.show', $delivery->delivery_id)
                ->withErrors($e->errors())
                ->with('error', $firstError);
        } catch (\Throwable $e) {
            return Redirect::route('inventory.deliveries.show', $delivery->delivery_id)
                ->with('error', 'Error creating follow-up purchase request: '.$e->getMessage());
        }
    }
}
