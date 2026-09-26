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
        released_total?: number;
        shortage_total: number;
        has_unreleased_available_stock?: boolean;
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
            quantity_released?: number;
            quantity_remaining?: number;
            available_stock: number;
            shortage: number;
        }>;
        follow_up_context?: {
            is_follow_up: boolean;
            type: string;
            label: string;
            parent?: { supply_request_id: number; request_code: string } | null;
            children?: Array<{ supply_request_id: number; request_code: string; status: string; reason?: string }>;
        } | null;
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
                    {request.follow_up_context?.parent && (
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-300 bg-amber-50/90 p-4 text-amber-950 shadow-xs">
                            <div className="flex items-center gap-3.5">
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white shadow">
                                    <svg className="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h4 className="text-sm font-extrabold uppercase tracking-wide text-amber-900">
                                            Follow-Up Requisition (Discrepancy / Replacement)
                                        </h4>
                                        <span className="rounded bg-amber-200 px-2 py-0.5 text-[10px] font-bold text-amber-900">
                                            {request.follow_up_context.label}
                                        </span>
                                    </div>
                                    <p className="mt-0.5 text-xs text-amber-800">
                                        This supply request was auto-generated to replenish deficit items from parent requisition{' '}
                                        <span className="font-bold">{request.follow_up_context.parent.request_code}</span>.
                                        Distributions released from this request fulfill the remaining quantity of the parent requisition.
                                    </p>
                                </div>
                            </div>
                            <Link
                                href={route('inventory.office-supply-requests.show', request.follow_up_context.parent.supply_request_id)}
                                className="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-amber-400 bg-white px-3 py-1.5 text-xs font-bold text-amber-900 shadow-2xs hover:bg-amber-100/50"
                            >
                                <span>View Parent ({request.follow_up_context.parent.request_code})</span>
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </Link>
                        </div>
                    )}

                    {request.follow_up_context?.children && request.follow_up_context.children.length > 0 && (
                        <div className="mb-4 rounded-xl border border-blue-300 bg-blue-50/90 p-4 text-blue-950 shadow-xs">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="flex items-start gap-3.5">
                                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white shadow">
                                        <svg className="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </span>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h4 className="text-sm font-extrabold uppercase tracking-wide text-blue-900">
                                                Linked Follow-Up Requisitions
                                            </h4>
                                            <span className="rounded bg-blue-200 px-2 py-0.5 text-[10px] font-bold text-blue-900">
                                                {request.follow_up_context.children.length} Batch{request.follow_up_context.children.length === 1 ? '' : 'es'}
                                            </span>
                                        </div>
                                        <p className="mt-0.5 text-xs text-blue-800">
                                            Delivery discrepancies resulted in follow-up replenishment requisition(s).
                                            Once distributions for all batches are released and received, this parent requisition will automatically reflect 100% full fulfillment.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div className="mt-3 flex flex-wrap gap-2 border-t border-blue-200/70 pt-3">
                                {request.follow_up_context.children.map((child) => (
                                    <Link
                                        key={child.supply_request_id}
                                        href={route('inventory.office-supply-requests.show', child.supply_request_id)}
                                        className="inline-flex items-center gap-2 rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-xs font-semibold text-blue-900 shadow-2xs hover:bg-blue-100/50"
                                    >
                                        <span>{child.request_code}</span>
                                        <span className="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-medium text-blue-700">
                                            {child.status.replaceAll('_', ' ')}
                                        </span>
                                        <svg className="h-3.5 w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}

                    {request.has_unreleased_available_stock && (
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-emerald-300 bg-emerald-50/90 p-4 text-emerald-950 shadow-xs">
                            <div className="flex items-center gap-3.5">
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white shadow">
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h4 className="text-sm font-extrabold uppercase tracking-wide text-emerald-900">
                                            Warehouse Stock Available for Remaining Request
                                        </h4>
                                        <span className="rounded bg-emerald-200 px-2 py-0.5 text-[10px] font-bold text-emerald-900">
                                            READY TO RELEASE
                                        </span>
                                    </div>
                                    <p className="mt-0.5 text-xs text-emerald-800">
                                        Matching warehouse stock is available to fulfill unreleased items for this request. Create an inventory distribution release to deliver them to the office.
                                    </p>
                                </div>
                            </div>
                            <Link
                                href={route('inventory.distribution-records.create', { supply_request_id: request.supply_request_id })}
                                className="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 transition"
                            >
                                <span>Create Distribution Release</span>
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
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
                    <div className="mt-3 grid gap-3 text-sm text-slate-700 md:grid-cols-2 lg:grid-cols-5">
                        <p><span className="font-semibold">Items:</span> {request.item_count}</p>
                        <p><span className="font-semibold">Requested Total:</span> {request.requested_total.toFixed(2)}</p>
                        <p><span className="font-semibold">Released Total:</span> {(request.released_total ?? 0).toFixed(2)}</p>
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
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Qty Requested</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Released</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Remaining</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Available Stock</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Shortage</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {request.items.map((item) => {
                                const piecesPerBox = item.pieces_per_box ?? 1;
                                const subUnit = item.sub_unit || 'piece';
                                const remaining = item.quantity_remaining ?? Math.max(item.quantity_requested - (item.quantity_released ?? 0), 0);

                                return (
                                <tr key={item.supply_request_item_id}>
                                    <td className="px-4 py-3 text-slate-700 font-medium">{item.item_name}</td>
                                    <td className="px-4 py-3 text-right text-slate-700 font-semibold">
                                        <span>{item.quantity_requested.toFixed(2)} {item.unit}</span>
                                        {piecesPerBox > 1 ? (
                                            <span className="block text-xs font-semibold text-indigo-700">
                                                ({(item.quantity_requested * piecesPerBox).toLocaleString()} {subUnit}s total @ {piecesPerBox} {subUnit}s/{item.unit})
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-3 text-right text-slate-700">
                                        <span>{(item.quantity_released ?? 0).toFixed(2)} {item.unit}</span>
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold text-slate-700">
                                        <span className={remaining > 0 ? 'text-amber-800' : 'text-slate-500'}>
                                            {remaining.toFixed(2)} {item.unit}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right text-slate-700">
                                        <span className={item.available_stock > 0 ? 'font-bold text-emerald-700' : 'text-slate-500'}>
                                            {item.available_stock.toFixed(2)} {item.unit}
                                        </span>
                                        {piecesPerBox > 1 ? (
                                            <span className="block text-xs text-slate-500">
                                                ({(item.available_stock * piecesPerBox).toLocaleString()} {subUnit}s)
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-3 text-right text-slate-700">
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






