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
        pr_number?: string | null;
        source_type?: string | null;
        source_delivery_id?: number | null;
        office?: {
            name?: string | null;
            code?: string | null;
        } | null;
    } | null;
    supply_request?: {
        request_code?: string | null;
        request_date?: string | null;
        office?: {
            name?: string | null;
            code?: string | null;
        } | null;
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
    procurement_approver?: {
        name: string;
        role: string;
        approved_at: string;
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
        request_date?: string;
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

type DeliveryItem = {
    delivery_item_id: number;
    item_name: string;
    expected_quantity: number;
    received_quantity: number;
    damaged_quantity: number;
    missing_quantity: number;
    item_condition: string;
    remarks?: string | null;
};

type DeliveryDocument = {
    document_id: number;
    title: string;
    file_name: string;
    uploaded_at?: string | null;
    uploader_name?: string | null;
    view_url: string;
    download_url: string;
};

type DeliveryVerification = {
    verification_id: number;
    verification_status: string;
    inspection_notes?: string | null;
    verified_at?: string | null;
    verifier_name?: string | null;
};

type DeliveryRecord = {
    delivery_id: number;
    delivery_code: string;
    delivery_receipt_no?: string | null;
    sales_invoice_no?: string | null;
    sales_invoice_date?: string | null;
    sales_invoice_amount?: number | null;
    actual_delivery_date?: string | null;
    receipt_status: string;
    remarks?: string | null;
    received_by?: {
        id: number;
        name: string;
    } | null;
    verifications: DeliveryVerification[];
    items: DeliveryItem[];
    documents: DeliveryDocument[];
};

type SourceDiscrepancyDelivery = {
    delivery_id: number;
    delivery_code: string;
    delivery_receipt_no?: string | null;
    sales_invoice_no?: string | null;
    sales_invoice_amount?: number | null;
    receipt_status: string;
    actual_delivery_date?: string | null;
    parent_po_number?: string | null;
    documents: DeliveryDocument[];
};

type PoAttachment = {
    document_id: number;
    title: string;
    file_name: string;
    uploaded_at?: string | null;
    uploaded_by?: number;
    uploader_name?: string | null;
    view_url: string;
    download_url: string;
};

type Props = {
    purchaseOrder: PurchaseOrder;
    isExecutive?: boolean;
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
    deliveries?: DeliveryRecord[];
    sourceDiscrepancyDelivery?: SourceDiscrepancyDelivery | null;
    attachments?: PoAttachment[];
};

export default function PurchaseOrdersShow({
    purchaseOrder,
    isExecutive = false,
    isZeroCostReplacement: propsIsZeroCostReplacement,
    traceability,
    statusHistory,
    approvalHistory,
    permissions,
    backLink,
    showProcurementDocumentsLink = true,
    deliveries = [],
    sourceDiscrepancyDelivery = null,
    attachments = [],
}: Props) {
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

    const signatories = useMemo(() => {
        if (traceability?.requisitioning_signatories && traceability.requisitioning_signatories.length > 0) {
            return traceability.requisitioning_signatories;
        }
        if (traceability?.source_supply_requests && traceability.source_supply_requests.length > 0) {
            const map = new Map<string, { office_code: string; office_name: string; requester_name: string; request_date?: string; request_codes: string[] }>();
            for (const sr of traceability.source_supply_requests) {
                const key = `${sr.office_code}:${sr.requester_name}`;
                if (!map.has(key)) {
                    map.set(key, {
                        office_code: sr.office_code,
                        office_name: sr.office_name,
                        requester_name: sr.requester_name,
                        request_date: sr.request_date,
                        request_codes: [sr.request_code],
                    });
                } else {
                    map.get(key)!.request_codes.push(sr.request_code);
                }
            }
            return Array.from(map.values());
        }
        return [];
    }, [traceability]);

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
                                            className="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 shadow-sm hover:bg-rose-100 disabled:opacity-50"
                                        >
                                            {isExportingPdf ? 'Exporting...' : '📄 Export PDF'}
                                        </button>
                                    </div>
                                )}
                                <span className={statusClass(purchaseOrder.status)}>{purchaseOrder.status}</span>
                            </div>
                        </div>

                        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <Info label="Supplier" value={purchaseOrder.supplier?.supplier_name ?? '-'} />
                            <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <dt className="text-xs uppercase tracking-wide text-slate-500">Linked Request</dt>
                                <dd className="mt-1 font-medium text-slate-800">
                                    {purchaseOrder.purchase_request?.purchase_request_id ? (
                                        <Link
                                            href={route('procurement.purchase-requests.show', purchaseOrder.purchase_request.purchase_request_id)}
                                            className="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline"
                                        >
                                            {purchaseOrder.purchase_request?.pr_number || `PR-${String(purchaseOrder.purchase_request.purchase_request_id).padStart(6, '0')}`}
                                        </Link>
                                    ) : (
                                        purchaseOrder.supply_request?.request_code || '-'
                                    )}
                                </dd>
                            </div>
                            <Info label="Office" value={purchaseOrder.supply_request?.office?.name || purchaseOrder.purchase_request?.office?.name || 'Requisitioning Office'} />
                            <Info label="Supply Request" value={purchaseOrder.supply_request?.request_code ?? '-'} />
                            <Info label="Requester" value={purchaseOrder.supply_request?.requester?.name ?? '-'} />
                            <Info label="Procurement Method" value={humanizeEnum(purchaseOrder.procurement_method ?? '-')} />
                            <Info label="Expected Delivery" value={formatDate(purchaseOrder.expected_delivery_date)} />
                            <Info label="Total Amount" value={formatMoney(Number(purchaseOrder.total_amount ?? 0))} />
                        </div>

                        <div className="mt-3 text-xs text-slate-600">
                            <span className="font-semibold">Remarks:</span> {purchaseOrder.remarks || 'None'}
                        </div>

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
                                    <dt className="text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                        1. Requisitioning Office{signatories.length > 1 ? 's' : ''}
                                    </dt>
                                    {signatories.length === 1 ? (
                                        <>
                                            <dd className="mt-1 text-sm font-semibold text-slate-900">
                                                {signatories[0].requester_name}
                                            </dd>
                                            <dd className="text-xs text-slate-600 truncate" title={signatories[0].office_name}>
                                                {signatories[0].office_name ? `${signatories[0].office_name} (${signatories[0].office_code})` : signatories[0].office_code}
                                            </dd>
                                            <dd className="mt-0.5 text-[11px] text-slate-500">
                                                {signatories[0].request_date ? (signatories[0].request_date.startsWith('Date:') ? signatories[0].request_date : `Date: ${signatories[0].request_date}`) : 'Date: N/A'}
                                            </dd>
                                        </>
                                    ) : signatories.length > 1 ? (
                                        <dd className="mt-1 space-y-2 max-h-32 overflow-y-auto">
                                            {signatories.map((sig) => (
                                                <div key={`${sig.office_code}:${sig.requester_name}`} className="border-t border-slate-200/60 pt-1.5 first:border-0 first:pt-0">
                                                    <p className="text-sm font-semibold text-slate-900">{sig.requester_name}</p>
                                                    <p className="text-xs text-slate-600 truncate" title={sig.office_name}>
                                                        {sig.office_name ? `${sig.office_name} (${sig.office_code})` : sig.office_code}
                                                    </p>
                                                    <p className="mt-0.5 text-[11px] text-slate-500">
                                                        {sig.request_date ? (sig.request_date.startsWith('Date:') ? sig.request_date : `Date: ${sig.request_date}`) : 'Date: N/A'}
                                                    </p>
                                                </div>
                                            ))}
                                        </dd>
                                    ) : (
                                        <>
                                            <dd className="mt-1 text-sm font-semibold text-slate-900">
                                                {purchaseOrder.supply_request?.requester?.name || purchaseOrder.purchase_request?.office?.name || 'Earl'}
                                            </dd>
                                            <dd className="text-xs text-slate-600 truncate">
                                                {purchaseOrder.supply_request?.office?.name || purchaseOrder.purchase_request?.office?.name || 'Requisitioning Office'}
                                            </dd>
                                            <dd className="mt-0.5 text-[11px] text-slate-500">
                                                {purchaseOrder.supply_request?.request_date
                                                    ? `Date: ${formatDate(purchaseOrder.supply_request.request_date)}`
                                                    : purchaseOrder.created_at
                                                    ? `Date: ${formatDate(purchaseOrder.created_at)}`
                                                    : 'Date: N/A'}
                                            </dd>
                                        </>
                                    )}
                                </div>

                                <div className="rounded-lg border border-indigo-200 bg-indigo-50/60 p-3">
                                    <dt className="text-[11px] font-bold uppercase tracking-wide text-indigo-700">2. Forwarded by (GSO)</dt>
                                    <dd className="mt-1 text-sm font-semibold text-indigo-950">
                                        {traceability?.gso_forwarder?.name || 'Gso Rieny'}
                                    </dd>
                                    <dd className="text-xs text-indigo-700">{traceability?.gso_forwarder?.role || 'GSO Property Custodian'}</dd>
                                    <dd className="mt-0.5 text-[11px] text-slate-500">{traceability?.gso_forwarder?.forwarded_at || 'Validated & Forwarded'}</dd>
                                </div>

                                <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <dt className="text-[11px] font-bold uppercase tracking-wide text-slate-500">3. Processed by (BAC)</dt>
                                    <dd className="mt-1 text-sm font-semibold text-slate-900">
                                        {traceability?.procurement_officer?.name || purchaseOrder.creator?.name || 'Proc Jeron'}
                                    </dd>
                                    <dd className="text-xs text-slate-600">{traceability?.procurement_officer?.role || 'BAC Procurement Secretariat'}</dd>
                                    <dd className="mt-0.5 text-[11px] text-slate-500">
                                        {traceability?.procurement_officer?.processed_at || formatDate(purchaseOrder.created_at)}
                                    </dd>
                                </div>

                                <div className="rounded-lg border border-emerald-200 bg-emerald-50/60 p-3">
                                    <dt className="text-[11px] font-bold uppercase tracking-wide text-emerald-700">4. Approved / Placed by</dt>
                                    <dd className="mt-1 text-sm font-semibold text-emerald-950">
                                        {traceability?.procurement_approver?.name || purchaseOrder.approver?.name || purchaseOrder.creator?.name || 'Proc Jeron'}
                                    </dd>
                                    <dd className="text-xs text-emerald-700">{traceability?.procurement_approver?.role || 'Procurement Unit / Authorized Placer'}</dd>
                                    <dd className="mt-0.5 text-[11px] text-emerald-600">
                                        {purchaseOrder.date_approved
                                            ? `Date: ${formatDate(purchaseOrder.date_approved)}`
                                            : traceability?.procurement_approver?.approved_at
                                            ? `Date: ${traceability.procurement_approver.approved_at}`
                                            : (purchaseOrder.status === 'PLACED' ? 'Placed Order' : 'Pending Placement')}
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

                    {/* Executive Exclusive Oversight Dossier: DR, SI, Inspection Reports & Compliance Audit */}
                    {isExecutive ? (
                        <section className="rounded-xl border-2 border-indigo-200/90 bg-gradient-to-b from-white via-indigo-50/20 to-white p-6 shadow-sm space-y-6">
                            {/* Executive Header Banner */}
                            <div className="flex flex-wrap items-start justify-between gap-4 border-b border-indigo-100 pb-4">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <span className="inline-flex items-center gap-1.5 rounded-md bg-indigo-900 px-2.5 py-1 text-xs font-bold text-white shadow-xs">
                                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                            Executive Oversight Dossier
                                        </span>
                                        <span className="text-xs font-semibold text-indigo-900">
                                            Confidential Audit Trail & Verification
                                        </span>
                                    </div>
                                    <h3 className="text-lg font-bold text-slate-900">
                                        Delivery Receipts (DR), Sales Invoices (SI) & Statutory Compliance Audit
                                    </h3>
                                    <p className="text-xs text-slate-600 max-w-3xl">
                                        High-level executive dossier consolidating all supplier delivery receipts, sales invoices, GSO warehouse physical inspection reports, and attached documentary evidence for this Purchase Order pursuant to COA & RA 9184 guidelines.
                                    </p>
                                </div>
                                <div className="text-right">
                                    <span className="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-900">
                                        <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                                        Authorized Executive View
                                    </span>
                                </div>
                            </div>

                            {/* Source Discrepancy Delivery (if zero-cost warranty replacement) */}
                            {sourceDiscrepancyDelivery ? (
                                <div className="rounded-xl border border-amber-300 bg-amber-50/70 p-4 space-y-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-amber-200 pb-2">
                                        <div className="flex items-center gap-2">
                                            <span className="text-base">🛡️</span>
                                            <div>
                                                <h4 className="text-xs font-bold uppercase tracking-wider text-amber-950">
                                                    Warranty Origin: Linked Discrepancy Delivery Audit
                                                </h4>
                                                <p className="text-[11px] text-amber-800">
                                                    This PO rectifies missing/damaged goods from original shipment <strong>{sourceDiscrepancyDelivery.delivery_code}</strong>.
                                                </p>
                                            </div>
                                        </div>
                                        <span className="inline-flex items-center rounded-full bg-amber-200/80 px-2.5 py-0.5 text-xs font-bold text-amber-900">
                                            Original Discrepancy: {humanizeEnum(sourceDiscrepancyDelivery.receipt_status)}
                                        </span>
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-xs">
                                        <div className="rounded-lg border border-amber-200 bg-white/80 p-2.5">
                                            <span className="text-[10px] font-bold uppercase tracking-wider text-amber-700">Originating PO</span>
                                            <p className="mt-0.5 font-bold text-slate-900">{sourceDiscrepancyDelivery.parent_po_number || 'N/A'}</p>
                                        </div>
                                        <div className="rounded-lg border border-amber-200 bg-white/80 p-2.5">
                                            <span className="text-[10px] font-bold uppercase tracking-wider text-amber-700">Original DR No.</span>
                                            <p className="mt-0.5 font-bold text-slate-900">{sourceDiscrepancyDelivery.delivery_receipt_no || 'N/A'}</p>
                                        </div>
                                        <div className="rounded-lg border border-amber-200 bg-white/80 p-2.5">
                                            <span className="text-[10px] font-bold uppercase tracking-wider text-amber-700">Original SI No.</span>
                                            <p className="mt-0.5 font-bold text-slate-900">{sourceDiscrepancyDelivery.sales_invoice_no || 'N/A'}</p>
                                        </div>
                                        <div className="rounded-lg border border-amber-200 bg-white/80 p-2.5">
                                            <span className="text-[10px] font-bold uppercase tracking-wider text-amber-700">Original SI Amount</span>
                                            <p className="mt-0.5 font-bold text-slate-900">
                                                {sourceDiscrepancyDelivery.sales_invoice_amount ? formatMoney(sourceDiscrepancyDelivery.sales_invoice_amount) : 'N/A'}
                                            </p>
                                        </div>
                                    </div>

                                    {sourceDiscrepancyDelivery.documents && sourceDiscrepancyDelivery.documents.length > 0 ? (
                                        <div className="pt-1">
                                            <p className="text-[11px] font-bold uppercase tracking-wider text-amber-900 mb-1.5">
                                                Original Discrepancy Documents & Photographic Evidence:
                                            </p>
                                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                {sourceDiscrepancyDelivery.documents.map((doc) => (
                                                    <div key={doc.document_id} className="flex items-center justify-between rounded-lg border border-amber-200 bg-white p-2.5 text-xs shadow-2xs">
                                                        <div className="min-w-0 pr-2">
                                                            <p className="truncate font-semibold text-slate-900" title={doc.title}>{doc.title}</p>
                                                            <p className="truncate text-[10px] text-slate-500">{doc.file_name}</p>
                                                        </div>
                                                        <div className="flex items-center gap-1.5 shrink-0">
                                                            <a
                                                                href={doc.view_url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="rounded border border-amber-300 bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-900 hover:bg-amber-100"
                                                            >
                                                                View
                                                            </a>
                                                            <a
                                                                href={doc.download_url}
                                                                className="rounded border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                                                            >
                                                                Download
                                                            </a>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ) : null}
                                </div>
                            ) : null}

                            {/* PO Compliance & Statutory Attachments */}
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700">
                                            1. Purchase Order Statutory Documents & Compliance Attachments
                                        </h4>
                                        <p className="text-xs text-slate-500">
                                            Official PO documents, Notice of Award, PhilGEPS postings, and supplier quotations on file.
                                        </p>
                                    </div>
                                    <span className="text-xs font-medium text-slate-500">
                                        {attachments.length} attachment{attachments.length === 1 ? '' : 's'}
                                    </span>
                                </div>

                                {attachments.length > 0 ? (
                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        {attachments.map((att) => (
                                            <div key={att.document_id} className="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-3.5 shadow-2xs hover:border-indigo-300 transition-colors">
                                                <div>
                                                    <div className="flex items-start justify-between gap-2">
                                                        <span className="rounded bg-indigo-50 p-1.5 text-indigo-700">
                                                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                        </span>
                                                        <span className="text-[10px] text-slate-400">{att.uploaded_at || 'Attached'}</span>
                                                    </div>
                                                    <h5 className="mt-2 text-xs font-bold text-slate-900 truncate" title={att.title}>{att.title}</h5>
                                                    <p className="text-[11px] text-slate-500 truncate" title={att.file_name}>{att.file_name}</p>
                                                    {att.uploader_name ? (
                                                        <p className="text-[10px] text-slate-400 mt-1">Uploaded by: {att.uploader_name}</p>
                                                    ) : null}
                                                </div>
                                                <div className="mt-3 flex items-center gap-2 border-t border-slate-100 pt-2.5">
                                                    <a
                                                        href={att.view_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="flex-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-center text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors"
                                                    >
                                                        View in Browser
                                                    </a>
                                                    <a
                                                        href={att.download_url}
                                                        className="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors"
                                                        title="Download document file"
                                                    >
                                                        <svg className="h-3.5 w-3.5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                        </svg>
                                                    </a>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50/60 p-4 text-center text-xs text-slate-500">
                                        No statutory PO compliance attachments uploaded yet.
                                    </div>
                                )}
                            </div>

                            {/* Deliveries, Delivery Receipts (DR) & Sales Invoices (SI) */}
                            <div className="space-y-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700">
                                            2. Delivery Receipts (DR), Sales Invoices (SI) & GSO Warehouse Inspections
                                        </h4>
                                        <p className="text-xs text-slate-500">
                                            Shipment-by-shipment records submitted by supplier and inspected by General Services Office (GSO).
                                        </p>
                                    </div>
                                    <span className="text-xs font-medium text-slate-500">
                                        {deliveries.length} shipment{deliveries.length === 1 ? '' : 's'} recorded
                                    </span>
                                </div>

                                {deliveries.length > 0 ? (
                                    <div className="space-y-4">
                                        {deliveries.map((delivery, dIdx) => (
                                            <div key={delivery.delivery_id} className="rounded-xl border border-slate-300 bg-white p-4 shadow-xs space-y-3.5">
                                                {/* Delivery Header */}
                                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-3">
                                                    <div className="flex items-center gap-3">
                                                        <span className="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-800">
                                                            {dIdx + 1}
                                                        </span>
                                                        <div>
                                                            <span className="font-mono text-sm font-bold text-slate-900">{delivery.delivery_code}</span>
                                                            <span className="text-xs text-slate-500 ml-2">
                                                                Arrival: {delivery.actual_delivery_date || 'Date not recorded'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        {deliveryStatusBadge(delivery.receipt_status)}
                                                        {delivery.received_by?.name ? (
                                                            <span className="text-xs text-slate-500">
                                                                Received by: <strong className="text-slate-800">{delivery.received_by.name}</strong>
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </div>

                                                {/* DR & SI Numbers & Financial Metrics Grid */}
                                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-2.5">
                                                        <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Delivery Receipt (DR) No.</span>
                                                        <p className="mt-0.5 text-sm font-bold font-mono text-indigo-900">
                                                            {delivery.delivery_receipt_no || <span className="text-slate-400 font-sans font-normal italic">Pending / Unencoded</span>}
                                                        </p>
                                                    </div>
                                                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-2.5">
                                                        <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Sales Invoice (SI) No.</span>
                                                        <p className="mt-0.5 text-sm font-bold font-mono text-indigo-900">
                                                            {delivery.sales_invoice_no || <span className="text-slate-400 font-sans font-normal italic">Pending / Unencoded</span>}
                                                        </p>
                                                    </div>
                                                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-2.5">
                                                        <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Sales Invoice Date</span>
                                                        <p className="mt-0.5 text-sm font-semibold text-slate-800">
                                                            {delivery.sales_invoice_date || '-'}
                                                        </p>
                                                    </div>
                                                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-2.5">
                                                        <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Sales Invoice Amount</span>
                                                        <p className="mt-0.5 text-sm font-bold text-slate-900">
                                                            {delivery.sales_invoice_amount ? formatMoney(delivery.sales_invoice_amount) : (isZeroCostReplacement ? '₱0.00 (Warranty)' : '-')}
                                                        </p>
                                                    </div>
                                                </div>

                                                {/* GSO Inspection Verification & Remarks */}
                                                {delivery.verifications && delivery.verifications.length > 0 ? (
                                                    <div className="rounded-lg border border-indigo-100 bg-indigo-50/40 p-3 space-y-1.5">
                                                        <div className="flex items-center justify-between text-xs">
                                                            <span className="font-bold text-indigo-950 uppercase tracking-wide text-[11px]">
                                                                GSO Physical Inspection & Quality Verification:
                                                            </span>
                                                            <span className="font-semibold text-indigo-800">
                                                                Status: {delivery.verifications[0].verification_status}
                                                            </span>
                                                        </div>
                                                        {delivery.verifications[0].inspection_notes ? (
                                                            <p className="text-xs text-slate-700 italic bg-white/70 rounded p-2 border border-indigo-100/60">
                                                                "{delivery.verifications[0].inspection_notes}"
                                                            </p>
                                                        ) : null}
                                                        <div className="flex items-center justify-between text-[11px] text-slate-500 pt-0.5">
                                                            <span>Verified By: <strong>{delivery.verifications[0].verifier_name || 'GSO Property Inspector'}</strong></span>
                                                            <span>Timestamp: {delivery.verifications[0].verified_at || 'Inspected'}</span>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-2.5 text-xs text-slate-500">
                                                        <span className="font-semibold text-slate-700">Inspection Note:</span> Awaiting formal GSO Inspection and Acceptance Report (IAR).
                                                    </div>
                                                )}

                                                {/* Scanned DR / SI / IAR Documents for this Delivery */}
                                                <div className="space-y-2 pt-1">
                                                    <span className="text-[11px] font-bold uppercase tracking-wider text-slate-600">
                                                        Attached Scanned DR, SI & Delivery Documents:
                                                    </span>
                                                    {delivery.documents && delivery.documents.length > 0 ? (
                                                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                            {delivery.documents.map((doc) => (
                                                                <div key={doc.document_id} className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50/70 p-2 text-xs">
                                                                    <div className="min-w-0 pr-2">
                                                                        <p className="truncate font-semibold text-slate-800" title={doc.title}>
                                                                            {doc.title}
                                                                        </p>
                                                                        <p className="truncate text-[10px] text-slate-400">
                                                                            {doc.file_name} {doc.uploaded_at ? `(${doc.uploaded_at})` : ''}
                                                                        </p>
                                                                    </div>
                                                                    <div className="flex items-center gap-1.5 shrink-0">
                                                                        <a
                                                                            href={doc.view_url}
                                                                            target="_blank"
                                                                            rel="noopener noreferrer"
                                                                            className="rounded border border-indigo-300 bg-white px-2 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-50"
                                                                        >
                                                                            View
                                                                        </a>
                                                                        <a
                                                                            href={doc.download_url}
                                                                            className="rounded border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50"
                                                                            title="Download"
                                                                        >
                                                                            <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                                            </svg>
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <p className="text-xs text-slate-400 italic">
                                                            No scanned delivery documents or photos attached to this delivery record yet.
                                                        </p>
                                                    )}
                                                </div>

                                                {/* Delivered Items Breakdown */}
                                                {delivery.items && delivery.items.length > 0 ? (
                                                    <div className="mt-2 overflow-x-auto rounded border border-slate-200">
                                                        <table className="min-w-full divide-y divide-slate-200 text-xs">
                                                            <thead className="bg-slate-50 text-[10px] font-bold uppercase text-slate-600">
                                                                <tr>
                                                                    <th className="px-3 py-1.5 text-left">Delivered Item</th>
                                                                    <th className="px-2 py-1.5 text-right">Expected</th>
                                                                    <th className="px-2 py-1.5 text-right">Received</th>
                                                                    <th className="px-2 py-1.5 text-right">Damaged</th>
                                                                    <th className="px-2 py-1.5 text-right">Missing</th>
                                                                    <th className="px-2 py-1.5 text-center">Condition</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody className="divide-y divide-slate-100 bg-white">
                                                                {delivery.items.map((item) => (
                                                                    <tr key={item.delivery_item_id} className="hover:bg-slate-50/50">
                                                                        <td className="px-3 py-1.5 font-medium text-slate-900">{item.item_name}</td>
                                                                        <td className="px-2 py-1.5 text-right text-slate-600">{item.expected_quantity}</td>
                                                                        <td className="px-2 py-1.5 text-right font-semibold text-emerald-700">{item.received_quantity}</td>
                                                                        <td className={`px-2 py-1.5 text-right font-semibold ${item.damaged_quantity > 0 ? 'text-rose-600' : 'text-slate-400'}`}>
                                                                            {item.damaged_quantity}
                                                                        </td>
                                                                        <td className={`px-2 py-1.5 text-right font-semibold ${item.missing_quantity > 0 ? 'text-amber-600' : 'text-slate-400'}`}>
                                                                            {item.missing_quantity}
                                                                        </td>
                                                                        <td className="px-2 py-1.5 text-center">
                                                                            <span className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold ${item.item_condition === 'GOOD' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>
                                                                                {item.item_condition}
                                                                            </span>
                                                                        </td>
                                                                    </tr>
                                                                ))}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                ) : null}
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center">
                                        <svg className="mx-auto h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        <p className="mt-2 text-xs font-semibold text-slate-700">No Delivery Shipments Recorded Yet</p>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            Once the supplier makes delivery and GSO warehouse encodes the Delivery Receipt (DR) and Sales Invoice (SI), full audit records and scanned documents will appear here.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </section>
                    ) : null}

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
                                                        {purchaseOrder.purchase_request?.pr_number
                                                            || (purchaseOrder.purchase_request?.purchase_request_id
                                                                ? `PR-${String(purchaseOrder.purchase_request.purchase_request_id).padStart(6, '0')}`
                                                                : (purchaseOrder.supply_request?.request_code || 'N/A'))}
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
                                                        Ref: {purchaseOrder.purchase_request?.pr_number || (purchaseOrder.purchase_request?.purchase_request_id ? `PR-${String(purchaseOrder.purchase_request.purchase_request_id).padStart(6, '0')}` : (purchaseOrder.supply_request?.request_code || 'Direct Municipal Order'))}
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

function deliveryStatusBadge(status: string) {
    const base = 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold';
    switch (status) {
        case 'VERIFIED':
            return <span className={`${base} bg-emerald-100 text-emerald-800`}>Verified by GSO</span>;
        case 'RECEIVED':
            return <span className={`${base} bg-blue-100 text-blue-800`}>Received / Pending Inspection</span>;
        case 'PARTIALLY_RECEIVED':
            return <span className={`${base} bg-amber-100 text-amber-800`}>Partially Received</span>;
        case 'DISCREPANCY':
            return <span className={`${base} bg-rose-100 text-rose-800`}>Discrepancy Recorded</span>;
        default:
            return <span className={`${base} bg-slate-100 text-slate-700`}>{humanizeEnum(status)}</span>;
    }
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



