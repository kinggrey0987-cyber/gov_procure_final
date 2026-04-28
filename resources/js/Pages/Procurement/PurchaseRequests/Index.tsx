import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type PurchaseRequestRow = {
    purchase_request_id: number;
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

    const { isRefreshing, lastRefreshedAt } = useSmartRefresh({
        only: ['purchaseRequests', 'filters'],
        refreshOnFocus: true,
        refreshOnMount: false,
        pollIntervalMs: 30000,
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
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Request Review</h2>}>
            <Head title="Request Review" />

            <div className="space-y-4 py-8">
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-sm text-slate-600">Review procurement requests forwarded from office supply demand and move eligible ones to processing.</p>
                    <p className="mt-2 text-xs text-slate-500">
                        {isRefreshing
                            ? 'Refreshing request queue...'
                            : lastRefreshedAt
                            ? `Updated ${lastRefreshedAt.toLocaleTimeString()}`
                            : 'Auto-refresh every 30 seconds while this page is open'}
                    </p>
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 className="text-sm font-semibold text-slate-900">Filter request review queue</h3>
                            <p className="text-xs text-slate-500">Filters update automatically; no apply button required.</p>
                        </div>
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Reset
                        </button>
                    </div>
                    <div className="grid gap-3 md:grid-cols-5">
                        <select
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                            className="rounded-lg border-slate-300 text-sm"
                        >
                            <option value="">All Statuses</option>
                            <option value="OPEN">Open</option>
                            <option value="PROCESSING">Processing</option>
                            <option value="RETURNED_FOR_REVISION">Returned for Revision</option>
                            <option value="APPROVED">Approved</option>
                            <option value="REJECTED">Rejected</option>
                        </select>

                        <input
                            type="text"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search request code, office, or PR ID"
                            className="md:col-span-2 rounded-lg border-slate-300 text-sm"
                        />

                        <p className="flex items-center text-xs text-slate-500 md:col-span-2">Showing 5 records per page. Use pagination for the next 5.</p>
                    </div>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Request ID</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Office</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Item / Category</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Quantity</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Date</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {purchaseRequests.data.map((row) => {
                                const firstCategory = row.items?.[0]?.item?.category?.category_name ?? 'Uncategorized';
                                const quantity = Number(row.items_sum_quantity_requested ?? 0);

                                return (
                                    <tr key={row.purchase_request_id} className="hover:bg-slate-50/60">
                                        <td className="px-4 py-3 font-semibold text-red-700">
                                            {row.supply_request?.request_code || `PR-${String(row.purchase_request_id).padStart(6, '0')}`}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">{row.office?.name ?? '-'}</td>
                                        <td className="px-4 py-3 text-slate-700">
                                            {firstCategory}
                                            <p className="text-xs text-slate-500">{row.items_count ?? 0} item(s)</p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">{quantity.toFixed(2)}</td>
                                        <td className="px-4 py-3">
                                            <span className={statusClass(row.status)}>{row.status}</span>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{formatDate(row.created_at)}</td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-3">
                                                <Link
                                                    href={route('procurement.purchase-requests.show', {
                                                        purchase_request: row.purchase_request_id,
                                                        status: status || undefined,
                                                        search: search || undefined,
                                                    })}
                                                    className="text-sm font-semibold text-red-700 hover:text-red-900"
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
                                                    className="text-sm font-semibold text-slate-700 hover:text-slate-900"
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
                                    <td colSpan={7} className="px-4 py-10 text-center text-sm text-slate-500">
                                        No purchase requests available for review.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>

                <PaginationNav links={purchaseRequests.links} preserveState={false} />
            </div>
        </AuthenticatedLayout>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        OPEN: `${base} bg-amber-100 text-amber-700`,
        PROCESSING: `${base} bg-blue-100 text-blue-700`,
        RETURNED_FOR_REVISION: `${base} bg-violet-100 text-violet-700`,
        APPROVED: `${base} bg-emerald-100 text-emerald-700`,
        REJECTED: `${base} bg-rose-100 text-rose-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function formatDate(value?: string) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}
