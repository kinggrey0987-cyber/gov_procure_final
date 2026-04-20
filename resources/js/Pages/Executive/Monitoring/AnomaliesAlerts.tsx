import { ExecutiveChartCard, HorizontalBarChart, TrendGroupedBarChart } from '@/Components/Executive/Charts';
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
    const anomalyGroups = buildAnomalyGroups(recent.anomalies).slice(0, 5);
    const representativeAnomalies = anomalyGroups.map((group) => group.representative);

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
                        <p className="text-sm text-slate-600">Executive risk signal tracking and impact evidence.</p>
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
                    <StatCard label="Anomalies" value={totals.anomaly_count} />
                    <StatCard label="Alerts" value={totals.alert_count} />
                    <StatCard label="High Severity Anomalies" value={totals.high_severity_anomaly_count} />
                    <StatCard label="High Severity Alerts" value={totals.high_severity_alert_count} />
                </div>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-7">
                        <ExecutiveChartCard title="Incident Trend" subtitle="Anomaly and alert movement" tone="critical">
                            {trendData.length === 0 ? (
                                <CompactEmpty text="No incident trend data in current filter scope." />
                            ) : trendData.length === 1 ? (
                                <div className="grid gap-3 md:grid-cols-3">
                                    <MiniStat label="Month" value={trendData[0].label} />
                                    <MiniStat label="Anomalies" value={trendData[0].first} />
                                    <MiniStat label="Alerts" value={trendData[0].second} />
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

                    <div className="xl:col-span-5">
                        <ExecutiveChartCard title="Office Risk Ranking" subtitle="Total incidents by office" tone="neutral">
                            <HorizontalBarChart data={officeRisk} emptyText="No data in current filter scope" />
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                        <ExecutiveChartCard title="Top Anomaly Categories" subtitle="Most frequent anomaly types" tone="warning">
                            <HorizontalBarChart data={anomalyTypeMix} emptyText="No data in current filter scope" />
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                        <ExecutiveChartCard title="Severity and Status Snapshot" subtitle="Compact comparison" tone="default">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">Severity</p>
                                    <HorizontalBarChart data={severityMix} emptyText="No data in current filter scope" />
                                </div>
                                <div>
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">Status</p>
                                    <HorizontalBarChart data={statusMix} emptyText="No data in current filter scope" />
                                </div>
                            </div>
                        </ExecutiveChartCard>
                    </div>
                </div>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h3 className="text-lg font-semibold text-slate-900">Recent Anomalies</h3>
                        <p className="mt-1 text-sm text-slate-600">Distinct anomaly groups first, with representative records for executive triage.</p>
                    </div>
                    {recent.anomalies.length === 0 ? (
                        <p className="px-5 py-6 text-sm text-slate-500">No anomalies found for the selected filters.</p>
                    ) : (
                        <div className="space-y-4 px-4 py-4">
                            <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                {anomalyGroups.map((group) => (
                                    <article key={group.key} className="rounded-lg border border-red-100 bg-red-50/50 px-3 py-2">
                                        <p className="text-sm font-semibold text-slate-900">{group.label}</p>
                                        <p className="mt-1 text-xs text-slate-600">{group.office} • {group.source}</p>
                                        <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-red-700">{group.count} related record{group.count > 1 ? 's' : ''}</p>
                                    </article>
                                ))}
                            </div>

                            {representativeAnomalies.map((row) => (
                                <article key={row.anomaly_id} className="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <p className="text-sm font-semibold text-slate-900">{formatAnomalyType(row.anomaly_type)}</p>
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            {SeverityBadge(row.severity)}
                                            {StatusPill(row.status)}
                                        </div>
                                    </div>

                                    <div className="mt-3 grid gap-3 text-sm md:grid-cols-2">
                                        <InfoCell label="Office" value={row.office?.name ?? 'N/A'} />
                                        <InfoCell label="Source Context" value={formatAnomalySource(row)} />
                                        <InfoCell label="Detected vs Expected / Threshold" value={formatDetectionVsExpected(row)} />
                                        <InfoCell label="Detected Date" value={formatDate(row.detected_at)} />
                                    </div>

                                    <div className="mt-3 rounded-lg border border-red-100 bg-white px-3 py-2">
                                        <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">Impact Summary</p>
                                        <p className="mt-1 text-sm text-slate-700">{buildAnomalyImpact(row)}</p>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </section>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h3 className="text-lg font-semibold text-slate-900">Recent Alerts</h3>
                        <p className="mt-1 text-sm text-slate-600">Latest alerts with source context and executive relevance.</p>
                    </div>
                    {recent.alerts.length === 0 ? (
                        <p className="px-5 py-6 text-sm text-slate-500">No alerts found for the selected filters.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Alert Type</th>
                                        <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Severity</th>
                                        <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Office</th>
                                        <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Source Context</th>
                                        <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Impact Summary</th>
                                        <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Detected</th>
                                        <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {recent.alerts.slice(0, 5).map((row) => (
                                        <tr key={row.alerts_id}>
                                            <td className="px-4 py-2 font-medium text-slate-800">{formatAlertType(row.alert_type)}</td>
                                            <td className="px-4 py-2">{SeverityBadge(row.severity)}</td>
                                            <td className="px-4 py-2 text-slate-700">{row.office?.name ?? 'N/A'}</td>
                                            <td className="px-4 py-2 text-slate-700">{formatAlertSource(row)}</td>
                                            <td className="px-4 py-2 text-slate-700">{buildAlertImpact(row)}</td>
                                            <td className="px-4 py-2 text-slate-700">{formatDate(row.detected_at)}</td>
                                            <td className="px-4 py-2">{StatusPill(row.status)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
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
        maximumFractionDigits: 2,
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

function StatCard({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-2xl border border-red-200 bg-gradient-to-r from-red-50 to-white p-6 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
            <p className="text-sm font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-2 text-4xl font-bold text-red-700">{value}</p>
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
