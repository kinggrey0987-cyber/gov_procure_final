import { ExecutiveChartCard, GroupedComparisonChart, TrendLineChart } from '@/Components/Executive/Charts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
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
    const form = useForm({ status: '', remarks: '' });
    const trendData = (evidence?.series ?? []).map((row) => ({ label: row.label, value: row.value }));
    const detectedMetric = evidence?.metrics.detected_value ?? parseOptionalNumber(anomaly.detected_value);
    const expectedMetric = evidence?.metrics.expected_value ?? parseOptionalNumber(anomaly.expected_value);
    const thresholdMetric = evidence?.metrics.threshold_value ?? parseOptionalNumber(anomaly.threshold_value);
    const zScoreMetric = evidence?.metrics.z_score ?? parseOptionalNumber(anomaly.z_score);
    const percentDelta = evidence?.metrics.percent_delta ?? calculatePercentDelta(detectedMetric, expectedMetric);

    return (
        <AuthenticatedLayout header="Risk & Alerts">
            <Head title="Anomaly Detail" />
            <div className="space-y-6 py-8">
                <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-red-200 bg-white px-5 py-4 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <div>
                        <p className="text-sm font-semibold uppercase tracking-wide text-red-700">Executive risk review</p>
                        <h2 className="mt-1 text-2xl font-semibold text-slate-900">{formatAnomalyType(anomaly.anomaly_type)}</h2>
                        <p className="mt-1 text-sm text-slate-600">Detailed anomaly evidence, baseline comparison, and executive disposition controls.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <SeverityBadge value={anomaly.severity} />
                        <StatusBadge value={anomaly.status} />
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
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-800">EO</div>
                        <div>
                            <p className="text-sm font-semibold text-indigo-950">Executive review only</p>
                            <p className="mt-0.5 text-sm text-indigo-900">Only the Executive Officer can open this detail page and update anomaly status.</p>
                        </div>
                    </div>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <MetricCell label="Detected" value={formatMetric(detectedMetric)} tone="critical" />
                    <MetricCell label="Expected" value={formatMetric(expectedMetric)} />
                    <MetricCell label="Threshold" value={formatMetric(thresholdMetric)} />
                    <MetricCell label="Z-Score" value={formatMetric(zScoreMetric)} />
                    <MetricCell
                        label="% Delta"
                        value={percentDelta === null ? 'N/A' : `${percentDelta >= 0 ? '+' : ''}${percentDelta.toFixed(2)}%`}
                        tone={percentDelta !== null && percentDelta > 0 ? 'warning' : 'default'}
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-12">
                    <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)] xl:col-span-7">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-red-700">Anomaly #{anomaly.anomaly_id}</p>
                            <h3 className="mt-1 text-lg font-semibold text-slate-900">Signal Summary</h3>
                            <p className="mt-0.5 text-sm text-slate-600">Core classification, detection method, and narrative context for this record.</p>
                        </div>
                        <div className="grid gap-3 p-5 text-sm md:grid-cols-2">
                            <DetailItem label="Type" value={formatAnomalyType(anomaly.anomaly_type)} />
                            <DetailItem label="Detection Method" value={anomaly.detection_method ?? '-'} />
                            <DetailItem label="Severity" value={<SeverityBadge value={anomaly.severity} />} />
                            <DetailItem label="Status" value={<StatusBadge value={anomaly.status} />} />
                            <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 md:col-span-2">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Remarks</p>
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
                                <MetricCell label="Detected" value={formatMetric(evidence.metrics.detected_value)} tone="critical" />
                                <MetricCell label="Expected" value={formatMetric(evidence.metrics.expected_value)} />
                                <MetricCell label="Threshold" value={formatMetric(evidence.metrics.threshold_value)} />
                                <MetricCell label="Z-Score" value={formatMetric(evidence.metrics.z_score)} />
                                <MetricCell
                                    label="% Delta"
                                    value={
                                        evidence.metrics.percent_delta === null || evidence.metrics.percent_delta === undefined
                                            ? 'N/A'
                                            : `${evidence.metrics.percent_delta >= 0 ? '+' : ''}${evidence.metrics.percent_delta.toFixed(2)}%`
                                    }
                                    tone={evidence.metrics.percent_delta !== null && evidence.metrics.percent_delta !== undefined && evidence.metrics.percent_delta > 0 ? 'warning' : 'default'}
                                />
                            </div>
                        </ExecutiveChartCard>

                            <ExecutiveChartCard title="Detected vs Baseline" subtitle="Direct comparison snapshot" tone="neutral">
                                <GroupedComparisonChart
                                    data={evidence.comparison}
                                    firstLabel="Detected"
                                    secondLabel="Expected"
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

                <form
                    className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(route('executive.anomalies.update-status', anomaly.anomaly_id));
                    }}
                >
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h3 className="text-lg font-semibold text-slate-900">Executive Disposition</h3>
                        <p className="mt-0.5 text-sm text-slate-600">Update this anomaly after completing the evidence review.</p>
                    </div>
                    <div className="grid gap-3 p-5 md:grid-cols-12">
                        <input className="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200 md:col-span-4" placeholder="IN_REVIEW / RESOLVED / DISMISSED" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} />
                        <input className="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200 md:col-span-6" placeholder="Remarks" value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                        <button type="submit" className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-70 md:col-span-2" disabled={form.processing}>Update Status</button>
                    </div>
                </form>
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

function DetailItem({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <div className="mt-1 font-semibold text-slate-900">{value}</div>
        </div>
    );
}

function SeverityBadge({ value }: { value: string }) {
    const normalized = value.toUpperCase();
    const className = normalized === 'HIGH'
        ? 'border-red-300 bg-red-100 text-red-800'
        : normalized === 'MEDIUM'
            ? 'border-amber-300 bg-amber-100 text-amber-800'
            : 'border-slate-300 bg-slate-100 text-slate-700';

    return <span className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide ${className}`}>{formatStatusLabel(value)}</span>;
}

function StatusBadge({ value }: { value: string }) {
    const normalized = value.toUpperCase();
    const className = normalized === 'OPEN'
        ? 'border-red-300 bg-red-100 text-red-800'
        : normalized === 'IN_REVIEW'
            ? 'border-blue-300 bg-blue-100 text-blue-800'
            : normalized === 'RESOLVED'
                ? 'border-emerald-300 bg-emerald-100 text-emerald-800'
                : 'border-slate-300 bg-slate-100 text-slate-700';

    return <span className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide ${className}`}>{formatStatusLabel(value)}</span>;
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

function calculatePercentDelta(detected?: number | null, expected?: number | null): number | null {
    if (detected === null || detected === undefined || expected === null || expected === undefined || expected === 0) {
        return null;
    }

    return ((detected - expected) / expected) * 100;
}





