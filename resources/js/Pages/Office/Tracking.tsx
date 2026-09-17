import DashboardAlertBanner, { type DashboardAlert } from '@/Components/DashboardAlertBanner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Props = {
    summary: {
        total_requests: number;
        pending_requests: number;
        processed_requests: number;
        ready_for_claim: number;
        completed_requests: number;
    };
    recentRequests: Array<{
        supply_request_id: number;
        request_code: string;
        request_date?: string | null;
        status: string;
        items_count: number;
    }>;
    releasedSuppliesPreview: Array<{
        distribution_record_id: number;
        distribution_code: string;
        request_code: string;
        release_date?: string | null;
        status: string;
        items_count: number;
        items_summary: string;
    }>;
    notificationPreview: Array<{
        notification_id: number;
        title: string;
        message: string;
        is_read: boolean;
        created_at?: string | null;
    }>;
    dashboardAlerts: DashboardAlert[];
};

export default function OfficeTracking({ summary, recentRequests, notificationPreview, dashboardAlerts }: Props) {
    const [requestSearch, setRequestSearch] = useState('');
    const [requestStatus, setRequestStatus] = useState('ALL');

    const requestStatusOptions = useMemo(
        () => ['ALL', ...Array.from(new Set(recentRequests.map((request) => request.status.toUpperCase())))],
        [recentRequests],
    );

    const filteredRecentRequests = useMemo(() => {
        const keyword = requestSearch.trim().toLowerCase();

        return recentRequests.filter((request) => {
            const normalizedStatus = request.status.toUpperCase();
            const matchesStatus = requestStatus === 'ALL' || normalizedStatus === requestStatus;

            if (!matchesStatus) {
                return false;
            }

            if (keyword.length === 0) {
                return true;
            }

            const searchIndex = [
                request.request_code,
                request.status,
                displayOfficeRequestStatus(request.status),
                formatDate(request.request_date),
                `${request.items_count} item(s)`,
            ]
                .join(' ')
                .toLowerCase();

            return searchIndex.includes(keyword);
        });
    }, [recentRequests, requestSearch, requestStatus]);

    return (
        <AuthenticatedLayout
            header="Office Dashboard"
            headerActions={
                <Link
                    href={route('office.supply-requests.create')}
                    className="inline-flex h-8 items-center rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 shadow-sm transition"
                >
                    <svg className="mr-1.5 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                    </svg>
                    New Request
                </Link>
            }
        >
            <Head title="Office Dashboard" />

            <div className="space-y-2 lg:h-[calc(100vh-8.5rem)] flex flex-col justify-between overflow-y-auto lg:overflow-hidden">
                <DashboardAlertBanner alerts={dashboardAlerts} />

                {/* Unified High-Density Office Telemetry Cockpit - Interactive Drilldown */}
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white p-2 shadow-sm space-y-1.5">
                    <div className="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <button
                            type="button"
                            onClick={() => {
                                setRequestStatus('ALL');
                                setRequestSearch('');
                                document.getElementById('recent-requests-table')?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            className={`flex items-center gap-2.5 px-3 py-1.5 text-left transition rounded-lg group cursor-pointer hover:bg-slate-50 ${
                                requestStatus === 'ALL' && !requestSearch ? 'bg-slate-100 ring-1 ring-slate-300' : ''
                            }`}
                            title="Click to show all requests"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-slate-400" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 group-hover:text-slate-700">Total Requests</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight">{summary.total_requests}</p>
                            </div>
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                const pending = requestStatusOptions.find((s) => s.includes('PENDING')) ?? 'PENDING';
                                setRequestStatus(pending);
                                document.getElementById('recent-requests-table')?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            className={`flex items-center gap-2.5 px-3 py-1.5 text-left transition rounded-lg group cursor-pointer hover:bg-amber-50/60 ${
                                requestStatus.includes('PENDING') ? 'bg-amber-50 ring-1 ring-amber-300' : ''
                            }`}
                            title="Click to filter by Pending Review requests"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-amber-500" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-amber-700 group-hover:text-amber-800">Pending Review</p>
                                <p className="text-lg font-extrabold text-amber-900 leading-tight">{summary.pending_requests}</p>
                            </div>
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                const proc = requestStatusOptions.find((s) => s.includes('PROCESS') || s.includes('APPROVED')) ?? 'APPROVED';
                                setRequestStatus(proc);
                                document.getElementById('recent-requests-table')?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            className={`flex items-center gap-2.5 px-3 py-1.5 text-left transition rounded-lg group cursor-pointer hover:bg-emerald-50/60 ${
                                requestStatus.includes('PROCESS') || requestStatus.includes('APPROVED') ? 'bg-emerald-50 ring-1 ring-emerald-300' : ''
                            }`}
                            title="Click to filter by In Processing requests"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-700 group-hover:text-emerald-800">In Processing</p>
                                <p className="text-lg font-extrabold text-emerald-900 leading-tight">{summary.processed_requests}</p>
                            </div>
                        </button>
                        <Link
                            href={route('office.supplies-ready.index')}
                            className="flex items-center gap-2.5 px-3 py-1.5 text-left transition rounded-lg group cursor-pointer hover:bg-sky-50/60"
                            title="Click to view Supplies Ready to Claim"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-sky-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-sky-700 group-hover:text-sky-800">Ready for Claim</p>
                                <p className="text-lg font-extrabold text-sky-900 leading-tight">{summary.ready_for_claim}</p>
                            </div>
                        </Link>
                    </div>
                </section>

                {/* Compact Quick Access Cards */}
                <div className="shrink-0 grid gap-2 sm:grid-cols-3">
                    <Link
                        href={route('office.supply-requests.create')}
                        className="group flex items-center gap-2.5 rounded-xl border border-red-200 bg-red-50/40 p-2.5 shadow-xs transition hover:border-red-400 hover:bg-red-50"
                    >
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-600 text-white shadow-xs transition group-hover:bg-red-700">
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </span>
                        <div className="min-w-0">
                            <p className="text-xs font-bold text-slate-900 group-hover:text-red-700">Create New Request</p>
                            <p className="text-[10px] text-slate-500 truncate">Requisition supplies for department</p>
                        </div>
                    </Link>

                    <Link
                        href={route('office.supply-requests.index')}
                        className="group flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs transition hover:border-slate-300 hover:bg-slate-50/70"
                    >
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition group-hover:bg-slate-200">
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </span>
                        <div className="min-w-0">
                            <p className="text-xs font-bold text-slate-900 group-hover:text-red-700">Track Request Status</p>
                            <p className="text-[10px] text-slate-500 truncate">View approval and delivery timeline</p>
                        </div>
                    </Link>

                    <Link
                        href={route('office.supplies-ready.index')}
                        className="group flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs transition hover:border-slate-300 hover:bg-slate-50/70"
                    >
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700 transition group-hover:bg-sky-100">
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </span>
                        <div className="min-w-0">
                            <p className="text-xs font-bold text-slate-900 group-hover:text-red-700">Supplies Ready to Claim</p>
                            <p className="text-[10px] text-slate-500 truncate">Pending warehouse pick-up</p>
                        </div>
                    </Link>
                </div>

                <div className="grid gap-2.5 xl:grid-cols-12 flex-1 min-h-0">
                    {/* Recent Requests Table (8 cols) */}
                    <div id="recent-requests-table" className="xl:col-span-8 flex flex-col min-h-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs scroll-mt-6">
                        <div className="shrink-0 flex items-center justify-between border-b border-slate-200 px-3.5 py-2">
                            <div>
                                <h3 className="text-xs font-bold text-slate-900">Recent Supply Requests</h3>
                                <p className="text-[10px] text-slate-500">Latest office requisitions & lifecycle status</p>
                            </div>
                            <Link href={route('office.supply-requests.index')} className="text-xs font-semibold text-red-700 hover:text-red-800 hover:underline">
                                View All &rarr;
                            </Link>
                        </div>

                        <div className="shrink-0 border-b border-slate-100 bg-slate-50/60 p-2">
                            <div className="grid gap-2 grid-cols-1 sm:grid-cols-12">
                                <input
                                    type="text"
                                    value={requestSearch}
                                    onChange={(event) => setRequestSearch(event.target.value)}
                                    placeholder="Search request ID, date, status"
                                    className="sm:col-span-7 h-7 rounded-lg border border-slate-300 px-2 text-xs shadow-xs focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                />
                                <select
                                    value={requestStatus}
                                    onChange={(event) => setRequestStatus(event.target.value)}
                                    className="sm:col-span-3 h-7 rounded-lg border border-slate-300 px-2 text-xs shadow-xs focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                >
                                    {requestStatusOptions.map((status) => (
                                        <option key={status} value={status}>
                                            {status === 'ALL' ? 'All statuses' : displayOfficeRequestStatus(status)}
                                        </option>
                                    ))}
                                </select>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setRequestSearch('');
                                        setRequestStatus('ALL');
                                    }}
                                    className="sm:col-span-2 h-7 rounded-lg border border-slate-300 px-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>

                        <div className="overflow-x-auto flex-1 overflow-y-auto min-h-0">
                            {filteredRecentRequests.length === 0 ? (
                                <div className="px-4 py-6 text-center text-xs text-slate-500">No requests match your current filters.</div>
                            ) : (
                                <table className="min-w-full divide-y divide-slate-200 text-xs">
                                    <thead className="bg-slate-50 sticky top-0 z-10">
                                        <tr>
                                            <th className="px-3 py-1.5 text-left font-semibold uppercase tracking-wide text-slate-500 text-[10px]">Request ID</th>
                                            <th className="px-3 py-1.5 text-left font-semibold uppercase tracking-wide text-slate-500 text-[10px]">Date</th>
                                            <th className="px-3 py-1.5 text-left font-semibold uppercase tracking-wide text-slate-500 text-[10px]">Items</th>
                                            <th className="px-3 py-1.5 text-left font-semibold uppercase tracking-wide text-slate-500 text-[10px]">Status</th>
                                            <th className="px-3 py-1.5 text-right font-semibold uppercase tracking-wide text-slate-500 text-[10px]">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {filteredRecentRequests.map((request) => (
                                            <tr key={request.supply_request_id} className="hover:bg-slate-50/50 transition">
                                                <td className="px-3 py-1.5 font-bold text-red-700">{request.request_code}</td>
                                                <td className="px-3 py-1.5 text-slate-600">{formatDate(request.request_date)}</td>
                                                <td className="px-3 py-1.5 text-slate-600">{request.items_count} item(s)</td>
                                                <td className="px-3 py-1.5">
                                                    <StatusBadge status={request.status} />
                                                </td>
                                                <td className="px-3 py-1.5 text-right">
                                                    <Link
                                                        href={route('office.supply-requests.show', request.supply_request_id)}
                                                        className="inline-flex h-6 items-center rounded border border-slate-300 bg-white px-2 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 hover:text-red-700 transition"
                                                    >
                                                        View
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>

                    {/* Recent Updates / Notifications (4 cols) */}
                    <div className="xl:col-span-4 flex flex-col min-h-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                        <div className="shrink-0 border-b border-slate-200 px-3.5 py-2">
                            <h3 className="text-xs font-bold text-slate-900">Recent Updates</h3>
                            <p className="text-[10px] text-slate-500">Notifications and status signals</p>
                        </div>
                        <div className="p-2.5 space-y-1.5 flex-1 overflow-y-auto min-h-0">
                            {notificationPreview.length === 0 ? (
                                <p className="py-6 text-center text-xs text-slate-500">No recent updates.</p>
                            ) : (
                                notificationPreview.map((notification) => (
                                    <div
                                        key={notification.notification_id}
                                        className={`rounded-lg border p-2 text-xs transition ${notification.is_read ? 'border-slate-100 bg-slate-50/60' : 'border-emerald-200 bg-emerald-50/40'}`}
                                    >
                                        <p className="font-bold text-slate-900 leading-tight text-xs">{notification.title}</p>
                                        <p className="mt-0.5 text-slate-600 text-[11px] line-clamp-2">{notification.message}</p>
                                        <p className="mt-1 text-[10px] text-slate-400">{formatDateTime(notification.created_at)}</p>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

function formatDateTime(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function StatusBadge({ status }: { status: string }) {
    const normalized = status.toUpperCase();
    const style =
        normalized === 'APPROVED' || normalized === 'COMPLETED'
            ? 'bg-emerald-100 text-emerald-700'
            : normalized === 'IN_REVIEW' || normalized === 'PROCESSING'
                ? 'bg-blue-100 text-blue-700'
                : normalized === 'REJECTED'
                    ? 'bg-rose-100 text-rose-700'
                    : 'bg-amber-100 text-amber-700';

    return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${style}`}>{displayOfficeRequestStatus(status)}</span>;
}

function displayOfficeRequestStatus(status: string): string {
    const normalized = status.toUpperCase();
    const map: Record<string, string> = {
        OPEN: 'Pending Review',
        SUBMITTED: 'Submitted',
        PROCESSING: 'In Processing',
        APPROVED: 'Approved',
        COMPLETED: 'Completed',
        REJECTED: 'Rejected',
    };

    return map[normalized] ?? status.replaceAll('_', ' ');
}

function MetricCard({ label, value, tone }: { label: string; value: number; tone: 'slate' | 'amber' | 'emerald' | 'sky' }) {
    const toneClass: Record<string, string> = {
        slate: 'border-slate-200 bg-white text-slate-800',
        amber: 'border-amber-200 bg-amber-50/70 text-amber-900',
        emerald: 'border-emerald-200 bg-emerald-50/70 text-emerald-900',
        sky: 'border-blue-200 bg-blue-50/70 text-blue-900',
    };

    return (
        <div className={`rounded-2xl border px-5 py-4 shadow-sm ${toneClass[tone]}`}>
            <p className="text-sm font-medium">{label}</p>
            <p className="mt-2 text-5xl font-bold leading-none">{value}</p>
        </div>
    );
}




