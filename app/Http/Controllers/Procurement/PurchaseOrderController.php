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
use App\Services\History\HistoryTimelineService;
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
        private readonly HistoryTimelineService $historyTimelineService,
    ) {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.view-any'), 403);

        $status = strtoupper(trim((string) $request->input('status', '')));
        $search = trim((string) $request->input('search', ''));

        $purchaseOrders = PurchaseOrder::query()
            ->with(['purchaseRequest.office', 'supplyRequest', 'supplier'])
            ->withCount('deliveries')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->whereLikeInsensitive('po_number', $search)
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->whereLikeInsensitive('supplier_name', $search))
                        ->orWhereHas('purchaseRequest.office', fn ($officeQuery) => $officeQuery->whereLikeInsensitive('name', $search));
                });
            })
            ->latest('purchase_order_id')
            ->paginate(5)
            ->withQueryString();

        return Inertia::render('Procurement/PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.create'), 403);

        $eligiblePurchaseRequests = PurchaseRequest::query()
            ->processing()
            ->with([
                'office:id,code,name',
                'supplyRequest:supply_request_id,request_code,requested_by',
                'supplyRequest.requester:id,name',
                'items.item.category:item_category_id,category_name',
            ])
            ->latest('purchase_request_id')
            ->get(['purchase_request_id', 'supply_request_id', 'office_id', 'status', 'requested_by', 'created_at'])
            ->map(fn (PurchaseRequest $purchaseRequest): array => [
                'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                'status' => (string) $purchaseRequest->status,
                'created_at' => optional($purchaseRequest->created_at)?->toIso8601String(),
                'office' => $purchaseRequest->office ? [
                    'id' => (int) $purchaseRequest->office->id,
                    'code' => (string) $purchaseRequest->office->code,
                    'name' => (string) $purchaseRequest->office->name,
                ] : null,
                'supply_request' => $purchaseRequest->supplyRequest ? [
                    'supply_request_id' => (int) $purchaseRequest->supplyRequest->supply_request_id,
                    'request_code' => (string) ($purchaseRequest->supplyRequest->request_code ?? ''),
                ] : null,
                'origin_requester' => $purchaseRequest->supplyRequest?->requester ? [
                    'id' => (int) $purchaseRequest->supplyRequest->requester->id,
                    'name' => (string) $purchaseRequest->supplyRequest->requester->name,
                ] : null,
                'items' => $purchaseRequest->items->map(fn ($item): array => [
                    'purchase_request_item_id' => (int) $item->purchase_request_item_id,
                    'item_name' => (string) ($item->item?->item_name ?? 'Unknown item'),
                    'category_name' => (string) ($item->item?->category?->category_name ?? 'Uncategorized'),
                    'quantity_requested' => (float) ($item->quantity_requested ?? 0),
                    'remarks' => $item->remarks,
                ])->values(),
            ])
            ->values();

        $suppliers = Supplier::query()
            ->active()
            ->orderBy('supplier_name')
            ->get(['supplier_id', 'supplier_name'])
            ->map(fn (Supplier $supplier): array => [
                'supplier_id' => (int) $supplier->supplier_id,
                'supplier_name' => (string) $supplier->supplier_name,
            ])
            ->values();

        return Inertia::render('Procurement/PurchaseOrders/Create', [
            'eligiblePurchaseRequests' => $eligiblePurchaseRequests,
            'suppliers' => $suppliers,
            'poNumberPreview' => $this->service->previewNextPoNumber(),
        ]);
    }

    public function records(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.view-any'), 403);

        $status = trim((string) $request->input('status', ''));

        $records = PurchaseOrder::query()
            ->with(['supplier', 'purchaseRequest.office'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->where('status', '!=', 'DRAFT')
            ->latest('purchase_order_id')
            ->paginate(5)
            ->withQueryString();

        return Inertia::render('Procurement/Records/Index', [
            'records' => $records,
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.purchase-order.create'), 403);

        $purchaseOrder = $this->service->create($request->user(), $request->validated());

        return redirect()->route('procurement.purchase-orders.items.index', $purchaseOrder->purchase_order_id)
            ->with('success', 'Purchase order created successfully. Add item prices and quantities next.');
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.view', $purchaseOrder), 403);
        $isExecutiveRoute = $request->routeIs('executive.procurement-records.*');

        $history = $this->historyTimelineService->forRecord($request->user(), $purchaseOrder);

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
            'purchaseOrder' => $purchaseOrder->load([
                'items.item.category',
                'supplier',
                'creator',
                'purchaseRequest.office',
                'supplyRequest.requester',
            ]),
            'attachments' => $documents,
            'attachmentPermissions' => [
                'upload' => $request->user()?->can('procurement.purchase-order.view', $purchaseOrder) ?? false,
                'delete' => $request->user()?->can('procurement.purchase-order.view', $purchaseOrder) ?? false,
            ],
            'permissions' => [
                'place' => $request->user()?->can('procurement.purchase-order.place', $purchaseOrder) ?? false,
            ],
            'backLink' => $isExecutiveRoute ? [
                'href' => route('executive.procurement-analytics'),
                'label' => 'Back to Procurement Insights',
            ] : [
                'href' => route('procurement.purchase-orders.index'),
                'label' => 'Back to Purchase Orders',
            ],
            'showProcurementDocumentsLink' => ! $isExecutiveRoute,
            'statusHistory' => $history['status']->map(fn ($entry): array => [
                'id' => (int) $entry->id,
                'old_status' => $entry->old_status,
                'new_status' => (string) $entry->new_status,
                'remarks' => $entry->remarks,
                'changed_at' => optional($entry->changed_at)?->toIso8601String(),
                'changed_by' => $entry->changedBy ? [
                    'id' => (int) $entry->changedBy->id,
                    'name' => (string) $entry->changedBy->name,
                ] : null,
            ])->values(),
            'approvalHistory' => $history['approval']->map(fn ($entry): array => [
                'id' => (int) $entry->id,
                'status' => (string) $entry->status,
                'remarks' => $entry->remarks,
                'approved_at' => optional($entry->approved_at)?->toIso8601String(),
                'approver' => $entry->approver ? [
                    'id' => (int) $entry->approver->id,
                    'name' => (string) $entry->approver->name,
                ] : null,
            ])->values(),
        ]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.update', $purchaseOrder), 403);

        $suppliers = Supplier::query()->active()->orderBy('supplier_name')->get(['supplier_id', 'supplier_name']);

        return Inertia::render('Procurement/PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseOrder->load([
                'supplier:supplier_id,supplier_name',
                'purchaseRequest.office:id,code,name',
                'supplyRequest.requester:id,name',
                'supplyRequest.items.item.category',
            ]),
            'suppliers' => $suppliers,
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.purchase-order.update', $purchaseOrder), 403);

        $this->service->update($purchaseOrder, $request->user(), $request->validated());

        return redirect()->route('procurement.purchase-orders.items.index', $purchaseOrder->purchase_order_id)
            ->with('success', 'Purchase order updated. Continue encoding item prices and quantities.');
    }

    public function place(PlacePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.purchase-order.place', $purchaseOrder), 403);

        $this->service->place($purchaseOrder, $request->user(), $request->input('remarks'));

        return redirect()->route('procurement.purchase-orders.show', $purchaseOrder->purchase_order_id)
            ->with('success', 'Purchase order placed successfully.');
    }
}
