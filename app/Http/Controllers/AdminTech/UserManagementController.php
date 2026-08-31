<?php

namespace App\Http\Controllers\AdminTech;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Governance\StoreInternalUserRequest;
use App\Http\Requests\Governance\UpdateInternalUserRequest;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    private const TECHNICAL_OFFICE_CODE = 'TECH_ADMIN';

    private const TECHNICAL_OFFICE_NAME = 'Technical Administration';

    private const TECHNICAL_OFFICE_DESCRIPTION = 'Reserved technical office for system administrator accounts.';

    public function index(Request $request): Response
    {
        // Validate filter parameters
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $actingUserId = auth()->id();

        // Build base query
        $query = User::query()
            ->with(['role:id,code,name', 'office:id,code,name'])
            ->where(function ($q): void {
                $q->whereHas('role', fn ($roleQuery) => $roleQuery->where('code', '!=', UserRole::PUBLIC->value))
                    ->orWhereNull('role_id');
            });

        // Apply search filter
        if ($filters['search'] ?? null) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereLikeInsensitive('name', $search)
                    ->orWhereLikeInsensitive('email', $search);
            });
        }

        // Apply role filter
        if ($filters['role_id'] ?? null) {
            $query->where('role_id', $filters['role_id']);
        }

        // Apply status filter
        if ($filters['status'] ?? null) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        // Count total and active admins for safety checks
        $totalAdminCount = User::query()
            ->whereHas('role', fn ($q) => $q->where('code', UserRole::ADMIN->value))
            ->count();

        $activeAdminCount = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('code', UserRole::ADMIN->value))
            ->count();

        // Get paginated users
        $users = $query
            ->orderBy('name')
            ->paginate(perPage: 5, page: $filters['page'] ?? 1)
            ->withQueryString()
            ->through(function (User $user) use ($actingUserId, $totalAdminCount, $activeAdminCount): array {
                $isSelf = $actingUserId === $user->id;
                $isAdmin = $user->role?->code === UserRole::ADMIN->value;
                $isLastActiveAdmin = $isAdmin && $user->is_active && $activeAdminCount <= 1;
                $isLastAdmin = $isAdmin && $totalAdminCount <= 1;
                $hasLinked = $this->hasLinkedRecords($user);

                $canToggleActive = ! $isSelf && ! $isLastActiveAdmin;
                $toggleActiveReason = $isSelf
                    ? 'You cannot change the active status of your own account.'
                    : ($isLastActiveAdmin ? 'The last remaining active administrator account cannot be deactivated.' : null);

                $canDelete = ! $isSelf && ! $isLastAdmin && ! $hasLinked;
                $deleteReason = $isSelf
                    ? 'You cannot delete your own account.'
                    : ($isLastAdmin
                        ? 'The last remaining administrator account cannot be deleted.'
                        : ($hasLinked ? 'This user has linked records and should be deactivated rather than deleted.' : null));

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'emp_id' => $user->emp_id,
                    'is_active' => (bool) $user->is_active,
                    'role' => $user->role ? [
                        'code' => $user->role->code,
                        'name' => $user->role->name,
                    ] : null,
                    'office' => $user->office ? [
                        'code' => $user->office->code,
                        'name' => $user->office->name,
                    ] : null,
                    'can_toggle_active' => $canToggleActive,
                    'toggle_active_reason' => $toggleActiveReason,
                    'can_delete' => $canDelete,
                    'delete_reason' => $deleteReason,
                ];
            });

        return Inertia::render('AdminTech/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $filters['search'] ?? null,
                'role_id' => $filters['role_id'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'roles' => $this->manageableRoles(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('AdminTech/Users/Create', [
            'roles' => $this->manageableRoles(),
            'offices' => $this->activeOffices(),
        ]);
    }

    public function store(StoreInternalUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $roleId = (int) $validated['role_id'];

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'emp_id' => $validated['emp_id'],
            'password' => Hash::make($validated['password']),
            'must_change_password' => true,
            'is_active' => true,
            'role_id' => $roleId,
            'office_id' => $this->resolveOfficeIdForRole(
                roleId: $roleId,
                requestedOfficeId: isset($validated['office_id']) ? (int) $validated['office_id'] : null,
            ),
            'email_verified_at' => now(),
        ]);

        return to_route('admin-tech.users.index')->with('success', 'User account created successfully.');
    }

    public function edit(User $user): Response
    {
        $this->ensureManageableUser($user);

        return Inertia::render('AdminTech/Users/Edit', [
            'userRecord' => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'emp_id' => $user->emp_id ? (string) $user->emp_id : null,
                'role_id' => (int) $user->role_id,
                'role_code' => (string) ($user->role?->code ?? ''),
                'office_id' => $user->office_id ? (int) $user->office_id : null,
                'is_active' => (bool) $user->is_active,
            ],
            'roles' => $this->manageableRoles(),
            'offices' => $this->activeOffices(),
        ]);
    }

    public function update(UpdateInternalUserRequest $request, User $user): RedirectResponse
    {
        $this->ensureManageableUser($user);

        $validated = $request->validated();
        $roleId = (int) $validated['role_id'];

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'emp_id' => $validated['emp_id'],
            'role_id' => $roleId,
            'office_id' => $this->resolveOfficeIdForRole(
                roleId: $roleId,
                requestedOfficeId: isset($validated['office_id']) ? (int) $validated['office_id'] : null,
            ),
        ]);

        return to_route('admin-tech.users.index')->with('success', 'User account updated successfully.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageableUser($user);

        if ($request->user()?->id === $user->id) {
            return back()->withErrors([
                'toggle_active' => 'You cannot change the active status of your own account.',
            ]);
        }

        if ($user->is_active && $user->role?->code === UserRole::ADMIN->value) {
            $activeAdminCount = User::query()
                ->where('is_active', true)
                ->whereHas('role', fn ($query) => $query->where('code', UserRole::ADMIN->value))
                ->count();

            if ($activeAdminCount <= 1) {
                return back()->withErrors([
                    'toggle_active' => 'The last remaining active administrator account cannot be deactivated.',
                ]);
            }
        }

        $newStatus = ! $user->is_active;
        $user->update(['is_active' => $newStatus]);

        $actionWord = $newStatus ? 'activated' : 'deactivated';

        return to_route('admin-tech.users.index')->with('success', "User account for {$user->name} has been {$actionWord} successfully.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageableUser($user);

        if ($request->user()?->id === $user->id) {
            return back()->withErrors([
                'delete' => 'You cannot delete your own account from User Management.',
            ]);
        }

        if ($user->role?->code === UserRole::ADMIN->value) {
            $otherAdminExists = User::query()
                ->where('id', '!=', $user->id)
                ->whereHas('role', fn ($query) => $query->where('code', UserRole::ADMIN->value))
                ->exists();

            if (! $otherAdminExists) {
                return back()->withErrors([
                    'delete' => 'The last remaining admin account cannot be deleted.',
                ]);
            }
        }

        if ($this->hasLinkedRecords($user)) {
            return back()->withErrors([
                'delete' => 'This user account has linked system records and cannot be deleted. Please deactivate it instead.',
            ]);
        }

        try {
            $user->delete();
        } catch (QueryException) {
            return back()->withErrors([
                'delete' => 'This account cannot be deleted because it is linked to existing records. Please deactivate it instead.',
            ]);
        }

        return to_route('admin-tech.users.index')->with('success', 'User account deleted successfully.');
    }

    private function hasLinkedRecords(User $user): bool
    {
        return $user->auditLogs()->exists()
            || $user->approvals()->exists()
            || $user->statusChanges()->exists()
            || $user->preparedPpmps()->exists()
            || $user->approvedPpmps()->exists()
            || $user->requestedSupplyRequests()->exists()
            || $user->approvedSupplyRequests()->exists()
            || $user->requestedPurchaseRequests()->exists()
            || $user->createdPurchaseOrders()->exists()
            || $user->receivedDeliveries()->exists()
            || $user->verifiedDeliveries()->exists()
            || $user->inventoryMovements()->exists()
            || $user->preparedDistributionRecords()->exists()
            || $user->receivedDistributionRecords()->exists()
            || $user->receivedDistributionItems()->exists()
            || $user->filedClaims()->exists()
            || $user->reviewedAlerts()->exists()
            || $user->publishedDisclosures()->exists()
            || $user->uploadedDocuments()->exists()
            || $user->generatedRecordArchives()->exists()
            || $user->createdSystemBackups()->exists();
    }

    private function ensureManageableUser(User $user): void
    {
        abort_if($user->role?->code === UserRole::PUBLIC->value, 404);
    }

    private function manageableRoles()
    {
        return Role::query()
            ->where('code', '!=', UserRole::PUBLIC->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    private function activeOffices()
    {
        return Office::query()
            ->where('is_active', true)
            ->where('code', '!=', self::TECHNICAL_OFFICE_CODE)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    private function resolveOfficeIdForRole(int $roleId, ?int $requestedOfficeId): int
    {
        $roleCode = $this->roleById($roleId)->code;

        if ($roleCode === UserRole::ADMIN->value) {
            return (int) $this->technicalOffice()->id;
        }

        return (int) $requestedOfficeId;
    }

    private function roleById(int $roleId): Role
    {
        return Role::query()->findOrFail($roleId);
    }

    private function technicalOffice(): Office
    {
        return Office::query()->updateOrCreate(
            ['code' => self::TECHNICAL_OFFICE_CODE],
            [
                'name' => self::TECHNICAL_OFFICE_NAME,
                'description' => self::TECHNICAL_OFFICE_DESCRIPTION,
                'is_active' => true,
            ],
        );
    }
}
