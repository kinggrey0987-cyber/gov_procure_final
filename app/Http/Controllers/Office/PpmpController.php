<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Requests\Office\StorePpmpRequest;
use App\Http\Requests\Office\SubmitPpmpRequest;
use App\Http\Requests\Office\UpdatePpmpRequest;
use App\Models\Ppmp;
use App\Services\Office\PpmpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PpmpController extends Controller
{
    public function __construct(private readonly PpmpService $service)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('office.ppmp.view-any'), 403);

        $ppmps = Ppmp::query()
            ->forOffice((int) $request->user()?->office_id)
            ->latest('ppmp_id')
            ->paginate(15);

        return Inertia::render('Office/Ppmp/Index', [
            'ppmps' => $ppmps,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Office/Ppmp/Create');
    }

    public function store(StorePpmpRequest $request): RedirectResponse
    {
        $ppmp = $this->service->create($request->user(), $request->validated());

        return redirect()->route('office.ppmp.show', $ppmp->ppmp_id)
            ->with('success', 'PPMP created successfully.');
    }

    public function show(Request $request, Ppmp $ppmp): Response
    {
        abort_unless($request->user()?->can('office.ppmp.view', $ppmp), 403);

        return Inertia::render('Office/Ppmp/Show', [
            'ppmp' => $ppmp->load(['items', 'office', 'preparer', 'approver']),
        ]);
    }

    public function edit(Request $request, Ppmp $ppmp): Response
    {
        abort_unless($request->user()?->can('office.ppmp.update', $ppmp), 403);

        return Inertia::render('Office/Ppmp/Edit', [
            'ppmp' => $ppmp,
        ]);
    }

    public function update(UpdatePpmpRequest $request, Ppmp $ppmp): RedirectResponse
    {
        $this->service->update($ppmp, $request->user(), $request->validated());

        return redirect()->route('office.ppmp.show', $ppmp->ppmp_id)
            ->with('success', 'PPMP updated successfully.');
    }

    public function submit(SubmitPpmpRequest $request, Ppmp $ppmp): RedirectResponse
    {
        $this->service->submit($ppmp, $request->user(), $request->input('remarks'));

        return redirect()->route('office.ppmp.show', $ppmp->ppmp_id)
            ->with('success', 'PPMP submitted successfully.');
    }
}
