import { ExecutiveChartCard, GroupedComparisonChart, TrendLineChart } from '@/Components/Executive/Charts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ReactNode } from 'react';

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
    const percentDelta = anomaly_evidence?.metrics.percent_delta ?? null;

    return (
        <AuthenticatedLayout header="Risk & Alerts">
            <Head title="Alert Detail" />
            <div className="space-y-6 py-8">
                <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-red-200 bg-white px-5 py-4 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <div>
                        <p className="text-sm font-semibold uppercase tracking-wide text-red-700">Executive alert review</p>
                        <h2 className="mt-1 text-2xl font-semibold text-slate-900">{formatLabel(alert.alert_type)}</h2>
                        <p className="mt-1 text-sm text-slate-600">Alert context, linked anomaly evidence, and executive disposition controls.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <SeverityBadge value={alert.severity} />
                        <StatusBadge value={alert.status} />
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
                            <p className="mt-0.5 text-sm text-indigo-900">Only the Executive Officer can open this detail page and update alert status.</p>
                        </div>
                    </div>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <MetricCell label="Alert ID" value={`#${alert.alerts_id}`} />
                    <MetricCell label="Severity" value={formatLabel(alert.severity)} tone={alert.severity.toUpperCase() === 'HIGH' ? 'critical' : 'warning'} />
                    <MetricCell label="Status" value={formatLabel(alert.status)} />
                    <MetricCell label="Linked Anomaly" value={alert.anomaly_id ? `#${alert.anomaly_id}` : 'None'} />
                    <MetricCell
                        label="% Delta"
                        value={percentDelta === null || percentDelta === undefined ? 'N/A' : `${percentDelta >= 0 ? '+' : ''}${percentDelta.toFixed(2)}%`}
                        tone={percentDelta !== null && percentDelta !== undefined && percentDelta > 0 ? 'warning' : 'default'}
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-12">
                    <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)] xl:col-span-7">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-red-700">Alert #{alert.alerts_id}</p>
                            <h3 className="mt-1 text-lg font-semibold text-slate-900">Signal Summary</h3>
                            <p className="mt-0.5 text-sm text-slate-600">Core alert classification and operational message.</p>
                        </div>
                        <div className="grid gap-3 p-5 text-sm md:grid-cols-2">
                            <DetailItem label="Type" value={formatLabel(alert.alert_type)} />
                            <DetailItem label="Severity" value={<SeverityBadge value={alert.severity} />} />
                            <DetailItem label="Status" value={<StatusBadge value={alert.status} />} />
                            <DetailItem label="Source" value={alert.anomaly_id ? 'Linked anomaly' : 'Operational alert'} />
                            <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 md:col-span-2">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Message</p>
                                <p className="mt-1 leading-6 text-slate-800">{alert.alert_message}</p>
                            </div>
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)] xl:col-span-5">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <h3 className="text-lg font-semibold text-slate-900">Linked Record</h3>
                            <p className="mt-0.5 text-sm text-slate-600">Open related anomaly evidence when available.</p>
                        </div>
                        <div className="p-5">
                            {alert.anomaly_id ? (
                                <Link
                                    href={route('executive.anomalies.show', alert.anomaly_id)}
                                    className="flex items-center justify-between rounded-lg border border-red-100 bg-red-50 px-3 py-3 text-sm font-semibold text-red-800 hover:bg-red-100"
                                >
                                    <span>Anomaly #{alert.anomaly_id}</span>
                                    <span className="text-xs uppercase tracking-wide text-red-600">View evidence</span>
                                </Link>
                            ) : (
                                <p className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-500">No linked anomaly for this alert.</p>
                            )}
                        </div>
                    </section>
                </div>

                {anomaly_evidence ? (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <ExecutiveChartCard title={anomaly_evidence.title} subtitle={anomaly_evidence.narrative} tone="warning">
                            <div className="grid gap-2 text-sm sm:grid-cols-2">
                                <MetricCell label="Detected" value={formatMetric(anomaly_evidence.metrics.detected_value)} tone="critical" />
                                <MetricCell label="Expected" value={formatMetric(anomaly_evidence.metrics.expected_value)} />
                                <MetricCell label="Threshold" value={formatMetric(anomaly_evidence.metrics.threshold_value)} />
                                <MetricCell label="Z-Score" value={formatMetric(anomaly_evidence.metrics.z_score)} />
                                <MetricCell
                                    label="% Delta"
                                    value={percentDelta === null || percentDelta === undefined ? 'N/A' : `${percentDelta >= 0 ? '+' : ''}${percentDelta.toFixed(2)}%`}
                                    tone={percentDelta !== null && percentDelta !== undefined && percentDelta > 0 ? 'warning' : 'default'}
                                />
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
                    className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(route('executive.alerts.update-status', alert.alerts_id));
                    }}
                >
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h3 className="text-lg font-semibold text-slate-900">Executive Disposition</h3>
                        <p className="mt-0.5 text-sm text-slate-600">Use this only after the alert has been reviewed and action has been taken or deemed unnecessary.</p>
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

    return <span className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide ${className}`}>{formatLabel(value)}</span>;
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

    return <span className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide ${className}`}>{formatLabel(value)}</span>;
}

function formatLabel(value: string): string {
    return value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatMetric(value?: number | null): string {
    if (value === null || value === undefined) {
        return 'N/A';
    }

    return Number(value).toLocaleString('en-PH', { maximumFractionDigits: 4 });
}





