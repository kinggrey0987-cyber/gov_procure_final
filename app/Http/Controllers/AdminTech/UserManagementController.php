<?php

namespace App\Http\Controllers\AdminTech;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->with(['role:id,code,name', 'office:id,code,name'])
            ->where(function ($query): void {
                $query->whereHas('role', fn ($roleQuery) => $roleQuery->where('code', '!=', UserRole::PUBLIC->value))
                    ->orWhereNull('role_id');
            })
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('AdminTech/Users/Index', [
            'users' => $users,
        ]);
    }

    public function edit(User $user): Response
    {
        $this->ensureManageableUser($user);

        return Inertia::render('AdminTech/Users/Edit', [
            'userRecord' => $user->load(['role:id,code,name', 'office:id,code,name']),
            'roles' => Role::query()
                ->where('code', '!=', UserRole::PUBLIC->value)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'offices' => Office::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageableUser($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('code', '!=', UserRole::PUBLIC->value)),
            ],
            'office_id' => ['required', 'integer', Rule::exists('offices', 'id')],
        ]);

        $user->update($validated);

        return to_route('admin-tech.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageableUser($user);

        if ($request->user()?->id === $user->id) {
            return back()->withErrors([
                'delete' => 'You cannot delete your own account from User Management.',
            ]);
        }

        try {
            $user->delete();
        } catch (QueryException) {
            return back()->withErrors([
                'delete' => 'This account cannot be deleted because it is linked to existing records.',
            ]);
        }

        return to_route('admin-tech.users.index');
    }

    private function ensureManageableUser(User $user): void
    {
        abort_if($user->role?->code === UserRole::PUBLIC->value, 404);
    }
}
