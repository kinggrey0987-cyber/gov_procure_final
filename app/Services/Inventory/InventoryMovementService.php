<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\User;

class InventoryMovementService
{
    public function record(
        int $inventoryId,
        string $movementType,
        string $referenceType,
        int $referenceId,
        float $quantity,
        ?User $actor,
        ?string $remarks = null,
    ): InventoryMovement {
        return InventoryMovement::query()->create([
            'inventory_id' => $inventoryId,
            'movement_type' => strtoupper($movementType),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'quantity' => $quantity,
            'performed_by' => $actor?->id,
            'last_updated' => now(),
            'movement_date' => now()->toDateString(),
            'remarks' => $remarks,
        ]);
    }
}
