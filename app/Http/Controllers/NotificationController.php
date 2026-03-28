<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notification\MarkAllNotificationsReadRequest;
use App\Http\Requests\Notification\MarkNotificationReadRequest;
use App\Models\Notification;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationCenterService $service)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('notifications.access'), 403);

        $notifications = $this->service->paginateForUser($request->user(), 20);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function show(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($request->user()?->can('notification.view', $notification), 403);

        try {
            $notification = $this->service->markAsRead($request->user(), $notification);
        } catch (ValidationException) {
            abort(403);
        }

        $target = $this->service->resolveTargetUrl($request->user(), $notification);

        if (! $target) {
            return redirect()->route('notifications.index')
                ->with('warning', 'The linked record is unavailable or inaccessible.');
        }

        return redirect()->to($target);
    }

    public function markAsRead(MarkNotificationReadRequest $request, Notification $notification): RedirectResponse
    {
        abort_unless($request->user()?->can('notification.update', $notification), 403);

        try {
            $this->service->markAsRead($request->user(), $notification);
        } catch (ValidationException) {
            abort(403);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(MarkAllNotificationsReadRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('notifications.access'), 403);

        $updated = $this->service->markAllAsRead($request->user());

        return back()->with('success', $updated > 0
            ? 'All notifications marked as read.'
            : 'No unread notifications found.');
    }
}
