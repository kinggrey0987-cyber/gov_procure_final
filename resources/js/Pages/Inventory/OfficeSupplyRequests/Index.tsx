import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type RequestRow = {
    supply_request_id: number;
    request_code: string;
    status: string;
    priority_level: string;
    needed_by_date?: string | null;
    office?: { name?: string | null } | null;
    requester?: { name?: string | null } | null;
    item_count: number;
    requested_total: number;
    shortage_total: number;
    stock_assessment: 'STOCK_READY' | 'SHORTAGE_REQUIRES_PROCUREMENT';
    has_purchase_request: boolean;
    distribution_records_count: number;
};

type Props = {
    requests: {
        data: RequestRow[];
        links: PaginationLink[];
    };
};

export default function OfficeSupplyRequestsIndex({ requests }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Office Supply Requests</h2>
                    <Link href={route('inventory.distribution-records.create')} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                        Open Supply Distribution
                    </Link>
                </div>
            }
        >
            <Head title="Office Supply Requests" />

            <div className="space-y-4 py-8">
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-sm text-slate-600">
                        Review submitted office requests against current stock. Requests with shortages are forwarded to the procurement initiation queue; stock-ready
                        requests proceed to distribution.
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Request</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Office</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Needed By</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Stock Assessment</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Progress</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {requests.data.map((request) => (
                                <tr key={request.supply_request_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3">
                                        <p className="font-mono text-xs text-slate-700">{request.request_code}</p>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {request.item_count} item(s) • {request.requested_total.toFixed(2)} requested
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">
                                        <p>{request.office?.name ?? '-'}</p>
                                        <p className="text-xs text-slate-500">{request.requester?.name ?? '-'}</p>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{request.needed_by_date ?? '-'}</td>
                                    <td className="px-4 py-3">
                                        <span className={request.stock_assessment === 'STOCK_READY' ? stockReadyClass : shortageClass}>
                                            {request.stock_assessment === 'STOCK_READY' ? 'Stock Ready' : 'Needs Procurement'}
                                        </span>
                                        {request.shortage_total > 0 ? (
                                            <p className="mt-1 text-xs text-rose-600">Shortage: {request.shortage_total.toFixed(2)}</p>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        <p>Status: {request.status}</p>
                                        <p className="text-xs text-slate-500">Distribution records: {request.distribution_records_count}</p>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {request.stock_assessment === 'STOCK_READY' ? (
                                            <Link
                                                href={route('inventory.distribution-records.create')}
                                                className="text-sm font-semibold text-red-700 hover:text-red-900"
                                            >
                                                Process Distribution
                                            </Link>
                                        ) : request.has_purchase_request ? (
                                            <span className="text-sm font-semibold text-slate-500">Queued</span>
                                        ) : (
                                            <Link
                                                href={route('inventory.office-supply-requests.queue', request.supply_request_id)}
                                                method="post"
                                                as="button"
                                                className="text-sm font-semibold text-rose-700 hover:text-rose-900"
                                            >
                                                Queue Procurement
                                            </Link>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {requests.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No office supply requests available for review.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-2">
                    {requests.links.map((link, index) => (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url || '#'}
                            className={`rounded-md px-3 py-2 text-sm ${
                                link.active ? 'bg-red-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                            } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

const stockReadyClass = 'inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700';
const shortageClass = 'inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700';
