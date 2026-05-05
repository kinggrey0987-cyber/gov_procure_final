import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

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

    const handleFilterSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        router.get(route('dashboard'), {
            search: formData.search || undefined,
            actor: formData.actor || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

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
                        <form onSubmit={handleFilterSubmit} className="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-5">
                            <div className="md:col-span-3">
                                <label htmlFor="admin-activity-search" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    Search activity
                                </label>
                                <input
                                    id="admin-activity-search"
                                    type="text"
                                    value={formData.search}
                                    onChange={(event) => setFormData(prev => ({ ...prev, search: event.target.value }))}
                                    placeholder="Search module name"
                                    className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                                />
                            </div>
                            <div>
                                <label htmlFor="admin-activity-actor" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    Actor
                                </label>
                                <input
                                    id="admin-activity-actor"
                                    type="text"
                                    value={formData.actor}
                                    onChange={(event) => setFormData(prev => ({ ...prev, actor: event.target.value }))}
                                    placeholder="Actor name"
                                    className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                                />
                            </div>
                            <div className="flex items-end gap-2">
                                <button
                                    type="submit"
                                    className="flex-1 rounded-lg border border-blue-300 bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                >
                                    Filter
                                </button>
                                <button
                                    type="button"
                                    onClick={handleClearFilters}
                                    className="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                >
                                    Clear
                                </button>
                            </div>
                        </form>

                        {activities.length === 0 && (
                            <p className="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-slate-500">
                                No activity records matched the selected filters.
                            </p>
                        )}

                        {activities.length > 0 && (
                            <>
                                <ul className="mb-4 space-y-4">
                                    {activities.map((activity) => (
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

                                <div className="border-t border-slate-200 pt-4">
                                    <div className="mb-4 flex items-center justify-between">
                                        <p className="text-sm text-slate-600">
                                            Page <span className="font-semibold">{pagination.current_page}</span> of <span className="font-semibold">{pagination.last_page}</span> • Showing <span className="font-semibold">{activities.length}</span> of <span className="font-semibold">{pagination.total}</span>
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <button
                                            onClick={() => handlePaginationLink(pagination.prev_page_url)}
                                            disabled={!pagination.prev_page_url}
                                            className="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400"
                                        >
                                            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                <path d="M15 19l-7-7 7-7" />
                                            </svg>
                                            Previous
                                        </button>
                                        <button
                                            onClick={() => handlePaginationLink(pagination.next_page_url)}
                                            disabled={!pagination.next_page_url}
                                            className="ml-auto inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400"
                                        >
                                            Next
                                            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                <path d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </>
                        )}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}





