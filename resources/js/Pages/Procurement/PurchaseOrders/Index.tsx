import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

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
        office?: {
            name?: string | null;
        } | null;
    } | null;
    deliveries_count?: number;
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

    useEffect(() => {
        setStatus(filters?.status ?? '');
        setSearch(filters?.search ?? '');
    }, [filters?.search, filters?.status]);

    const activeFilters = useMemo(
        () => ({
            status,
            search: debouncedSearch,
        }),
        [status, debouncedSearch],
    );

    useEffect(() => {
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
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Purchase Orders</h2>}>
            <Head title="Purchase Orders" />

            <div className="space-y-4 py-8">
                <section className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-600">Manage procurement execution stages after request review and supplier assignment.</p>
                    <Link href={route('procurement.purchase-orders.create')} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                        Create PO
                    </Link>
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="grid gap-3 md:grid-cols-5">
                        <select
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                            className="rounded-lg border-slate-300 text-sm"
                        >
                            <option value="">All Statuses</option>
                            <option value="DRAFT">Draft</option>
                            <option value="PLACED">Placed</option>
                            <option value="COMPLETED">Completed</option>
                        </select>

                        <input
                            type="text"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search PO number, supplier, office"
                            className="md:col-span-2 rounded-lg border-slate-300 text-sm"
                        />

                        <button
                            type="button"
                            onClick={resetFilters}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 md:col-start-5"
                        >
                            Reset
                        </button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">PO Number</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Supplier</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Linked Request</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Total Cost</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Date</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {purchaseOrders.data.map((row) => (
                                <tr key={row.purchase_order_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 font-semibold text-red-700">{row.po_number}</td>
                                    <td className="px-4 py-3 text-slate-700">{row.supplier?.supplier_name ?? '-'}</td>
                                    <td className="px-4 py-3 text-slate-700">
                                        {row.purchase_request?.purchase_request_id ? `PR-${String(row.purchase_request.purchase_request_id).padStart(6, '0')}` : '-'}
                                        <p className="text-xs text-slate-500">{row.purchase_request?.office?.name ?? '-'}</p>
                                    </td>
                                    <td className="px-4 py-3 font-semibold text-slate-800">{formatMoney(Number(row.total_amount || 0))}</td>
                                    <td className="px-4 py-3">
                                        <span className={statusClass(row.status)}>{row.status}</span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{formatDate(row.created_at)}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex flex-wrap justify-end gap-2">
                                            <Link href={route('procurement.purchase-orders.show', row.purchase_order_id)} className="rounded border border-red-300 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-50">
                                                View
                                            </Link>
                                            {row.status === 'DRAFT' ? (
                                                <Link href={route('procurement.purchase-orders.edit', row.purchase_order_id)} className="rounded border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                    Update Header
                                                </Link>
                                            ) : null}
                                            {row.status === 'DRAFT' ? (
                                                <Link href={route('procurement.purchase-orders.items.index', row.purchase_order_id)} className="rounded border border-emerald-300 px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">
                                                    Manage Items
                                                </Link>
                                            ) : null}
                                            <span
                                                className={`rounded px-3 py-1 text-xs font-semibold ${Number(row.deliveries_count ?? 0) > 0 ? 'border border-blue-300 text-blue-700' : 'border border-slate-200 text-slate-500'}`}
                                            >
                                                Deliveries: {Number(row.deliveries_count ?? 0)}
                                            </span>
                                            <Link href={route('procurement.purchase-orders.records')} className="rounded border border-amber-300 px-3 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50">
                                                Tracking Records
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {purchaseOrders.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-10 text-center text-sm text-slate-500">
                                        No purchase orders found.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>

                <PaginationNav links={purchaseOrders.links} />
            </div>
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
