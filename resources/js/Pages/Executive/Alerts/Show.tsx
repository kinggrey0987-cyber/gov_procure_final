import { ExecutiveChartCard, TrendLineChart } from '@/Components/Executive/Charts';
import { InfographicVarianceMeter, OperationalStockGauge, interpretAnomaly } from '@/Components/Executive/InfographicEvidence';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Props = {
    alert: {
        alerts_id: number;
        alert_type: string;
        severity: string;
        status: string;
        alert_message: string;
        anomaly_id?: number | null;
        detected_at?: string | null;
        office?: { id: number; name: string } | null;
        purchase_order?: { purchase_order_id: number; po_number?: string } | null;
        supply_request?: { supply_request_id: number; tracking_code?: string } | null;
        supplier?: { supplier_id: number; business_name: string } | null;
        reviewer?: { id: number; name: string } | null;
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
    const trendData = (anomaly_evidence?.series ?? []).map((row) => ({ label: row.label, value: row.value }));
    const detectedMetric = anomaly_evidence?.metrics.detected_value ?? null;
    const expectedMetric = anomaly_evidence?.metrics.expected_value ?? null;
    const thresholdMetric = anomaly_evidence?.metrics.threshold_value ?? null;
    const percentDelta = anomaly_evidence?.metrics.percent_delta ?? null;

    const severityTone = getSeverityTone(alert.severity);
    const itemLabel = getItemLabel(alert.alert_type);
    const isCurrency = isPriceAlert(alert.alert_type);

    // Check for operational stock alert patterns (e.g. available X vs threshold Y)
    const stockMatch = alert.alert_message.match(/available\s*([\d.]+)\s*vs\s*threshold\s*([\d.]+)/i);
    const itemNameMatch = alert.alert_message.match(/^([^is]+?)\s+is\s+below/i);
    const stockAvailable = stockMatch ? parseFloat(stockMatch[1]) : null;
    const stockThreshold = stockMatch ? parseFloat(stockMatch[2]) : null;
    const itemName = itemNameMatch ? itemNameMatch[1].trim() : 'Warehouse Inventory Item';

    const alertInfo = anomaly_evidence
        ? interpretAnomaly(anomaly_evidence.type, detectedMetric, expectedMetric, thresholdMetric, percentDelta, itemLabel)
        : interpretOperationalAlert(alert.alert_type, alert.alert_message, stockAvailable, stockThreshold);

    return (
        <AuthenticatedLayout header="Risk & Alerts">
            <Head title={`Alert #${alert.alerts_id} - ${formatAlertLabel(alert.alert_type)}`} />

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
                                    Alert #{alert.alerts_id}
                                </span>
                                {alert.office && (
                                    <>
                                        <span className="text-xs font-semibold text-slate-400">•</span>
                                        <span className="text-xs font-bold text-slate-700">{alert.office.name}</span>
                                    </>
                                )}
                            </div>
                            <h2 className="text-2xl font-black text-slate-900 tracking-tight">
                                {alertInfo.headline}
                            </h2>
                            <p className="text-sm text-slate-600 leading-relaxed font-medium">
                                {alertInfo.explanation}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <StatusBadge status={alert.status} />
                            <Link
                                href={route('executive.anomalies-monitoring')}
                                className="inline-flex items-center rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                            >
                                &larr; Return to Risk & Alerts
                            </Link>
                        </div>
                    </div>
                </section>

                {/* 2. Visual Infographic (Variance Meter OR Stock Gauge OR Operational Card) */}
                {anomaly_evidence ? (
                    <InfographicVarianceMeter
                        detected={detectedMetric}
                        expected={expectedMetric}
                        threshold={thresholdMetric}
                        isCurrency={isCurrency}
                        unitLabel={alertInfo.unitLabel}
                        percentDelta={percentDelta}
                    />
                ) : stockAvailable !== null && stockThreshold !== null ? (
                    <OperationalStockGauge
                        available={stockAvailable}
                        threshold={stockThreshold}
                        itemName={itemName}
                    />
                ) : (
                    <section className="rounded-2xl border border-amber-200 bg-white p-6 shadow-sm space-y-4">
                        <div className="flex items-center gap-3">
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white font-black text-lg">
                                !
                            </span>
                            <div>
                                <p className="text-xs font-bold uppercase tracking-wider text-amber-800">Operational Notice</p>
                                <h3 className="text-base font-extrabold text-slate-900">{formatAlertLabel(alert.alert_type)}</h3>
                            </div>
                        </div>
                        <div className="rounded-xl border border-amber-100 bg-amber-50/60 p-4 text-xs font-medium text-amber-950 leading-relaxed">
                            {alert.alert_message}
                        </div>
                    </section>
                )}

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
                                    <p className="text-xs font-bold uppercase tracking-wider text-rose-900">Governance & Service Risk</p>
                                    <p className="text-xs text-rose-800 leading-relaxed font-medium">
                                        {alertInfo.governanceRisk}
                                    </p>
                                </div>

                                <div className="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 space-y-1">
                                    <p className="text-xs font-bold uppercase tracking-wider text-emerald-900">Recommended Executive Action</p>
                                    <p className="text-xs text-emerald-800 leading-relaxed font-medium">
                                        {alertInfo.recommendedAction}
                                    </p>
                                </div>

                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-1">
                                    <p className="text-xs font-bold uppercase tracking-wider text-slate-600">Operational Log Entry</p>
                                    <p className="text-xs text-slate-800 leading-relaxed">
                                        {alert.alert_message}
                                    </p>
                                </div>
                            </div>
                        </section>
                    </div>

                    {/* Right: Context & Connected Records (5 cols) */}
                    <div className="xl:col-span-5 space-y-6">
                        <section className="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div className="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Source Audit Trail</span>
                                <h3 className="text-base font-bold text-slate-900 mt-0.5">Context & Source Entities</h3>
                            </div>

                            <div className="p-5 space-y-3 text-xs">
                                <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
                                    <span className="font-medium text-slate-500">Concerned Office:</span>
                                    <span className="font-bold text-slate-900">{alert.office?.name ?? 'General Services / Central Store'}</span>
                                </div>

                                {alert.purchase_order && (
                                    <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
                                        <span className="font-medium text-slate-500">Purchase Order:</span>
                                        <Link
                                            href={route('executive.procurement-records.purchase-orders.show', alert.purchase_order.purchase_order_id)}
                                            className="font-bold text-red-700 hover:underline"
                                        >
                                            {alert.purchase_order.po_number ?? `PO #${alert.purchase_order.purchase_order_id}`} &rarr;
                                        </Link>
                                    </div>
                                )}

                                {alert.supply_request && (
                                    <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
                                        <span className="font-medium text-slate-500">Supply Request:</span>
                                        <span className="font-bold text-slate-900">
                                            {alert.supply_request.tracking_code ?? `REQ #${alert.supply_request.supply_request_id}`}
                                        </span>
                                    </div>
                                )}

                                {alert.supplier && (
                                    <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
                                        <span className="font-medium text-slate-500">Supplier:</span>
                                        <span className="font-bold text-slate-900">{alert.supplier.business_name}</span>
                                    </div>
                                )}

                                {alert.detected_at && (
                                    <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
                                        <span className="font-medium text-slate-500">Triggered Timestamp:</span>
                                        <span className="font-bold text-slate-900">{formatDate(alert.detected_at)}</span>
                                    </div>
                                )}
                            </div>
                        </section>

                        {/* Linked Anomaly */}
                        <section className="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div className="border-b border-slate-100 px-5 py-4">
                                <h3 className="text-sm font-bold text-slate-900">Underlying Anomaly Record</h3>
                            </div>
                            <div className="p-4">
                                {alert.anomaly_id ? (
                                    <Link
                                        href={route('executive.anomalies.show', alert.anomaly_id)}
                                        className="flex items-center justify-between rounded-xl border border-red-200 bg-red-50/60 p-3.5 text-xs font-bold text-red-900 hover:bg-red-100 transition"
                                    >
                                        <span>Anomaly #{alert.anomaly_id} Evidence & Details</span>
                                        <span className="text-red-700">&rarr;</span>
                                    </Link>
                                ) : (
                                    <p className="rounded-xl border border-dashed border-slate-200 p-3.5 text-center text-xs text-slate-500">
                                        Generated as an independent operational alert without a parent statistical anomaly.
                                    </p>
                                )}
                            </div>
                        </section>
                    </div>
                </div>

                {/* 4. Historical Series Infographic (if linked to anomaly series) */}
                {trendData.length > 0 ? (
                    <ExecutiveChartCard
                        title="Historical Valuation Trajectory"
                        subtitle={`Linked anomaly historical observations (${trendData.length} recorded data points)`}
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

function interpretOperationalAlert(
    alertType: string,
    message: string,
    available?: number | null,
    threshold?: number | null,
) {
    const type = (alertType || '').toUpperCase();

    const hasStockValues = typeof available === 'number' && typeof threshold === 'number';

    if (type.includes('LOW_STOCK') || hasStockValues) {
        return {
            category: 'Warehouse Inventory Depletion',
            headline: hasStockValues
                ? `Critical Inventory Buffer Alert: ${available.toFixed(2)} Units Remaining`
                : 'Warehouse Supply Below Minimum Reorder Point',
            unitLabel: 'Units',
            explanation: message,
            governanceRisk: 'Supply stockout risks interrupting essential frontline municipal services and office operations.',
            recommendedAction: 'Direct General Services Office (GSO) to verify warehouse inventory and initiate restock purchase requests.',
        };
    }

    if (type.includes('OVERDUE') || type.includes('DELAY')) {
        return {
            category: 'Procurement Workflow Delay',
            headline: 'Procurement / Delivery Milestone Overdue',
            unitLabel: 'Days',
            explanation: message,
            governanceRisk: 'Unresolved delivery delays disrupt project implementation schedules and delivery commitments.',
            recommendedAction: 'Issue notice to supplier or expediting order to verify current shipment status.',
        };
    }

    return {
        category: 'Operational Alert',
        headline: formatAlertLabel(alertType),
        unitLabel: 'Records',
        explanation: message,
        governanceRisk: 'Operational deviation requiring executive monitoring and departmental coordination.',
        recommendedAction: 'Endorse to concerned department head for immediate operational resolution.',
    };
}

function formatAlertLabel(value: string): string {
    return (value || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function isPriceAlert(value: string): boolean {
    const normalized = (value || '').toUpperCase();
    return normalized.includes('PRICE') || normalized.includes('Z_SCORE');
}

function getItemLabel(value: string): string {
    const normalized = (value || '').toUpperCase();
    if (normalized.includes('STOCK')) return 'Units';
    if (normalized.includes('PRICE')) return 'Pesos';
    if (normalized.includes('REQUEST')) return 'Requests';
    return 'Records';
}

function getSeverityTone(value: string): { shell: string; badge: string; label: string } {
    const normalized = (value || '').toUpperCase();
    if (normalized === 'HIGH' || normalized === 'CRITICAL') {
        return {
            shell: 'border-rose-200 bg-gradient-to-r from-rose-50/80 via-white to-white',
            badge: 'bg-rose-100 text-rose-800 border-rose-300',
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
