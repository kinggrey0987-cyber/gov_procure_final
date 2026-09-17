import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link } from '@inertiajs/react';

type PurchaseRequestDetail = {
    purchase_request_id: number;
    pr_number?: string | null;
    status: string;
    remarks?: string | null;
    created_at?: string | null;
    office?: { name?: string | null } | null;
    requester?: { name?: string | null } | null;
    supply_request?: {
        supply_request_id: number;
        request_code: string;
        status: string;
        purpose: string;
        requester?: { name?: string | null } | null;
    } | null;
    items: Array<{
        purchase_request_item_id: number;
        item_name: string;
        category_name: string;
        quantity_requested: number;
        unit: string;
        available_stock: number;
        remarks?: string | null;
    }>;
    purchase_orders: Array<{
        purchase_order_id: number;
        po_number: string;
        status: string;
        supplier_name: string;
        total_amount: number;
        expected_delivery_date?: string | null;
        deliveries: Array<{
            delivery_id: number;
            delivery_code: string;
            receipt_status: string;
            actual_delivery_date?: string | null;
            distribution_count: number;
        }>;
    }>;
};

type Props = {
    purchaseRequest: PurchaseRequestDetail;
    backFilters?: {
        page?: number | null;
    };
};

export default function ProcurementRequestShow({ purchaseRequest, backFilters }: Props) {
    const reference = purchaseRequest.pr_number || `PR-${String(purchaseRequest.purchase_request_id).padStart(6, '0')}`;
    const totalQuantity = purchaseRequest.items.reduce((sum, item) => sum + Number(item.quantity_requested || 0), 0);

    return (
        <AuthenticatedLayout
            header={
                <div className="space-y-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Purchase Request Record</h2>
                    <Link
                        href={route('inventory.procurement-requests.index', {
                            page: backFilters?.page || undefined,
                        })}
                        className="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Back to Purchase Request Records
                    </Link>
                </div>
            }
        >
            <Head title={`Purchase Request ${reference}`} />

            <div className="space-y-5 py-8">
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="font-mono text-sm text-slate-700">{reference}</p>
                            <h3 className="mt-1 text-lg font-semibold text-slate-900">
                                {purchaseRequest.supply_request?.request_code ?? 'Unlinked Supply Request'}
                            </h3>
                        </div>
                        <span className={statusClass(purchaseRequest.status)}>{displayPurchaseRequestStatus(purchaseRequest.status)}</span>
                    </div>

                    <div className="mt-4 grid gap-3 text-sm text-slate-700 md:grid-cols-2 lg:grid-cols-4">
                        <Info label="Office" value={purchaseRequest.office?.name ?? '-'} />
                        <Info label="Requester" value={purchaseRequest.supply_request?.requester?.name || purchaseRequest.requester?.name || '-'} />
                        <Info label="SR Status" value={purchaseRequest.supply_request?.status ? humanizeEnum(purchaseRequest.supply_request.status) : '-'} />
                        <Info label="Created" value={formatDate(purchaseRequest.created_at)} />
                        <Info label="Items" value={String(purchaseRequest.items.length)} />
                        <Info label="Total Qty" value={totalQuantity.toFixed(2)} />
                        <Info label="PO Count" value={String(purchaseRequest.purchase_orders.length)} />
                    </div>

                    <p className="mt-4 text-sm text-slate-700">
                        <span className="font-semibold">Purpose:</span> {purchaseRequest.supply_request?.purpose ?? '-'}
                    </p>
                    <p className="mt-2 text-sm text-slate-700">
                        <span className="font-semibold">Remarks:</span> {purchaseRequest.remarks?.trim() || 'No remarks provided.'}
                    </p>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Requested Items</h3>
                    </div>
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Category</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Quantity</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Available Stock</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Remarks</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {purchaseRequest.items.map((item) => (
                                <tr key={item.purchase_request_item_id}>
                                    <td className="px-4 py-3 text-slate-700">{item.item_name}</td>
                                    <td className="px-4 py-3 text-slate-700">{item.category_name}</td>
                                    <td className="px-4 py-3 text-slate-700">{item.quantity_requested.toFixed(2)} {item.unit}</td>
                                    <td className="px-4 py-3 text-slate-700">{item.available_stock.toFixed(2)}</td>
                                    <td className="px-4 py-3 text-slate-600">{item.remarks ?? '-'}</td>
                                </tr>
                            ))}
                            {purchaseRequest.items.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No item lines were found for this purchase request.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Linked Purchase Orders and Delivery Status</h3>
                    </div>
                    <div className="divide-y divide-slate-100">
                        {purchaseRequest.purchase_orders.map((order) => (
                            <div key={order.purchase_order_id} className="p-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold text-slate-900">{order.po_number}</p>
                                        <p className="text-sm text-slate-600">{order.supplier_name}</p>
                                    </div>
                                    <div className="text-right">
                                        <span className={purchaseOrderStatusClass(order.status)}>{displayPurchaseOrderStatus(order.status)}</span>
                                        <p className="mt-1 text-xs font-semibold text-slate-700">{formatMoney(order.total_amount)}</p>
                                    </div>
                                </div>
                                <p className="mt-2 text-xs text-slate-500">Expected delivery: {order.expected_delivery_date ?? '-'}</p>
                                <div className="mt-3 grid gap-2 md:grid-cols-2">
                                    {order.deliveries.map((delivery) => (
                                        <div key={delivery.delivery_id} className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                            <p className="font-semibold text-slate-800">{delivery.delivery_code}</p>
                                            <p className="text-xs text-slate-600">Receipt: {humanizeEnum(delivery.receipt_status)}</p>
                                            <p className="text-xs text-slate-600">Actual date: {delivery.actual_delivery_date ?? '-'}</p>
                                            <p className="text-xs text-slate-600">Distribution records: {delivery.distribution_count}</p>
                                        </div>
                                    ))}
                                    {order.deliveries.length === 0 ? <p className="text-sm text-slate-500">No delivery intake has been recorded for this PO.</p> : null}
                                </div>
                            </div>
                        ))}
                        {purchaseRequest.purchase_orders.length === 0 ? (
                            <p className="px-4 py-8 text-center text-sm text-slate-500">No linked purchase orders yet.</p>
                        ) : null}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 font-medium text-slate-800">{value}</p>
        </div>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        OPEN: `${base} bg-amber-100 text-amber-700`,
        PROCESSING: `${base} bg-blue-100 text-blue-700`,
        COMPLETED: `${base} bg-emerald-100 text-emerald-700`,
        RETURNED_FOR_REVISION: `${base} bg-violet-100 text-violet-700`,
        REJECTED: `${base} bg-rose-100 text-rose-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function purchaseOrderStatusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        DRAFT: `${base} bg-amber-100 text-amber-700`,
        PLACED: `${base} bg-blue-100 text-blue-700`,
        COMPLETED: `${base} bg-emerald-100 text-emerald-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function displayPurchaseRequestStatus(status: string): string {
    const map: Record<string, string> = {
        OPEN: 'Queued for Procurement',
        PROCESSING: 'In Procurement Processing',
        COMPLETED: 'Completed',
        RETURNED_FOR_REVISION: 'Returned for Revision',
        REJECTED: 'Rejected',
    };

    return map[status] ?? humanizeEnum(status);
}

function displayPurchaseOrderStatus(status: string): string {
    const map: Record<string, string> = {
        DRAFT: 'Draft PO',
        PLACED: 'Issued PO',
        COMPLETED: 'Completed PO',
    };

    return map[status] ?? humanizeEnum(status);
}

function formatDate(value?: string | null) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}

function formatMoney(value: number) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(value);
}
