import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type PurchaseRequest = {
    purchase_request_id: number;
};

type PurchaseRequestItem = {
    purchase_request_item_id: number;
    item_id: number;
    quantity_requested: string;
    remarks: string | null;
};

type Props = {
    purchaseRequest: PurchaseRequest;
    items: {
        data: PurchaseRequestItem[];
    };
};

export default function PurchaseRequestItemsIndex({ purchaseRequest, items }: Props) {
    const form = useForm({ item_id: 0, quantity_requested: '', remarks: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('procurement.purchase-requests.items.store', purchaseRequest.purchase_request_id));
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Purchase Request Items</h2>}
        >
            <Head title="Purchase Request Items" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4">
                        <input
                            type="number"
                            placeholder="Item ID"
                            value={form.data.item_id || ''}
                            onChange={(event) => form.setData('item_id', Number(event.target.value))}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="number"
                            step="0.01"
                            placeholder="Quantity Requested"
                            value={form.data.quantity_requested}
                            onChange={(event) => form.setData('quantity_requested', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="text"
                            placeholder="Remarks"
                            value={form.data.remarks}
                            onChange={(event) => form.setData('remarks', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            Add Item
                        </button>
                    </form>

                    <div className="overflow-hidden rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left">Item ID</th>
                                    <th className="px-3 py-2 text-left">Quantity</th>
                                    <th className="px-3 py-2 text-left">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.data.map((row) => (
                                    <tr key={row.purchase_request_item_id} className="border-t">
                                        <td className="px-3 py-2">{row.item_id}</td>
                                        <td className="px-3 py-2">{row.quantity_requested}</td>
                                        <td className="px-3 py-2">{row.remarks ?? '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
