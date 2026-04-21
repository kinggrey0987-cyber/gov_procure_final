<?php

namespace App\Http\Controllers\Inventory;

use App\Models\Inventory;
use App\Models\Office;
use App\Models\PurchaseRequest;
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

        $rows = $rawRequests->map(function (SupplyRequest $request): array {
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

            $workflowBucket = 'UNDER_VALIDATION';
            if ($hasPurchaseRequest) {
                $workflowBucket = 'QUEUED_FOR_PROCUREMENT';
            } elseif ($stockAssessment === 'STOCK_READY' && (string) $request->status === 'SUBMITTED') {
                $workflowBucket = 'READY_FOR_DISTRIBUTION';
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
            ->lowStock()
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
            ->values();

        return Inertia::render('Inventory/ProcurementRequests/Index', [
            'purchaseRequests' => $purchaseRequests,
            'shortageCandidates' => $shortageCandidates,
            'lowStockItems' => $lowStockItems,
        ]);
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