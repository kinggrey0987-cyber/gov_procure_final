<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\PlacePurchaseOrderRequest;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Http\Requests\Procurement\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
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
        private readonly \App\Services\Procurement\ConsolidationTraceabilityService $traceabilityService,
    ) {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.view-any'), 403);

        $status = strtoupper(trim((string) $request->input('status', '')));
        $search = trim((string) $request->input('search', ''));

        $purchaseOrders = PurchaseOrder::query()
            ->with(['purchaseRequest.office', 'purchaseRequest.supplyRequest', 'purchaseRequest.sourceDelivery.purchaseOrder', 'supplyRequest', 'supplier'])
            ->withCount('deliveries')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->whereLikeInsensitive('po_number', $search)
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->whereLikeInsensitive('supplier_name', $search))
                        ->orWhereHas('purchaseRequest.office', fn ($officeQuery) => $officeQuery->whereLikeInsensitive('name', $search))
                        ->orWhereHas('purchaseRequest', fn ($prQuery) => $prQuery->whereLikeInsensitive('pr_number', $search));
                });
            })
            ->latest('purchase_order_id')
            ->paginate(5)
            ->withQueryString();

        $currentYear = (int) now()->format('Y');

        $readyPurchaseRequests = PurchaseRequest::query()
            ->processing()
            ->whereDoesntHave('purchaseOrders')
            ->whereYear('created_at', $currentYear)
            ->with(['office:id,code,name', 'supplyRequest:supply_request_id,request_code,priority_level'])
            ->latest('purchase_request_id')
            ->get(['purchase_request_id', 'pr_number', 'supply_request_id', 'office_id', 'status', 'created_at', 'remarks']);

        $readyPrCount = $readyPurchaseRequests->count();
        $urgentReadyPrCount = $readyPurchaseRequests->filter(fn ($pr) => $pr->priority_level === 'URGENT')->count();
        $readyPurchaseRequestsSummary = $readyPurchaseRequests->take(25)->map(fn ($pr): array => [
            'purchase_request_id' => (int) $pr->purchase_request_id,
            'pr_number' => (string) $pr->pr_number,
            'office_name' => (string) ($pr->office?->name ?? 'Unknown Office'),
            'office_code' => (string) ($pr->office?->code ?? $pr->office?->name ?? 'Office'),
            'is_urgent' => $pr->priority_level === 'URGENT',
        ])->values();

        return Inertia::render('Procurement/PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'readyPrCount' => $readyPrCount,
            'urgentReadyPrCount' => $urgentReadyPrCount,
            'readyPurchaseRequests' => $readyPurchaseRequestsSummary,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.create'), 403);

        $currentYear = (int) now()->format('Y');

        $eligiblePurchaseRequests = PurchaseRequest::query()
            ->processing()
            ->whereDoesntHave('purchaseOrders')
            ->whereYear('created_at', $currentYear)
            ->with([
                'office:id,code,name',
                'supplyRequest:supply_request_id,request_code,requested_by,item_category_id',
                'supplyRequest.category:item_category_id,category_name',
                'supplyRequest.requester:id,name',
                'items.item.category:item_category_id,category_name',
                'sourceDelivery.supplier:supplier_id,supplier_name',
                'sourceDelivery.purchaseOrder.supplier:supplier_id,supplier_name',
            ])
            ->latest('purchase_request_id')
            ->get(['purchase_request_id', 'pr_number', 'supply_request_id', 'office_id', 'status', 'requested_by', 'created_at', 'source_type', 'source_delivery_id', 'source_claim_id', 'remarks'])
            ->map(function (PurchaseRequest $purchaseRequest): array {
                $isDiscrepancyFollowUp = (bool) $purchaseRequest->is_zero_cost_replacement;
                $isClaimReplacement = ! $isDiscrepancyFollowUp && (bool) $purchaseRequest->is_claim_replacement;
                $sourceDelivery = $isDiscrepancyFollowUp ? $purchaseRequest->sourceDelivery : null;
                $parentPo = $sourceDelivery?->purchaseOrder;
                $parentSupplier = $isDiscrepancyFollowUp ? ($sourceDelivery?->supplier ?? $parentPo?->supplier) : null;

                $sourceRequests = $this->traceabilityService->getSourceSupplyRequests($purchaseRequest);
                $sourceCount = $sourceRequests->count();
                $isConsolidated = $sourceCount >= 2;
                $sourceCodes = $sourceRequests->pluck('request_code')->filter()->values()->all();
                $sourceOffices = $sourceRequests->map(fn ($sr) => (string) ($sr->office?->code ?? $sr->office?->name ?? 'OFFICE'))->unique()->values()->all();

                $prCategoryId = $purchaseRequest->supplyRequest?->item_category_id
                    ?: ($purchaseRequest->items->first()?->item?->item_category_id ?? null);
                $prCategoryName = (string) (
                    $purchaseRequest->supplyRequest?->category?->category_name
                    ?? $purchaseRequest->items->first()?->item?->category?->category_name
                    ?? 'General / Uncategorized'
                );

                return [
                    'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                    'pr_number' => (string) $purchaseRequest->pr_number,
                    'status' => (string) $purchaseRequest->status,
                    'item_category_id' => $prCategoryId ? (int) $prCategoryId : null,
                    'category_name' => $prCategoryName,
                    'created_at' => optional($purchaseRequest->created_at)?->toIso8601String(),
                    'source_type' => (string) ($purchaseRequest->source_type ?? ''),
                    'source_delivery_id' => $purchaseRequest->source_delivery_id ? (int) $purchaseRequest->source_delivery_id : null,
                    'source_claim_id' => $purchaseRequest->source_claim_id ? (int) $purchaseRequest->source_claim_id : null,
                    'is_discrepancy_follow_up' => $isDiscrepancyFollowUp,
                    'is_claim_replacement' => $isClaimReplacement,
                    'is_consolidated' => $isConsolidated,
                    'source_count' => $sourceCount,
                    'source_codes' => $sourceCodes,
                    'source_offices' => $sourceOffices,
                    'suggested_supplier_id' => $parentSupplier ? (int) $parentSupplier->supplier_id : null,
                    'suggested_supplier_name' => (string) ($parentSupplier?->supplier_name ?? ''),
                    'parent_delivery_code' => (string) ($sourceDelivery?->delivery_code ?? ''),
                    'parent_po_number' => (string) ($parentPo?->po_number ?? ''),
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
                        'unit' => (string) ($item->item?->default_unit ?? 'piece'),
                        'remarks' => $item->remarks,
                    ])->values(),
                ];
            })
            ->values();

        $suppliers = Supplier::query()
            ->active()
            ->with('categories:item_categories.item_category_id,category_name')
            ->orderBy('supplier_name')
            ->get(['supplier_id', 'supplier_name'])
            ->map(fn (Supplier $supplier): array => [
                'supplier_id' => (int) $supplier->supplier_id,
                'supplier_name' => (string) $supplier->supplier_name,
                'category_ids' => $supplier->categories->pluck('item_category_id')->map(fn ($id) => (int) $id)->values()->all(),
                'category_names' => $supplier->categories->pluck('category_name')->values()->all(),
            ])
            ->values();

        $selectedPrId = (int) ($request->query('pr_id') ?? $request->query('purchase_request_id') ?? 0);

        return Inertia::render('Procurement/PurchaseOrders/Create', [
            'eligiblePurchaseRequests' => $eligiblePurchaseRequests,
            'suppliers' => $suppliers,
            'poNumberPreview' => $this->service->previewNextPoNumber(),
            'selectedPurchaseRequestId' => $selectedPrId,
        ]);
    }

    public function records(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.view-any'), 403);

        $status = strtoupper(trim((string) $request->input('status', '')));

        $records = PurchaseOrder::query()
            ->with(['purchaseRequest.office', 'supplyRequest', 'supplier', 'items'])
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

        if ($purchaseOrder->items()->count() > 0) {
            return redirect()->route('procurement.purchase-orders.show', $purchaseOrder->purchase_order_id)
                ->with('success', 'Zero-cost replacement purchase order created successfully with items auto-populated at ₱0.00.');
        }

        return redirect()->route('procurement.purchase-orders.items.index', $purchaseOrder->purchase_order_id)
            ->with('success', 'Purchase order created successfully. Add item prices and quantities next.');
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.view', $purchaseOrder), 403);
        $isExecutiveRoute = $request->routeIs('executive.procurement-records.*');
        $isExecutive = (bool) $request->user()?->isExecutive();

        $history = $this->historyTimelineService->forRecord($request->user(), $purchaseOrder);

        $documents = $this->documentService
            ->listForRelated($request->user(), 'purchase_orders', (int) $purchaseOrder->purchase_order_id)
            ->map(fn ($document): array => [
                'document_id' => (int) $document->document_id,
                'title' => (string) $document->title,
                'file_name' => (string) $document->file_name,
                'uploaded_at' => optional($document->uploaded_at)?->format('M d, Y'),
                'uploaded_by' => (int) $document->uploaded_by,
                'uploader_name' => $document->uploader?->name,
                'view_url' => route('documents.show', $document->document_id),
                'download_url' => route('documents.show', ['document' => $document->document_id, 'download' => 1]),
            ])->values();

        $deliveries = [];
        $sourceDiscrepancyDelivery = null;

        if ($isExecutive) {
            $deliveries = $purchaseOrder->deliveries()
                ->with([
                    'receiver:id,name',
                    'verifications.verifier:id,name',
                    'documents.uploader:id,name',
                    'items.purchaseOrderItem.item',
                ])
                ->latest('delivery_id')
                ->get()
                ->map(fn (\App\Models\Delivery $delivery): array => [
                    'delivery_id' => (int) $delivery->delivery_id,
                    'delivery_code' => (string) $delivery->delivery_code,
                    'delivery_receipt_no' => $delivery->delivery_receipt_no,
                    'sales_invoice_no' => $delivery->sales_invoice_no,
                    'sales_invoice_date' => optional($delivery->sales_invoice_date)?->format('M d, Y'),
                    'sales_invoice_amount' => $delivery->sales_invoice_amount ? (float) $delivery->sales_invoice_amount : null,
                    'actual_delivery_date' => optional($delivery->actual_delivery_date)?->format('M d, Y'),
                    'receipt_status' => (string) $delivery->receipt_status,
                    'remarks' => $delivery->remarks,
                    'received_by' => $delivery->receiver ? [
                        'id' => (int) $delivery->receiver->id,
                        'name' => (string) $delivery->receiver->name,
                    ] : null,
                    'verifications' => $delivery->verifications->map(fn ($v): array => [
                        'verification_id' => (int) $v->verification_id,
                        'verification_status' => (string) $v->verification_status,
                        'inspection_notes' => $v->inspection_notes,
                        'verified_at' => optional($v->verified_at)?->format('M d, Y H:i'),
                        'verifier_name' => $v->verifier?->name,
                    ])->values(),
                    'items' => $delivery->items->map(fn ($di): array => [
                        'delivery_item_id' => (int) $di->delivery_item_id,
                        'item_name' => (string) ($di->purchaseOrderItem?->item?->item_name ?? $di->purchaseOrderItem?->item_name_snapshot ?? $di->item?->item_name ?? 'Item'),
                        'expected_quantity' => (float) $di->expected_quantity,
                        'received_quantity' => (float) $di->received_quantity,
                        'damaged_quantity' => (float) $di->damaged_quantity,
                        'missing_quantity' => (float) $di->missing_quantity,
                        'item_condition' => (string) $di->item_condition,
                        'remarks' => $di->remarks,
                    ])->values(),
                    'documents' => $delivery->documents->map(fn (\App\Models\Document $doc): array => [
                        'document_id' => (int) $doc->document_id,
                        'title' => (string) $doc->title,
                        'file_name' => (string) $doc->file_name,
                        'uploaded_at' => optional($doc->uploaded_at)?->format('M d, Y'),
                        'uploader_name' => $doc->uploader?->name,
                        'view_url' => route('documents.show', $doc->document_id),
                        'download_url' => route('documents.show', ['document' => $doc->document_id, 'download' => 1]),
                    ])->values(),
                ])->values();

            if ($purchaseOrder->is_zero_cost_replacement && $purchaseOrder->purchaseRequest?->sourceDelivery) {
                $sd = $purchaseOrder->purchaseRequest->sourceDelivery->loadMissing(['receiver:id,name', 'documents.uploader:id,name', 'verifications.verifier:id,name', 'purchaseOrder']);
                $sourceDiscrepancyDelivery = [
                    'delivery_id' => (int) $sd->delivery_id,
                    'delivery_code' => (string) $sd->delivery_code,
                    'delivery_receipt_no' => $sd->delivery_receipt_no,
                    'sales_invoice_no' => $sd->sales_invoice_no,
                    'sales_invoice_amount' => $sd->sales_invoice_amount ? (float) $sd->sales_invoice_amount : null,
                    'receipt_status' => (string) $sd->receipt_status,
                    'actual_delivery_date' => optional($sd->actual_delivery_date)?->format('M d, Y'),
                    'parent_po_number' => $sd->purchaseOrder?->po_number,
                    'documents' => $sd->documents->map(fn (\App\Models\Document $doc): array => [
                        'document_id' => (int) $doc->document_id,
                        'title' => (string) $doc->title,
                        'file_name' => (string) $doc->file_name,
                        'uploaded_at' => optional($doc->uploaded_at)?->format('M d, Y'),
                        'uploader_name' => $doc->uploader?->name,
                        'view_url' => route('documents.show', $doc->document_id),
                        'download_url' => route('documents.show', ['document' => $doc->document_id, 'download' => 1]),
                    ])->values(),
                ];
            }
        }

        $mayor = null;
        if ($purchaseOrder->approver instanceof User && ($purchaseOrder->approver->role?->code ?? '') === 'EXEC_OFFICER') {
            $mayor = $purchaseOrder->approver;
        } else {
            $mayor = User::query()
                ->whereHas('role', fn ($q) => $q->where('code', 'EXEC_OFFICER'))
                ->first();
        }
        $mayorName = (string) ($mayor?->name ?? 'Mayor Jeron');

        return Inertia::render('Procurement/PurchaseOrders/Show', [
            'purchaseOrder' => $purchaseOrder->load([
                'items.item.category',
                'supplier',
                'creator.role',
                'approver.role',
                'purchaseRequest.office',
                'purchaseRequest.sourceDelivery.purchaseOrder',
                'supplyRequest.requester',
            ]),
            'mayorName' => $mayorName,
            'isExecutive' => $isExecutive,
            'isZeroCostReplacement' => (bool) $purchaseOrder->is_zero_cost_replacement,
            'traceability' => $this->traceabilityService->resolveForPurchaseOrder($purchaseOrder),
            'deliveries' => $deliveries,
            'sourceDiscrepancyDelivery' => $sourceDiscrepancyDelivery,
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
                    'role' => (string) ($entry->changedBy->role?->name ?? ''),
                    'office' => (string) ($entry->changedBy->office?->name ?? ''),
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
                    'role' => (string) ($entry->approver->role?->name ?? ''),
                    'office' => (string) ($entry->approver->office?->name ?? ''),
                ] : null,
            ])->values(),
        ]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-order.update', $purchaseOrder), 403);

        $suppliers = Supplier::query()->active()->orderBy('supplier_name')->get(['supplier_id', 'supplier_name']);

        $purchaseOrder->loadMissing([
            'supplier:supplier_id,supplier_name',
            'purchaseRequest.office:id,code,name',
            'purchaseRequest.sourceDelivery.supplier',
            'purchaseRequest.sourceDelivery.purchaseOrder.supplier',
            'supplyRequest.requester:id,name',
            'supplyRequest.items.item.category',
        ]);

        return Inertia::render('Procurement/PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => $suppliers,
            'isZeroCostReplacement' => (bool) $purchaseOrder->is_zero_cost_replacement,
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.purchase-order.update', $purchaseOrder), 403);

        $purchaseOrder->loadMissing('purchaseRequest');

        if ($purchaseOrder->is_zero_cost_replacement && (int) $request->validated('supplier_id') !== (int) $purchaseOrder->supplier_id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'supplier_id' => 'Cannot change supplier for a warranty replacement purchase order. It is locked to the original contractor.',
            ]);
        }

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
