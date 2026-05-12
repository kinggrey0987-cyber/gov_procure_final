import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ExecutiveChartCard, HorizontalBarChart, TrendGroupedBarChart, TrendLineChart } from '@/Components/Executive/Charts';
import { downloadSectionAsPdfById } from '@/lib/printSection';
import { Head, Link } from '@inertiajs/react';
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

    const anomalyPreviewRows = useMemo(() => filteredAnomalyGroups.slice(0, 5), [filteredAnomalyGroups]);
    const alertPreviewRows = useMemo(() => filteredAlerts.slice(0, 5), [filteredAlerts]);
    const anomalyExportPages = useMemo(() => chunkRows(recent_anomalies_export, 12), [recent_anomalies_export]);
    const alertExportPages = useMemo(() => chunkRows(recent_alerts_export, 12), [recent_alerts_export]);
    const disclosureExportPages = useMemo(() => chunkRows(recent_disclosures_export, 12), [recent_disclosures_export]);
    const businessLogExportPages = useMemo(() => chunkRows(recent_business_logs_export, 12), [recent_business_logs_export]);

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

    const exportPdf = async () => {
        await downloadSectionAsPdfById(exportSectionId, {
            title: 'Executive Dashboard',
            fileName: 'executive-dashboard.pdf',
            marginMm: 5,
            paperWidthPx: 760,
            orientation: 'portrait',
            excludeSelectors: ['.print-hide', 'nav', 'aside', '[role="navigation"]'],
            revealSelectors: ['.pdf-export-content'],
            pageBreakSelectors: ['.pdf-page'],
        });
    };

    return (
        <AuthenticatedLayout header="Executive Dashboard">
            <Head title="Executive Dashboard" />

            <div id={printSectionId} className="space-y-6 py-8">
                <section className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-200 bg-white px-5 py-4 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <div>
                        <h2 className="text-2xl font-semibold text-slate-900">Executive Oversight Overview</h2>
                        <p className="text-sm text-slate-600">Summary first, trend signals second, risk concentration third, supporting evidence last.</p>
                    </div>
                    <div className="print-hide flex items-center gap-2">
                        <button
                            type="button"
                            onClick={exportPdf}
                            className="rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                        >
                            Export PDF
                        </button>
                        <button
                            type="button"
                            onClick={exportCsv}
                            className="rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white hover:bg-red-800"
                        >
                            Export CSV
                        </button>
                    </div>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    <MetricCard label="Purchase Requests" value={procurement.purchase_request_count} helper="Office requests routed to BAC review" />
                    <MetricCard label="Purchase Orders" value={procurement.purchase_order_count} helper="Supplier-backed procurement records" />
                    <MetricCard label="Procurement Spend" value={formatCurrency(procurement.total_purchase_order_amount)} helper="Total value of seeded PO portfolio" />
                    <MetricCard label="Active Anomalies" value={activeAnomalies} helper="Open or under-review risk signals" tone="risk" />
                    <MetricCard label="Active Alerts" value={activeAlerts} helper="Executive attention queue" tone="warning" />
                    <MetricCard label="Published Disclosures" value={transparency.published_disclosure_count} helper="Citizen-facing transparency records" tone="success" />
                </div>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-8">
                        <ExecutiveChartCard title="Procurement Activity Trend" subtitle="Monthly procurement movement" tone="critical">
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
                                />
                            )}
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                        <ExecutiveChartCard title="PPMP Workflow Summary" subtitle="Approval status at a glance" tone="default">
                            {approvalStatusMix.length <= 2 ? (
                                <div className="grid gap-2">
                                    {approvalStatusMix.map((row) => (
                                        <MiniStat key={row.label} label={row.label} value={row.value} />
                                    ))}
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <div className="mx-auto h-[150px] w-[150px] lg:h-[160px] lg:w-[160px]">
                                        <svg viewBox="0 0 220 220" className="h-full w-full">
                                            <circle cx="110" cy="110" r={72} fill="none" stroke="rgb(254 226 226)" strokeWidth={24} />
                                            {approvalStatusMix.map((segment, index) => {
                                                const total = approvalStatusMix.reduce((sum, row) => sum + row.value, 0);
                                                const circumference = 2 * Math.PI * 72;
                                                const segmentLength = (segment.value / total) * circumference;
                                                const offset = approvalStatusMix.slice(0, index).reduce((sum, row) => sum + (row.value / total) * circumference, 0);

                                                return (
                                                    <circle
                                                        key={segment.label}
                                                        cx="110"
                                                        cy="110"
                                                        r={72}
                                                        fill="none"
                                                        stroke={['rgb(185 28 28)', 'rgb(245 158 11)', 'rgb(37 99 235)', 'rgb(22 163 74)'][index % 4]}
                                                        strokeWidth={24}
                                                        strokeDasharray={`${segmentLength} ${circumference - segmentLength}`}
                                                        strokeDashoffset={-offset}
                                                        transform="rotate(-90 110 110)"
                                                        strokeLinecap="butt"
                                                    />
                                                );
                                            })}

                                            <text x="110" y="105" textAnchor="middle" className="fill-slate-500 text-[10px] uppercase tracking-[0.14em]">
                                                PPMP
                                            </text>
                                            <text x="110" y="128" textAnchor="middle" className="fill-slate-900 text-[22px] font-bold">
                                                {approvals.ppmp_workflow_total}
                                            </text>
                                        </svg>
                                    </div>

                                    <div className="grid gap-2">
                                        {approvalStatusMix.map((segment, index) => {
                                            const total = approvalStatusMix.reduce((sum, row) => sum + row.value, 0);
                                            const percent = total > 0 ? Math.round((segment.value / total) * 100) : 0;

                                            return (
                                                <div key={segment.label} className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                                    <div className="flex min-w-0 items-start gap-2">
                                                        <span className="mt-1 h-3 w-3 shrink-0 rounded-full" style={{ backgroundColor: ['rgb(185 28 28)', 'rgb(245 158 11)', 'rgb(37 99 235)', 'rgb(22 163 74)'][index % 4] }} />
                                                        <span className="min-w-0 flex-1 break-words font-medium leading-5 text-slate-700">
                                                            {segment.label}
                                                        </span>
                                                    </div>
                                                    <div className="mt-1 pl-5 text-sm font-semibold text-slate-900">
                                                        {segment.value} ({percent}%)
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                    <div className="grid grid-cols-3 gap-2">
                                        <MiniStat label="Pending" value={approvals.pending_ppmp_approvals} />
                                        <MiniStat label="Returned" value={approvals.returned_for_revision} />
                                        <MiniStat label="Approved" value={approvals.recently_approved} />
                                    </div>
                                </div>
                            )}
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                        <ExecutiveChartCard title="Risk Summary" subtitle="Active and high-severity risk pressure" tone="critical">
                            <div className="space-y-3">
                                <RiskStrip label="Active Anomalies" value={activeAnomalies} max={Math.max(activeAnomalies, activeAlerts, highSeverityTotal, 1)} tone="rose" />
                                <RiskStrip label="Active Alerts" value={activeAlerts} max={Math.max(activeAnomalies, activeAlerts, highSeverityTotal, 1)} tone="amber" />
                                <RiskStrip label="High Severity Total" value={highSeverityTotal} max={Math.max(activeAnomalies, activeAlerts, highSeverityTotal, 1)} tone="slate" />
                            </div>
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-8">
                        <ExecutiveChartCard title="Risk Breakdown" subtitle="Top anomaly categories and severity snapshot" tone="warning">
                            <div className="grid gap-5 lg:grid-cols-2">
                                <div>
                                    <p className="mb-2 text-sm font-semibold text-slate-700">Top anomaly categories</p>
                                    <HorizontalBarChart data={topAnomalyCategories} emptyText="No data in current filter scope" />
                                </div>
                                <div>
                                    <p className="mb-2 text-sm font-semibold text-slate-700">Severity snapshot</p>
                                    <HorizontalBarChart data={severitySnapshot} emptyText="No data in current filter scope" />
                                </div>
                            </div>
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-8">
                        <ExecutiveChartCard title="Office Oversight Comparison" subtitle="Risk concentration by office" tone="neutral">
                            <HorizontalBarChart data={officeRanking} emptyText="No data in current filter scope" />
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4 space-y-4">
                        <ExecutiveChartCard title="Procurement Throughput" subtitle="Compact process concentration" tone="neutral">
                            <HorizontalBarChart data={throughputMix} emptyText="No data in current filter scope" />
                        </ExecutiveChartCard>

                        <ExecutiveChartCard title="Disclosure Snapshot" subtitle="Publication status summary" tone="default">
                            <div className="grid gap-2">
                                <MiniStat label="Published" value={transparency.published_disclosure_count} />
                                <MiniStat label="Draft" value={transparency.draft_disclosure_count} />
                                <MiniStat label="Pending / Review" value={pendingOrReviewDisclosures} />
                            </div>
                        </ExecutiveChartCard>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                            <h3 className="text-lg font-semibold text-slate-900">Recent Anomaly Signals</h3>
                            <Link href={route('executive.anomalies-monitoring')} className="rounded-md border border-red-300 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-50">
                                Open Risk & Alerts
                            </Link>
                        </div>
                        <div className="border-b border-slate-200 bg-slate-50 px-5 py-4">
                            <div className="grid gap-2 md:grid-cols-12">
                                <input
                                    type="text"
                                    value={anomalySearch}
                                    onChange={(event) => setAnomalySearch(event.target.value)}
                                    placeholder="Search anomaly type or office"
                                    className="md:col-span-7 rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                                />
                                <select
                                    value={anomalySeverity}
                                    onChange={(event) => setAnomalySeverity(event.target.value)}
                                    className="md:col-span-3 rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
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
                                    className="md:col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                >
                                    Clear
                                </button>
                            </div>
                            <p className="mt-2 text-xs text-slate-500">Showing {anomalyPreviewRows.length} of {filteredAnomalyGroups.length} anomaly group(s).</p>
                        </div>
                        {anomalyPreviewRows.length === 0 ? (
                            <p className="px-5 py-6 text-sm text-slate-500">No data in current filter scope</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Type</th>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Related Records</th>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Severity</th>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Office</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {anomalyPreviewRows.map((group) => (
                                            <tr key={group.key}>
                                                <td className="px-4 py-2 text-slate-700">{group.label}</td>
                                                <td className="px-4 py-2 text-slate-700">{group.count}</td>
                                                <td className="px-4 py-2">{SeverityBadge(group.representative.severity)}</td>
                                                <td className="px-4 py-2 text-slate-700">{group.office}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                            <h3 className="text-lg font-semibold text-slate-900">Recent Alert Signals</h3>
                            <Link href={route('executive.anomalies-monitoring')} className="rounded-md border border-red-300 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-50">
                                Open Risk & Alerts
                            </Link>
                        </div>
                        <div className="border-b border-slate-200 bg-slate-50 px-5 py-4">
                            <div className="grid gap-2 md:grid-cols-12">
                                <input
                                    type="text"
                                    value={alertSearch}
                                    onChange={(event) => setAlertSearch(event.target.value)}
                                    placeholder="Search alert type or office"
                                    className="md:col-span-7 rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                                />
                                <select
                                    value={alertSeverity}
                                    onChange={(event) => setAlertSeverity(event.target.value)}
                                    className="md:col-span-3 rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
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
                                    className="md:col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                >
                                    Clear
                                </button>
                            </div>
                            <p className="mt-2 text-xs text-slate-500">Showing {alertPreviewRows.length} of {filteredAlerts.length} alert(s).</p>
                        </div>
                        {alertPreviewRows.length === 0 ? (
                            <p className="px-5 py-6 text-sm text-slate-500">No data in current filter scope</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Type</th>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Severity</th>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Office</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {alertPreviewRows.map((row) => (
                                            <tr key={row.alerts_id}>
                                                <td className="px-4 py-2 text-slate-700">{formatStatusLabel(row.alert_type)}</td>
                                                <td className="px-4 py-2">{SeverityBadge(row.severity)}</td>
                                                <td className="px-4 py-2 text-slate-700">{row.office?.name ?? 'N/A'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>

                <div id={exportSectionId} className="pdf-export-content hidden space-y-4 text-slate-900">
                    <section className="pdf-page rounded-xl border border-red-100 bg-white p-4">
                        <h2 className="text-xl font-semibold text-slate-900">Executive Dashboard Summary</h2>
                        <p className="mt-1 text-xs text-slate-600">Summary and charts first, detailed records on the following pages.</p>

                        <div className="mt-3 grid gap-3 sm:grid-cols-3">
                            <MetricCard label="Purchase Requests" value={procurement.purchase_request_count} />
                            <MetricCard label="Purchase Orders" value={procurement.purchase_order_count} />
                            <MetricCard label="Published Disclosures" value={transparency.published_disclosure_count} />
                        </div>

                        <div className="mt-3 grid grid-cols-2 gap-3 [&_.truncate]:whitespace-normal [&_.truncate]:overflow-visible [&_.truncate]:text-clip [&_[class*='space-y-3.5']]:space-y-4">
                            <section className="rounded-xl border border-red-100 bg-white p-3">
                                <h3 className="text-sm font-semibold text-slate-900">Risk Summary</h3>
                                <div className="mt-3 space-y-3">
                                    <RiskStrip label="Active Anomalies" value={activeAnomalies} max={Math.max(activeAnomalies, activeAlerts, highSeverityTotal, 1)} tone="rose" />
                                    <RiskStrip label="Active Alerts" value={activeAlerts} max={Math.max(activeAnomalies, activeAlerts, highSeverityTotal, 1)} tone="amber" />
                                    <RiskStrip label="High Severity Total" value={highSeverityTotal} max={Math.max(activeAnomalies, activeAlerts, highSeverityTotal, 1)} tone="slate" />
                                </div>
                            </section>
                            <section className="rounded-xl border border-red-100 bg-white p-3">
                                <h3 className="text-sm font-semibold text-slate-900">Office Oversight Comparison</h3>
                                <div className="mt-3">
                                    <HorizontalBarChart data={officeRanking} emptyText="No data in current filter scope" />
                                </div>
                            </section>
                        </div>
                    </section>

                    {anomalyExportPages.map((rows, pageIndex) => (
                        <section key={`dashboard-anomalies-${pageIndex + 1}`} className="pdf-page overflow-hidden rounded-xl border border-red-100 bg-white">
                            <ExportPageHeader title="Executive Anomaly Records" subtitle={pageIndex === 0 ? 'All exported anomaly records.' : `Continuation page ${pageIndex + 2}.`} />
                            <div className="overflow-x-auto">
                                <table className="min-w-full table-fixed divide-y divide-slate-200 text-[11px]">
                                    <thead className="bg-slate-50"><tr><th className="w-[26%] px-2 py-2 text-left">Type</th><th className="w-[14%] px-2 py-2 text-left">Severity</th><th className="w-[14%] px-2 py-2 text-left">Status</th><th className="w-[24%] px-2 py-2 text-left">Office</th><th className="w-[22%] px-2 py-2 text-left">Detected</th></tr></thead>
                                    <tbody className="divide-y divide-slate-100">{rows.map((row) => <tr key={`anomaly-${row.anomaly_id}`}><td className="px-2 py-1.5 align-top leading-snug">{formatAnomalyType(row.anomaly_type)}</td><td className="px-2 py-1.5 align-top leading-snug">{formatStatusLabel(row.severity)}</td><td className="px-2 py-1.5 align-top leading-snug">{formatStatusLabel(row.status)}</td><td className="px-2 py-1.5 align-top leading-snug">{row.office?.name ?? 'N/A'}</td><td className="px-2 py-1.5 align-top leading-snug">{row.detected_at ?? 'N/A'}</td></tr>)}</tbody>
                                </table>
                            </div>
                        </section>
                    ))}

                    {alertExportPages.map((rows, pageIndex) => (
                        <section key={`dashboard-alerts-${pageIndex + 1}`} className="pdf-page overflow-hidden rounded-xl border border-red-100 bg-white">
                            <ExportPageHeader title="Executive Alert Records" subtitle={pageIndex === 0 ? 'All exported alert records.' : `Continuation page ${pageIndex + 2}.`} />
                            <div className="overflow-x-auto">
                                <table className="min-w-full table-fixed divide-y divide-slate-200 text-[11px]">
                                    <thead className="bg-slate-50"><tr><th className="w-[30%] px-2 py-2 text-left">Type</th><th className="w-[14%] px-2 py-2 text-left">Severity</th><th className="w-[14%] px-2 py-2 text-left">Status</th><th className="w-[24%] px-2 py-2 text-left">Office</th><th className="w-[18%] px-2 py-2 text-left">Detected</th></tr></thead>
                                    <tbody className="divide-y divide-slate-100">{rows.map((row) => <tr key={`alert-${row.alerts_id}`}><td className="px-2 py-1.5 align-top leading-snug">{formatStatusLabel(row.alert_type)}</td><td className="px-2 py-1.5 align-top leading-snug">{formatStatusLabel(row.severity)}</td><td className="px-2 py-1.5 align-top leading-snug">{formatStatusLabel(row.status)}</td><td className="px-2 py-1.5 align-top leading-snug">{row.office?.name ?? 'N/A'}</td><td className="px-2 py-1.5 align-top leading-snug">{row.detected_at ?? 'N/A'}</td></tr>)}</tbody>
                                </table>
                            </div>
                        </section>
                    ))}

                    {disclosureExportPages.map((rows, pageIndex) => (
                        <section key={`dashboard-disclosures-${pageIndex + 1}`} className="pdf-page overflow-hidden rounded-xl border border-red-100 bg-white">
                            <ExportPageHeader title="Executive Disclosure Records" subtitle={pageIndex === 0 ? 'All exported disclosure records.' : `Continuation page ${pageIndex + 2}.`} />
                            <div className="overflow-x-auto">
                                <table className="min-w-full table-fixed divide-y divide-slate-200 text-[11px]">
                                    <thead className="bg-slate-50"><tr><th className="w-[34%] px-2 py-2 text-left">Title</th><th className="w-[18%] px-2 py-2 text-left">Type</th><th className="w-[16%] px-2 py-2 text-left">Status</th><th className="w-[16%] px-2 py-2 text-left">Published</th><th className="w-[16%] px-2 py-2 text-left">Created</th></tr></thead>
                                    <tbody className="divide-y divide-slate-100">{rows.map((row) => <tr key={`disclosure-${row.disclosure_id}`}><td className="px-2 py-1.5 align-top leading-snug">{row.title}</td><td className="px-2 py-1.5 align-top leading-snug">{row.type}</td><td className="px-2 py-1.5 align-top leading-snug">{row.status}</td><td className="px-2 py-1.5 align-top leading-snug">{row.published_at ?? 'N/A'}</td><td className="px-2 py-1.5 align-top leading-snug">{row.created_at ?? 'N/A'}</td></tr>)}</tbody>
                                </table>
                            </div>
                        </section>
                    ))}

                    {businessLogExportPages.map((rows, pageIndex) => (
                        <section key={`dashboard-logs-${pageIndex + 1}`} className="pdf-page overflow-hidden rounded-xl border border-red-100 bg-white">
                            <ExportPageHeader title="Executive Governance Activity Records" subtitle={pageIndex === 0 ? 'All exported governance activity records.' : `Continuation page ${pageIndex + 2}.`} />
                            <div className="overflow-x-auto">
                                <table className="min-w-full table-fixed divide-y divide-slate-200 text-[11px]">
                                    <thead className="bg-slate-50"><tr><th className="w-[18%] px-2 py-2 text-left">Timestamp</th><th className="w-[20%] px-2 py-2 text-left">Module</th><th className="w-[18%] px-2 py-2 text-left">Action</th><th className="w-[22%] px-2 py-2 text-left">User</th><th className="w-[22%] px-2 py-2 text-left">Office</th></tr></thead>
                                    <tbody className="divide-y divide-slate-100">{rows.map((row) => <tr key={`log-${row.id}`}><td className="px-2 py-1.5 align-top leading-snug">{row.created_at}</td><td className="px-2 py-1.5 align-top leading-snug">{row.module_name}</td><td className="px-2 py-1.5 align-top leading-snug">{row.action_type}</td><td className="px-2 py-1.5 align-top leading-snug">{row.user?.name ?? 'System'}</td><td className="px-2 py-1.5 align-top leading-snug">{row.office?.name ?? 'N/A'}</td></tr>)}</tbody>
                                </table>
                            </div>
                        </section>
                    ))}
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
        <div className={`relative overflow-hidden rounded-2xl border ${currentTone.border} bg-gradient-to-r ${currentTone.gradient} p-6 shadow-[0_8px_18px_rgba(15,23,42,0.06)]`}>
            <span className={`absolute right-4 top-4 h-2.5 w-2.5 rounded-full ${currentTone.accent}`} aria-hidden="true" />
            <p className="pr-5 text-sm font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className={`mt-2 text-4xl font-bold ${currentTone.value}`}>{value}</p>
            {helper ? <p className="mt-3 min-h-10 text-xs leading-5 text-slate-600">{helper}</p> : null}
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





