<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Inventory\StoreItemUnitRequest;
use App\Models\ItemUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ItemUnitController
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('inventory.catalog.view-any'), 403);

        return Inertia::render('Inventory/ItemUnits/Index', [
            'units' => ItemUnit::query()
                ->orderBy('status')
                ->orderBy('name')
                ->get(['item_unit_id', 'name', 'status', 'created_at'])
                ->map(fn (ItemUnit $unit): array => [
                    'item_unit_id' => (int) $unit->item_unit_id,
                    'name' => (string) $unit->name,
                    'status' => (string) $unit->status,
                    'created_at' => optional($unit->created_at)?->toIso8601String(),
                ])
                ->values(),
            'canManage' => $request->user()?->can('inventory.catalog.manage') ?? false,
        ]);
    }

    public function store(StoreItemUnitRequest $request): RedirectResponse
    {
        $name = strtolower(trim((string) $request->validated('name')));

        ItemUnit::query()->create([
            'name' => $name,
            'status' => 'active',
            'created_by' => $request->user()?->id,
        ]);

        return Redirect::back()->with('success', 'Unit added successfully.');
    }

    public function toggleStatus(Request $request, ItemUnit $item_unit): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.catalog.manage'), 403);

        $item_unit->forceFill([
            'status' => (string) $item_unit->status === 'active' ? 'inactive' : 'active',
        ])->save();

        return Redirect::back()->with('success', 'Unit status updated.');
    }
}

