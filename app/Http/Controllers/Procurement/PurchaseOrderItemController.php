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
    public function __construct(private readonly PurchaseOrderService $service)
    {
    }

    public function index(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.view', $purchaseOrder), 403);

        return Inertia::render('Procurement/PurchaseOrderItems/Index', [
            'purchaseOrder' => $purchaseOrder,
            'items' => $purchaseOrder->items()->latest('purchase_order_item_id')->paginate(20),
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
}
