import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Props = {
    request: {
        supply_request_id: number;
        request_code: string;
        status: string;
        priority_level: string;
        purpose: string;
        needed_by_date?: string | null;
        request_date?: string | null;
        office?: { id: number; name: string } | null;
        requester?: { id: number; name: string } | null;
        item_count: number;
        requested_total: number;
        shortage_total: number;
        has_purchase_request: boolean;
        purchase_request_count: number;
        distribution_records_count: number;
        items: Array<{
            supply_request_item_id: number;
            item_name: string;
            unit: string;
            pieces_per_box?: number | null;
            sub_unit?: string | null;
            quantity_requested: number;
            available_stock: number;
            shortage: number;
        }>;
    };
    backFilters?: {
        q?: string;
        office_id?: number | null;
        stock_assessment?: string;
        workflow_bucket?: string;
        page?: number | null;
    };
};

export default function InventoryOfficeSupplyRequestShow({ request, backFilters }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="space-y-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Office Supply Request Details</h2>
                    <Link
                        href={route('inventory.office-supply-requests.index', {
                            q: backFilters?.q || undefined,
                            office_id: backFilters?.office_id || undefined,
                            stock_assessment: backFilters?.stock_assessment || undefined,
                            workflow_bucket: backFilters?.workflow_bucket || undefined,
                            page: backFilters?.page || undefined,
                        })}
                        className="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Back to Office Supply Requests
                    </Link>
                </div>
            }
        >
            <Head title={`Office Supply Request ${request.request_code}`} />

            <div className="space-y-5 py-8">
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    {request.priority_level?.toUpperCase() === 'URGENT' && (
                        <div className="mb-4 flex items-center gap-3.5 rounded-xl border-2 border-red-500 bg-red-50 p-4 text-red-900 shadow-xs">
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-600 text-white shadow">
                                <svg className="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </span>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h4 className="text-sm font-extrabold uppercase tracking-wide text-red-900">URGENT PRIORITY SUPPLY REQUEST</h4>
                                    <span className="rounded bg-red-600 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-white shadow-2xs">EXPEDITE PROCESSING</span>
                                </div>
                                <p className="mt-0.5 text-xs text-red-700">This request is marked Urgent and pinned for priority assessment, shortage review, and procurement fulfillment.</p>
                            </div>
                        </div>
                    )}
                    <p className="font-mono text-sm text-slate-700">{request.request_code}</p>
                    <div className="mt-3 grid gap-3 text-sm text-slate-700 md:grid-cols-2 lg:grid-cols-3">
                        <p><span className="font-semibold">Status:</span> {request.status}</p>
                        <p className="flex items-center gap-1.5">
                            <span className="font-semibold">Priority:</span>
                            {request.priority_level?.toUpperCase() === 'URGENT' ? (
                                <span className="inline-flex items-center gap-1.5 rounded-full border border-red-300 bg-red-100 px-2.5 py-0.5 text-xs font-bold text-red-800 shadow-2xs">
                                    <span className="h-1.5 w-1.5 rounded-full bg-red-600"></span> URGENT
                                </span>
                            ) : (
                                <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                    Normal
                                </span>
                            )}
                        </p>
                        <p><span className="font-semibold">Needed By:</span> {request.needed_by_date ?? '-'}</p>
                        <p><span className="font-semibold">Request Date:</span> {request.request_date ?? '-'}</p>
                        <p><span className="font-semibold">Office:</span> {request.office?.name ?? '-'}</p>
                        <p><span className="font-semibold">Requester:</span> {request.requester?.name ?? '-'}</p>
                    </div>
                    <p className="mt-3 text-sm text-slate-700"><span className="font-semibold">Purpose:</span> {request.purpose}</p>
                    <div className="mt-3 grid gap-3 text-sm text-slate-700 md:grid-cols-2 lg:grid-cols-4">
                        <p><span className="font-semibold">Items:</span> {request.item_count}</p>
                        <p><span className="font-semibold">Requested Total:</span> {request.requested_total.toFixed(2)}</p>
                        <p><span className="font-semibold">Shortage Total:</span> {request.shortage_total.toFixed(2)}</p>
                        <p><span className="font-semibold">Purchase Requests:</span> {request.purchase_request_count}</p>
                    </div>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Requested Items</h3>
                    </div>
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Qty Requested</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Available Stock</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Shortage</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {request.items.map((item) => {
                                const piecesPerBox = item.pieces_per_box ?? 1;
                                const subUnit = item.sub_unit || 'piece';

                                return (
                                <tr key={item.supply_request_item_id}>
                                    <td className="px-4 py-3 text-slate-700 font-medium">{item.item_name}</td>
                                    <td className="px-4 py-3 text-slate-700 font-semibold">
                                        <span>{item.quantity_requested.toFixed(2)} {item.unit}</span>
                                        {piecesPerBox > 1 ? (
                                            <span className="block text-xs font-semibold text-indigo-700">
                                                ({(item.quantity_requested * piecesPerBox).toLocaleString()} {subUnit}s total @ {piecesPerBox} {subUnit}s/{item.unit})
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">
                                        <span>{item.available_stock.toFixed(2)} {item.unit}</span>
                                        {piecesPerBox > 1 ? (
                                            <span className="block text-xs text-slate-500">
                                                ({(item.available_stock * piecesPerBox).toLocaleString()} {subUnit}s)
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">
                                        <span>{item.shortage.toFixed(2)} {item.unit}</span>
                                        {piecesPerBox > 1 && item.shortage > 0 ? (
                                            <span className="block text-xs text-rose-600 font-medium">
                                                ({(item.shortage * piecesPerBox).toLocaleString()} {subUnit}s shortage)
                                            </span>
                                        ) : null}
                                    </td>
                                </tr>
                                );
                            })}
                            {request.items.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No items found for this request.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}






