<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Inventory\StoreItemCategoryRequest;
use App\Http\Requests\Inventory\UpdateItemCategoryRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ItemCategoryController
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user?->can('inventory.catalog.view-any'), 403);

        $query = ItemCategory::query()->withCount('items')->latest('item_category_id');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where('category_name', 'like', '%'.$search.'%');
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        return Inertia::render('Inventory/ItemCategories/Index', [
            'categories' => $query->paginate(5)->withQueryString(),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'canManage' => $user?->can('inventory.catalog.manage') ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('inventory.catalog.manage'), 403);

        return Inertia::render('Inventory/ItemCategories/Create');
    }

    public function store(StoreItemCategoryRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.catalog.manage'), 403);

        $category = ItemCategory::query()->create($request->validated());

        return Redirect::route('inventory.item-categories.show', $category->item_category_id)
            ->with('success', 'Item category created successfully.');
    }

    public function show(Request $request, ItemCategory $item_category): Response
    {
        $user = $request->user();

        abort_unless($user?->can('inventory.catalog.view-any'), 403);

        $item_category->loadCount('items');

        return Inertia::render('Inventory/ItemCategories/Show', [
            'category' => [
                'item_category_id' => (int) $item_category->item_category_id,
                'category_name' => (string) $item_category->category_name,
                'standard_delivery_days' => (int) $item_category->standard_delivery_days,
                'description' => $item_category->description,
                'status' => (string) $item_category->status,
                'items_count' => (int) $item_category->items_count,
                'created_at' => optional($item_category->created_at)?->toIso8601String(),
                'updated_at' => optional($item_category->updated_at)?->toIso8601String(),
            ],
            'canManage' => $user?->can('inventory.catalog.manage') ?? false,
        ]);
    }

    public function edit(Request $request, ItemCategory $item_category): Response
    {
        abort_unless($request->user()?->can('inventory.catalog.manage'), 403);

        return Inertia::render('Inventory/ItemCategories/Edit', [
            'category' => [
                'item_category_id' => (int) $item_category->item_category_id,
                'category_name' => (string) $item_category->category_name,
                'standard_delivery_days' => (int) $item_category->standard_delivery_days,
                'description' => $item_category->description,
                'status' => (string) $item_category->status,
            ],
        ]);
    }

    public function update(UpdateItemCategoryRequest $request, ItemCategory $item_category): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.catalog.manage'), 403);

        $item_category->fill($request->validated())->save();

        return Redirect::route('inventory.item-categories.show', $item_category->item_category_id)
            ->with('success', 'Item category updated successfully.');
    }

    public function toggleStatus(Request $request, ItemCategory $item_category): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.catalog.manage'), 403);

        DB::transaction(function () use ($item_category): void {
            $nextStatus = (string) $item_category->status === 'active' ? 'inactive' : 'active';

            $item_category->forceFill([
                'status' => $nextStatus,
            ])->save();

            if ($nextStatus === 'inactive') {
                Item::query()
                    ->where('item_category_id', (int) $item_category->item_category_id)
                    ->update(['status' => 'inactive']);
            }
        });

        return Redirect::back()->with('success', 'Item category status updated.');
    }
}
