<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Delivery\StoreDeliveryRequest;
use App\Http\Requests\Delivery\UpdateDeliveryRequest;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StatusHistory;
use App\Services\Delivery\DeliveryService;
use App\Services\Documents\DocumentService;
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
                    $nested->whereLikeInsensitive('delivery_code', $search)
                        ->orWhereHas('purchaseOrder', fn ($purchaseOrderQuery) => $purchaseOrderQuery->whereLikeInsensitive('po_number', $search))
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->whereLikeInsensitive('supplier_name', $search));
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

        $documents = Document::query()
            ->where('related_module', 'deliveries')
            ->where('related_reference_id', (int) $delivery->delivery_id)
            ->with('uploader')
            ->latest('document_id')
            ->get();

        $hasDrFile = $documents->contains(fn (Document $doc): bool => $doc->document_kind === 'delivery_receipt'
            || str_contains(strtolower($doc->title), 'delivery receipt')
            || str_contains(strtolower($doc->title), ' dr '));

        $hasSiFile = $documents->contains(fn (Document $doc): bool => $doc->document_kind === 'sales_invoice'
            || str_contains(strtolower($doc->title), 'sales invoice')
            || str_contains(strtolower($doc->title), 'invoice'));

        $hasAnyFile = $documents->isNotEmpty();
        $hasBillingNumbers = ! empty($delivery->delivery_receipt_no) && ! empty($delivery->sales_invoice_no);
        $isAuditReady = $hasBillingNumbers && ($hasDrFile || $hasSiFile || $hasAnyFile);

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
                    'can_upload_documents' => request()->user()->can('delivery.update', $delivery),
                    'can_delete_documents' => request()->user()->can('delivery.update', $delivery) && (string) $delivery->receipt_status === 'PENDING',
                ],
                'delivery_receipt_no' => (string) ($delivery->delivery_receipt_no ?? ''),
                'sales_invoice_no' => (string) ($delivery->sales_invoice_no ?? ''),
                'sales_invoice_date' => optional($delivery->sales_invoice_date)?->toDateString(),
                'sales_invoice_amount' => $delivery->sales_invoice_amount !== null ? (float) $delivery->sales_invoice_amount : null,
                'audit_status' => [
                    'has_dr_no' => ! empty($delivery->delivery_receipt_no),
                    'has_si_no' => ! empty($delivery->sales_invoice_no),
                    'has_dr_file' => $hasDrFile,
                    'has_si_file' => $hasSiFile,
                    'has_any_file' => $hasAnyFile,
                    'is_audit_ready' => $isAuditReady,
                    'status_label' => $isAuditReady ? 'ATTACHED & AUDIT READY' : 'PENDING SCANNED COPY',
                ],
                'documents' => $documents->map(fn (Document $doc): array => [
                    'document_id' => (int) $doc->document_id,
                    'title' => (string) $doc->title,
                    'file_name' => (string) $doc->file_name,
                    'document_kind' => (string) ($doc->document_kind ?? ''),
                    'uploaded_at' => optional($doc->uploaded_at)?->toIso8601String(),
                    'uploader_name' => optional($doc->uploader)?->name,
                ])->values(),
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

        $poPlacedDate = null;
        if ($delivery->purchaseOrder instanceof PurchaseOrder) {
            $poPlacedDate = StatusHistory::query()
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', (int) $delivery->purchaseOrder->purchase_order_id)
                ->where('new_status', 'PLACED')
                ->orderBy('changed_at')
                ->value('changed_at')?->toDateString()
                ?? optional($delivery->purchaseOrder->updated_at)?->toDateString()
                ?? optional($delivery->purchaseOrder->created_at)?->toDateString();
        }

        $existingDocuments = Document::query()
            ->where('related_module', 'deliveries')
            ->where('related_reference_id', (int) $delivery->delivery_id)
            ->get();
        $hasExistingDr = $existingDocuments->contains(fn ($doc) => $doc->document_kind === 'delivery_receipt'
            || str_contains(strtolower((string) $doc->title), 'delivery receipt')
            || str_contains(strtolower((string) $doc->title), ' dr '));
        $hasExistingSi = $existingDocuments->contains(fn ($doc) => $doc->document_kind === 'sales_invoice'
            || str_contains(strtolower((string) $doc->title), 'sales invoice')
            || str_contains(strtolower((string) $doc->title), 'invoice'));

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
                    'default_unit' => (string) ($item->purchaseOrderItem->unit ?: ($item->purchaseOrderItem->item->default_unit ?? 'piece')),
                    'pieces_per_box' => (int) ($item->purchaseOrderItem->item->pieces_per_box ?? 1),
                    'sub_unit' => (string) ($item->purchaseOrderItem->item->sub_unit ?? 'piece'),
                ] : null,
            ])->values();
        }

        $estimatedPoTotal = 0.0;
        if ($delivery->purchaseOrder instanceof PurchaseOrder) {
            $delivery->purchaseOrder->loadMissing('items');
            $estimatedPoTotal = (float) $delivery->purchaseOrder->items->sum(function ($poItem): float {
                return (float) $poItem->quantity_ordered * (float) $poItem->unit_price;
            });
        }

        $isZeroCost = (bool) ($delivery->purchaseOrder?->is_zero_cost_replacement ?? false);

        return Inertia::render('Inventory/Delivery/Receive', [
            'delivery' => [
                'delivery_id' => (int) $delivery->delivery_id,
                'delivery_code' => (string) $delivery->delivery_code,
                'receipt_status' => (string) $delivery->receipt_status,
                'actual_delivery_date' => optional($delivery->actual_delivery_date)?->toDateString(),
                'expected_arrival_date' => optional($delivery->expected_arrival_date)?->toDateString(),
                'delivery_receipt_no' => (string) ($delivery->delivery_receipt_no ?? ''),
                'sales_invoice_no' => (string) ($delivery->sales_invoice_no ?? ''),
                'sales_invoice_date' => optional($delivery->sales_invoice_date)?->toDateString(),
                'sales_invoice_amount' => $delivery->sales_invoice_amount !== null ? (float) $delivery->sales_invoice_amount : ($isZeroCost ? 0.0 : ($estimatedPoTotal > 0 ? $estimatedPoTotal : null)),
                'estimated_po_total' => $estimatedPoTotal,
                'is_zero_cost_replacement' => $isZeroCost,
                'remarks' => $delivery->remarks,
                'has_existing_dr' => $hasExistingDr,
                'has_existing_si' => $hasExistingSi,
                'purchase_order' => $delivery->purchaseOrder ? [
                    'purchase_order_id' => (int) $delivery->purchaseOrder->purchase_order_id,
                    'po_number' => (string) ($delivery->purchaseOrder->po_number ?? ''),
                    'placed_date' => $poPlacedDate,
                    'is_zero_cost_replacement' => $isZeroCost,
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

        $documentService = app(DocumentService::class);

        if ($request->hasFile('delivery_receipt_file')) {
            $documentService->upload(
                request()->user(),
                'deliveries',
                (int) $delivery->delivery_id,
                [
                    'title' => 'Supplier Delivery Receipt (DR) — ' . ($delivery->delivery_receipt_no ?: $delivery->delivery_code),
                    'document_kind' => 'delivery_receipt',
                    'file' => $request->file('delivery_receipt_file'),
                ]
            );
        }

        if ($request->hasFile('sales_invoice_file')) {
            $documentService->upload(
                request()->user(),
                'deliveries',
                (int) $delivery->delivery_id,
                [
                    'title' => 'Supplier Sales Invoice (SI) — ' . ($delivery->sales_invoice_no ?: $delivery->delivery_code),
                    'document_kind' => 'sales_invoice',
                    'file' => $request->file('sales_invoice_file'),
                ]
            );
        }

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
