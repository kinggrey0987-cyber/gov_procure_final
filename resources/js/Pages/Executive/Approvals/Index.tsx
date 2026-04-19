import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { DonutChart, ExecutiveChartCard, HorizontalBarChart } from '@/Components/Executive/Charts';
import PpmpDecisionModal from '@/Components/PpmpDecisionModal';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type DecisionStatus = 'APPROVED' | 'REJECTED' | 'RETURNED_FOR_REVISION';

type OfficeOption = { id: number; name: string };

type ApprovalRow = {
    ppmp_id: number;
    office?: { id: number; name: string } | null;
    preparer?: { id: number; name: string } | null;
    fiscal_year: number;
    total_estimated_budget: number;
    status: string;
    date_prepared?: string | null;
    submitted_at?: string | null;
    items_count: number;
    can_signoff: boolean;
};

type Props = {
    records: { data: ApprovalRow[] };
    summary: {
        pending_ppmp_approvals: number;
        returned_for_revision: number;
        recently_approved: number;
        rejected: number;
    };
    filters: {
        office_id?: number | null;
        status?: string | null;
        from_date?: string | null;
        to_date?: string | null;
        search?: string | null;
    };
    filter_options: {
        offices: OfficeOption[];
        statuses: string[];
    };
    office_breakdown: Array<{ office_id: number; office_name: string; total: number }>;
};

export default function ExecutiveApprovalsIndex({ records, summary, filters, filter_options, office_breakdown }: Props) {
    const { isRefreshing, lastRefreshedAt } = useSmartRefresh({
        only: ['records', 'summary', 'filters', 'filter_options'],
        refreshOnFocus: true,
        refreshOnMount: true,
    });

    const [form, setForm] = useState({
        office_id: filters.office_id ? String(filters.office_id) : '',
        status: filters.status ?? '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
        search: filters.search ?? '',
    });
    const [decisionTarget, setDecisionTarget] = useState<{ ppmpId: number; status: DecisionStatus } | null>(null);
    const decisionForm = useForm({ status: '', remarks: '' });

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('executive.ppmp-approvals.index'), form, { preserveState: true, replace: true });
    };

    const requestDecisionConfirmation = (ppmpId: number, status: DecisionStatus) => {
        setDecisionTarget({ ppmpId, status });
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

        const { ppmpId, status } = decisionTarget;
        decisionForm.setData('status', status);

        decisionForm.put(route('executive.ppmp-approvals.update-status', ppmpId), {
            preserveScroll: true,
            onSuccess: () => {
                setDecisionTarget(null);
                decisionForm.reset('status', 'remarks');
            },
        });
    };

    const decisionMeta: Record<DecisionStatus, { title: string; message: string; confirmLabel: string; confirmClass: string; requiresRemarks: boolean; remarksLabel?: string; remarksPlaceholder?: string }> = {
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

    const statusMix = [
        { label: 'Pending', value: summary.pending_ppmp_approvals },
        { label: 'Returned', value: summary.returned_for_revision },
        { label: 'Approved (30d)', value: summary.recently_approved },
        { label: 'Rejected', value: summary.rejected },
    ];

    return (
        <AuthenticatedLayout header="Executive Approvals">
            <Head title="Executive Approvals" />

            <div className="space-y-6 py-8">
                <section className="rounded-2xl border border-red-200 bg-white p-5 shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-700">Executive Approval Desk</p>
                    <h3 className="mt-1 text-2xl font-semibold text-slate-900">PPMP Final Signoff</h3>
                    <p className="mt-2 text-xs text-slate-500">
                        {isRefreshing
                            ? 'Refreshing approvals queue...'
                            : lastRefreshedAt
                            ? `Updated ${lastRefreshedAt.toLocaleTimeString()}`
                            : 'Auto-refresh when this tab regains focus'}
                    </p>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard label="Pending PPMP Approvals" value={summary.pending_ppmp_approvals} tone="amber" />
                    <SummaryCard label="Returned for Revision" value={summary.returned_for_revision} tone="blue" />
                    <SummaryCard label="Recently Approved" value={summary.recently_approved} tone="emerald" />
                    <SummaryCard label="Rejected" value={summary.rejected} tone="rose" />
                </div>

                <section className="rounded-2xl border border-red-200 bg-white p-5 shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                    <form onSubmit={onSubmit} className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-0 flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <select
                            className="h-10 rounded-lg border-slate-300 text-sm"
                            value={form.office_id}
                            onChange={(event) => setForm((current) => ({ ...current, office_id: event.target.value }))}
                        >
                            <option value="">All offices</option>
                            {filter_options.offices.map((office) => (
                                <option key={office.id} value={office.id}>
                                    {office.name}
                                </option>
                            ))}
                        </select>

                        <select
                            className="h-10 rounded-lg border-slate-300 text-sm"
                            value={form.status}
                            onChange={(event) => setForm((current) => ({ ...current, status: event.target.value }))}
                        >
                            <option value="">All statuses</option>
                            {filter_options.statuses.map((status) => (
                                <option key={status} value={status}>
                                    {status}
                                </option>
                            ))}
                        </select>

                        <input
                            type="date"
                            className="h-10 rounded-lg border-slate-300 text-sm"
                            value={form.from_date}
                            onChange={(event) => setForm((current) => ({ ...current, from_date: event.target.value }))}
                        />

                        <input
                            type="date"
                            className="h-10 rounded-lg border-slate-300 text-sm"
                            value={form.to_date}
                            onChange={(event) => setForm((current) => ({ ...current, to_date: event.target.value }))}
                        />

                        <input
                            type="text"
                            className="h-10 rounded-lg border-slate-300 text-sm"
                            placeholder="Search PPMP code, office, preparer"
                            value={form.search}
                            onChange={(event) => setForm((current) => ({ ...current, search: event.target.value }))}
                        />
                        </div>

                        <div className="ml-auto flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => router.get(route('executive.ppmp-approvals.index'))}
                                className="h-10 rounded-lg border border-red-300 px-4 text-sm font-semibold text-red-700 transition hover:border-red-500 hover:bg-red-50"
                            >
                                Reset
                            </button>
                            <button type="submit" className="h-10 rounded-lg bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800">
                                Apply
                            </button>
                        </div>
                    </form>
                </section>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-5">
                    <ExecutiveChartCard title="Approval Status Mix" subtitle="Current decision state" tone="default">
                        <DonutChart
                            data={statusMix}
                            centerLabel="Queue"
                            emptyText="No approval status metrics are available."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-7">
                    <ExecutiveChartCard title="Office Queue Distribution" subtitle="Workflow records by office" tone="neutral">
                        <HorizontalBarChart
                            data={office_breakdown.map((row) => ({ label: row.office_name, value: row.total }))}
                            emptyText="No office-level queue data is available."
                        />
                    </ExecutiveChartCard>
                    </div>
                </div>

                <section className="space-y-3">
                    {records.data.length === 0 && (
                        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-600">
                            No PPMP records are currently in the executive approval queue for the selected filters.
                        </div>
                    )}

                    {records.data.map((row) => (
                        <article key={row.ppmp_id} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">PPMP Reference</p>
                                    <p className="text-lg font-semibold text-slate-900">PPMP-{row.ppmp_id}</p>
                                    <p className="text-sm text-slate-600">{row.office?.name ?? 'Unassigned Office'} | Prepared by {row.preparer?.name ?? 'Unknown'}</p>
                                </div>
                                <span className={statusBadgeClass(row.status)}>{row.status}</span>
                            </div>

                            <div className="mt-4 grid gap-2 text-sm text-slate-700 md:grid-cols-4">
                                <p>
                                    <span className="font-semibold">Submission Date:</span> {formatDate(row.submitted_at)}
                                </p>
                                <p>
                                    <span className="font-semibold">Date Prepared:</span> {formatDate(row.date_prepared)}
                                </p>
                                <p>
                                    <span className="font-semibold">Items:</span> {row.items_count}
                                </p>
                                <p>
                                    <span className="font-semibold">Estimated Budget:</span> {formatCurrency(row.total_estimated_budget)}
                                </p>
                            </div>

                            <div className="mt-4 flex flex-wrap items-center gap-2">
                                <Link
                                    href={route('executive.ppmp-approvals.show', row.ppmp_id)}
                                    className="rounded-lg bg-red-700 px-3 py-2 text-xs font-semibold text-white hover:bg-red-800"
                                >
                                    View Summary
                                </Link>

                                {row.can_signoff && (
                                    <>
                                        <button
                                            type="button"
                                            onClick={() => requestDecisionConfirmation(row.ppmp_id, 'APPROVED')}
                                            className="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => requestDecisionConfirmation(row.ppmp_id, 'RETURNED_FOR_REVISION')}
                                            className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                                        >
                                            Return for Revision
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => requestDecisionConfirmation(row.ppmp_id, 'REJECTED')}
                                            className="rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                        >
                                            Reject
                                        </button>
                                    </>
                                )}

                                <Link
                                    href={`${route('executive.ppmp-approvals.show', row.ppmp_id)}#history`}
                                    className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    View History
                                </Link>
                            </div>
                        </article>
                    ))}
                </section>
            </div>

            <PpmpDecisionModal
                show={decisionTarget !== null}
                title={activeDecision?.title ?? 'Confirm Executive Action'}
                message={activeDecision?.message ?? 'Are you sure you want to continue with this executive action?'}
                confirmLabel={activeDecision?.confirmLabel ?? 'Confirm'}
                confirmToneClass={activeDecision?.confirmClass ?? 'bg-slate-700 hover:bg-slate-800 text-white'}
                targetLabel={decisionTarget ? `Target Record: PPMP-${decisionTarget.ppmpId}` : undefined}
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

function SummaryCard({ label, value, tone }: { label: string; value: number; tone: 'amber' | 'blue' | 'emerald' | 'rose' }) {
    const toneClass: Record<typeof tone, string> = {
        amber: 'border-amber-300 bg-amber-50 text-amber-900',
        blue: 'border-blue-300 bg-blue-50 text-blue-900',
        emerald: 'border-emerald-300 bg-emerald-50 text-emerald-900',
        rose: 'border-rose-300 bg-rose-50 text-rose-900',
    };

    return (
        <div className={`rounded-2xl border p-5 shadow-sm ${toneClass[tone]}`}>
            <p className="text-sm font-semibold uppercase tracking-wide">{label}</p>
            <p className="mt-2 text-3xl font-bold">{value}</p>
        </div>
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
