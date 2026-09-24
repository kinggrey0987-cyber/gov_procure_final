import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { DonutChart, ExecutiveChartCard, HorizontalBarChart } from '@/Components/Executive/Charts';
import { GovDocumentPage, GovDocumentMetadata } from '@/Components/Executive/GovDocumentLayout';
import MonthFilterSelect from '@/Components/MonthFilterSelect';
import PaginationNav from '@/Components/PaginationNav';
import PpmpDecisionModal from '@/Components/PpmpDecisionModal';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { downloadSectionAsPdfById } from '@/lib/printSection';
import { PageProps } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

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
    records: {
        data: ApprovalRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    recordsExport: ApprovalRow[];
    summary: {
        pending_ppmp_approvals: number;
        returned_for_revision: number;
        recently_approved: number;
        rejected: number;
    };
    filters: {
        office_id?: number | null;
        status?: string | null;
        year?: string | null;
        month?: string | null;
        from_date?: string | null;
        to_date?: string | null;
        search?: string | null;
    };
    filter_options: {
        offices: OfficeOption[];
        statuses: string[];
        years: number[];
    };
    office_breakdown: Array<{ office_id: number; office_name: string; total: number }>;
};

export default function ExecutiveApprovalsIndex({ records, recordsExport, summary, filters, filter_options, office_breakdown }: Props) {
    const printSectionId = 'executive-approvals-print-section';
    const exportSectionId = 'executive-approvals-export-section';
    const { isRefreshing, refreshNow } = useSmartRefresh({
        only: ['records', 'summary', 'filters', 'filter_options'],
        refreshOnFocus: false,
        refreshOnMount: false,
    });

    const [form, setForm] = useState({
        office_id: filters.office_id ? String(filters.office_id) : '',
        status: filters.status ?? '',
        year: filters.year ?? '',
        month: filters.month ?? '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
        search: filters.search ?? '',
    });
    const [decisionTarget, setDecisionTarget] = useState<{ ppmpId: number; status: DecisionStatus } | null>(null);
    const decisionForm = useForm({ status: '', remarks: '' });

    useEffect(() => {
        setForm({
            office_id: filters.office_id ? String(filters.office_id) : '',
            status: filters.status ?? '',
            year: filters.year ?? '',
            month: filters.month ?? '',
            from_date: filters.from_date ?? '',
            to_date: filters.to_date ?? '',
            search: filters.search ?? '',
        });
    }, [filters.from_date, filters.month, filters.office_id, filters.search, filters.status, filters.to_date, filters.year]);

    const updateFilters = (nextForm: typeof form) => {
        setForm(nextForm);
        router.get(route('executive.ppmp-approvals.index'), nextForm, {
            preserveState: true,
            replace: true,
            only: ['records', 'recordsExport', 'summary', 'filters', 'filter_options', 'office_breakdown'],
        });
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

    const exportCsv = () => {
        const lines = [
            ['metric', 'value'],
            ['pending_ppmp_approvals', String(summary.pending_ppmp_approvals)],
            ['returned_for_revision', String(summary.returned_for_revision)],
            ['recently_approved', String(summary.recently_approved)],
            ['rejected', String(summary.rejected)],
            [''],
            ['ppmp_id', 'office', 'preparer', 'fiscal_year', 'status', 'date_prepared', 'submitted_at', 'items_count', 'total_estimated_budget'],
            ...recordsExport.map((row) => [
                String(row.ppmp_id),
                row.office?.name ?? 'Unassigned Office',
                row.preparer?.name ?? 'Unknown',
                String(row.fiscal_year),
                row.status,
                row.date_prepared ?? '',
                row.submitted_at ?? '',
                String(row.items_count),
                String(row.total_estimated_budget),
            ]),
        ];

        const csv = lines.map((row) => row.map(csvValue).join(',')).join('\n');
        downloadFile(csv, 'executive-approvals.csv', 'text/csv;charset=utf-8;');
    };

    const { auth } = usePage<PageProps>().props;
    const recordsExportPages = useMemo(() => chunkRows(recordsExport.slice(0, 36), 12), [recordsExport]);
    const totalPdfPages = 1 + (recordsExportPages.length > 0 ? recordsExportPages.length : 1);

    const selectedOfficeName = useMemo(() => {
        if (!form.office_id) return 'All Offices';
        const match = filter_options.offices.find((o) => String(o.id) === String(form.office_id));
        return match ? match.name : `Office #${form.office_id}`;
    }, [filter_options.offices, form.office_id]);

    const filterScopeSummary = useMemo(() => {
        const parts: string[] = [];
        parts.push(`Office: ${selectedOfficeName}`);
        parts.push(`Fiscal Year: ${form.year || 'All Years'}`);
        if (form.status) parts.push(`Status: ${form.status}`);
        if (form.month) parts.push(`Month: ${form.month}`);
        if (form.from_date || form.to_date) parts.push(`Date: ${form.from_date || 'Start'} to ${form.to_date || 'Present'}`);
        if (form.search) parts.push(`Search: "${form.search}"`);
        return parts.join(' | ');
    }, [selectedOfficeName, form.year, form.status, form.month, form.from_date, form.to_date, form.search]);

    const baseMetadata: GovDocumentMetadata = {
        title: 'PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP) EXECUTIVE APPROVAL REPORT',
        formCode: 'FDP Form - PPMP Approval & Utilization',
        trackingCode: `EBM-PPMP-${form.year || new Date().getFullYear()}`,
        fiscalYear: form.year || new Date().getFullYear(),
        period: form.year ? `Fiscal Year ${form.year}` : `Fiscal Year ${new Date().getFullYear()}`,
        moduleScope: `Executive PPMP Signoff (${selectedOfficeName})`,
        filterScopeSummary,
        preparedByName: auth?.user?.name || 'Mayor Jeron',
        preparedByRole: auth?.user?.role_code || 'Municipal Mayor / Local Chief Executive',
        preparedAt: new Date().toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }),
    };

    const [isExportingPdf, setIsExportingPdf] = useState(false);

    const exportPdf = async () => {
        if (isExportingPdf) return;
        setIsExportingPdf(true);
        try {
            await downloadSectionAsPdfById(exportSectionId, {
                title: 'Executive Approvals Report',
                fileName: 'executive-ppmp-approvals.pdf',
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

    return (
        <AuthenticatedLayout
            header="PPMP Approvals"
            headerActions={
                <div className="print-hide flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => void refreshNow()}
                        disabled={isRefreshing}
                        className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 shadow-sm transition"
                    >
                        {isRefreshing ? 'Refreshing…' : 'Refresh'}
                    </button>
                    <button
                        type="button"
                        onClick={exportPdf}
                        disabled={isExportingPdf}
                        className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 shadow-sm transition"
                    >
                        <svg className="mr-1.5 h-3.5 w-3.5 text-red-600" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clipRule="evenodd" />
                        </svg>
                        {isExportingPdf ? 'Generating PDF...' : 'Export PDF'}
                    </button>
                    <button
                        type="button"
                        onClick={exportCsv}
                        className="inline-flex h-8 items-center rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 shadow-sm transition"
                    >
                        <svg className="mr-1.5 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clipRule="evenodd" />
                        </svg>
                        Export CSV
                    </button>
                </div>
            }
        >
            <Head title="PPMP Approvals" />

            <div id={printSectionId} className="space-y-3.5">
                {/* Slim Telemetry Strip */}
                <section className="rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    <div className="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-amber-500" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-amber-700">Pending Approvals</p>
                                <p className="text-lg font-extrabold text-amber-900 leading-tight">{summary.pending_ppmp_approvals}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-sky-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-sky-700">Returned for Revision</p>
                                <p className="text-lg font-extrabold text-sky-900 leading-tight">{summary.returned_for_revision}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Approved</p>
                                <p className="text-lg font-extrabold text-emerald-900 leading-tight">{summary.recently_approved}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-rose-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-rose-700">Rejected</p>
                                <p className="text-lg font-extrabold text-rose-900 leading-tight">{summary.rejected}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="grid min-w-0 flex-1 gap-2 grid-cols-1 sm:grid-cols-2 xl:grid-cols-5">
                            <select
                                className="h-8 rounded-lg border-slate-300 px-2 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                value={form.office_id}
                                onChange={(event) => updateFilters({ ...form, office_id: event.target.value })}
                            >
                                <option value="">All offices</option>
                                {filter_options.offices.map((office) => (
                                    <option key={office.id} value={office.id}>
                                        {office.name}
                                    </option>
                                ))}
                            </select>

                            <select
                                className="h-8 rounded-lg border-slate-300 px-2 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                value={form.status}
                                onChange={(event) => updateFilters({ ...form, status: event.target.value })}
                            >
                                <option value="">All statuses</option>
                                {filter_options.statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {displayPpmpStatus(status)}
                                    </option>
                                ))}
                            </select>

                            <MonthFilterSelect
                                className="h-8 rounded-lg border-slate-300 px-2 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                month={form.month}
                                year={form.year}
                                yearOptions={filter_options.years}
                                onMonthChange={(value) => updateFilters({ ...form, month: value, from_date: '', to_date: '' })}
                                onYearChange={(value) => updateFilters({ ...form, year: value })}
                            />

                            <input
                                type="text"
                                className="h-8 rounded-lg border-slate-300 px-2.5 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 xl:col-span-2"
                                placeholder="Search PPMP code, office, preparer"
                                value={form.search}
                                onChange={(event) => setForm((current) => ({ ...current, search: event.target.value }))}
                                onBlur={() => updateFilters(form)}
                            />
                        </div>

                        <div className="ml-auto flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => router.get(route('executive.ppmp-approvals.index'))}
                                className="h-8 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
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
                            palette="multi"
                            showPercentage={true}
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
                        <article key={row.ppmp_id} className="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">PPMP Reference</p>
                                    <p className="text-base font-semibold text-slate-900">PPMP-{row.ppmp_id}</p>
                                    <p className="text-xs text-slate-600">{row.office?.name ?? 'Unassigned Office'} | Prepared by {row.preparer?.name ?? 'Unknown'}</p>
                                </div>
                                <span className={statusBadgeClass(row.status)}>{displayPpmpStatus(row.status)}</span>
                            </div>

                            <div className="mt-3 grid gap-2 text-xs text-slate-700 md:grid-cols-4">
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

                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                <Link
                                    href={route('executive.ppmp-approvals.show', row.ppmp_id)}
                                    className="rounded-md bg-red-700 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-red-800"
                                >
                                    View Summary
                                </Link>

                                {row.can_signoff && (
                                    <>
                                        <button
                                            type="button"
                                            onClick={() => requestDecisionConfirmation(row.ppmp_id, 'APPROVED')}
                                            className="rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => requestDecisionConfirmation(row.ppmp_id, 'RETURNED_FOR_REVISION')}
                                            className="rounded-md border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                                        >
                                            Return for Revision
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => requestDecisionConfirmation(row.ppmp_id, 'REJECTED')}
                                            className="rounded-md border border-rose-300 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                        >
                                            Reject
                                        </button>
                                    </>
                                )}

                            </div>
                        </article>
                    ))}

                    {records.data.length > 0 ? <PaginationNav links={records.links} preserveState={false} /> : null}
                </section>

                <div id={exportSectionId} className="pdf-export-content hidden space-y-6 text-slate-900">
                    {/* Page 1: Summary, Decision Mix & Office Queue */}
                    <GovDocumentPage
                        metadata={baseMetadata}
                        pageNumber={1}
                        totalPages={totalPdfPages}
                        showLetterhead={true}
                        showSignatories={totalPdfPages === 1}
                        showAttestation={totalPdfPages === 1}
                    >
                        <div className="rounded-lg border border-slate-300 bg-slate-50/50 p-4">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-2 mb-3">
                                Executive PPMP Approval Queue Status
                            </h3>
                            <div className="grid grid-cols-4 gap-3">
                                <div className="rounded-md border border-amber-300 bg-amber-50/70 p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-amber-900">Pending Review</p>
                                    <p className="mt-1 text-2xl font-extrabold text-amber-800">{summary.pending_ppmp_approvals}</p>
                                </div>
                                <div className="rounded-md border border-blue-300 bg-blue-50/70 p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-blue-900">Returned Revision</p>
                                    <p className="mt-1 text-2xl font-extrabold text-blue-800">{summary.returned_for_revision}</p>
                                </div>
                                <div className="rounded-md border border-emerald-300 bg-emerald-50/70 p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-emerald-900">Approved</p>
                                    <p className="mt-1 text-2xl font-extrabold text-emerald-800">{summary.recently_approved}</p>
                                </div>
                                <div className="rounded-md border border-rose-300 bg-rose-50/70 p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-rose-900">Rejected</p>
                                    <p className="mt-1 text-2xl font-extrabold text-rose-800">{summary.rejected}</p>
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <section className="rounded-lg border border-slate-300 bg-white p-3.5">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2.5">
                                    Approval Decision State Mix
                                </h3>
                                <DonutChart data={statusMix} centerLabel="Queue" emptyText="No approval status metrics are available." />
                            </section>

                            <section className="rounded-lg border border-slate-300 bg-white p-3.5">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2.5">
                                    Office Queue Concentration
                                </h3>
                                <HorizontalBarChart
                                    data={office_breakdown.map((row) => ({ label: row.office_name, value: row.total }))}
                                    emptyText="No office-level queue data is available."
                                />
                            </section>
                        </div>
                    </GovDocumentPage>

                    {/* Continuation Pages: Detailed PPMP Records */}
                    {recordsExportPages.map((rows, pageIndex) => {
                        const pageNumber = 2 + pageIndex;
                        const isLast = pageNumber === totalPdfPages;

                        return (
                            <GovDocumentPage
                                key={`executive-approvals-export-${pageIndex + 1}`}
                                metadata={{
                                    ...baseMetadata,
                                    title: 'PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP) RECORD SCHEDULE',
                                    moduleScope: 'Itemized PPMP Records & Budget Allocations',
                                }}
                                pageNumber={pageNumber}
                                totalPages={totalPdfPages}
                                showLetterhead={true}
                                showSignatories={isLast}
                                showAttestation={isLast}
                            >
                                <div className="overflow-x-auto rounded-md border border-slate-300">
                                    <table className="min-w-full table-fixed divide-y divide-slate-300 text-[11px]">
                                        <thead className="bg-slate-100 text-slate-800 font-bold uppercase">
                                            <tr>
                                                <th className="w-[12%] px-2.5 py-2.5 text-left border-r border-slate-300 whitespace-nowrap">PPMP No.</th>
                                                <th className="w-[23%] px-2.5 py-2.5 text-left border-r border-slate-300">Office / Department</th>
                                                <th className="w-[14%] px-2.5 py-2.5 text-left border-r border-slate-300">Preparer</th>
                                                <th className="w-[7%] px-2 py-2.5 text-center border-r border-slate-300 whitespace-nowrap">Year</th>
                                                <th className="w-[14%] px-2.5 py-2.5 text-center border-r border-slate-300 whitespace-nowrap">Status</th>
                                                <th className="w-[11%] px-2.5 py-2.5 text-left border-r border-slate-300 whitespace-nowrap">Prepared</th>
                                                <th className="w-[7%] px-2 py-2.5 text-center border-r border-slate-300 whitespace-nowrap">Items</th>
                                                <th className="w-[12%] px-2.5 py-2.5 text-right whitespace-nowrap">Budget (PHP)</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 bg-white">
                                            {rows.map((row) => (
                                                <tr key={`approval-${row.ppmp_id}`}>
                                                    <td className="px-2.5 py-2.5 align-middle font-bold text-slate-900 border-r border-slate-200 whitespace-nowrap">PPMP-{row.ppmp_id}</td>
                                                    <td className="px-2.5 py-2.5 align-middle text-slate-700 border-r border-slate-200 leading-snug">{row.office?.name ?? 'Unassigned'}</td>
                                                    <td className="px-2.5 py-2.5 align-middle text-slate-700 border-r border-slate-200">{row.preparer?.name ?? 'Unknown'}</td>
                                                    <td className="px-2 py-2.5 align-middle text-center border-r border-slate-200">{row.fiscal_year}</td>
                                                    <td className="px-2.5 py-2.5 align-middle text-center border-r border-slate-200">
                                                        <span className={statusBadgeClass(row.status)}>{displayPpmpStatus(row.status)}</span>
                                                    </td>
                                                    <td className="px-2.5 py-2.5 align-middle text-slate-600 border-r border-slate-200 whitespace-nowrap">{formatDate(row.date_prepared)}</td>
                                                    <td className="px-2 py-2.5 align-middle text-center border-r border-slate-200 font-semibold">{row.items_count}</td>
                                                    <td className="px-2.5 py-2.5 align-middle text-right font-bold text-slate-900 whitespace-nowrap">{formatCurrency(row.total_estimated_budget)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </GovDocumentPage>
                        );
                    })}
                </div>
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

function ExportPageHeader({ title, subtitle }: { title: string; subtitle: string }) {
    return (
        <div className="border-b border-slate-200 px-3 py-2.5">
            <h3 className="text-lg font-semibold text-slate-800">{title}</h3>
            <p className="mt-1 text-xs text-slate-600">{subtitle}</p>
        </div>
    );
}

function chunkRows<T>(rows: T[], pageSize: number): T[][] {
    const pages: T[][] = [];
    for (let index = 0; index < rows.length; index += pageSize) {
        pages.push(rows.slice(index, index + pageSize));
    }
    return pages;
}

function csvValue(value: string): string {
    return `"${value.replaceAll('"', '""')}"`;
}

function downloadFile(content: string, fileName: string, mimeType: string): void {
    const blob = new Blob([content], { type: mimeType });
    const url = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = fileName;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.URL.revokeObjectURL(url);
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

function displayPpmpStatus(status: string): string {
    const map: Record<string, string> = {
        SUBMITTED: 'Submitted',
        APPROVED: 'Approved',
        RETURNED_FOR_REVISION: 'Returned for Revision',
        REJECTED: 'Rejected',
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
    if (!value) return 'N/A';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return String(value);
    return parsed.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}





