import PpmpDecisionModal from '@/Components/PpmpDecisionModal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { getFundSourceLabel } from '@/constants/fundSources';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Props = {
    ppmp: {
        ppmp_id: number;
        reference_code: string;
        office?: { id: number; name: string } | null;
        preparer?: { id: number; name: string } | null;
        approver?: { id: number; name: string } | null;
        status: string;
        fiscal_year: number;
        fund_source: string;
        date_prepared?: string | null;
        submitted_at?: string | null;
        date_approved?: string | null;
        total_estimated_budget: number;
        remarks?: string | null;
        items_count: number;
        items: Array<{
            ppmp_item_id: number;
            item_name: string;
            category_name: string;
            quantity: number;
            unit_cost: number;
            estimated_budget: number;
            procurement_schedule?: string | null;
            remarks?: string | null;
        }>;
    };
    categorySummary: Array<{
        item_category_id: number;
        category_name: string;
        items_count: number;
        estimated_budget_total: number;
    }>;
    canSignoff: boolean;
};

export default function ExecutiveApprovalShow({
    ppmp,
    categorySummary,
    canSignoff,
}: Props) {
    const [decisionTarget, setDecisionTarget] = useState<{ status: 'APPROVED' | 'REJECTED' | 'RETURNED_FOR_REVISION' } | null>(null);
    const decisionForm = useForm({ status: '', remarks: '' });

    const openDecisionModal = (status: 'APPROVED' | 'REJECTED' | 'RETURNED_FOR_REVISION') => {
        setDecisionTarget({ status });
        decisionForm.clearErrors();
        decisionForm.setData('status', status);
        decisionForm.setData('remarks', '');
    };

    const closeDecisionModal = () => {
        setDecisionTarget(null);
        decisionForm.clearErrors();
        decisionForm.reset('status', 'remarks');
    };

    const confirmDecision = () => {
        if (!decisionTarget) {
            return;
        }

        decisionForm.setData('status', decisionTarget.status);

        decisionForm.put(route('executive.ppmp-approvals.update-status', ppmp.ppmp_id), {
            preserveScroll: true,
            onSuccess: () => {
                setDecisionTarget(null);
                decisionForm.reset('status', 'remarks');
            },
        });
    };

    const decisionMeta: Record<'APPROVED' | 'REJECTED' | 'RETURNED_FOR_REVISION', {
        title: string;
        message: string;
        confirmLabel: string;
        confirmClass: string;
        requiresRemarks: boolean;
        remarksLabel?: string;
        remarksPlaceholder?: string;
    }> = {
        APPROVED: {
            title: 'Approve PPMP',
            message: 'Are you sure you want to approve this PPMP?',
            confirmLabel: 'Approve PPMP',
            confirmClass: 'bg-emerald-700 hover:bg-emerald-800 text-white',
            requiresRemarks: false,
        },
        RETURNED_FOR_REVISION: {
            title: 'Return PPMP for Revision',
            message: 'Are you sure you want to return this PPMP for revision?',
            confirmLabel: 'Return for Revision',
            confirmClass: 'bg-amber-600 hover:bg-amber-700 text-white',
            requiresRemarks: true,
            remarksLabel: 'Revision Remarks',
            remarksPlaceholder: 'Please explain what the office needs to revise.',
        },
        REJECTED: {
            title: 'Reject PPMP',
            message: 'Are you sure you want to reject this PPMP?',
            confirmLabel: 'Reject PPMP',
            confirmClass: 'bg-rose-700 hover:bg-rose-800 text-white',
            requiresRemarks: true,
            remarksLabel: 'Rejection Remarks',
            remarksPlaceholder: 'Please provide the reason for rejection.',
        },
    };

    const activeDecision = decisionTarget ? decisionMeta[decisionTarget.status] : null;

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
                        <span className={statusBadgeClass(ppmp.status)}>{displayPpmpStatus(ppmp.status)}</span>
                    </div>

                    <div className="mt-4 grid gap-3 text-sm text-slate-700 md:grid-cols-2 lg:grid-cols-3">
                        <p><span className="font-semibold">Office:</span> {ppmp.office?.name ?? '-'}</p>
                        <p><span className="font-semibold">Prepared By:</span> {ppmp.preparer?.name ?? '-'}</p>
                        {ppmp.status === 'APPROVED' || ppmp.status === 'CLOSED' ? (
                            <>
                                <p><span className="font-semibold">Approved By:</span> {ppmp.approver?.name ?? '-'}</p>
                                <p><span className="font-semibold">Approval Date:</span> {formatDate(ppmp.date_approved)}</p>
                            </>
                        ) : ppmp.status === 'REJECTED' ? (
                            <p><span className="font-semibold">Rejected By:</span> {ppmp.approver?.name ?? '-'}</p>
                        ) : ppmp.status === 'RETURNED_FOR_REVISION' ? (
                            <p><span className="font-semibold">Returned By:</span> {ppmp.approver?.name ?? '-'}</p>
                        ) : (
                            <p><span className="font-semibold">Review Authority:</span> {ppmp.approver?.name ?? 'Executive Office'}</p>
                        )}
                        <p><span className="font-semibold">Fiscal Year:</span> {ppmp.fiscal_year}</p>
                        <p><span className="font-semibold">Fund Source:</span> {getFundSourceLabel(ppmp.fund_source) || '-'}</p>
                        <p><span className="font-semibold">Date Prepared:</span> {formatDate(ppmp.date_prepared)}</p>
                        <p><span className="font-semibold">Submission Date:</span> {formatDate(ppmp.submitted_at)}</p>
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
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="text-lg font-semibold text-slate-900">Category Budget Summary</h3>
                    <p className="mt-1 text-sm text-slate-600">Aggregated PPMP items by category name for executive signoff reading.</p>

                    <div className="mt-4 overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead>
                                <tr className="border-b text-xs uppercase tracking-wide text-slate-500">
                                    <th className="py-2">Category</th>
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
                                        <td className="py-2">{row.category_name}</td>
                                        <td className="py-2">{row.items_count}</td>
                                        <td className="py-2">{formatCurrency(row.estimated_budget_total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="text-lg font-semibold text-slate-900">Planned Item Details</h3>
                    <p className="mt-1 text-sm text-slate-600">Readable item names, specifications, and categories included for review clarity.</p>

                    <div className="mt-4 overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead>
                                <tr className="border-b text-xs uppercase tracking-wide text-slate-500">
                                    <th className="py-2 pr-4">Item</th>
                                    <th className="py-2 pr-4">Specification</th>
                                    <th className="py-2 pr-4">Category</th>
                                    <th className="py-2 pr-4">Qty</th>
                                    <th className="py-2 pr-4">Unit Cost</th>
                                    <th className="py-2 pr-4">Subtotal</th>
                                    <th className="py-2 pr-4">Schedule</th>
                                </tr>
                            </thead>
                            <tbody>
                                {ppmp.items.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="py-3 text-slate-500">No planned items available.</td>
                                    </tr>
                                )}
                                {ppmp.items.map((item) => (
                                    <tr key={item.ppmp_item_id} className="border-b">
                                        <td className="py-2 pr-4 font-medium text-slate-800">{item.item_name}</td>
                                        <td className="py-2 pr-4 text-slate-700">{item.remarks?.trim() || '-'}</td>
                                        <td className="py-2 pr-4 text-slate-700">{item.category_name}</td>
                                        <td className="py-2 pr-4 text-slate-700">{item.quantity}</td>
                                        <td className="py-2 pr-4 text-slate-700">{formatCurrency(item.unit_cost)}</td>
                                        <td className="py-2 pr-4 text-slate-700">{formatCurrency(item.estimated_budget)}</td>
                                        <td className="py-2 pr-4 text-slate-700">{item.procurement_schedule ?? '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                {canSignoff && ppmp.status === 'SUBMITTED' && (
                    <section className="rounded-2xl border border-red-200 bg-white p-6 shadow-sm">
                        <h3 className="text-lg font-semibold text-slate-900">Executive Decision</h3>
                        <p className="mt-1 text-sm text-slate-600">Record the final signoff action with optional approval notes or required revision/rejection remarks.</p>

                        <div className="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => openDecisionModal('APPROVED')}
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-60"
                            >
                                Approve
                            </button>
                            <button
                                type="button"
                                onClick={() => openDecisionModal('RETURNED_FOR_REVISION')}
                                className="rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-700 disabled:opacity-60"
                            >
                                Return for Revision
                            </button>
                            <button
                                type="button"
                                onClick={() => openDecisionModal('REJECTED')}
                                className="rounded-lg bg-rose-700 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-800 disabled:opacity-60"
                            >
                                Reject
                            </button>
                        </div>
                    </section>
                )}

            </div>

            <PpmpDecisionModal
                show={decisionTarget !== null}
                title={activeDecision?.title ?? 'Confirm Executive Action'}
                message={activeDecision?.message ?? 'Are you sure you want to continue with this executive action?'}
                confirmLabel={activeDecision?.confirmLabel ?? 'Confirm'}
                confirmToneClass={activeDecision?.confirmClass ?? 'bg-slate-700 hover:bg-slate-800 text-white'}
                targetLabel={decisionTarget ? `Target Record: ${ppmp.reference_code}` : undefined}
                remarks={decisionForm.data.remarks}
                remarksRequired={activeDecision?.requiresRemarks ?? false}
                remarksLabel={activeDecision?.remarksLabel}
                remarksPlaceholder={activeDecision?.remarksPlaceholder}
                remarksError={decisionForm.errors.remarks}
                processing={decisionForm.processing}
                onRemarksChange={(value) => decisionForm.setData('remarks', value)}
                onCancel={closeDecisionModal}
                onConfirm={confirmDecision}
            />
        </AuthenticatedLayout>
    );
}

function statusBadgeClass(status: string): string {
    const base = 'inline-flex rounded-full px-3 py-1 text-xs font-semibold';
    if (status === 'SUBMITTED') return `${base} bg-amber-100 text-amber-800`;
    if (status === 'APPROVED') return `${base} bg-emerald-100 text-emerald-800`;
    if (status === 'RETURNED_FOR_REVISION') return `${base} bg-blue-100 text-blue-800`;
    if (status === 'REJECTED') return `${base} bg-rose-100 text-rose-800`;
    if (status === 'CLOSED') return `${base} bg-slate-200 text-slate-700 font-medium`;
    return `${base} bg-slate-100 text-slate-800`;
}

function displayPpmpStatus(status: string): string {
    const map: Record<string, string> = {
        SUBMITTED: 'Submitted',
        APPROVED: 'Approved',
        RETURNED_FOR_REVISION: 'Returned for Revision',
        REJECTED: 'Rejected',
        CLOSED: 'Closed',
    };

    return map[status] ?? status.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
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

