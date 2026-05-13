import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type PurchaseRequest = {
    purchase_request_id: number;
    supply_request_id: number | null;
    status: string;
    remarks: string | null;
    created_at?: string | null;
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

type Props = {
    purchaseRequest: PurchaseRequest;
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
};

export default function PurchaseRequestsShow({ purchaseRequest, reviewMode = false, backFilters, permissions, backLink }: Props) {
    const processForm = useForm({ action: 'process', remarks: '' });
    const totalQuantity = (purchaseRequest.items ?? []).reduce((sum, row) => sum + Number(row.quantity_requested ?? 0), 0);
    const normalizedRequestRemarks = formatRequestRemarks(purchaseRequest.remarks);
    const reasonIsBlank = processForm.data.remarks.trim() === '';
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
        processForm.setData('action', action);
        processForm.post(route('procurement.purchase-requests.process', purchaseRequest.purchase_request_id), {
            preserveScroll: true,
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
                                <p className="text-xs uppercase tracking-[0.15em] text-slate-500">{reviewMode ? 'Request Review' : 'Request Information'}</p>
                                <h3 className="mt-1 text-lg font-semibold text-slate-900">
                                    {purchaseRequest.supply_request?.request_code || `PR-${String(purchaseRequest.purchase_request_id).padStart(6, '0')}`}
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
                    </section>

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

                    <div className="flex flex-wrap gap-3">
                        <Link
                            href={resolvedBackLink.href}
                            className="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            {resolvedBackLink.label}
                        </Link>
                    </div>

                    {(purchaseRequest.status === 'OPEN' || purchaseRequest.status === 'PROCESSING' || purchaseRequest.status === 'RETURNED_FOR_REVISION') && permissions?.process && reviewMode ? (
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
                                {(purchaseRequest.status === 'OPEN' || purchaseRequest.status === 'RETURNED_FOR_REVISION') && (
                                    <button type="button" onClick={() => submitReviewAction('process')} className="rounded bg-amber-600 px-4 py-2 text-sm text-white">
                                        Move to Processing
                                    </button>
                                )}
                                <button type="button" onClick={() => submitReviewAction('return')} disabled={reasonIsBlank || processForm.processing} className="rounded bg-violet-600 px-4 py-2 text-sm text-white disabled:cursor-not-allowed disabled:opacity-60">
                                    Return for Revision
                                </button>
                                <button type="button" onClick={() => submitReviewAction('reject')} disabled={reasonIsBlank || processForm.processing} className="rounded bg-rose-600 px-4 py-2 text-sm text-white disabled:cursor-not-allowed disabled:opacity-60">
                                    Reject Request
                                </button>
                            </div>
                        </form>
                    ) : null}
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





