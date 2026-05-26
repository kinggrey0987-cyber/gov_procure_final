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
    specification?: string | null;
    quantity_ordered: string;
    unit: string;
    unit_price: string;
    total_price: string;
};

type SourceItem = {
    supply_request_item_id: number;
    item_id: number;
    item_name_snapshot: string;
    item_specification: string;
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
    const toWholeQuantity = (value: string | number): number => Math.max(Math.trunc(Number(value) || 0), 0);

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
            form.setData('specification', '');
            form.setData('quantity_ordered', '');
            form.setData('unit', '');
            return;
        }

        form.setData('supply_request_item_id', row.supply_request_item_id);
        form.setData('item_id', row.item_id);
        form.setData('item_name_snapshot', row.item_name_snapshot);
        form.setData('specification', row.item_specification || '');
        form.setData('unit', row.default_unit || '');
        form.setData('quantity_ordered', String(toWholeQuantity(row.quantity_requested ?? 0)));
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

            <div className="space-y-6 py-4">
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.15em] text-red-700">Line Encoding</p>
                                <h3 className="mt-1 text-2xl font-semibold text-slate-800">{purchaseOrder.po_number ?? `PO #${purchaseOrder.purchase_order_id}`}</h3>
                                <p className="mt-1 text-sm text-slate-600">Select a source request item, then encode quantity and unit price from supplier quotation.</p>
                            </div>
                            <span className="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">{purchaseOrder.status ?? 'DRAFT'}</span>
                        </div>
                    </section>

                    <form onSubmit={submit} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="md:col-span-4">
                            <p className="text-xl font-semibold text-slate-800">Source Request Line</p>
                            <p className="mt-1 text-xs text-slate-500">Choose the approved request line. Source identifiers, item name, requested quantity, and unit are locked for traceability.</p>
                        </div>
                        <div className="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                        <label className="grid gap-1 text-sm font-semibold text-slate-700 md:col-span-2">
                            Select Source Request Item
                            <select
                                value={form.data.supply_request_item_id || ''}
                                onChange={(event) => applySourceItem(Number(event.target.value))}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"
                                required
                            >
                                <option value="">Select Source Request Item</option>
                                {sourceItems.map((row) => (
                                    <option key={row.supply_request_item_id} value={row.supply_request_item_id}>
                                        SR Line #{row.supply_request_item_id} · {row.item_name_snapshot} · Req Qty {toWholeQuantity(row.quantity_requested ?? 0)} {row.default_unit || ''}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <ReadOnlyField label="Supply Request Line Reference" value={form.data.supply_request_item_id ? `SR Line #${form.data.supply_request_item_id}` : 'Select a source line'} />
                        <ReadOnlyField label="Catalog Item Reference" value={form.data.item_id ? `Item #${form.data.item_id}` : 'Select a source line'} />
                        <ReadOnlyField label="Item Name from Request" value={form.data.item_name_snapshot || 'Select a source line'} />
                        <ReadOnlyField label="Quantity to Procure" value={form.data.quantity_ordered ? `${form.data.quantity_ordered}` : 'Select a source line'} />
                        <ReadOnlyField label="Unit" value={form.data.unit || 'Select a source line'} />
                        <input type="hidden" name="supply_request_item_id" value={form.data.supply_request_item_id || ''} />
                        <input type="hidden" name="item_id" value={form.data.item_id || ''} />
                        <input type="hidden" name="item_name_snapshot" value={form.data.item_name_snapshot} />
                        <input type="hidden" name="quantity_ordered" value={form.data.quantity_ordered} />
                        <input type="hidden" name="unit" value={form.data.unit} />
                        <label className="grid gap-1 text-sm font-semibold text-slate-700 md:col-span-2">
                            Specification from PPMP Item Remarks
                            <input
                                type="text"
                                placeholder="Auto-filled from PPMP item remarks"
                                value={form.data.specification}
                                className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-normal text-slate-700"
                                readOnly
                                aria-readonly="true"
                                required
                            />
                            <span className="text-xs font-normal text-slate-500">This is locked because it is derived from the PPMP item remarks.</span>
                        </label>
                        <label className="grid gap-1 text-sm font-semibold text-slate-700">
                            Unit Price from Supplier Quote
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="0.00"
                                value={form.data.unit_price}
                                onChange={(event) => form.setData('unit_price', event.target.value)}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"
                                required
                            />
                        </label>
                        <button type="submit" disabled={isDuplicateSelection || form.processing || !selectedSource} className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60">
                            Add Item
                        </button>
                        </div>
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

                    <div className="responsive-table-wrap rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left">Item ID</th>
                                    <th className="px-3 py-2 text-left">Name</th>
                                    <th className="px-3 py-2 text-left">Specification</th>
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
                                        <td className="px-3 py-2">{row.specification || '-'}</td>
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
                                        <td colSpan={8} className="px-3 py-8 text-center text-slate-500">No purchase order items recorded yet.</td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <Link href={route('procurement.purchase-orders.show', purchaseOrder.purchase_order_id)} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Back to Purchase Order
                        </Link>
                    </div>
            </div>
        </AuthenticatedLayout>
    );
}

function ReadOnlyField({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-sm font-medium text-slate-800">{value}</p>
        </div>
    );
}



