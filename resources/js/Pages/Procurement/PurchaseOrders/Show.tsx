import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type PurchaseOrder = {
    purchase_order_id: number;
    po_number: string;
    status: string;
    total_amount: string | number;
    remarks: string | null;
    procurement_method?: string | null;
    expected_delivery_date?: string | null;
    supplier?: {
        supplier_name?: string | null;
    } | null;
    purchase_request?: {
        purchase_request_id?: number;
        office?: {
            name?: string | null;
        } | null;
    } | null;
    supply_request?: {
        request_code?: string | null;
        requester?: {
            name?: string | null;
        } | null;
    } | null;
    creator?: {
        name?: string | null;
    } | null;
    approver?: {
        name?: string | null;
    } | null;
    date_approved?: string | null;
    items?: Array<{
        purchase_order_item_id: number;
        item_name_snapshot?: string | null;
        specification?: string | null;
        quantity_ordered: string | number;
        unit?: string | null;
        unit_price: string | number;
        total_price: string | number;
        item?: {
            category?: {
                category_name?: string | null;
            } | null;
        } | null;
    }>;
};

type Props = {
    purchaseOrder: PurchaseOrder;
    statusHistory: Array<{
        id: number;
        old_status?: string | null;
        new_status: string;
        remarks?: string | null;
        changed_at?: string | null;
        changed_by?: { id: number; name: string } | null;
    }>;
    approvalHistory: Array<{
        id: number;
        status: string;
        remarks?: string | null;
        approved_at?: string | null;
        approver?: { id: number; name: string } | null;
    }>;
    permissions?: {
        place?: boolean;
    };
    backLink?: {
        href: string;
        label: string;
    };
    showProcurementDocumentsLink?: boolean;
};
export default function PurchaseOrdersShow({ purchaseOrder, statusHistory, approvalHistory, permissions, backLink, showProcurementDocumentsLink = true }: Props) {
    const placeForm = useForm({ remarks: '' });
    const resolvedBackLink = backLink ?? {
        href: route('procurement.purchase-orders.index'),
        label: 'Back to Purchase Orders',
    };

    const placeOrder = (event: FormEvent) => {
        event.preventDefault();
        placeForm.post(route('procurement.purchase-orders.place', purchaseOrder.purchase_order_id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Purchase Order</h2>}>
            <Head title="Purchase Order" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs uppercase tracking-[0.15em] text-slate-500">Purchase Order Details</p>
                                <h3 className="mt-1 text-lg font-semibold text-slate-900">{purchaseOrder.po_number}</h3>
                            </div>
                            <span className={statusClass(purchaseOrder.status)}>{purchaseOrder.status}</span>
                        </div>

                        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <Info label="Supplier" value={purchaseOrder.supplier?.supplier_name ?? '-'} />
                            <Info label="Linked Request" value={purchaseOrder.purchase_request?.purchase_request_id ? `PR-${String(purchaseOrder.purchase_request.purchase_request_id).padStart(6, '0')}` : '-'} />
                            <Info label="Office" value={purchaseOrder.purchase_request?.office?.name ?? '-'} />
                            <Info label="Supply Request" value={purchaseOrder.supply_request?.request_code ?? '-'} />
                            <Info label="Requester" value={purchaseOrder.supply_request?.requester?.name ?? '-'} />
                            <Info label="Procurement Method" value={humanizeEnum(purchaseOrder.procurement_method)} />
                            <Info label="Expected Delivery" value={formatDate(purchaseOrder.expected_delivery_date)} />
                            <Info label="Total Amount" value={formatMoney(Number(purchaseOrder.total_amount ?? 0))} />
                        </dl>

                        <p className="mt-4 text-sm text-slate-600">
                            <span className="font-semibold text-slate-700">Remarks:</span> {purchaseOrder.remarks ?? 'No remarks provided.'}
                        </p>

                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            {purchaseOrder.creator?.name ? (
                                <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                    <dt className="text-xs uppercase tracking-wide text-slate-500">Created By</dt>
                                    <dd className="mt-1 font-medium text-slate-800">{purchaseOrder.creator.name}</dd>
                                </div>
                            ) : null}
                            {purchaseOrder.approver?.name ? (
                                <div className="rounded-lg border border-emerald-200 bg-emerald-50/60 px-3 py-2">
                                    <dt className="text-xs uppercase tracking-wide text-emerald-700">Approved / Placed By</dt>
                                    <dd className="mt-1 font-semibold text-emerald-900">{purchaseOrder.approver.name}</dd>
                                    {purchaseOrder.date_approved ? (
                                        <dd className="mt-0.5 text-xs text-emerald-700">Date: {formatDate(purchaseOrder.date_approved)}</dd>
                                    ) : null}
                                </div>
                            ) : null}
                        </div>
                    </section>

                    <section className="responsive-table-wrap rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Category</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Qty</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Unit</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Unit Price</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Line Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {(purchaseOrder.items ?? []).map((row) => (
                                    <tr key={row.purchase_order_item_id}>
                                        <td className="px-4 py-3 text-slate-900">
                                            <p className="font-medium">{row.item_name_snapshot ?? '-'}</p>
                                            <p className="text-xs text-slate-500">{row.specification || 'No specification'}</p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">{row.item?.category?.category_name ?? 'Uncategorized'}</td>
                                        <td className="px-4 py-3 text-slate-700">{Number(row.quantity_ordered ?? 0).toFixed(2)}</td>
                                        <td className="px-4 py-3 text-slate-700">{row.unit ?? '-'}</td>
                                        <td className="px-4 py-3 text-slate-700">{formatMoney(Number(row.unit_price ?? 0))}</td>
                                        <td className="px-4 py-3 font-semibold text-slate-900">{formatMoney(Number(row.total_price ?? 0))}</td>
                                    </tr>
                                ))}
                                {(purchaseOrder.items ?? []).length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                                            No purchase order item lines recorded yet.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </section>

                    {purchaseOrder.status === 'DRAFT' && permissions?.place ? (
                        <form onSubmit={placeOrder} className="space-y-3 rounded-lg border bg-white p-6">
                            <label className="mb-1 block text-sm text-gray-700">Placement Remarks</label>
                            <textarea
                                value={placeForm.data.remarks}
                                onChange={(event) => placeForm.setData('remarks', event.target.value)}
                                className="w-full rounded border-gray-300 text-sm"
                                rows={3}
                            />
                            <div className="flex flex-wrap gap-3">
                                <Link href={resolvedBackLink.href} className="rounded border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                    {resolvedBackLink.label}
                                </Link>
                                <button type="submit" className="rounded bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                                    Place Purchase Order
                                </button>
                            </div>
                        </form>
                    ) : (
                        <div className="flex flex-wrap gap-3">
                            <Link href={resolvedBackLink.href} className="rounded border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                {resolvedBackLink.label}
                            </Link>
                        </div>
                    )}

                    {showProcurementDocumentsLink ? <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="text-lg font-semibold text-slate-900">Purchase Order Documents</h3>
                        <p className="mt-1 text-sm text-slate-600">
                            PO-related files are managed in the central procurement document registry to avoid duplicating workflow documents on this page.
                        </p>

                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <Link
                                href={route('procurement.documents.index', { module: 'purchase_orders' })}
                                className="rounded border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                            >
                                Open Procurement Documents
                            </Link>
                            <p className="text-sm text-slate-500">
                                This keeps purchase-order files searchable from one registry instead of a separate attachment panel.
                            </p>
                        </div>
                    </section> : null}

                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <dt className="text-xs uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="mt-1 font-medium text-slate-800">{value}</dd>
        </div>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        DRAFT: `${base} bg-amber-100 text-amber-700`,
        PLACED: `${base} bg-blue-100 text-blue-700`,
        COMPLETED: `${base} bg-emerald-100 text-emerald-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
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
    }).format(value);
}



