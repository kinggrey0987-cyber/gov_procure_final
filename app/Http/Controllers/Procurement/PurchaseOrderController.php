<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\PlacePurchaseOrderRequest;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Http\Requests\Procurement\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Services\Documents\DocumentService;
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
        private readonly DocumentService $documentService,
    ) {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.view-any'), 403);

        $purchaseOrders = PurchaseOrder::query()
            ->with(['purchaseRequest', 'supplyRequest', 'supplier'])
            ->latest('purchase_order_id')
            ->paginate(15);

        return Inertia::render('Procurement/PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.create'), 403);

        $purchaseRequests = PurchaseRequest::query()
            ->processing()
            ->latest('purchase_request_id')
            ->get(['purchase_request_id', 'supply_request_id', 'office_id', 'status']);

        $suppliers = Supplier::query()->active()->orderBy('supplier_name')->get(['supplier_id', 'supplier_name']);

        return Inertia::render('Procurement/PurchaseOrders/Create', [
            'purchaseRequests' => $purchaseRequests,
            'suppliers' => $suppliers,
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.purchase-order.create'), 403);

        $purchaseOrder = $this->service->create($request->user(), $request->validated());

        return redirect()->route('procurement.purchase-orders.show', $purchaseOrder->purchase_order_id)
            ->with('success', 'Purchase order created successfully.');
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.view', $purchaseOrder), 403);

        $documents = $this->documentService
            ->listForRelated($request->user(), 'purchase_orders', (int) $purchaseOrder->purchase_order_id)
            ->map(fn ($document): array => [
                'document_id' => (int) $document->document_id,
                'title' => (string) $document->title,
                'file_name' => (string) $document->file_name,
                'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
                'uploaded_by' => (int) $document->uploaded_by,
                'uploader_name' => $document->uploader?->name,
            ])->values();

        return Inertia::render('Procurement/PurchaseOrders/Show', [
            'purchaseOrder' => $purchaseOrder->load(['items', 'supplier', 'creator', 'purchaseRequest', 'supplyRequest']),
            'attachments' => $documents,
            'attachmentPermissions' => [
                'upload' => $request->user()?->can('procurement.purchase-order.view', $purchaseOrder) ?? false,
                'delete' => $request->user()?->can('procurement.purchase-order.view', $purchaseOrder) ?? false,
            ],
        ]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.update', $purchaseOrder), 403);

        $suppliers = Supplier::query()->active()->orderBy('supplier_name')->get(['supplier_id', 'supplier_name']);

        return Inertia::render('Procurement/PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => $suppliers,
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.purchase-order.update', $purchaseOrder), 403);

        $this->service->update($purchaseOrder, $request->user(), $request->validated());

        return redirect()->route('procurement.purchase-orders.show', $purchaseOrder->purchase_order_id)
            ->with('success', 'Purchase order updated successfully.');
    }

    public function place(PlacePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.purchase-order.place', $purchaseOrder), 403);

        $this->service->place($purchaseOrder, $request->user(), $request->input('remarks'));

        return redirect()->route('procurement.purchase-orders.show', $purchaseOrder->purchase_order_id)
            ->with('success', 'Purchase order placed successfully.');
    }
}
