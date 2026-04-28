<?php

namespace App\Http\Controllers\Executive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Executive\UpdatePpmpApprovalStatusRequest;
use App\Models\Office;
use App\Models\Ppmp;
use App\Support\DashboardStatusGroups;
use App\Services\Documents\DocumentService;
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
        private readonly DocumentService $documentService,
    ) {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('executive.ppmp-approval.view-any'), 403);

        $filters = $request->validate([
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'status' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'digits:4'],
            'month' => ['nullable', 'date_format:Y-m'],
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
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('office', static fn ($officeQuery) => $officeQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('preparer', static fn ($preparerQuery) => $preparerQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $summary = [
            'pending_ppmp_approvals' => Ppmp::query()
                ->whereIn('status', DashboardStatusGroups::executivePendingPpmpStatuses())
                ->count(),
            'returned_for_revision' => Ppmp::query()->where('status', 'RETURNED_FOR_REVISION')->count(),
            'recently_approved' => Ppmp::query()->where('status', 'APPROVED')->whereDate('updated_at', '>=', now()->subDays(30)->toDateString())->count(),
            'rejected' => Ppmp::query()->where('status', 'REJECTED')->count(),
        ];

        $officeBreakdown = Ppmp::query()
            ->join('offices', 'offices.id', '=', 'ppmp.office_id')
            ->whereIn('ppmp.status', ['SUBMITTED', 'APPROVED', 'REJECTED', 'RETURNED_FOR_REVISION'])
            ->selectRaw('offices.id as office_id, offices.name as office_name, COUNT(*) as total')
            ->groupBy('offices.id', 'offices.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $records = $query->latest('ppmp_id')->paginate(5)->withQueryString();
        $records->through(function (Ppmp $record) use ($request): array {
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
                'submitted_at' => $record->submitted_at,
                'items_count' => (int) $record->items_count,
                'can_signoff' => $request->user()?->can('executive.ppmp-approval.signoff', $record) ?? false,
            ];
        });

        return Inertia::render('Executive/Approvals/Index', [
            'records' => $records,
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
                'offices' => Office::query()->select(['id', 'name'])->orderBy('name')->get(),
                'statuses' => ['SUBMITTED', 'APPROVED', 'REJECTED', 'RETURNED_FOR_REVISION'],
                'years' => Ppmp::query()
                    ->where('date_prepared', '!=', null)
                    ->selectRaw('DISTINCT YEAR(date_prepared) as year')
                    ->orderByDesc('year')
                    ->pluck('year')
                    ->map(fn ($value): int => (int) $value)
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
            'items:ppmp_item_id,ppmp_id,item_id,item_category_id,quantity,unit_cost,estimated_budget,procurement_schedule,fund_source,remarks',
        ]);

        $history = $this->historyTimelineService->forRecord($request->user(), $ppmp);

        $attachments = $this->documentService
            ->listForRelated($request->user(), 'ppmp', (int) $ppmp->ppmp_id)
            ->map(fn ($document): array => [
                'document_id' => (int) $document->document_id,
                'title' => (string) $document->title,
                'file_name' => (string) $document->file_name,
                'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
                'uploaded_by' => (int) $document->uploaded_by,
                'uploader_name' => $document->uploader?->name,
            ])->values();

        $categorySummary = collect($ppmp->items)
            ->groupBy('item_category_id')
            ->map(fn ($items, $categoryId): array => [
                'item_category_id' => (int) $categoryId,
                'items_count' => $items->count(),
                'estimated_budget_total' => (float) $items->sum('estimated_budget'),
            ])
            ->sortByDesc('estimated_budget_total')
            ->values();

        $submittedAt = DB::table('status_history')
            ->where('reference_type', Ppmp::class)
            ->where('reference_id', (int) $ppmp->ppmp_id)
            ->where('new_status', 'SUBMITTED')
            ->max('changed_at');

        $workflowTimeline = $history['status']
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
                'date_prepared' => optional($ppmp->date_prepared)?->toDateString(),
                'submitted_at' => $submittedAt,
                'date_approved' => optional($ppmp->date_approved)?->toDateString(),
                'total_estimated_budget' => (float) $ppmp->total_estimated_budget,
                'remarks' => $ppmp->remarks,
                'items_count' => $ppmp->items->count(),
            ],
            'categorySummary' => $categorySummary,
            'attachments' => $attachments,
            'attachmentPermissions' => [
                'upload' => false,
                'delete' => false,
            ],
            'workflowTimeline' => $workflowTimeline,
            'canSignoff' => $request->user()?->can('executive.ppmp-approval.signoff', $ppmp) ?? false,
        ]);
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
