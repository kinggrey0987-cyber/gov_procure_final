<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Requests\Office\StorePpmpRequest;
use App\Http\Requests\Office\SubmitPpmpRequest;
use App\Http\Requests\Office\UpdatePpmpRequest;
use App\Models\Item;
use App\Models\Ppmp;
use App\Services\History\HistoryTimelineService;
use App\Services\Office\PpmpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PpmpController extends Controller
{
    public function __construct(
        private readonly PpmpService $service,
        private readonly HistoryTimelineService $historyTimelineService,
    ) {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('office.ppmp.view-any'), 403);

        $status = strtoupper(trim((string) $request->input('status', '')));
        $month = trim((string) $request->input('month', ''));

        $ppmps = Ppmp::query()
            ->forOffice((int) $request->user()?->office_id)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when(preg_match('/^\d{4}-\d{2}$/', $month) === 1, function ($query) use ($month): void {
                [$year, $monthNumber] = array_map('intval', explode('-', $month));
                $query->whereYear('date_prepared', $year)->whereMonth('date_prepared', $monthNumber);
            })
            ->latest('ppmp_id')
            ->paginate(5)
            ->through(fn (Ppmp $ppmp): array => [
                'ppmp_id' => (int) $ppmp->ppmp_id,
                'ppmp_code' => sprintf('PPMP-%d-%04d', (int) $ppmp->fiscal_year, (int) $ppmp->ppmp_id),
                'fiscal_year' => (int) $ppmp->fiscal_year,
                'status' => (string) $ppmp->status,
                'last_updated' => optional($ppmp->updated_at)?->toIso8601String(),
                'date_prepared' => optional($ppmp->date_prepared)?->toDateString(),
            ]);

        return Inertia::render('Office/Ppmp/Index', [
            'ppmps' => $ppmps,
            'filters' => [
                'status' => $status,
                'month' => $month,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Office/Ppmp/Create', [
            'itemOptions' => $this->itemOptions(),
        ]);
    }

    public function store(StorePpmpRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $ppmp = $this->service->create($request->user(), $payload);

        $items = $payload['items'] ?? [];
        foreach ($items as $item) {
            $this->service->addItem($ppmp, $request->user(), $item);
        }

        return redirect()->route('office.ppmp.show', $ppmp->ppmp_id)
            ->with('success', 'PPMP created successfully.');
    }

    public function show(Request $request, Ppmp $ppmp): Response
    {
        abort_unless($request->user()?->can('office.ppmp.view', $ppmp), 403);

        $canEdit = $request->user()?->can('office.ppmp.update', $ppmp) ?? false;
        $canSubmit = $request->user()?->can('office.ppmp.submit', $ppmp) ?? false;
        $history = $this->historyTimelineService->forRecord($request->user(), $ppmp);

        return Inertia::render('Office/Ppmp/Show', [
            'ppmp' => $ppmp->load(['items.item', 'items.itemCategory', 'office', 'preparer', 'approver']),
            'canEdit' => $canEdit,
            'canSubmit' => $canSubmit,
            'itemOptions' => $this->itemOptions(),
            'statusHistory' => $history['status']->map(fn ($entry): array => [
                'id' => (int) $entry->id,
                'old_status' => $entry->old_status,
                'new_status' => (string) $entry->new_status,
                'remarks' => $entry->remarks,
                'changed_at' => optional($entry->changed_at)?->toIso8601String(),
                'changed_by' => $entry->changedBy ? [
                    'id' => (int) $entry->changedBy->id,
                    'name' => (string) $entry->changedBy->name,
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
                ] : null,
            ])->values(),
        ]);
    }

    public function edit(Request $request, Ppmp $ppmp): Response
    {
        abort_unless($request->user()?->can('office.ppmp.update', $ppmp), 403);

        return Inertia::render('Office/Ppmp/Edit', [
            'ppmp' => $ppmp->load(['items.item', 'items.itemCategory']),
            'itemOptions' => $this->itemOptions(),
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

    private function itemOptions(): array
    {
        return Item::query()
            ->with('category:item_category_id,category_name')
            ->orderBy('item_name')
            ->get(['item_id', 'item_category_id', 'item_name'])
            ->map(fn (Item $item): array => [
                'item_id' => (int) $item->item_id,
                'item_name' => (string) $item->item_name,
                'item_category_id' => (int) $item->item_category_id,
                'item_category_name' => (string) ($item->category?->category_name ?? 'Uncategorized'),
            ])
            ->values()
            ->all();
    }
}
