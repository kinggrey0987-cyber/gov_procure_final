import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PurchaseRequestRow = {
    purchase_request_id: number;
    status: string;
    created_at?: string;
    office?: { name?: string | null } | null;
    supply_request?: {
        request_code?: string | null;
    } | null;
};

type ShortageCandidate = {
    supply_request_id: number;
    request_code: string;
    office?: { name?: string | null } | null;
    needed_by_date?: string | null;
    requested_total: number;
    shortage_total: number;
};

type LowStockItem = {
    inventory_id: number;
    item_name: string;
    available_stock: number;
    low_stock_threshold: number;
    deficit: number;
};

type Props = {
    purchaseRequests: {
        data: PurchaseRequestRow[];
        links: PaginationLink[];
    };
    shortageCandidates: ShortageCandidate[];
    lowStockItems: LowStockItem[];
};

export default function ProcurementRequestsIndex({ purchaseRequests, shortageCandidates, lowStockItems }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Purchase Requests</h2>
                    <Link href={route('inventory.office-supply-requests.index')} className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                        Back to Office Request Review
                    </Link>
                </div>
            }
        >
            <Head title="Procurement Requests" />

            <div className="space-y-6 py-8">
                <section className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Shortage-Based Initiation Queue</h3>
                        <p className="mt-2 text-sm text-slate-600">
                            This queue identifies office requests that cannot be fulfilled from current stock. It prepares purchase request initiation inputs and does not perform
                            BAC approval or adjudication.
                        </p>
                        <div className="mt-4 space-y-3">
                            {shortageCandidates.slice(0, 6).map((candidate) => (
                                <div key={candidate.supply_request_id} className="rounded-lg border border-rose-100 bg-rose-50/60 p-3">
                                    <p className="text-sm font-semibold text-slate-800">{candidate.request_code}</p>
                                    <p className="text-xs text-slate-600">{candidate.office?.name ?? '-'} • Needed by {candidate.needed_by_date ?? '-'}</p>
                                    <p className="mt-1 text-xs font-semibold text-rose-700">Shortage: {candidate.shortage_total.toFixed(2)}</p>
                                </div>
                            ))}
                            {shortageCandidates.length === 0 ? <p className="text-sm text-slate-500">No active shortages requiring purchase request initiation.</p> : null}
                        </div>x``
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Low Stock Signals</h3>
                        <div className="mt-4 space-y-3">
                            {lowStockItems.slice(0, 6).map((item) => (
                                <div key={item.inventory_id} className="rounded-lg border border-amber-100 bg-amber-50/70 p-3">
                                    <p className="text-sm font-semibold text-slate-800">{item.item_name}</p>
                                    <p className="text-xs text-slate-600">
                                        Available {item.available_stock.toFixed(2)} / Threshold {item.low_stock_threshold.toFixed(2)}
                                    </p>
                                    <p className="mt-1 text-xs font-semibold text-amber-700">Deficit: {item.deficit.toFixed(2)}</p>
                                </div>
                            ))}
                            {lowStockItems.length === 0 ? <p className="text-sm text-slate-500">No low-stock items at the moment.</p> : null}
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Existing Purchase Request Records</h3>
                    </div>
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Linked Office Request</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Office</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Reference</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {purchaseRequests.data.map((request) => (
                                <tr key={request.purchase_request_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 font-mono text-xs text-slate-700">{request.supply_request?.request_code ?? '-'}</td>
                                    <td className="px-4 py-3 text-slate-700">{request.office?.name ?? '-'}</td>
                                    <td className="px-4 py-3">
                                        <span className="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">{request.status}</span>
                                    </td>
                                    <td className="px-4 py-3 text-right text-xs font-semibold text-slate-700">PR-{String(request.purchase_request_id).padStart(6, '0')}</td>
                                </tr>
                            ))}
                            {purchaseRequests.data.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No procurement request records available.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>

                <div className="flex flex-wrap gap-2">
                    {purchaseRequests.links.map((link, index) => (
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
