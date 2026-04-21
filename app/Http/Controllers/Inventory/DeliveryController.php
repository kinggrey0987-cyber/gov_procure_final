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

        $delivery->load(['purchaseOrder', 'supplier', 'receiver', 'items.item', 'verifications.verifier']);

        return Inertia::render('Inventory/Delivery/Show', [
            'delivery' => [
                'delivery_id' => (int) $delivery->delivery_id,
                'delivery_code' => (string) $delivery->delivery_code,
                'receipt_status' => (string) $delivery->receipt_status,
                'created_at' => optional($delivery->created_at)?->toIso8601String(),
                'purchase_order' => $delivery->purchaseOrder ? [
                    'purchase_order_id' => (int) $delivery->purchaseOrder->purchase_order_id,
                    'po_number' => (string) ($delivery->purchaseOrder->po_number ?? ''),
                    'status' => (string) ($delivery->purchaseOrder->status ?? ''),
                ] : null,
                'supplier' => $delivery->supplier ? [
                    'supplier_name' => (string) ($delivery->supplier->supplier_name ?? ''),
                    'contact_person' => $delivery->supplier->contact_person,
                ] : null,
                'receiver' => $delivery->receiver ? [
                    'name' => (string) $delivery->receiver->name,
                ] : null,
                'items' => $delivery->items->map(fn ($item): array => [
                    'delivery_item_id' => (int) $item->delivery_item_id,
                    'expected_quantity' => (float) $item->expected_quantity,
                    'received_quantity' => (float) $item->received_quantity,
                    'damaged_quantity' => (float) $item->damaged_quantity,
                    'missing_quantity' => (float) $item->missing_quantity,
                    'item_condition' => $item->item_condition,
                    'item' => $item->item ? [
                        'item_name' => (string) $item->item->item_name,
                    ] : null,
                ])->values(),
                'verifications' => $delivery->verifications->map(fn ($verification): array => [
                    'delivery_verification_id' => (int) $verification->delivery_verification_id,
                    'verification_status' => (string) $verification->verification_status,
                    'verification_date' => optional($verification->verification_date)?->toDateString(),
                    'discrepancy_notes' => $verification->discrepancy_notes,
                    'verifier' => $verification->verifier ? [
                        'name' => (string) $verification->verifier->name,
                    ] : null,
                ])->values(),
            ],
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
