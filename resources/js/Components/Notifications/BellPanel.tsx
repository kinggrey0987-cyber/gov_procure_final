import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type BellNotification = {
    notification_id: number;
    title: string;
    message: string;
    is_read: boolean;
    created_at?: string | null;
    open_url: string;
};

type Props = {
    unreadCount: number;
    recent: BellNotification[];
};

function formatTimestamp(value?: string | null): string {
    if (!value) {
        return 'Just now';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return 'Just now';
    }

    return parsed.toLocaleString();
}

export default function BellPanel({ unreadCount, recent }: Props) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: MouseEvent) => {
            if (!containerRef.current) {
                return;
            }

            if (!containerRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        const onEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', onPointerDown);
        document.addEventListener('keydown', onEscape);

        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            document.removeEventListener('keydown', onEscape);
        };
    }, [open]);

    return (
        <div ref={containerRef} className="relative">
            <button
                type="button"
                title="Notifications"
                aria-haspopup="dialog"
                aria-expanded={open}
                onClick={() => setOpen((current) => !current)}
                className="relative text-slate-500 transition hover:text-slate-700"
            >
                <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5" />
                    <path d="M10 21a2 2 0 0 0 4 0" />
                </svg>
                {unreadCount > 0 && (
                    <span className="absolute -right-2 -top-2 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-bold text-white">
                        {unreadCount}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 z-50 mt-3 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h3 className="text-sm font-semibold text-slate-800">Recent Notifications</h3>
                        <p className="mt-1 text-xs text-slate-500">Latest updates from your account activity.</p>
                    </div>

                    <div className="max-h-96 overflow-y-auto">
                        {recent.length === 0 ? (
                            <div className="px-4 py-8 text-center text-sm text-slate-500">
                                No notifications yet.
                            </div>
                        ) : (
                            <div className="divide-y divide-slate-100">
                                {recent.map((notification) => (
                                    <Link
                                        key={notification.notification_id}
                                        href={notification.open_url}
                                        onClick={() => setOpen(false)}
                                        className={`block px-4 py-3 transition hover:bg-slate-50 ${notification.is_read ? 'bg-white' : 'bg-red-50/50'}`}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="text-sm font-semibold text-slate-800">{notification.title}</p>
                                            {!notification.is_read && (
                                                <span className="rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-semibold uppercase text-white">
                                                    New
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-1 line-clamp-2 text-xs text-slate-600">{notification.message}</p>
                                        <p className="mt-2 text-[11px] text-slate-500">{formatTimestamp(notification.created_at)}</p>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="border-t border-slate-200 bg-slate-50 px-3 py-2">
                        <Link
                            href={route('notifications.index')}
                            onClick={() => setOpen(false)}
                            className="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                        >
                            See all notifications
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
