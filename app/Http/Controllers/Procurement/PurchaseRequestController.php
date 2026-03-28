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

        $purchaseRequests = PurchaseRequest::query()
            ->with(['office', 'requester', 'supplyRequest', 'items.item.category'])
            ->withCount('items')
            ->withSum('items', 'quantity_requested')
            ->latest('purchase_request_id')
            ->paginate(15);

        return Inertia::render('Procurement/PurchaseRequests/Index', [
            'purchaseRequests' => $purchaseRequests,
        ]);
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        abort_unless($request->user()?->can('procurement.purchase-request.view', $purchaseRequest), 403);

        return Inertia::render('Procurement/PurchaseRequests/Show', [
            'purchaseRequest' => $purchaseRequest->load(['items', 'office', 'requester', 'supplyRequest']),
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

        $this->service->process($purchaseRequest, $request->user(), $request->input('remarks'));

        return redirect()->route('procurement.purchase-requests.show', $purchaseRequest->purchase_request_id)
            ->with('success', 'Purchase request moved to processing successfully.');
    }
}
