import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import WorkflowHistoryTimeline from '@/Components/WorkflowHistoryTimeline';
import { GovLetterhead } from '@/Components/Executive/GovDocumentLayout';
import { downloadSectionAsPdfById, printSectionById } from '@/lib/printSection';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

type PurchaseOrder = {
    purchase_order_id: number;
    po_number: string;
    status: string;
    total_amount: string | number;
    remarks: string | null;
    terms_notes?: string | null;
    procurement_method?: string | null;
    expected_delivery_date?: string | null;
    created_at?: string | null;
    supplier?: {
        supplier_id?: number;
        supplier_name?: string | null;
        contact_person?: string | null;
        address?: string | null;
        phone?: string | null;
        email?: string | null;
    } | null;
    is_zero_cost_replacement?: boolean;
    purchase_request?: {
        purchase_request_id?: number;
        source_type?: string | null;
        source_delivery_id?: number | null;
        office?: {
            name?: string | null;
            code?: string | null;
        } | null;
    } | null;
    supply_request?: {
        request_code?: string | null;
        requester?: {
            name?: string | null;
        } | null;
    } | null;
    creator?: {
        name?: string | null;
    } | null;
    approver?: {
        name?: string | null;
    } | null;
    date_approved?: string | null;
    items?: Array<{
        purchase_order_item_id: number;
        item_name_snapshot?: string | null;
        specification?: string | null;
        quantity_ordered: string | number;
        unit?: string | null;
        unit_price: string | number;
        total_price: string | number;
        item?: {
            item_name?: string | null;
            default_unit?: string | null;
            category?: {
                category_name?: string | null;
            } | null;
        } | null;
    }>;
};

type Traceability = {
    is_consolidated: boolean;
    gso_forwarder?: {
        name: string;
        role: string;
        forwarded_at: string;
    } | null;
    procurement_officer?: {
        name: string;
        role: string;
        processed_at: string;
    } | null;
    executive_approver?: {
        name: string;
        role: string;
        approved_at: string;
    } | null;
    source_supply_requests?: Array<{
        supply_request_id: number;
        request_code: string;
        office_name: string;
        office_code: string;
        requester_name: string;
        request_date: string;
        items: Array<{
            item_name: string;
            quantity: number;
            unit: string;
        }>;
    }>;
    requisitioning_signatories?: Array<{
        office_code: string;
        office_name: string;
        requester_name: string;
        request_codes: string[];
    }>;
    office_allocations?: Array<{
        item_name: string;
        unit: string;
        total_quantity: number;
        allocations: Array<{
            office_name: string;
            office_code: string;
            request_code: string;
            quantity: number;
        }>;
    }>;
};

type Props = {
    purchaseOrder: PurchaseOrder;
    isZeroCostReplacement?: boolean;
    traceability?: Traceability;
    statusHistory: Array<{
        id: number;
        old_status?: string | null;
        new_status: string;
        remarks?: string | null;
        changed_at?: string | null;
        changed_by?: {
            id: number;
            name: string;
            role?: string | null;
            office?: string | null;
        } | null;
    }>;
    approvalHistory: Array<{
        id: number;
        status: string;
        remarks?: string | null;
        approved_at?: string | null;
        approver?: {
            id: number;
            name: string;
            role?: string | null;
            office?: string | null;
        } | null;
    }>;
    permissions?: {
        place?: boolean;
    };
    backLink?: {
        href: string;
        label: string;
    };
    showProcurementDocumentsLink?: boolean;
};

export default function PurchaseOrdersShow({ purchaseOrder, isZeroCostReplacement: propsIsZeroCostReplacement, traceability, statusHistory, approvalHistory, permissions, backLink, showProcurementDocumentsLink = true }: Props) {
    const isZeroCostReplacement = Boolean(
        propsIsZeroCostReplacement ||
        purchaseOrder.is_zero_cost_replacement ||
        purchaseOrder.purchase_request?.source_type === 'PARTIAL_DELIVERY_FOLLOW_UP' ||
        Boolean(purchaseOrder.purchase_request?.source_delivery_id)
    );

    const [isExportingPdf, setIsExportingPdf] = useState(false);
    const isFinishedPo = purchaseOrder.status !== 'DRAFT';
    const exportSectionId = `po-${purchaseOrder.purchase_order_id}-printable-document`;

    const itemPages = useMemo(() => {
        const items = purchaseOrder.items ?? [];
        if (items.length === 0) return [[]];
        const chunks: (typeof items)[] = [];
        const pageSize = 10;
        for (let i = 0; i < items.length; i += pageSize) {
            chunks.push(items.slice(i, i + pageSize));
        }
        return chunks;
    }, [purchaseOrder.items]);

    const totalPages = itemPages.length;

    const exportPdf = async () => {
        if (isExportingPdf || !isFinishedPo) return;
        setIsExportingPdf(true);
        try {
            await downloadSectionAsPdfById(exportSectionId, {
                title: `${purchaseOrder.po_number} - Purchase Order`,
                fileName: `${purchaseOrder.po_number.toLowerCase()}-purchase-order.pdf`,
                marginMm: 6,
                paperWidthPx: 820,
                orientation: 'portrait',
                excludeSelectors: ['.print-hide', 'nav', 'aside', '[role="navigation"]'],
                revealSelectors: ['.pdf-export-content'],
                pageBreakSelectors: ['.pdf-page'],
                includeDefaultHeading: false,
            });
        } finally {
            setIsExportingPdf(false);
        }
    };

    const handlePrint = () => {
        if (!isFinishedPo) return;
        printSectionById(exportSectionId, {
            title: `${purchaseOrder.po_number} - Purchase Order`,
        });
    };

    const placeForm = useForm({ remarks: '' });
    const resolvedBackLink = backLink ?? {
        href: route('procurement.purchase-orders.index'),
        label: 'Back to Purchase Orders',
    };

    const placeOrder = (event: FormEvent) => {
        event.preventDefault();
        placeForm.post(route('procurement.purchase-orders.place', purchaseOrder.purchase_order_id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Purchase Order</h2>}>
            <Head title="Purchase Order" />

            <div className="py-10 print:hidden">
                <div className="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="text-xs uppercase tracking-[0.15em] text-slate-500">Purchase Order Details</p>
                                    {isZeroCostReplacement ? (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                            🛡️ Zero-Cost Warranty Replacement (₱0.00)
                                        </span>
                                    ) : null}
                                </div>
                                <h3 className="mt-1 text-lg font-semibold text-slate-900">{purchaseOrder.po_number}</h3>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                {purchaseOrder.status === 'DRAFT' ? (
                                    <>
                                        <Link
                                            href={route('procurement.purchase-orders.edit', purchaseOrder.purchase_order_id)}
                                            className="rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                                        >
                                            Edit Details
                                        </Link>
                                        <Link
                                            href={route('procurement.purchase-orders.items.index', purchaseOrder.purchase_order_id)}
                                            className="rounded-lg border border-red-300 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-100"
                                        >
                                            Manage Items
                                        </Link>
                                        <span
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-500"
                                            title="Official printing and PDF export are enabled once this purchase order is finalized and placed."
                                        >
                                            <svg className="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            Print & Export locked until Placed
                                        </span>
                                    </>
                                ) : (
                                    <div className="print-hide flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={handlePrint}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors"
                                            title="Print official Purchase Order document"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                            Print PO
                                        </button>
                                        <button
                                            type="button"
                                            onClick={exportPdf}
                                            disabled={isExportingPdf}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                            title="Download official Purchase Order as PDF"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            {isExportingPdf ? 'Exporting PDF...' : 'Export PDF'}
                                        </button>
                                    </div>
                                )}
                                <span className={statusClass(purchaseOrder.status)}>{purchaseOrder.status}</span>
                            </div>
                        </div>

                        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <Info label="Supplier" value={purchaseOrder.supplier?.supplier_name ?? '-'} />
                            <Info label="Linked Request" value={purchaseOrder.purchase_request?.purchase_request_id ? `PR-${String(purchaseOrder.purchase_request.purchase_request_id).padStart(6, '0')}` : '-'} />
                            <Info label="Office" value={purchaseOrder.purchase_request?.office?.name ?? '-'} />
                            <Info label="Supply Request" value={purchaseOrder.supply_request?.request_code ?? '-'} />
                            <Info label="Requester" value={purchaseOrder.supply_request?.requester?.name ?? '-'} />
                            <Info label="Procurement Method" value={humanizeEnum(purchaseOrder.procurement_method)} />
                            <Info label="Expected Delivery" value={formatDate(purchaseOrder.expected_delivery_date)} />
                            <Info
                                label="Total Amount"
                                value={isZeroCostReplacement ? '₱0.00 (Zero Municipal Cost)' : formatMoney(Number(purchaseOrder.total_amount ?? 0))}
                            />
                        </dl>

                        <p className="mt-4 text-sm text-slate-600">
                            <span className="font-semibold text-slate-700">Remarks:</span> {purchaseOrder.remarks ?? 'No remarks provided.'}
                        </p>

                        {isZeroCostReplacement ? (
                            <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                                <p className="font-semibold">🛡️ Zero-Cost Warranty Replacement Notice (COA & RA 9184 Compliant):</p>
                                <p className="mt-0.5">
                                    This purchase order rectifies missing or damaged goods under supplier warranty obligations at zero additional municipal cost. No budget is deducted from the requesting office's PPMP allocation. Once GSO accepts the replacement shipment, the linked partial delivery will automatically resolve to <em>VERIFIED</em>.
                                </p>
                            </div>
                        ) : null}

                        {/* Operational Signatories & Governance Trail */}
                        <div className="mt-5 border-t border-slate-200 pt-4">
                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                                Operational Signatories & Governance Trail
                            </h4>
                            <div className="mt-2.5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <dt className="text-[11px] font-bold uppercase tracking-wide text-slate-500">1. Requisitioning Offices</dt>
                                    {traceability?.requisitioning_signatories && traceability.requisitioning_signatories.length > 0 ? (
                                        <dd className="mt-1 space-y-1">
                                            {traceability.requisitioning_signatories.map((sig) => (
                                                <p key={`${sig.office_code}:${sig.requester_name}`} className="text-xs text-slate-700">
                                                    <span className="font-semibold text-slate-900">{sig.office_code}</span>: {sig.requester_name}
                                                </p>
                                            ))}
                                        </dd>
                                    ) : traceability?.source_supply_requests && traceability.source_supply_requests.length > 0 ? (
                                        <dd className="mt-1 space-y-1">
                                            {Array.from(
                                                new Map(
                                                    traceability.source_supply_requests.map((sr) => [
                                                        `${sr.office_code}:${sr.requester_name}`,
                                                        sr,
                                                    ])
                                                ).values()
                                            ).map((sr) => (
                                                <p key={sr.supply_request_id} className="text-xs text-slate-700">
                                                    <span className="font-semibold text-slate-900">{sr.office_code}</span>: {sr.requester_name}
                                                </p>
                                            ))}
                                        </dd>
                                    ) : (
                                        <dd className="mt-1 font-medium text-slate-800">
                                            {purchaseOrder.supply_request?.requester?.name || purchaseOrder.purchase_request?.office?.name || 'Requesting Office'}
                                        </dd>
                                    )}
                                </div>

                                <div className="rounded-lg border border-indigo-200 bg-indigo-50/60 p-3">
                                    <dt className="text-[11px] font-bold uppercase tracking-wide text-indigo-700">2. Forwarded by (GSO)</dt>
                                    <dd className="mt-1 text-sm font-semibold text-indigo-950">
                                        {traceability?.gso_forwarder?.name || 'GSO Property Officer'}
                                    </dd>
                                    <dd className="text-xs text-indigo-700">{traceability?.gso_forwarder?.role || 'GSO Property Custodian'}</dd>
                                    <dd className="mt-0.5 text-[11px] text-slate-500">{traceability?.gso_forwarder?.forwarded_at || 'Validated & Forwarded'}</dd>
                                </div>

                                <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <dt className="text-[11px] font-bold uppercase tracking-wide text-slate-500">3. Processed by (BAC)</dt>
                                    <dd className="mt-1 text-sm font-semibold text-slate-900">
                                        {traceability?.procurement_officer?.name || purchaseOrder.creator?.name || 'BAC Procurement Officer'}
                                    </dd>
                                    <dd className="text-xs text-slate-600">BAC Procurement Secretariat</dd>
                                    <dd className="mt-0.5 text-[11px] text-slate-500">
                                        {traceability?.procurement_officer?.processed_at || formatDate(purchaseOrder.created_at)}
                                    </dd>
                                </div>

                                <div className="rounded-lg border border-emerald-200 bg-emerald-50/60 p-3">
                                    <dt className="text-[11px] font-bold uppercase tracking-wide text-emerald-700">4. Approved / Placed by</dt>
                                    <dd className="mt-1 text-sm font-semibold text-emerald-950">
                                        {purchaseOrder.approver?.name || 'Hon. Municipal Mayor'}
                                    </dd>
                                    <dd className="text-xs text-emerald-700">Executive Approving Authority</dd>
                                    <dd className="mt-0.5 text-[11px] text-emerald-600">
                                        {purchaseOrder.date_approved ? `Date: ${formatDate(purchaseOrder.date_approved)}` : 'Approved Order'}
                                    </dd>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="responsive-table-wrap rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Category</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Qty</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Unit</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Unit Price</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Line Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {(purchaseOrder.items ?? []).map((row) => (
                                    <tr key={row.purchase_order_item_id}>
                                        <td className="px-4 py-3 text-slate-900">
                                            <p className="font-medium">{row.item_name_snapshot ?? '-'}</p>
                                            <p className="text-xs text-slate-500">{row.specification || 'No specification'}</p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">{row.item?.category?.category_name ?? 'Uncategorized'}</td>
                                        <td className="px-4 py-3 text-slate-700">{Number(row.quantity_ordered ?? 0).toFixed(2)}</td>
                                        <td className="px-4 py-3 text-slate-700">{row.unit ?? '-'}</td>
                                        <td className="px-4 py-3 text-slate-700">{formatMoney(Number(row.unit_price ?? 0))}</td>
                                        <td className="px-4 py-3 font-semibold text-slate-900">{formatMoney(Number(row.total_price ?? 0))}</td>
                                    </tr>
                                ))}
                                {(purchaseOrder.items ?? []).length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                                            <p className="font-medium">No purchase order item lines recorded yet.</p>
                                            {purchaseOrder.status === 'DRAFT' ? (
                                                <>
                                                    <p className="mt-1 text-xs text-slate-400">
                                                        Source items from the linked purchase request must be added and priced before this order can be placed.
                                                    </p>
                                                    <div className="mt-3">
                                                        <Link
                                                            href={route('procurement.purchase-orders.items.index', purchaseOrder.purchase_order_id)}
                                                            className="inline-flex items-center gap-1.5 rounded-lg bg-red-700 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-red-800"
                                                        >
                                                            Add Items from Request
                                                        </Link>
                                                    </div>
                                                </>
                                            ) : null}
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </section>

                    {/* Multi-Office Allocation & Distribution Plan (if consolidated) */}
                    {traceability?.office_allocations && traceability.office_allocations.length > 0 && (traceability.is_consolidated || (traceability.source_supply_requests && traceability.source_supply_requests.length > 1)) ? (
                        <section className="rounded-xl border border-indigo-200 bg-white p-5 shadow-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-indigo-100 pb-3">
                                <div>
                                    <h4 className="text-sm font-bold text-indigo-950">
                                        📦 Multi-Office Allocation & Supply Distribution Plan
                                    </h4>
                                    <p className="text-xs text-slate-500">
                                        Specifies how ordered items will be distributed by GSO across requesting municipal offices upon delivery and IAR inspection.
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-1.5">
                                    {traceability.source_supply_requests?.map((sr) => (
                                        <span key={sr.supply_request_id} className="inline-flex items-center gap-1 rounded bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-xs font-mono font-medium text-indigo-800">
                                            {sr.office_code} ({sr.request_code})
                                        </span>
                                    ))}
                                </div>
                            </div>

                            <div className="mt-3 overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50 text-xs font-semibold text-slate-700">
                                        <tr>
                                            <th className="px-3 py-2 text-left">Item Description</th>
                                            <th className="px-3 py-2 text-center">Unit</th>
                                            <th className="px-3 py-2 text-right">Total Ordered</th>
                                            <th className="px-4 py-2 text-left">Office Allocation Breakdown</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {traceability.office_allocations.map((alloc, idx) => (
                                            <tr key={idx} className="hover:bg-slate-50/50">
                                                <td className="px-3 py-2.5 font-medium text-slate-900">{alloc.item_name}</td>
                                                <td className="px-3 py-2.5 text-center text-slate-600">{alloc.unit}</td>
                                                <td className="px-3 py-2.5 text-right font-bold text-indigo-700">{alloc.total_quantity.toFixed(2)}</td>
                                                <td className="px-4 py-2.5">
                                                    <div className="flex flex-wrap gap-2">
                                                        {alloc.allocations.map((a, aIdx) => (
                                                            <span key={aIdx} className="inline-flex items-center gap-1 rounded border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-800">
                                                                <span className="font-semibold">{a.office_code}:</span>
                                                                <span>{a.quantity.toFixed(2)} {alloc.unit}</span>
                                                                <span className="text-[10px] text-slate-400">({a.request_code})</span>
                                                            </span>
                                                        ))}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    ) : null}

                    {purchaseOrder.status === 'DRAFT' ? (
                        (purchaseOrder.items ?? []).length > 0 && permissions?.place ? (
                            <form onSubmit={placeOrder} className="space-y-3 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                <label className="mb-1 block text-sm font-semibold text-slate-700">Placement Remarks</label>
                                <textarea
                                    value={placeForm.data.remarks}
                                    onChange={(event) => placeForm.setData('remarks', event.target.value)}
                                    placeholder="Add any placement remarks or supplier notes..."
                                    className="w-full rounded-lg border-slate-300 text-sm focus:border-red-500 focus:ring-red-500"
                                    rows={3}
                                />
                                <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
                                    <Link href={resolvedBackLink.href} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                        {resolvedBackLink.label}
                                    </Link>
                                    <button type="submit" className="rounded-lg bg-red-700 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-800">
                                        Place Purchase Order
                                    </button>
                                </div>
                            </form>
                        ) : (
                            <div className="rounded-xl border border-amber-200 bg-amber-50/80 p-5 shadow-sm">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-bold text-amber-900">Purchase Order Items Required Before Placement</p>
                                        <p className="mt-0.5 text-xs text-amber-800">
                                            This purchase order cannot be placed yet because no item lines have been encoded with supplier quotation pricing.
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link href={resolvedBackLink.href} className="rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            {resolvedBackLink.label}
                                        </Link>
                                        <Link
                                            href={route('procurement.purchase-orders.items.index', purchaseOrder.purchase_order_id)}
                                            className="rounded-lg bg-red-700 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-red-800"
                                        >
                                            Add / Encode Items
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        )
                    ) : (
                        <div className="print-hide flex flex-wrap items-center justify-between gap-3">
                            <Link href={resolvedBackLink.href} className="rounded border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                {resolvedBackLink.label}
                            </Link>
                            {isFinishedPo ? (
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={handlePrint}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                        Print PO
                                    </button>
                                    <button
                                        type="button"
                                        onClick={exportPdf}
                                        disabled={isExportingPdf}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-red-50 px-3.5 py-2 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        {isExportingPdf ? 'Exporting PDF...' : 'Export PDF'}
                                    </button>
                                </div>
                            ) : null}
                        </div>
                    )}

                    <WorkflowHistoryTimeline
                        statusHistory={statusHistory}
                        approvalHistory={approvalHistory}
                        title="Purchase Order Workflow & Audit Trail"
                        subtitle="Chronological timeline of PO drafting, item allocation, placement, and delivery verification."
                    />

                    {showProcurementDocumentsLink ? <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="text-lg font-semibold text-slate-900">Purchase Order Documents</h3>
                        <p className="mt-1 text-sm text-slate-600">
                            PO-related files are managed in the central procurement document registry to avoid duplicating workflow documents on this page.
                        </p>

                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <Link
                                href={route('procurement.documents.index', { module: 'purchase_orders' })}
                                className="rounded border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                            >
                                Open Procurement Documents
                            </Link>
                            <p className="text-sm text-slate-500">
                                This keeps purchase-order files searchable from one registry instead of a separate attachment panel.
                            </p>
                        </div>
                    </section> : null}

                </div>
            </div>

            {/* Hidden Printable / PDF Container for Placed & Finished Purchase Orders (COA / GAM Appendix 60) */}
            {isFinishedPo ? (
                <div id={exportSectionId} className="hidden print:block pdf-export-content space-y-6 text-slate-900">
                    {itemPages.map((itemsChunk, pageIndex) => {
                        const pageNumber = pageIndex + 1;
                        const isLast = pageNumber === totalPages;
                        const isFirst = pageNumber === 1;

                        return (
                            <section
                                key={`po-export-page-${pageNumber}`}
                                className="pdf-page bg-white p-6 rounded-xl border border-slate-300 shadow-sm space-y-3.5"
                            >
                                {/* Official LGU Letterhead */}
                                <GovLetterhead
                                    officeName="Bids and Awards Committee / Procurement Office"
                                    subtitle="GovSupply Connect – Municipal Procurement & Transparency Management System"
                                />

                                {/* Standard Appendix 60 Form Header */}
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between text-[11px] text-slate-600 font-medium">
                                        <span>Standard Form: <strong>Appendix 60 - LGU Purchase Order (COA / GAM)</strong></span>
                                        <span>Tracking: <strong>{purchaseOrder.po_number}</strong></span>
                                    </div>

                                    <div className="text-center">
                                        <h1 className="text-xl font-extrabold tracking-tight uppercase text-slate-950">
                                            PURCHASE ORDER
                                        </h1>
                                        <p className="text-xs font-bold uppercase tracking-wider text-red-800">
                                            MUNICIPALITY OF ENRIQUE B. MAGALONA
                                        </p>
                                        {!isFirst && (
                                            <p className="text-[10px] text-slate-500 italic mt-0.5">
                                                (Continuation Sheet — Page {pageNumber} of {totalPages})
                                            </p>
                                        )}
                                    </div>

                                    {/* Top Supplier & Order Metadata Table */}
                                    <div className="rounded border border-slate-400 overflow-hidden text-xs">
                                        <div className="grid grid-cols-2 divide-x divide-slate-400 border-b border-slate-400">
                                            <div className="p-2 space-y-1">
                                                <p>
                                                    <span className="font-bold text-slate-700">Supplier:</span>{' '}
                                                    <strong className="text-slate-950 font-bold text-sm">{purchaseOrder.supplier?.supplier_name || 'N/A'}</strong>
                                                </p>
                                                <p>
                                                    <span className="font-semibold text-slate-600">Address:</span>{' '}
                                                    <span className="text-slate-900">{purchaseOrder.supplier?.address || 'Negros Occidental, Philippines'}</span>
                                                </p>
                                                <p>
                                                    <span className="font-semibold text-slate-600">Contact / TIN:</span>{' '}
                                                    <span className="text-slate-900">{purchaseOrder.supplier?.phone || purchaseOrder.supplier?.email || 'N/A'}</span>
                                                </p>
                                            </div>
                                            <div className="p-2 space-y-1">
                                                <p>
                                                    <span className="font-bold text-slate-700">P.O. No.:</span>{' '}
                                                    <strong className="text-slate-950 font-bold font-mono text-sm">{purchaseOrder.po_number}</strong>
                                                </p>
                                                <p>
                                                    <span className="font-semibold text-slate-600">Date:</span>{' '}
                                                    <span className="text-slate-900">{formatDate(purchaseOrder.date_approved || purchaseOrder.created_at)}</span>
                                                </p>
                                                <p>
                                                    <span className="font-semibold text-slate-600">Mode of Procurement:</span>{' '}
                                                    <span className="text-slate-900">{humanizeEnum(purchaseOrder.procurement_method)}</span>
                                                </p>
                                                <p>
                                                    <span className="font-semibold text-slate-600">P.R. No.(s):</span>{' '}
                                                    <span className="font-mono text-slate-900">
                                                        {purchaseOrder.purchase_request?.purchase_request_id
                                                            ? `PR-${String(purchaseOrder.purchase_request.purchase_request_id).padStart(6, '0')}`
                                                            : (purchaseOrder.supply_request?.request_code || 'N/A')}
                                                    </span>
                                                </p>
                                            </div>
                                        </div>

                                        <div className="bg-slate-50 px-2.5 py-1 border-b border-slate-400 text-[11px] italic text-slate-700">
                                            Gentlemen: Please furnish this Office the following articles subject to the terms and conditions contained herein:
                                        </div>

                                        <div className="grid grid-cols-2 divide-x divide-slate-400">
                                            <div className="p-2 space-y-0.5">
                                                <p>
                                                    <span className="font-semibold text-slate-700">Place of Delivery:</span>{' '}
                                                    <span className="text-slate-900">GSO Warehouse / Municipal Hall, E.B. Magalona</span>
                                                </p>
                                                <p>
                                                    <span className="font-semibold text-slate-700">Date of Delivery:</span>{' '}
                                                    <span className="text-slate-900">
                                                        {purchaseOrder.expected_delivery_date ? formatDate(purchaseOrder.expected_delivery_date) : 'Within 30 Calendar Days upon receipt of PO'}
                                                    </span>
                                                </p>
                                            </div>
                                            <div className="p-2 space-y-0.5">
                                                <p>
                                                    <span className="font-semibold text-slate-700">Delivery Term:</span>{' '}
                                                    <span className="text-slate-900">FOB Destination</span>
                                                </p>
                                                <p>
                                                    <span className="font-semibold text-slate-700">Payment Term:</span>{' '}
                                                    <span className="text-slate-900">Full payment upon complete delivery & IAR acceptance</span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Itemized Table */}
                                <div className="overflow-x-auto rounded border border-slate-400">
                                    <table className="min-w-full divide-y divide-slate-300 text-xs">
                                        <thead className="bg-slate-100 font-bold uppercase text-[10px] tracking-wider text-slate-800">
                                            <tr>
                                                <th className="w-[8%] px-2 py-2 text-center border-r border-slate-300">Item No.</th>
                                                <th className="w-[10%] px-2 py-2 text-center border-r border-slate-300">Unit</th>
                                                <th className="w-[44%] px-3 py-2 text-left border-r border-slate-300">Description & Technical Specifications</th>
                                                <th className="w-[10%] px-2 py-2 text-right border-r border-slate-300">Quantity</th>
                                                <th className="w-[13%] px-2 py-2 text-right border-r border-slate-300">Unit Cost</th>
                                                <th className="w-[15%] px-3 py-2 text-right">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 bg-white">
                                            {itemsChunk.length === 0 ? (
                                                <tr>
                                                    <td colSpan={6} className="px-4 py-6 text-center text-slate-500 italic">No purchase order items recorded.</td>
                                                </tr>
                                            ) : (
                                                itemsChunk.map((item, idx) => {
                                                    const rowNum = pageIndex * 10 + idx + 1;
                                                    return (
                                                        <tr key={item.purchase_order_item_id}>
                                                            <td className="px-2 py-2 text-center font-bold text-slate-700 border-r border-slate-200">{rowNum}</td>
                                                            <td className="px-2 py-2 text-center text-slate-700 border-r border-slate-200">{item.unit || '-'}</td>
                                                            <td className="px-3 py-2 border-r border-slate-200">
                                                                <p className="font-bold text-slate-950">{item.item_name_snapshot || 'Item line'}</p>
                                                                {item.specification ? (
                                                                    <p className="text-[11px] text-slate-600 mt-0.5 leading-snug">{item.specification}</p>
                                                                ) : null}
                                                                {item.item?.category?.category_name ? (
                                                                    <p className="text-[10px] text-slate-400 mt-0.5">Category: {item.item.category.category_name}</p>
                                                                ) : null}
                                                            </td>
                                                            <td className="px-2 py-2 text-right font-semibold text-slate-900 border-r border-slate-200 whitespace-nowrap">
                                                                {Number(item.quantity_ordered ?? 0).toFixed(2)}
                                                            </td>
                                                            <td className="px-2 py-2 text-right text-slate-700 border-r border-slate-200 whitespace-nowrap">
                                                                {formatMoney(Number(item.unit_price ?? 0))}
                                                            </td>
                                                            <td className="px-3 py-2 text-right font-bold text-slate-950 whitespace-nowrap">
                                                                {formatMoney(Number(item.total_price ?? 0))}
                                                            </td>
                                                        </tr>
                                                    );
                                                })
                                            )}
                                        </tbody>
                                        {isLast && (
                                            <tfoot className="border-t-2 border-slate-800 bg-slate-50 text-slate-950">
                                                <tr>
                                                    <td colSpan={3} className="px-3 py-2.5 border-r border-slate-300">
                                                        <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total Amount in Words:</p>
                                                        <p className="text-xs font-extrabold text-slate-900 italic mt-0.5">
                                                            {isZeroCostReplacement
                                                                ? 'Zero Pesos (Zero-Cost Supplier Warranty Replacement under RA 9184)'
                                                                : numberToWords(Number(purchaseOrder.total_amount ?? 0))}
                                                        </p>
                                                    </td>
                                                    <td colSpan={2} className="px-2 py-2.5 text-right font-black uppercase text-[11px] tracking-wider border-r border-slate-300">
                                                        TOTAL (PHP):
                                                    </td>
                                                    <td className="px-3 py-2.5 text-right font-black text-sm text-red-950 whitespace-nowrap">
                                                        {isZeroCostReplacement ? '₱0.00' : formatMoney(Number(purchaseOrder.total_amount ?? 0))}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        )}
                                    </table>
                                </div>

                                {/* Terms & Liquidated Damages Penalty Clause (On Last Page) */}
                                {isLast && (
                                    <>
                                        {purchaseOrder.terms_notes || purchaseOrder.remarks ? (
                                            <div className="rounded border border-slate-300 bg-slate-50/70 p-2 text-[11px] text-slate-700">
                                                <span className="font-bold text-slate-900 uppercase tracking-wide text-[10px]">Special Instructions / Terms:</span>{' '}
                                                {purchaseOrder.terms_notes || purchaseOrder.remarks}
                                            </div>
                                        ) : null}

                                        <div className="rounded border border-slate-300 bg-slate-50/70 px-3 py-2 text-[11px] text-slate-800 leading-snug">
                                            <p className="font-semibold text-slate-900">Liquidated Damages Clause (COA / RA 9184):</p>
                                            <p className="italic text-slate-700 mt-0.5">
                                                In case of failure to make the full delivery within the time specified above, a penalty of one-tenth (1/10) of one percent (0.1%) for every day of delay shall be imposed on the undelivered item/s pursuant to RA 9184 and COA regulations.
                                            </p>
                                        </div>

                                        {/* Signatories Grid (Standard Appendix 60 4-box layout) */}
                                        <div className="border border-slate-400 rounded overflow-hidden text-xs divide-y divide-slate-400">
                                            <div className="grid grid-cols-2 divide-x divide-slate-400">
                                                {/* Conforme (Supplier) */}
                                                <div className="p-3 flex flex-col justify-between h-28">
                                                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-600">Conforme:</p>
                                                    <div>
                                                        <div className="border-b border-slate-900 w-4/5 pb-0.5 mb-1" />
                                                        <p className="text-xs font-bold text-slate-900 uppercase">
                                                            {purchaseOrder.supplier?.contact_person || purchaseOrder.supplier?.supplier_name || 'Signature Over Printed Name'}
                                                        </p>
                                                        <p className="text-[10px] text-slate-500">Authorized Supplier Representative / Date</p>
                                                    </div>
                                                </div>

                                                {/* Very Truly Yours (Head of Procuring Entity - Municipal Mayor) */}
                                                <div className="p-3 flex flex-col justify-between h-28">
                                                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-600">Very truly yours,</p>
                                                    <div>
                                                        <p className="text-xs font-extrabold text-slate-950 uppercase">
                                                            {purchaseOrder.approver?.name || 'Hon. Municipal Mayor'}
                                                        </p>
                                                        <p className="text-[11px] font-bold text-slate-800">Municipal Mayor / Head of the Procuring Entity</p>
                                                        <p className="text-[10px] text-slate-500">Municipality of Enrique B. Magalona</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 divide-x divide-slate-400 bg-slate-50/50">
                                                {/* BAC / Procurement Officer */}
                                                <div className="p-2.5">
                                                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-600">Prepared & Issued By:</p>
                                                    <p className="mt-1 text-xs font-bold text-slate-900">
                                                        {purchaseOrder.creator?.name || traceability?.procurement_officer?.name || 'BAC Procurement Officer'}
                                                    </p>
                                                    <p className="text-[10px] text-slate-600">BAC Procurement Unit / Secretariat</p>
                                                    <p className="text-[9px] text-slate-500 mt-0.5">Date Issued: {formatDate(purchaseOrder.created_at)}</p>
                                                </div>

                                                {/* Requisitioning Office & Fund Cluster */}
                                                <div className="p-2.5">
                                                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-600">Requisitioning Office / Fund Cluster:</p>
                                                    <p className="mt-1 text-xs font-bold text-slate-900">
                                                        {purchaseOrder.purchase_request?.office?.name || 'Requesting Municipal Office'}
                                                    </p>
                                                    <p className="text-[10px] text-slate-600">
                                                        Ref: {purchaseOrder.purchase_request?.purchase_request_id ? `PR-${String(purchaseOrder.purchase_request.purchase_request_id).padStart(6, '0')}` : (purchaseOrder.supply_request?.request_code || 'Direct Municipal Order')}
                                                    </p>
                                                    <p className="text-[9px] text-emerald-700 font-semibold mt-0.5">Status: Placed & Official LGU Record</p>
                                                </div>
                                            </div>
                                        </div>
                                    </>
                                )}

                                {/* Official Document Footer */}
                                <div className="border-t border-slate-300 pt-2 text-[10px] text-slate-500 flex items-center justify-between">
                                    <span>Standard Form: Appendix 60 - LGU Purchase Order (COA / GAM)</span>
                                    <span>GovSupply Connect Authenticated</span>
                                    <span className="font-bold text-slate-800">Page {pageNumber} of {totalPages}</span>
                                </div>
                            </section>
                        );
                    })}
                </div>
            ) : null}
        </AuthenticatedLayout>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <dt className="text-xs uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="mt-1 font-medium text-slate-800">{value}</dd>
        </div>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        DRAFT: `${base} bg-amber-100 text-amber-700`,
        PLACED: `${base} bg-blue-100 text-blue-700`,
        COMPLETED: `${base} bg-emerald-100 text-emerald-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime())
        ? value
        : parsed.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatMoney(value: number) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
}

function numberToWords(num: number): string {
    if (num === 0) return 'Zero Pesos Only';
    if (isNaN(num) || num < 0) return 'N/A';

    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    const scales = ['', 'Thousand', 'Million', 'Billion'];

    const wholePart = Math.floor(num);
    const cents = Math.round((num - wholePart) * 100);

    function convertGroup(n: number): string {
        let str = '';
        if (n >= 100) {
            str += ones[Math.floor(n / 100)] + ' Hundred ';
            n %= 100;
        }
        if (n >= 20) {
            str += tens[Math.floor(n / 10)] + (n % 10 !== 0 ? '-' + ones[n % 10] : '') + ' ';
        } else if (n > 0) {
            str += ones[n] + ' ';
        }
        return str.trim();
    }

    let groupIdx = 0;
    let tempNum = wholePart;
    let words = '';

    while (tempNum > 0) {
        const group = tempNum % 1000;
        if (group !== 0) {
            const groupWords = convertGroup(group);
            const scale = scales[groupIdx];
            words = `${groupWords} ${scale} ${words}`.trim();
        }
        tempNum = Math.floor(tempNum / 1000);
        groupIdx++;
    }

    words = words.trim() || 'Zero';

    if (cents > 0) {
        return `${words} Pesos & ${cents}/100 Only`;
    }
    return `${words} Pesos Only`;
}



