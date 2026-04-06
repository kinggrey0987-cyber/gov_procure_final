<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $roleCode = $request->user()?->roleCode();

        if (! is_string($roleCode) || $roleCode === '') {
            return redirect()->route('profile.edit')
                ->with('error', 'Your account role is not configured. Contact an administrator.');
        }

        return match ($roleCode) {
            UserRole::ADMIN->value => redirect()->route('admin-tech.dashboard'),
            UserRole::OFFICE_USER->value => redirect()->route('office.dashboard'),
            UserRole::PROCUREMENT_OFFICER->value => redirect()->route('procurement.dashboard'),
            UserRole::GSO_OFFICER->value => redirect()->route('inventory.dashboard'),
            UserRole::EXECUTIVE_OFFICER->value => redirect()->route('executive.dashboard'),
            UserRole::PUBLIC->value => redirect()->route('public.dashboard'),
            default => redirect()->route('profile.edit')
                ->with('error', 'Your account role is not recognized. Contact an administrator.'),
        };
    }
}
