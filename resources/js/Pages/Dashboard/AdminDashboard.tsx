import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Props = {
    summary: {
        total_users: number;
        office_count: number;
        active_role_count: number;
        unread_notification_count: number;
    };
    activities: Array<{
        id: number;
        title: string;
        actor: string;
        occurred_at: string | null;
    }>;
};

function formatRelativeDate(value: string | null): string {
    if (!value) {
        return 'Unknown time';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Unknown time';
    }

    return date.toLocaleString();
}

export default function AdminDashboard({ summary, activities }: Props) {
    const [activitySearch, setActivitySearch] = useState('');
    const [activityActor, setActivityActor] = useState('ALL');

    const actorOptions = useMemo(
        () => ['ALL', ...Array.from(new Set(activities.map((activity) => activity.actor))).sort((a, b) => a.localeCompare(b))],
        [activities],
    );

    const filteredActivities = useMemo(() => {
        const keyword = activitySearch.trim().toLowerCase();

        return activities.filter((activity) => {
            const matchesActor = activityActor === 'ALL' || activity.actor === activityActor;
            if (!matchesActor) {
                return false;
            }

            if (keyword.length === 0) {
                return true;
            }

            return [activity.title, activity.actor, formatRelativeDate(activity.occurred_at)].join(' ').toLowerCase().includes(keyword);
        });
    }, [activities, activityActor, activitySearch]);

    const statCards = [
        {
            label: 'Total Users',
            value: summary.total_users,
            accent: 'bg-blue-600',
            icon: (
                <svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="8" cy="8" r="3" />
                    <path d="M2 20c0-3 2.7-5 6-5" />
                    <circle cx="17" cy="9" r="2.5" />
                    <path d="M14 20c0-2.7 2.2-4.5 5-5" />
                </svg>
            ),
        },
        {
            label: 'Municipal Offices',
            value: summary.office_count,
            accent: 'bg-green-700',
            icon: (
                <svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <rect x="4" y="3" width="10" height="18" rx="2" />
                    <path d="M14 8h6v13h-6" />
                </svg>
            ),
        },
        {
            label: 'Active System Roles',
            value: summary.active_role_count,
            accent: 'bg-amber-500',
            icon: (
                <svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M12 3l7 4v6c0 4.4-3.5 7.8-7 8-3.5-.2-7-3.6-7-8V7l7-4z" />
                </svg>
            ),
        },
        {
            label: 'Unread Notifications',
            value: summary.unread_notification_count,
            accent: 'bg-red-600',
            icon: (
                <svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5" />
                    <path d="M10 21a2 2 0 0 0 4 0" />
                </svg>
            ),
        },
    ];

    return (
        <AuthenticatedLayout header="Admin Dashboard">
            <Head title="Admin Dashboard" />

            <div className="space-y-6">
                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {statCards.map((card) => (
                        <article key={card.label} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-lg text-slate-500">{card.label}</p>
                                    <p className="mt-2 text-5xl font-bold text-slate-800">{card.value}</p>
                                </div>
                                <div className={`flex h-14 w-14 items-center justify-center rounded-full text-white ${card.accent}`}>
                                    {card.icon}
                                </div>
                            </div>
                        </article>
                    ))}
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-3xl font-semibold text-slate-800">Quick Access</h3>
                    </header>
                    <div className="grid gap-4 p-6 md:grid-cols-3">
                        <Link href={route('admin-tech.users.index')} className="rounded-xl border border-blue-300 px-5 py-8 text-center text-blue-700 transition hover:bg-blue-50">
                            <p className="text-lg font-semibold">Manage Users</p>
                        </Link>
                        <Link href={route('admin-tech.offices.index')} className="rounded-xl border border-green-300 px-5 py-8 text-center text-green-700 transition hover:bg-green-50">
                            <p className="text-lg font-semibold">Manage Offices</p>
                        </Link>
                        <Link href={route('admin-tech.roles.index')} className="rounded-xl border border-red-300 px-5 py-8 text-center text-red-700 transition hover:bg-red-50">
                            <p className="text-lg font-semibold">System Monitoring</p>
                        </Link>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-3xl font-semibold text-slate-800">System Activity</h3>
                        <p className="mt-1 text-lg text-slate-500">Recent technical administration events</p>
                    </header>
                    <div className="p-6">
                        <div className="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-5">
                            <div className="md:col-span-3">
                                <label htmlFor="admin-activity-search" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    Search activity
                                </label>
                                <input
                                    id="admin-activity-search"
                                    type="text"
                                    value={activitySearch}
                                    onChange={(event) => setActivitySearch(event.target.value)}
                                    placeholder="Search title, actor, or timestamp"
                                    className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                                />
                            </div>
                            <div>
                                <label htmlFor="admin-activity-actor" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    Actor
                                </label>
                                <select
                                    id="admin-activity-actor"
                                    value={activityActor}
                                    onChange={(event) => setActivityActor(event.target.value)}
                                    className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                                >
                                    {actorOptions.map((actor) => (
                                        <option key={actor} value={actor}>
                                            {actor === 'ALL' ? 'All actors' : actor}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex items-end">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setActivitySearch('');
                                        setActivityActor('ALL');
                                    }}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                >
                                    Clear Filters
                                </button>
                            </div>
                            <p className="md:col-span-5 text-xs text-slate-500">
                                Showing {filteredActivities.length} of {activities.length} activity record(s).
                            </p>
                        </div>

                        {filteredActivities.length === 0 && (
                            <p className="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-slate-500">
                                No activity records matched the selected filters.
                            </p>
                        )}

                        {filteredActivities.length > 0 && (
                            <ul className="space-y-4">
                                {filteredActivities.map((activity) => (
                                    <li key={activity.id} className="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3">
                                        <span className="mt-1 inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                <path d="M12 8v4l3 2" />
                                                <circle cx="12" cy="12" r="9" />
                                            </svg>
                                        </span>
                                        <div>
                                            <p className="text-lg font-semibold text-slate-800">{activity.title}</p>
                                            <p className="text-sm text-slate-600">{activity.actor}</p>
                                            <p className="text-sm text-slate-500">{formatRelativeDate(activity.occurred_at)}</p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
