import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Props = {
    summary: {
        pending_requests: number;
        processing_requests: number;
        ongoing_procurement: number;
        completed_procurements: number;
    };
    recentPurchaseRequests: Array<{
        purchase_request_id: number;
        office_name: string;
        status: string;
        created_at?: string | null;
    }>;
    recentPurchaseOrders: Array<{
        purchase_order_id: number;
        po_number: string;
        supplier_name: string;
        status: string;
        total_amount: number;
        created_at?: string | null;
    }>;
};

export default function ProcurementTracking({ summary, recentPurchaseRequests, recentPurchaseOrders }: Props) {
    const [requestSearch, setRequestSearch] = useState('');
    const [requestStatus, setRequestStatus] = useState('ALL');
    const [orderSearch, setOrderSearch] = useState('');
    const [orderStatus, setOrderStatus] = useState('ALL');

    const requestStatuses = useMemo(
        () => ['ALL', ...Array.from(new Set(recentPurchaseRequests.map((request) => request.status.toUpperCase())))],
        [recentPurchaseRequests],
    );

    const orderStatuses = useMemo(
        () => ['ALL', ...Array.from(new Set(recentPurchaseOrders.map((order) => order.status.toUpperCase())))],
        [recentPurchaseOrders],
    );

    const filteredPurchaseRequests = useMemo(() => {
        const keyword = requestSearch.trim().toLowerCase();

        return recentPurchaseRequests.filter((request) => {
            const normalizedStatus = request.status.toUpperCase();
            const matchesStatus = requestStatus === 'ALL' || normalizedStatus === requestStatus;

            if (!matchesStatus) {
                return false;
            }

            if (keyword.length === 0) {
                return true;
            }

            return [
                `PR-${String(request.purchase_request_id).padStart(6, '0')}`,
                request.office_name,
                request.status,
                formatDate(request.created_at),
            ]
                .join(' ')
                .toLowerCase()
                .includes(keyword);
        });
    }, [recentPurchaseRequests, requestSearch, requestStatus]);

    const filteredPurchaseOrders = useMemo(() => {
        const keyword = orderSearch.trim().toLowerCase();

        return recentPurchaseOrders.filter((order) => {
            const normalizedStatus = order.status.toUpperCase();
            const matchesStatus = orderStatus === 'ALL' || normalizedStatus === orderStatus;

            if (!matchesStatus) {
                return false;
            }

            if (keyword.length === 0) {
                return true;
            }

            return [
                order.po_number,
                order.supplier_name,
                order.status,
                formatMoney(order.total_amount),
                formatDate(order.created_at),
            ]
                .join(' ')
                .toLowerCase()
                .includes(keyword);
        });
    }, [recentPurchaseOrders, orderSearch, orderStatus]);

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Procurement Dashboard</h2>}
        >
            <Head title="Procurement Dashboard" />

            <div className="space-y-6 py-8">
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard label="Pending Requests" value={summary.pending_requests} tone="amber" />
                    <SummaryCard label="Processing Requests" value={summary.processing_requests} tone="blue" />
                    <SummaryCard label="Ongoing Procurement" value={summary.ongoing_procurement} tone="green" />
                    <SummaryCard label="Completed Procurements" value={summary.completed_procurements} tone="red" />
                </section>

                <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-4">
                        <h3 className="text-lg font-semibold text-slate-900">Quick Actions</h3>
                    </div>
                    <div className="grid gap-4 p-6 md:grid-cols-3">
                        <Link href={route('procurement.purchase-requests.index')} className="rounded-xl border border-amber-300 px-4 py-5 text-center text-sm font-semibold text-amber-700 transition hover:bg-amber-50">
                            Review Requests
                        </Link>
                        <Link href={route('procurement.purchase-orders.create')} className="rounded-xl border border-emerald-300 px-4 py-5 text-center text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                            Create PO
                        </Link>
                        <Link href={route('procurement.suppliers.index')} className="rounded-xl border border-red-300 px-4 py-5 text-center text-sm font-semibold text-red-700 transition hover:bg-red-50">
                            Manage Suppliers
                        </Link>
                    </div>
                </section>

                <section className="grid gap-4 lg:grid-cols-2">
                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-6 py-4">
                            <h3 className="text-lg font-semibold text-slate-900">Recent Purchase Requests</h3>
                        </div>
                        <div className="border-b border-slate-200 bg-slate-50 px-6 py-4">
                            <div className="grid gap-3 md:grid-cols-5">
                                <div className="md:col-span-3">
                                    <label htmlFor="procurement-request-search" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        Search
                                    </label>
                                    <input
                                        id="procurement-request-search"
                                        type="text"
                                        value={requestSearch}
                                        onChange={(event) => setRequestSearch(event.target.value)}
                                        placeholder="Search PR ID, office, date"
                                        className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="procurement-request-status" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        Status
                                    </label>
                                    <select
                                        id="procurement-request-status"
                                        value={requestStatus}
                                        onChange={(event) => setRequestStatus(event.target.value)}
                                        className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                                    >
                                        {requestStatuses.map((status) => (
                                            <option key={status} value={status}>
                                                {status === 'ALL' ? 'All statuses' : status.replaceAll('_', ' ')}
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
                            <p className="mt-3 text-xs text-slate-500">Showing {filteredPurchaseRequests.length} of {recentPurchaseRequests.length} request(s).</p>
                        </div>
                        <div className="divide-y divide-slate-100">
                            {filteredPurchaseRequests.length === 0 ? (
                                <p className="px-6 py-8 text-sm text-slate-500">No purchase requests yet.</p>
                            ) : (
                                filteredPurchaseRequests.map((request) => (
                                    <div key={request.purchase_request_id} className="flex items-center justify-between gap-3 px-6 py-4">
                                        <div>
                                            <p className="font-semibold text-slate-800">PR-{String(request.purchase_request_id).padStart(6, '0')}</p>
                                            <p className="text-sm text-slate-600">{request.office_name}</p>
                                        </div>
                                        <div className="text-right">
                                            <span className={statusClass(request.status)}>{request.status}</span>
                                            <p className="mt-1 text-xs text-slate-500">{formatDate(request.created_at)}</p>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-6 py-4">
                            <h3 className="text-lg font-semibold text-slate-900">Recent Purchase Orders</h3>
                        </div>
                        <div className="border-b border-slate-200 bg-slate-50 px-6 py-4">
                            <div className="grid gap-3 md:grid-cols-5">
                                <div className="md:col-span-3">
                                    <label htmlFor="procurement-order-search" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        Search
                                    </label>
                                    <input
                                        id="procurement-order-search"
                                        type="text"
                                        value={orderSearch}
                                        onChange={(event) => setOrderSearch(event.target.value)}
                                        placeholder="Search PO number, supplier, amount"
                                        className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="procurement-order-status" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        Status
                                    </label>
                                    <select
                                        id="procurement-order-status"
                                        value={orderStatus}
                                        onChange={(event) => setOrderStatus(event.target.value)}
                                        className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                                    >
                                        {orderStatuses.map((status) => (
                                            <option key={status} value={status}>
                                                {status === 'ALL' ? 'All statuses' : status.replaceAll('_', ' ')}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="flex items-end">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setOrderSearch('');
                                            setOrderStatus('ALL');
                                        }}
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>
                            <p className="mt-3 text-xs text-slate-500">Showing {filteredPurchaseOrders.length} of {recentPurchaseOrders.length} order(s).</p>
                        </div>
                        <div className="divide-y divide-slate-100">
                            {filteredPurchaseOrders.length === 0 ? (
                                <p className="px-6 py-8 text-sm text-slate-500">No purchase orders yet.</p>
                            ) : (
                                filteredPurchaseOrders.map((order) => (
                                    <div key={order.purchase_order_id} className="flex items-center justify-between gap-3 px-6 py-4">
                                        <div>
                                            <p className="font-semibold text-slate-800">{order.po_number}</p>
                                            <p className="text-sm text-slate-600">{order.supplier_name}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-semibold text-slate-800">{formatMoney(order.total_amount)}</p>
                                            <p className="text-xs text-slate-500">{formatDate(order.created_at)}</p>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function SummaryCard({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone: 'amber' | 'blue' | 'green' | 'red';
}) {
    const tones: Record<'amber' | 'blue' | 'green' | 'red', string> = {
        amber: 'border-amber-200 text-amber-700',
        blue: 'border-blue-200 text-blue-700',
        green: 'border-emerald-200 text-emerald-700',
        red: 'border-red-200 text-red-700',
    };

    return (
        <article className={`rounded-xl border bg-white px-6 py-5 shadow-sm ${tones[tone]}`}>
            <p className="text-sm font-semibold text-slate-500">{label}</p>
            <p className="mt-2 text-4xl font-bold text-slate-900">{value}</p>
        </article>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        OPEN: `${base} bg-amber-100 text-amber-700`,
        PROCESSING: `${base} bg-blue-100 text-blue-700`,
        COMPLETED: `${base} bg-emerald-100 text-emerald-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function formatDate(value?: string | null) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}

function formatMoney(value: number) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(value);
}
