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

        $sourceItems = collect();
        $purchaseRequest = $purchaseOrder->purchaseRequest;

        if ($purchaseRequest && $purchaseRequest->items()->exists()) {
            $purchaseRequest->loadMissing(['items.item', 'office']);

            // Track already-matched SR item IDs so multiple PR items with the
            // same item_id (from consolidated PRs with different specifications)
            // are distributed across distinct source SR items instead of all
            // resolving to the same first match.
            $matchedSrItemIds = [];

            foreach ($purchaseRequest->items as $prItem) {
                // Resolve matching supply request item id if available
                $srItem = null;
                foreach ($sourceSupplyRequests as $sr) {
                    $match = $sr->items
                        ->where('item_id', $prItem->item_id)
                        ->whereNotIn('supply_request_item_id', $matchedSrItemIds)
                        ->first();
                    if ($match) {
                        $srItem = $match;
                        break;
                    }
                }
                if (! $srItem && $purchaseOrder->supplyRequest) {
                    $srItem = $purchaseOrder->supplyRequest->items
                        ->where('item_id', $prItem->item_id)
                        ->whereNotIn('supply_request_item_id', $matchedSrItemIds)
                        ->first();
                }

                // Skip items that cannot be traced to a supply request item.
                // Using purchase_request_item_id as a supply_request_item_id fallback
                // causes cross-table ID collisions and validation failures.
                if (! $srItem) {
                    continue;
                }

                $matchedSrItemIds[] = (int) $srItem->supply_request_item_id;

                $sourceItems->push([
                    'supply_request_item_id' => (int) $srItem->supply_request_item_id,
                    'supply_request_id' => (int) $srItem->supply_request_id,
                    'request_code' => (string) ($purchaseRequest->pr_number ?? 'PR'),
                    'office_code' => (string) ($purchaseRequest->office?->code ?? 'GSO'),
                    'item_id' => (int) $prItem->item_id,
                    'item_name_snapshot' => (string) ($prItem->item?->item_name ?? 'Unknown item'),
                    'item_specification' => (string) ($prItem->remarks ?? ''),
                    'default_unit' => (string) ($prItem->item?->default_unit ?? 'piece'),
                    'quantity_requested' => (float) $prItem->quantity_requested,
                ]);
            }
        } else {
            foreach ($sourceSupplyRequests as $sr) {
                $sr->loadMissing(['items.item', 'items.ppmpItem', 'office']);
                foreach ($sr->items ?? [] as $row) {
                    $sourceItems->push([
                        'supply_request_item_id' => (int) $row->supply_request_item_id,
                        'supply_request_id' => (int) $row->supply_request_id,
                        'request_code' => (string) $sr->request_code,
                        'office_code' => (string) ($sr->office?->code ?? ''),
                        'item_id' => (int) $row->item_id,
                        'item_name_snapshot' => (string) ($row->item_name_snapshot ?: ($row->item?->item_name ?? 'Unknown item')),
                        'item_specification' => (string) ($row->ppmpItem?->remarks ?? ''),
                        'default_unit' => (string) ($row->item?->default_unit ?? 'piece'),
                        'quantity_requested' => (float) ($row->quantity_requested ?? 0),
                    ]);
                }
            }
        }

        $encodedSrItemIds = $purchaseOrder->items()
            ->whereNotNull('supply_request_item_id')
            ->pluck('supply_request_item_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $encodedSourceItemIds = $sourceItems->filter(function ($si) use ($encodedSrItemIds) {
            return in_array((int) $si['supply_request_item_id'], $encodedSrItemIds, true);
        })
            ->pluck('supply_request_item_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return Inertia::render('Procurement/PurchaseOrderItems/Index', [
            'purchaseOrder' => $purchaseOrder,
            'items' => $purchaseOrder->items()->latest('purchase_order_item_id')->paginate(5)->withQueryString(),
            'sourceItems' => $sourceItems->values(),
            'encodedSourceItemIds' => $encodedSourceItemIds,
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
