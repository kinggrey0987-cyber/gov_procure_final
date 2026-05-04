import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

type DeliveryItem = {
    delivery_item_id: number;
    expected_quantity: number;
    received_quantity: number;
    damaged_quantity?: number;
    item_condition: string;
    item?: {
        item_name: string;
        item_id: number;
    } | null;
};

type Delivery = {
    delivery_id: number;
    delivery_code: string;
    actual_delivery_date?: string | null;
    expected_arrival_date?: string | null;
    remarks?: string | null;
    purchase_order?: {
        purchase_order_id: number;
        po_number: string;
    } | null;
    supplier?: {
        supplier_name: string;
    } | null;
    items: DeliveryItem[];
};

type ReceiveFormData = {
    actual_delivery_date: string;
    remarks: string;
    items: Array<{
        delivery_item_id: number;
        received_quantity: number | string;
        damaged_quantity: number | string;
        item_condition: string;
    }>;
};

type Props = {
    delivery: Delivery;
};

export default function DeliveryReceive({ delivery }: Props) {
    const { data, setData, put, processing, errors } = useForm<ReceiveFormData>({
        actual_delivery_date: delivery.actual_delivery_date || '',
        remarks: delivery.remarks || '',
        items: delivery.items.map(item => ({
            delivery_item_id: item.delivery_item_id,
            received_quantity: item.received_quantity || 0,
            damaged_quantity: item.damaged_quantity || 0,
            item_condition: item.item_condition || 'GOOD',
        })),
    });

    const handleItemChange = (index: number, field: 'received_quantity' | 'damaged_quantity' | 'item_condition', value: string | number) => {
        const updatedItems = [...data.items];
        if (field === 'received_quantity' || field === 'damaged_quantity') {
            updatedItems[index][field] = value === '' ? 0 : parseFloat(String(value));
        } else {
            updatedItems[index].item_condition = String(value);
        }
        setData('items', updatedItems);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('inventory.deliveries.update', delivery.delivery_id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Receive Delivery {delivery.delivery_code}</h2>}>
            <Head title={`Receive ${delivery.delivery_code}`} />

            <div className="py-8">
                <div className="mx-auto max-w-5xl space-y-6">
                    {/* Delivery Info */}
                    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <Info label="Delivery Code" value={delivery.delivery_code} />
                            <Info label="PO Number" value={delivery.purchase_order?.po_number ?? '-'} />
                            <Info label="Supplier" value={delivery.supplier?.supplier_name ?? '-'} />
                            <Info label="Expected Arrival" value={delivery.expected_arrival_date ?? '-'} />
                        </div>
                    </div>

                    {/* Receiving Form */}
                    <form onSubmit={submit} className="space-y-6">
                        {/* Items Table */}
                        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-200 bg-slate-50 px-6 py-4">
                                <h3 className="text-lg font-semibold text-slate-800">Received Items</h3>
                                <p className="mt-1 text-sm text-slate-600">Record the actual quantities received and item condition</p>
                            </div>
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left font-semibold text-slate-700">Item</th>
                                        <th className="px-6 py-3 text-center font-semibold text-slate-700">Expected Qty</th>
                                        <th className="px-6 py-3 text-center font-semibold text-slate-700">Received Qty</th>
                                        <th className="px-6 py-3 text-center font-semibold text-slate-700">Damaged Qty</th>
                                        <th className="px-6 py-3 text-left font-semibold text-slate-700">Condition</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {delivery.items.map((item, index) => (
                                        <tr key={item.delivery_item_id} className="hover:bg-slate-50/50">
                                            <td className="px-6 py-4 text-slate-800">{item.item?.item_name ?? 'Unknown Item'}</td>
                                            <td className="px-6 py-4 text-center text-slate-700">{item.expected_quantity.toFixed(2)}</td>
                                            <td className="px-6 py-4 text-center">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={data.items[index].received_quantity}
                                                    onChange={(e) => handleItemChange(index, 'received_quantity', e.target.value)}
                                                    className="w-24 rounded-lg border border-slate-300 px-3 py-2 text-center text-sm focus:border-blue-500 focus:outline-none"
                                                />
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={data.items[index].damaged_quantity ?? 0}
                                                    onChange={(e) => handleItemChange(index, 'damaged_quantity', e.target.value)}
                                                    className="w-24 rounded-lg border border-slate-300 px-3 py-2 text-center text-sm focus:border-blue-500 focus:outline-none"
                                                />
                                            </td>
                                            <td className="px-6 py-4">
                                                <select
                                                    value={data.items[index].item_condition}
                                                    onChange={(e) => handleItemChange(index, 'item_condition', e.target.value)}
                                                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                                                >
                                                    <option value="GOOD">Good</option>
                                                    <option value="DAMAGED">Damaged</option>
                                                    <option value="MISSING">Missing</option>
                                                </select>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Date and Remarks */}
                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <FormField label="Actual Delivery Date" error={errors.actual_delivery_date}>
                                <input
                                    type="date"
                                    value={data.actual_delivery_date}
                                    onChange={(e) => setData('actual_delivery_date', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                />
                            </FormField>

                            <div className="mt-4">
                                <FormField label="Remarks" error={errors.remarks}>
                                    <textarea
                                        rows={3}
                                        value={data.remarks}
                                        onChange={(e) => setData('remarks', e.target.value)}
                                        placeholder="e.g., Items in good condition, received all items..."
                                        className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                    />
                                </FormField>
                            </div>
                        </div>

                        {/* Action Buttons */}
                        <div className="flex flex-wrap gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? 'Saving...' : 'Complete Receipt'}
                            </button>
                            <Link href={route('inventory.deliveries.show', delivery.delivery_id)} className="rounded-lg bg-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-300">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 font-semibold text-slate-800">{value}</p>
        </div>
    );
}

function FormField({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label className="mb-2 block text-sm font-semibold text-slate-700">{label}</label>
            {children}
            {error ? <p className="mt-1 text-xs text-rose-600">{error}</p> : null}
        </div>
    );
}
