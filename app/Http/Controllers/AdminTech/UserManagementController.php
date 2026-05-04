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
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply role filter
        if ($filters['role_id'] ?? null) {
            $query->where('role_id', $filters['role_id']);
        }

        // Count admins for deletion checks
        $adminCount = (clone $query)
            ->whereHas('role', fn ($q) => $q->where('code', UserRole::ADMIN->value))
            ->count();

        // Get paginated users
        $users = $query
            ->orderBy('name')
            ->paginate(perPage: 20, page: $filters['page'] ?? 1)
            ->withQueryString()
            ->through(function (User $user) use ($actingUserId, $adminCount): array {
                $isSelf = $actingUserId === $user->id;
                $isLastAdmin = $user->role?->code === UserRole::ADMIN->value && $adminCount <= 1;
                $canDelete = ! $isSelf && ! $isLastAdmin;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ? [
                        'code' => $user->role->code,
                        'name' => $user->role->name,
                    ] : null,
                    'office' => $user->office ? [
                        'code' => $user->office->code,
                        'name' => $user->office->name,
                    ] : null,
                    'can_delete' => $canDelete,
                    'delete_reason' => $isSelf
                        ? 'You cannot delete your own account.'
                        : ($isLastAdmin ? 'The last remaining admin account cannot be deleted.' : null),
                ];
            });

        return Inertia::render('AdminTech/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $filters['search'] ?? null,
                'role_id' => $filters['role_id'] ?? null,
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

        try {
            $user->delete();
        } catch (QueryException) {
            return back()->withErrors([
                'delete' => 'This account cannot be deleted because it is linked to existing records.',
            ]);
        }

        return to_route('admin-tech.users.index')->with('success', 'User account deleted successfully.');
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
