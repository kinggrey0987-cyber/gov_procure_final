import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type ClaimRow = {
    claims_id: number;
    claim_code: string;
    claim_type: string;
    status: string;
    office?: { name?: string | null } | null;
    office_id?: number | string;
};

type Props = {
    claims: {
        data: ClaimRow[];
    };
};

export default function ClaimIndex({ claims }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Claims and Adjustments</h2>
                    <Link href={route('inventory.claims.create')} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                        File Claim
                    </Link>
                </div>
            }
        >
            <Head title="Claims" />
            <div className="py-8">
                <div className="mb-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-sm text-slate-600">
                        Use this page only for exception handling after release: damaged, missing, or disputed distributed supplies.
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Code</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Type</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Office</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {claims.data.map((row) => (
                                <tr key={row.claims_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 font-mono text-xs text-slate-700">{row.claim_code}</td>
                                    <td className="px-4 py-3 text-slate-700">{row.claim_type}</td>
                                    <td className="px-4 py-3">
                                        <span className={statusClass(row.status)}>{row.status}</span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{row.office?.name ?? row.office_id ?? '-'}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={route('inventory.claims.show', row.claims_id)} className="text-sm font-semibold text-red-700 hover:text-red-900">
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
        FILED: `${base} bg-amber-100 text-amber-700`,
        IN_REVIEW: `${base} bg-blue-100 text-blue-700`,
        RESOLVED: `${base} bg-emerald-100 text-emerald-700`,
        REJECTED: `${base} bg-rose-100 text-rose-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}
