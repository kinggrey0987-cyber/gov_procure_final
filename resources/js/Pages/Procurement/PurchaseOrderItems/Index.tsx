import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type PurchaseOrder = {
    purchase_order_id: number;
    po_number?: string;
    status?: string;
};

type PurchaseOrderItem = {
    purchase_order_item_id: number;
    supply_request_item_id: number;
    item_id: number;
    item_name_snapshot: string;
    quantity_ordered: string;
    unit: string;
    unit_price: string;
    total_price: string;
};

type SourceItem = {
    supply_request_item_id: number;
    item_id: number;
    item_name_snapshot: string;
    default_unit: string;
    quantity_requested: number;
};

type Props = {
    purchaseOrder: PurchaseOrder;
    items: {
        data: PurchaseOrderItem[];
    };
    sourceItems: SourceItem[];
};

export default function PurchaseOrderItemsIndex({ purchaseOrder, items, sourceItems }: Props) {
    const form = useForm({
        supply_request_item_id: 0,
        item_id: 0,
        item_name_snapshot: '',
        specification: '',
        quantity_ordered: '',
        unit: '',
        unit_price: '',
    });

    const selectedSource = sourceItems.find((row) => row.supply_request_item_id === form.data.supply_request_item_id) ?? null;
    const encodedSourceIds = new Set(items.data.map((row) => Number(row.supply_request_item_id)));
    const isDuplicateSelection = form.data.supply_request_item_id > 0 && encodedSourceIds.has(Number(form.data.supply_request_item_id));

    const applySourceItem = (supplyRequestItemId: number) => {
        const row = sourceItems.find((item) => item.supply_request_item_id === supplyRequestItemId);
        if (!row) {
            form.setData('supply_request_item_id', 0);
            form.setData('item_id', 0);
            form.setData('item_name_snapshot', '');
            return;
        }

        form.setData('supply_request_item_id', row.supply_request_item_id);
        form.setData('item_id', row.item_id);
        form.setData('item_name_snapshot', row.item_name_snapshot);

        if (!form.data.unit.trim()) {
            form.setData('unit', row.default_unit || '');
        }

        if (!form.data.quantity_ordered) {
            form.setData('quantity_ordered', String(Number(row.quantity_requested ?? 0).toFixed(2)));
        }
    };

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
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="text-xs uppercase tracking-[0.15em] text-slate-500">Line Encoding</p>
                                <h3 className="mt-1 text-lg font-semibold text-slate-900">{purchaseOrder.po_number ?? `PO #${purchaseOrder.purchase_order_id}`}</h3>
                                <p className="mt-1 text-sm text-slate-600">Select a source request item, then encode quantity and unit price from supplier quotation.</p>
                            </div>
                            <span className="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">{purchaseOrder.status ?? 'DRAFT'}</span>
                        </div>
                    </section>

                    <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4">
                        <select
                            value={form.data.supply_request_item_id || ''}
                            onChange={(event) => applySourceItem(Number(event.target.value))}
                            className="rounded border-gray-300 text-sm md:col-span-2"
                            required
                        >
                            <option value="">Select Source Request Item</option>
                            {sourceItems.map((row) => (
                                <option key={row.supply_request_item_id} value={row.supply_request_item_id}>
                                    {row.item_name_snapshot} · Req Qty {Number(row.quantity_requested ?? 0).toFixed(2)}
                                </option>
                            ))}
                        </select>
                        <input
                            type="number"
                            placeholder="Supply Request Item ID"
                            value={form.data.supply_request_item_id || ''}
                            onChange={(event) => applySourceItem(Number(event.target.value))}
                            className="rounded border-gray-300 text-sm"
                            required
                        />
                        <input
                            type="number"
                            placeholder="Item ID"
                            value={form.data.item_id || ''}
                            onChange={(event) => form.setData('item_id', Number(event.target.value))}
                            className="rounded border-gray-300 text-sm"
                            required
                        />
                        <input
                            type="text"
                            placeholder="Item Name Snapshot"
                            value={form.data.item_name_snapshot}
                            onChange={(event) => form.setData('item_name_snapshot', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                            required
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
                            required
                        />
                        <input
                            type="text"
                            placeholder="Unit"
                            value={form.data.unit}
                            onChange={(event) => form.setData('unit', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                            required
                        />
                        <input
                            type="number"
                            step="0.01"
                            placeholder="Unit Price"
                            value={form.data.unit_price}
                            onChange={(event) => form.setData('unit_price', event.target.value)}
                            className="rounded border-gray-300 text-sm"
                            required
                        />
                        <button type="submit" disabled={isDuplicateSelection || form.processing} className="rounded bg-indigo-600 px-4 py-2 text-sm text-white disabled:cursor-not-allowed disabled:opacity-60">
                            Add Item
                        </button>
                        {isDuplicateSelection ? (
                            <button type="button" className="rounded border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700">
                                Duplicate Not Allowed
                            </button>
                        ) : null}
                        {selectedSource ? (
                            <p className="text-xs text-slate-500 md:col-span-4">
                                Selected source item #{selectedSource.supply_request_item_id} · default unit: {selectedSource.default_unit || '-'}
                            </p>
                        ) : null}
                        {form.errors.supply_request_item_id ? (
                            <p className="text-xs font-semibold text-rose-600 md:col-span-4">{form.errors.supply_request_item_id}</p>
                        ) : null}
                        {isDuplicateSelection ? (
                            <p className="text-xs font-semibold text-amber-700 md:col-span-4">This source request line is already encoded below. Remove the old line first or update it instead of adding again.</p>
                        ) : null}
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
                                    <th className="px-3 py-2 text-right">Actions</th>
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
                                        <td className="px-3 py-2 text-right">
                                            <Link
                                                href={route('procurement.purchase-orders.items.destroy', [purchaseOrder.purchase_order_id, row.purchase_order_item_id])}
                                                method="delete"
                                                as="button"
                                                onClick={(event) => {
                                                    if (!window.confirm('Remove this item line from the purchase order?')) {
                                                        event.preventDefault();
                                                    }
                                                }}
                                                className="rounded border border-rose-300 px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            >
                                                Remove
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                                {items.data.length === 0 ? (
                                    <tr className="border-t">
                                        <td colSpan={7} className="px-3 py-8 text-center text-slate-500">No purchase order items recorded yet.</td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <Link href={route('procurement.purchase-orders.show', purchaseOrder.purchase_order_id)} className="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Back to Purchase Order
                        </Link>
                        <Link href={route('procurement.purchase-orders.index')} className="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Back to Purchase Orders
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
