import { ExecutiveChartCard, TrendLineChart } from '@/Components/Executive/Charts';
import { InfographicVarianceMeter, interpretAnomaly } from '@/Components/Executive/InfographicEvidence';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

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
        reference_type?: string | null;
        reference_id?: number | null;
        item_id?: number | null;
        detected_at?: string | null;
        office?: { id: number; name: string } | null;
        item?: { id: number; name: string; item_code?: string; unit?: string } | null;
        alerts?: Array<{ alerts_id: number; status: string; severity: string; alert_type?: string }>;
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
    const percentDelta = evidence?.metrics.percent_delta ?? null;

    const itemLabel = getItemLabel(anomaly.anomaly_type);
    const isCurrency = isPriceAnomaly(anomaly.anomaly_type);
    const anomalyInfo = interpretAnomaly(anomaly.anomaly_type, detectedMetric, expectedMetric, thresholdMetric, percentDelta, itemLabel);
    const severityTone = getSeverityTone(anomaly.severity);

    return (
        <AuthenticatedLayout header="Risk & Alerts">
            <Head title={`Anomaly #${anomaly.anomaly_id} - ${anomalyInfo.category}`} />

            <div className="space-y-6 py-8">
                {/* 1. Executive Finding Header */}
                <section className={`overflow-hidden rounded-2xl border ${severityTone.shell} p-6 shadow-sm`}>
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div className="space-y-1.5 max-w-3xl">
                            <div className="flex items-center gap-2">
                                <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider ${severityTone.badge}`}>
                                    {severityTone.label} Alert
                                </span>
                                <span className="text-xs font-semibold text-slate-400">•</span>
                                <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Anomaly #{anomaly.anomaly_id}
                                </span>
                                {anomaly.office && (
                                    <>
                                        <span className="text-xs font-semibold text-slate-400">•</span>
                                        <span className="text-xs font-bold text-slate-700">{anomaly.office.name}</span>
                                    </>
                                )}
                            </div>
                            <h2 className="text-2xl font-black text-slate-900 tracking-tight">
                                {anomalyInfo.headline}
                            </h2>
                            <p className="text-sm text-slate-600 leading-relaxed font-medium">
                                {anomalyInfo.explanation}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <StatusBadge status={anomaly.status} />
                            <Link
                                href={route('executive.anomalies-monitoring')}
                                className="inline-flex items-center rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                            >
                                &larr; Return to Risk & Alerts
                            </Link>
                        </div>
                    </div>
                </section>

                {/* 2. Visual Infographic Variance Meter */}
                <InfographicVarianceMeter
                    detected={detectedMetric}
                    expected={expectedMetric}
                    threshold={thresholdMetric}
                    isCurrency={isCurrency}
                    unitLabel={anomalyInfo.unitLabel}
                    percentDelta={percentDelta}
                />

                {/* 3. Executive Governance Briefing & Office Context */}
                <div className="grid gap-6 xl:grid-cols-12">
                    {/* Left: Mayor Governance Briefing (7 cols) */}
                    <div className="xl:col-span-7 space-y-6">
                        <section className="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div className="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-red-700">Executive Analysis</span>
                                <h3 className="text-base font-bold text-slate-900 mt-0.5">Mayor's Governance Briefing</h3>
                            </div>

                            <div className="p-5 space-y-4 text-sm">
                                <div className="rounded-xl border border-rose-100 bg-rose-50/50 p-4 space-y-1">
                                    <p className="text-xs font-bold uppercase tracking-wider text-rose-900">Governance & Compliance Risk</p>
                                    <p className="text-xs text-rose-800 leading-relaxed font-medium">
                                        {anomalyInfo.governanceRisk}
                                    </p>
                                </div>

                                <div className="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 space-y-1">
                                    <p className="text-xs font-bold uppercase tracking-wider text-emerald-900">Recommended Executive Next Steps</p>
                                    <p className="text-xs text-emerald-800 leading-relaxed font-medium">
                                        {anomalyInfo.recommendedAction}
                                    </p>
                                </div>

                                {anomaly.remarks && (
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-1">
                                        <p className="text-xs font-bold uppercase tracking-wider text-slate-600">Detection Context & Notes</p>
                                        <p className="text-xs text-slate-800 leading-relaxed">
                                            {anomaly.remarks}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>

                    {/* Right: Context & Connected Records (5 cols) */}
                    <div className="xl:col-span-5 space-y-6">
                        <section className="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div className="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Source Audit Trail</span>
                                <h3 className="text-base font-bold text-slate-900 mt-0.5">Transaction Context</h3>
                            </div>

                            <div className="p-5 space-y-3 text-xs">
                                <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
                                    <span className="font-medium text-slate-500">Responsible Office:</span>
                                    <span className="font-bold text-slate-900">{anomaly.office?.name ?? 'General Services / Central'}</span>
                                </div>

                                {anomaly.item && (
                                    <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
                                        <span className="font-medium text-slate-500">Associated Item:</span>
                                        <span className="font-bold text-slate-900 truncate max-w-[200px]" title={anomaly.item.name}>
                                            {anomaly.item.name} {anomaly.item.item_code ? `(${anomaly.item.item_code})` : ''}
                                        </span>
                                    </div>
                                )}

                                <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
                                    <span className="font-medium text-slate-500">Detection Method:</span>
                                    <span className="font-bold text-slate-900">{anomaly.detection_method ?? 'Statistical Baseline Algorithm'}</span>
                                </div>

                                {anomaly.detected_at && (
                                    <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
                                        <span className="font-medium text-slate-500">Detected Timestamp:</span>
                                        <span className="font-bold text-slate-900">{formatDate(anomaly.detected_at)}</span>
                                    </div>
                                )}
                            </div>
                        </section>

                        {/* Connected Alerts */}
                        <section className="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div className="border-b border-slate-100 px-5 py-4">
                                <h3 className="text-sm font-bold text-slate-900">Connected Operational Alerts</h3>
                            </div>
                            <div className="p-4">
                                {anomaly.alerts && anomaly.alerts.length > 0 ? (
                                    <div className="space-y-2">
                                        {anomaly.alerts.map((alert) => (
                                            <Link
                                                key={alert.alerts_id}
                                                href={route('executive.alerts.show', alert.alerts_id)}
                                                className="flex items-center justify-between rounded-xl border border-red-200 bg-red-50/60 px-3.5 py-2.5 text-xs font-semibold text-red-900 hover:bg-red-100 transition"
                                            >
                                                <span>Alert #{alert.alerts_id} • {alert.alert_type ?? 'Anomaly Linked'}</span>
                                                <span className="rounded-md bg-red-200/80 px-2 py-0.5 text-[10px] font-bold text-red-800 uppercase">
                                                    {alert.severity}
                                                </span>
                                            </Link>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="rounded-xl border border-dashed border-slate-200 p-3.5 text-center text-xs text-slate-500">
                                        No linked secondary alerts recorded.
                                    </p>
                                )}
                            </div>
                        </section>
                    </div>
                </div>

                {/* 4. Historical Series Infographic Chart */}
                {trendData.length > 0 ? (
                    <ExecutiveChartCard
                        title="Historical Transaction Series"
                        subtitle={`Prior recorded transaction benchmark points leading up to this flagged anomaly (${trendData.length} observations)`}
                        eyebrow="Historical Benchmark"
                        tone="default"
                    >
                        <TrendLineChart
                            data={trendData}
                            emptyText="No historical series data available"
                            showPointCards={false}
                            valueFormatter={(val) =>
                                isCurrency
                                    ? `₱${val.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                    : `${val.toLocaleString('en-PH')} ${itemLabel}`
                            }
                        />
                    </ExecutiveChartCard>
                ) : null}
            </div>
        </AuthenticatedLayout>
    );
}

function getItemLabel(value: string): string {
    const normalized = (value || '').toUpperCase();
    if (normalized.includes('REQUEST')) return 'Requests';
    if (normalized.includes('PRICE') || normalized.includes('Z_SCORE')) return 'Pesos';
    if (normalized.includes('QUANTITY')) return 'Units';
    if (normalized.includes('FREQUENCY')) return 'Occurrences';
    return 'Records';
}

function isPriceAnomaly(value: string): boolean {
    const normalized = (value || '').toUpperCase();
    return normalized.includes('PRICE') || normalized.includes('Z_SCORE');
}

function parseOptionalNumber(value?: string | null): number | null {
    if (value === null || value === undefined || value === '') return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function getSeverityTone(value: string): { shell: string; badge: string; label: string } {
    const normalized = (value || '').toUpperCase();
    if (normalized === 'HIGH' || normalized === 'CRITICAL') {
        return {
            shell: 'border-red-200 bg-gradient-to-r from-red-50/80 via-white to-white',
            badge: 'bg-red-100 text-red-800 border-red-300',
            label: 'High Severity',
        };
    }
    if (normalized === 'MEDIUM') {
        return {
            shell: 'border-amber-200 bg-gradient-to-r from-amber-50/80 via-white to-white',
            badge: 'bg-amber-100 text-amber-800 border-amber-300',
            label: 'Medium Severity',
        };
    }
    return {
        shell: 'border-slate-200 bg-gradient-to-r from-slate-50/80 via-white to-white',
        badge: 'bg-slate-100 text-slate-800 border-slate-300',
        label: 'Informational',
    };
}

function StatusBadge({ status }: { status: string }) {
    const normalized = (status || '').toUpperCase();
    if (normalized === 'RESOLVED') {
        return <span className="inline-flex rounded-full bg-emerald-100 border border-emerald-300 px-3 py-1 text-xs font-bold text-emerald-800 uppercase">Resolved</span>;
    }
    if (normalized === 'IN_REVIEW') {
        return <span className="inline-flex rounded-full bg-indigo-100 border border-indigo-300 px-3 py-1 text-xs font-bold text-indigo-800 uppercase">Under Review</span>;
    }
    return null;
}

function formatDate(dateStr?: string | null): string {
    if (!dateStr) return 'N/A';
    try {
        return new Date(dateStr).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return dateStr;
    }
}
