import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import { Head, Link, router } from '@inertiajs/react';

type ReadyRecord = {
    distribution_record_id: number;
    request_id: string;
    request_date?: string | null;
    items_summary: string;
    items_count: number;
    location: string;
    ready_since?: string | null;
    status: string;
    supply_request_id: number;
    is_claim_replacement?: boolean;
    claim_code?: string | null;
    claim_id?: number | null;
    has_existing_claim?: boolean;
    existing_claim_code?: string | null;
    existing_claim_id?: number | null;
    is_claim_expired?: boolean;
};

type Props = {
    records: {
        data: ReadyRecord[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
};

export default function SuppliesReadyForClaimIndex({ records }: Props) {
    return (
        <AuthenticatedLayout header="Supplies Ready for Claim">
            <Head title="Supplies Ready for Claim" />

            <div className="space-y-3">
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white px-3.5 py-2 shadow-sm flex items-center justify-between">
                    <p className="text-xs text-slate-600">Confirm receipt first, then file claims from the received supply request.</p>
                    <span className="text-[11px] text-slate-500 font-medium">Showing {records.data.length} records</span>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    {records.data.length === 0 ? (
                        <div className="px-4 py-8 text-center text-sm text-slate-600">No released supply records available for your office yet.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-xs">
                                <thead className="bg-slate-50 shadow-xs">
                                    <tr>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Request ID</th>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Request Date</th>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Items</th>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Storage Location</th>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Ready Since</th>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                        <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {records.data.map((record) => (
                                        <tr key={record.distribution_record_id} className="hover:bg-slate-50/60 transition-colors">
                                            <td className="px-3.5 py-2 font-semibold text-red-700">
                                                <div className="flex items-center gap-1.5 flex-wrap">
                                                    {record.claim_code ? (
                                                        <Link
                                                            href={record.claim_id ? route('office.claims.show', record.claim_id) : '#'}
                                                            className="font-bold text-red-700 hover:text-red-900 underline decoration-red-300"
                                                        >
                                                            {record.claim_code}
                                                        </Link>
                                                    ) : (
                                                        <span>{record.request_id}</span>
                                                    )}
                                                    {record.is_claim_replacement && (
                                                        <span className="inline-flex items-center gap-0.5 rounded-full bg-amber-100 border border-amber-300 px-2 py-0.5 text-[10px] font-black text-amber-800 shadow-2xs uppercase">
                                                            🛡️ Claim Replacement
                                                        </span>
                                                    )}
                                                    {record.claim_code && record.request_id && record.request_id !== 'N/A' && (
                                                        <span className="text-[10px] text-slate-400 font-normal">({record.request_id})</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-3.5 py-2 text-slate-700">{record.request_date ?? '-'}</td>
                                            <td className="px-3.5 py-2 text-slate-700">
                                                <p className="font-medium">{record.items_count} item(s)</p>
                                                <p className="text-[11px] text-slate-500">{record.items_summary}</p>
                                            </td>
                                            <td className="px-3.5 py-2 text-slate-700 font-medium">{record.location}</td>
                                            <td className="px-3.5 py-2 text-slate-700">{record.ready_since ?? '-'}</td>
                                            <td className="px-3.5 py-2">
                                                <StatusBadge status={record.status} />
                                            </td>
                                            <td className="px-3.5 py-2 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    {record.status.toUpperCase() === 'RELEASED' ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                if (record.is_claim_replacement && record.claim_id) {
                                                                    router.post(route('office.claims.verify-replacement', record.claim_id), {}, {
                                                                        preserveScroll: true,
                                                                    });
                                                                } else {
                                                                    router.post(route('office.distribution-records.receive', record.distribution_record_id), {}, {
                                                                        preserveScroll: true,
                                                                    });
                                                                }
                                                            }}
                                                            className="rounded bg-red-700 px-2.5 py-1 text-xs font-semibold text-white hover:bg-red-800 transition"
                                                        >
                                                            Confirm Received
                                                        </button>
                                                    ) : null}
                                                    {record.is_claim_replacement && record.claim_id ? (
                                                        <Link
                                                            href={route('office.claims.show', record.claim_id)}
                                                            className="rounded border border-red-200 bg-red-50/50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition"
                                                        >
                                                            View Claim
                                                        </Link>
                                                    ) : (
                                                        <Link
                                                            href={route('office.distribution-records.show', record.distribution_record_id)}
                                                            className="rounded border border-red-200 bg-red-50/50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition"
                                                        >
                                                            View
                                                        </Link>
                                                    )}
                                                    {record.status.toUpperCase() === 'RECEIVED' ? (
                                                        record.is_claim_replacement ? (
                                                            <span className="rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700">
                                                                Replacement Verified
                                                            </span>
                                                        ) : record.has_existing_claim ? (
                                                            <Link
                                                                href={record.existing_claim_id ? route('office.claims.show', record.existing_claim_id) : route('office.claims.index')}
                                                                className="rounded border border-indigo-200 bg-indigo-50/70 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition"
                                                            >
                                                                Claim Filed {record.existing_claim_code ? `(${record.existing_claim_code})` : ''}
                                                            </Link>
                                                        ) : record.is_claim_expired ? (
                                                            <span
                                                                className="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-medium text-slate-400 cursor-not-allowed"
                                                                title="Claims must be filed within 2 days (48 hours) of receiving supplies."
                                                            >
                                                                Claim expired (2 days)
                                                            </span>
                                                        ) : (
                                                            <Link
                                                                href={route('office.claims.create', {
                                                                    supply_request_id: record.supply_request_id,
                                                                    distribution_record_id: record.distribution_record_id,
                                                                })}
                                                                className="rounded border border-red-200 bg-red-50/50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition"
                                                            >
                                                                File Claim
                                                            </Link>
                                                        )
                                                    ) : (
                                                        <span className="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-medium text-slate-400">
                                                            Claim after receipt
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <div className="border-t border-slate-200 bg-slate-50/70 px-3.5 py-2 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs text-slate-500 font-medium">
                            Showing <span className="font-semibold text-slate-700">{records.data.length}</span> claimable records
                        </p>
                        <PaginationNav links={records.links} preserveState={false} />
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function StatusBadge({ status }: { status: string }) {
    const normalized = status.toUpperCase();
    const style =
        normalized === 'RELEASED' || normalized === 'RECEIVED'
            ? 'bg-emerald-100 text-emerald-700'
            : normalized === 'PENDING'
                ? 'bg-amber-100 text-amber-700'
                : 'bg-slate-100 text-slate-700';

    return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${style}`}>{status.replaceAll('_', ' ')}</span>;
}



