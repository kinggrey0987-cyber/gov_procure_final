import { ExecutiveChartCard, GroupedComparisonChart, TrendLineChart } from '@/Components/Executive/Charts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

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

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Anomaly Detail</h2>}>
            <Head title="Anomaly Detail" />
            <div className="py-10">
                <div className="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center">
                        <Link
                            href={route('executive.anomalies-monitoring')}
                            className="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Return to Risk &amp; Alerts
                        </Link>
                    </div>

                    <section className="rounded border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                        Executive review only. Only Executive Officer can open this detail page and update anomaly status.
                    </section>

                    <div className="rounded border bg-white p-6 text-sm space-y-1">
                        <p><strong>ID:</strong> {anomaly.anomaly_id}</p>
                        <p><strong>Type:</strong> {formatAnomalyType(anomaly.anomaly_type)}</p>
                        <p><strong>Severity:</strong> {anomaly.severity}</p>
                        <p><strong>Status:</strong> {anomaly.status}</p>
                        <p><strong>Detected Value:</strong> {anomaly.detected_value ?? '-'}</p>
                        <p><strong>Expected Value:</strong> {anomaly.expected_value ?? '-'}</p>
                        <p><strong>Threshold:</strong> {anomaly.threshold_value ?? '-'}</p>
                        <p><strong>Z-Score:</strong> {anomaly.z_score ?? '-'}</p>
                        <p><strong>Method:</strong> {anomaly.detection_method ?? '-'}</p>
                        <p><strong>Remarks:</strong> {anomaly.remarks ?? '-'}</p>
                        {isPriceAnomaly(anomaly.anomaly_type) ? (
                            <p><strong>Source Note:</strong> Price anomalies are derived from purchase order item pricing, with office used only for executive grouping context.</p>
                        ) : null}

                        {anomaly.alerts && anomaly.alerts.length > 0 ? (
                            <div className="pt-2">
                                <p><strong>Linked Alerts:</strong></p>
                                <div className="mt-1 flex flex-wrap gap-2">
                                    {anomaly.alerts.slice(0, 4).map((alert) => (
                                        <Link
                                            key={alert.alerts_id}
                                            href={route('executive.alerts.show', alert.alerts_id)}
                                            className="rounded bg-indigo-600 px-3 py-1 text-xs font-medium text-white"
                                        >
                                            Alert #{alert.alerts_id}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        ) : null}
                    </div>

                    {evidence ? (
                        <div className="grid gap-4 lg:grid-cols-2">
                            <ExecutiveChartCard title={evidence.title} subtitle={evidence.narrative} tone="warning">
                                <div className="grid gap-2 text-sm sm:grid-cols-2">
                                    <MetricCell label="Detected" value={formatMetric(evidence.metrics.detected_value)} />
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
                        className="space-y-2 rounded border bg-white p-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.put(route('executive.anomalies.update-status', anomaly.anomaly_id));
                        }}
                    >
                        <input className="w-full rounded border px-3 py-2" placeholder="IN_REVIEW / RESOLVED / DISMISSED" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" placeholder="Remarks" value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">Update Status</button>
                    </form>
                </div>
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

function MetricCell({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded border border-slate-200 bg-slate-50 px-3 py-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-0.5 text-sm font-bold text-slate-900">{value}</p>
        </div>
    );
}

function formatMetric(value?: number | null): string {
    if (value === null || value === undefined) {
        return 'N/A';
    }

    return Number(value).toLocaleString('en-PH', { maximumFractionDigits: 4 });
}
