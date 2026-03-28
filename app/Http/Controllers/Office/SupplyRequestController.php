<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Requests\Office\StoreSupplyRequestRequest;
use App\Http\Requests\Office\SubmitSupplyRequestRequest;
use App\Http\Requests\Office\UpdateSupplyRequestRequest;
use App\Models\SupplyRequest;
use App\Services\Office\SupplyRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplyRequestController extends Controller
{
    public function __construct(private readonly SupplyRequestService $service)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('office.supply-request.view-any'), 403);

        $supplyRequests = SupplyRequest::query()
            ->forOffice((int) $request->user()?->office_id)
            ->latest('supply_request_id')
            ->paginate(15);

        return Inertia::render('Office/SupplyRequests/Index', [
            'supplyRequests' => $supplyRequests,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Office/SupplyRequests/Create');
    }

    public function store(StoreSupplyRequestRequest $request): RedirectResponse
    {
        $supplyRequest = $this->service->create($request->user(), $request->validated());

        return redirect()->route('office.supply-requests.show', $supplyRequest->supply_request_id)
            ->with('success', 'Supply request created successfully.');
    }

    public function show(Request $request, SupplyRequest $supplyRequest): Response
    {
        abort_unless($request->user()?->can('office.supply-request.view', $supplyRequest), 403);

        return Inertia::render('Office/SupplyRequests/Show', [
            'supplyRequest' => $supplyRequest->load(['items', 'office', 'requester', 'approver']),
        ]);
    }

    public function edit(Request $request, SupplyRequest $supplyRequest): Response
    {
        abort_unless($request->user()?->can('office.supply-request.update', $supplyRequest), 403);

        return Inertia::render('Office/SupplyRequests/Edit', [
            'supplyRequest' => $supplyRequest,
        ]);
    }

    public function update(UpdateSupplyRequestRequest $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        $this->service->update($supplyRequest, $request->user(), $request->validated());

        return redirect()->route('office.supply-requests.show', $supplyRequest->supply_request_id)
            ->with('success', 'Supply request updated successfully.');
    }

    public function submit(SubmitSupplyRequestRequest $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        $this->service->submit($supplyRequest, $request->user(), $request->input('review_notes'));

        return redirect()->route('office.supply-requests.show', $supplyRequest->supply_request_id)
            ->with('success', 'Supply request submitted successfully.');
    }
}
