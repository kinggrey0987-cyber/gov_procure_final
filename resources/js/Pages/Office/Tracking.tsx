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
};

export default function OfficeTracking({ summary, recentRequests, releasedSuppliesPreview, notificationPreview }: Props) {
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
            header={
                <div className="space-y-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Office Dashboard</h2>
                    <Link href={route('dashboard')} className="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Back to Main Dashboard
                    </Link>
                </div>
            }
        >
            <Head title="Office Dashboard" />

            <div className="space-y-6 py-4">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard label="Total Requests" value={summary.total_requests} tone="slate" />
                    <MetricCard label="Pending Requests" value={summary.pending_requests} tone="amber" />
                    <MetricCard label="Processed Requests" value={summary.processed_requests} tone="emerald" />
                    <MetricCard label="Supplies Ready for Claim" value={summary.ready_for_claim} tone="sky" />
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-2xl font-semibold text-slate-800">Quick Actions</h3>
                    </div>
                    <div className="grid gap-3 p-6 md:grid-cols-3">
                        <Link
                            href={route('office.supply-requests.create')}
                            className="rounded-xl border border-red-500 bg-red-600 px-4 py-5 text-center text-lg font-semibold text-white transition hover:bg-red-700"
                        >
                            Create New Request
                        </Link>
                        <Link
                            href={route('office.supply-requests.index')}
                            className="rounded-xl border border-blue-300 bg-white px-4 py-5 text-center text-lg font-semibold text-blue-700 transition hover:bg-blue-50"
                        >
                            Track Request Status
                        </Link>
                        <Link
                            href={route('office.supplies-ready.index')}
                            className="rounded-xl border border-emerald-300 bg-white px-4 py-5 text-center text-lg font-semibold text-emerald-700 transition hover:bg-emerald-50"
                        >
                            View Ready Supplies
                        </Link>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                        <div>
                            <h3 className="text-2xl font-semibold text-slate-800">Recent Requests</h3>
                            <p className="mt-1 text-lg text-slate-500">Your latest supply requests</p>
                        </div>
                        <Link href={route('office.supply-requests.index')} className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                            View All
                        </Link>
                    </div>

                    <div className="border-b border-slate-200 bg-slate-50 px-6 py-4">
                        <div className="grid gap-3 md:grid-cols-6">
                            <div className="md:col-span-3">
                                <label htmlFor="office-dashboard-request-search" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    Search requests
                                </label>
                                <input
                                    id="office-dashboard-request-search"
                                    type="text"
                                    value={requestSearch}
                                    onChange={(event) => setRequestSearch(event.target.value)}
                                    placeholder="Search request ID, date, status"
                                    className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                                />
                            </div>
                            <div className="md:col-span-2">
                                <label htmlFor="office-dashboard-request-status" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    Status
                                </label>
                                <select
                                    id="office-dashboard-request-status"
                                    value={requestStatus}
                                    onChange={(event) => setRequestStatus(event.target.value)}
                                    className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                                >
                                    {requestStatusOptions.map((status) => (
                                        <option key={status} value={status}>
                                            {status === 'ALL' ? 'All statuses' : displayOfficeRequestStatus(status)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex items-end">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setRequestSearch('');
                                        setRequestStatus('ALL');
                                    }}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                        <p className="mt-3 text-xs text-slate-500">
                            Showing {filteredRecentRequests.length} of {recentRequests.length} request(s).
                        </p>
                    </div>

                    {filteredRecentRequests.length === 0 ? (
                        <div className="px-6 py-10 text-sm text-slate-600">No requests yet. Create your first request.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Request ID</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Date</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Items</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {filteredRecentRequests.map((request) => (
                                        <tr key={request.supply_request_id}>
                                            <td className="px-4 py-3 font-semibold text-red-700">{request.request_code}</td>
                                            <td className="px-4 py-3 text-slate-700">{formatDate(request.request_date)}</td>
                                            <td className="px-4 py-3 text-slate-700">{request.items_count} item(s)</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={request.status} />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Link href={route('office.supply-requests.show', request.supply_request_id)} className="rounded-lg border border-red-300 px-4 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-50">
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-100 bg-emerald-50/50 px-6 py-5">
                        <div>
                            <h3 className="text-2xl font-semibold text-slate-800">Released Supplies</h3>
                            <p className="mt-1 text-lg text-slate-500">Supplies released by GSO and ready for office confirmation or claims.</p>
                        </div>
                        <Link href={route('office.supplies-ready.index')} className="rounded-lg border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                            Open Released Supplies
                        </Link>
                    </div>

                    {releasedSuppliesPreview.length === 0 ? (
                        <div className="px-6 py-10 text-sm text-slate-600">No released supplies are awaiting office action.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Request ID</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Released</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Items</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {releasedSuppliesPreview.map((record) => (
                                        <tr key={record.distribution_record_id}>
                                            <td className="px-4 py-3">
                                                <p className="font-semibold text-red-700">{record.request_code}</p>
                                                <p className="font-mono text-xs text-slate-500">{record.distribution_code}</p>
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">{formatDate(record.release_date)}</td>
                                            <td className="px-4 py-3 text-slate-700">
                                                <p>{record.items_count} item(s)</p>
                                                <p className="text-xs text-slate-500">{record.items_summary || 'No item summary'}</p>
                                            </td>
                                            <td className="px-4 py-3">
                                                <DistributionStatusBadge status={record.status} />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex flex-wrap items-center justify-end gap-2">
                                                    <Link href={route('office.distribution-records.show', record.distribution_record_id)} className="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">
                                                        Open
                                                    </Link>
                                                    <Link href={route('office.claims.create', { distribution_record_id: record.distribution_record_id })} className="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                                        File Claim
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-2xl font-semibold text-slate-800">Recent Updates</h3>
                        <p className="mt-1 text-lg text-slate-500">Notifications and request updates</p>
                    </div>
                    <div className="space-y-3 p-6">
                        {notificationPreview.length === 0 ? (
                            <p className="text-sm text-slate-600">No updates yet.</p>
                        ) : (
                            notificationPreview.map((notification) => (
                                <div key={notification.notification_id} className={`rounded-xl border px-4 py-3 ${notification.is_read ? 'border-slate-200 bg-slate-50' : 'border-emerald-200 bg-emerald-50/60'}`}>
                                    <p className="text-base font-semibold text-slate-800">{notification.title}</p>
                                    <p className="mt-1 text-sm text-slate-600">{notification.message}</p>
                                    <p className="mt-1 text-xs text-slate-500">{formatDateTime(notification.created_at)}</p>
                                </div>
                            ))
                        )}
                    </div>
                </section>
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

function DistributionStatusBadge({ status }: { status: string }) {
    const normalized = status.toUpperCase();
    const style = normalized === 'RELEASED'
        ? 'bg-emerald-100 text-emerald-700'
        : normalized === 'RECEIVED'
            ? 'bg-blue-100 text-blue-700'
            : 'bg-slate-100 text-slate-700';

    return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${style}`}>{status.replaceAll('_', ' ')}</span>;
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





