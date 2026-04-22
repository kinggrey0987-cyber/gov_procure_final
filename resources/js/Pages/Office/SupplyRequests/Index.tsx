import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type RequestRow = {
    supply_request_id: number;
    request_code: string;
    status: string;
    request_date?: string | null;
    items_count?: number;
};

type Props = {
    supplyRequests: {
        data: RequestRow[];
    } | RequestRow[];
};

export default function SupplyRequestsIndex({ supplyRequests }: Props) {
    const rows = Array.isArray(supplyRequests) ? supplyRequests : supplyRequests.data;

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Request Tracking</h2>}
        >
            <Head title="Request Tracking" />

            <div className="space-y-5 py-4">
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 className="text-2xl font-semibold text-slate-800">Track Your Requests</h3>
                            <p className="text-sm text-slate-600">Single tracking view for all office supply requests.</p>
                            <Link
                                href={route('office.tracking')}
                                className="mt-3 inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Back to Dashboard
                            </Link>
                        </div>
                        <div className="flex items-center gap-2">
                            <Link
                                href={route('office.supply-requests.create')}
                                className="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                            >
                                Create New Request
                            </Link>
                        </div>
                    </div>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-4">
                        <h4 className="text-lg font-semibold text-slate-800">Request List</h4>
                    </div>

                    {rows.length === 0 ? (
                        <div className="px-6 py-10 text-sm text-slate-600">No requests found. Create your first supply request.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Request ID</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Date Submitted</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Items</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white">
                                    {rows.map((row) => (
                                        <tr key={row.supply_request_id}>
                                            <td className="px-4 py-3 font-semibold text-red-700">{row.request_code}</td>
                                            <td className="px-4 py-3 text-slate-700">{formatDate(row.request_date)}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={row.status} />
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">{row.items_count ?? 0} item(s)</td>
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={route('office.supply-requests.show', row.supply_request_id)}
                                                    className="inline-flex items-center rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                                >
                                                    View Details
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

function StatusBadge({ status }: { status: string }) {
    const normalized = status.toUpperCase();
    const style =
        normalized === 'APPROVED' || normalized === 'COMPLETED'
            ? 'bg-emerald-100 text-emerald-700'
            : normalized === 'IN_REVIEW' || normalized === 'PROCESSING' || normalized === 'RELEASED' || normalized === 'PROCUREMENT_PO_DRAFT' || normalized === 'PROCUREMENT_PLACED'
                ? 'bg-blue-100 text-blue-700'
                : normalized === 'REJECTED' || normalized === 'RETURNED_FOR_REVISION'
                    ? 'bg-rose-100 text-rose-700'
                    : 'bg-amber-100 text-amber-700';

    return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${style}`}>{status.replaceAll('_', ' ')}</span>;
}
