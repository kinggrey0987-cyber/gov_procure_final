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
        placed_date?: string | null;
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
    }>;
};

type Props = {
    delivery: Delivery;
};

const todayDate = (): string => {
    const date = new Date();
    const offsetDate = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);

    return offsetDate.toISOString().split('T')[0];
};

export default function DeliveryReceive({ delivery }: Props) {
    const toWholeQuantity = (value: string | number): number => {
        if (value === '') {
            return 0;
        }

        return Math.max(Math.trunc(Number(value) || 0), 0);
    };

    const toQuantityInput = (value: string | number): string => {
        const digits = String(value).replace(/\D/g, '');

        if (digits === '') {
            return '';
        }

        return digits.replace(/^0+(?=\d)/, '');
    };

    const { data, setData, put, processing, errors, transform } = useForm<ReceiveFormData>({
        actual_delivery_date: delivery.actual_delivery_date || todayDate(),
        remarks: delivery.remarks || '',
        items: delivery.items.map(item => ({
            delivery_item_id: item.delivery_item_id,
            received_quantity: toQuantityInput(item.received_quantity || 0),
            damaged_quantity: toQuantityInput(item.damaged_quantity || 0),
        })),
    });

    const handleItemChange = (index: number, field: 'received_quantity' | 'damaged_quantity', value: string | number) => {
        const updatedItems = [...data.items];
        updatedItems[index][field] = toQuantityInput(value);
        setData('items', updatedItems);
    };

    const lineWarnings = data.items.flatMap((item, index) => {
        const deliveryItem = delivery.items[index];
        const itemName = deliveryItem?.item?.item_name ?? `Item ${index + 1}`;
        const expectedQuantity = Number(deliveryItem?.expected_quantity ?? 0);
        const receivedQuantity = Number(item.received_quantity || 0);
        const damagedQuantity = Number(item.damaged_quantity || 0);
        const warnings: string[] = [];

        if (receivedQuantity > expectedQuantity) {
            warnings.push(`${itemName}: received quantity exceeds expected quantity.`);
        }

        if (damagedQuantity > receivedQuantity) {
            warnings.push(`${itemName}: damaged quantity cannot exceed received quantity.`);
        }

        return warnings;
    });

    const hasLineWarnings = lineWarnings.length > 0;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        transform((formData) => ({
            ...formData,
            items: formData.items.map((item) => ({
                ...item,
                received_quantity: toWholeQuantity(item.received_quantity),
                damaged_quantity: toWholeQuantity(item.damaged_quantity),
            })),
        }));
        put(route('inventory.deliveries.update', delivery.delivery_id), {
            onFinish: () => transform((formData) => formData),
        });
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
                                <p className="mt-1 text-sm text-slate-600">Record the actual quantities received. Item condition is calculated from damaged and missing quantities.</p>
                            </div>
                            {hasLineWarnings ? (
                                <div className="border-b border-amber-200 bg-amber-50 px-6 py-3 text-sm font-semibold text-amber-800">
                                    <ul className="list-disc space-y-1 pl-5">
                                        {lineWarnings.map((warning) => (
                                            <li key={warning}>{warning}</li>
                                        ))}
                                    </ul>
                                </div>
                            ) : null}
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left font-semibold text-slate-700">Item</th>
                                        <th className="px-6 py-3 text-center font-semibold text-slate-700">Expected Qty</th>
                                        <th className="px-6 py-3 text-center font-semibold text-slate-700">Received Qty</th>
                                        <th className="px-6 py-3 text-center font-semibold text-slate-700">Damaged Qty</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {delivery.items.map((item, index) => (
                                        <tr key={item.delivery_item_id} className="hover:bg-slate-50/50">
                                            <td className="px-6 py-4 text-slate-800">{item.item?.item_name ?? 'Unknown Item'}</td>
                                            <td className="px-6 py-4 text-center text-slate-700">{toWholeQuantity(item.expected_quantity)}</td>
                                            <td className="px-6 py-4 text-center">
                                                <input
                                                    type="number"
                                                    step="1"
                                                    min="0"
                                                    inputMode="numeric"
                                                    value={data.items[index].received_quantity}
                                                    onChange={(e) => handleItemChange(index, 'received_quantity', e.target.value)}
                                                    className="w-24 rounded-lg border border-slate-300 px-3 py-2 text-center text-sm focus:border-blue-500 focus:outline-none"
                                                />
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <input
                                                    type="number"
                                                    step="1"
                                                    min="0"
                                                    inputMode="numeric"
                                                    value={data.items[index].damaged_quantity}
                                                    onChange={(e) => handleItemChange(index, 'damaged_quantity', e.target.value)}
                                                    className="w-24 rounded-lg border border-slate-300 px-3 py-2 text-center text-sm focus:border-blue-500 focus:outline-none"
                                                />
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
                                    min={delivery.purchase_order?.placed_date ?? undefined}
                                    max={todayDate()}
                                    value={data.actual_delivery_date}
                                    onChange={(e) => setData('actual_delivery_date', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                />
                                <p className="mt-1 text-xs text-slate-500">Select the real delivery date. It cannot be before the PO placed date and future dates are not allowed.</p>
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
                                disabled={processing || hasLineWarnings}
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

