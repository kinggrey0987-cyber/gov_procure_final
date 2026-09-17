import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type EligibleRow = {
    supply_request_id: number;
    request_code: string;
    office_id: number;
    office_name: string;
    release_basis: 'AVAILABLE_INVENTORY' | 'VERIFIED_DELIVERY';
    release_basis_label: string;
    delivery_id: number | null;
    delivery_code: string | null;
    receipt_status: string | null;
    releasable_reason?: string | null;
};

type Props = {
    eligibleRows: {
        data: EligibleRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    focusSupplyRequestId?: number | null;
    focusReleasableReason?: string | null;
};

export default function DistributionCreate({ eligibleRows, focusSupplyRequestId = null, focusReleasableReason = null }: Props) {
    const [processingRowKey, setProcessingRowKey] = useState<string | null>(null);
    const form = useForm({
        supply_request_id: 0,
        delivery_id: null as number | null,
        office_id: 0,
        release_date: '',
        storage_location: '',
        notes: '',
    });

    const rows = useMemo(() => eligibleRows.data, [eligibleRows.data]);
    const focusedRowExists = focusSupplyRequestId ? rows.some((row) => row.supply_request_id === focusSupplyRequestId) : false;

    const processRelease = (row: EligibleRow) => {
        const rowKey = `${row.supply_request_id}-${row.delivery_id ?? 'inventory'}`;
        setProcessingRowKey(rowKey);

        form.transform((data) => ({
            ...data,
            supply_request_id: row.supply_request_id,
            delivery_id: row.delivery_id,
            office_id: row.office_id,
        }));

        form.post(route('inventory.distribution-records.store'), {
            onFinish: () => setProcessingRowKey(null),
        });
    };

    return (
        <AuthenticatedLayout
            header="Supply Distribution"
            headerActions={
                <div className="flex items-center gap-2">
                    <Link
                        href={route('inventory.office-supply-requests.index')}
                        className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs"
                    >
                        &larr; Office Supply Requests
                    </Link>
                    <Link
                        href={route('inventory.distribution-records.index')}
                        className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs"
                    >
                        Distribution Records
                    </Link>
                </div>
            }
        >
            <Head title="Supply Distribution" />
            <div className="space-y-3">
                {focusSupplyRequestId ? (
                    <div className={`rounded-xl border p-3 text-xs shadow-xs ${focusedRowExists ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900'}`}>
                        {focusedRowExists
                            ? `Focused release context loaded for request #${focusSupplyRequestId}. Continue with Process Release below.`
                            : focusReleasableReason
                                ? `Request #${focusSupplyRequestId} is not releasable yet: ${focusReleasableReason}`
                                : `Request #${focusSupplyRequestId} is not currently releasable in Supply Distribution.`}
                    </div>
                ) : null}

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="border-b border-slate-200 bg-slate-50/60 px-4 py-2.5 flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <h3 className="text-xs font-bold text-slate-900">Stock-Ready Releasable Requests</h3>
                            <span className="rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800">
                                {eligibleRows.total} ready
                            </span>
                        </div>
                        <p className="text-[11px] text-slate-500">Release office supplies directly from warehouse stock</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-xs">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Request ID</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Office</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Release Basis</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Source Context</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                    <th className="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {rows.map((row) => {
                                    const rowKey = `${row.supply_request_id}-${row.delivery_id ?? 'inventory'}`;
                                    const isProcessingThisRow = processingRowKey === rowKey;

                                    return (
                                    <tr key={rowKey} className={`hover:bg-slate-50/60 ${focusSupplyRequestId === row.supply_request_id ? 'bg-emerald-50/60' : ''}`}>
                                        <td className="px-4 py-3 font-semibold text-red-700">{row.request_code}</td>
                                        <td className="px-4 py-3 text-slate-700">{row.office_name}</td>
                                        <td className="px-4 py-3 text-slate-700">{row.release_basis_label}</td>
                                        <td className="px-4 py-3 text-slate-700">
                                            {row.release_basis === 'VERIFIED_DELIVERY'
                                                ? row.delivery_code ?? 'Verified delivery'
                                                : 'Current inventory balance'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold uppercase text-red-700">
                                                READY FOR RELEASE
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() => processRelease(row)}
                                                disabled={isProcessingThisRow}
                                                className="rounded-lg border border-emerald-600 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50"
                                            >
                                                {isProcessingThisRow ? 'Processing...' : 'Process Release'}
                                            </button>
                                        </td>
                                    </tr>
                                    );
                                })}
                                {rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-sm text-slate-500">
                                            No submitted supply requests are releasable right now. Stock-ready requests appear as From Available Inventory, while procurement-backed requests appear once delivery is verified.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-slate-200 bg-slate-50/70 px-3.5 py-2 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs text-slate-500 font-medium">
                            Showing <span className="font-semibold text-slate-700">{rows.length}</span> of <span className="font-semibold text-slate-700">{eligibleRows.total}</span> releasable requests
                        </p>
                        <PaginationNav links={eligibleRows.links} preserveState={false} />
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}





