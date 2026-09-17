import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

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
    pagination: {
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
    filters: {
        search: string | null;
        actor: string | null;
    };
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

export default function AdminDashboard({ summary, activities, pagination, filters }: Props) {
    const [formData, setFormData] = useState({
        search: filters.search ?? '',
        actor: filters.actor ?? '',
    });
    const debouncedSearch = useDebouncedValue(formData.search, 400);
    const debouncedActor = useDebouncedValue(formData.actor, 400);

    const activeFilters = useMemo(() => ({
        search: debouncedSearch || undefined,
        actor: debouncedActor || undefined,
    }), [debouncedActor, debouncedSearch]);

    const activeFiltersKey = useMemo(() => JSON.stringify(activeFilters), [activeFilters]);
    const currentFiltersKey = useMemo(() => JSON.stringify({
        search: filters.search || undefined,
        actor: filters.actor || undefined,
    }), [filters.actor, filters.search]);

    useEffect(() => {
        setFormData({
            search: filters.search ?? '',
            actor: filters.actor ?? '',
        });
    }, [filters.actor, filters.search]);

    useEffect(() => {
        if (activeFiltersKey === currentFiltersKey) {
            return;
        }

        router.get(route('dashboard'), activeFilters, {
            preserveState: true,
            replace: true,
        });
    }, [activeFilters, activeFiltersKey, currentFiltersKey]);

    const handleClearFilters = () => {
        setFormData({ search: '', actor: '' });
        router.get(route('dashboard'), {}, {
            preserveState: true,
            replace: true,
        });
    };

    const handlePaginationLink = (url: string | null) => {
        if (url) {
            router.get(url, {}, {
                preserveState: true,
                replace: true,
            });
        }
    };

    const statCards = [
        {
            label: 'Total Users',
            value: summary.total_users,
            accent: 'bg-blue-600',
            href: route('admin-tech.users.index'),
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
            href: route('admin-tech.offices.index'),
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
            href: route('admin-tech.roles.index'),
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
            href: route('notifications.index'),
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

            <div className="space-y-4">
                {/* Compact KPI Stat Cards */}
                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {statCards.map((card) => (
                        <Link
                            key={card.label}
                            href={card.href}
                            className="group flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm transition hover:border-slate-300 hover:shadow-md cursor-pointer"
                        >
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 group-hover:text-slate-700 transition">{card.label}</p>
                                <p className="mt-1 text-2xl font-bold tracking-tight text-slate-900 group-hover:text-red-700 transition sm:text-3xl">{card.value}</p>
                            </div>
                            <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white shadow-sm transition group-hover:scale-105 ${card.accent}`}>
                                {card.icon}
                            </div>
                        </Link>
                    ))}
                </section>

                {/* Streamlined Quick Access Grid */}
                <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-sm font-bold uppercase tracking-wider text-slate-700">Quick Access</h2>
                        <span className="text-xs text-slate-400">Core Administration Portals</span>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Link
                            href={route('admin-tech.users.index')}
                            className="flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50/40 p-3 text-blue-800 transition hover:bg-blue-100/60 hover:border-blue-300"
                        >
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white">
                                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                            </div>
                            <div>
                                <p className="text-sm font-bold">Manage Users</p>
                                <p className="text-xs text-slate-500">Access and role permissions</p>
                            </div>
                        </Link>

                        <Link
                            href={route('admin-tech.offices.index')}
                            className="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50/40 p-3 text-emerald-800 transition hover:bg-emerald-100/60 hover:border-emerald-300"
                        >
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white">
                                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                                </svg>
                            </div>
                            <div>
                                <p className="text-sm font-bold">Manage Offices</p>
                                <p className="text-xs text-slate-500">Municipal department directory</p>
                            </div>
                        </Link>

                        <Link
                            href={route('admin-tech.roles.index')}
                            className="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50/40 p-3 text-red-800 transition hover:bg-red-100/60 hover:border-red-300"
                        >
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-600 text-white">
                                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <path d="M12 3l7 4v6c0 4.4-3.5 7.8-7 8-3.5-.2-7-3.6-7-8V7l7-4z" />
                                </svg>
                            </div>
                            <div>
                                <p className="text-sm font-bold">System Monitoring</p>
                                <p className="text-xs text-slate-500">Audit logs and active telemetry</p>
                            </div>
                        </Link>
                    </div>
                </section>

                {/* Compact System Activity Section */}
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                        <div>
                            <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">System Activity</h2>
                            <p className="text-xs text-slate-500">Recent account and audit activity</p>
                        </div>

                        {/* Inline Compact Filter */}
                        <div className="flex flex-wrap items-center gap-2">
                            <input
                                id="admin-activity-search"
                                type="text"
                                value={formData.search}
                                onChange={(event) => setFormData(prev => ({ ...prev, search: event.target.value }))}
                                placeholder="Search action..."
                                className="h-8 rounded-lg border border-slate-300 px-2.5 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 w-40 sm:w-48"
                            />
                            <input
                                id="admin-activity-actor"
                                type="text"
                                value={formData.actor}
                                onChange={(event) => setFormData(prev => ({ ...prev, actor: event.target.value }))}
                                placeholder="Filter actor..."
                                className="h-8 rounded-lg border border-slate-300 px-2.5 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 w-32 sm:w-36"
                            />
                            {(formData.search || formData.actor) && (
                                <button
                                    type="button"
                                    onClick={handleClearFilters}
                                    className="h-8 rounded-lg border border-slate-300 px-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                                >
                                    Clear
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-xs">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-2.5 text-left font-semibold text-slate-600">Action / Activity</th>
                                    <th className="px-4 py-2.5 text-left font-semibold text-slate-600">Actor</th>
                                    <th className="px-4 py-2.5 text-left font-semibold text-slate-600">Timestamp</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white">
                                {activities.length === 0 ? (
                                    <tr>
                                        <td colSpan={3} className="px-4 py-6 text-center text-slate-500">
                                            No system activity recorded yet.
                                        </td>
                                    </tr>
                                ) : (
                                    activities.map((activity) => (
                                        <tr key={activity.id} className="hover:bg-slate-50/80 transition">
                                            <td className="px-4 py-2.5 font-medium text-slate-800">{activity.title}</td>
                                            <td className="px-4 py-2.5 text-slate-600">{activity.actor}</td>
                                            <td className="px-4 py-2.5 text-slate-500">{formatRelativeDate(activity.occurred_at)}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {activities.length > 0 && (
                        <div className="border-t border-slate-200 px-4 py-2.5 flex items-center justify-between text-xs">
                            <p className="text-slate-500">
                                Page <span className="font-semibold">{pagination.current_page}</span> of <span className="font-semibold">{pagination.last_page}</span> &bull; <span className="font-semibold">{pagination.total}</span> activities
                            </p>
                            <div className="flex items-center gap-1.5">
                                <button
                                    onClick={() => handlePaginationLink(pagination.prev_page_url)}
                                    disabled={!pagination.prev_page_url}
                                    className="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    Previous
                                </button>
                                <button
                                    onClick={() => handlePaginationLink(pagination.next_page_url)}
                                    disabled={!pagination.next_page_url}
                                    className="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}





