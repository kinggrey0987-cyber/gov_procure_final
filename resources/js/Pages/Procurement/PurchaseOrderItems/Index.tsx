import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type PurchaseOrder = {
    purchase_order_id: number;
    po_number?: string;
    status?: string;
    is_zero_cost_replacement?: boolean;
    parent_po?: {
        purchase_order_id: number;
        po_number?: string | null;
    } | null;
};

type PurchaseOrderItem = {
    purchase_order_item_id: number;
    supply_request_item_id: number;
    source_request_item_ids?: number[] | null;
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
    request_code?: string;
    office_code?: string;
    suggested_unit_price?: number | null;
};

type Props = {
    purchaseOrder: PurchaseOrder;
    items: {
        data: PurchaseOrderItem[];
    };
    sourceItems: SourceItem[];
    encodedSourceItemIds?: number[];
};

export default function PurchaseOrderItemsIndex({ purchaseOrder, items, sourceItems, encodedSourceItemIds }: Props) {
    const toWholeQuantity = (value: string | number): number => Math.max(Math.trunc(Number(value) || 0), 0);
    const hasSourceItems = sourceItems.length > 0;
    const isDraft = (purchaseOrder.status ?? 'DRAFT') === 'DRAFT';
    const [itemPendingRemoval, setItemPendingRemoval] = useState<PurchaseOrderItem | null>(null);
    const [confirmingProcess, setConfirmingProcess] = useState(false);

    const encodedSourceIds = new Set(
        encodedSourceItemIds && encodedSourceItemIds.length > 0
            ? encodedSourceItemIds.map(Number)
            : items.data.map((row) => Number(row.supply_request_item_id))
    );

    const totalSourceCount = sourceItems.length;
    const unencodedItems = sourceItems.filter((source) => !encodedSourceIds.has(Number(source.supply_request_item_id)));
    const encodedCount = totalSourceCount - unencodedItems.length;
    const remainingCount = unencodedItems.length;

    const isAllItemsEncoded = hasSourceItems ? remainingCount === 0 : items.data.length > 0;
        const isWarranty = Boolean(purchaseOrder.is_zero_cost_replacement);
    const canProcess = isDraft && (isWarranty ? items.data.length > 0 : isAllItemsEncoded);

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
    const isDuplicateSelection = form.data.supply_request_item_id > 0 && encodedSourceIds.has(Number(form.data.supply_request_item_id));

    const applySourceItem = (supplyRequestItemId: number) => {
        const row = sourceItems.find((item) => item.supply_request_item_id === supplyRequestItemId);
        if (!row) {
            form.setData({
                ...form.data,
                supply_request_item_id: 0,
                item_id: 0,
                item_name_snapshot: '',
                specification: '',
                quantity_ordered: '',
                unit: '',
                unit_price: '',
            });
            return;
        }

        form.setData({
            ...form.data,
            supply_request_item_id: row.supply_request_item_id,
            item_id: row.item_id,
            item_name_snapshot: row.item_name_snapshot,
            specification: row.item_specification || '',
            unit: row.default_unit || '',
            quantity_ordered: String(toWholeQuantity(row.quantity_requested ?? 0)),
            unit_price: row.suggested_unit_price && Number(row.suggested_unit_price) > 0 ? String(row.suggested_unit_price) : form.data.unit_price,
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('procurement.purchase-orders.items.store', purchaseOrder.purchase_order_id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
            },
        });
    };

    const processForm = useForm({ remarks: '' });
    const processErrors = processForm.errors as Record<string, string | undefined>;
    const deleteForm = useForm({});

    const requestProcessPurchaseOrder = (event: FormEvent) => {
        event.preventDefault();

        if (!canProcess) {
            return;
        }

        setConfirmingProcess(true);
    };

    const processPurchaseOrder = () => {
        if (!canProcess) {
            return;
        }

        processForm.post(route('procurement.purchase-orders.place', purchaseOrder.purchase_order_id), {
            onSuccess: () => setConfirmingProcess(false),
        });
    };

    const [removalError, setRemovalError] = useState<string | null>(null);

    const confirmItemRemoval = () => {
        if (!itemPendingRemoval) {
            return;
        }

        setRemovalError(null);
        deleteForm.delete(route('procurement.purchase-orders.items.destroy', [purchaseOrder.purchase_order_id, itemPendingRemoval.purchase_order_item_id]), {
            preserveScroll: true,
            onSuccess: () => {
                setItemPendingRemoval(null);
                setRemovalError(null);
            },
            onError: (errs) => {
                const message = Object.values(errs)[0] || 'Unable to remove purchase order item.';
                setRemovalError(String(message));
            },
        });
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

                    {isWarranty ? (
                        <section className="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-6 shadow-sm">
                            <div className="flex items-center gap-3">
                                <span className="text-2xl">🛡️</span>
                                <div>
                                    <h4 className="text-sm font-bold text-indigo-950">Zero-Cost Warranty Replacement Purchase Order</h4>
                                    <p className="mt-0.5 text-xs text-indigo-700">
                                        Items are automatically populated from the delivery discrepancy under supplier warranty rectification (RA 9184). Item lines and pricing are locked to ₱0.00.
                                    </p>
                                </div>
                            </div>
                        </section>
                    ) : (
                    <form onSubmit={submit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                            <div>
                                <h4 className="text-lg font-bold text-slate-800">Source Request Line</h4>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Choose the approved request line. Source identifiers, item specifications, and requested quantities are locked for traceability.
                                </p>
                            </div>
                            {unencodedItems.length > 0 ? (
                                <span className="inline-flex items-center rounded-full bg-blue-50 border border-blue-200 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                    {unencodedItems.length} {unencodedItems.length === 1 ? 'line' : 'lines'} remaining to encode
                                </span>
                            ) : hasSourceItems ? (
                                <span className="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                    All source lines encoded
                                </span>
                            ) : null}
                        </div>

                        {!hasSourceItems ? (
                            <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                No approved source request lines are attached to this purchase order yet. Re-seed or create workflow data with supply request items before encoding purchase order items.
                            </div>
                        ) : null}

                        {/* Step 1: Select Item Line Dropdown (Full Width, No Overlap) */}
                        <div className="space-y-1.5">
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                Select Source Request Item <span className="text-red-600">*</span>
                            </label>
                            <select
                                value={form.data.supply_request_item_id || ''}
                                onChange={(event) => applySourceItem(Number(event.target.value))}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-2xs focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                required
                            >
                                <option value="">-- Choose an approved request line --</option>
                                {sourceItems.map((row) => {
                                    const isAlreadyEncoded = encodedSourceIds.has(Number(row.supply_request_item_id));
                                    return (
                                        <option
                                            key={row.supply_request_item_id}
                                            value={row.supply_request_item_id}
                                            disabled={isAlreadyEncoded}
                                        >
                                            {row.request_code ? `${row.request_code} · ` : ''}SR Line #{row.supply_request_item_id} · {row.item_name_snapshot} · Req Qty: {toWholeQuantity(row.quantity_requested ?? 0)} {row.default_unit || ''}{isAlreadyEncoded ? ' (Already Added)' : ''}
                                        </option>
                                    );
                                })}
                            </select>
                        </div>

                        {/* Step 2: Selected Item Information Card */}
                        {form.data.supply_request_item_id > 0 ? (
                            <div className="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-2.5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="inline-flex items-center rounded-md bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-700">
                                            SR Line #{form.data.supply_request_item_id}
                                        </span>
                                        <span className="text-sm font-bold text-slate-900">
                                            {form.data.item_name_snapshot}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2 text-xs text-slate-500">
                                        <span>Catalog Ref: <strong className="text-slate-700">Item #{form.data.item_id}</strong></span>
                                        {selectedSource?.request_code && (
                                            <>
                                                <span>•</span>
                                                <span>Source Request: <strong className="text-slate-700">{selectedSource.request_code}</strong></span>
                                            </>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                    <div className="rounded-lg bg-white border border-slate-200/70 p-2.5">
                                        <span className="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Quantity to Procure</span>
                                        <span className="mt-0.5 text-base font-bold text-slate-900">
                                            {form.data.quantity_ordered} <span className="text-xs font-semibold text-slate-500">{form.data.unit}</span>
                                        </span>
                                    </div>
                                    <div className="sm:col-span-2 rounded-lg bg-white border border-slate-200/70 p-2.5">
                                        <span className="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Specification from PPMP Remarks</span>
                                        <span className="mt-0.5 block text-xs font-medium text-slate-700">
                                            {form.data.specification || <span className="italic text-slate-400">No special specification provided</span>}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ) : null}

                        {/* Step 3: Supplier Quotation & Add Action */}
                        <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end pt-2">
                            <div className="sm:col-span-6 lg:col-span-5">
                                <label className="block">
                                    <span className="mb-1 flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-slate-700">
                                        <span>Unit Price from Supplier Quote <span className="text-red-600">*</span></span>
                                        {selectedSource?.suggested_unit_price && Number(selectedSource.suggested_unit_price) > 0 ? (
                                            <span className="text-[11px] font-normal lowercase tracking-normal text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                                                PPMP: ₱{Number(selectedSource.suggested_unit_price).toFixed(2)}
                                            </span>
                                        ) : null}
                                    </span>
                                    <div className="relative rounded-lg shadow-2xs">
                                        <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500 font-semibold text-sm select-none">
                                            ₱
                                        </span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            placeholder="0.00"
                                            value={form.data.unit_price}
                                            onChange={(event) => form.setData('unit_price', event.target.value)}
                                            className="w-full rounded-lg border border-slate-300 pl-8 pr-3 py-2 text-sm font-semibold text-slate-900 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                            required
                                            disabled={!form.data.supply_request_item_id}
                                        />
                                    </div>
                                </label>
                            </div>

                            <div className="sm:col-span-3 lg:col-span-4">
                                {form.data.supply_request_item_id && Number(form.data.unit_price) > 0 ? (
                                    <div className="rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-xs">
                                        <span className="text-emerald-700 font-medium block text-[10px] uppercase tracking-wider">Estimated Line Total</span>
                                        <span className="text-sm font-bold text-emerald-900">
                                            ₱{(Number(form.data.quantity_ordered) * Number(form.data.unit_price)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                        </span>
                                    </div>
                                ) : null}
                            </div>

                            <div className="sm:col-span-3 lg:col-span-3 flex justify-end">
                                <button
                                    type="submit"
                                    disabled={isDuplicateSelection || form.processing || !selectedSource || Number(form.data.unit_price) <= 0}
                                    className="w-full inline-flex h-10 items-center justify-center gap-1.5 rounded-lg bg-red-600 px-5 text-sm font-semibold text-white shadow-xs hover:bg-red-700 transition disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span>{form.processing ? 'Adding…' : 'Add Item'}</span>
                                </button>
                            </div>
                        </div>

                        {/* Alerts & Errors */}
                        {isDuplicateSelection ? (
                            <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 flex items-start gap-2">
                                <svg className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div>
                                    <p className="font-semibold">This source request line is already encoded below.</p>
                                    <p className="mt-0.5">Remove the existing line in the table first or update it instead of adding again.</p>
                                </div>
                            </div>
                        ) : null}

                        {form.errors.supply_request_item_id ? (
                            <p className="text-xs font-semibold text-rose-600">{form.errors.supply_request_item_id}</p>
                        ) : null}
                    </form>
                    )}

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
                                        <td className="px-3 py-2 font-medium text-slate-900">
                                            {row.item_name_snapshot}
                                            {row.source_request_item_ids && row.source_request_item_ids.length > 1 ? (
                                                <span className="ml-1.5 inline-flex items-center rounded border border-blue-200 bg-blue-50 px-1.5 py-0.5 text-[10px] font-mono font-semibold text-blue-700">
                                                    SR Lines: #{row.source_request_item_ids.join(', #')} (Merged)
                                                </span>
                                            ) : row.supply_request_item_id ? (
                                                <span className="ml-1.5 inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-mono text-slate-500">
                                                    SR Line #{row.supply_request_item_id}
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="px-3 py-2">{row.specification || '-'}</td>
                                        <td className="px-3 py-2">{row.quantity_ordered}</td>
                                        <td className="px-3 py-2">{row.unit}</td>
                                        <td className="px-3 py-2">{row.unit_price}</td>
                                        <td className="px-3 py-2">{row.total_price}</td>
                                        <td className="px-3 py-2 text-right">
                                            {isWarranty ? (
                                                <span className="inline-flex items-center gap-1 rounded bg-slate-100 border border-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-500">
                                                    🔒 Locked (Warranty)
                                                </span>
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setRemovalError(null);
                                                        setItemPendingRemoval(row);
                                                    }}
                                                    className="rounded border border-rose-300 px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                                >
                                                    Remove
                                                </button>
                                            )}
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

                    {isDraft ? (
                        <form onSubmit={requestProcessPurchaseOrder} className="rounded-2xl border border-red-100 bg-white p-6 shadow-sm">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.15em] text-red-700">Ready for Processing</p>
                                    <h3 className="mt-1 text-xl font-semibold text-slate-800">Process Purchase Order</h3>
                                    <p className="mt-1 text-sm text-slate-600">
                                        After encoding all supplier-quoted item lines, issue the PO and generate the delivery intake workflow.
                                    </p>
                                </div>
                                <span className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                    canProcess 
                                        ? 'bg-emerald-100 text-emerald-700' 
                                        : 'bg-amber-100 text-amber-800'
                                }`}>
                                    {hasSourceItems
                                        ? (remainingCount === 0
                                            ? `All ${totalSourceCount} item(s) encoded`
                                            : `${encodedCount} of ${totalSourceCount} encoded (${remainingCount} remaining)`)
                                        : (canProcess ? `${items.data.length} encoded line(s)` : 'Add at least 1 item')
                                    }
                                </span>
                            </div>

                            {!isAllItemsEncoded && hasSourceItems ? (
                                <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50/90 p-4 text-xs text-amber-900">
                                    <div className="flex items-center gap-2 font-semibold text-amber-800">
                                        <span>⚠️</span>
                                        <span>All source request items must be encoded before this purchase order can be processed.</span>
                                    </div>
                                    <p className="mt-1.5 text-amber-700">
                                        <span className="font-semibold">{remainingCount} item(s) remaining:</span>{' '}
                                        {unencodedItems.map((u) => u.item_name_snapshot).join(', ')}.
                                    </p>
                                </div>
                            ) : null}

                            <label className="mt-4 block">
                                <span className="mb-1 block text-sm font-medium text-slate-700">Processing Remarks</span>
                                <textarea
                                    value={processForm.data.remarks}
                                    onChange={(event) => processForm.setData('remarks', event.target.value)}
                                    className="h-24 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    placeholder="Optional note for PO issuance"
                                />
                            </label>
                            {processErrors.items ? <p className="mt-2 text-xs font-semibold text-rose-600">{processErrors.items}</p> : null}
                            {processErrors.status ? <p className="mt-2 text-xs font-semibold text-rose-600">{processErrors.status}</p> : null}
                            <div className="mt-4 flex flex-wrap justify-end gap-3">
                                <Link href={route('procurement.purchase-orders.show', purchaseOrder.purchase_order_id)} className="rounded-lg border border-red-200 bg-red-50 px-5 py-2 text-sm font-semibold text-red-700 hover:border-red-300 hover:bg-red-100">
                                    Back to Purchase Order
                                </Link>
                                <button
                                    type="submit"
                                    disabled={!canProcess || processForm.processing}
                                    className="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {processForm.processing ? 'Processing…' : 'Process Purchase Order'}
                                </button>
                            </div>
                        </form>
                    ) : null}

                    <Modal show={confirmingProcess} maxWidth="md" closeable={!processForm.processing} onClose={() => setConfirmingProcess(false)}>
                        <div className="p-6">
                            <p className="text-xs font-semibold uppercase tracking-[0.15em] text-red-700">Process Purchase Order</p>
                            <h3 className="mt-2 text-lg font-semibold text-slate-900">Issue this purchase order?</h3>
                            <p className="mt-2 text-sm text-slate-600">
                                This will issue {purchaseOrder.po_number ?? `PO #${purchaseOrder.purchase_order_id}`} and create the delivery intake workflow for all {totalSourceCount || items.data.length} encoded line(s).
                            </p>
                            {processForm.data.remarks ? (
                                <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Processing Remarks</p>
                                    <p className="mt-1 whitespace-pre-wrap text-sm text-slate-700">{processForm.data.remarks}</p>
                                </div>
                            ) : null}
                            <div className="mt-6 flex flex-wrap justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setConfirmingProcess(false)}
                                    disabled={processForm.processing}
                                    className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={processPurchaseOrder}
                                    disabled={!canProcess || processForm.processing}
                                    className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {processForm.processing ? 'Processing…' : 'Issue Purchase Order'}
                                </button>
                            </div>
                        </div>
                    </Modal>

                    <Modal show={Boolean(itemPendingRemoval)} maxWidth="md" onClose={() => setItemPendingRemoval(null)}>
                        <div className="p-6">
                            <p className="text-xs font-semibold uppercase tracking-[0.15em] text-red-700">Remove Item Line</p>
                            <h3 className="mt-2 text-lg font-semibold text-slate-900">Confirm purchase order item removal</h3>
                            <p className="mt-2 text-sm text-slate-600">
                                Remove {itemPendingRemoval?.item_name_snapshot ?? 'this item line'} from this purchase order? This action will update the encoded line list.
                            </p>
                            {removalError ? (
                                <div className="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-700">
                                    {removalError}
                                </div>
                            ) : null}
                            <div className="mt-6 flex flex-wrap justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setItemPendingRemoval(null)}
                                    disabled={deleteForm.processing}
                                    className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={confirmItemRemoval}
                                    disabled={deleteForm.processing}
                                    className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {deleteForm.processing ? 'Removing…' : 'Remove Item'}
                                </button>
                            </div>
                        </div>
                    </Modal>
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
