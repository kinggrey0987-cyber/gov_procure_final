import AttachmentsPanel from '@/Components/AttachmentsPanel';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

type HistoryEntry = {
    id: number;
    old_status?: string | null;
    new_status?: string;
    status?: string;
    remarks?: string | null;
    changed_at?: string | null;
    approved_at?: string | null;
    changed_by?: { id: number; name: string } | null;
    approver?: { id: number; name: string } | null;
};

type Attachment = {
    document_id: number;
    title: string;
    file_name: string;
    uploaded_at?: string | null;
    uploader_name?: string | null;
};

type Props = {
    ppmp: {
        ppmp_id: number;
        reference_code: string;
        office?: { id: number; name: string } | null;
        preparer?: { id: number; name: string } | null;
        approver?: { id: number; name: string } | null;
        status: string;
        fiscal_year: number;
        date_prepared?: string | null;
        submitted_at?: string | null;
        date_approved?: string | null;
        total_estimated_budget: number;
        remarks?: string | null;
        items_count: number;
    };
    categorySummary: Array<{
        item_category_id: number;
        items_count: number;
        estimated_budget_total: number;
    }>;
    attachments: Attachment[];
    attachmentPermissions: {
        upload: boolean;
        delete: boolean;
    };
    statusHistory: HistoryEntry[];
    approvalHistory: HistoryEntry[];
    canSignoff: boolean;
};

export default function ExecutiveApprovalShow({
    ppmp,
    categorySummary,
    attachments,
    attachmentPermissions,
    statusHistory,
    approvalHistory,
    canSignoff,
}: Props) {
    const form = useForm({ status: '', remarks: ppmp.remarks ?? '' });

    const submitDecision = (status: 'APPROVED' | 'REJECTED' | 'RETURNED_FOR_REVISION') => {
        form.setData('status', status);
        form.put(route('executive.ppmp-approvals.update-status', ppmp.ppmp_id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('status');
            },
        });
    };

    return (
        <AuthenticatedLayout header="Executive Approval Review">
            <Head title="Executive Approval Review" />

            <div className="space-y-6 py-8">
                <section className="rounded-2xl border border-red-200 bg-white p-6 shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-700">Executive PPMP Review</p>
                            <h3 className="mt-1 text-2xl font-semibold text-slate-900">{ppmp.reference_code}</h3>
                            <p className="mt-1 text-sm text-slate-600">Final signoff workspace for executive approval authority.</p>
                        </div>
                        <span className={statusBadgeClass(ppmp.status)}>{ppmp.status}</span>
                    </div>

                    <div className="mt-4 grid gap-3 text-sm text-slate-700 md:grid-cols-2 lg:grid-cols-3">
                        <p><span className="font-semibold">Office:</span> {ppmp.office?.name ?? '-'}</p>
                        <p><span className="font-semibold">Prepared By:</span> {ppmp.preparer?.name ?? '-'}</p>
                        <p><span className="font-semibold">Current Approver:</span> {ppmp.approver?.name ?? '-'}</p>
                        <p><span className="font-semibold">Fiscal Year:</span> {ppmp.fiscal_year}</p>
                        <p><span className="font-semibold">Date Prepared:</span> {formatDate(ppmp.date_prepared)}</p>
                        <p><span className="font-semibold">Submission Date:</span> {formatDate(ppmp.submitted_at)}</p>
                        <p><span className="font-semibold">Approval Date:</span> {formatDate(ppmp.date_approved)}</p>
                        <p><span className="font-semibold">Items:</span> {ppmp.items_count}</p>
                        <p><span className="font-semibold">Total Estimated Budget:</span> {formatCurrency(ppmp.total_estimated_budget)}</p>
                    </div>

                    <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        <p className="font-semibold text-slate-800">Remarks</p>
                        <p className="mt-1">{ppmp.remarks ?? 'No remarks provided.'}</p>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                        <Link href={route('executive.ppmp-approvals.index')} className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Back to Executive Approvals
                        </Link>
                        <Link href="#history" className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            View History
                        </Link>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="text-lg font-semibold text-slate-900">Category Budget Summary</h3>
                    <p className="mt-1 text-sm text-slate-600">Aggregated PPMP items by category for executive signoff reading.</p>

                    <div className="mt-4 overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead>
                                <tr className="border-b text-xs uppercase tracking-wide text-slate-500">
                                    <th className="py-2">Category ID</th>
                                    <th className="py-2">Items</th>
                                    <th className="py-2">Budget Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {categorySummary.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="py-3 text-slate-500">No category totals available.</td>
                                    </tr>
                                )}
                                {categorySummary.map((row) => (
                                    <tr key={row.item_category_id} className="border-b">
                                        <td className="py-2">{row.item_category_id}</td>
                                        <td className="py-2">{row.items_count}</td>
                                        <td className="py-2">{formatCurrency(row.estimated_budget_total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                {canSignoff && ppmp.status === 'SUBMITTED' && (
                    <section className="rounded-2xl border border-red-200 bg-white p-6 shadow-sm">
                        <h3 className="text-lg font-semibold text-slate-900">Executive Decision</h3>
                        <p className="mt-1 text-sm text-slate-600">Record the final signoff action with optional executive remarks.</p>

                        <div className="mt-4">
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Decision Remarks</label>
                            <textarea
                                value={form.data.remarks}
                                onChange={(event) => form.setData('remarks', event.target.value)}
                                className="h-24 w-full rounded-lg border-slate-300 text-sm"
                                placeholder="Add executive notes for approval, rejection, or return decision"
                            />
                            {form.errors.remarks && <p className="mt-1 text-xs text-rose-700">{form.errors.remarks}</p>}
                        </div>

                        <div className="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => submitDecision('APPROVED')}
                                disabled={form.processing}
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-60"
                            >
                                Approve
                            </button>
                            <button
                                type="button"
                                onClick={() => submitDecision('RETURNED_FOR_REVISION')}
                                disabled={form.processing}
                                className="rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-700 disabled:opacity-60"
                            >
                                Return for Revision
                            </button>
                            <button
                                type="button"
                                onClick={() => submitDecision('REJECTED')}
                                disabled={form.processing}
                                className="rounded-lg bg-rose-700 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-800 disabled:opacity-60"
                            >
                                Reject
                            </button>
                        </div>
                    </section>
                )}

                <AttachmentsPanel
                    title="PPMP Attachments"
                    attachments={attachments}
                    uploadRoute={route('documents.store', { related_module: 'ppmp', related_reference_id: ppmp.ppmp_id })}
                    canUpload={attachmentPermissions.upload}
                    canDelete={attachmentPermissions.delete}
                    downloadRouteFor={(documentId: number) => route('documents.show', documentId)}
                    deleteRouteFor={(documentId: number) => route('documents.destroy', documentId)}
                />

                <section id="history" className="grid gap-6 xl:grid-cols-2">
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 className="text-lg font-semibold text-slate-900">Status Timeline</h3>
                        <div className="mt-4 space-y-3 text-sm">
                            {statusHistory.length === 0 && <p className="text-slate-500">No status timeline entries found.</p>}
                            {statusHistory.map((row) => (
                                <div key={row.id} className="rounded-lg border border-slate-200 p-3">
                                    <p className="font-semibold text-slate-900">{row.old_status ?? 'N/A'} to {row.new_status ?? 'N/A'}</p>
                                    <p className="text-slate-600">{row.remarks ?? '-'}</p>
                                    <p className="mt-1 text-xs text-slate-500">{formatDateTime(row.changed_at)} by {row.changed_by?.name ?? 'System'}</p>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 className="text-lg font-semibold text-slate-900">Approval Timeline</h3>
                        <div className="mt-4 space-y-3 text-sm">
                            {approvalHistory.length === 0 && <p className="text-slate-500">No approval timeline entries found.</p>}
                            {approvalHistory.map((row) => (
                                <div key={row.id} className="rounded-lg border border-slate-200 p-3">
                                    <p className="font-semibold text-slate-900">{row.status ?? 'N/A'}</p>
                                    <p className="text-slate-600">{row.remarks ?? '-'}</p>
                                    <p className="mt-1 text-xs text-slate-500">{formatDateTime(row.approved_at)} by {row.approver?.name ?? 'Pending'}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function statusBadgeClass(status: string): string {
    const base = 'inline-flex rounded-full px-3 py-1 text-xs font-semibold';
    if (status === 'SUBMITTED') return `${base} bg-amber-100 text-amber-800`;
    if (status === 'APPROVED') return `${base} bg-emerald-100 text-emerald-800`;
    if (status === 'RETURNED_FOR_REVISION') return `${base} bg-blue-100 text-blue-800`;
    if (status === 'REJECTED') return `${base} bg-rose-100 text-rose-800`;
    return `${base} bg-slate-100 text-slate-800`;
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 2,
    }).format(value ?? 0);
}

function formatDate(value?: string | null): string {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '-';
    return parsed.toLocaleDateString();
}

function formatDateTime(value?: string | null): string {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '-';
    return parsed.toLocaleString();
}
