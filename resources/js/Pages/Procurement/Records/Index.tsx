import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type RecordRow = {
    purchase_order_id: number;
    po_number: string;
    status: string;
    created_at?: string;
    supplier?: {
        supplier_name?: string | null;
    } | null;
    purchase_request?: {
        office?: {
            name?: string | null;
        } | null;
    } | null;
};

type Props = {
    records: {
        data: RecordRow[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    filters: {
        status?: string;
    };
};

const statuses = ['PLACED', 'COMPLETED'];

export default function ProcurementRecordsIndex({ records, filters }: Props) {
    const [status, setStatus] = useState(filters.status ?? '');

    useEffect(() => {
        setStatus(filters.status ?? '');
    }, [filters.status]);

    const updateStatus = (nextStatus: string) => {
        setStatus(nextStatus);

        router.get(
            route('procurement.purchase-orders.records'),
            nextStatus ? { status: nextStatus } : {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['records', 'filters'],
            },
        );
    };

    return (
        <AuthenticatedLayout header="Procurement Records">
            <Head title="Procurement Records" />

            <div className="space-y-2 lg:h-[calc(100vh-8.5rem)] flex flex-col justify-between overflow-y-auto lg:overflow-hidden">
                <section className="shrink-0 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3.5 py-2 shadow-sm">
                    <div className="flex items-center gap-2">
                        <h3 className="text-xs font-bold text-slate-800">Filter Records</h3>
                        <span className="hidden text-[11px] text-slate-500 sm:inline">• Issued purchase order history for audit and traceability</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <label htmlFor="status" className="text-xs font-medium text-slate-600">
                            Status:
                        </label>
                        <select
                            id="status"
                            value={status}
                            onChange={(event) => updateStatus(event.target.value)}
                            className="h-8 rounded-lg border-slate-300 py-1 text-xs"
                        >
                            <option value="">All PO records</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {displayPurchaseOrderStatus(status)}
                                </option>
                            ))}
                        </select>
                    </div>
                </section>

                <section className="flex-1 min-h-0 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-xs sm:text-sm">
                        <thead className="sticky top-0 z-10 bg-slate-50 shadow-[0_1px_2px_rgba(0,0,0,0.05)]">
                            <tr>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">PO Number</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Supplier</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Office</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Completion Date</th>
                                <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {records.data.map((record) => (
                                <tr key={record.purchase_order_id} className="hover:bg-slate-50/60 transition-colors">
                                    <td className="px-3.5 py-2 font-semibold text-red-700">{record.po_number}</td>
                                    <td className="px-3.5 py-2 text-slate-700 font-medium">{record.supplier?.supplier_name ?? '-'}</td>
                                    <td className="px-3.5 py-2 text-slate-700">{record.purchase_request?.office?.name ?? '-'}</td>
                                    <td className="px-3.5 py-2">
                                        <span className={statusClass(record.status)}>{displayPurchaseOrderStatus(record.status)}</span>
                                    </td>
                                    <td className="px-3.5 py-2 text-slate-600 text-xs">{formatDate(record.created_at)}</td>
                                    <td className="px-3.5 py-2 text-right">
                                        <div className="flex justify-end gap-2">
                                            <Link href={route('procurement.purchase-orders.show', record.purchase_order_id)} className="rounded border border-red-200 bg-red-50/50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition">
                                                View
                                            </Link>
                                            <Link
                                                href={route('procurement.documents.index', {
                                                    module: 'purchase_orders',
                                                    related_module: 'purchase_orders',
                                                    related_reference_id: record.purchase_order_id,
                                                    search: record.po_number,
                                                })}
                                                className="rounded border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                                            >
                                                Documents
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {records.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No procurement records found for the selected filter.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>

                <div className="shrink-0 pt-0.5">
                    <PaginationNav links={records.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        PLACED: `${base} bg-blue-100 text-blue-700`,
        COMPLETED: `${base} bg-emerald-100 text-emerald-700`,
        CANCELLED: `${base} bg-rose-100 text-rose-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function displayPurchaseOrderStatus(status: string): string {
    const map: Record<string, string> = {
        PLACED: 'Issued PO',
        COMPLETED: 'Completed PO',
        CANCELLED: 'Cancelled PO',
    };

    return map[status] ?? status.replaceAll('_', ' ');
}

function formatDate(value?: string) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}




