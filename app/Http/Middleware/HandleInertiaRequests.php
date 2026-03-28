<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_code' => $user->roleCode(),
                    'office_id' => $user->office_id,
                ] : null,
                'can' => [
                    'admin_tech' => $user?->can('domain.admin-tech.access') ?? false,
                    'office' => $user?->can('domain.office.access') ?? false,
                    'procurement' => $user?->can('domain.procurement.access') ?? false,
                    'inventory' => $user?->can('domain.inventory.access') ?? false,
                    'executive' => $user?->can('domain.executive.access') ?? false,
                ],
            ],
        ];
    }
}
