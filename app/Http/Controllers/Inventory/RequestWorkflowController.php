<?php

namespace App\Http\Controllers\Inventory;

use App\Models\Inventory;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\Delivery;
use App\Models\SupplyRequest;
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

            foreach ($request->items as $item) {
                $requested = (float) $item->quantity_requested;
                $available = (float) ($item->item?->inventory?->available_stock ?? 0);

                $requestedTotal += $requested;
                $shortageTotal += max($requested - $available, 0);
            }

            $hasPurchaseRequest = $request->purchaseRequests->isNotEmpty();
            $stockAssessment = $shortageTotal > 0 ? 'SHORTAGE_REQUIRES_PROCUREMENT' : 'STOCK_READY';
            $hasDistributionCandidate = isset($distributionReadyLookup[(int) $request->supply_request_id]);

            $workflowBucket = 'UNDER_VALIDATION';
            if ($hasPurchaseRequest) {
                $workflowBucket = 'QUEUED_FOR_PROCUREMENT';
            } elseif ($stockAssessment === 'STOCK_READY' && $hasDistributionCandidate) {
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

    public function procurementRequests(): Response
    {
        abort_unless(Auth::user()?->can('inventory.view-any'), 403);

        $focusInventoryId = (int) request()->query('focus_inventory_id', 0);

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
            ->map(fn (Inventory $inventory): array => [
                'inventory_id' => (int) $inventory->inventory_id,
                'item_name' => (string) ($inventory->item?->item_name ?? 'Unassigned Item'),
                'available_stock' => (float) $inventory->available_stock,
                'low_stock_threshold' => (float) $inventory->low_stock_threshold,
                'deficit' => round(max((float) $inventory->low_stock_threshold - (float) $inventory->available_stock, 0), 2),
            ])
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

        $supplyRequest->load(['items.item.inventory', 'purchaseRequests']);

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
}