import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ExecutiveChartCard, HorizontalBarChart, TrendGroupedBarChart, TrendLineChart } from '@/Components/Executive/Charts';
import { downloadSectionAsPdfById } from '@/lib/printSection';
import { Head } from '@inertiajs/react';

type OfficeBreakdown = {
    id: number;
    name: string;
    anomaly_count: number;
    alert_count: number;
};

type Props = {
    summary: {
        open_anomaly_count: number;
        in_review_anomaly_count: number;
        high_severity_anomaly_count: number;
        open_alert_count: number;
        in_review_alert_count: number;
        high_severity_alert_count: number;
        cross_office_anomaly_count: number;
        office_breakdown: OfficeBreakdown[];
    };
    procurement: {
        purchase_request_count: number;
        purchase_order_count: number;
        total_purchase_order_amount: number;
        monthly_trends: {
            purchase_requests: Array<{ month: string; total: number }>;
            purchase_orders: Array<{ month: string; total: number }>;
        };
    };
    transparency: {
        published_disclosure_count: number;
        draft_disclosure_count: number;
        status_breakdown: Array<{ status: string; total: number }>;
    };
    approvals: {
        pending_ppmp_approvals: number;
        ppmp_workflow_total: number;
        returned_for_revision: number;
        recently_approved: number;
        status_mix: Array<{ status: string; total: number }>;
    };
    anomaly_type_breakdown: Array<{ label: string; total: number }>;
    alert_severity_breakdown: Array<{ label: string; total: number }>;
    recent_anomalies: Array<{
        anomaly_id: number;
        anomaly_type: string;
        severity: string;
        status: string;
        detected_at?: string | null;
        office?: { id: number; name: string } | null;
        reference_type?: string | null;
        reference_id?: number | null;
        item_id?: number | null;
    }>;
    recent_alerts: Array<{
        alerts_id: number;
        alert_type: string;
        severity: string;
        status: string;
        detected_at?: string | null;
        office?: { id: number; name: string } | null;
    }>;
};

export default function ExecutiveDashboardIndex({
    summary,
    procurement,
    transparency,
    approvals,
    anomaly_type_breakdown,
    alert_severity_breakdown,
    recent_anomalies,
    recent_alerts,
}: Props) {
    const printSectionId = 'executive-dashboard-print-section';

    const activeAnomalies = summary.open_anomaly_count + summary.in_review_anomaly_count;
    const activeAlerts = summary.open_alert_count + summary.in_review_alert_count;
    const highSeverityTotal = summary.high_severity_anomaly_count + summary.high_severity_alert_count;

    const monthlyProcurement = mergeGroupedTrend(
        procurement.monthly_trends.purchase_requests,
        procurement.monthly_trends.purchase_orders,
    );

    const totalActivityTrend = monthlyProcurement.map((row) => ({
        label: row.label,
        value: row.first + row.second,
    }));

    const approvalStatusMix = approvals.status_mix.map((row) => ({
        label: formatStatusLabel(row.status),
        value: row.total,
    }));

    const topAnomalyCategories = (anomaly_type_breakdown.length > 0
        ? anomaly_type_breakdown.map((row) => ({
            label: formatAnomalyType(row.label || 'N/A'),
            value: row.total,
        }))
        : [
            { label: 'Open anomalies', value: summary.open_anomaly_count },
            { label: 'In review anomalies', value: summary.in_review_anomaly_count },
            { label: 'High severity anomalies', value: summary.high_severity_anomaly_count },
        ])
        .sort((a, b) => b.value - a.value)
        .slice(0, 6);

    const severitySnapshot = alert_severity_breakdown
        .map((row) => ({
            label: formatStatusLabel(row.label || 'N/A'),
            value: row.total,
        }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 6);

    const officeRanking = summary.office_breakdown
        .map((office) => ({
            label: office.name,
            value: office.anomaly_count + office.alert_count,
        }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 8);

    const throughputMix = [
        { label: 'Purchase Requests', value: procurement.purchase_request_count },
        { label: 'Purchase Orders', value: procurement.purchase_order_count },
        { label: 'Pending PPMP Approvals', value: approvals.pending_ppmp_approvals },
    ].sort((a, b) => b.value - a.value);

    const pendingOrReviewDisclosures = transparency.status_breakdown
        .filter((row) => {
            const status = String(row.status).toUpperCase();
            return status !== 'PUBLISHED' && status !== 'DRAFT';
        })
        .reduce((total, row) => total + row.total, 0);

    const recentAnomalyGroups = buildDashboardAnomalyGroups(recent_anomalies).slice(0, 5);

    const exportCsv = () => {
        const lines = [
            ['metric', 'value'],
            ['purchase_requests', String(procurement.purchase_request_count)],
            ['purchase_orders', String(procurement.purchase_order_count)],
            ['procurement_spend', String(procurement.total_purchase_order_amount)],
            ['active_anomalies', String(activeAnomalies)],
            ['active_alerts', String(activeAlerts)],
            ['published_disclosures', String(transparency.published_disclosure_count)],
        ];

        const csv = lines.map((row) => row.map(csvValue).join(',')).join('\n');
        downloadFile(csv, 'executive-dashboard.csv', 'text/csv;charset=utf-8;');
    };

    const exportPdf = async () => {
        await downloadSectionAsPdfById(printSectionId, {
            title: 'Executive Dashboard',
            fileName: 'executive-dashboard.pdf',
        });
    };

    return (
        <AuthenticatedLayout header="Executive Dashboard">
            <Head title="Executive Dashboard" />

            <div id={printSectionId} className="space-y-6 py-8">
                <section className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-200 bg-white px-5 py-4 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <div>
                        <h2 className="text-2xl font-semibold text-slate-900">Executive Oversight Overview</h2>
                        <p className="text-sm text-slate-600">Summary first, trend signals second, risk concentration third, supporting evidence last.</p>
                    </div>
                    <div className="print-hide flex items-center gap-2">
                        <button
                            type="button"
                            onClick={exportPdf}
                            className="rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                        >
                            Export PDF
                        </button>
                        <button
                            type="button"
                            onClick={exportCsv}
                            className="rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white hover:bg-red-800"
                        >
                            Export CSV
                        </button>
                    </div>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    <MetricCard label="Purchase Requests" value={procurement.purchase_request_count} />
                    <MetricCard label="Purchase Orders" value={procurement.purchase_order_count} />
                    <MetricCard label="Procurement Spend" value={formatCurrency(procurement.total_purchase_order_amount)} />
                    <MetricCard label="Active Anomalies" value={activeAnomalies} />
                    <MetricCard label="Active Alerts" value={activeAlerts} />
                    <MetricCard label="Published Disclosures" value={transparency.published_disclosure_count} />
                </div>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-8">
                        <ExecutiveChartCard title="Procurement Activity Trend" subtitle="Monthly procurement movement" tone="critical">
                            {monthlyProcurement.length === 0 ? (
                                <CompactEmpty text="No data in current filter scope" />
                            ) : monthlyProcurement.length === 1 ? (
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <MiniStat label="Month" value={monthlyProcurement[0].label} />
                                    <MiniStat label="Purchase Requests" value={monthlyProcurement[0].first} />
                                    <MiniStat label="Purchase Orders" value={monthlyProcurement[0].second} />
                                </div>
                            ) : monthlyProcurement.length <= 3 ? (
                                <TrendGroupedBarChart
                                    data={monthlyProcurement}
                                    firstLabel="Purchase Requests"
                                    secondLabel="Purchase Orders"
                                    emptyText="No data in current filter scope"
                                />
                            ) : (
                                <TrendLineChart
                                    data={totalActivityTrend}
                                    emptyText="No data in current filter scope"
                                />
                            )}
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                        <ExecutiveChartCard title="PPMP Workflow Summary" subtitle="Approval status at a glance" tone="default">
                            {approvalStatusMix.length <= 2 ? (
                                <div className="grid gap-2">
                                    {approvalStatusMix.map((row) => (
                                        <MiniStat key={row.label} label={row.label} value={row.value} />
                                    ))}
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <div className="mx-auto h-[150px] w-[150px] lg:h-[160px] lg:w-[160px]">
                                        <svg viewBox="0 0 220 220" className="h-full w-full">
                                            <circle cx="110" cy="110" r={72} fill="none" stroke="rgb(254 226 226)" strokeWidth={24} />
                                            {approvalStatusMix.map((segment, index) => {
                                                const total = approvalStatusMix.reduce((sum, row) => sum + row.value, 0);
                                                const circumference = 2 * Math.PI * 72;
                                                const segmentLength = (segment.value / total) * circumference;
                                                const offset = approvalStatusMix.slice(0, index).reduce((sum, row) => sum + (row.value / total) * circumference, 0);

                                                return (
                                                    <circle
                                                        key={segment.label}
                                                        cx="110"
                                                        cy="110"
                                                        r={72}
                                                        fill="none"
                                                        stroke={['rgb(185 28 28)', 'rgb(245 158 11)', 'rgb(37 99 235)', 'rgb(22 163 74)'][index % 4]}
                                                        strokeWidth={24}
                                                        strokeDasharray={`${segmentLength} ${circumference - segmentLength}`}
                                                        strokeDashoffset={-offset}
                                                        transform="rotate(-90 110 110)"
                                                        strokeLinecap="butt"
                                                    />
                                                );
                                            })}

                                            <text x="110" y="105" textAnchor="middle" className="fill-slate-500 text-[10px] uppercase tracking-[0.14em]">
                                                PPMP
                                            </text>
                                            <text x="110" y="128" textAnchor="middle" className="fill-slate-900 text-[22px] font-bold">
                                                {approvals.ppmp_workflow_total}
                                            </text>
                                        </svg>
                                    </div>

                                    <div className="grid gap-2">
                                        {approvalStatusMix.map((segment, index) => {
                                            const total = approvalStatusMix.reduce((sum, row) => sum + row.value, 0);
                                            const percent = total > 0 ? Math.round((segment.value / total) * 100) : 0;

                                            return (
                                                <div key={segment.label} className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                                    <div className="flex min-w-0 items-start gap-2">
                                                        <span className="mt-1 h-3 w-3 shrink-0 rounded-full" style={{ backgroundColor: ['rgb(185 28 28)', 'rgb(245 158 11)', 'rgb(37 99 235)', 'rgb(22 163 74)'][index % 4] }} />
                                                        <span className="min-w-0 flex-1 break-words font-medium leading-5 text-slate-700">
                                                            {segment.label}
                                                        </span>
                                                    </div>
                                                    <div className="mt-1 pl-5 text-sm font-semibold text-slate-900">
                                                        {segment.value} ({percent}%)
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                    <div className="grid grid-cols-3 gap-2">
                                        <MiniStat label="Pending" value={approvals.pending_ppmp_approvals} />
                                        <MiniStat label="Returned" value={approvals.returned_for_revision} />
                                        <MiniStat label="Approved" value={approvals.recently_approved} />
                                    </div>
                                </div>
                            )}
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                        <ExecutiveChartCard title="Risk Summary" subtitle="Active and high-severity risk pressure" tone="critical">
                            <div className="space-y-3">
                                <RiskStrip label="Active Anomalies" value={activeAnomalies} max={Math.max(activeAnomalies, activeAlerts, highSeverityTotal, 1)} tone="rose" />
                                <RiskStrip label="Active Alerts" value={activeAlerts} max={Math.max(activeAnomalies, activeAlerts, highSeverityTotal, 1)} tone="amber" />
                                <RiskStrip label="High Severity Total" value={highSeverityTotal} max={Math.max(activeAnomalies, activeAlerts, highSeverityTotal, 1)} tone="slate" />
                            </div>
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-8">
                        <ExecutiveChartCard title="Risk Breakdown" subtitle="Top anomaly categories and severity snapshot" tone="warning">
                            <div className="grid gap-5 lg:grid-cols-2">
                                <div>
                                    <p className="mb-2 text-sm font-semibold text-slate-700">Top anomaly categories</p>
                                    <HorizontalBarChart data={topAnomalyCategories} emptyText="No data in current filter scope" />
                                </div>
                                <div>
                                    <p className="mb-2 text-sm font-semibold text-slate-700">Severity snapshot</p>
                                    <HorizontalBarChart data={severitySnapshot} emptyText="No data in current filter scope" />
                                </div>
                            </div>
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-8">
                        <ExecutiveChartCard title="Office Oversight Comparison" subtitle="Risk concentration by office" tone="neutral">
                            <HorizontalBarChart data={officeRanking} emptyText="No data in current filter scope" />
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4 space-y-4">
                        <ExecutiveChartCard title="Procurement Throughput" subtitle="Compact process concentration" tone="neutral">
                            <HorizontalBarChart data={throughputMix} emptyText="No data in current filter scope" />
                        </ExecutiveChartCard>

                        <ExecutiveChartCard title="Disclosure Snapshot" subtitle="Publication status summary" tone="default">
                            <div className="grid gap-2">
                                <MiniStat label="Published" value={transparency.published_disclosure_count} />
                                <MiniStat label="Draft" value={transparency.draft_disclosure_count} />
                                <MiniStat label="Pending / Review" value={pendingOrReviewDisclosures} />
                            </div>
                        </ExecutiveChartCard>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <h3 className="text-lg font-semibold text-slate-900">Recent Anomalies</h3>
                        </div>
                        {recent_anomalies.length === 0 ? (
                            <p className="px-5 py-6 text-sm text-slate-500">No data in current filter scope</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Type</th>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Related Records</th>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Severity</th>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Office</th>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {recentAnomalyGroups.map((group) => (
                                            <tr key={group.key}>
                                                <td className="px-4 py-2 text-slate-700">{group.label}</td>
                                                <td className="px-4 py-2 text-slate-700">{group.count}</td>
                                                <td className="px-4 py-2">{SeverityBadge(group.representative.severity)}</td>
                                                <td className="px-4 py-2 text-slate-700">{group.office}</td>
                                                <td className="px-4 py-2">{StatusBadge(group.representative.status)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <h3 className="text-lg font-semibold text-slate-900">Recent Alerts</h3>
                        </div>
                        {recent_alerts.length === 0 ? (
                            <p className="px-5 py-6 text-sm text-slate-500">No data in current filter scope</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Type</th>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Severity</th>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Office</th>
                                            <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {recent_alerts.slice(0, 5).map((row) => (
                                            <tr key={row.alerts_id}>
                                                <td className="px-4 py-2 text-slate-700">{formatStatusLabel(row.alert_type)}</td>
                                                <td className="px-4 py-2">{SeverityBadge(row.severity)}</td>
                                                <td className="px-4 py-2 text-slate-700">{row.office?.name ?? 'N/A'}</td>
                                                <td className="px-4 py-2">{StatusBadge(row.status)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function mergeGroupedTrend(
    requestTrend: Array<{ month: string; total: number }>,
    orderTrend: Array<{ month: string; total: number }>,
) {
    const requestMap = new Map(requestTrend.map((row) => [row.month, row.total]));
    const orderMap = new Map(orderTrend.map((row) => [row.month, row.total]));
    const allMonths = [...new Set([...requestMap.keys(), ...orderMap.keys()])].sort((a, b) => a.localeCompare(b));

    return allMonths.map((month) => ({
        label: formatMonth(month),
        first: requestMap.get(month) ?? 0,
        second: orderMap.get(month) ?? 0,
    }));
}

function formatMonth(value: string): string {
    const date = new Date(`${value}-01T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-PH', { month: 'short', year: 'numeric' });
}

function formatStatusLabel(value: string): string {
    const aliases: Record<string, string> = {
        APPROVED: 'Approved',
        RETURNED_FOR_REVISION: 'Returned for Revision',
        SUBMITTED: 'Submitted',
        PENDING: 'Pending',
    };

    const normalized = value.toUpperCase();

    if (aliases[normalized]) {
        return aliases[normalized];
    }

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

function buildDashboardAnomalyGroups(rows: Props['recent_anomalies']): Array<{ key: string; label: string; office: string; count: number; representative: Props['recent_anomalies'][number] }> {
    const grouped = new Map<string, { key: string; label: string; office: string; count: number; representative: Props['recent_anomalies'][number]; timestamp: number }>();

    rows.forEach((row) => {
        const office = row.office?.name ?? 'N/A';
        const sourceType = row.reference_type ? row.reference_type.split('\\').pop() ?? 'Source' : 'Source';
        const itemPart = row.item_id ? `item-${row.item_id}` : 'item-na';
        const key = `${row.anomaly_type}|${office}|${sourceType}|${itemPart}`;
        const timestamp = row.detected_at ? new Date(row.detected_at).getTime() : 0;

        const existing = grouped.get(key);

        if (!existing) {
            grouped.set(key, {
                key,
                label: formatAnomalyType(row.anomaly_type),
                office,
                count: 1,
                representative: row,
                timestamp: Number.isNaN(timestamp) ? 0 : timestamp,
            });

            return;
        }

        existing.count += 1;

        if ((Number.isNaN(timestamp) ? 0 : timestamp) >= existing.timestamp) {
            existing.timestamp = Number.isNaN(timestamp) ? 0 : timestamp;
            existing.representative = row;
        }
    });

    return [...grouped.values()]
        .sort((a, b) => (b.count - a.count) || (b.timestamp - a.timestamp))
        .map(({ key, label, office, count, representative }) => ({ key, label, office, count, representative }));
}

function MetricCard({ label, value }: { label: string; value: number | string }) {
    return (
        <div className="rounded-2xl border border-red-200 bg-gradient-to-r from-red-50 to-white p-6 shadow-[0_8px_18px_rgba(15,23,42,0.06)]">
            <p className="text-sm font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-2 text-4xl font-bold text-red-700">{value}</p>
        </div>
    );
}

function MiniStat({ label, value }: { label: string; value: number | string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-1 text-lg font-bold text-slate-900">{value}</p>
        </div>
    );
}

function RiskStrip({
    label,
    value,
    max,
    tone,
}: {
    label: string;
    value: number;
    max: number;
    tone: 'rose' | 'amber' | 'slate';
}) {
    const width = `${Math.max((value / max) * 100, value > 0 ? 8 : 0)}%`;
    const barTone: Record<typeof tone, string> = {
        rose: 'from-rose-700 to-rose-500',
        amber: 'from-amber-600 to-amber-400',
        slate: 'from-slate-700 to-slate-500',
    };

    return (
        <div className="space-y-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <div className="flex items-center justify-between">
                <p className="text-sm font-semibold text-slate-700">{label}</p>
                <p className="text-base font-bold text-slate-900">{value}</p>
            </div>
            <div className="h-2 rounded-full bg-slate-200">
                <div className={`h-2 rounded-full bg-gradient-to-r ${barTone[tone]}`} style={{ width }} />
            </div>
        </div>
    );
}

function SeverityBadge(value: string) {
    const normalized = value.toUpperCase();
    const tone = normalized === 'HIGH'
        ? 'border-rose-200 bg-rose-50 text-rose-700'
        : normalized === 'MEDIUM'
            ? 'border-amber-200 bg-amber-50 text-amber-700'
            : 'border-slate-200 bg-slate-50 text-slate-700';

    return <span className={`inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold ${tone}`}>{formatStatusLabel(value)}</span>;
}

function StatusBadge(value: string) {
    const normalized = value.toUpperCase();
    const tone = normalized.includes('OPEN')
        ? 'border-rose-200 bg-rose-50 text-rose-700'
        : normalized.includes('REVIEW')
            ? 'border-amber-200 bg-amber-50 text-amber-700'
            : 'border-emerald-200 bg-emerald-50 text-emerald-700';

    return <span className={`inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold ${tone}`}>{formatStatusLabel(value)}</span>;
}

function CompactEmpty({ text }: { text: string }) {
    return (
        <div className="rounded-xl border border-dashed border-red-200 bg-gradient-to-br from-red-50 to-white px-4 py-8 text-center text-sm text-slate-600">
            {text}
        </div>
    );
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(amount);
}

function csvValue(value: string): string {
    return `"${value.replaceAll('"', '""')}"`;
}

function downloadFile(content: string, fileName: string, mimeType: string): void {
    const blob = new Blob([content], { type: mimeType });
    const url = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = fileName;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.URL.revokeObjectURL(url);
}
