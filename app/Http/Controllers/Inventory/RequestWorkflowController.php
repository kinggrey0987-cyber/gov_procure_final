<?php

namespace App\Http\Controllers\Inventory;

use App\Models\Inventory;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\Delivery;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RequestWorkflowController
{
    public function __construct(private readonly PurchaseRequestService $purchaseRequestService)
    {
    }

    public function officeSupplyRequests(Request $request): Response
    {
        abort_unless(Auth::user()?->can('inventory.view-any'), 403);

        $query = SupplyRequest::query()
            ->with(['office', 'requester', 'items.item.inventory', 'purchaseRequests'])
            ->withCount('distributionRecords')
            ->latest('supply_request_id');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('request_code', 'like', '%'.$search.'%')
                    ->orWhere('purpose', 'like', '%'.$search.'%')
                    ->orWhereHas('office', fn ($officeQuery) => $officeQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        $officeId = (int) $request->query('office_id', 0);
        if ($officeId > 0) {
            $query->where('office_id', $officeId);
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $rawRequests = $query->get();

        $distributionReadySupplyRequestIds = Delivery::query()
            ->verified()
            ->whereDoesntHave('distributionRecords')
            ->with('purchaseOrder:supply_request_id,purchase_order_id')
            ->get()
            ->map(fn (Delivery $delivery): ?int => $delivery->purchaseOrder?->supply_request_id)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $distributionReadyLookup = array_flip($distributionReadySupplyRequestIds);

        $rows = $rawRequests->map(function (SupplyRequest $request) use ($distributionReadyLookup): array {
            $requestedTotal = 0.0;
            $shortageTotal = 0.0;
            $requestedItems = [];

            foreach ($request->items as $item) {
                $requested = (float) $item->quantity_requested;
                $available = (float) ($item->item?->inventory?->available_stock ?? 0);

                $requestedTotal += $requested;
                $shortageTotal += max($requested - $available, 0);

                $requestedItems[] = [
                    'item_name' => (string) ($item->item?->item_name ?? $item->item_name_snapshot ?? 'Unknown Item'),
                    'quantity_requested' => round($requested, 2),
                    'unit' => (string) ($item->item?->default_unit ?? 'UNIT'),
                ];
            }

            $hasPurchaseRequest = $request->purchaseRequests->isNotEmpty();
            $stockAssessment = $shortageTotal > 0 ? 'SHORTAGE_REQUIRES_PROCUREMENT' : 'STOCK_READY';
            $hasDistributionCandidate = isset($distributionReadyLookup[(int) $request->supply_request_id])
                || (
                    $stockAssessment === 'STOCK_READY'
                    && ! $hasPurchaseRequest
                    && (int) $request->distribution_records_count === 0
                );

            $workflowBucket = 'UNDER_VALIDATION';
            if ($hasPurchaseRequest) {
                $workflowBucket = 'QUEUED_FOR_PROCUREMENT';
            } elseif ($stockAssessment === 'STOCK_READY' && (int) $request->distribution_records_count === 0) {
                $workflowBucket = 'READY_FOR_DISTRIBUTION';
            } elseif ($stockAssessment === 'STOCK_READY') {
                $workflowBucket = 'STOCK_READY_AWAITING_RELEASE_CONTEXT';
            }

            return [
                'supply_request_id' => (int) $request->supply_request_id,
                'request_code' => (string) $request->request_code,
                'status' => (string) $request->status,
                'priority_level' => (string) $request->priority_level,
                'needed_by_date' => optional($request->needed_by_date)?->toDateString(),
                'office' => $request->office ? [
                    'id' => (int) $request->office->id,
                    'name' => (string) $request->office->name,
                ] : null,
                'requester' => $request->requester ? [
                    'name' => (string) $request->requester->name,
                ] : null,
                'item_count' => $request->items->count(),
                'requested_total' => round($requestedTotal, 2),
                'shortage_total' => round($shortageTotal, 2),
                'requested_items' => $requestedItems,
                'stock_assessment' => $stockAssessment,
                'has_purchase_request' => $hasPurchaseRequest,
                'has_distribution_candidate' => $hasDistributionCandidate,
                'distribution_records_count' => (int) $request->distribution_records_count,
                'workflow_bucket' => $workflowBucket,
            ];
        });

        $stockAssessmentFilter = trim((string) $request->query('stock_assessment', ''));
        if ($stockAssessmentFilter !== '') {
            $rows = $rows->where('stock_assessment', $stockAssessmentFilter);
        }

        $workflowBucketFilter = trim((string) $request->query('workflow_bucket', ''));
        if ($workflowBucketFilter !== '') {
            $rows = $rows->where('workflow_bucket', $workflowBucketFilter);
        }

        $rows = $rows->values();

        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $requests = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $offices = Office::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Office $office): array => [
                'id' => (int) $office->id,
                'name' => (string) $office->name,
            ])
            ->values();

        return Inertia::render('Inventory/OfficeSupplyRequests/Index', [
            'requests' => $requests,
            'offices' => $offices,
            'filters' => [
                'q' => $search,
                'office_id' => $officeId > 0 ? $officeId : null,
                'status' => $status,
                'stock_assessment' => $stockAssessmentFilter,
                'workflow_bucket' => $workflowBucketFilter,
            ],
        ]);
    }

    public function officeSupplyRequestShow(Request $request, SupplyRequest $supplyRequest): Response
    {
        abort_unless($request->user()?->can('inventory.view-any'), 403);

        $supplyRequest->load([
            'office',
            'requester',
            'items.item.inventory',
            'purchaseRequests',
            'distributionRecords',
        ]);

        $requestedTotal = 0.0;
        $shortageTotal = 0.0;

        $items = $supplyRequest->items
            ->map(function (SupplyRequestItem $item) use (&$requestedTotal, &$shortageTotal): array {
                $requested = (float) $item->quantity_requested;
                $available = (float) ($item->item?->inventory?->available_stock ?? 0);
                $shortage = max($requested - $available, 0);

                $requestedTotal += $requested;
                $shortageTotal += $shortage;

                return [
                    'supply_request_item_id' => (int) $item->supply_request_item_id,
                    'item_name' => (string) ($item->item?->item_name ?? $item->item_name_snapshot ?? 'Unknown Item'),
                    'unit' => (string) ($item->item?->default_unit ?? 'UNIT'),
                    'quantity_requested' => round($requested, 2),
                    'available_stock' => round($available, 2),
                    'shortage' => round($shortage, 2),
                ];
            })
            ->values();

        return Inertia::render('Inventory/OfficeSupplyRequests/Show', [
            'request' => [
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'request_code' => (string) $supplyRequest->request_code,
                'status' => (string) $supplyRequest->status,
                'priority_level' => (string) $supplyRequest->priority_level,
                'purpose' => (string) $supplyRequest->purpose,
                'needed_by_date' => optional($supplyRequest->needed_by_date)?->toDateString(),
                'request_date' => optional($supplyRequest->request_date)?->toDateString(),
                'office' => $supplyRequest->office ? [
                    'id' => (int) $supplyRequest->office->id,
                    'name' => (string) $supplyRequest->office->name,
                ] : null,
                'requester' => $supplyRequest->requester ? [
                    'id' => (int) $supplyRequest->requester->id,
                    'name' => (string) $supplyRequest->requester->name,
                ] : null,
                'item_count' => $items->count(),
                'requested_total' => round($requestedTotal, 2),
                'shortage_total' => round($shortageTotal, 2),
                'has_purchase_request' => $supplyRequest->purchaseRequests->isNotEmpty(),
                'purchase_request_count' => $supplyRequest->purchaseRequests->count(),
                'distribution_records_count' => $supplyRequest->distributionRecords->count(),
                'items' => $items,
            ],
        ]);
    }

    public function procurementRequests(): Response
    {
        abort_unless(Auth::user()?->can('inventory.view-any'), 403);

        $focusInventoryId = (int) request()->query('focus_inventory_id', 0);

        $activeLowStockQueueByInventoryId = PurchaseRequest::query()
            ->whereIn('status', ['OPEN', 'PROCESSING'])
            ->where('remarks', 'like', '%LOW_STOCK_SIGNAL:inventory:%')
            ->get(['purchase_request_id', 'remarks'])
            ->reduce(function (array $carry, PurchaseRequest $request): array {
                $remarks = (string) ($request->remarks ?? '');
                if (preg_match('/LOW_STOCK_SIGNAL:inventory:(\d+)/', $remarks, $matches) !== 1) {
                    return $carry;
                }

                $inventoryId = (int) ($matches[1] ?? 0);
                if ($inventoryId <= 0 || isset($carry[$inventoryId])) {
                    return $carry;
                }

                $carry[$inventoryId] = (int) $request->purchase_request_id;

                return $carry;
            }, []);

        $purchaseRequests = PurchaseRequest::query()
            ->with(['office', 'requester', 'supplyRequest'])
            ->latest('purchase_request_id')
            ->paginate(12);

        $shortageCandidates = SupplyRequest::query()
            ->submitted()
            ->whereDoesntHave('purchaseRequests')
            ->with(['office', 'items.item.inventory'])
            ->latest('supply_request_id')
            ->limit(20)
            ->get()
            ->map(function (SupplyRequest $request): array {
                $requestedTotal = 0.0;
                $shortageTotal = 0.0;

                foreach ($request->items as $item) {
                    $requested = (float) $item->quantity_requested;
                    $available = (float) ($item->item?->inventory?->available_stock ?? 0);

                    $requestedTotal += $requested;
                    $shortageTotal += max($requested - $available, 0);
                }

                return [
                    'supply_request_id' => (int) $request->supply_request_id,
                    'request_code' => (string) $request->request_code,
                    'office' => $request->office ? [
                        'name' => (string) $request->office->name,
                    ] : null,
                    'needed_by_date' => optional($request->needed_by_date)?->toDateString(),
                    'requested_total' => round($requestedTotal, 2),
                    'shortage_total' => round($shortageTotal, 2),
                ];
            })
            ->filter(fn (array $request): bool => $request['shortage_total'] > 0)
            ->values();

        $lowStockItems = Inventory::query()
            ->with('item')
            ->whereColumn('available_stock', '<', 'low_stock_threshold')
            ->orderByRaw('(low_stock_threshold - available_stock) DESC')
            ->limit(12)
            ->get()
            ->map(function (Inventory $inventory) use ($activeLowStockQueueByInventoryId): array {
                $inventoryId = (int) $inventory->inventory_id;
                $deficit = round(max((float) $inventory->low_stock_threshold - (float) $inventory->available_stock, 0), 2);
                $activePurchaseRequestId = $activeLowStockQueueByInventoryId[$inventoryId] ?? null;

                return [
                    'inventory_id' => $inventoryId,
                    'item_name' => (string) ($inventory->item?->item_name ?? 'Unassigned Item'),
                    'available_stock' => (float) $inventory->available_stock,
                    'low_stock_threshold' => (float) $inventory->low_stock_threshold,
                    'deficit' => $deficit,
                    'suggested_quantity' => $deficit,
                    'is_already_queued' => $activePurchaseRequestId !== null,
                    'active_purchase_request_id' => $activePurchaseRequestId,
                ];
            })
            ->filter(fn (array $row): bool => $row['deficit'] > 0)
            ->values();

        $atReorderItems = Inventory::query()
            ->with('item')
            ->whereColumn('available_stock', '=', 'low_stock_threshold')
            ->orderByDesc('last_updated')
            ->limit(8)
            ->get()
            ->map(fn (Inventory $inventory): array => [
                'inventory_id' => (int) $inventory->inventory_id,
                'item_name' => (string) ($inventory->item?->item_name ?? 'Unassigned Item'),
                'available_stock' => (float) $inventory->available_stock,
                'low_stock_threshold' => (float) $inventory->low_stock_threshold,
            ])
            ->values();

        return Inertia::render('Inventory/ProcurementRequests/Index', [
            'purchaseRequests' => $purchaseRequests,
            'shortageCandidates' => $shortageCandidates,
            'lowStockItems' => $lowStockItems,
            'atReorderItems' => $atReorderItems,
            'focusInventoryId' => $focusInventoryId > 0 ? $focusInventoryId : null,
        ]);
    }

    public function processDistribution(Request $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.view-any'), 403);

        $supplyRequest->load(['items.item.inventory', 'purchaseRequests', 'distributionRecords']);

        if ($supplyRequest->distributionRecords->isNotEmpty()) {
            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->with('error', 'This request already has a distribution record and cannot be re-processed for release.');
        }

        if ($supplyRequest->purchaseRequests->isNotEmpty()) {
            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->with('error', 'This request is already queued for procurement and cannot be processed for direct distribution.');
        }

        $shortageTotal = 0.0;
        foreach ($supplyRequest->items as $item) {
            $requested = (float) $item->quantity_requested;
            $available = (float) ($item->item?->inventory?->available_stock ?? 0);
            $shortageTotal += max($requested - $available, 0);
        }

        if ($shortageTotal > 0) {
            return redirect()
                ->route('inventory.office-supply-requests.index', $request->query())
                ->with('error', 'Stock shortage detected. Queue this request for procurement initiation instead of distribution release.');
        }

        return redirect()
            ->route('inventory.distribution-records.create', ['supply_request_id' => (int) $supplyRequest->supply_request_id])
            ->with('success', 'Supply request opened in Supply Distribution workflow.');
    }

    public function queueProcurement(Request $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.view-any'), 403);

        $this->purchaseRequestService->queueFromSupplyRequest($request->user(), [
            'supply_request_id' => (int) $supplyRequest->supply_request_id,
            'remarks' => 'Queued by GSO due to stock shortage.',
        ]);

        return redirect()
            ->route('inventory.office-supply-requests.index', $request->query())
            ->with('success', 'Supply request queued for procurement processing.');
    }

    public function initiateLowStockProcurement(Request $request, Inventory $inventory): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.view-any'), 403);

        $validated = $request->validate([
            'quantity_requested' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $quantityRequested = isset($validated['quantity_requested'])
            ? (float) $validated['quantity_requested']
            : max((float) $inventory->low_stock_threshold - (float) $inventory->available_stock, 0);

        $queued = $this->purchaseRequestService->createFromLowStockSignal(
            actor: $request->user(),
            inventory: $inventory,
            quantityRequested: $quantityRequested
        );

        return redirect()
            ->route('inventory.procurement-requests.index', [
                'focus_inventory_id' => (int) $inventory->inventory_id,
            ])
            ->with('success', sprintf(
                'Purchase request PR-%06d queued from low-stock signal for %s (Qty %.2f).',
                (int) $queued->purchase_request_id,
                (string) ($inventory->item?->item_name ?? 'selected item'),
                $quantityRequested
            ));
    }

    private function computeItemShortage(SupplyRequest $supplyRequest, int $itemId): float
    {
        $requestedForItem = (float) $supplyRequest->items
            ->where('item_id', $itemId)
            ->sum('quantity_requested');

        if ($requestedForItem <= 0) {
            return 0.0;
        }

        $available = (float) ($supplyRequest->items
            ->firstWhere('item_id', $itemId)?->item?->inventory?->available_stock ?? 0);

        return max($requestedForItem - $available, 0);
    }
}
