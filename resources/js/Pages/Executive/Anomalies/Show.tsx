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
        <AuthenticatedLayout
            header="Risk & Alerts"
            headerActions={
                <div className="flex items-center gap-2">
                    <StatusBadge status={anomaly.status} />
                    <Link
                        href={route('executive.anomalies-monitoring')}
                        className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50"
                    >
                        &larr; Return to Risk & Alerts
                    </Link>
                </div>
            }
        >
            <Head title={`Anomaly #${anomaly.anomaly_id} - ${anomalyInfo.category}`} />

            <div className="space-y-2.5">
                {/* 1. Executive Finding Header Strip */}
                <section className={`rounded-xl border ${severityTone.shell} px-4 py-2.5 shadow-xs`}>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="space-y-0.5">
                            <div className="flex items-center gap-2">
                                <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${severityTone.badge}`}>
                                    {severityTone.label} Alert
                                </span>
                                <span className="text-xs font-semibold text-slate-300">•</span>
                                <span className="text-xs font-bold uppercase tracking-wider text-slate-600">
                                    Anomaly #{anomaly.anomaly_id}
                                </span>
                                {anomaly.office && (
                                    <>
                                        <span className="text-xs font-semibold text-slate-300">•</span>
                                        <span className="text-xs font-bold text-slate-800">{anomaly.office.name}</span>
                                    </>
                                )}
                            </div>
                            <h2 className="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-tight">
                                {anomalyInfo.headline}
                            </h2>
                            <p className="text-xs text-slate-600 font-medium">
                                {anomalyInfo.explanation}
                            </p>
                        </div>
                    </div>
                </section>

                {/* 2. Cockpit Grid: Visual Evidence & Briefing (7 cols) | Audit Context & Historical Trend (5 cols) */}
                <div className="grid gap-2.5 xl:grid-cols-12 items-start">
                    {/* Left: Variance Meter & Mayor's Governance Briefing (7 cols) */}
                    <div className="xl:col-span-7 space-y-2.5">
                        <InfographicVarianceMeter
                            detected={detectedMetric}
                            expected={expectedMetric}
                            threshold={thresholdMetric}
                            isCurrency={isCurrency}
                            unitLabel={anomalyInfo.unitLabel}
                            percentDelta={percentDelta}
                        />

                        {/* Mayor's Governance Briefing */}
                        <section className="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                            <div className="border-b border-slate-100 bg-slate-50/70 px-3.5 py-2 flex items-center justify-between">
                                <h3 className="text-xs font-bold text-slate-900">Mayor's Governance Briefing</h3>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-red-700">Executive Analysis</span>
                            </div>

                            <div className="p-3 space-y-2 text-xs">
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <div className="rounded-lg border border-rose-100 bg-rose-50/50 p-2.5 space-y-0.5">
                                        <p className="text-[10px] font-bold uppercase tracking-wider text-rose-900">Compliance Risk</p>
                                        <p className="text-xs text-rose-800 leading-snug font-medium">
                                            {anomalyInfo.governanceRisk}
                                        </p>
                                    </div>

                                    <div className="rounded-lg border border-emerald-100 bg-emerald-50/50 p-2.5 space-y-0.5">
                                        <p className="text-[10px] font-bold uppercase tracking-wider text-emerald-900">Recommended Next Steps</p>
                                        <p className="text-xs text-emerald-800 leading-snug font-medium">
                                            {anomalyInfo.recommendedAction}
                                        </p>
                                    </div>
                                </div>

                                {anomaly.remarks && (
                                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-2 space-y-0.5">
                                        <p className="text-[10px] font-bold uppercase tracking-wider text-slate-600">Detection Notes</p>
                                        <p className="text-xs text-slate-800 leading-snug">
                                            {anomaly.remarks}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>

                    {/* Right: Context & Connected Records & Historical Series (5 cols) */}
                    <div className="xl:col-span-5 space-y-2.5">
                        <section className="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                            <div className="border-b border-slate-100 bg-slate-50/70 px-3.5 py-2 flex items-center justify-between">
                                <h3 className="text-xs font-bold text-slate-900">Transaction Context</h3>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Source Audit</span>
                            </div>

                            <div className="p-2.5 space-y-1.5 text-xs">
                                <div className="flex items-start justify-between gap-2.5 rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5">
                                    <span className="shrink-0 font-medium text-slate-500">Office:</span>
                                    <span className="font-bold text-slate-900 text-right leading-tight break-words">{anomaly.office?.name ?? 'General Services / Central'}</span>
                                </div>

                                {anomaly.item && (
                                    <div className="flex items-start justify-between gap-2.5 rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5">
                                        <span className="shrink-0 font-medium text-slate-500">Item:</span>
                                        <span className="font-bold text-slate-900 text-right leading-tight break-words" title={anomaly.item.name}>
                                            {anomaly.item.name} {anomaly.item.item_code ? `(${anomaly.item.item_code})` : ''}
                                        </span>
                                    </div>
                                )}

                                <div className="flex items-start justify-between gap-2.5 rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5">
                                    <span className="shrink-0 font-medium text-slate-500">Method:</span>
                                    <span className="font-bold text-slate-900 text-right leading-tight break-words">{anomaly.detection_method ?? 'Statistical Baseline'}</span>
                                </div>

                                {anomaly.detected_at && (
                                    <div className="flex items-start justify-between gap-2.5 rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5">
                                        <span className="shrink-0 font-medium text-slate-500">Detected:</span>
                                        <span className="font-bold text-slate-900 text-right leading-tight">{formatDate(anomaly.detected_at)}</span>
                                    </div>
                                )}
                            </div>
                        </section>

                        {/* Connected Alerts */}
                        {anomaly.alerts && anomaly.alerts.length > 0 ? (
                            <section className="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                                <div className="border-b border-slate-100 px-3.5 py-2">
                                    <h3 className="text-xs font-bold text-slate-900">Connected Operational Alerts</h3>
                                </div>
                                <div className="p-2.5 space-y-1.5">
                                    {anomaly.alerts.map((alert) => (
                                        <Link
                                            key={alert.alerts_id}
                                            href={route('executive.alerts.show', alert.alerts_id)}
                                            className="flex items-center justify-between rounded-lg border border-red-200 bg-red-50/60 px-2.5 py-1.5 text-xs font-semibold text-red-900 hover:bg-red-100 transition"
                                        >
                                            <span className="truncate">Alert #{alert.alerts_id} • {alert.alert_type ?? 'Anomaly Linked'}</span>
                                            <span className="rounded bg-red-200/80 px-1.5 py-0.5 text-[9px] font-bold text-red-800 uppercase">
                                                {alert.severity}
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            </section>
                        ) : null}

                        {/* Historical Series (Compact) */}
                        {trendData.length > 0 ? (
                            <section className="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                                <div className="border-b border-slate-100 bg-slate-50/70 px-3.5 py-2 flex items-center justify-between">
                                    <h3 className="text-xs font-bold text-slate-900">Historical Observation Series</h3>
                                    <span className="text-[10px] font-semibold text-slate-500">{trendData.length} records</span>
                                </div>
                                <div className="p-2.5">
                                    <TrendLineChart
                                        data={trendData}
                                        emptyText="No historical series available"
                                        showPointCards={false}
                                        valueFormatter={(val) =>
                                            isCurrency
                                                ? `₱${val.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                                : `${val.toLocaleString('en-PH')} ${itemLabel}`
                                        }
                                    />
                                </div>
                            </section>
                        ) : null}
                    </div>
                </div>
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
