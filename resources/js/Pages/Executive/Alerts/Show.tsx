import { ExecutiveChartCard, GroupedComparisonChart, TrendLineChart } from '@/Components/Executive/Charts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

type Props = {
    alert: {
        alerts_id: number;
        alert_type: string;
        severity: string;
        status: string;
        alert_message: string;
        anomaly_id?: number | null;
    };
    anomaly_evidence?: {
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
        series: Array<{ label: string; value: number }>;
        comparison: Array<{ label: string; first: number; second: number }>;
    } | null;
};

export default function ExecutiveAlertShow({ alert, anomaly_evidence }: Props) {
    const form = useForm({ status: '', remarks: '' });
    const evidenceTrend = (anomaly_evidence?.series ?? []).map((row) => ({ label: row.label, value: row.value }));

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Alert Detail</h2>}>
            <Head title="Alert Detail" />
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
                        Executive review only. Only Executive Officer can open this detail page and update alert status.
                    </section>

                    <div className="rounded border bg-white p-6 text-sm space-y-1">
                        <p><strong>ID:</strong> {alert.alerts_id}</p>
                        <p><strong>Type:</strong> {alert.alert_type}</p>
                        <p><strong>Severity:</strong> {alert.severity}</p>
                        <p><strong>Status:</strong> {alert.status}</p>
                        <p><strong>Message:</strong> {alert.alert_message}</p>
                        {alert.anomaly_id ? (
                            <p>
                                <strong>Linked Anomaly:</strong>{' '}
                                <Link href={route('executive.anomalies.show', alert.anomaly_id)} className="font-semibold text-indigo-700 hover:text-indigo-800">
                                    #{alert.anomaly_id}
                                </Link>
                            </p>
                        ) : null}
                    </div>

                    {anomaly_evidence ? (
                        <div className="grid gap-4 lg:grid-cols-2">
                            <ExecutiveChartCard title={anomaly_evidence.title} subtitle={anomaly_evidence.narrative} tone="warning">
                                <div className="grid gap-2 text-sm sm:grid-cols-2">
                                    <MetricCell label="Detected" value={formatMetric(anomaly_evidence.metrics.detected_value)} />
                                    <MetricCell label="Expected" value={formatMetric(anomaly_evidence.metrics.expected_value)} />
                                    <MetricCell label="Threshold" value={formatMetric(anomaly_evidence.metrics.threshold_value)} />
                                    <MetricCell label="Z-Score" value={formatMetric(anomaly_evidence.metrics.z_score)} />
                                </div>
                            </ExecutiveChartCard>

                            <ExecutiveChartCard title="Detected vs Baseline" subtitle="Source anomaly evidence snapshot" tone="neutral">
                                <GroupedComparisonChart
                                    data={anomaly_evidence.comparison}
                                    firstLabel="Detected"
                                    secondLabel="Expected"
                                    emptyText="No comparison evidence available"
                                />
                            </ExecutiveChartCard>

                            <div className="lg:col-span-2">
                                <ExecutiveChartCard title="Historical Series" subtitle="Linked anomaly source values" tone="default">
                                    <TrendLineChart
                                        data={evidenceTrend}
                                        emptyText="No linked anomaly trend data available"
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
                            form.put(route('executive.alerts.update-status', alert.alerts_id));
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
