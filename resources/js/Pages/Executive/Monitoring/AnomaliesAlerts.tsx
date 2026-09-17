import { DonutChart, ExecutiveChartCard, HorizontalBarChart, TrendGroupedBarChart } from '@/Components/Executive/Charts';
import { GovDocumentPage, GovDocumentMetadata } from '@/Components/Executive/GovDocumentLayout';
import MonthFilterSelect from '@/Components/MonthFilterSelect';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { downloadSectionAsPdfById } from '@/lib/printSection';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

type OptionOffice = { id: number; name: string };
type BreakdownRow = { status: string; total: number };

type AnomalyRow = {
    anomaly_id: number;
    anomaly_type: string;
    severity: string;
    status: string;
    detected_at: string | null;
    office?: { id: number; name: string } | null;
    reference_type?: string | null;
    reference_id?: number | null;
    item_id?: number | null;
    detected_value?: number | string | null;
    expected_value?: number | string | null;
    threshold_value?: number | string | null;
};

type AlertRow = {
    alerts_id: number;
    alert_type: string;
    severity: string;
    status: string;
    detected_at: string | null;
    office?: { id: number; name: string } | null;
    anomaly_id?: number | null;
    supply_request_id?: number | null;
    purchase_order_id?: number | null;
    supplier_id?: number | null;
    alert_message?: string | null;
};

type Props = {
    filters: {
        office_id?: number | null;
        severity?: string | null;
        status?: string | null;
        anomaly_type?: string | null;
        year?: string | null;
        month?: string | null;
        from_date?: string | null;
        to_date?: string | null;
    };
    filter_options: {
        offices: OptionOffice[];
        severities: string[];
        statuses: string[];
        anomaly_types: string[];
        years: number[];
    };
    totals: {
        anomaly_count: number;
        alert_count: number;
        high_severity_anomaly_count: number;
        high_severity_alert_count: number;
    };
    status_breakdown: {
        anomalies: BreakdownRow[];
        alerts: BreakdownRow[];
    };
    anomaly_type_breakdown: Array<{ label: string; total: number }>;
    anomaly_severity_breakdown: Array<{ label: string; total: number }>;
    alert_severity_breakdown: Array<{ label: string; total: number }>;
    office_comparison: Array<{
        office_id: number;
        office_name: string;
        anomaly_count: number;
        alert_count: number;
    }>;
    monthly_trends: {
        anomalies: Array<{ month: string; total: number }>;
        alerts: Array<{ month: string; total: number }>;
    };
    recent: {
        anomalies: AnomalyRow[];
        alerts: AlertRow[];
    };
};

export default function ExecutiveAnomaliesAlertsMonitoring({
    filters,
    filter_options,
    totals,
    status_breakdown,
    anomaly_type_breakdown,
    anomaly_severity_breakdown,
    alert_severity_breakdown,
    office_comparison,
    monthly_trends,
    recent,
}: Props) {
    const pageSize = 5;
    const printSectionId = 'risk-alerts-print-section';
    const [anomalyPage, setAnomalyPage] = useState(1);
    const [alertPage, setAlertPage] = useState(1);

    const [form, setForm] = useState({
        office_id: filters.office_id ? String(filters.office_id) : '',
        status: filters.status ?? '',
        year: filters.year ?? '',
        month: filters.month ?? '',
    });

    useEffect(() => {
        setForm({
            office_id: filters.office_id ? String(filters.office_id) : '',
            status: filters.status ?? '',
            year: filters.year ?? '',
            month: filters.month ?? '',
        });
    }, [filters.month, filters.office_id, filters.status, filters.year]);

    const activeFilters = useMemo(
        () => ({
            office_id: form.office_id,
            status: form.status,
            year: form.year,
            month: form.month,
        }),
        [form.month, form.office_id, form.status, form.year],
    );

    const activeFiltersKey = useMemo(() => serializeRiskFilters(activeFilters), [activeFilters]);
    const currentFiltersKey = useMemo(() => serializeRiskFilters({
        office_id: filters.office_id ? String(filters.office_id) : '',
        status: filters.status ?? '',
        year: filters.year ?? '',
        month: filters.month ?? '',
    }), [filters.month, filters.office_id, filters.status, filters.year]);

    useEffect(() => {
        if (activeFiltersKey === currentFiltersKey) {
            return;
        }

        router.get(route('executive.anomalies-monitoring'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['filters', 'filter_options', 'totals', 'status_breakdown', 'anomaly_type_breakdown', 'anomaly_severity_breakdown', 'alert_severity_breakdown', 'office_comparison', 'monthly_trends', 'recent'],
        });
    }, [activeFilters, activeFiltersKey, currentFiltersKey]);

    const trendData = mergeMonthlyTrends(monthly_trends.anomalies, monthly_trends.alerts);
    const officeRisk = office_comparison
        .map((row) => ({
            label: row.office_name,
            value: row.anomaly_count + row.alert_count,
        }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 8);

    const anomalyTypeMix = anomaly_type_breakdown
        .map((row) => ({ label: formatAnomalyType(row.label || 'N/A'), value: row.total }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 6);

    const severityMix = mergeSeverity(anomaly_severity_breakdown, alert_severity_breakdown);
    const statusMix = mergeStatus(status_breakdown.anomalies, status_breakdown.alerts);
    const totalAnomalyPages = Math.max(1, Math.ceil(recent.anomalies.length / pageSize));
    const totalAlertPages = Math.max(1, Math.ceil(recent.alerts.length / pageSize));

    const normalizedAnomalyPage = Math.min(anomalyPage, totalAnomalyPages);
    const normalizedAlertPage = Math.min(alertPage, totalAlertPages);

    const visibleAnomalies = useMemo(() => {
        const start = (normalizedAnomalyPage - 1) * pageSize;
        return recent.anomalies.slice(start, start + pageSize);
    }, [normalizedAnomalyPage, recent.anomalies]);

    const visibleAlerts = useMemo(() => {
        const start = (normalizedAlertPage - 1) * pageSize;
        return recent.alerts.slice(start, start + pageSize);
    }, [normalizedAlertPage, recent.alerts]);

    useEffect(() => {
        setAnomalyPage(1);
    }, [recent.anomalies]);

    useEffect(() => {
        setAlertPage(1);
    }, [recent.alerts]);

    const exportCsv = () => {
        const rows = [
            ['metric', 'value'],
            ['anomalies', String(totals.anomaly_count)],
            ['alerts', String(totals.alert_count)],
            ['high_severity_anomalies', String(totals.high_severity_anomaly_count)],
            ['high_severity_alerts', String(totals.high_severity_alert_count)],
        ];

        downloadCsv(rows, 'risk-and-alerts.csv');
    };

    const { auth } = usePage<PageProps>().props;
    const exportSectionId = 'risk-alerts-export-section';

    const anomalyExportPages = useMemo(() => chunkRows(recent.anomalies.slice(0, 24), 12), [recent.anomalies]);
    const alertExportPages = useMemo(() => chunkRows(recent.alerts.slice(0, 24), 12), [recent.alerts]);
    const totalPdfPages = 1 + (anomalyExportPages.length > 0 ? anomalyExportPages.length : 1) + (alertExportPages.length > 0 ? alertExportPages.length : 1);

    const baseMetadata: GovDocumentMetadata = {
        title: 'EXECUTIVE RISK SIGNALS & PROCUREMENT ANOMALIES REPORT',
        formCode: 'FDP Form - Risk Management & Anomaly Detection',
        trackingCode: `EBM-RISK-${form.year || new Date().getFullYear()}`,
        fiscalYear: form.year || new Date().getFullYear(),
        period: form.year ? `Fiscal Year ${form.year}${form.month ? ` - Month ${form.month}` : ''}` : `Fiscal Year ${new Date().getFullYear()}`,
        moduleScope: 'Automated Anomaly Detection & High-Severity Alerts Monitoring',
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
                title: 'Risk and Alerts Report',
                fileName: 'risk-and-alerts-report.pdf',
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
            header="Risk & Alerts"
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
            <Head title="Risk & Alerts" />

            <div id={printSectionId} className="space-y-4">
                <section className="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <div className="grid min-w-0 flex-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                            <select
                                className="h-8 rounded-lg border-slate-300 text-xs shadow-sm focus:border-red-500 focus:ring-1 focus:ring-red-500"
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
                                className="h-8 rounded-lg border-slate-300 text-xs shadow-sm focus:border-red-500 focus:ring-1 focus:ring-red-500"
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

                            <MonthFilterSelect
                                className="h-8 rounded-lg border-slate-300 text-xs shadow-sm focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                month={form.month}
                                year={form.year}
                                yearOptions={filter_options.years}
                                onMonthChange={(value) => setForm((current) => ({ ...current, month: value }))}
                                onYearChange={(value) => setForm((current) => ({ ...current, year: value }))}
                            />
                        </div>

                        <div className="ml-auto flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => router.get(route('executive.anomalies-monitoring'))}
                                className="h-8 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </section>

                {/* Slim Executive Telemetry Strip - Interactive Scroll Drill-Downs */}
                <section className="rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    <div className="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <button
                            type="button"
                            onClick={() => {
                                document.getElementById('recent-anomalies-table')?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-left transition hover:bg-slate-50 group cursor-pointer"
                            title="Jump to Recent Anomalies"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-red-600 group-hover:scale-125 transition" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 group-hover:text-slate-700">Anomalies</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight group-hover:text-red-700">{totals.anomaly_count}</p>
                            </div>
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                document.getElementById('recent-alerts-table')?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-left transition hover:bg-slate-50 group cursor-pointer"
                            title="Jump to Recent Alerts"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-amber-500 group-hover:scale-125 transition" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 group-hover:text-slate-700">Alerts</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight group-hover:text-amber-700">{totals.alert_count}</p>
                            </div>
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                document.getElementById('recent-anomalies-table')?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-left transition hover:bg-rose-50/50 group cursor-pointer"
                            title="Jump to Recent Anomalies"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-rose-600 group-hover:scale-125 transition" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-rose-700">High Severity Anomalies</p>
                                <p className="text-lg font-extrabold text-rose-900 leading-tight">{totals.high_severity_anomaly_count}</p>
                            </div>
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                document.getElementById('recent-alerts-table')?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-left transition hover:bg-rose-50/50 group cursor-pointer"
                            title="Jump to Recent Alerts"
                        >
                            <span className="h-2 w-2 shrink-0 rounded-full bg-rose-600 group-hover:scale-125 transition" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-rose-700">High Severity Alerts</p>
                                <p className="text-lg font-extrabold text-rose-900 leading-tight">{totals.high_severity_alert_count}</p>
                            </div>
                        </button>
                    </div>
                </section>

                <div className="grid gap-4 xl:grid-cols-12">
                    <div className="xl:col-span-6">
                        <ExecutiveChartCard
                            title="Incident Movement & Distribution"
                            subtitle="Anomaly and alert movement across fiscal monitoring periods"
                            eyebrow="Incident Activity"
                            tone="critical"
                        >
                            {trendData.length === 0 ? (
                                <CompactEmpty text="No incident trend data in current filter scope." />
                            ) : trendData.length === 1 ? (
                                <div className="space-y-3">
                                    <div className="grid gap-2 sm:grid-cols-3">
                                        <MiniStat label="Monitored Period" value={trendData[0].label} />
                                        <MiniStat label="Anomalies" value={trendData[0].first} />
                                        <MiniStat label="Alerts" value={trendData[0].second} />
                                    </div>

                                    <div className="space-y-1 pt-1">
                                        <div className="flex justify-between text-[11px] font-semibold text-slate-600">
                                            <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-red-600" /> Anomalies ({trendData[0].first})</span>
                                            <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-amber-500" /> Alerts ({trendData[0].second})</span>
                                        </div>
                                        <div className="flex h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                className="bg-gradient-to-r from-red-600 to-rose-500 transition-all duration-500"
                                                style={{
                                                    width: `${(trendData[0].first / Math.max(trendData[0].first + trendData[0].second, 1)) * 100}%`,
                                                }}
                                            />
                                            <div
                                                className="bg-gradient-to-r from-amber-500 to-yellow-400 transition-all duration-500"
                                                style={{
                                                    width: `${(trendData[0].second / Math.max(trendData[0].first + trendData[0].second, 1)) * 100}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <TrendGroupedBarChart
                                    data={trendData}
                                    firstLabel="Anomalies"
                                    secondLabel="Alerts"
                                    emptyText="No data in current filter scope"
                                />
                            )}
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                        <ExecutiveChartCard
                            title="Office Incident Ranking"
                            subtitle="Total anomalies & alerts concentrated by municipal department"
                            eyebrow="Departmental Risk"
                            tone="neutral"
                        >
                            <HorizontalBarChart
                                data={officeRisk}
                                palette="multi"
                                showPercentage={true}
                                emptyText="No data in current filter scope"
                            />
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                        <ExecutiveChartCard
                            title="Top Anomaly Categories"
                            subtitle="Most frequent irregularity types detected across requisitions"
                            eyebrow="Pattern Breakdown"
                            tone="warning"
                        >
                            <HorizontalBarChart
                                data={anomalyTypeMix}
                                palette="multi"
                                showPercentage={true}
                                emptyText="No data in current filter scope"
                            />
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                        <ExecutiveChartCard
                            title="Severity & Lifecycle Status Breakdown"
                            subtitle="Risk level concentration and incident resolution velocity"
                            eyebrow="Risk & Velocity"
                            tone="default"
                        >
                            <div className="grid gap-4 sm:grid-cols-2 items-start">
                                <div>
                                    <p className="mb-2.5 text-xs font-bold uppercase tracking-wider text-slate-600">Severity Distribution</p>
                                    <DonutChart
                                        data={severityMix}
                                        emptyText="No severity data"
                                        centerLabel="Total"
                                        layout="col"
                                        paletteType="severity"
                                    />
                                </div>
                                <div>
                                    <p className="mb-2.5 text-xs font-bold uppercase tracking-wider text-slate-600">Resolution Status</p>
                                    <HorizontalBarChart
                                        data={statusMix}
                                        palette="severity"
                                        showPercentage={true}
                                        emptyText="No status data"
                                    />
                                </div>
                            </div>
                        </ExecutiveChartCard>
                    </div>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <section id="recent-anomalies-table" className="scroll-mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-2.5">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">Recent Anomalies</h3>
                                <p className="text-[11px] text-slate-500">Top 5 records &bull; Select record to view full anomaly breakdown</p>
                            </div>
                        </div>
                        {recent.anomalies.length === 0 ? (
                            <p className="px-4 py-6 text-center text-xs text-slate-500">No anomalies found for the selected filters.</p>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full table-fixed divide-y divide-slate-200 text-xs">
                                        <thead className="bg-slate-50">
                                            <tr>
                                                <th className="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Type</th>
                                                <th className="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Severity</th>
                                                <th className="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Office</th>
                                                <th className="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Detected</th>
                                                <th className="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {visibleAnomalies.map((row) => (
                                                <tr key={row.anomaly_id} className="hover:bg-slate-50/50">
                                                    <td className="px-3 py-2 font-medium text-slate-800 break-words">{formatAnomalyType(row.anomaly_type)}</td>
                                                    <td className="px-3 py-2">{SeverityBadge(row.severity)}</td>
                                                    <td className="px-3 py-2 text-slate-600 break-words">{row.office?.name ?? 'N/A'}</td>
                                                    <td className="px-3 py-2 text-slate-500">{formatDate(row.detected_at)}</td>
                                                    <td className="px-3 py-2 text-right">
                                                        <Link
                                                            href={route('executive.anomalies.show', row.anomaly_id)}
                                                            className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-50 hover:border-red-300 transition shadow-xs"
                                                        >
                                                            Inspect &rarr;
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <SimplePager
                                    page={normalizedAnomalyPage}
                                    totalPages={totalAnomalyPages}
                                    onPageChange={setAnomalyPage}
                                    totalCount={recent.anomalies.length}
                                    pageSize={pageSize}
                                />
                            </>
                        )}
                    </section>

                    <section id="recent-alerts-table" className="scroll-mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-2.5">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">Recent Alerts</h3>
                                <p className="text-[11px] text-slate-500">Top 5 records &bull; Select record to view full alert breakdown</p>
                            </div>
                        </div>
                        {recent.alerts.length === 0 ? (
                            <p className="px-4 py-6 text-center text-xs text-slate-500">No alerts found for the selected filters.</p>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full table-fixed divide-y divide-slate-200 text-xs">
                                        <thead className="bg-slate-50">
                                            <tr>
                                                <th className="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Alert Type</th>
                                                <th className="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Severity</th>
                                                <th className="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Office</th>
                                                <th className="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Detected</th>
                                                <th className="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {visibleAlerts.map((row) => (
                                                <tr key={row.alerts_id} className="hover:bg-slate-50/50">
                                                    <td className="px-3 py-2 font-medium text-slate-800 break-words">{formatAlertType(row.alert_type)}</td>
                                                    <td className="px-3 py-2">{SeverityBadge(row.severity)}</td>
                                                    <td className="px-3 py-2 text-slate-600 break-words">{row.office?.name ?? 'N/A'}</td>
                                                    <td className="px-3 py-2 text-slate-500">{formatDate(row.detected_at)}</td>
                                                    <td className="px-3 py-2 text-right">
                                                        <Link
                                                            href={route('executive.alerts.show', row.alerts_id)}
                                                            className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-50 hover:border-red-300 transition shadow-xs"
                                                        >
                                                            Inspect &rarr;
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <SimplePager
                                    page={normalizedAlertPage}
                                    totalPages={totalAlertPages}
                                    onPageChange={setAlertPage}
                                    totalCount={recent.alerts.length}
                                    pageSize={pageSize}
                                />
                            </>
                        )}
                    </section>
                </div>

                <div id={exportSectionId} className="pdf-export-content hidden space-y-6 text-slate-900">
                    {/* Page 1: Risk & Alerts Summary Overview */}
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
                                Procurement Integrity & Risk Metrics
                            </h3>
                            <div className="grid grid-cols-4 gap-3">
                                <div className="rounded-md border border-slate-300 bg-white p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-slate-600">Total Anomalies</p>
                                    <p className="mt-1 text-2xl font-extrabold text-slate-900">{totals.anomaly_count}</p>
                                </div>
                                <div className="rounded-md border border-slate-300 bg-white p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-slate-600">Total Alerts</p>
                                    <p className="mt-1 text-2xl font-extrabold text-slate-900">{totals.alert_count}</p>
                                </div>
                                <div className="rounded-md border border-rose-300 bg-rose-50/70 p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-rose-900">Critical Anomalies</p>
                                    <p className="mt-1 text-2xl font-extrabold text-rose-800">{totals.high_severity_anomaly_count}</p>
                                </div>
                                <div className="rounded-md border border-amber-300 bg-amber-50/70 p-3 text-center">
                                    <p className="text-[10px] font-bold uppercase text-amber-900">Critical Alerts</p>
                                    <p className="mt-1 text-2xl font-extrabold text-amber-800">{totals.high_severity_alert_count}</p>
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <section className="rounded-lg border border-slate-300 bg-white p-3.5">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2.5">
                                    Incident Monthly Movement Trend
                                </h3>
                                {trendData.length === 0 ? (
                                    <CompactEmpty text="No incident trend data in current filter scope." />
                                ) : (
                                    <TrendGroupedBarChart
                                        data={trendData}
                                        firstLabel="Anomalies"
                                        secondLabel="Alerts"
                                        emptyText="No data in current filter scope"
                                    />
                                )}
                            </section>

                            <section className="rounded-lg border border-slate-300 bg-white p-3.5">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5 mb-2.5">
                                    Office Risk Concentration
                                </h3>
                                <HorizontalBarChart data={officeRisk} emptyText="No data in current filter scope" />
                            </section>
                        </div>
                    </GovDocumentPage>

                    {/* Continuation Pages: Anomaly Records */}
                    {anomalyExportPages.length === 0 ? (
                        <GovDocumentPage
                            metadata={{
                                ...baseMetadata,
                                title: 'ITEMIZED ANOMALY INCIDENT SCHEDULE',
                                moduleScope: 'Detected Algorithmic Anomalies & Irregularities',
                            }}
                            pageNumber={2}
                            totalPages={totalPdfPages}
                            showLetterhead={true}
                            showSignatories={alertExportPages.length === 0}
                            showAttestation={alertExportPages.length === 0}
                        >
                            <p className="py-8 text-center text-sm text-slate-500">No anomaly incidents recorded for the selected filter scope.</p>
                        </GovDocumentPage>
                    ) : (
                        anomalyExportPages.map((rows, pageIndex) => {
                            const pageNumber = 2 + pageIndex;
                            const isLast = pageNumber === totalPdfPages;

                            return (
                                <GovDocumentPage
                                    key={`risk-anomalies-export-${pageIndex + 1}`}
                                    metadata={{
                                        ...baseMetadata,
                                        title: 'ITEMIZED ANOMALY INCIDENT SCHEDULE',
                                        moduleScope: 'Detected Algorithmic Anomalies & Irregularities',
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
                                                    <th className="w-[30%] px-3 py-2 text-left border-r border-slate-300">Anomaly Classification</th>
                                                    <th className="w-[14%] px-3 py-2 text-center border-r border-slate-300">Severity</th>
                                                    <th className="w-[24%] px-3 py-2 text-left border-r border-slate-300">Office / Unit</th>
                                                    <th className="w-[18%] px-3 py-2 text-left border-r border-slate-300">Date Detected</th>
                                                    <th className="w-[14%] px-3 py-2 text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-200 bg-white">
                                                {rows.map((row) => (
                                                    <tr key={row.anomaly_id}>
                                                        <td className="px-3 py-2 align-top font-bold text-slate-900 border-r border-slate-200">{formatAnomalyType(row.anomaly_type)}</td>
                                                        <td className="px-3 py-2 align-top text-center border-r border-slate-200">{formatStatus(row.severity)}</td>
                                                        <td className="px-3 py-2 align-top text-slate-700 border-r border-slate-200">{row.office?.name ?? 'N/A'}</td>
                                                        <td className="px-3 py-2 align-top text-slate-600 border-r border-slate-200">{formatDate(row.detected_at)}</td>
                                                        <td className="px-3 py-2 align-top text-center text-slate-700">{formatStatus(row.status)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </GovDocumentPage>
                            );
                        })
                    )}

                    {/* Continuation Pages: Alert Records */}
                    {alertExportPages.length === 0 ? (
                        <GovDocumentPage
                            metadata={{
                                ...baseMetadata,
                                title: 'ITEMIZED SYSTEM ALERT LOG',
                                moduleScope: 'Procurement Workflow & Threshold Alerts',
                            }}
                            pageNumber={totalPdfPages}
                            totalPages={totalPdfPages}
                            showLetterhead={true}
                            showSignatories={true}
                            showAttestation={true}
                        >
                            <p className="py-8 text-center text-sm text-slate-500">No system alerts recorded for the selected filter scope.</p>
                        </GovDocumentPage>
                    ) : (
                        alertExportPages.map((rows, pageIndex) => {
                            const pageNumber = 2 + (anomalyExportPages.length > 0 ? anomalyExportPages.length : 1) + pageIndex;
                            const isLast = pageNumber === totalPdfPages;

                            return (
                                <GovDocumentPage
                                    key={`risk-alerts-export-${pageIndex + 1}`}
                                    metadata={{
                                        ...baseMetadata,
                                        title: 'ITEMIZED SYSTEM ALERT LOG',
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
                                                    <th className="w-[30%] px-3 py-2 text-left border-r border-slate-300">Alert Classification</th>
                                                    <th className="w-[14%] px-3 py-2 text-center border-r border-slate-300">Severity</th>
                                                    <th className="w-[24%] px-3 py-2 text-left border-r border-slate-300">Office / Unit</th>
                                                    <th className="w-[18%] px-3 py-2 text-left border-r border-slate-300">Date Detected</th>
                                                    <th className="w-[14%] px-3 py-2 text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-200 bg-white">
                                                {rows.map((row) => (
                                                    <tr key={row.alerts_id}>
                                                        <td className="px-3 py-2 align-top font-bold text-slate-900 border-r border-slate-200">{formatAlertType(row.alert_type)}</td>
                                                        <td className="px-3 py-2 align-top text-center border-r border-slate-200">{formatStatus(row.severity)}</td>
                                                        <td className="px-3 py-2 align-top text-slate-700 border-r border-slate-200">{row.office?.name ?? 'N/A'}</td>
                                                        <td className="px-3 py-2 align-top text-slate-600 border-r border-slate-200">{formatDate(row.detected_at)}</td>
                                                        <td className="px-3 py-2 align-top text-center text-slate-700">{formatStatus(row.status)}</td>
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

function SimplePager({
    page,
    totalPages,
    onPageChange,
    totalCount,
    pageSize,
}: {
    page: number;
    totalPages: number;
    onPageChange: (value: number) => void;
    totalCount: number;
    pageSize: number;
}) {
    const start = totalCount === 0 ? 0 : ((page - 1) * pageSize) + 1;
    const end = Math.min(page * pageSize, totalCount);

    if (totalPages <= 1 && totalCount <= pageSize) {
        return (
            <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50/50 px-4 py-2 text-xs text-slate-500">
                <span>Showing {start}-{end} of {totalCount} records</span>
            </div>
        );
    }

    return (
        <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50/50 px-4 py-2 text-xs text-slate-600">
            <span>Showing {start}-{end} of {totalCount} records</span>
            <div className="flex flex-wrap items-center justify-end gap-1.5">
                <button
                    type="button"
                    disabled={page <= 1}
                    onClick={() => onPageChange(page - 1)}
                    className="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                >
                    &larr; Prev
                </button>
                {Array.from({ length: totalPages }, (_, index) => index + 1).map((pageNumber) => (
                    <button
                        key={pageNumber}
                        type="button"
                        onClick={() => onPageChange(pageNumber)}
                        className={`h-6 min-w-6 rounded-md px-1.5 text-xs font-semibold transition ${
                            page === pageNumber
                                ? 'bg-red-600 text-white shadow-xs'
                                : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                        }`}
                    >
                        {pageNumber}
                    </button>
                ))}
                <button
                    type="button"
                    disabled={page >= totalPages}
                    onClick={() => onPageChange(page + 1)}
                    className="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                >
                    Next &rarr;
                </button>
            </div>
        </div>
    );
}

function mergeMonthlyTrends(
    anomalyTrend: Array<{ month: string; total: number }>,
    alertTrend: Array<{ month: string; total: number }>,
) {
    const monthMap = new Map<string, { anomaly: number; alert: number }>();

    anomalyTrend.forEach((row) => {
        const current = monthMap.get(row.month) ?? { anomaly: 0, alert: 0 };
        monthMap.set(row.month, { ...current, anomaly: row.total });
    });

    alertTrend.forEach((row) => {
        const current = monthMap.get(row.month) ?? { anomaly: 0, alert: 0 };
        monthMap.set(row.month, { ...current, alert: row.total });
    });

    return [...monthMap.entries()]
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([month, values]) => ({
            label: formatMonth(month),
            first: values.anomaly,
            second: values.alert,
        }));
}

function mergeStatus(anomalyRows: BreakdownRow[], alertRows: BreakdownRow[]) {
    const statusMap = new Map<string, { anomaly: number; alert: number }>();

    anomalyRows.forEach((row) => {
        const key = row.status || 'N/A';
        const existing = statusMap.get(key) ?? { anomaly: 0, alert: 0 };
        statusMap.set(key, { ...existing, anomaly: row.total });
    });

    alertRows.forEach((row) => {
        const key = row.status || 'N/A';
        const existing = statusMap.get(key) ?? { anomaly: 0, alert: 0 };
        statusMap.set(key, { ...existing, alert: row.total });
    });

    return [...statusMap.entries()]
        .map(([label, values]) => ({
            label: formatStatus(label),
            value: values.anomaly + values.alert,
        }))
        .sort((a, b) => b.value - a.value);
}

function mergeSeverity(
    anomalyRows: Array<{ label: string; total: number }>,
    alertRows: Array<{ label: string; total: number }>,
) {
    const severityMap = new Map<string, { anomaly: number; alert: number }>();

    anomalyRows.forEach((row) => {
        const key = row.label || 'N/A';
        const existing = severityMap.get(key) ?? { anomaly: 0, alert: 0 };
        severityMap.set(key, { ...existing, anomaly: row.total });
    });

    alertRows.forEach((row) => {
        const key = row.label || 'N/A';
        const existing = severityMap.get(key) ?? { anomaly: 0, alert: 0 };
        severityMap.set(key, { ...existing, alert: row.total });
    });

    return [...severityMap.entries()]
        .map(([label, values]) => ({
            label: formatStatus(label),
            value: values.anomaly + values.alert,
        }))
        .sort((a, b) => b.value - a.value);
}

function formatMonth(value: string): string {
    const date = new Date(`${value}-01T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-PH', { month: 'short', year: 'numeric' });
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'N/A';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatStatus(value: string): string {
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

function buildAnomalyGroups(rows: AnomalyRow[]): Array<{ key: string; label: string; office: string; source: string; count: number; representative: AnomalyRow }> {
    const grouped = new Map<string, { key: string; label: string; office: string; source: string; count: number; representative: AnomalyRow; timestamp: number }>();

    rows.forEach((row) => {
        const office = row.office?.name ?? 'Unassigned Office';
        const sourceType = row.reference_type ? row.reference_type.split('\\').pop() ?? 'Source' : 'Source';
        const itemPart = row.item_id ? `item-${row.item_id}` : 'item-na';
        const key = `${row.anomaly_type}|${office}|${sourceType}|${itemPart}`;
        const timestamp = toTimestamp(row.detected_at);

        const existing = grouped.get(key);

        if (!existing) {
            grouped.set(key, {
                key,
                label: formatAnomalyType(row.anomaly_type),
                office,
                source: sourceType,
                count: 1,
                representative: row,
                timestamp,
            });

            return;
        }

        existing.count += 1;

        if (timestamp >= existing.timestamp) {
            existing.timestamp = timestamp;
            existing.representative = row;
        }
    });

    return [...grouped.values()]
        .sort((a, b) => (b.count - a.count) || (b.timestamp - a.timestamp))
        .map(({ key, label, office, source, count, representative }) => ({ key, label, office, source, count, representative }));
}

function toTimestamp(value: string | null): number {
    if (!value) {
        return 0;
    }

    const parsed = new Date(value).getTime();
    return Number.isNaN(parsed) ? 0 : parsed;
}

function formatAlertType(value: string): string {
    return value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatAnomalySource(row: AnomalyRow): string {
    const reference = row.reference_type
        ? `${row.reference_type.split('\\').pop()} #${row.reference_id ?? 'N/A'}`
        : 'N/A';

    if (row.item_id) {
        return `${reference} | Item #${row.item_id}`;
    }

    return reference;
}

function formatDetectionVsExpected(row: AnomalyRow): string {
    const detected = toCompactNumber(row.detected_value);
    const expected = toCompactNumber(row.expected_value);
    const threshold = toCompactNumber(row.threshold_value);

    if (!detected && !expected && !threshold) {
        return 'No quantitative values available';
    }

    const expectedPart = expected ? `Expected ${expected}` : 'Expected N/A';
    const thresholdPart = threshold ? `Threshold ${threshold}` : null;

    return [
        detected ? `Detected ${detected}` : 'Detected N/A',
        expectedPart,
        thresholdPart,
    ].filter(Boolean).join(' | ');
}

function formatAlertSource(row: AlertRow): string {
    if (row.anomaly_id) {
        return `Anomaly #${row.anomaly_id}`;
    }

    if (row.purchase_order_id) {
        return `Purchase Order #${row.purchase_order_id}`;
    }

    if (row.supply_request_id) {
        return `Supply Request #${row.supply_request_id}`;
    }

    if (row.supplier_id) {
        return `Supplier #${row.supplier_id}`;
    }

    return 'General monitoring signal';
}

function buildAnomalyImpact(row: AnomalyRow): string {
    const detected = toCompactNumber(row.detected_value);
    const threshold = toCompactNumber(row.threshold_value);

    if (detected && threshold) {
        return `Detected value ${detected} against threshold ${threshold}; requires executive review.`;
    }

    if (detected) {
        return `Detected value ${detected}; requires executive verification.`;
    }

    return `${formatAnomalyType(row.anomaly_type)} flagged for executive monitoring.`;
}

function buildAlertImpact(row: AlertRow): string {
    if (row.alert_message && row.alert_message.trim().length > 0) {
        return row.alert_message;
    }

    return `${formatAlertType(row.alert_type)} requires attention due to ${formatStatus(row.severity)} severity.`;
}

function toCompactNumber(value: number | string | null | undefined): string | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const numeric = Number(value);

    if (Number.isNaN(numeric)) {
        return String(value);
    }

    return new Intl.NumberFormat('en-PH', {
        maximumFractionDigits: 0,
    }).format(numeric);
}

function StatusPill(value: string) {
    const normalized = value.toUpperCase();
    const tone = normalized.includes('OPEN')
        ? 'bg-rose-100 text-rose-700'
        : normalized.includes('REVIEW')
            ? 'bg-amber-100 text-amber-700'
            : 'bg-emerald-100 text-emerald-700';

    return <span className={`inline-flex cursor-default select-none rounded-full px-2 py-0.5 text-xs font-semibold ${tone}`}>{formatStatus(value)}</span>;
}

function SeverityBadge(value: string) {
    const normalized = value.toUpperCase();
    const tone = normalized === 'HIGH'
        ? 'bg-rose-100 text-rose-700'
        : normalized === 'MEDIUM'
            ? 'bg-amber-100 text-amber-700'
            : 'bg-slate-200 text-slate-700';

    return <span className={`inline-flex cursor-default select-none rounded-full px-2 py-0.5 text-xs font-semibold ${tone}`}>{formatStatus(value)}</span>;
}

function InfoCell({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-1 break-words text-sm text-slate-700">{value}</p>
        </div>
    );
}

function CompactEmpty({ text }: { text: string }) {
    return (
        <div className="rounded-xl border border-dashed border-red-200 bg-gradient-to-br from-red-50 to-white px-4 py-8 text-center text-sm text-slate-600">
            {text}
        </div>
    );
}


function MiniStat({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-1 text-lg font-bold text-slate-900">{value}</p>
        </div>
    );
}

function downloadCsv(rows: string[][], fileName: string): void {
    const csv = rows.map((row) => row.map((column) => `"${String(column).replaceAll('"', '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = fileName;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.URL.revokeObjectURL(url);
}

function serializeRiskFilters(filters: {
    office_id?: number | string | null;
    status?: string | null;
    year?: string | null;
    month?: string | null;
}): string {
    return JSON.stringify({
        office_id: String(filters.office_id ?? ''),
        status: filters.status ?? '',
        year: filters.year ?? '',
        month: filters.month ?? '',
    });
}

function chunkRows<T>(rows: T[], pageSize: number): T[][] {
    const pages: T[][] = [];
    for (let index = 0; index < rows.length; index += pageSize) {
        pages.push(rows.slice(index, index + pageSize));
    }
    return pages;
}
