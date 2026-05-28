import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';
import { PROCUREMENT_METHODS } from '@/constants/procurementMethods';
import { dateInputValue, todayInputValue } from '@/lib/dateInput';

type SupplierOption = {
    supplier_id: number;
    supplier_name: string;
};

type PurchaseOrder = {
    purchase_order_id: number;
    supplier_id: number;
    po_number: string;
    status?: string;
    procurement_method: string;
    expected_delivery_date: string | null;
    terms_notes: string | null;
    remarks: string | null;
    supplier?: SupplierOption | null;
    purchase_request?: {
        purchase_request_id: number;
        office?: {
            id: number;
            code?: string | null;
            name?: string | null;
        } | null;
    } | null;
    supply_request?: {
        supply_request_id: number;
        request_code?: string | null;
        requester?: {
            id: number;
            name: string;
        } | null;
        items?: Array<{
            supply_request_item_id: number;
            item_name_snapshot?: string | null;
            quantity_requested: number | string;
            remarks?: string | null;
            item?: {
                item_name?: string | null;
                category?: {
                    category_name?: string | null;
                } | null;
            } | null;
        }>;
    } | null;
};

type Props = {
    purchaseOrder: PurchaseOrder;
    suppliers: SupplierOption[];
};

export default function PurchaseOrdersEdit({ purchaseOrder, suppliers }: Props) {
    const today = todayInputValue();

    const form = useForm({
        supplier_id: purchaseOrder.supplier_id,
        po_number: purchaseOrder.po_number,
        procurement_method: purchaseOrder.procurement_method,
        expected_delivery_date: dateInputValue(purchaseOrder.expected_delivery_date),
        terms_notes: purchaseOrder.terms_notes ?? '',
        remarks: purchaseOrder.remarks ?? '',
    });

    const requestItems = purchaseOrder.supply_request?.items ?? [];
    const totalQuantity = requestItems.reduce((sum, row) => sum + Number(row.quantity_requested ?? 0), 0);
    const selectedSupplier = suppliers.find((row) => row.supplier_id === Number(form.data.supplier_id)) ?? purchaseOrder.supplier ?? null;

    useEffect(() => {
        if (!form.isDirty) return;

        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', handleBeforeUnload);
        return () => window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [form.isDirty]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('procurement.purchase-orders.update', purchaseOrder.purchase_order_id));
    };

    return (
        <AuthenticatedLayout header="Edit Purchase Order">
            <Head title="Edit Purchase Order" />

            <form onSubmit={submit} className="space-y-6 py-4">
                {Object.keys(form.errors).length > 0 ? (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm">
                        <p className="font-semibold">Unable to update purchase order.</p>
                        <p className="mt-1">Please review the supplier, PO details, and delivery information.</p>
                    </div>
                ) : null}

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.15em] text-red-700">Procurement Unit Workflow</p>
                            <h3 className="mt-1 text-2xl font-semibold text-slate-800">Edit Purchase Order</h3>
                            <p className="mt-1 text-sm text-slate-600">Refine the draft PO details in the same workflow layout used when creating a purchase order.</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('procurement.purchase-orders.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Back to Purchase Orders
                            </Link>
                            <Link href={route('procurement.purchase-orders.items.index', purchaseOrder.purchase_order_id)} className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                Manage Items
                            </Link>
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="block md:col-span-2">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Linked Purchase Request Source</span>
                            <input
                                type="text"
                                value={purchaseOrder.purchase_request?.purchase_request_id ? `PR-${String(purchaseOrder.purchase_request.purchase_request_id).padStart(6, '0')}` : 'No linked purchase request'}
                                className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600"
                                readOnly
                            />
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Select Supplier</span>
                            <select
                                value={form.data.supplier_id}
                                onChange={(event) => form.setData('supplier_id', Number(event.target.value))}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                required
                            >
                                {suppliers.map((row) => (
                                    <option key={row.supplier_id} value={row.supplier_id}>
                                        {row.supplier_name}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">PO Number</span>
                            <input
                                type="text"
                                value={form.data.po_number}
                                onChange={(event) => form.setData('po_number', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                required
                            />
                        </label>
                    </div>
                </section>

                <section className="rounded-2xl border border-red-100 bg-red-50/40 p-6 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-[0.15em] text-red-700">Derived Request Context</p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <ContextField label="Requesting Office" value={formatOffice(purchaseOrder.purchase_request?.office)} />
                        <ContextField label="Request Reference" value={purchaseOrder.supply_request?.request_code || (purchaseOrder.supply_request?.supply_request_id ? `SR #${purchaseOrder.supply_request.supply_request_id}` : 'N/A')} />
                        <ContextField label="Originating Requester" value={purchaseOrder.supply_request?.requester?.name || 'N/A'} />
                        <ContextField label="Selected Supplier" value={selectedSupplier?.supplier_name || 'N/A'} />
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Requested Item Context</p>
                            <p className="mt-1 text-sm text-slate-700">Review the source request lines before continuing to item line coding.</p>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-right">
                            <p className="text-xs uppercase tracking-wide text-slate-500">Line Count</p>
                            <p className="text-base font-semibold text-slate-800">{requestItems.length}</p>
                            <p className="mt-1 text-xs uppercase tracking-wide text-slate-500">Total Quantity</p>
                            <p className="text-base font-semibold text-slate-800">{totalQuantity.toFixed(2)}</p>
                        </div>
                    </div>

                    <div className="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-3 py-2 text-left font-semibold text-slate-700">Item</th>
                                    <th className="px-3 py-2 text-left font-semibold text-slate-700">Category</th>
                                    <th className="px-3 py-2 text-left font-semibold text-slate-700">Qty Requested</th>
                                    <th className="px-3 py-2 text-left font-semibold text-slate-700">Line Coding</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {requestItems.map((row) => (
                                    <tr key={row.supply_request_item_id}>
                                        <td className="px-3 py-2 text-slate-900">
                                            <p className="font-medium">{row.item_name_snapshot || row.item?.item_name || 'Unknown item'}</p>
                                            <p className="text-xs text-slate-500">{row.remarks || 'No line remarks'}</p>
                                        </td>
                                        <td className="px-3 py-2 text-slate-700">{row.item?.category?.category_name || 'Uncategorized'}</td>
                                        <td className="px-3 py-2 text-slate-700">{Number(row.quantity_requested ?? 0).toFixed(2)}</td>
                                        <td className="px-3 py-2 text-slate-600">Encode final quantity and supplier quote in Manage Items</td>
                                    </tr>
                                ))}
                                {requestItems.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-3 py-6 text-center text-slate-500">
                                            No request items loaded for this source.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h4 className="mb-4 text-xl font-semibold text-slate-800">Procurement Details</h4>
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="block">
                            <span className="text-sm font-semibold text-slate-700">Procurement Method</span>
                            <p className="text-xs text-slate-500 mt-1">This field records the declared mode of procurement used externally (e.g., PhilGEPS) for compliance and audit purposes.</p>
                            <select
                                value={form.data.procurement_method}
                                onChange={(event) => form.setData('procurement_method', event.target.value)}
                                className="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                required
                            >
                                {PROCUREMENT_METHODS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="block">
                            <span className="text-sm font-semibold text-slate-700">Expected Delivery Date</span>
                            <p className="mt-1 text-xs text-slate-500">This is the target supplier delivery date used later as the delivery intake expected arrival date. Past dates are not allowed.</p>
                            <input
                                type="date"
                                min={today}
                                value={form.data.expected_delivery_date}
                                onChange={(event) => form.setData('expected_delivery_date', event.target.value)}
                                className="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                required
                            />
                            {form.errors.expected_delivery_date ? <p className="mt-1 text-xs font-semibold text-rose-600">{form.errors.expected_delivery_date}</p> : null}
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Terms Notes</span>
                            <textarea
                                value={form.data.terms_notes}
                                onChange={(event) => form.setData('terms_notes', event.target.value)}
                                className="h-24 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Remarks</span>
                            <textarea
                                value={form.data.remarks}
                                onChange={(event) => form.setData('remarks', event.target.value)}
                                className="h-24 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </label>
                    </div>
                </section>

                <div className="flex flex-wrap justify-end gap-3">
                    <Link href={route('procurement.purchase-orders.items.index', purchaseOrder.purchase_order_id)} className="rounded-lg border border-red-300 px-5 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                        Continue to Manage Items
                    </Link>
                    <button type="submit" disabled={form.processing} className="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60">
                        {form.processing ? 'Saving…' : 'Save Purchase Order'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

function ContextField({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-red-100 bg-white px-3 py-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-sm font-medium text-slate-800">{value}</p>
        </div>
    );
}

function formatOffice(office?: { code?: string | null; name?: string | null } | null) {
    if (!office) {
        return 'N/A';
    }

    return [office.code, office.name].filter(Boolean).join(' - ') || 'N/A';
}
