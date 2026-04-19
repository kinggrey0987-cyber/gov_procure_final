import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ExecutiveChartCard, HorizontalBarChart, TrendLineChart } from '@/Components/Executive/Charts';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Props = {
    auditLogs: {
        data: Array<{
            id: number;
            log_domain: string;
            module_name: string;
            action_type: string;
            reference_type: string;
            reference_id: number;
            created_at: string;
            user?: { id: number; name: string } | null;
            office?: { id: number; name: string } | null;
        }>;
    };
    filters: {
        module_name?: string;
        action_type?: string;
        user_id?: number | string;
        from_date?: string;
        to_date?: string;
    };
    analytics: {
        module_summary: Array<{ label: string; total: number }>;
        action_summary: Array<{ label: string; total: number }>;
        daily_trend: Array<{ label: string; total: number }>;
    };
};

export default function BusinessAudit({ auditLogs, filters, analytics }: Props) {
    const [form, setForm] = useState({
        module_name: filters.module_name ?? '',
        action_type: filters.action_type ?? '',
        user_id: filters.user_id ? String(filters.user_id) : '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
    });

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('executive.audit.business'), form, { preserveState: true, replace: true });
    };

    const moduleSummary = analytics.module_summary.map((row) => ({
        label: row.label || 'N/A',
        value: row.total,
    }));

    const actionSummary = analytics.action_summary.map((row) => ({
        label: row.label || 'N/A',
        value: row.total,
    }));

    const auditTrend = analytics.daily_trend.map((row) => ({
        label: formatDateLabel(row.label),
        value: row.total,
    }));

    return (
        <AuthenticatedLayout header="Audit Trail">
            <Head title="Business Audit Logs" />
            <div className="space-y-6 py-8">
                <section className="overflow-hidden rounded-2xl border border-red-200 bg-white shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-red-200 bg-gradient-to-r from-red-700 via-red-600 to-red-500 px-6 py-4 text-white">
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-100">Business Governance Logs</p>
                        <p className="text-lg font-semibold">Executive Audit Controls</p>
                        <p className="text-sm text-red-100">Complete activity trail across oversight-relevant system actions.</p>
                    </div>
                    <div className="p-6">
                        <form onSubmit={onSubmit} className="grid gap-3 md:grid-cols-6">
                            <input className="rounded-lg border-red-200 text-sm" placeholder="Module" value={form.module_name} onChange={(e) => setForm((v) => ({ ...v, module_name: e.target.value }))} />
                            <input className="rounded-lg border-red-200 text-sm" placeholder="Action" value={form.action_type} onChange={(e) => setForm((v) => ({ ...v, action_type: e.target.value }))} />
                            <input className="rounded-lg border-red-200 text-sm" placeholder="User ID" value={form.user_id} onChange={(e) => setForm((v) => ({ ...v, user_id: e.target.value }))} />
                            <input type="date" className="rounded-lg border-red-200 text-sm" value={form.from_date} onChange={(e) => setForm((v) => ({ ...v, from_date: e.target.value }))} />
                            <input type="date" className="rounded-lg border-red-200 text-sm" value={form.to_date} onChange={(e) => setForm((v) => ({ ...v, to_date: e.target.value }))} />

                            <div className="md:col-span-6 flex flex-wrap justify-end gap-2 pt-1">
                                <button
                                    type="button"
                                    onClick={() => router.get(route('executive.audit.business'))}
                                    className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                                >
                                    Reset
                                </button>
                                <button className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800" type="submit">
                                    Apply
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-12">
                    <ExecutiveChartCard
                        title="Business Audit Activity Trend"
                        subtitle="Daily event volume from the active filter scope"
                        eyebrow="Trend"
                        tone="critical"
                    >
                        <TrendLineChart
                            data={auditTrend}
                            emptyText="No business audit trend data in the current filter scope."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard
                        title="Actions by Module"
                        subtitle="Module concentration in the current audit slice"
                        eyebrow="Module Mix"
                        tone="neutral"
                    >
                        <HorizontalBarChart
                            data={moduleSummary}
                            emptyText="No audit activity in the current filter scope."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard
                        title="Actions by Type"
                        subtitle="Most common action types in current filters"
                        eyebrow="Action Mix"
                        tone="default"
                    >
                        <HorizontalBarChart
                            data={actionSummary}
                            emptyText="No action summary is available in the current filter scope."
                        />
                    </ExecutiveChartCard>
                    </div>
                </div>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-xl font-semibold text-slate-900">System Audit Trail</h3>
                        <p className="mt-1 text-sm text-slate-600">Complete activity log of executive-facing business transactions.</p>
                    </div>

                    {auditLogs.data.length === 0 ? (
                        <p className="px-6 py-10 text-sm text-slate-500">No audit logs found for the selected filters.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Timestamp</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">User</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Action</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Details</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {auditLogs.data.map((log) => (
                                        <tr key={log.id} className="align-top">
                                            <td className="px-4 py-3 text-slate-600">{new Date(log.created_at).toLocaleString()}</td>
                                            <td className="px-4 py-3 font-semibold text-slate-800">{log.user?.name ?? 'System'}</td>
                                            <td className="px-4 py-3 text-red-700">{log.action_type}</td>
                                            <td className="px-4 py-3 text-slate-600">
                                                <p>{log.module_name}</p>
                                                <p className="text-xs">{log.office?.name ?? 'N/A'} - {log.reference_type} #{log.reference_id}</p>
                                            </td>
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

function formatDateLabel(value: string): string {
    const parsed = new Date(`${value}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}
