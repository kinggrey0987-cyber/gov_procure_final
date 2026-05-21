<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Inventory\StoreItemRequest;
use App\Http\Requests\Inventory\UpdateItemRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ItemCatalogController
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user?->can('inventory.catalog.view-any'), 403);

        $query = Item::query()
            ->with('category:item_category_id,category_name')
            ->latest('item_id');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where('item_name', 'like', '%'.$search.'%');
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $categoryId = (int) $request->query('category_id', 0);
        if ($categoryId > 0) {
            $query->where('item_category_id', $categoryId);
        }

        $categories = ItemCategory::query()
            ->orderBy('category_name', 'asc')
            ->get(['item_category_id', 'category_name'])
            ->map(fn (ItemCategory $category): array => [
                'item_category_id' => (int) $category->item_category_id,
                'category_name' => (string) $category->category_name,
            ])
            ->values();

        return Inertia::render('Inventory/Items/Index', [
            'items' => $query->paginate(5)->withQueryString(),
            'categories' => $categories,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'category_id' => $categoryId > 0 ? $categoryId : null,
            ],
            'canManage' => $user?->can('inventory.catalog.manage') ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('inventory.catalog.manage'), 403);

        return Inertia::render('Inventory/Items/Create', [
            'categories' => $this->categoryOptions(),
            'units' => $this->unitOptions(),
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.catalog.manage'), 403);

        $item = Item::query()->create($request->validated());

        return Redirect::route('inventory.items.show', $item->item_id)
            ->with('success', 'Item created successfully.');
    }

    public function show(Request $request, Item $item): Response
    {
        $user = $request->user();

        abort_unless($user?->can('inventory.catalog.view-any'), 403);

        $item->load('category:item_category_id,category_name,standard_delivery_days');

        return Inertia::render('Inventory/Items/Show', [
            'item' => [
                'item_id' => (int) $item->item_id,
                'item_name' => (string) $item->item_name,
                'item_category_id' => (int) $item->item_category_id,
                'category' => $item->category ? [
                    'item_category_id' => (int) $item->category->item_category_id,
                    'category_name' => (string) $item->category->category_name,
                    'standard_delivery_days' => (int) ($item->category->standard_delivery_days ?? 0),
                ] : null,
                'description' => $item->description,
                'default_unit' => $item->default_unit,
                'status' => (string) $item->status,
                'created_at' => optional($item->created_at)?->toIso8601String(),
                'updated_at' => optional($item->updated_at)?->toIso8601String(),
            ],
            'canManage' => $user?->can('inventory.catalog.manage') ?? false,
        ]);
    }

    public function edit(Request $request, Item $item): Response
    {
        abort_unless($request->user()?->can('inventory.catalog.manage'), 403);

        return Inertia::render('Inventory/Items/Edit', [
            'item' => [
                'item_id' => (int) $item->item_id,
                'item_name' => (string) $item->item_name,
                'item_category_id' => (int) $item->item_category_id,
                'description' => $item->description,
                'default_unit' => $item->default_unit,
                'status' => (string) $item->status,
            ],
            'categories' => $this->categoryOptions(),
            'units' => $this->unitOptions((string) $item->default_unit),
        ]);
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.catalog.manage'), 403);

        $item->fill($request->validated())->save();

        return Redirect::route('inventory.items.show', $item->item_id)
            ->with('success', 'Item updated successfully.');
    }

    public function toggleStatus(Request $request, Item $item): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.catalog.manage'), 403);

        $nextStatus = (string) $item->status === 'active' ? 'inactive' : 'active';

        if ($nextStatus === 'active') {
            $item->loadMissing('category');

            if ((string) ($item->category?->status ?? '') !== 'active') {
                return Redirect::back()->with('error', 'Cannot activate this item because its category is deactivated. Reactivate the category first.');
            }
        }

        $item->forceFill([
            'status' => $nextStatus,
        ])->save();

        return Redirect::back()->with('success', 'Item status updated.');
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function categoryOptions(): array
    {
        return ItemCategory::query()
            ->orderBy('category_name', 'asc')
            ->get(['item_category_id', 'category_name'])
            ->map(fn (ItemCategory $category): array => [
                'item_category_id' => (int) $category->item_category_id,
                'category_name' => (string) $category->category_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function unitOptions(?string $includeUnit = null): array
    {
        return ItemUnit::query()
            ->where(function ($query) use ($includeUnit): void {
                $query->where('status', 'active');

                if ($includeUnit !== null && $includeUnit !== '') {
                    $query->orWhere('name', $includeUnit);
                }
            })
            ->orderBy('name', 'asc')
            ->get(['item_unit_id', 'name', 'status'])
            ->map(fn (ItemUnit $unit): array => [
                'item_unit_id' => (int) $unit->item_unit_id,
                'name' => (string) $unit->name,
                'status' => (string) $unit->status,
            ])
            ->values()
            ->all();
    }
}
