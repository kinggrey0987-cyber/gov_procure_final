import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ExecutiveChartCard, HorizontalBarChart, TrendGroupedBarChart, TrendLineChart } from '@/Components/Executive/Charts';
import { GovDocumentPage, GovDocumentMetadata } from '@/Components/Executive/GovDocumentLayout';
import { downloadSectionAsPdfById } from '@/lib/printSection';
import { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type OfficeBreakdown = {
    id: number;
    name: string;
    anomaly_count: number;
    alert_count: number;
};

type Props = {
    summary: {
        open_anomaly_count: number;
        in_review_anomaly_count: number;
        high_severity_anomaly_count: number;
        open_alert_count: number;
        in_review_alert_count: number;
        high_severity_alert_count: number;
        cross_office_anomaly_count: number;
        office_breakdown: OfficeBreakdown[];
    };
    procurement: {
        purchase_request_count: number;
        purchase_order_count: number;
        total_purchase_order_amount: number;
        monthly_trends: {
            purchase_requests: Array<{ month: string; total: number }>;
            purchase_orders: Array<{ month: string; total: number }>;
        };
    };
    transparency: {
        published_disclosure_count: number;
        draft_disclosure_count: number;
        status_breakdown: Array<{ status: string; total: number }>;
    };
    approvals: {
        pending_ppmp_approvals: number;
        ppmp_workflow_total: number;
        returned_for_revision: number;
        recently_approved: number;
        status_mix: Array<{ status: string; total: number }>;
    };
    anomaly_type_breakdown: Array<{ label: string; total: number }>;
    alert_severity_breakdown: Array<{ label: string; total: number }>;
    recent_anomalies: Array<{
        anomaly_id: number;
        anomaly_type: string;
        severity: string;
        status: string;
        detected_at?: string | null;
        office?: { id: number; name: string } | null;
        reference_type?: string | null;
        reference_id?: number | null;
        item_id?: number | null;
    }>;
    recent_anomalies_export: Array<{
        anomaly_id: number;
        anomaly_type: string;
        severity: string;
        status: string;
        detected_at?: string | null;
        office?: { id: number; name: string } | null;
        reference_type?: string | null;
        reference_id?: number | null;
        item_id?: number | null;
    }>;
    recent_alerts: Array<{
        alerts_id: number;
        alert_type: string;
        severity: string;
        status: string;
        detected_at?: string | null;
        office?: { id: number; name: string } | null;
    }>;
    recent_alerts_export: Array<{
        alerts_id: number;
        alert_type: string;
        severity: string;
        status: string;
        detected_at?: string | null;
        office?: { id: number; name: string } | null;
    }>;
    recent_disclosures: Array<{
        disclosure_id: number;
        title: string;
        type: string;
        status: string;
        published_at?: string | null;
        created_at?: string | null;
    }>;
    recent_disclosures_export: Array<{
        disclosure_id: number;
        title: string;
        type: string;
        status: string;
        published_at?: string | null;
        created_at?: string | null;
    }>;
    recent_business_logs_export: Array<{
        id: number;
        module_name: string;
        action_type: string;
        created_at: string;
        user?: { id: number; name: string } | null;
        office?: { id: number; name: string } | null;
    }>;
};

export default function ExecutiveDashboardIndex({
    summary,
    procurement,
    transparency,
    approvals,
    anomaly_type_breakdown,
    alert_severity_breakdown,
    recent_anomalies,
    recent_anomalies_export,
    recent_alerts,
    recent_alerts_export,
    recent_disclosures,
    recent_disclosures_export,
    recent_business_logs_export,
}: Props) {
    const printSectionId = 'executive-dashboard-print-section';
    const exportSectionId = 'executive-dashboard-export-section';
    const [anomalySearch, setAnomalySearch] = useState('');
    const [anomalySeverity, setAnomalySeverity] = useState('ALL');
    const [alertSearch, setAlertSearch] = useState('');
    const [alertSeverity, setAlertSeverity] = useState('ALL');

    const activeAnomalies = summary.open_anomaly_count + summary.in_review_anomaly_count;
    const activeAlerts = summary.open_alert_count + summary.in_review_alert_count;
    const highSeverityTotal = summary.high_severity_anomaly_count + summary.high_severity_alert_count;

    const monthlyProcurement = mergeGroupedTrend(
        procurement.monthly_trends.purchase_requests,
        procurement.monthly_trends.purchase_orders,
    );

    const totalActivityTrend = monthlyProcurement.map((row) => ({
        label: row.label,
        value: row.first + row.second,
    }));

    const approvalStatusMix = approvals.status_mix.map((row) => ({
        label: formatStatusLabel(row.status),
        value: row.total,
    }));

    const ppmpClearanceRate = approvals.ppmp_workflow_total > 0
        ? Math.round((approvals.recently_approved / approvals.ppmp_workflow_total) * 100)
        : 100;

    const topAnomalyCategories = (anomaly_type_breakdown.length > 0
        ? anomaly_type_breakdown.map((row) => ({
            label: formatAnomalyType(row.label || 'N/A'),
            value: row.total,
        }))
        : [
            { label: 'Open anomalies', value: summary.open_anomaly_count },
            { label: 'In review anomalies', value: summary.in_review_anomaly_count },
            { label: 'High severity anomalies', value: summary.high_severity_anomaly_count },
        ])
        .sort((a, b) => b.value - a.value)
        .slice(0, 6);

    const severitySnapshot = alert_severity_breakdown
        .map((row) => ({
            label: formatStatusLabel(row.label || 'N/A'),
            value: row.total,
        }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 6);

    const officeRanking = summary.office_breakdown
        .map((office) => ({
            label: office.name,
            value: office.anomaly_count + office.alert_count,
        }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 8);

    const throughputMix = [
        { label: 'Purchase Requests', value: procurement.purchase_request_count },
        { label: 'Purchase Orders', value: procurement.purchase_order_count },
        { label: 'Pending PPMP Approvals', value: approvals.pending_ppmp_approvals },
    ].sort((a, b) => b.value - a.value);

    const pendingOrReviewDisclosures = transparency.status_breakdown
        .filter((row) => {
            const status = String(row.status).toUpperCase();
            return status !== 'PUBLISHED' && status !== 'DRAFT';
        })
        .reduce((total, row) => total + row.total, 0);

    const recentAnomalyGroups = buildDashboardAnomalyGroups(recent_anomalies).slice(0, 5);

    const anomalySeverityOptions = useMemo(
        () => ['ALL', ...Array.from(new Set(recentAnomalyGroups.map((group) => group.representative.severity.toUpperCase())))],
        [recentAnomalyGroups],
    );

    const alertSeverityOptions = useMemo(
        () => ['ALL', ...Array.from(new Set(recent_alerts.map((alert) => alert.severity.toUpperCase())))],
        [recent_alerts],
    );

    const filteredAnomalyGroups = useMemo(() => {
        const keyword = anomalySearch.trim().toLowerCase();

        return recentAnomalyGroups.filter((group) => {
            const severityMatch = anomalySeverity === 'ALL' || group.representative.severity.toUpperCase() === anomalySeverity;

            if (!severityMatch) {
                return false;
            }

            if (keyword.length === 0) {
                return true;
            }

            return [
                group.label,
                group.office,
                group.representative.severity,
                group.representative.status,
                String(group.count),
            ].join(' ').toLowerCase().includes(keyword);
        });
    }, [anomalySearch, anomalySeverity, recentAnomalyGroups]);

    const filteredAlerts = useMemo(() => {
        const keyword = alertSearch.trim().toLowerCase();

        return recent_alerts.slice(0, 5).filter((alert) => {
            const severityMatch = alertSeverity === 'ALL' || alert.severity.toUpperCase() === alertSeverity;

            if (!severityMatch) {
                return false;
            }

            if (keyword.length === 0) {
                return true;
            }

            return [
                alert.alert_type,
                alert.severity,
                alert.status,
                alert.office?.name ?? 'N/A',
            ].join(' ').toLowerCase().includes(keyword);
        });
    }, [alertSearch, alertSeverity, recent_alerts]);

    const { auth } = usePage<PageProps>().props;
    const preparedByName = auth?.user?.name || 'Executive Officer';
    const preparedByRole = auth?.user?.role_code || 'Executive Officer';
    const preparedAt = new Date().toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });

    const anomalyPreviewRows = useMemo(() => filteredAnomalyGroups.slice(0, 5), [filteredAnomalyGroups]);
    const alertPreviewRows = useMemo(() => filteredAlerts.slice(0, 5), [filteredAlerts]);
    const anomalyExportPages = useMemo(() => chunkRows(recent_anomalies_export.slice(0, 24), 12), [recent_anomalies_export]);
    const alertExportPages = useMemo(() => chunkRows(recent_alerts_export.slice(0, 24), 12), [recent_alerts_export]);
    const disclosureExportPages = useMemo(() => chunkRows(recent_disclosures_export.slice(0, 12), 12), [recent_disclosures_export]);
    const businessLogExportPages = useMemo(() => chunkRows(recent_business_logs_export.slice(0, 12), 12), [recent_business_logs_export]);

    const totalPdfPages = 1 + anomalyExportPages.length + alertExportPages.length + disclosureExportPages.length + businessLogExportPages.length;

    const baseMetadata: GovDocumentMetadata = {
        title: 'CONSOLIDATED EXECUTIVE OVERSIGHT & MONITORING REPORT',
        formCode: 'FDP Form - Executive Oversight & Procurement Management',
        trackingCode: `EBM-EXEC-DASH-${new Date().getFullYear()}`,
        fiscalYear: new Date().getFullYear(),
        period: `Fiscal Year ${new Date().getFullYear()}`,
        moduleScope: 'Executive Overview & Comprehensive Risk Monitoring',
        preparedByName,
        preparedByRole,
        preparedAt,
    };

    const exportCsv = () => {
        const lines = [
            ['metric', 'value'],
            ['purchase_requests', String(procurement.purchase_request_count)],
            ['purchase_orders', String(procurement.purchase_order_count)],
            ['procurement_spend', String(procurement.total_purchase_order_amount)],
            ['active_anomalies', String(activeAnomalies)],
            ['active_alerts', String(activeAlerts)],
            ['published_disclosures', String(transparency.published_disclosure_count)],
            [''],
            ['anomaly_id', 'anomaly_type', 'severity', 'status', 'office', 'detected_at'],
            ...recent_anomalies_export.map((row) => [
                String(row.anomaly_id),
                formatAnomalyType(row.anomaly_type),
                formatStatusLabel(row.severity),
                formatStatusLabel(row.status),
                row.office?.name ?? 'N/A',
                row.detected_at ?? '',
            ]),
            [''],
            ['alert_id', 'alert_type', 'severity', 'status', 'office', 'detected_at'],
            ...recent_alerts_export.map((row) => [
                String(row.alerts_id),
                formatStatusLabel(row.alert_type),
                formatStatusLabel(row.severity),
                formatStatusLabel(row.status),
                row.office?.name ?? 'N/A',
                row.detected_at ?? '',
            ]),
            [''],
            ['disclosure_id', 'title', 'type', 'status', 'published_at'],
            ...recent_disclosures_export.map((row) => [
                String(row.disclosure_id),
                row.title,
                row.type,
                row.status,
                row.published_at ?? '',
            ]),
            [''],
            ['log_id', 'timestamp', 'module', 'action', 'user', 'office'],
            ...recent_business_logs_export.map((row) => [
                String(row.id),
                row.created_at,
                row.module_name,
                row.action_type,
                row.user?.name ?? 'System',
                row.office?.name ?? 'N/A',
            ]),
        ];

        const csv = lines.map((row) => row.map(csvValue).join(',')).join('\n');
        downloadFile(csv, 'executive-dashboard.csv', 'text/csv;charset=utf-8;');
    };

    const [isExportingPdf, setIsExportingPdf] = useState(false);

    const exportPdf = async () => {
        if (isExportingPdf) return;
        setIsExportingPdf(true);
        try {
            await downloadSectionAsPdfById(exportSectionId, {
                title: 'Executive Dashboard Report',
                fileName: 'executive-oversight-report.pdf',
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
            header="Executive Dashboard"
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
            <Head title="Executive Dashboard" />

            <div id={printSectionId} className="space-y-3.5">
                {/* Slim Executive Telemetry Strip - Zero Truncation, High Density */}
                <section className="rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-red-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Requests</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight">{procurement.purchase_request_count}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-red-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Orders</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight">{procurement.purchase_order_count}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-red-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Total Spend</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight truncate">{formatCurrency(procurement.total_purchase_order_amount)}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-rose-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-rose-700">Anomalies</p>
                                <p className="text-lg font-extrabold text-rose-700 leading-tight">{activeAnomalies}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-amber-500" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-amber-700">Active Alerts</p>
                                <p className="text-lg font-extrabold text-amber-700 leading-tight">{activeAlerts}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-600" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Disclosures</p>
                                <p className="text-lg font-extrabold text-emerald-700 leading-tight">{transparency.published_disclosure_count}</p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Executive Signal & Compliance Strip */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
                    <div className="flex items-center justify-between rounded-lg border border-red-200 bg-red-50/50 px-3 py-2 text-xs">
                        <div className="flex items-center gap-2 min-w-0">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-red-600" />
                            <p className="truncate text-slate-700">
                                <span className="font-bold text-red-800">{highSeverityTotal} High-Severity</span> &bull; {activeAnomalies + activeAlerts} Risk Signals
                            </p>
                        </div>
                        <Link
                            href={route('executive.anomalies-monitoring')}
                            className="shrink-0 ml-2 font-semibold text-red-700 hover:text-red-900 hover:underline"
                        >
                            Monitor &rarr;
                        </Link>
                    </div>

                    <div className="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50/50 px-3 py-2 text-xs">
                        <div className="flex items-center gap-2 min-w-0">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-600" />
                            <p className="truncate text-slate-700">
                                <span className="font-bold text-emerald-800">{ppmpClearanceRate}% Cleared</span> &bull; {approvals.pending_ppmp_approvals} Pending Signoff
                            </p>
                        </div>
                        <Link
                            href={route('executive.ppmp-approvals.index')}
                            className="shrink-0 ml-2 font-semibold text-emerald-700 hover:text-emerald-900 hover:underline"
                        >
                            PPMP &rarr;
                        </Link>
                    </div>

                    <div className="flex items-center justify-between rounded-lg border border-sky-200 bg-sky-50/50 px-3 py-2 text-xs">
                        <div className="flex items-center gap-2 min-w-0">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-sky-600" />
                            <p className="truncate text-slate-700">
                                <span className="font-bold text-sky-800">SGLG Compliant</span> &bull; {transparency.published_disclosure_count} Published ({transparency.draft_disclosure_count} Drafts)
                            </p>
                        </div>
                        <Link
                            href={route('executive.transparency-monitoring')}
                            className="shrink-0 ml-2 font-semibold text-sky-700 hover:text-sky-900 hover:underline"
                        >
                            Portal &rarr;
                        </Link>
                    </div>
                </div>

                {/* Primary Visual Cockpit: Graphs Emphasized */}
                <div className="grid gap-3.5 xl:grid-cols-12">
                    {/* Row 1: Activity Trend (7 cols) + PPMP Budget Clearance Gauge (5 cols) */}
                    <div className="xl:col-span-7">
                        <ExecutiveChartCard
                            title="Procurement Velocity & Trend"
                            subtitle="Requisition and purchase order movement across fiscal periods"
                            eyebrow="Activity Signal"
                            tone="critical"
                        >
                            {monthlyProcurement.length === 0 ? (
                                <CompactEmpty text="No data in current filter scope" />
                            ) : monthlyProcurement.length === 1 ? (
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <MiniStat label="Month" value={monthlyProcurement[0].label} />
                                    <MiniStat label="Purchase Requests" value={monthlyProcurement[0].first} />
                                    <MiniStat label="Purchase Orders" value={monthlyProcurement[0].second} />
                                </div>
                            ) : monthlyProcurement.length <= 3 ? (
                                <TrendGroupedBarChart
                                    data={monthlyProcurement}
                                    firstLabel="Purchase Requests"
                                    secondLabel="Purchase Orders"
                                    emptyText="No data in current filter scope"
                                />
                            ) : (
                                <TrendLineChart
                                    data={totalActivityTrend}
                                    emptyText="No data in current filter scope"
                                    showPointCards={false}
                                />
                            )}
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-5">
                        <ExecutiveChartCard
                            title="Annual PPMP & Budget Clearance"
                            subtitle="Departmental annual procurement plan clearance status"
                            eyebrow="Governance"
                            tone="emerald"
                            action={
                                <Link
                                    href={route('executive.ppmp-approvals.index')}
                                    className="text-xs font-semibold text-emerald-700 hover:text-emerald-800 hover:underline"
                                >
                                    Approvals Desk &rarr;
                                </Link>
                            }
                        >
                            <div className="space-y-3">
                                <div className="flex items-center gap-3.5 rounded-xl border border-emerald-100 bg-emerald-50/40 p-3">
                                    <div className="relative h-20 w-20 shrink-0">
                                        <svg viewBox="0 0 100 100" className="h-20 w-20 -rotate-90">
                                            <circle
                                                cx="50"
                                                cy="50"
                                                r="38"
                                                fill="none"
                                                stroke="rgb(226 232 240)"
                                                strokeWidth="8"
                                            />
                                            <circle
                                                cx="50"
                                                cy="50"
                                                r="38"
                                                fill="none"
                                                stroke={ppmpClearanceRate === 100 ? 'rgb(16 185 129)' : 'rgb(245 158 11)'}
                                                strokeWidth="8"
                                                strokeDasharray={2 * Math.PI * 38}
                                                strokeDashoffset={(2 * Math.PI * 38) * (1 - ppmpClearanceRate / 100)}
                                                strokeLinecap="round"
                                                className="transition-all duration-700 ease-out"
                                            />
                                        </svg>
                                        <div className="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none">
                                            <span className="text-base font-extrabold text-slate-900 leading-none">{ppmpClearanceRate}%</span>
                                            <span className="text-[8px] font-bold uppercase tracking-wider text-slate-500 mt-0.5">Cleared</span>
                                        </div>
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-xs font-bold uppercase tracking-wider text-emerald-900">
                                            {approvals.ppmp_workflow_total} Municipal Submissions
                                        </p>
                                        <p className="mt-0.5 text-xs text-slate-600 leading-tight">
                                            {approvals.pending_ppmp_approvals === 0
                                                ? 'All departmental plans cleared for budget execution.'
                                                : `${approvals.pending_ppmp_approvals} plan awaiting executive authorization.`}
                                        </p>
                                        <div className="mt-2 flex flex-wrap items-center gap-1.5 text-[11px] font-semibold">
                                            <span className="rounded-md border border-emerald-200 bg-white px-2 py-0.5 text-emerald-800">
                                                {approvals.recently_approved} Approved
                                            </span>
                                            <span className="rounded-md border border-amber-200 bg-white px-2 py-0.5 text-amber-800">
                                                {approvals.pending_ppmp_approvals} Pending
                                            </span>
                                            <span className="rounded-md border border-rose-200 bg-white px-2 py-0.5 text-rose-800">
                                                {approvals.returned_for_revision} Returned
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <Link
                                    href={route('executive.ppmp-approvals.index')}
                                    className="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 py-2 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800"
                                >
                                    Review PPMP Submissions &rarr;
                                </Link>
                            </div>
                        </ExecutiveChartCard>
                    </div>

                    {/* Row 2: Detected Anomaly Patterns (6 cols) + Departmental Concentration (6 cols) */}
                    <div className="xl:col-span-6">
                        <ExecutiveChartCard
                            title="Detected Anomaly Patterns"
                            subtitle="Distribution of flagged irregularities across municipal procurement"
                            eyebrow="Pattern Breakdown"
                            tone="warning"
                            action={
                                <Link
                                    href={route('executive.anomalies-monitoring')}
                                    className="text-xs font-semibold text-amber-700 hover:text-amber-800 hover:underline"
                                >
                                    View Signals &rarr;
                                </Link>
                            }
                        >
                            <HorizontalBarChart
                                data={topAnomalyCategories}
                                palette="multi"
                                showPercentage={true}
                                emptyText="No anomalies detected in current scope"
                            />
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                        <ExecutiveChartCard
                            title="Departmental Incident Concentration"
                            subtitle="Combined anomaly & alert incidents concentrated by municipal office"
                            eyebrow="Office Oversight"
                            tone="neutral"
                            action={
                                <Link
                                    href={route('executive.procurement-analytics')}
                                    className="text-xs font-semibold text-slate-700 hover:text-slate-900 hover:underline"
                                >
                                    Office Ledger &rarr;
                                </Link>
                            }
                        >
                            <HorizontalBarChart
                                data={officeRanking}
                                palette="multi"
                                showPercentage={true}
                                emptyText="No office incidents in current filter scope"
                            />
                        </ExecutiveChartCard>
                    </div>
                </div>

                {/* Bottom Tables: Recent Signals with Compact Filters */}
                <div className="grid gap-4 xl:grid-cols-2">
                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-2.5">
                            <h3 className="text-sm font-bold text-slate-900">Recent Anomaly Signals</h3>
                            <Link href={route('executive.anomalies-monitoring')} className="text-xs font-semibold text-red-700 hover:text-red-800 hover:underline">
                                Open Monitoring &rarr;
                            </Link>
                        </div>
                        <div className="border-b border-slate-100 bg-slate-50/60 p-2.5">
                            <div className="grid gap-2 grid-cols-1 sm:grid-cols-12">
                                <input
                                    type="text"
                                    value={anomalySearch}
                                    onChange={(event) => setAnomalySearch(event.target.value)}
                                    placeholder="Search anomaly or office"
                                    className="sm:col-span-7 h-8 rounded-lg border border-slate-300 px-2.5 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                />
                                <select
                                    value={anomalySeverity}
                                    onChange={(event) => setAnomalySeverity(event.target.value)}
                                    className="sm:col-span-3 h-8 rounded-lg border border-slate-300 px-2 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                >
                                    {anomalySeverityOptions.map((value) => (
                                        <option key={value} value={value}>{value === 'ALL' ? 'All severities' : formatStatusLabel(value)}</option>
                                    ))}
                                </select>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setAnomalySearch('');
                                        setAnomalySeverity('ALL');
                                    }}
                                    className="sm:col-span-2 h-8 rounded-lg border border-slate-300 px-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                        {anomalyPreviewRows.length === 0 ? (
                            <p className="px-4 py-5 text-center text-xs text-slate-500">No data in current filter scope</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-xs">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-3 py-1.5 text-left font-semibold uppercase tracking-wide text-slate-500">Type</th>
                                            <th className="px-3 py-1.5 text-left font-semibold uppercase tracking-wide text-slate-500">Records</th>
                                            <th className="px-3 py-1.5 text-left font-semibold uppercase tracking-wide text-slate-500">Severity</th>
                                            <th className="px-3 py-1.5 text-left font-semibold uppercase tracking-wide text-slate-500">Office</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {anomalyPreviewRows.map((group) => (
                                            <tr key={group.key} className="hover:bg-slate-50/50">
                                                <td className="px-3 py-1.5 text-slate-700 font-medium">{group.label}</td>
                                                <td className="px-3 py-1.5 text-slate-700">{group.count}</td>
                                                <td className="px-3 py-1.5">{SeverityBadge(group.representative.severity)}</td>
                                                <td className="px-3 py-1.5 text-slate-700">{group.office}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>

                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-2.5">
                            <h3 className="text-sm font-bold text-slate-900">Recent Operational Alerts</h3>
                            <Link href={route('executive.anomalies-monitoring')} className="text-xs font-semibold text-red-700 hover:text-red-800 hover:underline">
                                Open Monitoring &rarr;
                            </Link>
                        </div>
                        <div className="border-b border-slate-100 bg-slate-50/60 p-2.5">
                            <div className="grid gap-2 grid-cols-1 sm:grid-cols-12">
                                <input
                                    type="text"
                                    value={alertSearch}
                                    onChange={(event) => setAlertSearch(event.target.value)}
                                    placeholder="Search alert or office"
                                    className="sm:col-span-7 h-8 rounded-lg border border-slate-300 px-2.5 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                />
                                <select
                                    value={alertSeverity}
                                    onChange={(event) => setAlertSeverity(event.target.value)}
                                    className="sm:col-span-3 h-8 rounded-lg border border-slate-300 px-2 text-xs shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                                >
                                    {alertSeverityOptions.map((value) => (
                                        <option key={value} value={value}>{value === 'ALL' ? 'All severities' : formatStatusLabel(value)}</option>
                                    ))}
                                </select>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setAlertSearch('');
                                        setAlertSeverity('ALL');
                                    }}
                                    className="sm:col-span-2 h-8 rounded-lg border border-slate-300 px-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                        {alertPreviewRows.length === 0 ? (
                            <p className="px-4 py-5 text-center text-xs text-slate-500">No data in current filter scope</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-xs">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-3 py-1.5 text-left font-semibold uppercase tracking-wide text-slate-500">Type</th>
                                            <th className="px-3 py-1.5 text-left font-semibold uppercase tracking-wide text-slate-500">Severity</th>
                                            <th className="px-3 py-1.5 text-left font-semibold uppercase tracking-wide text-slate-500">Office</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {alertPreviewRows.map((row) => (
                                            <tr key={row.alerts_id} className="hover:bg-slate-50/50">
                                                <td className="px-3 py-1.5 text-slate-700 font-medium">{formatStatusLabel(row.alert_type)}</td>
                                                <td className="px-3 py-1.5">{SeverityBadge(row.severity)}</td>
                                                <td className="px-3 py-1.5 text-slate-700">{row.office?.name ?? 'N/A'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>

                <div id={exportSectionId} className="pdf-export-content hidden space-y-6 text-slate-900">
                    {/* Page 1: Executive Overview & Summary Metrics */}
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
                                Executive Activity & Spend Highlights
                            </h3>
                            <div className="grid grid-cols-3 gap-3">
                                <div className="rounded-md border border-slate-300 bg-white p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-slate-600">Purchase Requests</p>
                                    <p className="mt-1 text-2xl font-extrabold text-slate-900">{procurement.purchase_request_count}</p>
                                </div>
                                <div className="rounded-md border border-slate-300 bg-white p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-slate-600">Purchase Orders</p>
                                    <p className="mt-1 text-2xl font-extrabold text-slate-900">{procurement.purchase_order_count}</p>
                                </div>
                                <div className="rounded-md border border-slate-300 bg-white p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-slate-600">Published Disclosures</p>
                                    <p className="mt-1 text-2xl font-extrabold text-emerald-700">{transparency.published_disclosure_count}</p>
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <section className="rounded-lg border border-slate-300 bg-white p-3.5">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2.5">
                                    Active Risk Exposure Summary
                                </h3>
                                <div className="space-y-2">
                                    <RiskStrip label="Active Anomalies" value={activeAnomalies} max={Math.max(activeAnomalies, activeAlerts, highSeverityTotal, 1)} tone="rose" />
                                    <RiskStrip label="Active Alerts" value={activeAlerts} max={Math.max(activeAnomalies, activeAlerts, highSeverityTotal, 1)} tone="amber" />
                                    <RiskStrip label="High Severity Incidents" value={highSeverityTotal} max={Math.max(activeAnomalies, activeAlerts, highSeverityTotal, 1)} tone="slate" />
                                </div>
                            </section>

                            <section className="rounded-lg border border-slate-300 bg-white p-3.5">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2.5">
                                    Office Risk Concentration
                                </h3>
                                <HorizontalBarChart data={officeRanking} emptyText="No data in current filter scope" />
                            </section>
                        </div>
                    </GovDocumentPage>

                    {/* Continuation Pages: Anomaly Records */}
                    {anomalyExportPages.map((rows, pageIndex) => {
                        const pageNumber = 2 + pageIndex;
                        const isLast = pageNumber === totalPdfPages;

                        return (
                            <GovDocumentPage
                                key={`dashboard-anomalies-${pageIndex + 1}`}
                                metadata={{
                                    ...baseMetadata,
                                    title: 'EXECUTIVE ANOMALY INCIDENT RECORDS',
                                    moduleScope: 'Procurement Risk & Anomaly Detection',
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
                                                <th className="w-[28%] px-3 py-2 text-left border-r border-slate-300">Anomaly Type</th>
                                                <th className="w-[14%] px-3 py-2 text-center border-r border-slate-300">Severity</th>
                                                <th className="w-[14%] px-3 py-2 text-center border-r border-slate-300">Status</th>
                                                <th className="w-[24%] px-3 py-2 text-left border-r border-slate-300">Office</th>
                                                <th className="w-[20%] px-3 py-2 text-left">Detected At</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 bg-white">
                                            {rows.map((row) => (
                                                <tr key={`anomaly-${row.anomaly_id}`}>
                                                    <td className="px-3 py-2 align-top font-semibold text-slate-900 border-r border-slate-200">{formatAnomalyType(row.anomaly_type)}</td>
                                                    <td className="px-3 py-2 align-top text-center border-r border-slate-200">{formatStatusLabel(row.severity)}</td>
                                                    <td className="px-3 py-2 align-top text-center border-r border-slate-200">{formatStatusLabel(row.status)}</td>
                                                    <td className="px-3 py-2 align-top text-slate-700 border-r border-slate-200">{row.office?.name ?? 'N/A'}</td>
                                                    <td className="px-3 py-2 align-top text-slate-600 whitespace-nowrap">{formatDateTime(row.detected_at)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </GovDocumentPage>
                        );
                    })}

                    {/* Continuation Pages: Alert Records */}
                    {alertExportPages.map((rows, pageIndex) => {
                        const pageNumber = 2 + anomalyExportPages.length + pageIndex;
                        const isLast = pageNumber === totalPdfPages;

                        return (
                            <GovDocumentPage
                                key={`dashboard-alerts-${pageIndex + 1}`}
                                metadata={{
                                    ...baseMetadata,
                                    title: 'EXECUTIVE SYSTEM ALERT LOG',
                                    moduleScope: 'Procurement Workflow & Threshold Alerts',
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
                                                <th className="w-[30%] px-3 py-2 text-left border-r border-slate-300">Alert Type</th>
                                                <th className="w-[14%] px-3 py-2 text-center border-r border-slate-300">Severity</th>
                                                <th className="w-[14%] px-3 py-2 text-center border-r border-slate-300">Status</th>
                                                <th className="w-[22%] px-3 py-2 text-left border-r border-slate-300">Office</th>
                                                <th className="w-[20%] px-3 py-2 text-left">Detected At</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 bg-white">
                                            {rows.map((row) => (
                                                <tr key={`alert-${row.alerts_id}`}>
                                                    <td className="px-3 py-2 align-top font-semibold text-slate-900 border-r border-slate-200">{formatStatusLabel(row.alert_type)}</td>
                                                    <td className="px-3 py-2 align-top text-center border-r border-slate-200">{formatStatusLabel(row.severity)}</td>
                                                    <td className="px-3 py-2 align-top text-center border-r border-slate-200">{formatStatusLabel(row.status)}</td>
                                                    <td className="px-3 py-2 align-top text-slate-700 border-r border-slate-200">{row.office?.name ?? 'N/A'}</td>
                                                    <td className="px-3 py-2 align-top text-slate-600 whitespace-nowrap">{formatDateTime(row.detected_at)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </GovDocumentPage>
                        );
                    })}

                    {/* Continuation Pages: Disclosure Records */}
                    {disclosureExportPages.map((rows, pageIndex) => {
                        const pageNumber = 2 + anomalyExportPages.length + alertExportPages.length + pageIndex;
                        const isLast = pageNumber === totalPdfPages;

                        return (
                            <GovDocumentPage
                                key={`dashboard-disclosures-${pageIndex + 1}`}
                                metadata={{
                                    ...baseMetadata,
                                    title: 'PUBLIC DISCLOSURE PUBLICATION LOG',
                                    moduleScope: 'Public Transparency & Portal Publications',
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
                                                <th className="w-[34%] px-3 py-2 text-left border-r border-slate-300">Title</th>
                                                <th className="w-[18%] px-3 py-2 text-left border-r border-slate-300">Type</th>
                                                <th className="w-[14%] px-3 py-2 text-center border-r border-slate-300">Status</th>
                                                <th className="w-[17%] px-3 py-2 text-left border-r border-slate-300">Published</th>
                                                <th className="w-[17%] px-3 py-2 text-left">Created</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 bg-white">
                                            {rows.map((row) => (
                                                <tr key={`disclosure-${row.disclosure_id}`}>
                                                    <td className="px-3 py-2 align-top font-semibold text-slate-900 border-r border-slate-200">{row.title}</td>
                                                    <td className="px-3 py-2 align-top text-slate-700 border-r border-slate-200">{row.type}</td>
                                                    <td className="px-3 py-2 align-top text-center border-r border-slate-200">{row.status}</td>
                                                    <td className="px-3 py-2 align-top text-slate-600 border-r border-slate-200 whitespace-nowrap">{formatDateTime(row.published_at)}</td>
                                                    <td className="px-3 py-2 align-top text-slate-600 whitespace-nowrap">{formatDateTime(row.created_at)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </GovDocumentPage>
                        );
                    })}

                    {/* Continuation Pages: Business Activity Logs */}
                    {businessLogExportPages.map((rows, pageIndex) => {
                        const pageNumber = 2 + anomalyExportPages.length + alertExportPages.length + disclosureExportPages.length + pageIndex;
                        const isLast = pageNumber === totalPdfPages;

                        return (
                            <GovDocumentPage
                                key={`dashboard-logs-${pageIndex + 1}`}
                                metadata={{
                                    ...baseMetadata,
                                    title: 'GOVERNANCE BUSINESS ACTIVITY LOG',
                                    moduleScope: 'Executive Business Audit Trail',
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
                                                <th className="w-[20%] px-3 py-2 text-left border-r border-slate-300">Timestamp</th>
                                                <th className="w-[18%] px-3 py-2 text-left border-r border-slate-300">Module</th>
                                                <th className="w-[18%] px-3 py-2 text-left border-r border-slate-300">Action</th>
                                                <th className="w-[22%] px-3 py-2 text-left border-r border-slate-300">User</th>
                                                <th className="w-[22%] px-3 py-2 text-left">Office</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 bg-white">
                                            {rows.map((row) => (
                                                <tr key={`log-${row.id}`}>
                                                    <td className="px-3 py-2 align-top text-slate-600 border-r border-slate-200 whitespace-nowrap">{formatDateTime(row.created_at)}</td>
                                                    <td className="px-3 py-2 align-top font-semibold text-slate-900 border-r border-slate-200">{row.module_name}</td>
                                                    <td className="px-3 py-2 align-top text-slate-700 border-r border-slate-200">{row.action_type}</td>
                                                    <td className="px-3 py-2 align-top text-slate-800 border-r border-slate-200">{row.user?.name ?? 'System'}</td>
                                                    <td className="px-3 py-2 align-top text-slate-700">{row.office?.name ?? 'N/A'}</td>
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

function mergeGroupedTrend(
    requestTrend: Array<{ month: string; total: number }>,
    orderTrend: Array<{ month: string; total: number }>,
) {
    const requestMap = new Map(requestTrend.map((row) => [row.month, row.total]));
    const orderMap = new Map(orderTrend.map((row) => [row.month, row.total]));
    const allMonths = [...new Set([...requestMap.keys(), ...orderMap.keys()])].sort((a, b) => a.localeCompare(b));

    return allMonths.map((month) => ({
        label: formatMonth(month),
        first: requestMap.get(month) ?? 0,
        second: orderMap.get(month) ?? 0,
    }));
}

function formatMonth(value: string): string {
    const date = new Date(`${value}-01T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-PH', { month: 'short', year: 'numeric' });
}

function formatStatusLabel(value: string): string {
    const aliases: Record<string, string> = {
        OPEN: 'Pending Review',
        PROCESSING: 'Processing',
        DRAFT: 'Draft PO',
        PLACED: 'Issued PO',
        APPROVED: 'Approved',
        RETURNED_FOR_REVISION: 'Returned for Revision',
        SUBMITTED: 'Submitted',
        PENDING: 'Pending',
    };

    const normalized = value.toUpperCase();

    if (aliases[normalized]) {
        return aliases[normalized];
    }

    return value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatAnomalyType(value: string): string {
    const aliases: Record<string, string> = {
        PRICE_Z_SCORE: 'Price Anomaly',
        PRICE_ANOMALY: 'Price Anomaly',
        Z_SCORE_PRICE_ANOMALY: 'Price Anomaly',
        FREQUENT_REQUEST_WINDOW: 'Frequent Request Spike',
        FREQUENT_REQUEST_SPIKE: 'Frequent Request Spike',
        EXCESSIVE_QUANTITY: 'Excessive Quantity Anomaly',
        PPMP_OVER_REQUEST: 'PPMP Over-Request',
        PPMP_EARLY_DEPLETION: 'PPMP Early Depletion',
        PPMP_FREQUENCY_IRREGULARITY: 'PPMP Frequency Irregularity',
    };

    const normalized = value.toUpperCase();
    const raw = aliases[normalized] ?? value;
    return raw.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function buildDashboardAnomalyGroups(rows: Props['recent_anomalies']): Array<{ key: string; label: string; office: string; count: number; representative: Props['recent_anomalies'][number] }> {
    const grouped = new Map<string, { key: string; label: string; office: string; count: number; representative: Props['recent_anomalies'][number]; timestamp: number }>();

    rows.forEach((row) => {
        const office = row.office?.name ?? 'N/A';
        const sourceType = row.reference_type ? row.reference_type.split('\\').pop() ?? 'Source' : 'Source';
        const itemPart = row.item_id ? `item-${row.item_id}` : 'item-na';
        const key = `${row.anomaly_type}|${office}|${sourceType}|${itemPart}`;
        const timestamp = row.detected_at ? new Date(row.detected_at).getTime() : 0;

        const existing = grouped.get(key);

        if (!existing) {
            grouped.set(key, {
                key,
                label: formatAnomalyType(row.anomaly_type),
                office,
                count: 1,
                representative: row,
                timestamp: Number.isNaN(timestamp) ? 0 : timestamp,
            });

            return;
        }

        existing.count += 1;

        if ((Number.isNaN(timestamp) ? 0 : timestamp) >= existing.timestamp) {
            existing.timestamp = Number.isNaN(timestamp) ? 0 : timestamp;
            existing.representative = row;
        }
    });

    return [...grouped.values()]
        .sort((a, b) => (b.count - a.count) || (b.timestamp - a.timestamp))
        .map(({ key, label, office, count, representative }) => ({ key, label, office, count, representative }));
}

function MetricCard({
    label,
    value,
    helper,
    tone = 'default',
}: {
    label: string;
    value: number | string;
    helper?: string;
    tone?: 'default' | 'risk' | 'warning' | 'success';
}) {
    const toneClasses: Record<typeof tone, { border: string; gradient: string; value: string; accent: string }> = {
        default: {
            border: 'border-red-200',
            gradient: 'from-red-50 to-white',
            value: 'text-red-700',
            accent: 'bg-red-600',
        },
        risk: {
            border: 'border-rose-200',
            gradient: 'from-rose-50 to-white',
            value: 'text-rose-700',
            accent: 'bg-rose-600',
        },
        warning: {
            border: 'border-amber-200',
            gradient: 'from-amber-50 to-white',
            value: 'text-amber-700',
            accent: 'bg-amber-500',
        },
        success: {
            border: 'border-emerald-200',
            gradient: 'from-emerald-50 to-white',
            value: 'text-emerald-700',
            accent: 'bg-emerald-600',
        },
    };
    const currentTone = toneClasses[tone];

    return (
        <div className={`relative overflow-hidden rounded-xl border ${currentTone.border} bg-gradient-to-r ${currentTone.gradient} p-3.5 shadow-sm transition hover:shadow`}>
            <div className="flex items-center justify-between">
                <p className="pr-4 text-[11px] font-semibold uppercase tracking-wider text-slate-500 truncate" title={label}>{label}</p>
                <span className={`h-2 w-2 shrink-0 rounded-full ${currentTone.accent}`} aria-hidden="true" />
            </div>
            <p className={`mt-1 text-2xl font-bold tracking-tight ${currentTone.value}`}>{value}</p>
            {helper ? <p className="mt-1 text-[11px] text-slate-500 truncate" title={helper}>{helper}</p> : null}
        </div>
    );
}

function MiniStat({ label, value }: { label: string; value: number | string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-1 text-lg font-bold text-slate-900">{value}</p>
        </div>
    );
}

function RiskStrip({
    label,
    value,
    max,
    tone,
}: {
    label: string;
    value: number;
    max: number;
    tone: 'rose' | 'amber' | 'slate';
}) {
    const width = `${Math.max((value / max) * 100, value > 0 ? 8 : 0)}%`;
    const barTone: Record<typeof tone, string> = {
        rose: 'from-rose-700 to-rose-500',
        amber: 'from-amber-600 to-amber-400',
        slate: 'from-slate-700 to-slate-500',
    };

    return (
        <div className="space-y-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <div className="flex items-center justify-between">
                <p className="text-sm font-semibold text-slate-700">{label}</p>
                <p className="text-base font-bold text-slate-900">{value}</p>
            </div>
            <div className="h-2 rounded-full bg-slate-200">
                <div className={`h-2 rounded-full bg-gradient-to-r ${barTone[tone]}`} style={{ width }} />
            </div>
        </div>
    );
}

function SeverityBadge(value: string) {
    const normalized = value.toUpperCase();
    const tone = normalized === 'HIGH'
        ? 'border-rose-200 bg-rose-50 text-rose-700'
        : normalized === 'MEDIUM'
            ? 'border-amber-200 bg-amber-50 text-amber-700'
            : 'border-slate-200 bg-slate-50 text-slate-700';

    return <span className={`inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold ${tone}`}>{formatStatusLabel(value)}</span>;
}

function CompactEmpty({ text }: { text: string }) {
    return (
        <div className="rounded-xl border border-dashed border-red-200 bg-gradient-to-br from-red-50 to-white px-4 py-8 text-center text-sm text-slate-600">
            {text}
        </div>
    );
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(amount);
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





