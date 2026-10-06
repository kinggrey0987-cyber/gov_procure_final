import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { humanizeEnum } from '@/lib/displayLabels';
import { PageProps } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';

type DeliveryItem = {
    delivery_item_id: number;
    accepted_quantity?: number | string;
    expected_quantity: number | string;
    received_quantity: number | string;
    damaged_quantity: number | string;
    missing_quantity: number | string;
    inventory_synced_quantity?: number | string;
    item_condition?: string | null;
    item?: {
        item_id?: number;
        item_name?: string;
    };
};

type DeliveryVerification = {
    delivery_verification_id: number;
    verification_status: string;
    verification_date: string;
    discrepancy_notes?: string | null;
    verifier?: {
        full_name?: string;
        name?: string;
    };
};

type DeliveryDocument = {
    document_id: number;
    title: string;
    file_name: string;
    document_kind?: string | null;
    uploaded_at?: string | null;
    uploader_name?: string | null;
};

type DeliveryAuditStatus = {
    has_dr_no: boolean;
    has_si_no: boolean;
    has_dr_file: boolean;
    has_si_file: boolean;
    has_any_file: boolean;
    is_audit_ready: boolean;
    status_label: string;
};

type Delivery = {
    delivery_id: number;
    delivery_code: string;
    receipt_status: string;
    batch_number?: number;
    total_batches?: number | null;
    delivery_schedule_type?: string;
    is_final_batch?: boolean;
    batch_label?: string;
    sibling_deliveries?: Array<{
        delivery_id: number;
        delivery_code: string;
        batch_number: number;
        total_batches?: number | null;
        receipt_status: string;
        batch_label: string;
        is_current: boolean;
    }>;
    created_at: string;
    delivery_receipt_no?: string | null;
    sales_invoice_no?: string | null;
    sales_invoice_date?: string | null;
    sales_invoice_amount?: number | null;
    audit_status?: DeliveryAuditStatus;
    documents?: DeliveryDocument[];
    permissions?: {
        can_create_follow_up_purchase_request?: boolean;
        can_upload_documents?: boolean;
        can_delete_documents?: boolean;
    };
    discrepancy_summary?: {
        has_discrepancy: boolean;
        total_accepted_quantity: number;
        total_inventory_synced_quantity: number;
        total_missing_quantity: number;
        total_damaged_quantity: number;
        affected_item_count: number;
    };
    follow_up_purchase_request?: {
        purchase_request_id: number;
        pr_number?: string | null;
        status: string;
        source_type?: string | null;
        purchase_order_count?: number;
        purchase_orders?: Array<{
            purchase_order_id: number;
            po_number?: string;
            status?: string;
        }>;
        status_history?: Array<{
            old_status?: string | null;
            new_status?: string | null;
            remarks?: string | null;
            changed_at?: string | null;
        }>;
    } | null;
    purchase_order?: {
        purchase_order_id: number;
        po_number?: string;
        status?: string;
    };
    supplier?: {
        supplier_name?: string;
        contact_person?: string | null;
    };
    receiver?: {
        name?: string;
    };
    items: DeliveryItem[];
    verifications: DeliveryVerification[];
};

type Props = {
    delivery: Delivery;
};

export default function DeliveryShow({ delivery }: Props) {
    const { flash, errors: pageErrors } = usePage<PageProps<{
        flash?: { success?: string; error?: string };
        errors?: Record<string, string>;
    }>>().props;

    const canVerify = delivery.receipt_status === 'RECEIVED' && (!delivery.verifications || delivery.verifications.length === 0);
    const canCreateFollowUpPurchaseRequest = delivery.permissions?.can_create_follow_up_purchase_request === true
        && !delivery.follow_up_purchase_request
        && (delivery.discrepancy_summary?.has_discrepancy ?? false);
    const { post, processing } = useForm({ remarks: '' });
    const [activeTab, setActiveTab] = useState<'overview' | 'items' | 'status' | 'related' | 'notes'>('overview');
    const inventorySyncTotal = Number(delivery.discrepancy_summary?.total_inventory_synced_quantity ?? 0);

    const uploadForm = useForm<{
        title: string;
        document_kind: string;
        file: File | null;
    }>({
        title: '',
        document_kind: 'delivery_receipt',
        file: null,
    });

    const createFollowUpPurchaseRequest = () => {
        post(route('inventory.deliveries.follow-up-purchase-request.store', delivery.delivery_id));
    };

    const handleUploadDocument = (e: FormEvent) => {
        e.preventDefault();
        uploadForm.post(route('documents.store', { related_module: 'deliveries', related_reference_id: delivery.delivery_id }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset('title', 'file');
            },
        });
    };

    const handleDeleteDocument = (documentId: number) => {
        if (!window.confirm('Are you sure you want to delete this attachment?')) {
            return;
        }

        router.delete(route('documents.destroy', documentId), {
            preserveScroll: true,
        });
    };

    const isAuditReady = delivery.audit_status?.is_audit_ready ?? false;

    return (
        <AuthenticatedLayout
            header={
                <div className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div className="flex items-center gap-2">
                                <h2 className="text-xl font-semibold leading-tight text-gray-800">Delivery {delivery.delivery_code}</h2>
                                <span className="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-bold text-indigo-800 border border-indigo-200">
                                    {delivery.batch_label || `Batch #${delivery.batch_number ?? 1}`}
                                    {delivery.is_final_batch && <span className="text-[10px] text-indigo-600 font-normal">(Final)</span>}
                                </span>
                            </div>
                            <p className="mt-0.5 text-xs text-slate-500">
                                Recorded Intake & Verification Details • {delivery.delivery_schedule_type === 'STAGGERED_BATCH' ? 'Staggered Batch Delivery Schedule' : 'Single Delivery Schedule'}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {canVerify ? (
                                <Link href={route('inventory.delivery-verifications.create', delivery.delivery_id)} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                    Verify Delivery
                                </Link>
                            ) : null}
                            {canCreateFollowUpPurchaseRequest ? (
                                <button
                                    type="button"
                                    onClick={createFollowUpPurchaseRequest}
                                    disabled={processing}
                                    className="inline-flex items-center rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100 hover:text-red-900 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-100 disabled:text-slate-500"
                                >
                                    {processing ? 'Creating Follow-up PR...' : 'Create Follow-up PR'}
                                </button>
                            ) : null}
                        </div>
                    </div>

                    <Link
                        href={route('inventory.deliveries.index')}
                        className="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50"
                    >
                        Back to Delivery Monitoring
                    </Link>
                </div>
            }
        >
            <Head title={`Delivery ${delivery.delivery_code}`} />

            <div className="space-y-6 py-8">
                {flash?.success ? (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <span className="font-bold">✓ Success:</span> {flash.success}
                    </div>
                ) : null}
                {flash?.error ? (
                    <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <span className="font-bold">⚠️ Error:</span> {flash.error}
                    </div>
                ) : null}
                {pageErrors && Object.keys(pageErrors).length > 0 && !flash?.error ? (
                    <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <span className="font-bold">⚠️ Error:</span> {Object.values(pageErrors)[0]}
                    </div>
                ) : null}

                {/* Tab Navigation */}
                <section className="rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
                    <div className="grid grid-cols-2 gap-2 md:grid-cols-5">
                        {[
                            { key: 'overview', label: 'Overview & Billing' },
                            { key: 'items', label: `Items (${delivery.items?.length || 0})` },
                            { key: 'status', label: `Verifications (${delivery.verifications?.length || 0})` },
                            { key: 'related', label: 'Related Records' },
                            { key: 'notes', label: `Documents & Scans (${delivery.documents?.length || 0})` },
                        ].map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveTab(tab.key as 'overview' | 'items' | 'status' | 'related' | 'notes')}
                                className={`rounded-lg px-3 py-2 text-xs font-semibold md:text-sm transition ${activeTab === tab.key ? 'bg-indigo-700 text-white shadow-sm' : 'bg-indigo-50/60 text-indigo-800 hover:bg-indigo-100'}`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                </section>

                {activeTab === 'overview' ? (
                    <>
                        {/* Multi-Batch Delivery Schedule Section */}
                        <section className="overflow-hidden rounded-xl border border-indigo-200 bg-white shadow-sm">
                            <div className="flex flex-wrap items-center justify-between border-b border-indigo-100 bg-indigo-50/70 px-6 py-3.5 gap-3">
                                <div>
                                    <p className="text-[11px] font-bold uppercase tracking-wider text-indigo-700">Delivery Schedule & Staggered Batches</p>
                                    <h3 className="text-sm font-extrabold text-indigo-950 flex items-center gap-2">
                                        <span>📦</span>
                                        Purchase Order Batch Allocation • {delivery.purchase_order?.po_number ?? 'PO'}
                                    </h3>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold text-indigo-800 border border-indigo-300">
                                        {delivery.batch_label || `Batch #${delivery.batch_number ?? 1}`}
                                    </span>
                                    {delivery.is_final_batch && (
                                        <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800 border border-emerald-300">
                                            ✓ Final Batch
                                        </span>
                                    )}
                                </div>
                            </div>
                            <div className="p-5">
                                <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-4 mb-2">
                                    <div className="rounded-lg border border-slate-100 bg-slate-50 p-3">
                                        <p className="text-[11px] font-semibold text-slate-500">Current Intake Batch</p>
                                        <p className="text-sm font-bold text-slate-900 mt-0.5">
                                            Batch #{delivery.batch_number ?? 1} {delivery.total_batches ? `of ${delivery.total_batches}` : ''}
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-slate-100 bg-slate-50 p-3">
                                        <p className="text-[11px] font-semibold text-slate-500">Delivery Schedule Type</p>
                                        <p className="text-sm font-bold text-slate-900 mt-0.5">
                                            {delivery.delivery_schedule_type === 'STAGGERED_BATCH' ? 'Staggered Batches' : 'Single Delivery'}
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-slate-100 bg-slate-50 p-3">
                                        <p className="text-[11px] font-semibold text-slate-500">Associated Purchase Order</p>
                                        <p className="text-sm font-bold text-slate-900 mt-0.5">
                                            {delivery.purchase_order ? (
                                                <Link href={route('procurement.purchase-orders.show', delivery.purchase_order.purchase_order_id)} className="text-blue-600 hover:underline">
                                                    {delivery.purchase_order.po_number}
                                                </Link>
                                            ) : '-'}
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-slate-100 bg-slate-50 p-3">
                                        <p className="text-[11px] font-semibold text-slate-500">Total Batches for this PO</p>
                                        <p className="text-sm font-bold text-slate-900 mt-0.5">
                                            {delivery.sibling_deliveries?.length || 1} Batch{(delivery.sibling_deliveries?.length || 1) > 1 ? 'es' : ''} Recorded
                                        </p>
                                    </div>
                                </div>

                                {delivery.sibling_deliveries && delivery.sibling_deliveries.length > 1 && (
                                    <div className="mt-4 pt-3 border-t border-slate-100">
                                        <p className="text-xs font-semibold text-slate-700 mb-2">All Batches for this Purchase Order:</p>
                                        <div className="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                                            {delivery.sibling_deliveries.map((sib) => (
                                                <Link
                                                    key={sib.delivery_id}
                                                    href={route('inventory.deliveries.show', sib.delivery_id)}
                                                    className={`flex items-center justify-between rounded-lg border p-2.5 text-xs transition ${sib.is_current ? 'border-indigo-500 bg-indigo-50/80 ring-1 ring-indigo-500' : 'border-slate-200 bg-white hover:bg-slate-50'}`}
                                                >
                                                    <div>
                                                        <p className="font-bold text-slate-900 flex items-center gap-1.5">
                                                            <span>{sib.batch_label || `Batch #${sib.batch_number}`}</span>
                                                            {sib.is_current && <span className="text-[10px] text-indigo-700 font-semibold">(Current View)</span>}
                                                        </p>
                                                        <p className="font-mono text-[11px] text-slate-500 mt-0.5">{sib.delivery_code}</p>
                                                    </div>
                                                    <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${sib.receipt_status === 'VERIFIED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}>
                                                        {sib.receipt_status}
                                                    </span>
                                                </Link>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </section>

                        {/* 1. Supplier Commercial Documents & Billing (COA Compliance Highlight) */}
                        <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                            <div className="flex flex-wrap items-center justify-between border-b border-slate-200 bg-slate-50/80 px-6 py-4 gap-3">
                                <div>
                                    <p className="text-xs font-bold uppercase tracking-wider text-indigo-700">COA Circular 2023-004 Audit Trail</p>
                                    <h3 className="mt-0.5 text-base font-extrabold text-slate-900 flex items-center gap-2">
                                        <span>🧾</span>
                                        Supplier Billing & Proof of Delivery
                                    </h3>
                                </div>
                                <div>
                                    {isAuditReady ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3.5 py-1 text-xs font-bold text-emerald-700 ring-1 ring-emerald-300 shadow-sm">
                                            <svg className="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            ATTACHED & AUDIT READY
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3.5 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-300 shadow-sm">
                                            <svg className="h-4 w-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            PENDING SCANNED COPY
                                        </span>
                                    )}
                                </div>
                            </div>

                            <div className="p-6">
                                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                    <InfoCell
                                        label="Delivery Receipt (DR) No."
                                        value={delivery.delivery_receipt_no ? <strong className="font-bold text-slate-900">{delivery.delivery_receipt_no}</strong> : <span className="text-slate-400 italic">Not recorded</span>}
                                        mono
                                    />
                                    <InfoCell
                                        label="Sales Invoice (SI) No."
                                        value={delivery.sales_invoice_no ? <strong className="font-bold text-slate-900">{delivery.sales_invoice_no}</strong> : <span className="text-slate-400 italic">Not recorded</span>}
                                        mono
                                    />
                                    <InfoCell
                                        label="Sales Invoice Date"
                                        value={delivery.sales_invoice_date ? new Date(delivery.sales_invoice_date).toLocaleDateString() : '-'}
                                    />
                                    <InfoCell
                                        label="Billed Invoice Amount"
                                        value={
                                            delivery.sales_invoice_amount !== undefined && delivery.sales_invoice_amount !== null
                                                ? <span className="font-bold text-red-700">₱ {Number(delivery.sales_invoice_amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                : '-'
                                        }
                                    />
                                </div>

                                {/* Quick Scanned Files Ribbon */}
                                <div className="mt-5 border-t border-slate-100 pt-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-xs font-bold uppercase tracking-wider text-slate-600">
                                            Scanned Proof & Commercial Attachments ({delivery.documents?.length || 0})
                                        </p>
                                        <button
                                            type="button"
                                            onClick={() => setActiveTab('notes')}
                                            className="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700 hover:bg-indigo-100 transition"
                                        >
                                            <span>+</span> Upload / Manage Scans
                                        </button>
                                    </div>

                                    {delivery.documents && delivery.documents.length > 0 ? (
                                        <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            {delivery.documents.map((doc) => (
                                                <div key={doc.document_id} className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50/70 p-3 text-xs shadow-sm">
                                                    <div className="min-w-0 flex-1 pr-2">
                                                        <p className="font-bold text-slate-900 truncate">{doc.title}</p>
                                                        <p className="text-[11px] text-slate-500 truncate">{doc.file_name}</p>
                                                        {doc.uploaded_at ? (
                                                            <p className="text-[10px] text-slate-400 mt-0.5">Uploaded {new Date(doc.uploaded_at).toLocaleDateString()}</p>
                                                        ) : null}
                                                    </div>
                                                    <div className="flex items-center gap-1.5 shrink-0">
                                                        <a
                                                            href={route('documents.show', doc.document_id)}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1 text-xs font-bold text-indigo-700 border border-slate-300 shadow-sm hover:bg-slate-50 transition"
                                                            title="Preview attachment"
                                                        >
                                                            View
                                                        </a>
                                                        <a
                                                            href={`${route('documents.show', doc.document_id)}?download=1`}
                                                            className="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1 text-xs font-bold text-slate-700 border border-slate-300 shadow-sm hover:bg-slate-50 transition"
                                                            title="Download attachment file"
                                                        >
                                                            Download
                                                        </a>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="mt-3 rounded-lg border border-dashed border-amber-300 bg-amber-50/50 p-4 text-xs text-amber-900 flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <p className="font-semibold">No scanned DR or Sales Invoice uploaded yet.</p>
                                                <p className="mt-0.5 text-amber-700">The physical DR and Invoice numbers have been logged, but the scanned copies or photos from the loading dock have not been attached.</p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => setActiveTab('notes')}
                                                className="rounded-md bg-amber-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-amber-700"
                                            >
                                                Upload Scan Now
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </section>

                        {/* Discrepancy Alert (if applicable) */}
                        {delivery.receipt_status === 'PARTIALLY_RECEIVED' && ((delivery.discrepancy_summary?.has_discrepancy ?? false) || Boolean(delivery.follow_up_purchase_request)) ? (
                            <section className="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm">
                                <div className="border-b border-amber-200 bg-amber-50 px-5 py-4">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="text-xs font-semibold uppercase tracking-wide text-amber-700">Discrepancy Follow-up</p>
                                            <h3 className="mt-1 text-base font-semibold text-slate-900">Partially Received Delivery</h3>
                                            <p className="mt-2 max-w-3xl text-sm text-slate-700">
                                                This delivery was already verified with discrepancies. Re-verification is locked, and the next GSO step is to endorse the lacking quantities through a linked follow-up purchase request for Procurement Unit processing.
                                            </p>
                                        </div>

                                        <span className="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                            Action Required
                                        </span>
                                    </div>
                                </div>

                                <div className="p-5">
                                    {delivery.discrepancy_summary?.has_discrepancy ? (
                                        <div className="grid gap-3 md:grid-cols-3">
                                            <SummaryCard label="Affected Items" value={delivery.discrepancy_summary.affected_item_count} tone="warning" />
                                            <SummaryCard label="Accepted Quantity" value={delivery.discrepancy_summary.total_accepted_quantity} />
                                            <SummaryCard label="Missing Quantity" value={delivery.discrepancy_summary.total_missing_quantity} tone="warning" />
                                            <SummaryCard label="Damaged Quantity" value={delivery.discrepancy_summary.total_damaged_quantity} tone="warning" />
                                            <SummaryCard
                                                label="Inventory Posting"
                                                value={inventorySyncTotal > 0 ? inventorySyncTotal : 'Pending'}
                                                tone={inventorySyncTotal > 0 ? 'default' : 'warning'}
                                                helperText={inventorySyncTotal > 0 ? undefined : 'Inventory is not updated yet while this delivery is still under discrepancy follow-up.'}
                                            />
                                        </div>
                                    ) : null}

                                    <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        {delivery.follow_up_purchase_request ? (
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Linked Follow-up Request</p>
                                                    <p className="mt-1 text-sm font-semibold text-slate-900">
                                                        {delivery.follow_up_purchase_request.pr_number || `PR-${String(delivery.follow_up_purchase_request.purchase_request_id).padStart(6, '0')}`}
                                                    </p>
                                                    <p className="mt-1 text-sm text-slate-600">
                                                        Status: {humanizeEnum(delivery.follow_up_purchase_request.status)}
                                                    </p>
                                                    <p className="mt-1 text-sm text-slate-600">
                                                        Linked POs: {delivery.follow_up_purchase_request.purchase_order_count ?? 0}
                                                    </p>
                                                </div>

                                                <span className="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                                    Follow-up Recorded
                                                </span>
                                            </div>
                                        ) : (
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Next Step</p>
                                                    <p className="mt-1 text-sm font-semibold text-slate-900">Create the linked follow-up purchase request</p>
                                                    <p className="mt-1 text-sm text-slate-600">
                                                        This will forward the missing and damaged quantities into the Procurement Unit workflow without duplicating the original delivery verification.
                                                    </p>
                                                </div>

                                                <span className="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-200">
                                                    Awaiting GSO Action
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </section>
                        ) : null}

                        {/* Intake Info & Metadata Grid */}
                        <div className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-4">
                            <InfoCell label="Delivery Code" value={delivery.delivery_code} mono />
                            <InfoCell label="Receipt Status" value={<span className={statusClass(delivery.receipt_status)}>{humanizeEnum(delivery.receipt_status)}</span>} />
                            <InfoCell label="Received By Officer" value={delivery.receiver?.name ?? '-'} />
                            <InfoCell label="Intake Date" value={new Date(delivery.created_at).toLocaleDateString()} />
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h3 className="text-sm font-semibold text-slate-900">Purchase Order</h3>
                                <dl className="mt-3 space-y-2 text-sm">
                                    <MetaRow
                                        label="PO Number"
                                        value={delivery.purchase_order?.po_number ?? '-'}
                                    />
                                    <MetaRow label="Status" value={delivery.purchase_order?.status ? humanizeEnum(delivery.purchase_order.status) : '-'} />
                                </dl>
                            </section>

                            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h3 className="text-sm font-semibold text-slate-900">Supplier</h3>
                                <dl className="mt-3 space-y-2 text-sm">
                                    <MetaRow label="Name" value={delivery.supplier?.supplier_name ?? '-'} />
                                    <MetaRow label="Contact" value={delivery.supplier?.contact_person ?? 'N/A'} />
                                </dl>
                            </section>
                        </div>
                    </>
                ) : null}

                {activeTab === 'items' ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="mb-3 text-sm font-semibold text-slate-900">Delivery Items</h3>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-semibold text-slate-700">Item</th>
                                        <th className="px-4 py-2 text-center font-semibold text-slate-700">Expected</th>
                                        <th className="px-4 py-2 text-center font-semibold text-slate-700">Received</th>
                                        <th className="px-4 py-2 text-center font-semibold text-slate-700">Accepted</th>
                                        <th className="px-4 py-2 text-center font-semibold text-slate-700">Damaged</th>
                                        <th className="px-4 py-2 text-center font-semibold text-slate-700">Missing</th>
                                        <th className="px-4 py-2 text-center font-semibold text-slate-700">Added to Inventory</th>
                                        <th className="px-4 py-2 text-left font-semibold text-slate-700">Condition</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {delivery.items.map((item) => (
                                        <tr key={item.delivery_item_id} className="hover:bg-slate-50/60">
                                            <td className="px-4 py-2 text-slate-700 font-medium">{item.item?.item_name ?? '-'}</td>
                                            <td className="px-4 py-2 text-center text-slate-700">{item.expected_quantity}</td>
                                            <td className="px-4 py-2 text-center font-semibold text-slate-800">{item.received_quantity}</td>
                                            <td className="px-4 py-2 text-center font-semibold text-emerald-700">{item.accepted_quantity ?? 0}</td>
                                            <td className="px-4 py-2 text-center text-rose-600 font-bold">{item.damaged_quantity}</td>
                                            <td className="px-4 py-2 text-center text-amber-600 font-bold">{item.missing_quantity}</td>
                                            <td className="px-4 py-2 text-center font-semibold text-sky-700">{formatInventorySyncValue(delivery.receipt_status, item.inventory_synced_quantity)}</td>
                                            <td className="px-4 py-2 text-slate-700">{item.item_condition || '-'}</td>
                                        </tr>
                                    ))}
                                    {delivery.items.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="px-4 py-8 text-center text-sm text-slate-500">
                                                No delivery items are linked to this record yet.
                                            </td>
                                        </tr>
                                    ) : null}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ) : null}

                {activeTab === 'status' ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="mb-3 text-sm font-semibold text-slate-900">Inspection & Verifications</h3>
                        {delivery.verifications.length === 0 ? (
                            <p className="text-sm text-slate-500">No verification records yet for this delivery.</p>
                        ) : (
                            <div className="space-y-3">
                                {delivery.verifications.map((verification) => (
                                    <article key={verification.delivery_verification_id} className="rounded-lg border border-slate-200 p-4">
                                        <div className="mb-1 flex items-center justify-between gap-3">
                                            <p className="text-sm font-semibold text-slate-800">{verification.verifier?.full_name ?? verification.verifier?.name ?? '-'}</p>
                                            <span className={verificationClass(verification.verification_status)}>{humanizeEnum(verification.verification_status)}</span>
                                        </div>
                                        <p className="text-xs text-slate-500">{new Date(verification.verification_date).toLocaleDateString()}</p>
                                        {verification.discrepancy_notes ? <p className="mt-2 text-sm text-slate-700 bg-slate-50 p-2.5 rounded border border-slate-200">{verification.discrepancy_notes}</p> : null}
                                    </article>
                                ))}
                            </div>
                        )}
                    </section>
                ) : null}

                {activeTab === 'related' ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-semibold text-slate-900">Related Records</h3>
                        <dl className="mt-3 space-y-2 text-sm">
                            <MetaRow label="Linked Purchase Order" value={delivery.purchase_order?.po_number ?? '-'} />
                            <MetaRow label="Supplier" value={delivery.supplier?.supplier_name ?? '-'} />
                            <MetaRow
                                label="Follow-up Purchase Request"
                                value={delivery.follow_up_purchase_request ? (delivery.follow_up_purchase_request.pr_number || `PR-${String(delivery.follow_up_purchase_request.purchase_request_id).padStart(6, '0')}`) : '-'}
                            />
                        </dl>

                        {delivery.follow_up_purchase_request?.purchase_orders?.length ? (
                            <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Follow-up Purchase Orders</p>
                                <div className="mt-3 space-y-2">
                                    {delivery.follow_up_purchase_request.purchase_orders.map((purchaseOrder) => (
                                        <div key={purchaseOrder.purchase_order_id} className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                            <span className="font-semibold text-slate-800">{purchaseOrder.po_number ?? `PO #${purchaseOrder.purchase_order_id}`}</span>
                                            <span className="text-slate-600">{purchaseOrder.status ? humanizeEnum(purchaseOrder.status) : '-'}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ) : null}

                        {delivery.follow_up_purchase_request?.status_history?.length ? (
                            <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Follow-up PR Workflow</p>
                                <div className="mt-3 space-y-2">
                                    {delivery.follow_up_purchase_request.status_history.map((history, index) => (
                                        <div key={`${history.new_status ?? 'status'}-${index}`} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="font-semibold text-slate-800">{history.new_status ? humanizeEnum(history.new_status) : '-'}</span>
                                                <span className="text-xs text-slate-500">{history.changed_at ? new Date(history.changed_at).toLocaleDateString() : '-'}</span>
                                            </div>
                                            {history.remarks ? <p className="mt-1 text-slate-600">{history.remarks}</p> : null}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ) : null}
                    </section>
                ) : null}

                {/* 5. Documents & Evidence Vault Tab */}
                {activeTab === 'notes' ? (
                    <div className="space-y-6">
                        {/* Upload Form */}
                        <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 className="text-base font-bold text-slate-900 flex items-center gap-2">
                                <span>📤</span> Upload Scanned Document or Camera Photo
                            </h3>
                            <p className="mt-1 text-xs text-slate-500">
                                Attach physical delivery receipts, BIR sales invoices, or inspection photos. Documents are permanently stored for COA audit inspection.
                            </p>

                            <form onSubmit={handleUploadDocument} className="mt-5 space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-700">Document Type</label>
                                        <select
                                            value={uploadForm.data.document_kind}
                                            onChange={(e) => uploadForm.setData('document_kind', e.target.value)}
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-800 focus:border-indigo-600 focus:outline-none"
                                        >
                                            <option value="delivery_receipt">Supplier Delivery Receipt (DR)</option>
                                            <option value="sales_invoice">Supplier Sales Invoice (SI)</option>
                                            <option value="verification_evidence">Inspection / Discrepancy Evidence Photo</option>
                                            <option value="other_supporting_doc">Other Supporting Document</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-700">Document Title</label>
                                        <input
                                            type="text"
                                            value={uploadForm.data.title}
                                            onChange={(e) => uploadForm.setData('title', e.target.value)}
                                            placeholder="e.g. Scanned DR #89201 or Photo of Crushed Boxes"
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-600 focus:outline-none"
                                            required
                                        />
                                        {uploadForm.errors.title && <p className="mt-1 text-xs text-rose-600">{uploadForm.errors.title}</p>}
                                    </div>
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-700">Select File or Snap Photo</label>
                                    <input
                                        type="file"
                                        accept="image/*,application/pdf"
                                        capture="environment"
                                        onChange={(e) => {
                                            const selected = e.target.files?.[0] ?? null;
                                            if (selected && selected.size > 15 * 1024 * 1024) {
                                                uploadForm.setError('file', `Selected file exceeds the 15 MB limit (${(selected.size / (1024 * 1024)).toFixed(1)} MB).`);
                                                e.target.value = '';
                                                uploadForm.setData('file', null);
                                                return;
                                            }
                                            uploadForm.clearErrors('file');
                                            uploadForm.setData('file', selected);
                                        }}
                                        className="w-full rounded-lg border border-slate-300 p-2 text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-indigo-700 cursor-pointer"
                                        required
                                    />
                                    <p className="mt-1 text-xs text-slate-500">Accepted formats: JPG, PNG, PDF (max 15 MB).</p>
                                    {uploadForm.errors.file && <p className="mt-1 text-xs text-rose-600">{uploadForm.errors.file}</p>}
                                </div>

                                <button
                                    type="submit"
                                    disabled={uploadForm.processing}
                                    className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-bold text-white shadow hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {uploadForm.processing ? 'Uploading Document...' : 'Upload Attachment'}
                                </button>
                            </form>
                        </section>

                        {/* Documents List */}
                        <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 className="text-base font-bold text-slate-900">Attached Delivery Records</h3>
                            <p className="mt-0.5 text-xs text-slate-500">All supporting commercial and audit files attached to {delivery.delivery_code}.</p>

                            {delivery.documents && delivery.documents.length > 0 ? (
                                <div className="mt-4 overflow-x-auto">
                                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                                        <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-600">
                                            <tr>
                                                <th className="px-4 py-3 text-left font-bold">Document Title</th>
                                                <th className="px-4 py-3 text-left font-bold">Type</th>
                                                <th className="px-4 py-3 text-left font-bold">File Name</th>
                                                <th className="px-4 py-3 text-left font-bold">Uploaded By</th>
                                                <th className="px-4 py-3 text-right font-bold">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {delivery.documents.map((doc) => (
                                                <tr key={doc.document_id} className="hover:bg-slate-50/60">
                                                    <td className="px-4 py-3 font-semibold text-slate-900">{doc.title}</td>
                                                    <td className="px-4 py-3">
                                                        <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                                            {doc.document_kind ? humanizeEnum(doc.document_kind) : 'Supporting Doc'}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-600 font-mono text-xs">{doc.file_name}</td>
                                                    <td className="px-4 py-3 text-xs text-slate-500">
                                                        {doc.uploader_name || '-'}
                                                        {doc.uploaded_at ? ` on ${new Date(doc.uploaded_at).toLocaleDateString()}` : ''}
                                                    </td>
                                                    <td className="px-4 py-3 text-right whitespace-nowrap space-x-2">
                                                        <a
                                                            href={route('documents.show', doc.document_id)}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="inline-flex items-center rounded bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700 hover:bg-indigo-100"
                                                            title="Preview document in new tab"
                                                        >
                                                            View
                                                        </a>
                                                        <a
                                                            href={`${route('documents.show', doc.document_id)}?download=1`}
                                                            className="inline-flex items-center rounded bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-200"
                                                            title="Download document file"
                                                        >
                                                            Download
                                                        </a>
                                                        {delivery.permissions?.can_delete_documents ? (
                                                            <button
                                                                type="button"
                                                                onClick={() => handleDeleteDocument(doc.document_id)}
                                                                className="inline-flex items-center rounded bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700 hover:bg-rose-100"
                                                            >
                                                                Delete
                                                            </button>
                                                        ) : (
                                                            <span
                                                                className="inline-flex items-center rounded bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500"
                                                                title="Permanent audit evidence. Deletion is locked after delivery receipt."
                                                            >
                                                                🔒 Audit Locked
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="mt-4 rounded-lg border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-xs text-slate-500">
                                    No documents attached yet. Use the upload form above to add scanned receipts, invoices, or photos.
                                </div>
                            )}
                        </section>
                    </div>
                ) : null}
            </div>
        </AuthenticatedLayout>
    );
}

function InfoCell({
    label,
    value,
    mono,
}: {
    label: string;
    value: ReactNode;
    mono?: boolean;
}) {
    return (
        <div>
            <p className="text-xs text-slate-500">{label}</p>
            <p className={`mt-1 text-sm ${mono ? 'font-mono text-xs text-slate-700' : 'text-slate-800'}`}>{value}</p>
        </div>
    );
}

function MetaRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <dt className="text-slate-500">{label}</dt>
            <dd className="text-right text-slate-700">{value}</dd>
        </div>
    );
}

function SummaryCard({
    label,
    value,
    tone = 'default',
    helperText,
}: {
    label: string;
    value: ReactNode;
    tone?: 'default' | 'warning';
    helperText?: ReactNode;
}) {
    const toneClass = tone === 'warning'
        ? 'border-amber-200 bg-amber-50/70 text-amber-900'
        : 'border-slate-200 bg-white text-slate-900';

    return (
        <div className={`rounded-lg border px-4 py-3 shadow-sm ${toneClass}`}>
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-2 text-lg font-semibold">{value}</p>
            {helperText ? <p className="mt-2 text-xs text-slate-600">{helperText}</p> : null}
        </div>
    );
}

function formatInventorySyncValue(receiptStatus: string, quantity: number | string | undefined): string | number {
    const numericQuantity = Number(quantity ?? 0);

    if (receiptStatus === 'PENDING') {
        return 'Pending';
    }

    return numericQuantity;
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        PENDING: `${base} bg-amber-100 text-amber-700`,
        RECEIVED: `${base} bg-blue-100 text-blue-700`,
        VERIFIED: `${base} bg-emerald-100 text-emerald-700`,
        PARTIALLY_RECEIVED: `${base} bg-orange-100 text-orange-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function verificationClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        APPROVED: `${base} bg-emerald-100 text-emerald-700`,
        REJECTED: `${base} bg-rose-100 text-rose-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}
