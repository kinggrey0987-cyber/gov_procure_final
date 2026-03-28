import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type PurchaseRequestOption = {
    purchase_request_id: number;
    supply_request_id: number;
};

type SupplierOption = {
    supplier_id: number;
    supplier_name: string;
};

type Props = {
    purchaseRequests: PurchaseRequestOption[];
    suppliers: SupplierOption[];
};

export default function PurchaseOrdersCreate({ purchaseRequests, suppliers }: Props) {
    const firstPurchaseRequest = purchaseRequests[0];

    const form = useForm({
        purchase_request_id: firstPurchaseRequest?.purchase_request_id ?? 0,
        supply_request_id: firstPurchaseRequest?.supply_request_id ?? 0,
        supplier_id: suppliers[0]?.supplier_id ?? 0,
        po_number: '',
        procurement_method: '',
        expected_delivery_date: '',
        terms_notes: '',
        remarks: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('procurement.purchase-orders.store'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create Purchase Order</h2>}>
            <Head title="Create Purchase Order" />

            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-white p-6">
                        <select
                            value={form.data.purchase_request_id}
                            onChange={(event) => {
                                const selected = purchaseRequests.find((row) => row.purchase_request_id === Number(event.target.value));
                                form.setData('purchase_request_id', Number(event.target.value));
                                if (selected) {
                                    form.setData('supply_request_id', selected.supply_request_id);
                                }
                            }}
                            className="rounded border-gray-300 text-sm"
                        >
                            {purchaseRequests.map((row) => (
                                <option key={row.purchase_request_id} value={row.purchase_request_id}>
                                    PR #{row.purchase_request_id} / SR #{row.supply_request_id}
                                </option>
                            ))}
                        </select>

                        <input type="hidden" value={form.data.supply_request_id} readOnly />

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
                            placeholder="PO Number"
                            value={form.data.po_number}
                            onChange={(event) => form.setData('po_number', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="text"
                            placeholder="Procurement Method"
                            value={form.data.procurement_method}
                            onChange={(event) => form.setData('procurement_method', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="date"
                            value={form.data.expected_delivery_date}
                            onChange={(event) => form.setData('expected_delivery_date', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <textarea
                            placeholder="Terms Notes"
                            value={form.data.terms_notes}
                            onChange={(event) => form.setData('terms_notes', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                            rows={3}
                        />
                        <textarea
                            placeholder="Remarks"
                            value={form.data.remarks}
                            onChange={(event) => form.setData('remarks', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                            rows={3}
                        />

                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            Create Purchase Order
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
