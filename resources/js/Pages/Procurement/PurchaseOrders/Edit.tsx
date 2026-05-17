import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { PROCUREMENT_METHODS } from '@/constants/procurementMethods';

type SupplierOption = {
    supplier_id: number;
    supplier_name: string;
};

type PurchaseOrder = {
    purchase_order_id: number;
    supplier_id: number;
    po_number: string;
    procurement_method: string;
    expected_delivery_date: string | null;
    terms_notes: string | null;
    remarks: string | null;
};

type Props = {
    purchaseOrder: PurchaseOrder;
    suppliers: SupplierOption[];
};

export default function PurchaseOrdersEdit({ purchaseOrder, suppliers }: Props) {
    const today = new Date().toISOString().slice(0, 10);

    const form = useForm({
        supplier_id: purchaseOrder.supplier_id,
        po_number: purchaseOrder.po_number,
        procurement_method: purchaseOrder.procurement_method,
        expected_delivery_date: purchaseOrder.expected_delivery_date ?? '',
        terms_notes: purchaseOrder.terms_notes ?? '',
        remarks: purchaseOrder.remarks ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('procurement.purchase-orders.update', purchaseOrder.purchase_order_id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Purchase Order</h2>}>
            <Head title="Edit Purchase Order" />

            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-white p-6">
                        <select
                            value={form.data.supplier_id}
                            onChange={(event) => form.setData('supplier_id', Number(event.target.value))}
                            className="rounded border-gray-300 text-sm"
                        >
                            {suppliers.map((row) => (
                                <option key={row.supplier_id} value={row.supplier_id}>
                                    {row.supplier_name}
                                </option>
                            ))}
                        </select>
                        <input
                            type="text"
                            value={form.data.po_number}
                            onChange={(event) => form.setData('po_number', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <div>
                            <label className="text-sm font-semibold text-slate-700">Procurement Method</label>
                            <p className="text-xs text-slate-500 mt-1">This field records the declared mode of procurement used externally (e.g., PhilGEPS) for compliance and audit purposes.</p>
                            <select
                                value={form.data.procurement_method}
                                onChange={(event) => form.setData('procurement_method', event.target.value)}
                                className="mt-2 rounded border-gray-300 text-sm"
                                required
                            >
                                {PROCUREMENT_METHODS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="text-sm font-semibold text-slate-700">Expected Delivery Date</label>
                            <p className="mt-1 text-xs text-slate-500">This is the target supplier delivery date used later as the delivery intake expected arrival date. Past dates are not allowed.</p>
                            <input
                                type="date"
                                min={today}
                                value={form.data.expected_delivery_date}
                                onChange={(event) => form.setData('expected_delivery_date', event.target.value)}
                                className="mt-2 w-full rounded border-gray-300 text-sm"
                                required
                            />
                            {form.errors.expected_delivery_date ? <p className="mt-1 text-xs font-semibold text-rose-600">{form.errors.expected_delivery_date}</p> : null}
                        </div>
                        <textarea
                            value={form.data.terms_notes}
                            onChange={(event) => form.setData('terms_notes', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                            rows={3}
                        />
                        <textarea
                            value={form.data.remarks}
                            onChange={(event) => form.setData('remarks', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                            rows={3}
                        />
                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            Update Purchase Order
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}





