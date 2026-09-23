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
        $year = trim((string) $request->input('year', ''));
        $month = trim((string) $request->input('month', ''));

        $officeId = (int) $request->user()?->office_id;

        $yearOptions = Ppmp::query()
            ->forOffice($officeId)
            ->where('date_prepared', '!=', null)
            ->latest('date_prepared')
            ->pluck('date_prepared')
            ->map(fn ($value): int => (int) $value->year)
            ->unique()
            ->values();

        $ppmps = Ppmp::query()
            ->forOffice($officeId)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when(preg_match('/^\d{4}$/', $year) === 1, fn ($query) => $query->whereYear('date_prepared', (int) $year))
            ->when(preg_match('/^\d{1,2}$/', $month) === 1, function ($query) use ($month): void {
                $query->whereMonth('date_prepared', (int) $month);
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
                'year' => $year,
                'month' => $month,
            ],
            'filter_options' => [
                'years' => $yearOptions,
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
                    'role' => (string) ($entry->changedBy->role?->name ?? ''),
                    'office' => (string) ($entry->changedBy->office?->name ?? ''),
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
                    'role' => (string) ($entry->approver->role?->name ?? ''),
                    'office' => (string) ($entry->approver->office?->name ?? ''),
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

    public function destroy(Request $request, Ppmp $ppmp): RedirectResponse
    {
        abort_unless($request->user()?->can('office.ppmp.delete', $ppmp), 403);

        $this->service->deleteDraft($ppmp, $request->user());

        return redirect()->route('office.ppmp.index')
            ->with('success', 'Draft PPMP deleted successfully.');
    }

    private function itemOptions(): array
    {
        return Item::query()
            ->with([
                'category:item_category_id,category_name',
                'specifications:item_specification_id,item_id,specification',
            ])
            ->orderBy('item_name', 'asc')
            ->get(['item_id', 'item_category_id', 'item_name', 'default_unit', 'pieces_per_box', 'sub_unit'])
            ->map(fn (Item $item): array => [
                'item_id' => (int) $item->item_id,
                'item_name' => (string) $item->item_name,
                'item_category_id' => (int) $item->item_category_id,
                'item_category_name' => (string) ($item->category?->category_name ?? 'Uncategorized'),
                'default_unit' => (string) ($item->default_unit ?? ''),
                'pieces_per_box' => (int) ($item->pieces_per_box ?? 1),
                'sub_unit' => (string) ($item->sub_unit ?? 'piece'),
                'specifications' => $item->specifications
                    ->map(fn ($spec): array => [
                        'item_specification_id' => (int) $spec->item_specification_id,
                        'specification' => (string) $spec->specification,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
