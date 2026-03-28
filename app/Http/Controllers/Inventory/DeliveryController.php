<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Delivery\StoreDeliveryRequest;
use App\Http\Requests\Delivery\UpdateDeliveryRequest;
use App\Models\Delivery;
use App\Models\PurchaseOrder;
use App\Services\Delivery\DeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController
{
    public function __construct(private readonly DeliveryService $deliveryService)
    {
    }

    public function index(): Response
    {
        abort_unless(auth()->user()->can('delivery.view-any'), 403);

        $deliveries = Delivery::query()
            ->with(['purchaseOrder', 'supplier', 'receiver'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return Inertia::render('Inventory/Delivery/Index', [
            'deliveries' => $deliveries,
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()->can('delivery.create'), 403);

        $purchaseOrders = PurchaseOrder::query()
            ->where('status', 'PLACED')
            ->with('creator')
            ->get();

        return Inertia::render('Inventory/Delivery/Create', [
            'purchaseOrders' => $purchaseOrders,
        ]);
    }

    public function store(StoreDeliveryRequest $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('delivery.create'), 403);

        $delivery = $this->deliveryService->create(
            actor: auth()->user(),
            payload: $request->validated(),
        );

        return Redirect::route('inventory.deliveries.show', $delivery->delivery_id)
            ->with('success', 'Delivery created successfully.');
    }

    public function show(Delivery $delivery): Response
    {
        abort_unless(auth()->user()->can('delivery.view', $delivery), 403);

        $delivery->load(['purchaseOrder.creator', 'supplier', 'receiver', 'items.item', 'verifications.verifier', 'statusHistory']);

        return Inertia::render('Inventory/Delivery/Show', [
            'delivery' => $delivery,
        ]);
    }

    public function edit(Delivery $delivery): Response
    {
        abort_unless(auth()->user()->can('delivery.update', $delivery), 403);

        return Inertia::render('Inventory/Delivery/Edit', [
            'delivery' => $delivery,
        ]);
    }

    public function update(UpdateDeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        abort_unless(auth()->user()->can('delivery.update', $delivery), 403);

        $this->deliveryService->updateReceipt(
            delivery: $delivery,
            actor: auth()->user(),
            payload: $request->validated(),
        );

        return Redirect::route('inventory.deliveries.show', $delivery->delivery_id)
            ->with('success', 'Delivery updated successfully.');
    }
}
