<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AuditLogViewerService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function technical(User $actor, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        if (! $actor->can('audit.technical.view')) {
            throw ValidationException::withMessages([
                'authorization' => 'Not authorized for technical audit logs.',
            ]);
        }

        return $this->buildBaseQuery($filters)
            ->technical()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function business(User $actor, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        if (! $actor->can('audit.business.view')) {
            throw ValidationException::withMessages([
                'authorization' => 'Not authorized for business audit logs.',
            ]);
        }

        return $this->buildBaseQuery($filters)
            ->business()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, Collection>
     */
    public function businessAnalytics(User $actor, array $filters): array
    {
        if (! $actor->can('audit.business.view')) {
            throw ValidationException::withMessages([
                'authorization' => 'Not authorized for business audit analytics.',
            ]);
        }

        $baseQuery = $this->buildBaseQuery($filters)->business();

        $moduleSummary = (clone $baseQuery)
            ->selectRaw('module_name as label, COUNT(*) as total')
            ->groupBy('module_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $actionSummary = (clone $baseQuery)
            ->selectRaw('action_type as label, COUNT(*) as total')
            ->groupBy('action_type')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $dailyTrend = (clone $baseQuery)
            ->select('created_at')
            ->get()
            ->groupBy(static fn ($row): string => Carbon::parse($row->created_at)->format('Y-m-d'))
            ->map(static fn (Collection $rows, string $day): array => [
                'label' => $day,
                'total' => $rows->count(),
            ])
            ->sortBy('label')
            ->values();

        return [
            'module_summary' => $moduleSummary,
            'action_summary' => $actionSummary,
            'daily_trend' => $dailyTrend,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildBaseQuery(array $filters): Builder
    {
        return AuditLog::query()
            ->with(['user', 'office'])
            ->when(isset($filters['module_name']) && $filters['module_name'] !== '', fn (Builder $query): Builder => $query->where('module_name', 'like', '%'.$filters['module_name'].'%'))
            ->when(isset($filters['action_type']) && $filters['action_type'] !== '', fn (Builder $query): Builder => $query->where('action_type', 'like', '%'.$filters['action_type'].'%'))
            ->when(isset($filters['user_id']) && $filters['user_id'] !== null && $filters['user_id'] !== '', fn (Builder $query): Builder => $query->where('user_id', (int) $filters['user_id']))
            ->when(isset($filters['from_date']) && $filters['from_date'] !== '', fn (Builder $query): Builder => $query->whereDate('created_at', '>=', (string) $filters['from_date']))
            ->when(isset($filters['to_date']) && $filters['to_date'] !== '', fn (Builder $query): Builder => $query->whereDate('created_at', '<=', (string) $filters['to_date']));
    }
}
