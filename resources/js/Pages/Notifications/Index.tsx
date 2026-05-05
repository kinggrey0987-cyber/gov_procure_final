import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

type NotificationItem = {
    notification_id: number;
    title: string;
    message: string;
    notification_type: string;
    reference_type?: string | null;
    reference_id?: number | null;
    reference_label?: string | null;
    target_url?: string | null;
    target_label?: string | null;
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
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Notifications</h2>
                    <p className="mt-1 text-sm font-normal text-slate-500">Review system messages and open only the linked records available to your current dashboard.</p>
                </div>
            }
        >
            <Head title="Notifications" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="text-sm font-semibold text-slate-800">Notification Center</p>
                                <p className="mt-1 text-sm text-slate-600">
                                    <span className="font-medium">Total:</span> {notifications.total} <span className="px-1 text-slate-300">|</span>
                                    <span className="font-medium">Unread on this page:</span> {unreadCount}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={markAllAsRead}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Mark All as Read
                            </button>
                        </div>
                    </div>

                    <div className="space-y-3">
                        {notifications.data.length === 0 && (
                            <div className="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
                                You have no notifications yet.
                            </div>
                        )}

                        {notifications.data.map((notification) => (
                            <div
                                key={notification.notification_id}
                                className={`rounded-xl border p-4 shadow-sm ${notification.is_read ? 'border-slate-200 bg-white' : 'border-amber-200 bg-amber-50'}`}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <h3 className="text-sm font-semibold text-slate-900">{notification.title}</h3>
                                        <p className="mt-1 text-sm text-slate-700">{notification.message}</p>
                                        <div className="mt-2 flex flex-wrap gap-2 text-xs text-slate-500">
                                            <span className="rounded-full bg-slate-100 px-2 py-1 font-medium text-slate-600">{formatType(notification.notification_type)}</span>
                                            {notification.reference_label ? (
                                                <span className="rounded-full bg-slate-100 px-2 py-1 font-medium text-slate-600">{notification.reference_label}</span>
                                            ) : null}
                                            {!notification.target_url ? (
                                                <span className="rounded-full bg-rose-50 px-2 py-1 font-medium text-rose-700">No accessible target</span>
                                            ) : null}
                                        </div>
                                        <p className="text-xs text-slate-500">{new Date(notification.created_at).toLocaleString()}</p>
                                    </div>
                                    <span
                                        className={`rounded px-2 py-1 text-xs font-semibold ${notification.is_read ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-800'}`}
                                    >
                                        {notification.is_read ? 'READ' : 'UNREAD'}
                                    </span>
                                </div>

                                <div className="mt-3 flex flex-wrap gap-2">
                                    {!notification.is_read && (
                                        <button
                                            type="button"
                                            onClick={() => markAsRead(notification.notification_id)}
                                            className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Mark as Read
                                        </button>
                                    )}
                                    {notification.target_url ? (
                                        <Link
                                            href={route('notifications.show', notification.notification_id)}
                                            className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                                        >
                                            {notification.target_label ?? 'View Linked Record'}
                                        </Link>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function formatType(value: string): string {
    return value.replaceAll('_', ' ').toLowerCase().replace(/\b\w/g, (char) => char.toUpperCase());
}





