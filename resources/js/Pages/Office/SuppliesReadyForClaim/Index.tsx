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
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Supplies Ready for Claim</h2>}>
            <Head title="Supplies Ready for Claim" />

            <div className="space-y-5 py-4">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 className="text-2xl font-semibold text-slate-800">Released Supplies</h3>
                            <p className="text-sm text-slate-600">Confirm receipt and file claims for missing or damaged items.</p>
                            <Link
                                href={route('office.supply-requests.index')}
                                className="mt-3 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Back to Request Tracking
                            </Link>
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-4">
                        <h4 className="text-lg font-semibold text-slate-800">Ready Supply Records</h4>
                        <p className="mt-1 text-xs text-slate-500">Showing up to 5 recent supply records on this page.</p>
                    </div>

                    {records.data.length === 0 ? (
                        <div className="px-6 py-10 text-sm text-slate-600">No released supply records available for your office yet.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Request ID</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Request Date</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Items</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Storage Location</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Ready Since</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {records.data.map((record) => (
                                        <tr key={record.distribution_record_id}>
                                            <td className="px-4 py-3 font-semibold text-red-700">{record.request_id}</td>
                                            <td className="px-4 py-3 text-slate-700">{record.request_date ?? '-'}</td>
                                            <td className="px-4 py-3 text-slate-700">
                                                <p>{record.items_count} item(s)</p>
                                                <p className="text-xs text-slate-500">{record.items_summary}</p>
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">{record.location}</td>
                                            <td className="px-4 py-3 text-slate-700">{record.ready_since ?? '-'}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={record.status} />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    {record.status.toUpperCase() === 'RELEASED' ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                router.post(route('office.distribution-records.receive', record.distribution_record_id), {}, {
                                                                    preserveScroll: true,
                                                                });
                                                            }}
                                                            className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                                                        >
                                                            Confirm Received
                                                        </button>
                                                    ) : null}
                                                    <Link
                                                        href={route('office.distribution-records.show', record.distribution_record_id)}
                                                        className="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                                    >
                                                        Open Distribution
                                                    </Link>
                                                    <Link
                                                        href={route('office.claims.create', {
                                                            distribution_record_id: record.distribution_record_id,
                                                        })}
                                                        className="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                                    >
                                                        File Claim
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <PaginationNav links={records.links} preserveState={false} />
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





