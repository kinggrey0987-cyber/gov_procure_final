<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StorePurchaseOrderItemRequest;
use App\Http\Requests\Procurement\UpdatePurchaseOrderItemRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderItemController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
        private readonly \App\Services\Procurement\ConsolidationTraceabilityService $traceabilityService,
    ) {
    }

    public function index(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.view', $purchaseOrder), 403);

        $purchaseOrder->load([
            'items.item.category',
            'purchaseRequest.office',
            'purchaseRequest.items',
            'supplyRequest.office',
        ]);

        $sourceSupplyRequests = $this->traceabilityService->getSourceSupplyRequests($purchaseOrder);

        $purchaseRequestItems = $purchaseOrder->purchaseRequest?->items ?? collect();
        $prItemsByItemId = $purchaseRequestItems->keyBy('item_id');
        $validItemIds = $prItemsByItemId->keys()->map(fn ($id) => (int) $id)->all();

        $sourceItems = collect();
        foreach ($sourceSupplyRequests as $sr) {
            $sr->loadMissing(['items.item', 'items.ppmpItem', 'office']);
            foreach ($sr->items ?? [] as $row) {
                if ($validItemIds !== [] && ! in_array((int) $row->item_id, $validItemIds, true)) {
                    continue;
                }

                $prItem = $prItemsByItemId->get((int) $row->item_id);
                $qtyRequested = $prItem ? (float) $prItem->quantity_requested : (float) ($row->quantity_requested ?? 0);

                $sourceItems->push([
                    'supply_request_item_id' => (int) $row->supply_request_item_id,
                    'supply_request_id' => (int) $row->supply_request_id,
                    'request_code' => (string) $sr->request_code,
                    'office_code' => (string) ($sr->office?->code ?? ''),
                    'item_id' => (int) $row->item_id,
                    'item_name_snapshot' => (string) ($row->item_name_snapshot ?: ($row->item?->item_name ?? 'Unknown item')),
                    'item_specification' => (string) ($row->ppmpItem?->remarks ?? ''),
                    'default_unit' => (string) ($row->item?->default_unit ?? ''),
                    'quantity_requested' => $qtyRequested,
                ]);
            }
        }

        if ($sourceItems->isEmpty() && $purchaseOrder->supplyRequest) {
            $purchaseOrder->loadMissing(['supplyRequest.items.item', 'supplyRequest.items.ppmpItem', 'supplyRequest.office']);
            foreach ($purchaseOrder->supplyRequest->items ?? [] as $row) {
                if ($validItemIds !== [] && ! in_array((int) $row->item_id, $validItemIds, true)) {
                    continue;
                }

                $prItem = $prItemsByItemId->get((int) $row->item_id);
                $qtyRequested = $prItem ? (float) $prItem->quantity_requested : (float) ($row->quantity_requested ?? 0);

                $sourceItems->push([
                    'supply_request_item_id' => (int) $row->supply_request_item_id,
                    'supply_request_id' => (int) $row->supply_request_id,
                    'request_code' => (string) $purchaseOrder->supplyRequest->request_code,
                    'office_code' => (string) ($purchaseOrder->supplyRequest->office?->code ?? ''),
                    'item_id' => (int) $row->item_id,
                    'item_name_snapshot' => (string) ($row->item_name_snapshot ?: ($row->item?->item_name ?? 'Unknown item')),
                    'item_specification' => (string) ($row->ppmpItem?->remarks ?? ''),
                    'default_unit' => (string) ($row->item?->default_unit ?? ''),
                    'quantity_requested' => $qtyRequested,
                ]);
            }
        }

        return Inertia::render('Procurement/PurchaseOrderItems/Index', [
            'purchaseOrder' => $purchaseOrder,
            'items' => $purchaseOrder->items()->latest('purchase_order_item_id')->paginate(5)->withQueryString(),
            'sourceItems' => $sourceItems->values(),
            'encodedSourceItemIds' => $purchaseOrder->items()
                ->whereNotNull('supply_request_item_id')
                ->where('supply_request_item_id', '>', 0)
                ->pluck('supply_request_item_id')
                ->map(fn ($id) => (int) $id)
                ->values(),
        ]);
    }

    public function store(StorePurchaseOrderItemRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.purchase-order.update', $purchaseOrder), 403);

        $this->service->addItem($purchaseOrder, $request->user(), $request->validated());

        return redirect()->route('procurement.purchase-orders.items.index', $purchaseOrder->purchase_order_id)
            ->with('success', 'Purchase order item added successfully.');
    }

    public function update(
        UpdatePurchaseOrderItemRequest $request,
        PurchaseOrder $purchaseOrder,
        PurchaseOrderItem $purchaseOrderItem
    ): RedirectResponse {
        abort_unless($request->user()?->can('procurement.purchase-order.update', $purchaseOrder), 403);

        $this->service->updateItem($purchaseOrder, $purchaseOrderItem, $request->user(), $request->validated());

        return redirect()->route('procurement.purchase-orders.items.index', $purchaseOrder->purchase_order_id)
            ->with('success', 'Purchase order item updated successfully.');
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $purchaseOrderItem): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.purchase-order.update', $purchaseOrder), 403);

        $this->service->removeItem($purchaseOrder, $purchaseOrderItem, $request->user());

        return redirect()->route('procurement.purchase-orders.items.index', $purchaseOrder->purchase_order_id)
            ->with('success', 'Purchase order item removed successfully.');
    }
}
