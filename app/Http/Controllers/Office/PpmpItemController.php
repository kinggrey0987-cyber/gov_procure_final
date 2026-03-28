<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Requests\Office\StorePpmpItemRequest;
use App\Http\Requests\Office\UpdatePpmpItemRequest;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Services\Office\PpmpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PpmpItemController extends Controller
{
    public function __construct(private readonly PpmpService $service)
    {
    }

    public function index(Request $request, Ppmp $ppmp): Response
    {
        abort_unless($request->user()?->can('office.ppmp.view', $ppmp), 403);

        return Inertia::render('Office/PpmpItems/Index', [
            'ppmp' => $ppmp,
            'items' => $ppmp->items()->latest('ppmp_item_id')->paginate(20),
        ]);
    }

    public function store(StorePpmpItemRequest $request, Ppmp $ppmp): RedirectResponse
    {
        $this->service->addItem($ppmp, $request->user(), $request->validated());

        return redirect()->route('office.ppmp.items.index', $ppmp->ppmp_id)
            ->with('success', 'PPMP item added.');
    }

    public function update(UpdatePpmpItemRequest $request, Ppmp $ppmp, PpmpItem $ppmpItem): RedirectResponse
    {
        $this->service->updateItem($ppmp, $ppmpItem, $request->user(), $request->validated());

        return redirect()->route('office.ppmp.items.index', $ppmp->ppmp_id)
            ->with('success', 'PPMP item updated.');
    }
}
