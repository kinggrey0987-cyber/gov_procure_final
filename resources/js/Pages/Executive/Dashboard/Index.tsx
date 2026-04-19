import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { DonutChart, ExecutiveChartCard, GroupedComparisonChart, HorizontalBarChart, TrendLineChart } from '@/Components/Executive/Charts';
import { Head, Link } from '@inertiajs/react';

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
    anomaly_type_breakdown,
    alert_severity_breakdown,
    recent_business_logs,
}: Props) {
    const officePulse = summary.office_breakdown
        .map((office) => ({
            label: office.name,
            first: office.anomaly_count,
            second: office.alert_count,
            total: office.anomaly_count + office.alert_count,
        }))
        .sort((a, b) => b.total - a.total)
        .slice(0, 6)
        .map(({ label, first, second }) => ({ label, first, second }));

    const riskMix = [
        { label: 'Open Anomalies', value: summary.open_anomaly_count },
        { label: 'In Review Anomalies', value: summary.in_review_anomaly_count },
        { label: 'High Severity Anomalies', value: summary.high_severity_anomaly_count },
        { label: 'Open Alerts', value: summary.open_alert_count },
        { label: 'In Review Alerts', value: summary.in_review_alert_count },
        { label: 'High Severity Alerts', value: summary.high_severity_alert_count },
    ];

    const procurementSnapshot = [
        { label: 'Purchase Requests', value: procurement.purchase_request_count },
        { label: 'Purchase Orders', value: procurement.purchase_order_count },
    ];

    const disclosureSnapshot = [
        { label: 'Published', value: transparency.published_disclosure_count },
        { label: 'Draft', value: transparency.draft_disclosure_count },
    ];

    const procurementTrend = mergeTrend(
        procurement.monthly_trends.purchase_requests,
        procurement.monthly_trends.purchase_orders,
    );

    const approvalStatusMix = approvals.status_mix.map((row) => ({
        label: row.status,
        value: row.total,
    }));

    const disclosureStatusMix = transparency.status_breakdown.map((row) => ({
        label: row.status || 'N/A',
        value: row.total,
    }));

    const executiveSignals = [
        { label: 'Cross Office Anomalies', value: String(summary.cross_office_anomaly_count) },
        { label: 'Purchase Orders', value: String(procurement.purchase_order_count) },
        { label: 'Draft Disclosures', value: String(transparency.draft_disclosure_count) },
        { label: 'Total PO Amount', value: formatCurrency(procurement.total_purchase_order_amount) },
    ];

    return (
        <AuthenticatedLayout header="Executive Dashboard">
            <Head title="Executive Dashboard" />

            <div className="space-y-6 py-8">
                <section className="overflow-hidden rounded-2xl border border-red-200 bg-gradient-to-r from-red-700 via-red-600 to-red-500 px-6 py-5 text-white shadow-[0_10px_28px_rgba(185,28,28,0.25)]">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-100">Executive Risk Brief</p>
                    <h3 className="mt-1 text-2xl font-semibold">Procurement Oversight and Irregularity Signals</h3>
                    <p className="mt-1 text-sm text-red-100">Live indicators from anomalies, alerts, purchasing activity, and disclosure governance.</p>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard label="Open Anomalies" value={summary.open_anomaly_count} tone="rose" />
                    <MetricCard label="Open Alerts" value={summary.open_alert_count} tone="amber" />
                    <MetricCard label="Purchase Requests" value={procurement.purchase_request_count} tone="sky" />
                    <MetricCard label="Published Disclosures" value={transparency.published_disclosure_count} tone="emerald" />
                </div>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 className="text-xl font-semibold text-slate-900">Executive Approval Queue</h3>
                                <p className="mt-1 text-sm text-slate-600">Final PPMP signoff pipeline for executive authority decisions.</p>
                            </div>
                            <Link href={route('executive.ppmp-approvals.index')} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                                Open Executive Approvals
                            </Link>
                        </div>
                    </div>
                    <div className="grid gap-3 p-6 md:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-amber-800">Pending PPMP Approvals</p>
                            <p className="mt-2 text-2xl font-semibold text-slate-900">{approvals.pending_ppmp_approvals}</p>
                        </div>
                        <div className="rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-amber-800">PPMP Records in Workflow</p>
                            <p className="mt-2 text-2xl font-semibold text-slate-900">{approvals.ppmp_workflow_total}</p>
                        </div>
                        <div className="rounded-xl border border-blue-200 bg-blue-50/70 px-4 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-blue-800">Returned for Revision</p>
                            <p className="mt-2 text-2xl font-semibold text-slate-900">{approvals.returned_for_revision}</p>
                        </div>
                        <div className="rounded-xl border border-emerald-200 bg-emerald-50/70 px-4 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-emerald-800">Recently Approved</p>
                            <p className="mt-2 text-2xl font-semibold text-slate-900">{approvals.recently_approved}</p>
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-xl font-semibold text-slate-900">Executive Summary</h3>
                        <p className="mt-1 text-sm text-slate-600">High-level governance signals across procurement, risk, and transparency.</p>
                    </div>
                    <div className="grid gap-3 p-6 md:grid-cols-2 lg:grid-cols-4">
                        {executiveSignals.map((signal) => (
                            <div key={signal.label} className="rounded-xl border border-red-100 bg-gradient-to-b from-red-50/60 to-white px-4 py-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-600">{signal.label}</p>
                                <p className="mt-2 text-2xl font-semibold text-slate-900">{signal.value}</p>
                            </div>
                        ))}
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-8">
                    <ExecutiveChartCard
                        title="Procurement Activity Trend"
                        subtitle="Requests and orders over time for executive capacity monitoring"
                        eyebrow="Trend"
                        tone="critical"
                    >
                        <TrendLineChart
                            data={procurementTrend}
                            emptyText="No procurement trend activity found yet."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                    <ExecutiveChartCard
                        title="PPMP Workflow Mix"
                        subtitle="Current approval-status distribution"
                        eyebrow="Approvals"
                        tone="default"
                    >
                        <DonutChart
                            data={approvalStatusMix}
                            centerLabel="PPMP"
                            emptyText="No PPMP workflow records in the current scope."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-7">
                    <ExecutiveChartCard
                        title="Anomaly and Alert Overview"
                        subtitle="Live anomaly and alert pressure by review state"
                        eyebrow="Risk Snapshot"
                        tone="critical"
                    >
                        <HorizontalBarChart data={riskMix} emptyText="No anomaly or alert records available yet." />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-5">
                    <ExecutiveChartCard
                        title="Office Oversight Comparison"
                        subtitle="Top offices by combined anomaly and alert volume"
                        eyebrow="Cross-Office"
                        tone="default"
                    >
                        <GroupedComparisonChart
                            data={officePulse}
                            firstLabel="Anomalies"
                            secondLabel="Alerts"
                            emptyText="No office-level anomaly or alert data available."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard
                        title="Procurement Activity Snapshot"
                        subtitle="Current filtered purchasing volume summary"
                        eyebrow="Procurement"
                        tone="neutral"
                    >
                        <HorizontalBarChart data={procurementSnapshot} emptyText="No procurement activity found in the current scope." />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard
                        title="Transparency Disclosure Snapshot"
                        subtitle="Published versus draft disclosure count"
                        eyebrow="Transparency"
                        tone="warning"
                    >
                        <HorizontalBarChart data={disclosureSnapshot} emptyText="No disclosure records found in the current scope." />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard
                        title="Anomaly Type Mix"
                        subtitle="Top anomaly categories requiring executive attention"
                        eyebrow="Risk Signals"
                        tone="critical"
                    >
                        <HorizontalBarChart
                            data={anomaly_type_breakdown.map((row) => ({ label: row.label || 'N/A', value: row.total }))}
                            emptyText="No anomaly type records found in the current scope."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard
                        title="Alert Severity Mix"
                        subtitle="Severity distribution from current alert inventory"
                        eyebrow="Alert Pressure"
                        tone="warning"
                    >
                        <HorizontalBarChart
                            data={alert_severity_breakdown.map((row) => ({ label: row.label || 'N/A', value: row.total }))}
                            emptyText="No alert severity data found in the current scope."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-12">
                    <ExecutiveChartCard
                        title="Disclosure Governance Mix"
                        subtitle="Status composition of disclosure records"
                        eyebrow="Transparency"
                        tone="neutral"
                    >
                        <DonutChart
                            data={disclosureStatusMix}
                            centerLabel="Disclosures"
                            emptyText="No disclosure status records found in the current scope."
                        />
                    </ExecutiveChartCard>
                    </div>
                </div>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-xl font-semibold text-slate-900">Latest Executive Audit Evidence</h3>
                        <p className="mt-1 text-sm text-slate-600">Recent business-log activities linked to executive oversight decisions.</p>
                    </div>
                    {recent_business_logs.length === 0 ? (
                        <p className="px-6 py-8 text-sm text-slate-500">No recent business logs are available.</p>
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

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-xl font-semibold text-slate-900">Executive Navigation</h3>
                        <p className="mt-1 text-sm text-slate-600">Jump to detailed analytics, risk monitoring, and governance evidence trails.</p>
                    </div>
                    <div className="grid gap-3 p-6 sm:grid-cols-2 lg:grid-cols-4">
                        <Link href={route('executive.procurement-analytics')} className="rounded-xl border border-red-200 bg-red-50/40 px-4 py-5 text-center text-sm font-semibold text-red-800 transition hover:border-red-500 hover:bg-red-100/50">
                            Procurement Analytics
                        </Link>
                        <Link href={route('executive.anomalies-monitoring')} className="rounded-xl border border-red-200 bg-red-50/40 px-4 py-5 text-center text-sm font-semibold text-red-800 transition hover:border-red-500 hover:bg-red-100/50">
                            Anomalies and Alerts
                        </Link>
                        <Link href={route('executive.ppmp-approvals.index')} className="rounded-xl border border-red-200 bg-red-50/40 px-4 py-5 text-center text-sm font-semibold text-red-800 transition hover:border-red-500 hover:bg-red-100/50">
                            Executive Approvals
                        </Link>
                        <Link href={route('executive.audit.business')} className="rounded-xl border border-red-200 bg-red-50/40 px-4 py-5 text-center text-sm font-semibold text-red-800 transition hover:border-red-500 hover:bg-red-100/50">
                            Audit Trail
                        </Link>
                        <Link href={route('executive.transparency-monitoring')} className="rounded-xl border border-red-200 bg-red-50/40 px-4 py-5 text-center text-sm font-semibold text-red-800 transition hover:border-red-500 hover:bg-red-100/50">
                            Transparency Monitoring
                        </Link>
                    </div>
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
