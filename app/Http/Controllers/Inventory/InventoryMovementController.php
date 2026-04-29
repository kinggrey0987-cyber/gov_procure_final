<?php

namespace App\Http\Controllers\Inventory;

use App\Models\Delivery;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SupplyRequest;
use Inertia\Inertia;
use Inertia\Response;

class InventoryMovementController
{
    public function index(): Response
    {
        abort_unless(request()->user()?->can('inventory-movement.view-any'), 403);

        $movements = InventoryMovement::query()
            ->with(['inventory.item', 'performer'])
            ->recent()
            ->paginate(5)
            ->withQueryString();

        $movements->setCollection(
            $movements->getCollection()->map(function (InventoryMovement $movement): array {
                return [
                    'movement_id' => (int) $movement->movement_id,
                    'movement_type' => (string) $movement->movement_type,
                    'inventory_id' => (int) $movement->inventory_id,
                    'item_name' => (string) ($movement->inventory?->item?->item_name ?? 'Unknown Item'),
                    'quantity' => (float) $movement->quantity,
                    'movement_date' => optional($movement->movement_date)?->toDateString(),
                    'reference_label' => $this->resolveReferenceLabel($movement),
                ];
            })
        );

        return Inertia::render('Inventory/Movement/Index', [
            'movements' => $movements,
        ]);
    }

    public function show(InventoryMovement $movement): Response
    {
        abort_unless(request()->user()?->can('inventory-movement.view', $movement), 403);

        $movement->load(['inventory.item', 'performer']);

        return Inertia::render('Inventory/Movement/Show', [
            'movement' => [
                'movement_id' => (int) $movement->movement_id,
                'movement_type' => (string) $movement->movement_type,
                'quantity' => (float) $movement->quantity,
                'movement_date' => optional($movement->movement_date)?->toDateString(),
                'remarks' => $movement->remarks,
                'performed_by' => $movement->performer ? [
                    'name' => (string) $movement->performer->name,
                ] : null,
                'inventory' => $movement->inventory ? [
                    'inventory_id' => (int) $movement->inventory->inventory_id,
                    'item_name' => (string) ($movement->inventory->item?->item_name ?? 'Unknown Item'),
                ] : null,
                'reference_label' => $this->resolveReferenceLabel($movement),
            ],
        ]);
    }

    private function resolveReferenceLabel(InventoryMovement $movement): string
    {
        $type = (string) $movement->reference_type;
        $referenceId = (int) $movement->reference_id;

        if ($referenceId <= 0) {
            return 'Manual Adjustment';
        }

        return match ($type) {
            DistributionRecord::class => $this->distributionLabel($referenceId),
            Delivery::class => $this->deliveryLabel($referenceId),
            PurchaseOrder::class => $this->purchaseOrderLabel($referenceId),
            PurchaseRequest::class => $this->purchaseRequestLabel($referenceId),
            SupplyRequest::class => $this->supplyRequestLabel($referenceId),
            Inventory::class => sprintf('Inventory Update #%d', $referenceId),
            default => sprintf('%s #%d', class_basename($type), $referenceId),
        };
    }

    private function distributionLabel(int $referenceId): string
    {
        $code = DistributionRecord::query()
            ->where('distribution_record_id', $referenceId)
            ->value('distribution_code');

        return $code
            ? sprintf('Distribution Record %s', $code)
            : sprintf('Distribution Record #%d', $referenceId);
    }

    private function deliveryLabel(int $referenceId): string
    {
        $code = Delivery::query()
            ->where('delivery_id', $referenceId)
            ->value('delivery_code');

        return $code
            ? sprintf('Delivery Record %s', $code)
            : sprintf('Delivery Record #%d', $referenceId);
    }

    private function purchaseOrderLabel(int $referenceId): string
    {
        $code = PurchaseOrder::query()
            ->where('purchase_order_id', $referenceId)
            ->value('po_number');

        return $code
            ? sprintf('Purchase Order %s', $code)
            : sprintf('Purchase Order #%d', $referenceId);
    }

    private function purchaseRequestLabel(int $referenceId): string
    {
        $record = PurchaseRequest::query()
            ->where('purchase_request_id', $referenceId)
            ->first(['purchase_request_id']);

        return $record instanceof PurchaseRequest
            ? sprintf('Purchase Request PR-%06d', (int) $record->purchase_request_id)
            : sprintf('Purchase Request #%d', $referenceId);
    }

    private function supplyRequestLabel(int $referenceId): string
    {
        $code = SupplyRequest::query()
            ->where('supply_request_id', $referenceId)
            ->value('request_code');

        return $code
            ? sprintf('Supply Request %s', $code)
            : sprintf('Supply Request #%d', $referenceId);
    }
}
