<?php

namespace App\Http\Controllers\AdminTech;

use App\Http\Controllers\Controller;
use App\Http\Requests\Governance\StoreOfficeRequest;
use App\Http\Requests\Governance\UpdateOfficeRequest;
use App\Models\Office;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfficeManagementController extends Controller
{
    public function index(Request $request): Response
    {
        // Validate filter parameters
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        // Build query with optional filters
        $query = Office::query()
            ->withCount([
                'users',
                'ppmps',
                'supplyRequests',
                'purchaseRequests',
                'distributionRecords',
                'claims',
                'auditLogs',
            ])
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, function ($q, $status) {
                $isActive = $status === 'active';
                $q->where('is_active', $isActive);
            });

        $offices = $query
            ->orderBy('name')
            ->paginate(perPage: 5, page: $filters['page'] ?? 1)
            ->withQueryString()
            ->through(function (Office $office): array {
                $linkedCount =
                    $office->users_count +
                    $office->ppmps_count +
                    $office->supply_requests_count +
                    $office->purchase_requests_count +
                    $office->distribution_records_count +
                    $office->claims_count +
                    $office->audit_logs_count;

                $canHardDelete = $linkedCount === 0;
                $canDeleteAction = $canHardDelete || $office->is_active;

                return [
                    'id' => $office->id,
                    'name' => $office->name,
                    'code' => $office->code,
                    'users_count' => $office->users_count,
                    'is_active' => (bool) $office->is_active,
                    'can_hard_delete' => $canHardDelete,
                    'can_delete_action' => $canDeleteAction,
                    'delete_reason' => $canDeleteAction
                        ? null
                        : 'This office has linked records and is already inactive. It cannot be deleted.',
                ];
            });

        return Inertia::render('AdminTech/Offices/Index', [
            'offices' => $offices,
            'filters' => [
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('AdminTech/Offices/Create');
    }

    public function store(StoreOfficeRequest $request): RedirectResponse
    {
        Office::query()->create($request->validated());

        return to_route('admin-tech.offices.index')->with('success', 'Office created successfully.');
    }

    public function edit(Office $office): Response
    {
        return Inertia::render('AdminTech/Offices/Edit', [
            'office' => $office,
        ]);
    }

    public function update(UpdateOfficeRequest $request, Office $office): RedirectResponse
    {
        $office->update($request->validated());

        return to_route('admin-tech.offices.index')->with('success', 'Office updated successfully.');
    }

    public function destroy(Office $office): RedirectResponse
    {
        if ($this->hasLinkedRecords($office)) {
            if (! $office->is_active) {
                return back()->withErrors([
                    'delete' => 'This office has linked records and is already inactive. It cannot be deleted.',
                ]);
            }

            $office->forceFill(['is_active' => false])->save();

            return to_route('admin-tech.offices.index')->with('success', 'Office has linked records and was safely deactivated.');
        }

        try {
            $office->delete();
        } catch (QueryException) {
            return back()->withErrors([
                'delete' => 'This office cannot be deleted because it is linked to existing records.',
            ]);
        }

        return to_route('admin-tech.offices.index')->with('success', 'Office deleted successfully.');
    }

    private function hasLinkedRecords(Office $office): bool
    {
        return $office->users()->exists()
            || $office->ppmps()->exists()
            || $office->supplyRequests()->exists()
            || $office->purchaseRequests()->exists()
            || $office->distributionRecords()->exists()
            || $office->claims()->exists()
            || $office->auditLogs()->exists();
    }
}
