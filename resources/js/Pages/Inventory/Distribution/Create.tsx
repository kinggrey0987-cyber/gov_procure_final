import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

type EligibleRow = {
    supply_request_id: number;
    request_code: string;
    office_id: number;
    office_name: string;
    delivery_id: number;
    delivery_code: string;
    receipt_status: string;
};

type Props = {
    eligibleRows: EligibleRow[];
};

export default function DistributionCreate({ eligibleRows }: Props) {
    const form = useForm({
        supply_request_id: 0,
        delivery_id: 0,
        office_id: 0,
        release_date: '',
        storage_location: '',
        notes: '',
    });

    const rows = useMemo(() => eligibleRows, [eligibleRows]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Supply Distribution</h2>
                    <Link href={route('inventory.distribution-records.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Distribution Records
                    </Link>
                </div>
            }
        >
            <Head title="Supply Distribution" />
            <div className="space-y-6 py-8">
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-2xl font-semibold text-slate-900">Supply Distribution</h3>
                        <p className="mt-1 text-lg text-slate-500">
                            Process stock-ready office requests and release supplies to offices - {rows.length} request(s) ready
                        </p>
                        <p className="mt-2 text-sm text-slate-500">Use Claims and Adjustments only for post-release discrepancies, losses, or damages.</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Request ID</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Office</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Delivery</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                    <th className="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {rows.map((row) => (
                                    <tr key={`${row.supply_request_id}-${row.delivery_id}`} className="hover:bg-slate-50/60">
                                        <td className="px-4 py-3 font-semibold text-red-700">{row.request_code}</td>
                                        <td className="px-4 py-3 text-slate-700">{row.office_name}</td>
                                        <td className="px-4 py-3 text-slate-700">{row.delivery_code}</td>
                                        <td className="px-4 py-3">
                                            <span className="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold uppercase text-red-700">
                                                READY FOR RELEASE
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    form.transform((data) => ({
                                                        ...data,
                                                        supply_request_id: row.supply_request_id,
                                                        delivery_id: row.delivery_id,
                                                        office_id: row.office_id,
                                                    }));
                                                    form.post(route('inventory.distribution-records.store'));
                                                }}
                                                disabled={form.processing}
                                                className="rounded-lg border border-emerald-600 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50"
                                            >
                                                {form.processing ? 'Processing...' : 'Process Release'}
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                                {rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-8 text-center text-sm text-slate-500">
                                            No submitted supply requests with verified deliveries are ready for distribution.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
