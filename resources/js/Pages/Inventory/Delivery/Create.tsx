import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

type PurchaseOrder = {
    purchase_order_id: number;
    po_number: string;
    creator?: {
        full_name?: string;
        name?: string;
    };
    suppliers?: Array<{
        supplier_id: number;
        supplier_name: string;
    }>;
};

type Props = {
    purchaseOrders: PurchaseOrder[];
};

type DeliveryForm = {
    purchase_order_id: string;
    supplier_id: string;
    delivery_code: string;
    actual_delivery_date: string;
    expected_arrival_date: string;
    remarks: string;
};

export default function DeliveryCreate({ purchaseOrders }: Props) {
    const { data, setData, post, processing, errors } = useForm<DeliveryForm>({
        purchase_order_id: '',
        supplier_id: '',
        delivery_code: '',
        actual_delivery_date: '',
        expected_arrival_date: '',
        remarks: '',
    });

    const selectedPo = purchaseOrders.find((po) => String(po.purchase_order_id) === data.purchase_order_id);
    const suppliers = selectedPo?.suppliers ?? [];

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('inventory.deliveries.store'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Receive Deliveries</h2>}>
            <Head title="Create Delivery" />

            <div className="py-8">
                <div className="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form onSubmit={submit} className="space-y-5">
                        <FormField label="Purchase Order" required error={errors.purchase_order_id}>
                            <select
                                value={data.purchase_order_id}
                                onChange={(e) => {
                                    setData('purchase_order_id', e.target.value);
                                    setData('supplier_id', '');
                                }}
                                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                            >
                                <option value="">Select a purchase order</option>
                                {purchaseOrders.map((po) => (
                                    <option key={po.purchase_order_id} value={po.purchase_order_id}>
                                        {po.po_number} - {po.creator?.full_name ?? po.creator?.name ?? 'Creator'}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField label="Supplier" required error={errors.supplier_id}>
                            <select
                                value={data.supplier_id}
                                onChange={(e) => setData('supplier_id', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                            >
                                <option value="">Select a supplier</option>
                                {suppliers.map((supplier) => (
                                    <option key={supplier.supplier_id} value={supplier.supplier_id}>
                                        {supplier.supplier_name}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField label="Delivery Code" error={errors.delivery_code}>
                            <input
                                type="text"
                                value={data.delivery_code}
                                onChange={(e) => setData('delivery_code', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                placeholder="Leave blank to auto-generate"
                            />
                        </FormField>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <FormField label="Actual Delivery Date" error={errors.actual_delivery_date}>
                                <input
                                    type="date"
                                    value={data.actual_delivery_date}
                                    onChange={(e) => setData('actual_delivery_date', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                />
                            </FormField>

                            <FormField label="Expected Arrival Date" error={errors.expected_arrival_date}>
                                <input
                                    type="date"
                                    value={data.expected_arrival_date}
                                    onChange={(e) => setData('expected_arrival_date', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                />
                            </FormField>
                        </div>

                        <FormField label="Remarks" error={errors.remarks}>
                            <textarea
                                rows={4}
                                value={data.remarks}
                                onChange={(e) => setData('remarks', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                            />
                        </FormField>

                        <div className="flex flex-wrap gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? 'Creating...' : 'Create Delivery'}
                            </button>
                            <Link href={route('inventory.deliveries.index')} className="rounded-lg bg-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-300">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function FormField({
    label,
    required,
    error,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label className="mb-2 block text-sm font-semibold text-slate-700">
                {label}
                {required ? <span className="ml-1 text-rose-600">*</span> : null}
            </label>
            {children}
            {error ? <p className="mt-1 text-xs text-rose-600">{error}</p> : null}
        </div>
    );
}





