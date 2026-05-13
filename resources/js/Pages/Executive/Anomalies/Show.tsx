import { ExecutiveChartCard, GroupedComparisonChart, TrendLineChart } from '@/Components/Executive/Charts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';

type Props = {
    anomaly: {
        anomaly_id: number;
        anomaly_type: string;
        severity: string;
        status: string;
        detected_value?: string | null;
        expected_value?: string | null;
        threshold_value?: string | null;
        z_score?: string | null;
        detection_method?: string | null;
        remarks?: string | null;
        alerts?: Array<{ alerts_id: number; status: string; severity: string }>;
    };
    evidence?: {
        type: string;
        title: string;
        narrative: string;
        metrics: {
            detected_value?: number | null;
            expected_value?: number | null;
            threshold_value?: number | null;
            z_score?: number | null;
            percent_delta?: number | null;
        };
        series: Array<{ label: string; value: number; is_detected?: boolean }>;
        comparison: Array<{ label: string; first: number; second: number }>;
    };
};

export default function ExecutiveAnomalyShow({ anomaly, evidence }: Props) {
    const trendData = (evidence?.series ?? []).map((row) => ({ label: row.label, value: row.value }));
    const detectedMetric = evidence?.metrics.detected_value ?? parseOptionalNumber(anomaly.detected_value);
    const expectedMetric = evidence?.metrics.expected_value ?? parseOptionalNumber(anomaly.expected_value);
    const thresholdMetric = evidence?.metrics.threshold_value ?? parseOptionalNumber(anomaly.threshold_value);
    const severityTone = getSeverityTone(anomaly.severity);
    const severityText = getSeverityText(anomaly.severity);
    const itemLabel = getItemLabel(anomaly.anomaly_type);
    const comparisonSummary = getComparisonSummary(detectedMetric, expectedMetric, thresholdMetric);
    const infographicReason = getInfographicReason(anomaly.severity, detectedMetric, expectedMetric, thresholdMetric, itemLabel);
    const comparisonData = (evidence?.comparison ?? []).map((item) => ({ ...item, label: `${itemLabel}: actual vs normal` }));

    return (
        <AuthenticatedLayout header="Risk & Alerts">
            <Head title="Anomaly Detail" />
            <div className="space-y-6 py-8">
                <section className={`flex flex-wrap items-center justify-between gap-4 rounded-2xl border px-5 py-5 shadow-[0_8px_24px_rgba(15,23,42,0.08)] ${severityTone.hero}`}>
                    <div>
                        <p className="text-sm font-semibold uppercase tracking-wide text-red-700">Risk overview</p>
                        <h2 className="mt-1 text-2xl font-semibold text-slate-900">{formatAnomalyType(anomaly.anomaly_type)}</h2>
                        <p className="mt-1 text-sm text-slate-600">{infographicReason}</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <SeverityBadge value={anomaly.severity} label={`${severityText} Alert`} />
                        <Link
                            href={route('executive.anomalies-monitoring')}
                            className="inline-flex items-center rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                        >
                            Return to Risk &amp; Alerts
                        </Link>
                    </div>
                </section>

                <section className="rounded-2xl border border-indigo-200 bg-indigo-50 px-5 py-4 shadow-sm">
                    <div className="flex gap-3">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-800">i</div>
                        <div>
                            <p className="text-sm font-semibold text-indigo-950">Viewing mode</p>
                            <p className="mt-0.5 text-sm text-indigo-900">This page only explains why the item was flagged; no status or record changes are made here.</p>
                        </div>
                    </div>
                </section>

                <div className="grid gap-4 lg:grid-cols-3">
                    <InfographicCard eyebrow="Alert Level" title={severityText} body={infographicReason} tone={severityTone.card} icon={severityTone.icon} />
                    <InfographicCard eyebrow="Actual Count" title={`${formatMetric(detectedMetric)} ${itemLabel}`} body="What the system found in the procurement records." tone="critical" icon="4" />
                    <InfographicCard eyebrow="Why Flagged" title={comparisonSummary.title} body={comparisonSummary.body} tone="default" icon=">" />
                </div>

                <div className="grid gap-6 xl:grid-cols-12">
                    <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)] xl:col-span-7">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-red-700">Anomaly #{anomaly.anomaly_id}</p>
                            <h3 className="mt-1 text-lg font-semibold text-slate-900">Alert Summary</h3>
                            <p className="mt-0.5 text-sm text-slate-600">Plain-language context for this alert.</p>
                        </div>
                        <div className="grid gap-3 p-5 text-sm md:grid-cols-2">
                            <DetailItem label="Alert Type" value={formatAnomalyType(anomaly.anomaly_type)} />
                            <DetailItem label="Alert Level" value={<SeverityBadge value={anomaly.severity} label={`${severityText} Alert`} />} />
                            <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 md:col-span-2">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Why it matters</p>
                                <p className="mt-1 leading-6 text-slate-800">{anomaly.remarks ?? '-'}</p>
                            </div>
                            {isPriceAnomaly(anomaly.anomaly_type) ? (
                                <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 md:col-span-2">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-amber-700">Source Note</p>
                                    <p className="mt-1 leading-6 text-amber-900">Price anomalies are derived from purchase order item pricing, with office used only for executive grouping context.</p>
                                </div>
                            ) : null}
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)] xl:col-span-5">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <h3 className="text-lg font-semibold text-slate-900">Linked Alerts</h3>
                            <p className="mt-0.5 text-sm text-slate-600">Related alerts connected to this anomaly.</p>
                        </div>
                        <div className="p-5">
                            {anomaly.alerts && anomaly.alerts.length > 0 ? (
                                <div className="grid gap-2">
                                    {anomaly.alerts.slice(0, 4).map((alert) => (
                                        <Link
                                            key={alert.alerts_id}
                                            href={route('executive.alerts.show', alert.alerts_id)}
                                            className="flex items-center justify-between rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm font-semibold text-red-800 hover:bg-red-100"
                                        >
                                            <span>Alert #{alert.alerts_id}</span>
                                            <span className="text-xs uppercase tracking-wide text-red-600">{alert.severity}</span>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <p className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-500">No linked alerts for this anomaly.</p>
                            )}
                        </div>
                    </section>
                </div>

                {evidence ? (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <ExecutiveChartCard title={evidence.title} subtitle={evidence.narrative} tone="warning">
                            <div className="grid gap-2 text-sm sm:grid-cols-2">
                                <MetricCell label={`Actual ${itemLabel}`} value={formatMetric(evidence.metrics.detected_value)} tone="critical" />
                                <MetricCell label="Normal Reference" value={formatMetric(evidence.metrics.expected_value)} />
                                <MetricCell label="Review Point" value={formatMetric(evidence.metrics.threshold_value)} tone="warning" />
                            </div>
                        </ExecutiveChartCard>

                            <ExecutiveChartCard title={`${itemLabel} Compared with Normal Pattern`} subtitle={comparisonSummary.body} tone="neutral">
                                <GroupedComparisonChart
                                    data={comparisonData}
                                    firstLabel={`Actual ${itemLabel}`}
                                    secondLabel="Normal Reference"
                                    emptyText="No comparison evidence available"
                                />
                            </ExecutiveChartCard>

                            <div className="lg:col-span-2">
                                <ExecutiveChartCard title="Historical Series" subtitle="Prior item values and latest detected point" tone="default">
                                    <TrendLineChart
                                        data={trendData}
                                        emptyText="No historical series available for this anomaly"
                                        valueFormatter={(value) => value.toLocaleString('en-PH', { maximumFractionDigits: 4 })}
                                    />
                                </ExecutiveChartCard>
                            </div>
                    </div>
                ) : null}

            </div>
        </AuthenticatedLayout>
    );
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

    if (aliases[normalized]) {
        return aliases[normalized];
    }

    return value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function isPriceAnomaly(value: string): boolean {
    const normalized = value.toUpperCase();

    return normalized === 'PRICE_Z_SCORE' || normalized === 'PRICE_ANOMALY' || normalized === 'Z_SCORE_PRICE_ANOMALY';
}

function getItemLabel(value: string): string {
    const normalized = value.toUpperCase();

    if (normalized.includes('REQUEST')) {
        return 'Requests';
    }

    if (normalized.includes('PRICE')) {
        return 'Price';
    }

    if (normalized.includes('QUANTITY')) {
        return 'Quantity';
    }

    if (normalized.includes('FREQUENCY')) {
        return 'Occurrences';
    }

    return 'Records';
}

function MetricCell({ label, value, tone = 'default' }: { label: string; value: string; tone?: 'default' | 'critical' | 'warning' }) {
    const toneClass = {
        default: 'border-slate-200 bg-white text-slate-900',
        critical: 'border-red-200 bg-red-50 text-red-900',
        warning: 'border-amber-200 bg-amber-50 text-amber-900',
    }[tone];

    return (
        <div className={`rounded-xl border px-4 py-3 shadow-sm ${toneClass}`}>
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-lg font-bold">{value}</p>
        </div>
    );
}

function InfographicCard({ eyebrow, title, body, tone, icon }: { eyebrow: string; title: string; body: string; tone: 'default' | 'critical' | 'warning'; icon: string }) {
    const toneClass = {
        default: 'border-slate-200 bg-white text-slate-900',
        critical: 'border-red-200 bg-red-50 text-red-950',
        warning: 'border-amber-200 bg-amber-50 text-amber-950',
    }[tone];

    const iconClass = {
        default: 'bg-slate-100 text-slate-700',
        critical: 'bg-red-100 text-red-700',
        warning: 'bg-amber-100 text-amber-700',
    }[tone];

    return (
        <section className={`rounded-2xl border p-5 shadow-sm ${toneClass}`}>
            <div className="flex items-start gap-4">
                <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-2xl font-black ${iconClass}`}>{icon}</div>
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{eyebrow}</p>
                    <h3 className="mt-1 text-2xl font-black uppercase tracking-wide">{title}</h3>
                    <p className="mt-2 text-sm leading-6 text-slate-700">{body}</p>
                </div>
            </div>
        </section>
    );
}

function DetailItem({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <div className="mt-1 font-semibold text-slate-900">{value}</div>
        </div>
    );
}

function SeverityBadge({ value, label }: { value: string; label?: string }) {
    const normalized = value.toUpperCase();
    const className = normalized === 'HIGH'
        ? 'border-red-300 bg-red-100 text-red-800'
        : normalized === 'MEDIUM'
            ? 'border-amber-300 bg-amber-100 text-amber-800'
            : 'border-slate-300 bg-slate-100 text-slate-700';

    return <span className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide ${className}`}>{label ?? formatStatusLabel(value)}</span>;
}

function formatStatusLabel(value: string): string {
    return value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatMetric(value?: number | null): string {
    if (value === null || value === undefined) {
        return 'N/A';
    }

    return Number(value).toLocaleString('en-PH', { maximumFractionDigits: 4 });
}

function parseOptionalNumber(value?: string | null): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
}

function getSeverityTone(value: string): { card: 'default' | 'critical' | 'warning'; hero: string; icon: string } {
    const normalized = value.toUpperCase();

    if (normalized === 'HIGH') {
        return { card: 'critical', hero: 'border-red-200 bg-gradient-to-r from-red-50 to-white', icon: '!' };
    }

    if (normalized === 'MEDIUM') {
        return { card: 'warning', hero: 'border-amber-200 bg-gradient-to-r from-amber-50 to-white', icon: '!' };
    }

    return { card: 'default', hero: 'border-slate-200 bg-white', icon: 'i' };
}

function getSeverityText(value: string): string {
    const normalized = value.toUpperCase();

    if (normalized === 'HIGH') {
        return 'High Level';
    }

    if (normalized === 'MEDIUM') {
        return 'Medium Level';
    }

    return `${formatStatusLabel(value)} Level`;
}

function getComparisonSummary(detected?: number | null, expected?: number | null, threshold?: number | null): { title: string; body: string } {
    if (detected === null || detected === undefined) {
        return { title: 'No actual value', body: 'There is no actual count available for this record.' };
    }

    if (expected !== null && expected !== undefined) {
        return detected > expected
            ? { title: 'Above normal', body: `Normal is ${formatMetric(expected)}. Actual is higher.` }
            : { title: 'Within reference', body: `Normal is ${formatMetric(expected)}. Check the note for why it was flagged.` };
    }

    if (threshold !== null && threshold !== undefined) {
        return detected > threshold
            ? { title: 'Past review point', body: `Review point is ${formatMetric(threshold)}. Actual went past it.` }
            : { title: 'Near review point', body: `Review point is ${formatMetric(threshold)}. Actual is close enough to review.` };
    }

    return { title: 'Context needed', body: 'No normal reference or review point is available for this alert.' };
}

function getInfographicReason(severity: string, detected?: number | null, expected?: number | null, threshold?: number | null, itemLabel = 'Records'): string {
    const actual = formatMetric(detected);

    if (threshold !== null && threshold !== undefined) {
        return `${getSeverityText(severity)} because the system found ${actual} ${itemLabel} while the review point is ${formatMetric(threshold)}.`;
    }

    if (expected !== null && expected !== undefined) {
        return `${getSeverityText(severity)} because the system found ${actual} ${itemLabel} while normal is ${formatMetric(expected)}.`;
    }

    return `${getSeverityText(severity)} alert based on the flagged ${itemLabel.toLowerCase()} pattern.`;
}





