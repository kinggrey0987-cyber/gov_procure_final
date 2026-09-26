import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ExecutiveChartCard, HorizontalBarChart } from '@/Components/Executive/Charts';
import { GovDocumentPage, GovDocumentMetadata } from '@/Components/Executive/GovDocumentLayout';
import PaginationNav from '@/Components/PaginationNav';
import { downloadSectionAsPdfById } from '@/lib/printSection';
import { PageProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

type Props = {
    auditLogs: {
        data: Array<{
            id: number;
            log_domain: string;
            module_name: string;
            action_type: string;
            reference_type: string;
            reference_id: number;
            created_at: string;
            user?: { id: number; name: string } | null;
            office?: { id: number; name: string } | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    auditLogsExport: Array<{
        id: number;
        log_domain: string;
        module_name: string;
        action_type: string;
        reference_type: string;
        reference_id: number;
        created_at: string;
        user?: { id: number; name: string } | null;
        office?: { id: number; name: string } | null;
    }>;
    filters: {
        module_name?: string;
        action_type?: string;
        user_id?: number | string;
        from_date?: string;
        to_date?: string;
    };
    analytics: {
        module_summary: Array<{ label: string; total: number }>;
        action_summary: Array<{ label: string; total: number }>;
        daily_trend: Array<{ label: string; total: number }>;
    };
    filter_options: {
        modules: string[];
        actions: string[];
        users: Array<{ id: number; name: string }>;
    };
};

export default function BusinessAudit({ auditLogs, auditLogsExport, filters, analytics, filter_options }: Props) {
    const printSectionId = 'governance-logs-print-section';
    const exportSectionId = 'governance-logs-export-section';

    const selectedUser = filter_options.users.find((user) => String(user.id) === String(filters.user_id ?? ''));

    const [form, setForm] = useState({
        module_name: filters.module_name ?? '',
        action_type: filters.action_type ?? '',
        user_query: selectedUser ? `${selectedUser.name} (#${selectedUser.id})` : '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
    });

    useEffect(() => {
        const incomingSelectedUser = filter_options.users.find((user) => String(user.id) === String(filters.user_id ?? ''));

        setForm({
            module_name: filters.module_name ?? '',
            action_type: filters.action_type ?? '',
            user_query: incomingSelectedUser ? `${incomingSelectedUser.name} (#${incomingSelectedUser.id})` : '',
            from_date: filters.from_date ?? '',
            to_date: filters.to_date ?? '',
        });
    }, [filter_options.users, filters.action_type, filters.from_date, filters.module_name, filters.to_date, filters.user_id]);

    const activeFilters = useMemo(() => {
        const matchedUser = filter_options.users.find((user) => `${user.name} (#${user.id})` === form.user_query);

        return {
            module_name: form.module_name,
            action_type: form.action_type,
            user_id: matchedUser ? matchedUser.id : '',
            from_date: form.from_date,
            to_date: form.to_date,
        };
    }, [filter_options.users, form.action_type, form.from_date, form.module_name, form.to_date, form.user_query]);

    const activeFiltersKey = useMemo(() => serializeAuditFilters(activeFilters), [activeFilters]);
    const currentFiltersKey = useMemo(() => serializeAuditFilters({
        module_name: filters.module_name ?? '',
        action_type: filters.action_type ?? '',
        user_id: filters.user_id ?? '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
    }), [filters.action_type, filters.from_date, filters.module_name, filters.to_date, filters.user_id]);

    useEffect(() => {
        if (activeFiltersKey === currentFiltersKey) {
            return;
        }

        router.get(route('executive.audit.business'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['auditLogs', 'auditLogsExport', 'filters', 'analytics', 'filter_options'],
        });
    }, [activeFilters, activeFiltersKey, currentFiltersKey]);

    const moduleSummary = analytics.module_summary
        .map((row) => ({
            label: formatModuleLabel(row.label || 'N/A'),
            value: row.total,
        }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 6);

    const actionSummary = analytics.action_summary
        .map((row) => ({
            label: formatActionLabel(row.label || 'N/A'),
            value: row.total,
        }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 6);

    const totalEvents = analytics.daily_trend.reduce((sum, row) => sum + row.total, 0);
    const activeModules = moduleSummary.length;
    const activeActions = actionSummary.length;
    const auditLogsExportPages = useMemo(() => {
        const pageSize = 14;
        const targetLogs = auditLogsExport.slice(0, 42);
        const pages: typeof auditLogsExport[] = [];

        for (let index = 0; index < targetLogs.length; index += pageSize) {
            pages.push(targetLogs.slice(index, index + pageSize));
        }

        return pages;
    }, [auditLogsExport]);

    const exportCsv = () => {
        const rows = [
            ['timestamp', 'user', 'module', 'action', 'reference', 'office'],
            ...auditLogsExport.map((log) => [
                new Date(log.created_at).toISOString(),
                log.user?.name ?? 'System',
                log.module_name,
                log.action_type,
                `${log.reference_type}#${log.reference_id}`,
                log.office?.name ?? 'N/A',
            ]),
        ];

        const csv = rows.map((row) => row.map((column) => `"${String(column).replaceAll('"', '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = 'governance-logs.csv';
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.URL.revokeObjectURL(url);
    };

    const { auth } = usePage<PageProps>().props;
    const totalPdfPages = 1 + (auditLogsExportPages.length > 0 ? auditLogsExportPages.length : 1);

    const filterScopeSummary = useMemo(() => {
        const parts: string[] = [];
        if (form.module_name) parts.push(`Module: ${formatModuleLabel(form.module_name)}`);
        if (form.action_type) parts.push(`Action: ${formatActionLabel(form.action_type)}`);
        if (form.user_query) parts.push(`User: ${form.user_query}`);
        if (form.from_date || form.to_date) parts.push(`Date: ${form.from_date || 'Start'} to ${form.to_date || 'Present'}`);
        if (parts.length === 0) return 'All Governance Events & System Users';
        return parts.join(' | ');
    }, [form.module_name, form.action_type, form.user_query, form.from_date, form.to_date]);

    const baseMetadata: GovDocumentMetadata = {
        title: 'OFFICIAL GOVERNANCE & BUSINESS AUDIT TRAIL REPORT',
        formCode: 'FDP Form - Executive Governance & Audit Trail',
        trackingCode: `EBM-AUDIT-${new Date().getFullYear()}`,
        fiscalYear: new Date().getFullYear(),
        period: form.from_date || form.to_date ? `${form.from_date || 'Start'} to ${form.to_date || 'Present'}` : `Calendar Year ${new Date().getFullYear()}`,
        moduleScope: form.module_name ? `Governance Trail (${formatModuleLabel(form.module_name)})` : 'Executive Business Event Logging & Governance Trail',
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
                title: 'Governance Logs Report',
                fileName: 'governance-logs-report.pdf',
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

    const updateDateRange = (range: string) => {
        const dateRange = getDateRange(range);
        setForm((current) => ({
            ...current,
            from_date: dateRange.from_date,
            to_date: dateRange.to_date,
        }));
    };

    return (
        <AuthenticatedLayout
            header="Governance Logs"
            headerActions={
                <div className="print-hide flex items-center gap-2">
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
            <Head title="Governance Logs" />
            <div id={printSectionId} className="space-y-3.5">
                {/* Slim Telemetry Strip */}
                <section className="rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    <div className="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-red-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Events in Scope</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight">{totalEvents}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-indigo-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-indigo-700">Modules Involved</p>
                                <p className="text-lg font-extrabold text-indigo-900 leading-tight">{activeModules}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Action Types</p>
                                <p className="text-lg font-extrabold text-emerald-900 leading-tight">{activeActions}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="grid min-w-0 flex-1 gap-2 grid-cols-1 sm:grid-cols-2 xl:grid-cols-5">
                            <select className="h-8 rounded-lg border-slate-300 px-2 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500" value={form.module_name} onChange={(e) => setForm((v) => ({ ...v, module_name: e.target.value }))}>
                                <option value="">All modules</option>
                                {filter_options.modules.map((moduleName) => (
                                    <option key={moduleName} value={moduleName}>{formatModuleLabel(moduleName)}</option>
                                ))}
                            </select>

                            <select className="h-8 rounded-lg border-slate-300 px-2 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500" value={form.action_type} onChange={(e) => setForm((v) => ({ ...v, action_type: e.target.value }))}>
                                <option value="">All actions</option>
                                {filter_options.actions.map((actionName) => (
                                    <option key={actionName} value={actionName}>{formatActionLabel(actionName)}</option>
                                ))}
                            </select>

                            <input
                                list="governance-users"
                                className="h-8 rounded-lg border-slate-300 px-2.5 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                placeholder="Search user"
                                value={form.user_query}
                                onChange={(e) => setForm((v) => ({ ...v, user_query: e.target.value }))}
                            />
                            <datalist id="governance-users">
                                {filter_options.users.map((user) => (
                                    <option key={user.id} value={`${user.name} (#${user.id})`} />
                                ))}
                            </datalist>

                            <select
                                className="h-8 rounded-lg border-slate-300 px-2 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 xl:col-span-2"
                                value={resolveDateRangeKey(form.from_date, form.to_date)}
                                onChange={(event) => updateDateRange(event.target.value)}
                            >
                                <option value="all">All dates</option>
                                <option value="today">Today</option>
                                <option value="last_7_days">Last 7 days</option>
                                <option value="last_30_days">Last 30 days</option>
                                <option value="this_month">This month</option>
                                <option value="last_month">Last month</option>
                                <option value="custom" disabled>Selected date range</option>
                            </select>
                        </div>

                        <div className="ml-auto flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => router.get(route('executive.audit.business'))}
                                className="h-8 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </section>

                <div className="grid gap-4 xl:grid-cols-2">
                    <ExecutiveChartCard
                        title="Module Summary"
                        subtitle="Most active governance modules in current filters"
                        tone="neutral"
                    >
                        <HorizontalBarChart
                            data={moduleSummary}
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>

                    <ExecutiveChartCard
                        title="Action Summary"
                        subtitle="Most common governance actions in current filters"
                        tone="critical"
                    >
                        <HorizontalBarChart
                            data={actionSummary}
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                </div>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-lg font-semibold text-slate-900">Governance Activity Evidence</h3>
                    </div>

                    {auditLogs.data.length === 0 ? (
                        <p className="px-6 py-10 text-sm text-slate-500">No data in current filter scope</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Timestamp</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">User</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Module</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Action</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Reference</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Office</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {auditLogs.data.map((log) => (
                                        <tr key={log.id} className="align-top">
                                            <td className="px-4 py-3 text-slate-700 whitespace-nowrap">{formatDateTime(log.created_at)}</td>
                                            <td className="px-4 py-3 text-slate-800">{log.user?.name ?? 'System'}</td>
                                            <td className="px-4 py-3 text-slate-700">{formatModuleLabel(log.module_name)}</td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                                    {formatActionLabel(log.action_type)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">{formatReferenceLabel(log.reference_type)} #{log.reference_id}</td>
                                            <td className="px-4 py-3 text-slate-700">{log.office?.name ?? 'N/A'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {auditLogs.links && auditLogs.links.length > 3 && (
                        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/70 px-4 py-2.5">
                            <span className="text-xs font-medium text-slate-500">
                                Showing {auditLogs.data.length} audit logs
                            </span>
                            <PaginationNav links={auditLogs.links} preserveState={false} />
                        </div>
                    )}
                </section>

                <div id={exportSectionId} className="pdf-export-content hidden space-y-6 text-slate-900">
                    {/* Page 1: Governance Overview & Module/Action Breakdown */}
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
                                Executive Governance Event Summary
                            </h3>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="rounded-md border border-slate-300 bg-white p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-slate-600">Total Filtered Events</p>
                                    <p className="mt-1 text-2xl font-extrabold text-slate-900">{totalEvents}</p>
                                </div>
                                <div className="rounded-md border border-slate-300 bg-white p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-slate-600">Distinct Modules Active</p>
                                    <p className="mt-1 text-2xl font-extrabold text-slate-900">{activeModules}</p>
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <section className="rounded-lg border border-slate-300 bg-white p-3.5">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2.5">
                                    Activity by Functional Module
                                </h3>
                                <HorizontalBarChart data={moduleSummary} emptyText="No data in current filter scope" />
                            </section>

                            <section className="rounded-lg border border-slate-300 bg-white p-3.5">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2.5">
                                    Activity by Governance Action
                                </h3>
                                <HorizontalBarChart data={actionSummary} emptyText="No data in current filter scope" />
                            </section>
                        </div>
                    </GovDocumentPage>

                    {/* Continuation Pages: Itemized Audit Trail Tables */}
                    {auditLogsExportPages.length === 0 ? (
                        <GovDocumentPage
                            metadata={{
                                ...baseMetadata,
                                title: 'ITEMIZED GOVERNANCE EVENT LOG',
                                moduleScope: 'Itemized Business Event Audit Trail',
                            }}
                            pageNumber={2}
                            totalPages={2}
                            showLetterhead={true}
                            showSignatories={true}
                            showAttestation={true}
                        >
                            <p className="py-8 text-center text-sm text-slate-500">No governance logs recorded for the selected filter scope.</p>
                        </GovDocumentPage>
                    ) : (
                        auditLogsExportPages.map((pageRows, pageIndex) => {
                            const pageNumber = 2 + pageIndex;
                            const isLast = pageNumber === totalPdfPages;

                            return (
                                <GovDocumentPage
                                    key={`governance-export-page-${pageIndex + 1}`}
                                    metadata={{
                                        ...baseMetadata,
                                        title: 'ITEMIZED GOVERNANCE EVENT LOG',
                                        moduleScope: 'Itemized Business Event Audit Trail',
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
                                                    <th className="w-[18%] px-3 py-2 text-left border-r border-slate-300">Timestamp</th>
                                                    <th className="w-[16%] px-3 py-2 text-left border-r border-slate-300">User / Actor</th>
                                                    <th className="w-[22%] px-3 py-2 text-left border-r border-slate-300">Module</th>
                                                    <th className="w-[16%] px-3 py-2 text-left border-r border-slate-300">Action</th>
                                                    <th className="w-[14%] px-3 py-2 text-left border-r border-slate-300">Reference</th>
                                                    <th className="w-[14%] px-3 py-2 text-left">Office</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-200 bg-white">
                                                {pageRows.map((log) => (
                                                    <tr key={`${pageIndex}-${log.id}`}>
                                                        <td className="px-3 py-2 align-top text-slate-600 border-r border-slate-200 whitespace-nowrap">{formatDateTime(log.created_at)}</td>
                                                        <td className="px-3 py-2 align-top font-bold text-slate-900 border-r border-slate-200">{log.user?.name ?? 'System'}</td>
                                                        <td className="px-3 py-2 align-top text-slate-700 border-r border-slate-200">{formatModuleLabel(log.module_name)}</td>
                                                        <td className="px-3 py-2 align-top text-slate-700 border-r border-slate-200">{formatActionLabel(log.action_type)}</td>
                                                        <td className="px-3 py-2 align-top text-slate-700 border-r border-slate-200">{formatReferenceLabel(log.reference_type)} #{log.reference_id}</td>
                                                        <td className="px-3 py-2 align-top text-slate-700">{log.office?.name ?? 'N/A'}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </GovDocumentPage>
                            );
                        })
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function KpiCard({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-2xl border border-red-200 bg-gradient-to-r from-red-50 to-white p-6 shadow-[0_8px_18px_rgba(15,23,42,0.06)]">
            <p className="text-sm font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-2 text-4xl font-bold text-red-700">{value}</p>
        </div>
    );
}

function formatModuleLabel(value: string): string {
    const aliases: Record<string, string> = {
        'Executive.Anomaly': 'Anomaly Detection',
        'Executive.Alert': 'Alert Monitoring',
        'Executive.PPMP': 'PPMP Approval',
        'Executive.PublicDisclosure': 'Transparency Disclosure',
        'Executive.RecordArchive': 'Yearly Record Archive',
        'Office.SupplyRequest': 'Supply Request Submission',
        'Procurement.PurchaseRequest': 'Purchase Request Processing',
        'Procurement.PurchaseOrder': 'Procurement Order Processing',
        'Inventory.Delivery': 'Delivery Verification',
        'Inventory.Distribution': 'Distribution Activity',
        'Inventory.Claim': 'Claims Review',
    };

    return aliases[value] ?? value.replaceAll('.', ' ').replaceAll('_', ' ');
}

function formatActionLabel(value: string): string {
    const aliases: Record<string, string> = {
        CREATE_DRAFT: 'Draft Created',
        UPDATE_DRAFT: 'Draft Updated',
        PUBLISH: 'Disclosure Published',
        APPROVE: 'Approved',
        REJECTED: 'Rejected',
        RETURNED_FOR_REVISION: 'Returned for Revision',
    };

    const raw = aliases[value] ?? value;
    return raw.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function serializeAuditFilters(filters: {
    module_name?: string;
    action_type?: string;
    user_id?: number | string;
    from_date?: string;
    to_date?: string;
}): string {
    return JSON.stringify({
        module_name: filters.module_name ?? '',
        action_type: filters.action_type ?? '',
        user_id: String(filters.user_id ?? ''),
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
    });
}

function getDateRange(range: string): { from_date: string; to_date: string } {
    const today = new Date();
    const end = new Date(today.getFullYear(), today.getMonth(), today.getDate());

    if (range === 'today') {
        return { from_date: formatDateInput(end), to_date: formatDateInput(end) };
    }

    if (range === 'last_7_days') {
        return { from_date: formatDateInput(addDays(end, -6)), to_date: formatDateInput(end) };
    }

    if (range === 'last_30_days') {
        return { from_date: formatDateInput(addDays(end, -29)), to_date: formatDateInput(end) };
    }

    if (range === 'this_month') {
        return { from_date: formatDateInput(new Date(end.getFullYear(), end.getMonth(), 1)), to_date: formatDateInput(end) };
    }

    if (range === 'last_month') {
        const firstDay = new Date(end.getFullYear(), end.getMonth() - 1, 1);
        const lastDay = new Date(end.getFullYear(), end.getMonth(), 0);

        return { from_date: formatDateInput(firstDay), to_date: formatDateInput(lastDay) };
    }

    return { from_date: '', to_date: '' };
}

function resolveDateRangeKey(fromDate: string, toDate: string): string {
    const ranges = ['today', 'last_7_days', 'last_30_days', 'this_month', 'last_month'];
    const match = ranges.find((range) => {
        const value = getDateRange(range);

        return value.from_date === fromDate && value.to_date === toDate;
    });

    if (match) {
        return match;
    }

    return fromDate || toDate ? 'custom' : 'all';
}

function addDays(date: Date, days: number): Date {
    const next = new Date(date);
    next.setDate(next.getDate() + days);

    return next;
}

function formatDateInput(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function formatReferenceLabel(value: string): string {
    const aliases: Record<string, string> = {
        'App\\Models\\Ppmp': 'PPMP Record',
        'App\\Models\\SupplyRequest': 'Supply Request',
        'App\\Models\\PurchaseRequest': 'Purchase Request',
        'App\\Models\\PurchaseOrder': 'Purchase Order',
        'App\\Models\\DistributionRecord': 'Distribution Record',
        'App\\Models\\Claim': 'Claim Record',
        'App\\Models\\PublicDisclosure': 'Public Disclosure',
        'App\\Models\\Alert': 'Alert Record',
        'App\\Models\\Anomaly': 'Anomaly Record',
    };

    return aliases[value] ?? value.split('\\').pop()?.replaceAll('_', ' ') ?? 'Record';
}

function formatDateTime(value?: string | null): string {
    if (!value) return 'N/A';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}





