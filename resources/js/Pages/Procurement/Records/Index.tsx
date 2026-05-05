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

const statuses = ['PLACED', 'COMPLETED', 'CANCELLED'];

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
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Procurement Records</h2>}>
            <Head title="Procurement Records" />

            <div className="space-y-4 py-8">
                <section className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div>
                        <p className="text-sm text-slate-600">Completed and finalized procurement history for audit and traceability.</p>
                        <p className="mt-1 text-xs text-slate-500">Filters update automatically and results are limited to 5 records per page.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <label htmlFor="status" className="text-sm text-slate-600">
                            Filter by status
                        </label>
                        <select
                            id="status"
                            value={status}
                            onChange={(event) => updateStatus(event.target.value)}
                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">All</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {status.replaceAll('_', ' ')}
                                </option>
                            ))}
                        </select>
                    </div>
                </section>

                <section className="responsive-table-wrap rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">PO Number</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Supplier</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Office</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Completion Date</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {records.data.map((record) => (
                                <tr key={record.purchase_order_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 font-semibold text-red-700">{record.po_number}</td>
                                    <td className="px-4 py-3 text-slate-700">{record.supplier?.supplier_name ?? '-'}</td>
                                    <td className="px-4 py-3 text-slate-700">{record.purchase_request?.office?.name ?? '-'}</td>
                                    <td className="px-4 py-3">
                                        <span className={statusClass(record.status)}>{record.status.replaceAll('_', ' ')}</span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{formatDate(record.created_at)}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-3">
                                            <Link href={route('procurement.purchase-orders.show', record.purchase_order_id)} className="text-sm font-semibold text-red-700 hover:text-red-900">
                                                View
                                            </Link>
                                            <Link href={route('procurement.purchase-orders.show', record.purchase_order_id)} className="text-sm font-semibold text-slate-700 hover:text-slate-900">
                                                Export
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {records.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-sm text-slate-500">
                                        No procurement records found for the selected filter.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>

                <div>
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

function formatDate(value?: string) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}





