import { DonutChart, ExecutiveChartCard, GroupedComparisonChart, HorizontalBarChart, TrendLineChart } from '@/Components/Executive/Charts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type OptionOffice = { id: number; name: string };
type BreakdownRow = { status: string; total: number };

type AnomalyRow = {
    anomaly_id: number;
    anomaly_type: string;
    severity: string;
    status: string;
    detected_at: string | null;
    office?: { id: number; name: string } | null;
};

type AlertRow = {
    alerts_id: number;
    alert_type: string;
    severity: string;
    status: string;
    detected_at: string | null;
    office?: { id: number; name: string } | null;
};

type Props = {
    filters: {
        office_id?: number | null;
        severity?: string | null;
        status?: string | null;
        anomaly_type?: string | null;
        from_date?: string | null;
        to_date?: string | null;
    };
    filter_options: {
        offices: OptionOffice[];
        severities: string[];
        statuses: string[];
        anomaly_types: string[];
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
    const [form, setForm] = useState({
        office_id: filters.office_id ? String(filters.office_id) : '',
        severity: filters.severity ?? '',
        status: filters.status ?? '',
        anomaly_type: filters.anomaly_type ?? '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
    });

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('executive.anomalies-monitoring'), form, { preserveState: true, replace: true });
    };

    const mergedTrend = mergeMonthlyTrends(monthly_trends.anomalies, monthly_trends.alerts);
    const severityComparison = mergeSeverity(anomaly_severity_breakdown, alert_severity_breakdown);
    const officeRisk = office_comparison
        .map((row) => ({
            label: row.office_name,
            value: row.anomaly_count + row.alert_count,
        }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 10);

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

    return (
        <AuthenticatedLayout header="Risk & Alerts">
            <Head title="Risk & Alerts" />

            <div className="space-y-6 py-8">
                <section className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-200 bg-white px-5 py-4 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <div>
                        <h2 className="text-2xl font-semibold text-slate-900">Risk & Alerts</h2>
                        <p className="text-sm text-slate-600">Risk command view</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button type="button" onClick={() => window.print()} className="rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                            Export PDF
                        </button>
                        <button type="button" onClick={exportCsv} className="rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            Export CSV
                        </button>
                    </div>
                </section>

                <section className="rounded-2xl border border-red-200 bg-white p-5 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <form onSubmit={onSubmit} className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-0 flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
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
                                value={form.severity}
                                onChange={(event) => setForm((current) => ({ ...current, severity: event.target.value }))}
                            >
                                <option value="">All severities</option>
                                {filter_options.severities.map((severity) => (
                                    <option key={severity} value={severity}>
                                        {severity}
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

                            <select
                                className="h-10 rounded-lg border-slate-300 text-sm"
                                value={form.anomaly_type}
                                onChange={(event) => setForm((current) => ({ ...current, anomaly_type: event.target.value }))}
                            >
                                <option value="">All anomaly types</option>
                                {filter_options.anomaly_types.map((anomalyType) => (
                                    <option key={anomalyType} value={anomalyType}>
                                        {formatAnomalyType(anomalyType)}
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
                        </div>

                        <div className="ml-auto flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => router.get(route('executive.anomalies-monitoring'))}
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

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Anomalies" value={totals.anomaly_count} tone="rose" />
                    <StatCard label="Alerts" value={totals.alert_count} tone="amber" />
                    <StatCard label="High Severity Anomalies" value={totals.high_severity_anomaly_count} tone="fuchsia" />
                    <StatCard label="High Severity Alerts" value={totals.high_severity_alert_count} tone="orange" />
                </div>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-12">
                    <ExecutiveChartCard title="Incident Trend" subtitle="Monthly risk movement" tone="critical">
                        <TrendLineChart
                            data={mergedTrend}
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                    <ExecutiveChartCard title="Anomaly Type Mix" subtitle="Category distribution" tone="critical">
                        <DonutChart
                            data={anomaly_type_breakdown.map((row) => ({ label: formatAnomalyType(row.label || 'N/A'), value: row.total }))}
                            centerLabel="Anomalies"
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-8">
                    <ExecutiveChartCard title="Severity Distribution" subtitle="Anomalies vs alerts" tone="default">
                        <GroupedComparisonChart
                            data={severityComparison}
                            firstLabel="Anomalies"
                            secondLabel="Alerts"
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard title="Office Risk Comparison" subtitle="Total incidents by office" tone="neutral">
                        <HorizontalBarChart
                            data={officeRisk}
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard title="Status Breakdown" subtitle="Current resolution flow" tone="default">
                        <GroupedComparisonChart
                            data={mergeStatus(status_breakdown.anomalies, status_breakdown.alerts)}
                            firstLabel="Anomalies"
                            secondLabel="Alerts"
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <TableSection
                        title="Recent Anomalies"
                        headers={['Type', 'Severity', 'Status', 'Office', 'Detected']}
                        rows={recent.anomalies.map((row) => [
                            formatAnomalyType(row.anomaly_type),
                            row.severity,
                            row.status,
                            row.office?.name ?? 'N/A',
                            row.detected_at ? new Date(row.detected_at).toLocaleDateString() : 'N/A',
                        ])}
                        emptyText="No anomalies found for the selected filters."
                        subtitle="Latest records"
                    />

                    <TableSection
                        title="Recent Alerts"
                        headers={['Type', 'Severity', 'Status', 'Office', 'Detected']}
                        rows={recent.alerts.map((row) => [
                            row.alert_type,
                            row.severity,
                            row.status,
                            row.office?.name ?? 'N/A',
                            row.detected_at ? new Date(row.detected_at).toLocaleDateString() : 'N/A',
                        ])}
                        emptyText="No alerts found for the selected filters."
                        subtitle="Latest records"
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function mergeMonthlyTrends(
    anomalyTrend: Array<{ month: string; total: number }>,
    alertTrend: Array<{ month: string; total: number }>,
) {
    const monthMap = new Map<string, number>();

    anomalyTrend.forEach((row) => {
        monthMap.set(row.month, (monthMap.get(row.month) ?? 0) + row.total);
    });

    alertTrend.forEach((row) => {
        monthMap.set(row.month, (monthMap.get(row.month) ?? 0) + row.total);
    });

    return [...monthMap.entries()]
        .sort(([monthA], [monthB]) => monthA.localeCompare(monthB))
        .map(([month, total]) => ({
            label: formatMonth(month),
            value: total,
        }));
}

function formatMonth(value: string): string {
    const date = new Date(`${value}-01T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-PH', { month: 'short', year: 'numeric' });
}

function formatAnomalyType(value: string): string {
    if (value === 'PRICE_Z_SCORE') {
        return 'Procurement Price Anomaly';
    }

    return value;
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
            label,
            first: values.anomaly,
            second: values.alert,
        }))
        .sort((a, b) => (b.first + b.second) - (a.first + a.second));
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
            label,
            first: values.anomaly,
            second: values.alert,
        }))
        .sort((a, b) => (b.first + b.second) - (a.first + a.second));
}

function StatCard({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone: 'rose' | 'amber' | 'fuchsia' | 'orange';
}) {
    const toneClass: Record<typeof tone, string> = {
        rose: 'border-red-300 bg-gradient-to-b from-red-100 to-red-50 text-red-900 shadow-[0_8px_20px_rgba(185,28,28,0.18)]',
        amber: 'border-orange-300 bg-gradient-to-b from-orange-100 to-orange-50 text-orange-900 shadow-[0_8px_20px_rgba(234,88,12,0.16)]',
        fuchsia: 'border-rose-300 bg-gradient-to-b from-rose-100 to-rose-50 text-rose-900 shadow-[0_8px_20px_rgba(225,29,72,0.16)]',
        orange: 'border-amber-300 bg-gradient-to-b from-amber-100 to-amber-50 text-amber-900 shadow-[0_8px_20px_rgba(217,119,6,0.16)]',
    };

    return (
        <div className={`rounded-2xl border p-6 shadow-sm ${toneClass[tone]}`}>
            <p className="text-sm font-semibold uppercase tracking-wide">{label}</p>
            <p className="mt-2 text-4xl font-bold">{value}</p>
        </div>
    );
}

function TableSection({
    title,
    headers,
    rows,
    emptyText,
    subtitle,
}: {
    title: string;
    headers: string[];
    rows: string[][];
    emptyText: string;
    subtitle?: string;
}) {
    return (
        <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
            <div className="border-b border-slate-200 px-6 py-5">
                <h3 className="text-xl font-semibold text-slate-800">{title}</h3>
                {subtitle ? <p className="mt-1 text-sm text-slate-500">{subtitle}</p> : null}
            </div>
            {rows.length === 0 ? (
                <p className="px-6 py-8 text-sm text-slate-500">{emptyText}</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                {headers.map((header) => (
                                    <th key={header} className="px-4 py-2 text-left font-semibold text-slate-600">
                                        {header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.map((columns, rowIndex) => (
                                <tr key={`${title}-${rowIndex}`}>
                                    {columns.map((column, columnIndex) => (
                                        <td key={`${title}-${rowIndex}-${columnIndex}`} className="px-4 py-2 text-slate-700">
                                            {column}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
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
