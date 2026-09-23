<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Inventory\SyncInventoryFromDeliveryRequest;
use App\Http\Requests\Inventory\UpdateInventoryRequest;
use App\Models\Delivery;
use App\Models\Inventory;
use App\Models\ItemCategory;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless(auth()->user()->can('inventory.view-any'), 403);

        $query = Inventory::query()->with(['item.category', 'item.specifications']);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->whereHas('item', function ($builder) use ($search): void {
                $builder
                    ->whereLikeInsensitive('item_name', $search)
                    ->orWhereLikeInsensitive('description', $search);
            });
        }

        $categoryId = (int) $request->query('category_id', 0);
        if ($categoryId > 0) {
            $query->whereHas('item', fn ($builder) => $builder->where('item_category_id', $categoryId));
        }

        $stockStatus = trim((string) $request->query('stock_status', ''));
        if ($stockStatus === 'HEALTHY') {
            $query->whereColumn('available_stock', '>', 'low_stock_threshold');
        } elseif ($stockStatus === 'LOW') {
            $query
                ->whereColumn('available_stock', '<', 'low_stock_threshold')
                ->where('available_stock', '>', 0);
        } elseif ($stockStatus === 'CRITICAL') {
            $query->where('available_stock', '<=', 0);
        } elseif ($stockStatus === 'AT_REORDER_POINT') {
            $query->whereColumn('available_stock', '=', 'low_stock_threshold');
        }

        $inventories = $query
            ->orderByDesc('last_updated')
            ->paginate(5)
            ->withQueryString();

        $inventories->getCollection()->transform(fn (Inventory $inventory): Inventory => $this->withComputedReservedQuantity($inventory));

        $categories = ItemCategory::query()
            ->orderBy('category_name')
            ->get(['item_category_id', 'category_name'])
            ->map(fn (ItemCategory $category): array => [
                'item_category_id' => (int) $category->item_category_id,
                'category_name' => (string) $category->category_name,
            ])
            ->values();

        return Inertia::render('Inventory/Inventory/Index', [
            'inventories' => $inventories,
            'categories' => $categories,
            'filters' => [
                'q' => $search,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'stock_status' => $stockStatus,
            ],
        ]);
    }

    public function show(Inventory $inventory): Response
    {
        abort_unless(auth()->user()->can('inventory.view', $inventory), 403);

        $inventory->load(['item.category', 'item.specifications']);
        $inventory = $this->withComputedReservedQuantity($inventory);

        $recentMovements = $inventory->movements()
            ->with('performer')
            ->orderByDesc('movement_date')
            ->orderByDesc('movement_id')
            ->limit(10)
            ->get();

        return Inertia::render('Inventory/Inventory/Show', [
            'inventory' => $inventory,
            'recentMovements' => $recentMovements,
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
            ->with('success', 'Inventory updated from accepted delivery quantity.');
    }

    private function withComputedReservedQuantity(Inventory $inventory): Inventory
    {
        $computedReserved = max((float) $inventory->stock_on_hand - (float) $inventory->available_stock, 0);

        $inventory->setAttribute('reserved_quantity', number_format($computedReserved, 2, '.', ''));

        return $inventory;
    }
}
