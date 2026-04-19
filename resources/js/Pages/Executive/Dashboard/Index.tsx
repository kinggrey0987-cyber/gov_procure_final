import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { DonutChart, ExecutiveChartCard, GroupedComparisonChart, HorizontalBarChart, TrendLineChart } from '@/Components/Executive/Charts';
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
    const officeOversight = summary.office_breakdown
        .map((office) => ({
            label: office.name,
            value: office.anomaly_count + office.alert_count,
        }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 8);

    const riskSnapshot = [
        { label: 'Open', first: summary.open_anomaly_count, second: summary.open_alert_count },
        { label: 'In Review', first: summary.in_review_anomaly_count, second: summary.in_review_alert_count },
        { label: 'High Severity', first: summary.high_severity_anomaly_count, second: summary.high_severity_alert_count },
    ];

    const procurementTrend = mergeTrend(
        procurement.monthly_trends.purchase_requests,
        procurement.monthly_trends.purchase_orders,
    );

    const approvalStatusMix = approvals.status_mix.map((row) => ({
        label: row.status,
        value: row.total,
    }));

    const alertSeverityMix = alert_severity_breakdown.map((row) => ({
        label: row.label || 'N/A',
        value: row.total,
    }));

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

            <div className="space-y-6 py-8">
                <section className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-200 bg-white px-5 py-4 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <div>
                        <h2 className="text-2xl font-semibold text-slate-900">Executive DSS Home</h2>
                        <p className="text-sm text-slate-600">Live oversight view</p>
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

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard label="Open Anomalies" value={summary.open_anomaly_count} tone="rose" />
                    <MetricCard label="Open Alerts" value={summary.open_alert_count} tone="amber" />
                    <MetricCard label="Purchase Requests" value={procurement.purchase_request_count} tone="sky" />
                    <MetricCard label="Published Disclosures" value={transparency.published_disclosure_count} tone="emerald" />
                </div>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-8">
                    <ExecutiveChartCard
                        title="Procurement Trend"
                        subtitle="PR and PO movement"
                        eyebrow="Main Insight"
                        tone="critical"
                    >
                        <TrendLineChart
                            data={procurementTrend}
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                    <ExecutiveChartCard
                        title="PPMP Workflow Mix"
                        subtitle="Approval state"
                        eyebrow="Main Insight"
                        tone="default"
                    >
                        <DonutChart
                            data={approvalStatusMix}
                            centerLabel="PPMP"
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-8">
                    <ExecutiveChartCard
                        title="Risk Snapshot"
                        subtitle="Anomalies vs alerts"
                        eyebrow="Secondary Insight"
                        tone="critical"
                    >
                        <GroupedComparisonChart
                            data={riskSnapshot}
                            firstLabel="Anomalies"
                            secondLabel="Alerts"
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                    <ExecutiveChartCard
                        title="Alert Severity Mix"
                        subtitle="Current alert pressure"
                        eyebrow="Secondary Insight"
                        tone="warning"
                    >
                        <DonutChart
                            data={alertSeverityMix}
                            centerLabel="Alerts"
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-12">
                    <ExecutiveChartCard
                        title="Office Oversight Comparison"
                        subtitle="Risk volume by office"
                        eyebrow="Comparative Insight"
                        tone="neutral"
                    >
                        <HorizontalBarChart data={officeOversight} emptyText="No data in current filter scope" />
                    </ExecutiveChartCard>
                    </div>
                </div>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-xl font-semibold text-slate-900">Latest Executive Logs</h3>
                    </div>
                    {recent_business_logs.length === 0 ? (
                        <p className="px-6 py-8 text-sm text-slate-500">No data in current filter scope</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left">When</th>
                                        <th className="px-4 py-2 text-left">Module</th>
                                        <th className="px-4 py-2 text-left">Action</th>
                                        <th className="px-4 py-2 text-left">User</th>
                                        <th className="px-4 py-2 text-left">Office</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {recent_business_logs.map((row) => (
                                        <tr key={row.id}>
                                            <td className="px-4 py-2">{new Date(row.created_at).toLocaleString()}</td>
                                            <td className="px-4 py-2">{row.module_name}</td>
                                            <td className="px-4 py-2">{row.action_type}</td>
                                            <td className="px-4 py-2">{row.user?.name ?? 'System'}</td>
                                            <td className="px-4 py-2">{row.office?.name ?? 'N/A'}</td>
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

function mergeTrend(
    requestTrend: Array<{ month: string; total: number }>,
    orderTrend: Array<{ month: string; total: number }>,
) {
    const monthMap = new Map<string, number>();

    requestTrend.forEach((row) => {
        monthMap.set(row.month, (monthMap.get(row.month) ?? 0) + row.total);
    });

    orderTrend.forEach((row) => {
        monthMap.set(row.month, (monthMap.get(row.month) ?? 0) + row.total);
    });

    return [...monthMap.entries()]
        .sort(([monthA], [monthB]) => monthA.localeCompare(monthB))
        .map(([month, total]) => ({
            label: formatMonth(month),
            value: total,
        }));
}

function formatMonth(value: string): string {
    const date = new Date(`${value}-01T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-PH', { month: 'short', year: 'numeric' });
}

function MetricCard({ label, value, tone }: { label: string; value: number; tone: 'rose' | 'amber' | 'sky' | 'emerald' }) {
    const toneClass: Record<typeof tone, string> = {
        rose: 'border-rose-300 bg-gradient-to-b from-rose-100 to-rose-50 text-rose-900 shadow-[0_8px_18px_rgba(244,63,94,0.15)]',
        amber: 'border-amber-300 bg-gradient-to-b from-amber-100 to-amber-50 text-amber-900 shadow-[0_8px_18px_rgba(245,158,11,0.14)]',
        sky: 'border-sky-300 bg-gradient-to-b from-sky-100 to-sky-50 text-sky-900 shadow-[0_8px_18px_rgba(56,189,248,0.14)]',
        emerald: 'border-emerald-300 bg-gradient-to-b from-emerald-100 to-emerald-50 text-emerald-900 shadow-[0_8px_18px_rgba(16,185,129,0.14)]',
    };

    return (
        <div className={`rounded-2xl border p-6 shadow-sm ${toneClass[tone]}`}>
            <p className="text-sm font-semibold uppercase tracking-wide">{label}</p>
            <p className="mt-2 text-4xl font-bold">{value}</p>
        </div>
    );
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 2,
    }).format(value ?? 0);
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
