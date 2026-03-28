<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Inventory\SyncInventoryFromDeliveryRequest;
use App\Http\Requests\Inventory\UpdateInventoryRequest;
use App\Models\Delivery;
use App\Models\Inventory;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index(): Response
    {
        abort_unless(auth()->user()->can('inventory.view-any'), 403);

        $inventories = Inventory::query()
            ->with('item')
            ->orderByDesc('last_updated')
            ->paginate(20);

        return Inertia::render('Inventory/Inventory/Index', [
            'inventories' => $inventories,
        ]);
    }

    public function show(Inventory $inventory): Response
    {
        abort_unless(auth()->user()->can('inventory.view', $inventory), 403);

        $inventory->load(['item', 'movements.performer']);

        return Inertia::render('Inventory/Inventory/Show', [
            'inventory' => $inventory,
        ]);
    }

    public function update(UpdateInventoryRequest $request, Inventory $inventory): RedirectResponse
    {
        abort_unless(auth()->user()->can('inventory.manage', $inventory), 403);

        $inventory->forceFill([
            'low_stock_threshold' => (float) $request->validated('low_stock_threshold'),
            'last_updated' => now(),
        ])->save();

        return Redirect::route('inventory.inventory.show', $inventory->inventory_id)
            ->with('success', 'Inventory threshold updated successfully.');
    }

    public function syncFromDelivery(SyncInventoryFromDeliveryRequest $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('inventory.manage'), 403);

        $delivery = Delivery::query()
            ->where('delivery_id', (int) $request->validated('delivery_id'))
            ->firstOrFail();

        $this->inventoryService->updateFromVerifiedDelivery($delivery, auth()->user());

        return Redirect::route('inventory.inventory.index')
            ->with('success', 'Inventory updated from verified delivery.');
    }
}
