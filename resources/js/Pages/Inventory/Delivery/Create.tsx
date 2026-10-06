import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode } from 'react';

type PurchaseOrderItem = {
    purchase_order_item_id: number;
    quantity_ordered: number;
    unit_price?: number;
    item?: {
        item_id: number;
        item_name: string;
        unit?: string;
    };
};

type ExistingDelivery = {
    delivery_id: number;
    delivery_code: string;
    batch_number?: number;
    total_batches?: number;
    delivery_schedule_type?: string;
    is_final_batch?: boolean;
    receipt_status: string;
    created_at: string;
    items?: Array<{
        purchase_order_item_id: number;
        received_quantity: number;
    }>;
};

type PurchaseOrder = {
    purchase_order_id: number;
    po_number: string;
    status: string;
    supplier_id?: number;
    supplier?: {
        supplier_id: number;
        supplier_name: string;
    };
    creator?: {
        full_name?: string;
        name?: string;
    };
    suppliers?: Array<{
        supplier_id: number;
        supplier_name: string;
    }>;
    deliveries?: ExistingDelivery[];
    items?: PurchaseOrderItem[];
};

type Props = {
    purchaseOrders: PurchaseOrder[];
};

type DeliveryForm = {
    purchase_order_id: string;
    supplier_id: string;
    batch_number: number;
    total_batches: string;
    delivery_schedule_type: 'SINGLE_DELIVERY' | 'STAGGERED_BATCH';
    is_final_batch: boolean;
    delivery_code: string;
    actual_delivery_date: string;
    expected_arrival_date: string;
    remarks: string;
    items: Array<{
        purchase_order_item_id: number;
        expected_quantity: number | string;
    }>;
};

export default function DeliveryCreate({ purchaseOrders }: Props) {
    const { data, setData, post, processing, errors } = useForm<DeliveryForm>({
        purchase_order_id: '',
        supplier_id: '',
        batch_number: 1,
        total_batches: '',
        delivery_schedule_type: 'SINGLE_DELIVERY',
        is_final_batch: true,
        delivery_code: '',
        actual_delivery_date: '',
        expected_arrival_date: '',
        remarks: '',
        items: [],
    });

    const selectedPo = purchaseOrders.find((po) => String(po.purchase_order_id) === data.purchase_order_id);
    const suppliers = selectedPo?.supplier
        ? [selectedPo.supplier]
        : selectedPo?.suppliers ?? [];

    const existingDeliveries = selectedPo?.deliveries ?? [];
    const hasPriorDeliveries = existingDeliveries.length > 0;

    // When PO changes, sync supplier, batch tracking suggestions, and items
    const handlePoChange = (poId: string) => {
        const foundPo = purchaseOrders.find((po) => String(po.purchase_order_id) === poId);
        const priorBatches = foundPo?.deliveries ?? [];
        const nextBatch = priorBatches.length > 0
            ? Math.max(...priorBatches.map((d) => Number(d.batch_number || 1))) + 1
            : 1;
        const isStaggered = priorBatches.length > 0;

        const defaultItems = (foundPo?.items ?? []).map((it) => {
            const priorReceived = priorBatches.reduce((acc, del) => {
                const di = (del.items ?? []).find((dItem) => Number(dItem.purchase_order_item_id) === Number(it.purchase_order_item_id));
                return acc + (di ? Number(di.received_quantity || 0) : 0);
            }, 0);
            const remaining = Math.max(Number(it.quantity_ordered || 0) - priorReceived, 0);
            return {
                purchase_order_item_id: it.purchase_order_item_id,
                expected_quantity: remaining,
            };
        });

        setData((prev) => ({
            ...prev,
            purchase_order_id: poId,
            supplier_id: foundPo?.supplier?.supplier_id ? String(foundPo.supplier.supplier_id) : '',
            batch_number: nextBatch,
            delivery_schedule_type: isStaggered ? 'STAGGERED_BATCH' : 'SINGLE_DELIVERY',
            is_final_batch: !isStaggered,
            items: defaultItems,
        }));
    };

    const handleItemExpectedChange = (index: number, val: string) => {
        const next = [...(data.items || [])];
        if (next[index]) {
            next[index].expected_quantity = val === '' ? '' : Math.max(0, parseFloat(val) || 0);
            setData('items', next);
        }
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('inventory.deliveries.store'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Receive Deliveries (Batch Tracking)</h2>}>
            <Head title="Create Delivery" />

            <div className="py-8">
                <div className="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-6 border-b border-slate-100 pb-4">
                        <h3 className="text-lg font-bold text-slate-800">New Delivery Intake</h3>
                        <p className="text-xs text-slate-500">
                            Log a physical delivery against an approved Purchase Order. Supports single intake and multiple staggered delivery schedules under the same PO ID.
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-6">
                        <FormField label="Purchase Order" required error={errors.purchase_order_id}>
                            <select
                                value={data.purchase_order_id}
                                onChange={(e) => handlePoChange(e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                            >
                                <option value="">Select a purchase order</option>
                                {purchaseOrders.map((po) => (
                                    <option key={po.purchase_order_id} value={po.purchase_order_id}>
                                        {po.po_number} ({po.status}) - {po.supplier?.supplier_name ?? po.creator?.full_name ?? 'PO'}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        {/* Staggered Delivery Info Banner if PO has prior deliveries */}
                        {hasPriorDeliveries && (
                            <div className="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <span className="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">
                                            {existingDeliveries.length}
                                        </span>
                                        <h4 className="text-sm font-bold text-indigo-950">
                                            Multiple Delivery Schedule Detected
                                        </h4>
                                    </div>
                                    <span className="rounded-full bg-indigo-200/80 px-2.5 py-0.5 text-xs font-bold text-indigo-800">
                                        Next: Batch #{data.batch_number}
                                    </span>
                                </div>
                                <p className="mt-1 text-xs text-indigo-800">
                                    This Purchase Order already has recorded delivery intakes. Subsequent batches will automatically track remaining undelivered item balances.
                                </p>
                                <div className="mt-3 overflow-hidden rounded-lg border border-indigo-200 bg-white">
                                    <table className="min-w-full divide-y divide-indigo-100 text-xs">
                                        <thead className="bg-indigo-50/70 font-semibold text-indigo-900">
                                            <tr>
                                                <th className="px-3 py-1.5 text-left">Existing Delivery</th>
                                                <th className="px-3 py-1.5 text-left">Batch</th>
                                                <th className="px-3 py-1.5 text-left">Status</th>
                                                <th className="px-3 py-1.5 text-left">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-indigo-50">
                                            {existingDeliveries.map((del) => (
                                                <tr key={del.delivery_id}>
                                                    <td className="px-3 py-1.5 font-mono font-medium text-slate-800">{del.delivery_code}</td>
                                                    <td className="px-3 py-1.5 text-slate-700">Batch #{del.batch_number ?? 1}</td>
                                                    <td className="px-3 py-1.5 font-semibold text-indigo-700">{del.receipt_status}</td>
                                                    <td className="px-3 py-1.5 text-slate-500">{new Date(del.created_at).toLocaleDateString()}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Multi-Batch Schedule Configuration */}
                        <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-4">
                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-600">
                                Delivery Schedule & Batch Configuration
                            </h4>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-slate-700">Schedule Type</label>
                                    <select
                                        value={data.delivery_schedule_type}
                                        onChange={(e) => {
                                            const type = e.target.value as 'SINGLE_DELIVERY' | 'STAGGERED_BATCH';
                                            setData((prev) => ({
                                                ...prev,
                                                delivery_schedule_type: type,
                                                is_final_batch: type === 'SINGLE_DELIVERY' ? true : prev.is_final_batch,
                                            }));
                                        }}
                                        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                                    >
                                        <option value="SINGLE_DELIVERY">Single Delivery (Full Order)</option>
                                        <option value="STAGGERED_BATCH">Staggered Batch (Multi-Delivery)</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-slate-700">Batch Number</label>
                                    <input
                                        type="number"
                                        min="1"
                                        value={data.batch_number}
                                        onChange={(e) => setData('batch_number', Math.max(1, parseInt(e.target.value) || 1))}
                                        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                                    />
                                    {errors.batch_number && <p className="mt-1 text-xs text-rose-600">{errors.batch_number}</p>}
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-slate-700">Total Expected Batches</label>
                                    <input
                                        type="number"
                                        min="1"
                                        placeholder="Optional (e.g. 2 or 3)"
                                        value={data.total_batches}
                                        onChange={(e) => setData('total_batches', e.target.value)}
                                        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                                    />
                                    {errors.total_batches && <p className="mt-1 text-xs text-rose-600">{errors.total_batches}</p>}
                                </div>
                            </div>

                            <div className="flex items-center gap-2 pt-1">
                                <input
                                    type="checkbox"
                                    id="is_final_batch"
                                    checked={data.is_final_batch}
                                    onChange={(e) => setData('is_final_batch', e.target.checked)}
                                    className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                                <label htmlFor="is_final_batch" className="text-xs font-medium text-slate-700 cursor-pointer">
                                    This is the final delivery batch for this Purchase Order
                                </label>
                            </div>
                        </div>

                        {/* Items for this Batch Table */}
                        {selectedPo && selectedPo.items && selectedPo.items.length > 0 && (
                            <div className="rounded-xl border border-indigo-200 bg-indigo-50/40 p-4 space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-indigo-200/60 pb-2">
                                    <div>
                                        <h4 className="text-xs font-bold uppercase tracking-wider text-indigo-950">
                                            Planned Intake Items for Batch #{data.batch_number}
                                        </h4>
                                        <p className="text-[11px] text-indigo-800">
                                            Specify how many units arrive in this batch. If this is Batch 1 of a partial delivery (e.g. 40 of 100), entering 40 ensures the unarrived 60 units are deferred to Batch #{Number(data.batch_number) + 1} rather than falsely flagged as missing.
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-indigo-100 border border-indigo-300 px-2.5 py-0.5 text-[10px] font-bold text-indigo-900">
                                        Batch #{data.batch_number} Target
                                    </span>
                                </div>

                                <div className="overflow-x-auto rounded-lg border border-indigo-200 bg-white">
                                    <table className="min-w-full divide-y divide-indigo-100 text-xs">
                                        <thead className="bg-indigo-50/80 font-semibold text-indigo-900">
                                            <tr>
                                                <th className="px-3 py-2 text-left">Item Description</th>
                                                <th className="px-3 py-2 text-center">PO Total</th>
                                                <th className="px-3 py-2 text-center">Prior Received</th>
                                                <th className="px-3 py-2 text-center">Remaining Balance</th>
                                                <th className="px-3 py-2 text-right">Planned for Batch #{data.batch_number}</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-indigo-50">
                                            {selectedPo.items.map((it, idx) => {
                                                const priorReceived = existingDeliveries.reduce((acc, del) => {
                                                    const di = (del.items ?? []).find((dItem) => Number(dItem.purchase_order_item_id) === Number(it.purchase_order_item_id));
                                                    return acc + (di ? Number(di.received_quantity || 0) : 0);
                                                }, 0);
                                                const remaining = Math.max(Number(it.quantity_ordered || 0) - priorReceived, 0);
                                                const unit = it.item?.unit || 'Units';

                                                return (
                                                    <tr key={it.purchase_order_item_id} className="hover:bg-indigo-50/30">
                                                        <td className="px-3 py-2.5 font-medium text-slate-800">
                                                            <div>{it.item?.item_name || `PO Item #${it.purchase_order_item_id}`}</div>
                                                            <div className="text-[10px] text-slate-400 font-normal">Unit: {unit}</div>
                                                        </td>
                                                        <td className="px-3 py-2.5 text-center text-slate-600 font-semibold">
                                                            {it.quantity_ordered} {unit}
                                                        </td>
                                                        <td className="px-3 py-2.5 text-center text-slate-600 font-semibold">
                                                            {priorReceived} {unit}
                                                        </td>
                                                        <td className="px-3 py-2.5 text-center font-bold text-indigo-700">
                                                            {remaining} {unit}
                                                        </td>
                                                        <td className="px-3 py-2.5 text-right">
                                                            <div className="inline-flex items-center gap-1 justify-end">
                                                                <input
                                                                    type="number"
                                                                    min="0"
                                                                    max={remaining}
                                                                    value={data.items?.[idx]?.expected_quantity ?? remaining}
                                                                    onChange={(e) => handleItemExpectedChange(idx, e.target.value)}
                                                                    className="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-xs font-bold text-slate-900 focus:border-indigo-500 focus:outline-none ring-1 ring-slate-200"
                                                                />
                                                                <span className="text-[11px] text-slate-500 font-medium">{unit}</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

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
                                placeholder="Leave blank to auto-generate (e.g. DEL-2026-10-0001)"
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
                                rows={3}
                                value={data.remarks}
                                onChange={(e) => setData('remarks', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                placeholder="Notes on delivery batch condition, carrier, or receipt notes..."
                            />
                        </FormField>

                        <div className="flex flex-wrap gap-3 pt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 shadow-sm"
                            >
                                {processing ? 'Creating Delivery Intake...' : `Create Delivery (Batch #${data.batch_number})`}
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
