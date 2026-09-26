import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useEffect } from 'react';
import { PROCUREMENT_METHODS, DEFAULT_PROCUREMENT_METHOD } from '@/constants/procurementMethods';
import { todayInputValue } from '@/lib/dateInput';

type PurchaseRequestOption = {
    purchase_request_id: number;
    status: string;
    created_at: string | null;
    source_type?: string | null;
    source_delivery_id?: number | null;
    is_discrepancy_follow_up?: boolean;
    suggested_supplier_id?: number | null;
    suggested_supplier_name?: string | null;
    parent_delivery_code?: string | null;
    parent_po_number?: string | null;
    office: {
        id: number;
        code: string;
        name: string;
    } | null;
    supply_request: {
        supply_request_id: number;
        request_code: string;
    } | null;
    origin_requester: {
        id: number;
        name: string;
    } | null;
    item_category_id?: number | null;
    category_name?: string | null;
    items: Array<{
        purchase_request_item_id: number;
        item_name: string;
        category_name: string;
        quantity_requested: number;
        unit?: string;
        remarks?: string | null;
    }>;
};

type SupplierOption = {
    supplier_id: number;
    supplier_name: string;
    category_ids?: number[];
    category_names?: string[];
};

type Props = {
    eligiblePurchaseRequests: PurchaseRequestOption[];
    suppliers: SupplierOption[];
    poNumberPreview: string;
    selectedPurchaseRequestId?: number;
};

export default function PurchaseOrdersCreate({
    eligiblePurchaseRequests,
    suppliers,
    poNumberPreview,
    selectedPurchaseRequestId,
}: Props) {
    const defaultPrId = selectedPurchaseRequestId && eligiblePurchaseRequests.some((r) => r.purchase_request_id === selectedPurchaseRequestId)
        ? selectedPurchaseRequestId
        : (eligiblePurchaseRequests.length === 1 ? (eligiblePurchaseRequests[0]?.purchase_request_id ?? 0) : 0);

    const form = useForm({
        purchase_request_id: defaultPrId,
        supplier_id: 0,
        procurement_method: DEFAULT_PROCUREMENT_METHOD,
        expected_delivery_date: '',
        terms_notes: '',
        remarks: '',
    });

    const selectedPurchaseRequest = useMemo(
        () => eligiblePurchaseRequests.find((row) => row.purchase_request_id === Number(form.data.purchase_request_id)) ?? null,
        [eligiblePurchaseRequests, form.data.purchase_request_id],
    );

    const prCategoryId = selectedPurchaseRequest?.item_category_id;
    const prCategoryName = selectedPurchaseRequest?.category_name;

    const { accreditedSuppliers, otherSuppliers } = useMemo(() => {
        if (!prCategoryId) {
            return { accreditedSuppliers: [], otherSuppliers: suppliers };
        }
        const accredited: SupplierOption[] = [];
        const other: SupplierOption[] = [];
        suppliers.forEach((s) => {
            if (s.category_ids && s.category_ids.includes(prCategoryId)) {
                accredited.push(s);
            } else {
                other.push(s);
            }
        });
        return { accreditedSuppliers: accredited, otherSuppliers: other };
    }, [suppliers, prCategoryId]);

    // Auto-lock supplier and preset replacement details if PR is a discrepancy follow-up
    useEffect(() => {
        if (selectedPurchaseRequest?.is_discrepancy_follow_up && selectedPurchaseRequest.suggested_supplier_id) {
            form.setData((prev) => ({
                ...prev,
                supplier_id: selectedPurchaseRequest.suggested_supplier_id ?? prev.supplier_id,
                procurement_method: 'DIRECT_CONTRACTING',
                remarks: prev.remarks || `Zero-cost warranty replacement for delivery discrepancy under delivery ${selectedPurchaseRequest.parent_delivery_code} (PO ${selectedPurchaseRequest.parent_po_number}).`,
                terms_notes: prev.terms_notes || 'Zero-cost supplier replacement under warranty rectification (RA 9184 & COA).',
            }));
        }
    }, [selectedPurchaseRequest?.purchase_request_id]);

    const hasEligiblePurchaseRequests = eligiblePurchaseRequests.length > 0;
    const hasSuppliers = suppliers.length > 0;
    const canSubmit = hasEligiblePurchaseRequests && hasSuppliers && !form.processing;
    const selectedItems = selectedPurchaseRequest?.items ?? [];
    const selectedTotalQty = selectedItems.reduce((sum, row) => sum + Number(row.quantity_requested ?? 0), 0);
    const today = todayInputValue();

    // Warn before leaving if form has unsaved changes
    useEffect(() => {
        if (!form.isDirty) return;

        const handleBeforeUnload = (e: BeforeUnloadEvent) => {
            e.preventDefault();
            e.returnValue = '';
        };

        window.addEventListener('beforeunload', handleBeforeUnload);
        return () => window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [form.isDirty]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!canSubmit) {
            return;
        }

        form.post(route('procurement.purchase-orders.store'));
    };

    return (
        <AuthenticatedLayout header="Create Purchase Order">
            <Head title="Create Purchase Order" />

            <form onSubmit={submit} className="space-y-6 py-4">
                {Object.keys(form.errors).length > 0 ? (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm">
                        <p className="font-semibold">Unable to create purchase order.</p>
                        <p className="mt-1">Please review the required supplier, request source, and delivery details.</p>
                    </div>
                ) : null}

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.15em] text-red-700">Procurement Unit Workflow</p>
                            <h3 className="mt-1 text-2xl font-semibold text-slate-800">Create Purchase Order</h3>
                            <p className="mt-1 text-sm text-slate-600">Select the approved request source, then assign the supplier and procurement execution details.</p>
                        </div>
                        <Link
                            href={route('procurement.purchase-orders.index')}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Back to Purchase Orders
                        </Link>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        {selectedPurchaseRequest?.is_discrepancy_follow_up ? (
                            <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 shadow-sm md:col-span-2">
                                <div className="flex items-start gap-3">
                                    <span className="text-2xl">🛡️</span>
                                    <div>
                                        <h4 className="font-bold text-amber-950">
                                            Zero-Cost Supplier Replacement Order (COA & RA 9184 Compliant)
                                        </h4>
                                        <p className="mt-1 text-xs text-amber-800">
                                            This Purchase Order replaces damaged or missing goods from Delivery <strong className="font-mono">{selectedPurchaseRequest.parent_delivery_code}</strong> (Original PO: <strong className="font-mono">{selectedPurchaseRequest.parent_po_number}</strong>).
                                        </p>
                                        <ul className="mt-2 list-disc pl-4 text-xs text-amber-900 space-y-0.5">
                                            <li><strong>Supplier Auto-Locked:</strong> Contracted to original supplier <strong>{selectedPurchaseRequest.suggested_supplier_name}</strong>.</li>
                                            <li><strong>₱0.00 Replacement Cost:</strong> Replacement items will be automatically placed at zero additional municipal cost (no budget deduction).</li>
                                            <li><strong>Auto-Resolve:</strong> When GSO inspects and accepts this replacement delivery, the original delivery will automatically transition to <em>VERIFIED</em>.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        ) : null}

                        <label className="block md:col-span-2">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Select Purchase Request Source</span>
                        <select
                            value={form.data.purchase_request_id}
                            onChange={(event) => form.setData('purchase_request_id', Number(event.target.value))}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            disabled={!hasEligiblePurchaseRequests}
                            required
                        >
                            <option value={0}>-- Select Purchase Request Source --</option>
                            {hasEligiblePurchaseRequests ? (
                                eligiblePurchaseRequests.map((row) => (
                                    <option key={row.purchase_request_id} value={row.purchase_request_id}>
                                        {row.is_discrepancy_follow_up ? '🛡️ [WARRANTY REPLACEMENT] ' : ''}
                                        PR #{row.purchase_request_id} | {row.supply_request?.request_code || `SR #${row.supply_request?.supply_request_id ?? '-'}`} | {row.office?.code || row.office?.name || 'Unknown Office'}
                                    </option>
                                ))
                            ) : null}
                        </select>
                        {form.errors.purchase_request_id ? (
                            <p className="mt-1 text-xs font-semibold text-rose-600">{form.errors.purchase_request_id}</p>
                        ) : null}
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">
                                Select Supplier {selectedPurchaseRequest?.is_discrepancy_follow_up ? '(Locked to Original Contractor)' : ''}
                            </span>
                        <select
                            value={form.data.supplier_id}
                            onChange={(event) => form.setData('supplier_id', Number(event.target.value))}
                            className={`w-full rounded-lg border border-slate-300 px-3 py-2 text-sm ${selectedPurchaseRequest?.is_discrepancy_follow_up ? 'bg-slate-100 cursor-not-allowed text-slate-700 font-medium' : ''}`}
                            disabled={!hasSuppliers || Boolean(selectedPurchaseRequest?.is_discrepancy_follow_up)}
                            required
                        >
                            <option value={0}>-- Select a Supplier --</option>
                            {accreditedSuppliers.length > 0 && (
                                <optgroup label={`⭐ Accredited for ${prCategoryName || 'Requested Category'}`}>
                                    {accreditedSuppliers.map((row) => (
                                        <option key={row.supplier_id} value={row.supplier_id}>
                                            ✓ {row.supplier_name} (Accredited)
                                        </option>
                                    ))}
                                </optgroup>
                            )}
                            {otherSuppliers.length > 0 && (
                                <optgroup label={accreditedSuppliers.length > 0 ? 'Other Registered Vendors' : 'All Registered Vendors'}>
                                    {otherSuppliers.map((row) => (
                                        <option key={row.supplier_id} value={row.supplier_id}>
                                            {row.supplier_name}
                                        </option>
                                    ))}
                                </optgroup>
                            )}
                        </select>
                        {prCategoryName ? (
                            <p className="mt-1 text-xs text-slate-600">
                                Requisition Commodity: <span className="font-semibold text-slate-800">{prCategoryName}</span>
                                {accreditedSuppliers.length > 0 ? (
                                    <span className="text-emerald-700 font-medium"> ({accreditedSuppliers.length} accredited vendor{accreditedSuppliers.length === 1 ? '' : 's'})</span>
                                ) : (
                                    <span className="text-amber-700"> (No specialized vendor accredited)</span>
                                )}
                            </p>
                        ) : null}
                        {selectedPurchaseRequest?.is_discrepancy_follow_up ? (
                            <p className="mt-1 text-xs text-amber-700 font-medium">
                                Locked to original delivery contractor under warranty terms.
                            </p>
                        ) : null}
                        {form.errors.supplier_id ? (
                            <p className="mt-1 text-xs font-semibold text-rose-600">{form.errors.supplier_id}</p>
                        ) : null}
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">PO Number</span>
                        <input
                            type="text"
                            value={poNumberPreview || 'Auto-generated on save'}
                                className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600"
                            readOnly
                        />
                        </label>
                    </div>
                </section>

                <section className="rounded-2xl border border-red-100 bg-red-50/40 p-6 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-[0.15em] text-red-700">Derived Request Context</p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <ContextField label="Requesting Office" value={selectedPurchaseRequest?.office ? `${selectedPurchaseRequest.office.code} - ${selectedPurchaseRequest.office.name}` : 'N/A'} />
                        <ContextField label="Commodity Category" value={selectedPurchaseRequest?.category_name || 'General / Uncategorized'} />
                        <ContextField
                            label="Request Reference"
                            value={selectedPurchaseRequest?.supply_request?.request_code || (selectedPurchaseRequest?.supply_request?.supply_request_id ? `SR #${selectedPurchaseRequest.supply_request.supply_request_id}` : 'N/A')}
                        />
                        <ContextField label="Originating Requester" value={selectedPurchaseRequest?.origin_requester?.name || 'N/A'} />
                        <ContextField
                            label="Linked Supply Request"
                            value={selectedPurchaseRequest?.supply_request?.supply_request_id ? `#${selectedPurchaseRequest.supply_request.supply_request_id}` : 'N/A'}
                        />
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Requested Item Context</p>
                                    <p className="mt-1 text-sm text-slate-700">Review requested lines before creating the PO. Final vendor pricing is encoded in the next step (<span className="font-semibold">Manage Items</span>) after PO creation.</p>
                                </div>
                                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-right">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Line Count</p>
                                    <p className="text-base font-semibold text-slate-800">{selectedItems.length}</p>
                                    <p className="mt-1 text-xs uppercase tracking-wide text-slate-500">Total Quantity</p>
                                    <p className="text-base font-semibold text-slate-800">{selectedTotalQty.toFixed(2)}</p>
                                </div>
                            </div>

                            <div className="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold text-slate-700">Item</th>
                                            <th className="px-3 py-2 text-left font-semibold text-slate-700">Category</th>
                                            <th className="px-3 py-2 text-left font-semibold text-slate-700">Qty Requested</th>
                                            <th className="px-3 py-2 text-left font-semibold text-slate-700">Unit</th>
                                            <th className="px-3 py-2 text-left font-semibold text-slate-700">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {selectedItems.map((row) => (
                                            <tr key={row.purchase_request_item_id}>
                                                <td className="px-3 py-2 text-slate-900">
                                                    <p className="font-medium">{row.item_name}</p>
                                                    <p className="text-xs text-slate-500">{row.remarks || 'No line remarks'}</p>
                                                </td>
                                                <td className="px-3 py-2 text-slate-700">{row.category_name || 'Uncategorized'}</td>
                                                <td className="px-3 py-2 text-slate-700">{Number(row.quantity_requested ?? 0).toFixed(2)}</td>
                                                <td className="px-3 py-2 text-slate-700 font-medium">{row.unit || 'piece'}</td>
                                                <td className="px-3 py-2 text-slate-600">To be encoded upon supplier quote (Manage Items)</td>
                                            </tr>
                                        ))}
                                        {selectedItems.length === 0 ? (
                                            <tr>
                                                <td colSpan={5} className="px-3 py-6 text-center text-slate-500">
                                                    No request items loaded for this source.
                                                </td>
                                            </tr>
                                        ) : null}
                                    </tbody>
                                </table>
                            </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h4 className="mb-4 text-xl font-semibold text-slate-800">Procurement Details</h4>
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="block">
                            <span className="text-sm font-semibold text-slate-700">Procurement Method</span>
                            <p className="text-xs text-slate-500 mt-1">This field records the declared mode of procurement used externally (e.g., PhilGEPS) for compliance and audit purposes.</p>
                            <select
                                value={form.data.procurement_method}
                                onChange={(event) => form.setData('procurement_method', event.target.value)}
                                className="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                required
                            >
                                {PROCUREMENT_METHODS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="block">
                            <span className="text-sm font-semibold text-slate-700">Expected Delivery Date</span>
                            <p className="mt-1 text-xs text-slate-500">This is the target supplier delivery date used later as the delivery intake expected arrival date. Past dates are not allowed.</p>
                            <input
                                type="date"
                                min={today}
                                value={form.data.expected_delivery_date}
                                onChange={(event) => form.setData('expected_delivery_date', event.target.value)}
                                className="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                required
                            />
                            {form.errors.expected_delivery_date ? <p className="mt-1 text-xs font-semibold text-rose-600">{form.errors.expected_delivery_date}</p> : null}
                        </label>
                        <label className="block md:col-span-2">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Terms, Conditions & Instructions</span>
                            <textarea
                                value={form.data.terms_notes}
                                onChange={(event) => {
                                    form.setData('terms_notes', event.target.value);
                                    form.setData('remarks', event.target.value);
                                }}
                                placeholder="Payment terms, delivery location, or special supplier instructions..."
                                className="h-24 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </label>
                    </div>
                </section>

                        {!hasEligiblePurchaseRequests ? (
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                <p className="text-sm font-medium text-amber-900">No eligible purchase requests available</p>
                                <p className="text-xs text-amber-700 mt-1">Create and process supply requests first, then convert them to purchase requests before creating a purchase order.</p>
                            </div>
                        ) : null}
                        {!hasSuppliers ? (
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                <p className="text-sm font-medium text-amber-900">No active suppliers available</p>
                                <p className="text-xs text-amber-700 mt-1">Add or activate suppliers in the <Link href={route('procurement.suppliers.index')} className="underline hover:no-underline">Suppliers section</Link> before creating a purchase order.</p>
                            </div>
                        ) : null}

                <div className="flex justify-end">
                    <button type="submit" disabled={!canSubmit} className="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60">
                        Create Purchase Order
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

function ContextField({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-red-100 bg-white px-3 py-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-sm font-medium text-slate-800">{value}</p>
        </div>
    );
}


