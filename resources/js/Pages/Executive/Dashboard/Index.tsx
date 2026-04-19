import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { DonutChart, ExecutiveChartCard, HorizontalBarChart, TrendGroupedBarChart } from '@/Components/Executive/Charts';
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
    alert_severity_breakdown: Array<{ label: string; total: number }>;
    recent_business_logs: Array<{
        id: number;
        module_name: string;
        action_type: string;
        created_at: string;
        user?: { id: number; name: string } | null;
        office?: { id: number; name: string } | null;
    }>;
};

export default function ExecutiveDashboardIndex({
    summary,
    procurement,
    transparency,
    approvals,
    alert_severity_breakdown,
    recent_business_logs,
}: Props) {
    const activeAnomalies = summary.open_anomaly_count + summary.in_review_anomaly_count;
    const activeAlerts = summary.open_alert_count + summary.in_review_alert_count;

    const officeOversight = summary.office_breakdown
        .map((office) => ({
            label: office.name,
            value: office.anomaly_count + office.alert_count,
        }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 8);

    const procurementTrend = mergeGroupedTrend(
        procurement.monthly_trends.purchase_requests,
        procurement.monthly_trends.purchase_orders,
    );

    const anomalyRanked = [
        { label: 'Open anomalies', value: summary.open_anomaly_count },
        { label: 'In review anomalies', value: summary.in_review_anomaly_count },
        { label: 'High severity anomalies', value: summary.high_severity_anomaly_count },
    ].sort((a, b) => b.value - a.value);

    const approvalStatusMix = approvals.status_mix.map((row) => ({
        label: row.status,
        value: row.total,
    }));

    const alertSeverityMix = alert_severity_breakdown
        .map((row) => ({
            label: row.label || 'N/A',
            value: row.total,
        }))
        .sort((a, b) => b.value - a.value);

    const throughputMix = [
        { label: 'Purchase Requests', value: procurement.purchase_request_count },
        { label: 'Purchase Orders', value: procurement.purchase_order_count },
        { label: 'Pending PPMP Approvals', value: approvals.pending_ppmp_approvals },
    ].sort((a, b) => b.value - a.value);

    const latestLogs = recent_business_logs.slice(0, 5);

    const exportCsv = () => {
        const lines = [
            ['metric', 'value'],
            ['open_anomalies', String(summary.open_anomaly_count)],
            ['open_alerts', String(summary.open_alert_count)],
            ['purchase_requests', String(procurement.purchase_request_count)],
            ['published_disclosures', String(transparency.published_disclosure_count)],
            ['purchase_orders', String(procurement.purchase_order_count)],
            ['total_po_amount', String(procurement.total_purchase_order_amount)],
        ];

        const csv = lines.map((row) => row.map(csvValue).join(',')).join('\n');
        downloadFile(csv, 'executive-dashboard.csv', 'text/csv;charset=utf-8;');
    };

    return (
        <AuthenticatedLayout header="Executive Dashboard">
            <Head title="Executive Dashboard" />

            <div className="space-y-5 py-8">
                <section className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-200 bg-white px-5 py-4 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <div>
                        <h2 className="text-2xl font-semibold text-slate-900">Executive Oversight Overview</h2>
                        <p className="text-sm text-slate-600">Summary first, trend signals second, evidence details last.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => window.print()}
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

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <MetricCard label="Purchase Requests" value={procurement.purchase_request_count} tone="neutral" />
                    <MetricCard label="Purchase Orders" value={procurement.purchase_order_count} tone="neutral" />
                    <MetricCard
                        label="Procurement Spend"
                        value={formatCurrency(procurement.total_purchase_order_amount)}
                        tone="sky"
                    />
                    <MetricCard label="Active Anomalies" value={activeAnomalies} tone="rose" />
                    <MetricCard label="Active Alerts" value={activeAlerts} tone="amber" />
                    <MetricCard label="Published Disclosures" value={transparency.published_disclosure_count} tone="emerald" />
                </div>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-8">
                        <ExecutiveChartCard
                            title="Procurement Activity Trend"
                            subtitle="PR versus PO movement"
                            tone="neutral"
                        >
                            {procurementTrend.length === 0 ? (
                                <p className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-600">
                                    No data in current filter scope.
                                </p>
                            ) : procurementTrend.length === 1 ? (
                                <div className="grid gap-3 md:grid-cols-3">
                                    <MiniStat label="Month" value={procurementTrend[0].label} />
                                    <MiniStat label="Purchase Requests" value={procurementTrend[0].first} />
                                    <MiniStat label="Purchase Orders" value={procurementTrend[0].second} />
                                </div>
                            ) : (
                                <TrendGroupedBarChart
                                    data={procurementTrend}
                                    firstLabel="Purchase Requests"
                                    secondLabel="Purchase Orders"
                                    emptyText="No data in current filter scope"
                                />
                            )}
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                        <ExecutiveChartCard
                            title="PPMP Workflow Summary"
                            subtitle="Approval status distribution"
                            tone="default"
                        >
                            <div className="space-y-4">
                                <DonutChart
                                    data={approvalStatusMix}
                                    centerLabel="PPMP"
                                    emptyText="No data in current filter scope"
                                />
                                <div className="grid grid-cols-3 gap-2">
                                    <MiniStat label="Pending" value={approvals.pending_ppmp_approvals} />
                                    <MiniStat label="Returned" value={approvals.returned_for_revision} />
                                    <MiniStat label="Approved" value={approvals.recently_approved} />
                                </div>
                            </div>
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                        <ExecutiveChartCard
                            title="Risk Summary"
                            subtitle="Active anomaly and alert pressure"
                            tone="critical"
                        >
                            <div className="space-y-3">
                                <RiskStrip
                                    label="Active Anomalies"
                                    value={activeAnomalies}
                                    max={Math.max(activeAnomalies, activeAlerts, 1)}
                                    tone="rose"
                                />
                                <RiskStrip
                                    label="Active Alerts"
                                    value={activeAlerts}
                                    max={Math.max(activeAnomalies, activeAlerts, 1)}
                                    tone="amber"
                                />
                                <RiskStrip
                                    label="High Severity (Anomaly + Alert)"
                                    value={summary.high_severity_anomaly_count + summary.high_severity_alert_count}
                                    max={Math.max(activeAnomalies, activeAlerts, 1)}
                                    tone="slate"
                                />
                            </div>
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-8">
                        <ExecutiveChartCard
                            title="Risk Breakdown"
                            subtitle="Top anomaly focus and alert severity"
                            tone="warning"
                        >
                            <div className="grid gap-5 lg:grid-cols-2">
                                <div>
                                    <p className="mb-2 text-sm font-semibold text-slate-700">Top anomaly categories</p>
                                    <HorizontalBarChart data={anomalyRanked} emptyText="No data in current filter scope" />
                                </div>
                                <div>
                                    <p className="mb-2 text-sm font-semibold text-slate-700">Alert severity summary</p>
                                    <HorizontalBarChart data={alertSeverityMix} emptyText="No data in current filter scope" />
                                </div>
                            </div>
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                        <ExecutiveChartCard
                            title="Procurement Throughput"
                            subtitle="Volume by process lane"
                            tone="neutral"
                        >
                            <HorizontalBarChart data={throughputMix} emptyText="No data in current filter scope" />
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-8">
                        <ExecutiveChartCard
                            title="Office Oversight Comparison"
                            subtitle="Risk concentration by office"
                            tone="neutral"
                        >
                            <HorizontalBarChart data={officeOversight} emptyText="No data in current filter scope" />
                        </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                        <ExecutiveChartCard
                            title="Disclosure Summary"
                            subtitle="Transparency publication snapshot"
                            tone="default"
                        >
                            <div className="grid gap-2">
                                <MiniStat label="Published" value={transparency.published_disclosure_count} />
                                <MiniStat label="Draft" value={transparency.draft_disclosure_count} />
                                <MiniStat label="Public Reports Available" value={transparency.published_disclosure_count} />
                            </div>
                        </ExecutiveChartCard>
                    </div>
                </div>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h3 className="text-lg font-semibold text-slate-900">Recent Executive Activity</h3>
                    </div>
                    {latestLogs.length === 0 ? (
                        <p className="px-5 py-6 text-sm text-slate-500">No data in current filter scope</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">When</th>
                                        <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Module</th>
                                        <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Action</th>
                                        <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">User</th>
                                        <th className="px-4 py-2 text-left text-xs uppercase tracking-wide text-slate-600">Office</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {latestLogs.map((row) => (
                                        <tr key={row.id}>
                                            <td className="px-4 py-2 text-slate-700">{new Date(row.created_at).toLocaleString()}</td>
                                            <td className="px-4 py-2 text-slate-700">{formatModuleLabel(row.module_name)}</td>
                                            <td className="px-4 py-2">
                                                <span className="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                                    {formatActionLabel(row.action_type)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2 text-slate-700">{row.user?.name ?? 'System'}</td>
                                            <td className="px-4 py-2 text-slate-700">{row.office?.name ?? 'N/A'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
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

function MetricCard({
    label,
    value,
    tone,
}: {
    label: string;
    value: number | string;
    tone: 'rose' | 'amber' | 'sky' | 'emerald' | 'neutral';
}) {
    const toneClass: Record<typeof tone, string> = {
        rose: 'border-red-200 bg-gradient-to-r from-red-50 to-white text-slate-900 shadow-[0_8px_18px_rgba(15,23,42,0.06)]',
        amber: 'border-red-200 bg-gradient-to-r from-red-50 to-white text-slate-900 shadow-[0_8px_18px_rgba(15,23,42,0.06)]',
        sky: 'border-red-200 bg-gradient-to-r from-red-50 to-white text-slate-900 shadow-[0_8px_18px_rgba(15,23,42,0.06)]',
        emerald: 'border-red-200 bg-gradient-to-r from-red-50 to-white text-slate-900 shadow-[0_8px_18px_rgba(15,23,42,0.06)]',
        neutral: 'border-red-200 bg-gradient-to-r from-red-50 to-white text-slate-900 shadow-[0_8px_18px_rgba(15,23,42,0.06)]',
    };

    return (
        <div className={`flex min-h-[132px] flex-col justify-between rounded-2xl border p-4 ${toneClass[tone]}`}>
            <p className="text-xs font-semibold uppercase tracking-wide">{label}</p>
            <p className="mt-2 text-3xl font-bold leading-none sm:text-[2rem]">{value}</p>
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

function formatModuleLabel(value: string): string {
    const aliases: Record<string, string> = {
        'Executive.Anomaly': 'Anomaly Detection',
        'Executive.Alert': 'Alert Monitoring',
        'Executive.PPMP': 'PPMP Approval',
        'Executive.PublicDisclosure': 'Transparency Disclosure',
        'Office.SupplyRequest': 'Supply Request Submission',
        'Procurement.PurchaseOrder': 'Procurement Order Processing',
        'Inventory.Distribution': 'Distribution Activity',
        'Inventory.Claim': 'Claims Review',
    };

    return aliases[value] ?? value.replaceAll('.', ' ').replaceAll('_', ' ');
}

function formatActionLabel(value: string): string {
    const aliases: Record<string, string> = {
        CREATE_DRAFT: 'Draft Created',
        UPDATE_DRAFT: 'Draft Updated',
        PUBLISH: 'Disclosure Published',
        APPROVE: 'Approved',
        REJECTED: 'Rejected',
        RETURNED_FOR_REVISION: 'Returned for Revision',
    };

    const raw = aliases[value] ?? value;
    return raw.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}
