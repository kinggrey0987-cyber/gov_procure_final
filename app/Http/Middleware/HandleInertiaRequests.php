<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;
use Throwable;

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
        $roleCode = $user instanceof User ? $user->roleCode() : null;
        $domainAccess = $this->domainAccessFromRole($roleCode);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_code' => $roleCode,
                    'office_id' => $user->office_id,
                ] : null,
                'can' => $domainAccess,
            ],
            'notificationSummary' => fn (): array => $this->notificationSummary($request->user(), $domainAccess['notifications']),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * @return array{admin_tech: bool, office: bool, procurement: bool, inventory: bool, executive: bool, notifications: bool}
     */
    private function domainAccessFromRole(?string $roleCode): array
    {
        return [
            'admin_tech' => $roleCode === UserRole::ADMIN->value,
            'office' => $roleCode === UserRole::OFFICE_USER->value,
            'procurement' => $roleCode === UserRole::PROCUREMENT_OFFICER->value,
            'inventory' => $roleCode === UserRole::GSO_OFFICER->value,
            'executive' => $roleCode === UserRole::EXECUTIVE_OFFICER->value,
            'notifications' => $roleCode !== null && $roleCode !== UserRole::PUBLIC->value,
        ];
    }

    /**
     * @return array{unread_count: int, recent: array<int, array{notification_id: int, title: string, message: string, is_read: bool, created_at: string|null, open_url: string}>}
     */
    private function notificationSummary(?User $user, bool $canAccess): array
    {
        if (! $user instanceof User || ! $canAccess) {
            return [
                'unread_count' => 0,
                'recent' => [],
            ];
        }

        $startedAt = microtime(true);

        try {
            /** @var NotificationCenterService $notificationService */
            $notificationService = app(NotificationCenterService::class);

            $recent = $notificationService
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

            return [
                'unread_count' => $notificationService->unreadCountForUser($user),
                'recent' => $recent,
            ];
        } catch (Throwable $throwable) {
            report($throwable);

            Log::warning('inertia.notifications.load_failed', [
                'user_id' => (int) $user->id,
                'error' => $throwable->getMessage(),
            ]);

            return [
                'unread_count' => 0,
                'recent' => [],
            ];
        } finally {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($durationMs >= 1200) {
                Log::warning('inertia.notifications.load_slow', [
                    'user_id' => (int) $user->id,
                    'duration_ms' => $durationMs,
                ]);
            }
        }
    }
}
