import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

type NotificationItem = {
    notification_id: number;
    title: string;
    message: string;
    notification_type: string;
    reference_type?: string | null;
    reference_id?: number | null;
    is_read: boolean;
    created_at: string;
};

type PaginatedNotifications = {
    data: NotificationItem[];
    total: number;
};

type Props = {
    notifications: PaginatedNotifications;
};

export default function NotificationIndex({ notifications }: Props) {
    const unreadCount = notifications.data.filter((item) => !item.is_read).length;

    const markAsRead = (notificationId: number) => {
        router.put(route('notifications.mark-read', notificationId), {}, { preserveScroll: true });
    };

    const markAllAsRead = () => {
        router.put(route('notifications.mark-all-read'), {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Notifications</h2>}>
            <Head title="Notifications" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4 flex items-center justify-between">
                        <div className="text-sm text-slate-600">
                            <span className="font-medium">Total:</span> {notifications.total} |{' '}
                            <span className="font-medium">Unread:</span> {unreadCount}
                        </div>
                        <button
                            type="button"
                            onClick={markAllAsRead}
                            className="rounded border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700"
                        >
                            Mark All as Read
                        </button>
                    </div>

                    <div className="space-y-3">
                        {notifications.data.length === 0 && (
                            <div className="rounded border bg-white p-6 text-sm text-slate-500">
                                You have no notifications yet.
                            </div>
                        )}

                        {notifications.data.map((notification) => (
                            <div
                                key={notification.notification_id}
                                className={`rounded border p-4 ${notification.is_read ? 'bg-white' : 'bg-amber-50'}`}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 className="text-sm font-semibold text-slate-900">{notification.title}</h3>
                                        <p className="mt-1 text-sm text-slate-700">{notification.message}</p>
                                        <p className="mt-2 text-xs text-slate-500">
                                            Type: {notification.notification_type}
                                            {notification.reference_type && notification.reference_id
                                                ? ` | Reference: ${notification.reference_type} #${notification.reference_id}`
                                                : ''}
                                        </p>
                                        <p className="text-xs text-slate-500">{new Date(notification.created_at).toLocaleString()}</p>
                                    </div>
                                    <span
                                        className={`rounded px-2 py-1 text-xs font-semibold ${notification.is_read ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-800'}`}
                                    >
                                        {notification.is_read ? 'READ' : 'UNREAD'}
                                    </span>
                                </div>

                                <div className="mt-3 flex gap-2">
                                    {!notification.is_read && (
                                        <button
                                            type="button"
                                            onClick={() => markAsRead(notification.notification_id)}
                                            className="rounded border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700"
                                        >
                                            Mark as Read
                                        </button>
                                    )}
                                    <Link
                                        href={route('notifications.show', notification.notification_id)}
                                        className="rounded bg-slate-900 px-3 py-1 text-xs font-medium text-white"
                                    >
                                        Open Target
                                    </Link>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
