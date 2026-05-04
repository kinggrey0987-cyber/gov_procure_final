<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\DeliveryVerification\StoreDeliveryVerificationRequest;
use App\Models\Delivery;
use App\Services\Delivery\DeliveryService;
use App\Services\Delivery\DeliveryVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryVerificationController
{
    public function __construct(
        private readonly DeliveryVerificationService $verificationService,
        private readonly DeliveryService $deliveryService,
    ) {
    }

    public function create(Delivery $delivery): Response
    {
        abort_unless(auth()->user()->can('delivery-verification.create', $delivery), 403);

        $delivery = $this->deliveryService->ensureItemsFromPurchaseOrder($delivery);
        $delivery->load('purchaseOrder', 'supplier', 'items.purchaseOrderItem.item', 'verifications');

        $items = $delivery->items->map(function ($item): array {
            $expected = (float) $item->expected_quantity;
            $received = (float) $item->received_quantity;
            $damaged = (float) $item->damaged_quantity;
            $missing = (float) $item->missing_quantity;

            return [
                'delivery_item_id' => (int) $item->delivery_item_id,
                'item_name' => (string) ($item->purchaseOrderItem?->item?->item_name ?? $item->purchaseOrderItem?->item_name_snapshot ?? 'Unknown Item'),
                'unit' => (string) ($item->purchaseOrderItem?->unit ?? ''),
                'expected_quantity' => $expected,
                'received_quantity' => $received,
                'damaged_quantity' => $damaged,
                'missing_quantity' => $missing,
                'accepted_quantity' => max($received - $damaged, 0),
                'item_condition' => (string) ($item->item_condition ?? 'PENDING_REVIEW'),
                'has_discrepancy' => $damaged > 0 || $missing > 0 || ($received + $damaged + $missing) !== $expected,
                'remarks' => $item->remarks,
            ];
        })->values();

        $hasDiscrepancy = $items->contains(fn (array $item): bool => (bool) $item['has_discrepancy']);

        return Inertia::render('Inventory/DeliveryVerification/Create', [
            'delivery' => [
                'delivery_id' => (int) $delivery->delivery_id,
                'delivery_code' => (string) $delivery->delivery_code,
                'receipt_status' => (string) $delivery->receipt_status,
                'actual_delivery_date' => optional($delivery->actual_delivery_date)?->toDateString(),
                'expected_arrival_date' => optional($delivery->expected_arrival_date)?->toDateString(),
                'purchase_order' => $delivery->purchaseOrder ? [
                    'po_number' => (string) ($delivery->purchaseOrder->po_number ?? ''),
                ] : null,
                'supplier' => $delivery->supplier ? [
                    'supplier_name' => (string) ($delivery->supplier->supplier_name ?? ''),
                ] : null,
                'items' => $items,
                'verification_summary' => [
                    'item_count' => $items->count(),
                    'has_discrepancy' => $hasDiscrepancy,
                    'recommended_status' => $hasDiscrepancy ? 'REJECTED' : 'APPROVED',
                    'message' => $hasDiscrepancy
                        ? 'Discrepancies were found. Reject this delivery verification for BAC/procurement follow-up.'
                        : 'No quantity discrepancies found. This delivery is ready for approval.',
                ],
            ],
        ]);
    }

    public function store(StoreDeliveryVerificationRequest $request, Delivery $delivery): RedirectResponse
    {
        abort_unless(auth()->user()->can('delivery-verification.create', $delivery), 403);

        $this->verificationService->create(
            delivery: $delivery,
            actor: auth()->user(),
            payload: $request->validated(),
        );

        return Redirect::route('inventory.deliveries.show', $delivery->delivery_id)
            ->with('success', 'Delivery verified successfully.');
    }
}
