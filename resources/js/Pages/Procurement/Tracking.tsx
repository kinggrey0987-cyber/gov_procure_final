import DashboardAlertBanner, { type DashboardAlert } from '@/Components/DashboardAlertBanner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Props = {
    summary: {
        pending_requests: number;
        processing_requests: number;
        ongoing_procurement: number;
        completed_procurements: number;
        total_purchase_order_amount: number;
        draft_purchase_orders: number;
        placed_purchase_orders: number;
        active_supplier_count: number;
    };
    recentPurchaseRequests: Array<{
        purchase_request_id: number;
        pr_number?: string | null;
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
    dashboardAlerts: DashboardAlert[];
};

const REQUEST_STATUS_FILTERS = ['ALL', 'OPEN', 'PROCESSING', 'COMPLETED', 'RETURNED_FOR_REVISION', 'REJECTED'];
const ORDER_STATUS_FILTERS = ['ALL', 'DRAFT', 'PLACED', 'COMPLETED'];

export default function ProcurementTracking({ summary, recentPurchaseRequests, recentPurchaseOrders, dashboardAlerts }: Props) {
    const [requestSearch, setRequestSearch] = useState('');
    const [requestStatus, setRequestStatus] = useState('ALL');
    const [orderSearch, setOrderSearch] = useState('');
    const [orderStatus, setOrderStatus] = useState('ALL');

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
                request.pr_number || `PR-${String(request.purchase_request_id).padStart(6, '0')}`,
                request.office_name,
                request.status,
                displayPurchaseRequestStatus(request.status),
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
            header="Procurement Dashboard"
            headerActions={
                <Link
                    href={route('procurement.purchase-orders.create')}
                    className="inline-flex h-8 items-center rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 shadow-sm transition"
                >
                    <svg className="mr-1.5 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                    </svg>
                    New Purchase Order
                </Link>
            }
        >
            <Head title="Procurement Dashboard" />

            <div className="space-y-3.5">
                <DashboardAlertBanner alerts={dashboardAlerts} />

                {/* Unified High-Density Telemetry Cockpit */}
                <section className="rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm space-y-2">
                    {/* Requisition Pipeline Tier */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-amber-500" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-amber-700">Pending PRs</p>
                                <p className="text-lg font-extrabold text-amber-900 leading-tight">{summary.pending_requests}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-blue-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-blue-700">In Processing</p>
                                <p className="text-lg font-extrabold text-blue-900 leading-tight">{summary.processing_requests}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Ongoing POs</p>
                                <p className="text-lg font-extrabold text-emerald-900 leading-tight">{summary.ongoing_procurement}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-slate-400" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Completed</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight">{summary.completed_procurements}</p>
                            </div>
                        </div>
                    </div>

                    {/* Financial Portfolio Tier */}
                    <div className="border-t border-slate-100 pt-2 grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-red-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-red-700">Total Spend</p>
                                <p className="text-base font-extrabold text-red-900 leading-tight truncate">{formatMoney(summary.total_purchase_order_amount)}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-slate-400" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Draft POs</p>
                                <p className="text-base font-extrabold text-slate-900 leading-tight">{summary.draft_purchase_orders}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-indigo-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-indigo-700">Placed POs</p>
                                <p className="text-base font-extrabold text-indigo-900 leading-tight">{summary.placed_purchase_orders}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-teal-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-teal-700">Active Suppliers</p>
                                <p className="text-base font-extrabold text-teal-900 leading-tight">{summary.active_supplier_count}</p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Compact Quick Access Cards */}
                <div className="grid gap-2.5 sm:grid-cols-3">
                    <Link
                        href={route('procurement.purchase-requests.index')}
                        className="group flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50/40 p-3 shadow-sm transition hover:border-amber-400 hover:bg-amber-50 hover:shadow"
                    >
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-white shadow-sm transition group-hover:bg-amber-600">
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </span>
                        <div className="min-w-0">
                            <p className="text-xs font-bold text-slate-900 group-hover:text-amber-800">Review PR Queue</p>
                            <p className="text-[11px] text-slate-500 truncate">{summary.pending_requests} pending approval</p>
                        </div>
                    </Link>

                    <Link
                        href={route('procurement.purchase-orders.create')}
                        className="group flex items-center gap-3 rounded-xl border border-red-200 bg-red-50/40 p-3 shadow-sm transition hover:border-red-400 hover:bg-red-50 hover:shadow"
                    >
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-600 text-white shadow-sm transition group-hover:bg-red-700">
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </span>
                        <div className="min-w-0">
                            <p className="text-xs font-bold text-slate-900 group-hover:text-red-700">Create Purchase Order</p>
                            <p className="text-[11px] text-slate-500 truncate">Generate PO from approved PR</p>
                        </div>
                    </Link>

                    <Link
                        href={route('procurement.suppliers.index')}
                        className="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-slate-300 hover:bg-slate-50/70 hover:shadow"
                    >
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition group-hover:bg-slate-200">
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </span>
                        <div className="min-w-0">
                            <p className="text-xs font-bold text-slate-900 group-hover:text-red-700">Manage Suppliers</p>
                            <p className="text-[11px] text-slate-500 truncate">{summary.active_supplier_count} registered vendors</p>
                        </div>
                    </Link>
                </div>

                <section className="grid gap-3.5 lg:grid-cols-2">
                    {/* Recent Purchase Requests */}
                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-2.5">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">Recent Purchase Requests</h3>
                                <p className="text-[11px] text-slate-500">Incoming office requisition submissions</p>
                            </div>
                            <Link href={route('procurement.purchase-requests.index')} className="text-xs font-semibold text-red-700 hover:text-red-800 hover:underline">
                                View All &rarr;
                            </Link>
                        </div>
                        <div className="border-b border-slate-100 bg-slate-50/60 p-2.5">
                            <div className="grid gap-2 grid-cols-1 sm:grid-cols-12">
                                <input
                                    id="procurement-request-search"
                                    type="text"
                                    value={requestSearch}
                                    onChange={(event) => setRequestSearch(event.target.value)}
                                    placeholder="Search PR ID, office, date"
                                    className="sm:col-span-7 h-8 rounded-lg border border-slate-300 px-2.5 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                />
                                <select
                                    id="procurement-request-status"
                                    value={requestStatus}
                                    onChange={(event) => setRequestStatus(event.target.value)}
                                    className="sm:col-span-3 h-8 rounded-lg border border-slate-300 px-2 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                >
                                    {REQUEST_STATUS_FILTERS.map((status) => (
                                        <option key={status} value={status}>
                                            {status === 'ALL' ? 'All PR statuses' : displayPurchaseRequestStatus(status)}
                                        </option>
                                    ))}
                                </select>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setRequestSearch('');
                                        setRequestStatus('ALL');
                                    }}
                                    className="sm:col-span-2 h-8 rounded-lg border border-slate-300 px-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                        <div className="divide-y divide-slate-100">
                            {filteredPurchaseRequests.length === 0 ? (
                                <p className="px-4 py-6 text-center text-xs text-slate-500">No purchase requests match filters.</p>
                            ) : (
                                filteredPurchaseRequests.slice(0, 5).map((request) => (
                                    <div key={request.purchase_request_id} className="flex items-center justify-between gap-3 px-4 py-2 hover:bg-slate-50/50 transition">
                                        <div className="min-w-0">
                                            <p className="text-xs font-bold text-red-700 leading-tight">{request.pr_number || `PR-${String(request.purchase_request_id).padStart(6, '0')}`}</p>
                                            <p className="text-[11px] text-slate-600 truncate">{request.office_name}</p>
                                        </div>
                                        <div className="text-right shrink-0">
                                            <span className={statusClass(request.status)}>{displayPurchaseRequestStatus(request.status)}</span>
                                            <p className="mt-0.5 text-[10px] text-slate-400">{formatDate(request.created_at)}</p>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {/* Recent Purchase Orders */}
                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-2.5">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">Recent Purchase Orders</h3>
                                <p className="text-[11px] text-slate-500">Awarded vendor purchase agreements</p>
                            </div>
                            <Link href={route('procurement.purchase-orders.index')} className="text-xs font-semibold text-red-700 hover:text-red-800 hover:underline">
                                View All &rarr;
                            </Link>
                        </div>
                        <div className="border-b border-slate-100 bg-slate-50/60 p-2.5">
                            <div className="grid gap-2 grid-cols-1 sm:grid-cols-12">
                                <input
                                    id="procurement-order-search"
                                    type="text"
                                    value={orderSearch}
                                    onChange={(event) => setOrderSearch(event.target.value)}
                                    placeholder="Search PO, supplier, amount"
                                    className="sm:col-span-7 h-8 rounded-lg border border-slate-300 px-2.5 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                />
                                <select
                                    id="procurement-order-status"
                                    value={orderStatus}
                                    onChange={(event) => setOrderStatus(event.target.value)}
                                    className="sm:col-span-3 h-8 rounded-lg border border-slate-300 px-2 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                >
                                    {ORDER_STATUS_FILTERS.map((status) => (
                                        <option key={status} value={status}>
                                            {status === 'ALL' ? 'All PO statuses' : displayPurchaseOrderStatus(status)}
                                        </option>
                                    ))}
                                </select>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setOrderSearch('');
                                        setOrderStatus('ALL');
                                    }}
                                    className="sm:col-span-2 h-8 rounded-lg border border-slate-300 px-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                        <div className="divide-y divide-slate-100">
                            {filteredPurchaseOrders.length === 0 ? (
                                <p className="px-4 py-6 text-center text-xs text-slate-500">No purchase orders match filters.</p>
                            ) : (
                                filteredPurchaseOrders.slice(0, 5).map((order) => (
                                    <div key={order.purchase_order_id} className="flex items-center justify-between gap-3 px-4 py-2 hover:bg-slate-50/50 transition">
                                        <div className="min-w-0">
                                            <p className="text-xs font-bold text-slate-900 leading-tight">{order.po_number}</p>
                                            <p className="text-[11px] text-slate-600 truncate">{order.supplier_name}</p>
                                        </div>
                                        <div className="text-right shrink-0">
                                            <p className="text-xs font-bold text-emerald-700">{formatMoney(order.total_amount)}</p>
                                            <p className="text-[10px] text-slate-400">{formatDate(order.created_at)}</p>
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
    helper,
}: {
    label: string;
    value: number;
    tone: 'amber' | 'blue' | 'green' | 'red';
    helper?: string;
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
            {helper ? <p className="mt-2 text-xs leading-5 text-slate-500">{helper}</p> : null}
        </article>
    );
}

function MiniKpi({ label, value }: { label: string; value: number | string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-xl font-bold text-slate-900">{value}</p>
        </div>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        OPEN: `${base} bg-amber-100 text-amber-700`,
        PROCESSING: `${base} bg-blue-100 text-blue-700`,
        COMPLETED: `${base} bg-emerald-100 text-emerald-700`,
        RETURNED_FOR_REVISION: `${base} bg-violet-100 text-violet-700`,
        REJECTED: `${base} bg-rose-100 text-rose-700`,
        DRAFT: `${base} bg-amber-100 text-amber-700`,
        PLACED: `${base} bg-blue-100 text-blue-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function displayPurchaseRequestStatus(status: string): string {
    const normalized = status.toUpperCase();
    const map: Record<string, string> = {
        OPEN: 'Pending Review',
        PROCESSING: 'Processing',
        COMPLETED: 'Completed',
        RETURNED_FOR_REVISION: 'Returned',
        REJECTED: 'Rejected',
    };

    return map[normalized] ?? status.replaceAll('_', ' ');
}

function displayPurchaseOrderStatus(status: string): string {
    const normalized = status.toUpperCase();
    const map: Record<string, string> = {
        DRAFT: 'Draft PO',
        PLACED: 'Issued PO',
        COMPLETED: 'Completed PO',
    };

    return map[normalized] ?? status.replaceAll('_', ' ');
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



