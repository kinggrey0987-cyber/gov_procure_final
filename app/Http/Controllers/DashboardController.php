<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $startedAt = microtime(true);
        $user = $request->user();
        $userId = (int) ($user?->id ?? 0);
        $roleCode = $user?->roleCode();

        if (! is_string($roleCode) || $roleCode === '') {
            Log::warning('dashboard.redirect.missing_role', [
                'user_id' => $userId,
            ]);

            return $this->fallbackRedirect('Your account role is not configured. Contact an administrator.');
        }

        $targetRoute = $this->dashboardRouteForRole($roleCode);

        if (! is_string($targetRoute) || $targetRoute === '' || ! Route::has($targetRoute)) {
            Log::warning('dashboard.redirect.unknown_or_missing_route', [
                'user_id' => $userId,
                'role_code' => $roleCode,
                'target_route' => $targetRoute,
            ]);

            return $this->fallbackRedirect('Your account role is not recognized. Contact an administrator.');
        }

        $response = redirect()->route($targetRoute);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        if ($durationMs >= 300) {
            Log::warning('dashboard.redirect.slow', [
                'user_id' => $userId,
                'role_code' => $roleCode,
                'target_route' => $targetRoute,
                'duration_ms' => $durationMs,
            ]);
        }

        return $response;
    }

    private function dashboardRouteForRole(string $roleCode): ?string
    {
        return match ($roleCode) {
            UserRole::ADMIN->value => 'admin-tech.dashboard',
            UserRole::OFFICE_USER->value => 'office.dashboard',
            UserRole::PROCUREMENT_OFFICER->value => 'procurement.dashboard',
            UserRole::GSO_OFFICER->value => 'inventory.dashboard',
            UserRole::EXECUTIVE_OFFICER->value => 'executive.dashboard',
            UserRole::PUBLIC->value => 'public.dashboard',
            default => null,
        };
    }

    private function fallbackRedirect(string $error): RedirectResponse
    {
        return redirect()->route('profile.edit')->with('error', $error);
    }
}
