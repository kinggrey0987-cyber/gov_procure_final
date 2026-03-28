import { ExecutiveChartCard, GroupedComparisonChart, HorizontalBarChart } from '@/Components/Executive/Charts';
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
    alert_severity_breakdown: Array<{ label: string; total: number }>;
    office_comparison: Array<{
        office_id: number;
        office_name: string;
        anomaly_count: number;
        alert_count: number;
    }>;
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
    alert_severity_breakdown,
    office_comparison,
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

    const anomalySeveritySignals = [
        { label: 'High Severity Anomalies', value: totals.high_severity_anomaly_count },
        { label: 'High Severity Alerts', value: totals.high_severity_alert_count },
    ];

    return (
        <AuthenticatedLayout header="Anomalies and Alerts Monitoring">
            <Head title="Anomalies and Alerts Monitoring" />

            <div className="space-y-6 py-8">
                <section className="overflow-hidden rounded-2xl border border-red-200 bg-white shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-red-200 bg-gradient-to-r from-red-700 via-red-600 to-red-500 px-6 py-4 text-white">
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-100">Executive Alert Center</p>
                        <p className="text-lg font-semibold">Procurement Alerts and Irregularities</p>
                        <p className="text-sm text-red-100">System-detected procurement anomalies requiring executive attention.</p>
                    </div>
                    <div className="p-6">
                    <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 className="text-xl font-semibold text-slate-900">Monitoring Filter Bar</h3>
                            <p className="mt-1 text-sm text-slate-600">Focus risk intelligence by office, severity, status, anomaly type, and detection window.</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => router.get(route('executive.anomalies-monitoring'))}
                            className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 transition hover:border-red-500 hover:bg-red-50"
                        >
                            Reset
                        </button>
                    </div>

                    <form onSubmit={onSubmit} className="grid gap-3 md:grid-cols-6">
                        <select
                            className="rounded-lg border-slate-300 text-sm"
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
                            className="rounded-lg border-slate-300 text-sm"
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
                            className="rounded-lg border-slate-300 text-sm"
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
                            className="rounded-lg border-slate-300 text-sm"
                            value={form.anomaly_type}
                            onChange={(event) => setForm((current) => ({ ...current, anomaly_type: event.target.value }))}
                        >
                            <option value="">All anomaly types</option>
                            {filter_options.anomaly_types.map((anomalyType) => (
                                <option key={anomalyType} value={anomalyType}>
                                    {anomalyType}
                                </option>
                            ))}
                        </select>

                        <input
                            type="date"
                            className="rounded-lg border-slate-300 text-sm"
                            value={form.from_date}
                            onChange={(event) => setForm((current) => ({ ...current, from_date: event.target.value }))}
                        />

                        <div className="flex gap-2">
                            <input
                                type="date"
                                className="w-full rounded-lg border-slate-300 text-sm"
                                value={form.to_date}
                                onChange={(event) => setForm((current) => ({ ...current, to_date: event.target.value }))}
                            />
                            <button type="submit" className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                                Apply
                            </button>
                        </div>
                    </form>
                    </div>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Anomalies" value={totals.anomaly_count} tone="rose" />
                    <StatCard label="Alerts" value={totals.alert_count} tone="amber" />
                    <StatCard label="High Severity Anomalies" value={totals.high_severity_anomaly_count} tone="fuchsia" />
                    <StatCard label="High Severity Alerts" value={totals.high_severity_alert_count} tone="orange" />
                </div>

                <div className="rounded-2xl border border-red-200 bg-red-50/60 px-5 py-4">
                    <p className="text-xs font-semibold uppercase tracking-[0.16em] text-red-700">Interpretation Guide</p>
                    <p className="mt-1 text-sm text-slate-700">Prioritize offices where high-severity totals remain elevated while status movements are stalled in OPEN or IN_REVIEW states.</p>
                </div>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-8">
                    <ExecutiveChartCard title="Anomaly Type Distribution" subtitle="Most frequent anomaly categories in the current filter scope" eyebrow="Primary Risk Mix" tone="critical">
                        <HorizontalBarChart
                            data={anomaly_type_breakdown.map((row) => ({ label: row.label || 'N/A', value: row.total }))}
                            emptyText="No anomaly type distribution available for the selected filters."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                    <ExecutiveChartCard title="Critical Severity Snapshot" subtitle="Concentrated severe incidents requiring executive action" eyebrow="Immediate Attention" tone="critical">
                        <HorizontalBarChart
                            data={anomalySeveritySignals.map((row) => ({ label: row.label, value: row.value }))}
                            emptyText="No high-severity anomaly or alert records for the selected filters."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-5">
                    <ExecutiveChartCard title="Alert Severity Distribution" subtitle="Current spread of alert severity levels" eyebrow="Alert Pressure" tone="warning">
                        <HorizontalBarChart
                            data={alert_severity_breakdown.map((row) => ({ label: row.label || 'N/A', value: row.total }))}
                            emptyText="No alert severity data available for the selected filters."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-7">
                    <ExecutiveChartCard title="Status Breakdown" subtitle="Anomaly and alert statuses side-by-side" eyebrow="Resolution Flow" tone="default">
                        <GroupedComparisonChart
                            data={mergeStatus(status_breakdown.anomalies, status_breakdown.alerts)}
                            firstLabel="Anomalies"
                            secondLabel="Alerts"
                            emptyText="No status breakdown data available for the selected filters."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-12">
                    <ExecutiveChartCard title="Office-Based Anomaly and Alert Comparison" subtitle="Office-level anomaly and alert monitoring intensity" eyebrow="Cross-Office Risk" tone="default">
                        <GroupedComparisonChart
                            data={office_comparison.map((row) => ({
                                label: row.office_name,
                                first: row.anomaly_count,
                                second: row.alert_count,
                            }))}
                            firstLabel="Anomalies"
                            secondLabel="Alerts"
                            emptyText="No office-level monitoring data available for the selected filters."
                        />
                    </ExecutiveChartCard>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <TableSection
                        title="Recent Anomalies"
                        headers={['Type', 'Severity', 'Status', 'Office', 'Detected']}
                        rows={recent.anomalies.map((row) => [
                            row.anomaly_type,
                            row.severity,
                            row.status,
                            row.office?.name ?? 'N/A',
                            row.detected_at ? new Date(row.detected_at).toLocaleDateString() : 'N/A',
                        ])}
                        emptyText="No anomalies found for the selected filters."
                        subtitle="Secondary detail list after summary visuals."
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
                        subtitle="Secondary detail list after summary visuals."
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
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
