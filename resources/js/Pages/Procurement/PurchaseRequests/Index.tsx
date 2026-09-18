import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type PurchaseRequestRow = {
    purchase_request_id: number;
    pr_number?: string | null;
    supply_request_id?: number | null;
    status: string;
    created_at?: string;
    office?: { name?: string | null } | null;
    supply_request?: { request_code?: string | null } | null;
    items?: Array<{
        quantity_requested?: string | number;
        item?: {
            category?: {
                category_name?: string | null;
            } | null;
        } | null;
    }>;
    items_count?: number;
    items_sum_quantity_requested?: string | number | null;
    remarks?: string | null;
};

type Props = {
    purchaseRequests: {
        data: PurchaseRequestRow[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    filters?: {
        status?: string;
        search?: string;
    };
};

export default function PurchaseRequestsIndex({ purchaseRequests, filters }: Props) {
    const [status, setStatus] = useState(filters?.status ?? '');
    const [search, setSearch] = useState(filters?.search ?? '');
    const debouncedSearch = useDebouncedValue(search, 450);
    const hasMountedRef = useRef(false);

    useEffect(() => {
        setStatus(filters?.status ?? '');
        setSearch(filters?.search ?? '');
    }, [filters?.search, filters?.status]);

    const activeFilters = useMemo(() => {
        const nextFilters: { status?: string; search?: string } = {};

        if (status) {
            nextFilters.status = status;
        }

        if (debouncedSearch) {
            nextFilters.search = debouncedSearch;
        }

        return nextFilters;
    }, [status, debouncedSearch]);

    const { isRefreshing, refreshNow } = useSmartRefresh({
        only: ['purchaseRequests', 'filters'],
        refreshOnFocus: false,
        refreshOnMount: false,
    });

    useEffect(() => {
        if (!hasMountedRef.current) {
            hasMountedRef.current = true;

            return;
        }

        router.get(route('procurement.purchase-requests.index'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['purchaseRequests', 'filters'],
        });
    }, [activeFilters]);

    const resetFilters = () => {
        router.get(route('procurement.purchase-requests.index'));
    };

    return (
        <AuthenticatedLayout header="Request Review">
            <Head title="Request Review" />

            <div className="space-y-3">
                {/* Compact Control & Filter Header */}
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <h3 className="text-xs font-bold text-slate-800">Filter Review Queue</h3>
                            <span className="hidden text-[11px] text-slate-500 sm:inline">• Review procurement requests and move eligible ones to processing</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-[11px] text-slate-500">Showing {purchaseRequests.data.length} records</span>
                            <button
                                type="button"
                                onClick={() => void refreshNow()}
                                disabled={isRefreshing}
                                className="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60 transition shadow-2xs"
                            >
                                {isRefreshing ? 'Refreshing…' : 'Refresh Queue'}
                            </button>
                            <button
                                type="button"
                                onClick={resetFilters}
                                className="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                    <div className="mt-2 grid gap-2 sm:grid-cols-[13rem_minmax(0,1fr)]">
                        <select
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                            aria-label="Filter request review queue by status"
                            className="h-8 rounded-lg border-slate-300 pl-2.5 pr-8 py-1 text-xs"
                        >
                            <option value="">All PR statuses</option>
                            <option value="OPEN">Pending Review</option>
                            <option value="PROCESSING">Processing</option>
                            <option value="COMPLETED">Completed</option>
                            <option value="RETURNED_FOR_REVISION">Returned for Revision</option>
                            <option value="REJECTED">Rejected</option>
                        </select>

                        <input
                            type="text"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search request code, office, or PR ID..."
                            className="h-8 rounded-lg border-slate-300 px-2.5 py-1 text-xs"
                        />
                    </div>
                </section>

                {/* Table Container */}
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-xs sm:text-sm">
                        <thead className="sticky top-0 z-10 bg-slate-50 shadow-[0_1px_2px_rgba(0,0,0,0.05)]">
                            <tr>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Request ID</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Office</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Item / Category</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Quantity</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Date</th>
                                <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {purchaseRequests.data.map((row) => {
                                const firstCategory = row.items?.[0]?.item?.category?.category_name ?? 'Uncategorized';
                                const quantity = Number(row.items_sum_quantity_requested ?? 0);

                                return (
                                    <tr key={row.purchase_request_id} className="hover:bg-slate-50/60 transition-colors">
                                        <td className="px-3.5 py-2 font-semibold text-red-700">
                                            {(() => {
                                                const isConsolidated = Boolean(row.remarks && (row.remarks.includes('Consolidated PR') || /from \d+ supply requests/i.test(row.remarks)));
                                                const prNumber = row.pr_number || `PR-${String(row.purchase_request_id).padStart(6, '0')}`;
                                                return (
                                                    <div>
                                                        <div className="flex items-center gap-1.5 flex-wrap">
                                                            <span>{prNumber}</span>
                                                            {isConsolidated ? (
                                                                <span className="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-800">
                                                                    📦 Consolidated
                                                                </span>
                                                            ) : null}
                                                        </div>
                                                        {row.supply_request?.request_code ? (
                                                            <p className="text-[11px] font-normal text-slate-500">
                                                                {isConsolidated ? `Source: ${row.supply_request.request_code}` : row.supply_request.request_code}
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                );
                                            })()}
                                        </td>
                                        <td className="px-3.5 py-2 text-slate-700 font-medium">{row.office?.name ?? '-'}</td>
                                        <td className="px-3.5 py-2 text-slate-700">
                                            <div className="font-medium">{firstCategory}</div>
                                            <p className="text-[11px] text-slate-500">{row.items_count ?? 0} item(s)</p>
                                        </td>
                                        <td className="px-3.5 py-2 text-slate-700 font-semibold">{quantity.toFixed(2)}</td>
                                        <td className="px-3.5 py-2">
                                            <span className={statusClass(row.status)}>{displayPurchaseRequestStatus(row.status)}</span>
                                        </td>
                                        <td className="px-3.5 py-2 text-slate-600 text-xs">{formatDate(row.created_at)}</td>
                                        <td className="px-3.5 py-2 text-right">
                                            <div className="flex justify-end gap-2">
                                                <Link
                                                    href={route('procurement.purchase-requests.show', {
                                                        purchase_request: row.purchase_request_id,
                                                        status: status || undefined,
                                                        search: search || undefined,
                                                    })}
                                                    className="inline-flex items-center rounded border border-red-200 bg-red-50/50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition"
                                                >
                                                    View
                                                </Link>
                                                <Link
                                                    href={route('procurement.purchase-requests.show', {
                                                        purchase_request: row.purchase_request_id,
                                                        mode: 'review',
                                                        status: status || undefined,
                                                        search: search || undefined,
                                                    })}
                                                    className="inline-flex items-center rounded border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                                                >
                                                    Review
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                            {purchaseRequests.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No purchase requests available for review.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>

                {purchaseRequests.links && purchaseRequests.links.length > 3 && (
                    <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/70 px-3.5 py-2">
                        <span className="text-[11px] font-medium text-slate-500">
                            Showing {purchaseRequests.data.length} records
                        </span>
                        <PaginationNav links={purchaseRequests.links} preserveState={false} />
                    </div>
                )}
            </section>
        </div>
        </AuthenticatedLayout>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        OPEN: `${base} bg-amber-100 text-amber-700`,
        PROCESSING: `${base} bg-blue-100 text-blue-700`,
        COMPLETED: `${base} bg-emerald-100 text-emerald-700`,
        RETURNED_FOR_REVISION: `${base} bg-violet-100 text-violet-700`,
        APPROVED: `${base} bg-emerald-100 text-emerald-700`,
        REJECTED: `${base} bg-rose-100 text-rose-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function displayPurchaseRequestStatus(status: string): string {
    const map: Record<string, string> = {
        OPEN: 'Pending Review',
        PROCESSING: 'Processing',
        COMPLETED: 'Completed',
        RETURNED_FOR_REVISION: 'Returned for Revision',
        REJECTED: 'Rejected',
    };

    return map[status] ?? status.replaceAll('_', ' ');
}

function formatDate(value?: string) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}





