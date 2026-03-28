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
    public function index(): Response
    {
        $actingUserId = auth()->id();
        $adminCount = User::query()
            ->whereHas('role', fn ($query) => $query->where('code', UserRole::ADMIN->value))
            ->count();

        $users = User::query()
            ->with(['role:id,code,name', 'office:id,code,name'])
            ->where(function ($query): void {
                $query->whereHas('role', fn ($roleQuery) => $roleQuery->where('code', '!=', UserRole::PUBLIC->value))
                    ->orWhereNull('role_id');
            })
            ->orderBy('name')
            ->paginate(20)
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

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => (int) $validated['role_id'],
            'office_id' => (int) $validated['office_id'],
            'email_verified_at' => now(),
        ]);

        return to_route('admin-tech.users.index')->with('success', 'User account created successfully.');
    }

    public function edit(User $user): Response
    {
        $this->ensureManageableUser($user);

        return Inertia::render('AdminTech/Users/Edit', [
            'userRecord' => $user->load(['role:id,code,name', 'office:id,code,name']),
            'roles' => $this->manageableRoles(),
            'offices' => $this->activeOffices(),
        ]);
    }

    public function update(UpdateInternalUserRequest $request, User $user): RedirectResponse
    {
        $this->ensureManageableUser($user);

        $user->update($request->validated());

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
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }
}
