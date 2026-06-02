<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\ProcessPurchaseRequestRequest;
use App\Http\Requests\Procurement\StorePurchaseRequestRequest;
use App\Http\Requests\Procurement\UpdatePurchaseRequestRequest;
use App\Models\PurchaseRequest;
use App\Models\SupplyRequest;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestController extends Controller
{
    public function __construct(private readonly PurchaseRequestService $service)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-request.view-any'), 403);

        $status = strtoupper(trim((string) $request->input('status', '')));
        $search = trim((string) $request->input('search', ''));

        $purchaseRequests = PurchaseRequest::query()
            ->with(['office', 'requester', 'supplyRequest', 'items.item.category'])
            ->withCount('items')
            ->withSum('items', 'quantity_requested')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('purchase_request_id', 'like', '%'.$search.'%')
                        ->orWhereHas('supplyRequest', fn ($supplyRequestQuery) => $supplyRequestQuery->whereLikeInsensitive('request_code', $search))
                        ->orWhereHas('office', fn ($officeQuery) => $officeQuery->whereLikeInsensitive('name', $search));
                });
            })
            ->latest('purchase_request_id')
            ->paginate(5)
            ->withQueryString();

        return Inertia::render('Procurement/PurchaseRequests/Index', [
            'purchaseRequests' => $purchaseRequests,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-request.view', $purchaseRequest), 403);

        $mode = strtolower(trim((string) $request->input('mode', 'view')));
        $isReviewMode = $mode === 'review';
        $isExecutiveRoute = $request->routeIs('executive.procurement-records.*');

        return Inertia::render('Procurement/PurchaseRequests/Show', [
            'purchaseRequest' => $purchaseRequest->load([
                'items.item.category',
                'office',
                'requester',
                'supplyRequest.requester',
            ]),
            'reviewMode' => $isReviewMode,
            'backFilters' => [
                'status' => trim((string) $request->input('status', '')),
                'search' => trim((string) $request->input('search', '')),
            ],
            'permissions' => [
                'process' => $request->user()?->can('procurement.purchase-request.process', $purchaseRequest) ?? false,
            ],
            'backLink' => $isExecutiveRoute ? [
                'href' => route('executive.procurement-analytics'),
                'label' => 'Back to Procurement Insights',
            ] : [
                'href' => route('procurement.purchase-requests.index', [
                    'status' => trim((string) $request->input('status', '')) ?: null,
                    'search' => trim((string) $request->input('search', '')) ?: null,
                ]),
                'label' => 'Back to Request Review',
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-request.create'), 403);

        $eligibleSupplyRequests = SupplyRequest::query()
            ->submitted()
            ->whereDoesntHave('purchaseRequests')
            ->latest('supply_request_id')
            ->get(['supply_request_id', 'request_code', 'office_id', 'requested_by', 'status']);

        return Inertia::render('Procurement/PurchaseRequests/Create', [
            'eligibleSupplyRequests' => $eligibleSupplyRequests,
        ]);
    }

    public function store(StorePurchaseRequestRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.purchase-request.create'), 403);

        $purchaseRequest = $this->service->createFromSupplyRequest($request->user(), $request->validated());

        return redirect()->route('procurement.purchase-requests.show', $purchaseRequest->purchase_request_id)
            ->with('success', 'Purchase request created successfully.');
    }

    public function edit(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-request.update', $purchaseRequest), 403);

        return Inertia::render('Procurement/PurchaseRequests/Edit', [
            'purchaseRequest' => $purchaseRequest,
        ]);
    }

    public function update(UpdatePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.purchase-request.update', $purchaseRequest), 403);

        $this->service->update($purchaseRequest, $request->user(), $request->validated());

        return redirect()->route('procurement.purchase-requests.show', $purchaseRequest->purchase_request_id)
            ->with('success', 'Purchase request updated successfully.');
    }

    public function process(ProcessPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.purchase-request.process', $purchaseRequest), 403);

        $action = (string) $request->input('action', 'process');
        $remarks = $request->input('remarks');

        if ($action === 'return') {
            if (! is_string($remarks)) {
                throw ValidationException::withMessages([
                    'remarks' => 'Remarks are required when returning for revision.',
                ]);
            }

            $this->service->returnForRevision($purchaseRequest, $request->user(), $remarks);

            return redirect()->route('procurement.purchase-requests.show', [
                'purchase_request' => $purchaseRequest->purchase_request_id,
                'mode' => 'review',
            ])->with('success', 'Purchase request returned for revision successfully.');
        }

        if ($action === 'reject') {
            if (! is_string($remarks)) {
                throw ValidationException::withMessages([
                    'remarks' => 'Remarks are required when rejecting a request.',
                ]);
            }

            $this->service->reject($purchaseRequest, $request->user(), $remarks);

            return redirect()->route('procurement.purchase-requests.show', [
                'purchase_request' => $purchaseRequest->purchase_request_id,
                'mode' => 'review',
            ])->with('success', 'Purchase request rejected successfully.');
        }

        $this->service->process($purchaseRequest, $request->user(), $remarks);

        return redirect()->route('procurement.purchase-requests.show', [
            'purchase_request' => $purchaseRequest->purchase_request_id,
            'mode' => 'review',
        ])
            ->with('success', 'Purchase request moved to processing successfully.');
    }
}
