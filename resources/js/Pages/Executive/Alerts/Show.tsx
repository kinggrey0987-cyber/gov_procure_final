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
        <AuthenticatedLayout
            header="Risk & Alerts"
            headerActions={
                <div className="flex items-center gap-2">
                    <StatusBadge status={alert.status} />
                    <Link
                        href={route('executive.anomalies-monitoring')}
                        className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50"
                    >
                        &larr; Return to Risk & Alerts
                    </Link>
                </div>
            }
        >
            <Head title={`Alert #${alert.alerts_id} - ${formatAlertLabel(alert.alert_type)}`} />

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
                                    Alert #{alert.alerts_id}
                                </span>
                                {alert.office && (
                                    <>
                                        <span className="text-xs font-semibold text-slate-300">•</span>
                                        <span className="text-xs font-bold text-slate-800">{alert.office.name}</span>
                                    </>
                                )}
                            </div>
                            <h2 className="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-tight">
                                {alertInfo.headline}
                            </h2>
                            <p className="text-xs text-slate-600 font-medium">
                                {alertInfo.explanation}
                            </p>
                        </div>
                    </div>
                </section>

                {/* 2. Cockpit Grid: Visual Evidence & Briefing (7 cols) | Audit Context & Connections (5 cols) */}
                <div className="grid gap-2.5 xl:grid-cols-12 items-start">
                    {/* Left: Evidence & Mayor's Governance Briefing (7 cols) */}
                    <div className="xl:col-span-7 space-y-2.5">
                        {anomaly_evidence ? (
                            <InfographicVarianceMeter
                                detected={detectedMetric}
                                expected={expectedMetric}
                                threshold={thresholdMetric}
                                isCurrency={isCurrency}
                                unitLabel={alertInfo.unitLabel}
                                percentDelta={percentDelta}
                                zScore={anomaly_evidence?.metrics?.z_score ?? null}
                                taxonomy={(anomaly_evidence as any)?.taxonomy}
                                baselineParams={(anomaly_evidence as any)?.baseline_parameters}
                                literatureCitation={(anomaly_evidence as any)?.literature_citation}
                                plainBreakdown={(anomaly_evidence as any)?.plain_english_breakdown}
                            />
                        ) : stockAvailable !== null && stockThreshold !== null ? (
                            <OperationalStockGauge
                                available={stockAvailable}
                                threshold={stockThreshold}
                                itemName={itemName}
                            />
                        ) : (
                            <section className="rounded-xl border border-amber-200 bg-white p-3 shadow-xs space-y-2">
                                <div className="flex items-center gap-2.5">
                                    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-white font-black text-sm">
                                        !
                                    </span>
                                    <div>
                                        <p className="text-[10px] font-bold uppercase tracking-wider text-amber-800">Operational Notice</p>
                                        <h3 className="text-sm font-extrabold text-slate-900">{formatAlertLabel(alert.alert_type)}</h3>
                                    </div>
                                </div>
                                <div className="rounded-lg border border-amber-100 bg-amber-50/60 p-2.5 text-xs font-medium text-amber-950 leading-relaxed">
                                    {alert.alert_message}
                                </div>
                            </section>
                        )}

                        {/* Mayor's Governance Briefing */}
                        <section className="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                            <div className="border-b border-slate-100 bg-slate-50/70 px-3.5 py-2 flex items-center justify-between">
                                <h3 className="text-xs font-bold text-slate-900">Mayor's Governance Briefing</h3>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-red-700">Executive Analysis</span>
                            </div>

                            <div className="p-3 space-y-2 text-xs">
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <div className="rounded-lg border border-rose-100 bg-rose-50/50 p-2.5 space-y-0.5">
                                        <p className="text-[10px] font-bold uppercase tracking-wider text-rose-900">Governance & Service Risk</p>
                                        <p className="text-xs text-rose-800 leading-snug font-medium">
                                            {alertInfo.governanceRisk}
                                        </p>
                                    </div>

                                    <div className="rounded-lg border border-emerald-100 bg-emerald-50/50 p-2.5 space-y-0.5">
                                        <p className="text-[10px] font-bold uppercase tracking-wider text-emerald-900">Recommended Next Steps</p>
                                        <p className="text-xs text-emerald-800 leading-snug font-medium">
                                            {alertInfo.recommendedAction}
                                        </p>
                                    </div>
                                </div>

                                <div className="rounded-lg border border-slate-200 bg-slate-50 p-2 space-y-0.5">
                                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-600">Operational Log Entry</p>
                                    <p className="text-xs text-slate-800 leading-snug">
                                        {alert.alert_message}
                                    </p>
                                </div>
                            </div>
                        </section>
                    </div>

                    {/* Right: Context & Source Entities (5 cols) */}
                    <div className="xl:col-span-5 space-y-2.5">
                        <section className="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                            <div className="border-b border-slate-100 bg-slate-50/70 px-3.5 py-2 flex items-center justify-between">
                                <h3 className="text-xs font-bold text-slate-900">Context & Source Entities</h3>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Source Audit</span>
                            </div>

                            <div className="p-2.5 space-y-1.5 text-xs">
                                <div className="flex items-start justify-between gap-2.5 rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5">
                                    <span className="shrink-0 font-medium text-slate-500">Office:</span>
                                    <span className="font-bold text-slate-900 text-right leading-tight break-words">{alert.office?.name ?? 'General Services / Central Store'}</span>
                                </div>

                                {alert.purchase_order && (
                                    <div className="flex items-start justify-between gap-2.5 rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5">
                                        <span className="shrink-0 font-medium text-slate-500">PO:</span>
                                        <Link
                                            href={route('executive.procurement-records.purchase-orders.show', alert.purchase_order.purchase_order_id)}
                                            className="font-bold text-red-700 hover:underline text-right"
                                        >
                                            {alert.purchase_order.po_number ?? `PO #${alert.purchase_order.purchase_order_id}`} &rarr;
                                        </Link>
                                    </div>
                                )}

                                {alert.supply_request && (
                                    <div className="flex items-start justify-between gap-2.5 rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5">
                                        <span className="shrink-0 font-medium text-slate-500">Request:</span>
                                        <span className="font-bold text-slate-900 text-right leading-tight break-words">
                                            {alert.supply_request.tracking_code ?? `REQ #${alert.supply_request.supply_request_id}`}
                                        </span>
                                    </div>
                                )}

                                {alert.supplier && (
                                    <div className="flex items-start justify-between gap-2.5 rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5">
                                        <span className="shrink-0 font-medium text-slate-500">Supplier:</span>
                                        <span className="font-bold text-slate-900 text-right leading-tight break-words">{alert.supplier.business_name}</span>
                                    </div>
                                )}

                                {alert.detected_at && (
                                    <div className="flex items-start justify-between gap-2.5 rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5">
                                        <span className="shrink-0 font-medium text-slate-500">Triggered:</span>
                                        <span className="font-bold text-slate-900 text-right leading-tight">{formatDate(alert.detected_at)}</span>
                                    </div>
                                )}
                            </div>
                        </section>

                        {/* Linked Anomaly */}
                        <section className="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                            <div className="border-b border-slate-100 px-3.5 py-2">
                                <h3 className="text-xs font-bold text-slate-900">Underlying Anomaly Record</h3>
                            </div>
                            <div className="p-2.5">
                                {alert.anomaly_id ? (
                                    <Link
                                        href={route('executive.anomalies.show', alert.anomaly_id)}
                                        className="flex items-center justify-between rounded-lg border border-red-200 bg-red-50/60 p-2 text-xs font-bold text-red-900 hover:bg-red-100 transition"
                                    >
                                        <span>Anomaly #{alert.anomaly_id} Evidence & Details</span>
                                        <span className="text-red-700">&rarr;</span>
                                    </Link>
                                ) : (
                                    <p className="rounded-lg border border-dashed border-slate-200 p-2.5 text-center text-xs text-slate-500">
                                        Independent operational alert without parent anomaly.
                                    </p>
                                )}
                            </div>
                        </section>

                        {/* Historical Series (Compact) */}
                        {trendData.length > 0 ? (
                            <section className="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                                <div className="border-b border-slate-100 bg-slate-50/70 px-3.5 py-2 flex items-center justify-between">
                                    <h3 className="text-xs font-bold text-slate-900">Historical Valuation Trajectory</h3>
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
