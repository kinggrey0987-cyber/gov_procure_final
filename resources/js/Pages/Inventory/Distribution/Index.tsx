import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type RecordRow = {
    distribution_record_id: number;
    distribution_code: string;
    status: string;
    release_date?: string | null;
    supply_request?: { request_code?: string | null } | null;
    items_count: number;
    claims_count: number;
    office?: { name?: string | null } | null;
    office_id?: number | string;
};

type Props = {
    records: {
        data: RecordRow[];
    };
};

export default function DistributionIndex({ records }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Distribution Records</h2>
                    <Link href={route('inventory.distribution-records.create')} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                        Open Supply Distribution
                    </Link>
                </div>
            }
        >
            <Head title="Distribution Records" />
            <div className="py-8">
                <div className="mb-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-sm text-slate-600">
                        This page is for historical distribution records and audit viewing only. Operational release processing is done in Supply Distribution.
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Code</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Linked Office Request</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Office</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Release Date</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Items / Claims</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {records.data.map((row) => (
                                <tr key={row.distribution_record_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 font-mono text-xs text-slate-700">{row.distribution_code}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-slate-700">{row.supply_request?.request_code ?? '-'}</td>
                                    <td className="px-4 py-3 text-slate-700">{row.office?.name ?? row.office_id ?? '-'}</td>
                                    <td className="px-4 py-3">
                                        <span className={statusClass(row.status)}>{displayDistributionStatus(row.status)}</span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{row.release_date || '-'}</td>
                                    <td className="px-4 py-3 text-slate-600">{row.items_count} released / {row.claims_count} claim(s)</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={route('inventory.distribution-records.show', row.distribution_record_id)}
                                            className="text-sm font-semibold text-red-700 hover:text-red-900"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        DRAFT: `${base} bg-slate-100 text-slate-700`,
        RELEASED: `${base} bg-blue-100 text-blue-700`,
        RECEIVED: `${base} bg-emerald-100 text-emerald-700`,
        CANCELLED: `${base} bg-rose-100 text-rose-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function displayDistributionStatus(status: string): string {
    const map: Record<string, string> = {
        RELEASED: 'Released to Office',
        RECEIVED: 'Received by Office',
    };

    return map[status] ?? status;
}
