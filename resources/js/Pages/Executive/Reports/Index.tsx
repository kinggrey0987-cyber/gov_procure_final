import { DonutChart, ExecutiveChartCard, HorizontalBarChart, TrendLineChart } from '@/Components/Executive/Charts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type ModuleSummary = {
    module_name: string;
    total: number;
};

type AuditRow = {
    id: number;
    module_name: string;
    action_type: string;
    created_at: string;
    user?: { id: number; name: string } | null;
    office?: { id: number; name: string } | null;
};

type Props = {
    counts: {
        business_audit_entries: number;
        anomalies: number;
        alerts: number;
        published_disclosures: number;
    };
    audit_modules: ModuleSummary[];
    audit_actions: Array<{ action_type: string; total: number }>;
    monthly_business_trend: Array<{ month: string; total: number }>;
    latest_business_logs: AuditRow[];
};

export default function ExecutiveReportsIndex({ counts, audit_modules, audit_actions, monthly_business_trend, latest_business_logs }: Props) {
    const riskMix = [
        { label: 'Anomalies', value: counts.anomalies },
        { label: 'Alerts', value: counts.alerts },
    ];

    return (
        <AuthenticatedLayout header="Reports">
            <Head title="Executive Reports" />

            <div className="space-y-6 py-8">
                <section className="overflow-hidden rounded-2xl border border-red-200 bg-gradient-to-r from-red-700 via-red-600 to-red-500 px-6 py-5 text-white shadow-[0_10px_28px_rgba(185,28,28,0.25)]">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-100">Executive Reporting</p>
                    <h3 className="mt-1 text-2xl font-semibold">Generate Reports and Oversight Evidence</h3>
                    <p className="mt-1 text-sm text-red-100">Download comprehensive reports for governance analysis and leadership review.</p>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card label="Business Audit Entries" value={counts.business_audit_entries} />
                    <Card label="Anomalies" value={counts.anomalies} />
                    <Card label="Alerts" value={counts.alerts} />
                    <Card label="Published Disclosures" value={counts.published_disclosures} />
                </div>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-xl font-semibold text-slate-900">Executive Reporting Shortcuts</h3>
                    </div>
                    <div className="flex flex-wrap gap-2 p-6">
                        <Link href={route('executive.audit.business')} className="rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            Open Audit Trail
                        </Link>
                        <Link href={route('executive.procurement-analytics')} className="rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                            Open Procurement Analytics
                        </Link>
                        <Link href={route('executive.transparency-monitoring')} className="rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                            Open Transparency Monitoring
                        </Link>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-12">
                    <ExecutiveChartCard
                        title="Monthly Business Audit Trend"
                        subtitle="Executive evidence generation velocity over time"
                        eyebrow="Trend"
                        tone="critical"
                    >
                        <TrendLineChart
                            data={monthly_business_trend.map((row) => ({ label: formatMonth(row.month), value: row.total }))}
                            emptyText="No monthly audit trend data is available."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-5">
                    <ExecutiveChartCard
                        title="Top Business Audit Modules"
                        subtitle="Light summary of module event concentration"
                        eyebrow="Snapshot"
                        tone="neutral"
                    >
                        <HorizontalBarChart
                            data={audit_modules.map((row) => ({ label: row.module_name, value: row.total }))}
                            emptyText="No audit module activity in the current reporting scope."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-3">
                    <ExecutiveChartCard
                        title="Risk Case Mix"
                        subtitle="Anomaly and alert volume"
                        eyebrow="Risk"
                        tone="warning"
                    >
                        <DonutChart data={riskMix} centerLabel="Cases" emptyText="No risk case counts are available." />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-4">
                    <ExecutiveChartCard
                        title="Action Type Distribution"
                        subtitle="Most frequent business log actions"
                        eyebrow="Action Mix"
                        tone="default"
                    >
                        <HorizontalBarChart
                            data={audit_actions.map((row) => ({ label: row.action_type || 'N/A', value: row.total }))}
                            emptyText="No action-type summary found in current report scope."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-7">
                    <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <div className="border-b border-slate-200 px-6 py-5">
                            <h3 className="text-xl font-semibold text-slate-900">Latest Business Logs</h3>
                        </div>
                        {latest_business_logs.length === 0 ? (
                            <p className="px-6 py-8 text-sm text-slate-500">No business logs found.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-4 py-2 text-left">Module</th>
                                            <th className="px-4 py-2 text-left">Action</th>
                                            <th className="px-4 py-2 text-left">User</th>
                                            <th className="px-4 py-2 text-left">Office</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {latest_business_logs.map((row) => (
                                            <tr key={row.id}>
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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function formatMonth(value: string): string {
    const date = new Date(`${value}-01T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-PH', { month: 'short', year: 'numeric' });
}

function Card({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-2xl border border-red-200 bg-gradient-to-r from-red-50 to-white p-6 shadow-[0_8px_18px_rgba(15,23,42,0.06)]">
            <p className="text-sm font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-2 text-4xl font-bold text-red-700">{value}</p>
        </div>
    );
}
