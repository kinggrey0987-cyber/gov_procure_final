import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type PurchaseOrder = {
    purchase_order_id: number;
};

type PurchaseOrderItem = {
    purchase_order_item_id: number;
    item_id: number;
    item_name_snapshot: string;
    quantity_ordered: string;
    unit: string;
    unit_price: string;
    total_price: string;
};

type Props = {
    purchaseOrder: PurchaseOrder;
    items: {
        data: PurchaseOrderItem[];
    };
};

export default function PurchaseOrderItemsIndex({ purchaseOrder, items }: Props) {
    const form = useForm({
        supply_request_item_id: 0,
        item_id: 0,
        item_name_snapshot: '',
        specification: '',
        quantity_ordered: '',
        unit: '',
        unit_price: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('procurement.purchase-orders.items.store', purchaseOrder.purchase_order_id));
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Purchase Order Items</h2>}
        >
            <Head title="Purchase Order Items" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4">
                        <input
                            type="number"
                            placeholder="Supply Request Item ID"
                            value={form.data.supply_request_item_id || ''}
                            onChange={(event) => form.setData('supply_request_item_id', Number(event.target.value))}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="number"
                            placeholder="Item ID"
                            value={form.data.item_id || ''}
                            onChange={(event) => form.setData('item_id', Number(event.target.value))}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="text"
                            placeholder="Item Name Snapshot"
                            value={form.data.item_name_snapshot}
                            onChange={(event) => form.setData('item_name_snapshot', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="text"
                            placeholder="Specification"
                            value={form.data.specification}
                            onChange={(event) => form.setData('specification', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="number"
                            step="0.01"
                            placeholder="Quantity Ordered"
                            value={form.data.quantity_ordered}
                            onChange={(event) => form.setData('quantity_ordered', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="text"
                            placeholder="Unit"
                            value={form.data.unit}
                            onChange={(event) => form.setData('unit', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                        />
                        <input
                            type="number"
                            step="0.01"
                            placeholder="Unit Price"
                            value={form.data.unit_price}
                            onChange={(event) => form.setData('unit_price', event.target.value)}
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
                                    <th className="px-3 py-2 text-left">Name</th>
                                    <th className="px-3 py-2 text-left">Qty</th>
                                    <th className="px-3 py-2 text-left">Unit</th>
                                    <th className="px-3 py-2 text-left">Unit Price</th>
                                    <th className="px-3 py-2 text-left">Total Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.data.map((row) => (
                                    <tr key={row.purchase_order_item_id} className="border-t">
                                        <td className="px-3 py-2">{row.item_id}</td>
                                        <td className="px-3 py-2">{row.item_name_snapshot}</td>
                                        <td className="px-3 py-2">{row.quantity_ordered}</td>
                                        <td className="px-3 py-2">{row.unit}</td>
                                        <td className="px-3 py-2">{row.unit_price}</td>
                                        <td className="px-3 py-2">{row.total_price}</td>
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
