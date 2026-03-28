<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\DeliveryItem\StoreDeliveryItemRequest;
use App\Http\Requests\DeliveryItem\UpdateDeliveryItemRequest;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Services\Delivery\DeliveryItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class DeliveryItemController
{
    public function __construct(private readonly DeliveryItemService $deliveryItemService)
    {
    }

    public function store(StoreDeliveryItemRequest $request, Delivery $delivery): RedirectResponse
    {
        abort_unless(auth()->user()->can('delivery.update', $delivery), 403);

        $this->deliveryItemService->addItem(
            delivery: $delivery,
            actor: auth()->user(),
            payload: $request->validated(),
        );

        return Redirect::route('inventory.deliveries.show', $delivery->delivery_id)
            ->with('success', 'Delivery item added successfully.');
    }

    public function update(
        UpdateDeliveryItemRequest $request,
        Delivery $delivery,
        DeliveryItem $item
    ): RedirectResponse {
        abort_unless(auth()->user()->can('delivery.update', $delivery), 403);

        $this->deliveryItemService->updateItem(
            delivery: $delivery,
            item: $item,
            actor: auth()->user(),
            payload: $request->validated(),
        );

        return Redirect::route('inventory.deliveries.show', $delivery->delivery_id)
            ->with('success', 'Delivery item updated successfully.');
    }
}
