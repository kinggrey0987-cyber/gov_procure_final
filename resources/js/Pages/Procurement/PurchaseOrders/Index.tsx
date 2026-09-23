import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import PaginationNav from '@/Components/PaginationNav';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type PurchaseOrderRow = {
    purchase_order_id: number;
    po_number: string;
    status: string;
    total_amount: string | number;
    created_at?: string;
    supplier?: {
        supplier_name?: string | null;
    } | null;
    purchase_request?: {
        purchase_request_id: number;
        pr_number?: string | null;
        office?: {
            name?: string | null;
        } | null;
    } | null;
    deliveries_count?: number;
    is_zero_cost_replacement?: boolean;
    parent_po?: {
        purchase_order_id: number;
        po_number?: string | null;
    } | null;
};

type Props = {
    purchaseOrders: {
        data: PurchaseOrderRow[];
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

export default function PurchaseOrdersIndex({ purchaseOrders, filters }: Props) {
    const [status, setStatus] = useState(filters?.status ?? '');
    const [search, setSearch] = useState(filters?.search ?? '');
    const debouncedSearch = useDebouncedValue(search, 450);
    const hasMountedRef = useRef(false);

    const [poToIssue, setPoToIssue] = useState<PurchaseOrderRow | null>(null);
    const [issueRemarks, setIssueRemarks] = useState('');
    const [isIssuing, setIsIssuing] = useState(false);

    const handleConfirmIssue = () => {
        if (!poToIssue) return;
        setIsIssuing(true);
        router.post(route('procurement.purchase-orders.place', poToIssue.purchase_order_id), {
            remarks: issueRemarks,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setPoToIssue(null);
                setIssueRemarks('');
                setIsIssuing(false);
            },
            onError: () => {
                setIsIssuing(false);
            },
        });
    };

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

    useEffect(() => {
        if (!hasMountedRef.current) {
            hasMountedRef.current = true;

            return;
        }

        router.get(route('procurement.purchase-orders.index'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['purchaseOrders', 'filters'],
        });
    }, [activeFilters]);

    const resetFilters = () => {
        router.get(route('procurement.purchase-orders.index'));
    };

    return (
        <AuthenticatedLayout
            header="Purchase Orders"
            headerActions={
                <Link
                    href={route('procurement.purchase-orders.create')}
                    className="inline-flex h-8 items-center gap-1.5 rounded-lg bg-red-600 px-3 text-xs font-semibold text-white shadow-sm hover:bg-red-700 transition"
                >
                    <span className="text-sm leading-none">＋</span>
                    <span>Create PO</span>
                </Link>
            }
        >
            <Head title="Purchase Orders" />

            <div className="space-y-3">
                {/* Compact Control & Filter Header */}
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <h3 className="text-xs font-bold text-slate-800">Filter Purchase Orders</h3>
                            <span className="hidden text-[11px] text-slate-500 sm:inline">• Manage procurement execution stages and supplier fulfillment</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-[11px] text-slate-500">Showing {purchaseOrders.data.length} records</span>
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
                            aria-label="Filter purchase orders by status"
                            className="h-8 rounded-lg border-slate-300 pl-2.5 pr-8 py-1 text-xs"
                        >
                            <option value="">All PO statuses</option>
                            <option value="DRAFT">Draft PO</option>
                            <option value="PLACED">Issued PO</option>
                            <option value="COMPLETED">Completed PO</option>
                        </select>

                        <input
                            type="text"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search PO number, supplier, office..."
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
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">PO Number</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Supplier</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Linked Request</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Total Cost</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Date</th>
                                <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {purchaseOrders.data.map((row) => (
                                <tr key={row.purchase_order_id} className="hover:bg-slate-50/60 transition-colors">
                                    <td className="px-3.5 py-2 font-semibold text-red-700">
                                        <div className="flex items-center gap-1.5 flex-wrap">
                                            <span>{row.po_number}</span>
                                            {row.is_zero_cost_replacement && (
                                                <span className="inline-flex items-center gap-0.5 rounded-full bg-emerald-100 border border-emerald-300 px-2 py-0.5 text-[10px] font-black text-emerald-800 shadow-2xs uppercase tracking-wide">
                                                    🛡️ Warranty Replacement
                                                </span>
                                            )}
                                        </div>
                                        {row.parent_po && (
                                            <p className="text-[11px] text-slate-500 mt-0.5 font-normal">
                                                Ref Parent:{' '}
                                                <Link href={route('procurement.purchase-orders.show', row.parent_po.purchase_order_id)} className="text-blue-600 hover:underline font-medium">
                                                    {row.parent_po.po_number || `PO #${row.parent_po.purchase_order_id}`}
                                                </Link>
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-3.5 py-2 text-slate-700 font-medium">{row.supplier?.supplier_name ?? '-'}</td>
                                    <td className="px-3.5 py-2 text-slate-700">
                                        <div>{row.purchase_request?.pr_number || (row.purchase_request?.purchase_request_id ? `PR-${String(row.purchase_request.purchase_request_id).padStart(6, '0')}` : '-')}</div>
                                        <p className="text-[11px] text-slate-500">{row.purchase_request?.office?.name ?? '-'}</p>
                                    </td>
                                    <td className="px-3.5 py-2 font-semibold text-slate-800">
                                        {row.is_zero_cost_replacement ? (
                                            <span className="inline-flex items-center gap-1 rounded bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-xs font-bold text-emerald-700">
                                                ₱0.00 (Warranty)
                                            </span>
                                        ) : (
                                            formatMoney(Number(row.total_amount || 0))
                                        )}
                                    </td>
                                    <td className="px-3.5 py-2">
                                        <span className={statusClass(row.status)}>{displayPurchaseOrderStatus(row.status)}</span>
                                    </td>
                                    <td className="px-3.5 py-2 text-slate-600 text-xs">{formatDate(row.created_at)}</td>
                                    <td className="px-3.5 py-2 text-right">
                                        <div className="flex flex-wrap justify-end gap-1.5">
                                            <Link href={route('procurement.purchase-orders.show', row.purchase_order_id)} className="rounded border border-red-200 bg-red-50/50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition">
                                                View
                                            </Link>
                                            {row.status === 'DRAFT' ? (
                                                <Link href={route('procurement.purchase-orders.edit', row.purchase_order_id)} className="rounded border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">
                                                    Edit PO
                                                </Link>
                                            ) : null}
                                            {row.status === 'DRAFT' ? (
                                                <Link href={route('procurement.purchase-orders.items.index', row.purchase_order_id)} className="rounded border border-red-200 bg-red-50/50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition">
                                                    Manage Items
                                                </Link>
                                            ) : null}
                                            {row.status === 'DRAFT' && row.is_zero_cost_replacement ? (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setIssueRemarks('');
                                                        setPoToIssue(row);
                                                    }}
                                                    className="rounded bg-emerald-700 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-800 transition shadow-2xs"
                                                >
                                                    Issue PO
                                                </button>
                                            ) : null}
                                            <span
                                                className={`rounded px-2.5 py-1 text-xs font-semibold ${Number(row.deliveries_count ?? 0) > 0 ? 'border border-blue-200 bg-blue-50 text-blue-700' : 'border border-slate-200 bg-slate-50 text-slate-500'}`}
                                            >
                                                Deliveries: {Number(row.deliveries_count ?? 0)}
                                            </span>
                                            <Link href={route('procurement.purchase-orders.records')} className="rounded border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-100 transition">
                                                Tracking Records
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {purchaseOrders.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No purchase orders found.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>

                {purchaseOrders.links && purchaseOrders.links.length > 3 && (
                    <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/70 px-3.5 py-2">
                        <span className="text-[11px] font-medium text-slate-500">
                            Showing {purchaseOrders.data.length} records
                        </span>
                        <PaginationNav links={purchaseOrders.links} preserveState={false} />
                    </div>
                )}
            </section>
        </div>

        <Modal show={Boolean(poToIssue)} maxWidth="md" onClose={() => !isIssuing && setPoToIssue(null)}>
            <div className="p-6">
                <div className="flex items-center gap-2">
                    <span className="text-xl">🛡️</span>
                    <p className="text-xs font-bold uppercase tracking-wider text-emerald-800">Issue Purchase Order</p>
                </div>
                <h3 className="mt-2 text-lg font-bold text-slate-900">
                    Issue Zero-Cost Warranty Replacement PO {poToIssue?.po_number}?
                </h3>
                <p className="mt-2 text-sm text-slate-600">
                    This will formally place the purchase order under supplier warranty rectification (₱0.00) and generate the incoming delivery record for inspection.
                </p>

                <div className="mt-4">
                    <label className="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">
                        Remarks (Optional)
                    </label>
                    <textarea
                        value={issueRemarks}
                        onChange={(e) => setIssueRemarks(e.target.value)}
                        placeholder="Add any instructions or warranty reference notes…"
                        className="w-full rounded-lg border border-slate-300 p-2 text-sm focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600"
                        rows={2}
                    />
                </div>

                <div className="mt-6 flex flex-wrap justify-end gap-3">
                    <button
                        type="button"
                        onClick={() => setPoToIssue(null)}
                        disabled={isIssuing}
                        className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={handleConfirmIssue}
                        disabled={isIssuing}
                        className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60 flex items-center gap-1.5"
                    >
                        {isIssuing ? 'Issuing PO…' : 'Confirm & Issue PO'}
                    </button>
                </div>
            </div>
        </Modal>
        </AuthenticatedLayout>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        DRAFT: `${base} bg-amber-100 text-amber-700`,
        PLACED: `${base} bg-blue-100 text-blue-700`,
        COMPLETED: `${base} bg-emerald-100 text-emerald-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function displayPurchaseOrderStatus(status: string): string {
    const map: Record<string, string> = {
        DRAFT: 'Draft PO',
        PLACED: 'Issued PO',
        COMPLETED: 'Completed PO',
    };

    return map[status] ?? status.replaceAll('_', ' ');
}

function formatDate(value?: string) {
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

