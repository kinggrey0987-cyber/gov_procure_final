<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Notifications\NotificationCenterService;
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
        $notificationService = app(NotificationCenterService::class);
        $recentNotifications = [];

        if ($user instanceof User && $user->can('notifications.access')) {
            $recentNotifications = $notificationService
                ->recentForUser($user, 5)
                ->map(static fn ($notification): array => [
                    'notification_id' => (int) $notification->notification_id,
                    'title' => (string) $notification->title,
                    'message' => (string) $notification->message,
                    'is_read' => (bool) $notification->is_read,
                    'created_at' => optional($notification->created_at)?->toIso8601String(),
                    'open_url' => route('notifications.show', $notification->notification_id),
                ])
                ->values()
                ->all();
        }

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
                    'notifications' => $user?->can('notifications.access') ?? false,
                ],
            ],
            'notifications' => [
                'unread_count' => $user && $user->can('notifications.access')
                    ? $notificationService->unreadCountForUser($user)
                    : 0,
                'recent' => $recentNotifications,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
