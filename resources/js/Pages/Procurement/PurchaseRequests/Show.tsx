import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import WorkflowHistoryTimeline from '@/Components/WorkflowHistoryTimeline';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useMemo } from 'react';

type PurchaseRequest = {
    purchase_request_id: number;
    pr_number?: string | null;
    supply_request_id: number | null;
    status: string;
    remarks: string | null;
    created_at?: string | null;
    source_type?: string | null;
    source_delivery_id?: number | null;
    office?: {
        name?: string | null;
        code?: string | null;
    } | null;
    requester?: {
        name?: string | null;
    } | null;
    supply_request?: {
        request_code?: string | null;
        requester?: {
            name?: string | null;
        } | null;
    } | null;
    items?: Array<{
        purchase_request_item_id: number;
        quantity_requested: string | number;
        remarks?: string | null;
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

type Props = {
    purchaseRequest: PurchaseRequest;
    traceability?: Traceability;
    reviewMode?: boolean;
    backFilters?: {
        status?: string;
        search?: string;
    };
    permissions?: {
        process?: boolean;
    };
    backLink?: {
        href: string;
        label: string;
    };
    statusHistory?: Array<{
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
    approvalHistory?: Array<{
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
};

export default function PurchaseRequestsShow({ purchaseRequest, traceability, reviewMode = false, backFilters, permissions, backLink, statusHistory = [], approvalHistory = [] }: Props) {
    const processForm = useForm({ action: 'process', remarks: '' });
    const totalQuantity = (purchaseRequest.items ?? []).reduce((sum, row) => sum + Number(row.quantity_requested ?? 0), 0);
    const normalizedRequestRemarks = formatRequestRemarks(purchaseRequest.remarks);
    const reasonIsBlank = processForm.data.remarks.trim() === '';
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
    const canReviewRequest = purchaseRequest.status === 'OPEN' && permissions?.process && reviewMode;
    const resolvedBackLink = backLink ?? {
        href: route('procurement.purchase-requests.index', {
            status: backFilters?.status || undefined,
            search: backFilters?.search || undefined,
        }),
        label: 'Back to Request Review',
    };

    const processRequest = (event: FormEvent) => {
        event.preventDefault();
        processForm.post(route('procurement.purchase-requests.process', purchaseRequest.purchase_request_id));
    };

    const submitReviewAction = (action: 'process' | 'return' | 'reject') => {
        processForm.transform((data) => ({ ...data, action }));
        processForm.post(route('procurement.purchase-requests.process', purchaseRequest.purchase_request_id), {
            preserveScroll: true,
            onFinish: () => processForm.transform((data) => data),
        });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Purchase Request</h2>}>
            <Head title="Purchase Request" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="text-xs uppercase tracking-[0.15em] text-slate-500">{reviewMode ? 'Request Review' : 'Request Information'}</p>
                                    {purchaseRequest.source_type === 'PARTIAL_DELIVERY_FOLLOW_UP' || Boolean(purchaseRequest.source_delivery_id) ? (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                            🛡️ Discrepancy Replacement PR (Zero-Cost)
                                        </span>
                                    ) : null}
                                    {traceability?.is_consolidated ? (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800">
                                            📦 Consolidated Office PR
                                        </span>
                                    ) : null}
                                </div>
                                <h3 className="mt-1 text-lg font-semibold text-slate-900">
                                    {purchaseRequest.pr_number || `PR-${String(purchaseRequest.purchase_request_id).padStart(6, '0')}`}
                                    {purchaseRequest.supply_request?.request_code ? (
                                        <span className="ml-2 text-sm font-normal text-slate-500">
                                            ({traceability?.is_consolidated ? `Primary Source: ${purchaseRequest.supply_request.request_code}` : purchaseRequest.supply_request.request_code})
                                        </span>
                                    ) : null}
                                </h3>
                            </div>
                            <span className={statusClass(purchaseRequest.status)}>{purchaseRequest.status}</span>
                        </div>

                        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <Info label="Office" value={purchaseRequest.office?.name ?? '-'} />
                            <Info label="Requester" value={purchaseRequest.supply_request?.requester?.name || purchaseRequest.requester?.name || '-'} />
                            <Info label="Supply Request" value={purchaseRequest.supply_request?.request_code || (purchaseRequest.supply_request_id ? `SR #${purchaseRequest.supply_request_id}` : '-')} />
                            <Info label="Date" value={formatDate(purchaseRequest.created_at)} />
                            <Info label="Total Items" value={String(purchaseRequest.items?.length ?? 0)} />
                            <Info label="Total Quantity" value={totalQuantity.toFixed(2)} />
                        </dl>

                        <p className="mt-4 text-sm text-slate-600">
                            <span className="font-semibold text-slate-700">Remarks:</span> {normalizedRequestRemarks}
                        </p>

                        {purchaseRequest.source_type === 'PARTIAL_DELIVERY_FOLLOW_UP' || Boolean(purchaseRequest.source_delivery_id) ? (
                            <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                                <p className="font-semibold">🛡️ Zero-Cost Supplier Rectification Request:</p>
                                <p className="mt-0.5">
                                    Generated from a delivery discrepancy (damaged/missing items). Under COA and RA 9184 standards, this request will generate a ₱0.00 purchase order directly to the original contractor at no additional cost to the municipal government.
                                </p>
                            </div>
                        ) : null}

                        {/* Operational Signatories & Routing Trail */}
                        <div className="mt-5 border-t border-slate-200 pt-4">
                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                                Operational Governance & Routing Trail
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
                                                {purchaseRequest.supply_request?.requester?.name || purchaseRequest.requester?.name || 'Earl'}
                                            </dd>
                                            <dd className="text-xs text-slate-600 truncate">
                                                {purchaseRequest.office?.name || 'Requisitioning Office'}
                                            </dd>
                                            <dd className="mt-0.5 text-[11px] text-slate-500">
                                                {purchaseRequest.created_at ? `Date: ${formatDate(purchaseRequest.created_at)}` : 'Date: N/A'}
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
                                        {traceability?.procurement_officer?.name || 'Proc Jeron'}
                                    </dd>
                                    <dd className="text-xs text-slate-600">{traceability?.procurement_officer?.role || 'BAC Procurement Secretariat'}</dd>
                                    <dd className="mt-0.5 text-[11px] text-slate-500">
                                        {traceability?.procurement_officer?.processed_at || (purchaseRequest.status === 'PROCESSING' ? 'In Procurement Processing' : 'Pending Processing')}
                                    </dd>
                                </div>

                                <div className="rounded-lg border border-emerald-200 bg-emerald-50/60 p-3">
                                    <dt className="text-[11px] font-bold uppercase tracking-wide text-emerald-700">4. Status / Decision</dt>
                                    <dd className="mt-1 text-sm font-semibold text-emerald-950">
                                        {purchaseRequest.status === 'PROCESSING' ? 'Under Canvassing' : purchaseRequest.status === 'COMPLETED' ? 'Purchase Order Placed' : 'Open for Review'}
                                    </dd>
                                    <dd className="text-xs text-emerald-700">BAC Procurement Unit</dd>
                                    <dd className="mt-0.5 text-[11px] text-emerald-600">
                                        Ref: RA 9184 & RA 12009 Standards
                                    </dd>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* Multi-Office Allocation & Traceability Matrix (if consolidated) */}
                    {traceability?.office_allocations && traceability.office_allocations.length > 0 && (traceability.is_consolidated || (traceability.source_supply_requests && traceability.source_supply_requests.length > 1)) ? (
                        <section className="rounded-xl border border-indigo-200 bg-white p-5 shadow-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-indigo-100 pb-3">
                                <div>
                                    <h4 className="text-sm font-bold text-indigo-950">
                                        📦 Multi-Office Allocation Matrix
                                    </h4>
                                    <p className="text-xs text-slate-500">
                                        Breakdown of requested supply shares per office for this consolidated procurement.
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-1.5">
                                    {traceability.source_supply_requests?.map((sr) => (
                                        <span key={sr.supply_request_id} className="inline-flex items-center gap-1 rounded bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-xs font-mono font-medium text-indigo-800">
                                            {sr.request_code} ({sr.office_code})
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
                                            <th className="px-3 py-2 text-right">Consolidated Qty</th>
                                            <th className="px-4 py-2 text-left">Office Breakdown & Share</th>
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

                    <section className="responsive-table-wrap rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Category</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Quantity</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Unit</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Remarks</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {(purchaseRequest.items ?? []).map((row) => (
                                    <tr key={row.purchase_request_item_id}>
                                        <td className="px-4 py-3 text-slate-900">{row.item?.item_name ?? 'Unknown Item'}</td>
                                        <td className="px-4 py-3 text-slate-700">{row.item?.category?.category_name ?? 'Uncategorized'}</td>
                                        <td className="px-4 py-3 text-slate-700">{Number(row.quantity_requested ?? 0).toFixed(2)}</td>
                                        <td className="px-4 py-3 text-slate-700">{row.item?.default_unit ?? '-'}</td>
                                        <td className="px-4 py-3 text-slate-600">{row.remarks ?? '-'}</td>
                                    </tr>
                                ))}
                                {(purchaseRequest.items ?? []).length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                                            No item lines were found for this purchase request.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </section>

                    <WorkflowHistoryTimeline
                        statusHistory={statusHistory}
                        approvalHistory={approvalHistory}
                        title="Purchase Request Review & Audit History"
                        subtitle="Chronological audit trail of requisitions, BAC validations, reviews, and decisions."
                    />

                    {canReviewRequest ? (
                        <form onSubmit={processRequest} className="space-y-3 rounded-lg border bg-white p-6">
                            <label className="mb-1 block text-sm text-gray-700">Review Decision Remarks</label>
                            <textarea
                                value={processForm.data.remarks}
                                onChange={(event) => processForm.setData('remarks', event.target.value)}
                                placeholder="Provide remarks. Required for Return for Revision and Reject."
                                className="w-full rounded border-gray-300 text-sm"
                                rows={3}
                            />
                            {processForm.errors.remarks ? <p className="text-xs text-rose-600">{processForm.errors.remarks}</p> : null}
                            <div className="flex flex-wrap gap-2">
                                <Link
                                    href={resolvedBackLink.href}
                                    className="rounded border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                                >
                                    {resolvedBackLink.label}
                                </Link>
                                {purchaseRequest.status === 'OPEN' && (
                                    <button type="button" onClick={() => submitReviewAction('process')} className="rounded bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                                        Move to Processing
                                    </button>
                                )}
                                <button type="button" onClick={() => submitReviewAction('return')} disabled={reasonIsBlank || processForm.processing} className="rounded bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60">
                                    Return for Revision
                                </button>
                                <button type="button" onClick={() => submitReviewAction('reject')} disabled={reasonIsBlank || processForm.processing} className="rounded bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60">
                                    Reject Request
                                </button>
                            </div>
                        </form>
                    ) : (
                        <div className="flex flex-wrap gap-3">
                            <Link
                                href={resolvedBackLink.href}
                                className="rounded border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                            >
                                {resolvedBackLink.label}
                            </Link>
                        </div>
                    )}
                </div>
            </div>
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
        OPEN: `${base} bg-amber-100 text-amber-700`,
        PROCESSING: `${base} bg-blue-100 text-blue-700`,
        COMPLETED: `${base} bg-emerald-100 text-emerald-700`,
        RETURNED_FOR_REVISION: `${base} bg-violet-100 text-violet-700`,
        APPROVED: `${base} bg-emerald-100 text-emerald-700`,
        REJECTED: `${base} bg-rose-100 text-rose-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function formatDate(value?: string | null) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}

function formatRequestRemarks(remarks?: string | null) {
    const source = (remarks ?? '').trim();

    if (source === '') {
        return 'No remarks provided.';
    }

    const lowStockMarkerRegex = /^LOW_STOCK_SIGNAL:inventory:\d+\s*\|\s*/i;
    const cleaned = source.replace(lowStockMarkerRegex, '').trim();

    if (cleaned.toLowerCase().startsWith('auto-generated from low-stock signal')) {
        return 'Auto-generated due to low stock signal. Procurement review is required before processing.';
    }

    return cleaned;
}
