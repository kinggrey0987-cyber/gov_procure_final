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

        $notifications = $this->service
            ->paginateForUser($request->user(), 20)
            ->through(function (Notification $notification) use ($request): array {
                $targetUrl = $this->service->resolveTargetUrl($request->user(), $notification);

                return [
                    'notification_id' => (int) $notification->notification_id,
                    'title' => (string) $notification->title,
                    'message' => (string) $notification->message,
                    'notification_type' => (string) $notification->notification_type,
                    'reference_type' => $notification->reference_type,
                    'reference_id' => $notification->reference_id ? (int) $notification->reference_id : null,
                    'reference_label' => $this->referenceLabel($notification),
                    'target_url' => $targetUrl,
                    'target_label' => $targetUrl ? $this->targetLabel($notification) : null,
                    'is_read' => (bool) $notification->is_read,
                    'created_at' => optional($notification->created_at)?->toIso8601String(),
                ];
            });

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

    private function referenceLabel(Notification $notification): ?string
    {
        if (! $notification->reference_type || ! $notification->reference_id) {
            return null;
        }

        return sprintf('%s #%d', class_basename((string) $notification->reference_type), (int) $notification->reference_id);
    }

    private function targetLabel(Notification $notification): string
    {
        return match ((string) $notification->reference_type) {
            \App\Models\Alert::class => 'View Alert Queue',
            \App\Models\Anomaly::class => 'Review Anomaly',
            \App\Models\Claim::class => 'View Claim',
            \App\Models\Delivery::class => 'View Delivery',
            \App\Models\DistributionRecord::class => 'View Distribution',
            \App\Models\Ppmp::class => 'View PPMP',
            \App\Models\PurchaseOrder::class => 'View Purchase Order',
            \App\Models\PurchaseRequest::class => 'View Purchase Request',
            \App\Models\PublicDisclosure::class => 'View Disclosure',
            \App\Models\SupplyRequest::class => 'View Supply Request',
            default => 'View Linked Record',
        };
    }
}
