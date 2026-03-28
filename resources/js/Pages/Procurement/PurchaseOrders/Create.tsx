import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useEffect } from 'react';

type PurchaseRequestOption = {
    purchase_request_id: number;
    status: string;
    created_at: string | null;
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
};

type SupplierOption = {
    supplier_id: number;
    supplier_name: string;
};

type Props = {
    eligiblePurchaseRequests: PurchaseRequestOption[];
    suppliers: SupplierOption[];
    poNumberPreview: string;
};

export default function PurchaseOrdersCreate({ eligiblePurchaseRequests, suppliers, poNumberPreview }: Props) {
    const firstPurchaseRequest = eligiblePurchaseRequests[0];

    const form = useForm({
        purchase_request_id: firstPurchaseRequest?.purchase_request_id ?? 0,
        supplier_id: suppliers[0]?.supplier_id ?? 0,
        procurement_method: '',
        expected_delivery_date: '',
        terms_notes: '',
        remarks: '',
    });

    const selectedPurchaseRequest = useMemo(
        () => eligiblePurchaseRequests.find((row) => row.purchase_request_id === Number(form.data.purchase_request_id)) ?? null,
        [eligiblePurchaseRequests, form.data.purchase_request_id],
    );

    const hasEligiblePurchaseRequests = eligiblePurchaseRequests.length > 0;
    const hasSuppliers = suppliers.length > 0;
    const canSubmit = hasEligiblePurchaseRequests && hasSuppliers && !form.processing;

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

            <div className="space-y-6 py-8">
                <section className="overflow-hidden rounded-2xl border border-red-200 bg-gradient-to-r from-red-700 via-red-600 to-red-500 px-6 py-5 text-white shadow-[0_10px_28px_rgba(185,28,28,0.25)]">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-100">BAC Procurement Workflow</p>
                    <h3 className="mt-1 text-2xl font-semibold">Create Purchase Order from Approved Request Source</h3>
                    <p className="mt-1 text-sm text-red-100">Select the eligible Purchase Request first, then assign the supplier and procurement execution details.</p>
                </section>

                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4 flex items-center">
                        <Link
                            href={route('procurement.purchase-orders.index')}
                            className="inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-800 transition"
                        >
                            ← Back to Purchase Orders
                        </Link>
                    </div>

                    <form onSubmit={submit} className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <label className="text-sm font-semibold text-slate-700">Select Purchase Request Source</label>
                        <select
                            value={form.data.purchase_request_id}
                            onChange={(event) => form.setData('purchase_request_id', Number(event.target.value))}
                            className="rounded-lg border-gray-300 text-sm"
                            disabled={!hasEligiblePurchaseRequests}
                            required
                        >
                            {hasEligiblePurchaseRequests ? (
                                eligiblePurchaseRequests.map((row) => (
                                    <option key={row.purchase_request_id} value={row.purchase_request_id}>
                                        PR #{row.purchase_request_id} | {row.supply_request?.request_code || `SR #${row.supply_request?.supply_request_id ?? '-'}`} | {row.office?.code || row.office?.name || 'Unknown Office'}
                                    </option>
                                ))
                            ) : (
                                <option value={0}>No eligible purchase requests available</option>
                            )}
                        </select>

                        <label className="text-sm font-semibold text-slate-700">Select Supplier</label>
                        <select
                            value={form.data.supplier_id}
                            onChange={(event) => form.setData('supplier_id', Number(event.target.value))}
                            className="rounded-lg border-gray-300 text-sm"
                            disabled={!hasSuppliers}
                            required
                        >
                            {hasSuppliers ? (
                                suppliers.map((row) => (
                                    <option key={row.supplier_id} value={row.supplier_id}>
                                        {row.supplier_name}
                                    </option>
                                ))
                            ) : (
                                <option value={0}>No active suppliers available</option>
                            )}
                        </select>

                        <label className="text-sm font-semibold text-slate-700">PO Number</label>
                        <input
                            type="text"
                            value={poNumberPreview || 'Auto-generated on save'}
                            className="rounded-lg border-slate-200 bg-slate-50 text-sm text-slate-600"
                            readOnly
                        />

                        <section className="rounded-xl border border-red-100 bg-red-50/40 p-4">
                            <p className="text-xs font-semibold uppercase tracking-[0.15em] text-red-700">Derived Request Context</p>
                            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                <ContextField label="Requesting Office" value={selectedPurchaseRequest?.office ? `${selectedPurchaseRequest.office.code} - ${selectedPurchaseRequest.office.name}` : 'N/A'} />
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

                        <input
                            type="text"
                            placeholder="Procurement Method"
                            value={form.data.procurement_method}
                            onChange={(event) => form.setData('procurement_method', event.target.value)}
                            className="rounded-lg border-gray-300 text-sm"
                            required
                        />
                        <input
                            type="date"
                            value={form.data.expected_delivery_date}
                            onChange={(event) => form.setData('expected_delivery_date', event.target.value)}
                            className="rounded-lg border-gray-300 text-sm"
                        />
                        <textarea
                            placeholder="Terms Notes"
                            value={form.data.terms_notes}
                            onChange={(event) => form.setData('terms_notes', event.target.value)}
                            className="rounded-lg border-gray-300 text-sm"
                            rows={3}
                        />
                        <textarea
                            placeholder="Remarks"
                            value={form.data.remarks}
                            onChange={(event) => form.setData('remarks', event.target.value)}
                            className="rounded-lg border-gray-300 text-sm"
                            rows={3}
                        />

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

                        <button type="submit" disabled={!canSubmit} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60">
                            Create Purchase Order
                        </button>
                    </form>
                </div>
            </div>
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
