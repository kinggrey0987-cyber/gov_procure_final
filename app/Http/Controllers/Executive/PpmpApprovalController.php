<?php

namespace App\Http\Controllers\Executive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Executive\UpdatePpmpApprovalStatusRequest;
use App\Models\Office;
use App\Models\Ppmp;
use App\Support\DashboardStatusGroups;
use App\Services\Executive\PpmpApprovalService;
use App\Services\History\HistoryTimelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PpmpApprovalController extends Controller
{
    public function __construct(
        private readonly PpmpApprovalService $approvalService,
        private readonly HistoryTimelineService $historyTimelineService,
    ) {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('executive.ppmp-approval.view-any'), 403);

        $filters = $request->validate([
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'status' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'digits:4'],
            'month' => ['nullable', 'string', 'regex:/^(?:0?[1-9]|1[0-2]|\d{4}-\d{2})$/'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        $query = Ppmp::query()
            ->with(['office:id,name', 'preparer:id,name', 'approver:id,name'])
            ->withCount('items')
            ->withMax([
                'statusHistory as submitted_at' => static fn ($historyQuery) => $historyQuery->where('new_status', 'SUBMITTED'),
            ], 'changed_at')
            ->whereIn('status', ['SUBMITTED', 'APPROVED', 'REJECTED', 'RETURNED_FOR_REVISION']);

        if (! empty($filters['office_id'])) {
            $query->where('office_id', (int) $filters['office_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        if (! empty($filters['year'])) {
            $query->whereYear('created_at', (int) $filters['year']);
        }

        if (! empty($filters['month'])) {
            $query->whereMonth('created_at', (int) $filters['month']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', (string) $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', (string) $filters['to_date']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($searchQuery) use ($search): void {
                if (preg_match('/^PPMP-?(\d+)$/i', $search, $matches) === 1) {
                    $searchQuery->orWhere('ppmp_id', (int) $matches[1]);
                }

                if (ctype_digit($search)) {
                    $searchQuery->orWhere('ppmp_id', (int) $search);
                }

                $searchQuery
                    ->orWhereLikeInsensitive('remarks', $search)
                    ->orWhereHas('office', static fn ($officeQuery) => $officeQuery->whereLikeInsensitive('name', $search))
                    ->orWhereHas('preparer', static fn ($preparerQuery) => $preparerQuery->whereLikeInsensitive('name', $search));
            });
        }

        $summary = [
            'pending_ppmp_approvals' => Ppmp::query()
                ->where(function ($query): void {
                    foreach (DashboardStatusGroups::executivePendingPpmpStatuses() as $index => $status) {
                        if ($index === 0) {
                            $query->where('status', $status);

                            continue;
                        }

                        $query->orWhere('status', $status);
                    }
                })
                ->count(),
            'returned_for_revision' => Ppmp::query()->where('status', 'RETURNED_FOR_REVISION')->count(),
            'recently_approved' => Ppmp::query()->where('status', 'APPROVED')->whereDate('updated_at', '>=', now()->subDays(30)->toDateString())->count(),
            'rejected' => Ppmp::query()->where('status', 'REJECTED')->count(),
        ];

        $officeBreakdown = Office::query()
            ->requestingOffices()
            ->select(['offices.id as office_id', 'offices.name as office_name'])
            ->withCount([
                'ppmps as total' => function ($query): void {
                    $query->where(function ($statusQuery): void {
                        $statusQuery->where('status', 'SUBMITTED')
                            ->orWhere('status', 'APPROVED')
                            ->orWhere('status', 'REJECTED')
                            ->orWhere('status', 'RETURNED_FOR_REVISION');
                    });
                },
            ])
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $mapApprovalRecord = function (Ppmp $record) use ($request): array {
            return [
                'ppmp_id' => (int) $record->ppmp_id,
                'office' => $record->office ? [
                    'id' => (int) $record->office->id,
                    'name' => (string) $record->office->name,
                ] : null,
                'preparer' => $record->preparer ? [
                    'id' => (int) $record->preparer->id,
                    'name' => (string) $record->preparer->name,
                ] : null,
                'fiscal_year' => (int) $record->fiscal_year,
                'total_estimated_budget' => (float) $record->total_estimated_budget,
                'status' => (string) $record->status,
                'date_prepared' => optional($record->date_prepared)?->toDateString(),
                'submitted_at' => $this->ppmpSubmittedAt($record),
                'items_count' => (int) $record->items_count,
                'can_signoff' => $request->user()?->can('executive.ppmp-approval.signoff', $record) ?? false,
            ];
        };

        $records = $query->latest('ppmp_id')->paginate(5)->withQueryString();
        $records->through($mapApprovalRecord);

        $recordsExport = (clone $query)
            ->latest('ppmp_id')
            ->get()
            ->map($mapApprovalRecord)
            ->values();

        return Inertia::render('Executive/Approvals/Index', [
            'records' => $records,
            'recordsExport' => $recordsExport,
            'summary' => $summary,
            'filters' => [
                'office_id' => $filters['office_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'year' => $filters['year'] ?? null,
                'month' => $filters['month'] ?? null,
                'from_date' => $filters['from_date'] ?? null,
                'to_date' => $filters['to_date'] ?? null,
                'search' => $filters['search'] ?? null,
            ],
            'filter_options' => [
                'offices' => Office::query()->requestingOffices()->select(['id', 'name'])->orderBy('name', 'asc')->get(),
                'statuses' => ['SUBMITTED', 'APPROVED', 'REJECTED', 'RETURNED_FOR_REVISION'],
                'years' => Ppmp::query()
                    ->where('date_prepared', '!=', null)
                    ->latest('date_prepared')
                    ->pluck('date_prepared')
                    ->map(fn ($value): int => (int) $value->year)
                    ->unique()
                    ->values(),
            ],
            'office_breakdown' => $officeBreakdown,
        ]);
    }

    public function show(Request $request, Ppmp $ppmp): Response
    {
        abort_unless($request->user()?->can('executive.ppmp-approval.view', $ppmp), 403);

        $ppmp->load([
            'office:id,name',
            'preparer:id,name',
            'approver:id,name',
            'items:ppmp_item_id,ppmp_id,item_id,item_category_id,quantity,unit_cost,estimated_budget,procurement_schedule,remarks',
            'items.item:item_id,item_name',
            'items.itemCategory:item_category_id,category_name',
        ]);

        $history = $this->historyTimelineService->forRecord($request->user(), $ppmp);
        $categorySummary = $this->approvalCategorySummary($ppmp);
        $submittedAt = $this->ppmpSubmittedAt($ppmp);
        $workflowTimeline = $this->approvalWorkflowTimeline($history['status']);

        return Inertia::render('Executive/Approvals/Show', [
            'ppmp' => [
                'ppmp_id' => (int) $ppmp->ppmp_id,
                'reference_code' => sprintf('PPMP-%d', (int) $ppmp->ppmp_id),
                'office' => $ppmp->office ? [
                    'id' => (int) $ppmp->office->id,
                    'name' => (string) $ppmp->office->name,
                ] : null,
                'preparer' => $ppmp->preparer ? [
                    'id' => (int) $ppmp->preparer->id,
                    'name' => (string) $ppmp->preparer->name,
                ] : null,
                'approver' => $ppmp->approver ? [
                    'id' => (int) $ppmp->approver->id,
                    'name' => (string) $ppmp->approver->name,
                ] : null,
                'status' => (string) $ppmp->status,
                'fiscal_year' => (int) $ppmp->fiscal_year,
                'fund_source' => $ppmp->fund_source instanceof \BackedEnum
                    ? $ppmp->fund_source->value
                    : (string) $ppmp->fund_source,
                'date_prepared' => optional($ppmp->date_prepared)?->toDateString(),
                'submitted_at' => $submittedAt,
                'date_approved' => optional($ppmp->date_approved)?->toDateString(),
                'total_estimated_budget' => (float) $ppmp->total_estimated_budget,
                'remarks' => $ppmp->remarks,
                'items_count' => $ppmp->items->count(),
                'items' => $ppmp->items->map(fn ($item): array => [
                    'ppmp_item_id' => (int) $item->ppmp_item_id,
                    'item_name' => $item->item?->item_name ?? sprintf('Item #%d', (int) $item->item_id),
                    'category_name' => $item->itemCategory?->category_name ?? sprintf('Category #%d', (int) $item->item_category_id),
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => (float) $item->unit_cost,
                    'estimated_budget' => (float) $item->estimated_budget,
                    'procurement_schedule' => $item->procurement_schedule,
                    'remarks' => $item->remarks,
                ])->values(),
            ],
            'categorySummary' => $categorySummary,
            'workflowTimeline' => $workflowTimeline,
            'canSignoff' => $request->user()?->can('executive.ppmp-approval.signoff', $ppmp) ?? false,
        ]);
    }

    private function approvalCategorySummary(Ppmp $ppmp)
    {
        return collect($ppmp->items)
            ->groupBy('item_category_id')
            ->map(fn ($items, $categoryId): array => [
                'item_category_id' => (int) $categoryId,
                'category_name' => $items->first()?->itemCategory?->category_name ?? sprintf('Category #%d', (int) $categoryId),
                'items_count' => $items->count(),
                'estimated_budget_total' => (float) $items->sum('estimated_budget'),
            ])
            ->sortByDesc('estimated_budget_total')
            ->values();
    }

    private function ppmpSubmittedAt(Ppmp $ppmp): ?string
    {
        $submittedAt = $ppmp->getAttribute('submitted_at')
            ?: DB::table('status_history')
            ->where('reference_type', Ppmp::class)
            ->where('reference_id', (int) $ppmp->ppmp_id)
            ->where('new_status', 'SUBMITTED')
            ->max('changed_at');

        if ($submittedAt) {
            return (string) $submittedAt;
        }

        if (in_array((string) $ppmp->status, ['SUBMITTED', 'APPROVED', 'REJECTED', 'RETURNED_FOR_REVISION'], true)) {
            return optional($ppmp->created_at)?->toIso8601String();
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $statusHistory
     */
    private function approvalWorkflowTimeline($statusHistory)
    {
        return $statusHistory
            ->filter(function ($entry): bool {
                $newStatus = strtoupper((string) ($entry->new_status ?? ''));
                $oldStatus = strtoupper((string) ($entry->old_status ?? ''));

                if ($newStatus === '') {
                    return false;
                }

                if ($newStatus === 'DRAFT') {
                    return $oldStatus === '';
                }

                if ($oldStatus === $newStatus) {
                    return false;
                }

                return in_array($newStatus, ['SUBMITTED', 'APPROVED', 'REJECTED', 'RETURNED_FOR_REVISION'], true);
            })
            ->map(fn ($entry): array => [
                'id' => (int) $entry->id,
                'old_status' => $entry->old_status,
                'new_status' => (string) $entry->new_status,
                'remarks' => $entry->remarks,
                'changed_at' => optional($entry->changed_at)?->toIso8601String(),
                'changed_by' => $entry->changedBy ? [
                    'id' => (int) $entry->changedBy->id,
                    'name' => (string) $entry->changedBy->name,
                ] : null,
            ])
            ->values();
    }

    public function updateStatus(UpdatePpmpApprovalStatusRequest $request, Ppmp $ppmp): RedirectResponse
    {
        abort_unless($request->user()?->can('executive.ppmp-approval.signoff', $ppmp), 403);

        $this->approvalService->updateStatus(
            ppmp: $ppmp,
            actor: $request->user(),
            newStatus: (string) $request->validated('status'),
            remarks: $request->validated('remarks'),
        );

        return redirect()->route('executive.ppmp-approvals.show', $ppmp->ppmp_id)
            ->with('success', 'PPMP executive decision recorded successfully.');
    }
}
