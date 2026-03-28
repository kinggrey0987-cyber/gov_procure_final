<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\DeliveryVerification\StoreDeliveryVerificationRequest;
use App\Models\Delivery;
use App\Services\Delivery\DeliveryVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryVerificationController
{
    public function __construct(private readonly DeliveryVerificationService $verificationService)
    {
    }

    public function create(Delivery $delivery): Response
    {
        abort_unless(auth()->user()->can('delivery-verification.create', $delivery), 403);

        $delivery->load('items.item', 'verifications');

        return Inertia::render('Inventory/DeliveryVerification/Create', [
            'delivery' => $delivery,
        ]);
    }

    public function store(StoreDeliveryVerificationRequest $request, Delivery $delivery): RedirectResponse
    {
        abort_unless(auth()->user()->can('delivery-verification.create', $delivery), 403);

        $this->verificationService->create(
            delivery: $delivery,
            actor: auth()->user(),
            payload: $request->validated(),
        );

        return Redirect::route('inventory.deliveries.show', $delivery->delivery_id)
            ->with('success', 'Delivery verified successfully.');
    }
}
