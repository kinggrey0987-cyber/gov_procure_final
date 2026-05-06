<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Delivery\StoreDeliveryRequest;
use App\Http\Requests\Delivery\UpdateDeliveryRequest;
use App\Models\Delivery;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Services\Delivery\DeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController
{
    public function __construct(private readonly DeliveryService $deliveryService)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless(request()->user()->can('delivery.view-any'), 403);

        $status = strtoupper(trim((string) $request->input('status', '')));
        $year = trim((string) $request->input('year', ''));
        $month = trim((string) $request->input('month', ''));
        $search = trim((string) $request->input('search', ''));

        $yearOptions = Delivery::query()
            ->where('created_at', '!=', null)
            ->latest('created_at')
            ->pluck('created_at')
            ->map(fn ($value): int => (int) $value->year)
            ->unique()
            ->values();

        $deliveries = Delivery::query()
            ->with(['purchaseOrder', 'supplier', 'receiver'])
            ->when($status !== '', fn ($query) => $query->where('receipt_status', $status))
            ->when(preg_match('/^\d{4}$/', $year) === 1, fn ($query) => $query->whereYear('created_at', (int) $year))
            ->when(preg_match('/^\d{1,2}$/', $month) === 1, fn ($query) => $query->whereMonth('created_at', (int) $month))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('delivery_code', 'like', '%'.$search.'%')
                        ->orWhereHas('purchaseOrder', fn ($purchaseOrderQuery) => $purchaseOrderQuery->where('po_number', 'like', '%'.$search.'%'))
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('supplier_name', 'like', '%'.$search.'%'));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(5)
            ->withQueryString();

        return Inertia::render('Inventory/Delivery/Index', [
            'deliveries' => $deliveries,
            'filters' => [
                'status' => $status,
                'year' => $year,
                'month' => $month,
                'search' => $search,
            ],
            'filter_options' => [
                'years' => $yearOptions,
            ],
        ]);
    }

    public function create(): Response
    {
        abort_unless(request()->user()->can('delivery.create'), 403);

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
        abort_unless(request()->user()->can('delivery.create'), 403);

        $delivery = $this->deliveryService->create(
            actor: request()->user(),
            payload: $request->validated(),
        );

        return Redirect::route('inventory.deliveries.show', $delivery->delivery_id)
            ->with('success', 'Delivery created successfully.');
    }

    public function show(Delivery $delivery): Response
    {
        abort_unless(request()->user()->can('delivery.view', $delivery), 403);

        $delivery->load(['purchaseOrder', 'supplier', 'receiver', 'items.purchaseOrderItem.item', 'verifications.verifier']);

        $deliveryMovementMap = InventoryMovement::query()
            ->where('movement_type', 'DELIVERY_RECEIPT')
            ->where('reference_type', Delivery::class)
            ->where('reference_id', (int) $delivery->delivery_id)
            ->get()
            ->keyBy('inventory_id');

        $items = collect();
        if ($delivery->relationLoaded('items') && $delivery->items instanceof \Illuminate\Database\Eloquent\Collection) {
            $items = $delivery->items->map(fn ($item): array => [
                'accepted_quantity' => max((float) $item->received_quantity - (float) $item->damaged_quantity, 0),
                'delivery_item_id' => (int) $item->delivery_item_id,
                'expected_quantity' => (float) $item->expected_quantity,
                'received_quantity' => (float) $item->received_quantity,
                'damaged_quantity' => (float) $item->damaged_quantity,
                'missing_quantity' => (float) $item->missing_quantity,
                'item_condition' => $item->item_condition,
                'inventory_synced_quantity' => (float) ($deliveryMovementMap->first()?->quantity ?? 0),
                'item' => ($item->relationLoaded('purchaseOrderItem') && $item->purchaseOrderItem && $item->purchaseOrderItem->relationLoaded('item') && $item->purchaseOrderItem->item) ? [
                    'item_id' => (int) $item->purchaseOrderItem->item->item_id,
                    'item_name' => (string) $item->purchaseOrderItem->item->item_name,
                ] : null,
            ])->values();
        }

        $verifications = collect();
        if ($delivery->relationLoaded('verifications') && $delivery->verifications instanceof \Illuminate\Database\Eloquent\Collection) {
            $verifications = $delivery->verifications->map(fn ($verification): array => [
                'delivery_verification_id' => (int) $verification->delivery_verification_id,
                'verification_status' => (string) $verification->verification_status,
                'verification_date' => optional($verification->verification_date)?->toDateString(),
                'discrepancy_notes' => $verification->discrepancy_notes,
                'verifier' => ($verification->relationLoaded('verifier') && $verification->verifier) ? [
                    'name' => (string) $verification->verifier->name,
                ] : null,
            ])->values();
        }

        $totalMissingQuantity = round((float) $items->sum('missing_quantity'), 2);
        $totalDamagedQuantity = round((float) $items->sum('damaged_quantity'), 2);
        $followUpPurchaseRequest = PurchaseRequest::query()
            ->with(['purchaseOrders', 'statusHistory'])
            ->where('source_delivery_id', (int) $delivery->delivery_id)
            ->latest('purchase_request_id')
            ->first();

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
                'permissions' => [
                    'can_create_follow_up_purchase_request' => request()->user()->can('delivery.follow-up-pr.create', $delivery),
                ],
                'discrepancy_summary' => [
                    'has_discrepancy' => ($totalMissingQuantity + $totalDamagedQuantity) > 0,
                    'total_accepted_quantity' => round((float) $items->sum('accepted_quantity'), 2),
                    'total_inventory_synced_quantity' => round((float) $items->sum('inventory_synced_quantity'), 2),
                    'total_missing_quantity' => $totalMissingQuantity,
                    'total_damaged_quantity' => $totalDamagedQuantity,
                    'affected_item_count' => $items->filter(fn (array $item): bool => ((float) $item['missing_quantity'] + (float) $item['damaged_quantity']) > 0)->count(),
                ],
                'follow_up_purchase_request' => $followUpPurchaseRequest ? [
                    'purchase_request_id' => (int) $followUpPurchaseRequest->purchase_request_id,
                    'status' => (string) $followUpPurchaseRequest->status,
                    'source_type' => (string) ($followUpPurchaseRequest->source_type ?? ''),
                    'purchase_order_count' => (int) $followUpPurchaseRequest->purchaseOrders->count(),
                    'purchase_orders' => $followUpPurchaseRequest->purchaseOrders->map(fn ($purchaseOrder): array => [
                        'purchase_order_id' => (int) $purchaseOrder->purchase_order_id,
                        'po_number' => (string) ($purchaseOrder->po_number ?? ''),
                        'status' => (string) ($purchaseOrder->status ?? ''),
                    ])->values(),
                    'status_history' => $followUpPurchaseRequest->statusHistory
                        ->sortBy('changed_at')
                        ->values()
                        ->map(fn ($history): array => [
                            'old_status' => $history->old_status,
                            'new_status' => $history->new_status,
                            'remarks' => $history->remarks,
                            'changed_at' => optional($history->changed_at)?->toIso8601String(),
                        ])->values(),
                ] : null,
                'items' => $items->values(),
                'verifications' => $verifications->values(),
            ],
        ]);
    }

    public function receive(Delivery $delivery): Response
    {
        abort_unless(request()->user()->can('delivery.update', $delivery), 403);

        if ((string) $delivery->receipt_status !== 'PENDING') {
            abort(403, 'Only PENDING deliveries can be received.');
        }

        $delivery = $this->deliveryService->ensureItemsFromPurchaseOrder($delivery);
        $delivery->load(['purchaseOrder', 'supplier', 'items.purchaseOrderItem.item']);

        $items = collect();
        if ($delivery->relationLoaded('items') && $delivery->items instanceof \Illuminate\Database\Eloquent\Collection) {
            $items = $delivery->items->map(fn ($item): array => [
                'delivery_item_id' => (int) $item->delivery_item_id,
                'expected_quantity' => (float) $item->expected_quantity,
                'received_quantity' => (float) $item->received_quantity,
                'item_condition' => (string) ($item->item_condition ?? 'GOOD'),
                'item' => ($item->relationLoaded('purchaseOrderItem') && $item->purchaseOrderItem && $item->purchaseOrderItem->relationLoaded('item') && $item->purchaseOrderItem->item) ? [
                    'item_name' => (string) $item->purchaseOrderItem->item->item_name,
                    'item_id' => (int) $item->purchaseOrderItem->item->item_id,
                ] : null,
            ])->values();
        }

        return Inertia::render('Inventory/Delivery/Receive', [
            'delivery' => [
                'delivery_id' => (int) $delivery->delivery_id,
                'delivery_code' => (string) $delivery->delivery_code,
                'receipt_status' => (string) $delivery->receipt_status,
                'actual_delivery_date' => optional($delivery->actual_delivery_date)?->toDateString(),
                'expected_arrival_date' => optional($delivery->expected_arrival_date)?->toDateString(),
                'remarks' => $delivery->remarks,
                'purchase_order' => $delivery->purchaseOrder ? [
                    'purchase_order_id' => (int) $delivery->purchaseOrder->purchase_order_id,
                    'po_number' => (string) ($delivery->purchaseOrder->po_number ?? ''),
                ] : null,
                'supplier' => $delivery->supplier ? [
                    'supplier_name' => (string) ($delivery->supplier->supplier_name ?? ''),
                ] : null,
                'items' => $items->values(),
            ],
        ]);
    }

    public function edit(Delivery $delivery): Response
    {
        abort_unless(request()->user()->can('delivery.update', $delivery), 403);

        if ((string) $delivery->receipt_status !== 'PENDING') {
            abort(403, 'Only PENDING deliveries can be edited.');
        }

        return Inertia::render('Inventory/Delivery/Edit', [
            'delivery' => $delivery,
        ]);
    }

    public function update(UpdateDeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        abort_unless(request()->user()->can('delivery.update', $delivery), 403);

        if ((string) $delivery->receipt_status !== 'PENDING') {
            abort(403, 'Only PENDING deliveries can be updated.');
        }

        $delivery = $this->deliveryService->updateReceipt(
            delivery: $delivery,
            actor: request()->user(),
            payload: $request->validated(),
        );

        if ($request->has('items')) {
            $delivery = $this->deliveryService->markReceived(
                delivery: $delivery,
                actor: request()->user(),
                remarks: $request->validated('remarks'),
            );
        }

        return Redirect::route('inventory.deliveries.show', $delivery->delivery_id)
            ->with('success', 'Delivery updated successfully.');
    }
}
