import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Props = {
    summary: {
        total_requests: number;
        pending_requests: number;
        approved_requests: number;
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
    notificationPreview: Array<{
        notification_id: number;
        title: string;
        message: string;
        is_read: boolean;
        created_at?: string | null;
    }>;
};

export default function OfficeTracking({ summary, recentRequests, notificationPreview }: Props) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Office Dashboard</h2>}
        >
            <Head title="Office Dashboard" />

            <div className="space-y-6 py-4">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard label="Total Requests" value={summary.total_requests} tone="slate" />
                    <MetricCard label="Pending Requests" value={summary.pending_requests} tone="amber" />
                    <MetricCard label="Approved Requests" value={summary.approved_requests} tone="emerald" />
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

                    {recentRequests.length === 0 ? (
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
                                    {recentRequests.map((request) => (
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

    return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${style}`}>{status.replaceAll('_', ' ')}</span>;
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
