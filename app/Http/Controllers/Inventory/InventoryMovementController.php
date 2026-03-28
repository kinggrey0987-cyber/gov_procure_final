<?php

namespace App\Http\Controllers\Inventory;

use App\Models\InventoryMovement;
use Inertia\Inertia;
use Inertia\Response;

class InventoryMovementController
{
    public function index(): Response
    {
        abort_unless(auth()->user()->can('inventory-movement.view-any'), 403);

        $movements = InventoryMovement::query()
            ->with(['inventory.item', 'performer'])
            ->recent()
            ->paginate(25);

        return Inertia::render('Inventory/Movement/Index', [
            'movements' => $movements,
        ]);
    }

    public function show(InventoryMovement $movement): Response
    {
        abort_unless(auth()->user()->can('inventory-movement.view', $movement), 403);

        $movement->load(['inventory.item', 'performer']);

        return Inertia::render('Inventory/Movement/Show', [
            'movement' => $movement,
        ]);
    }
}
