import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { DonutChart, ExecutiveChartCard, HorizontalBarChart, TrendLineChart } from '@/Components/Executive/Charts';
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
    filter_options: {
        modules: string[];
        actions: string[];
        users: Array<{ id: number; name: string }>;
    };
};

export default function BusinessAudit({ auditLogs, filters, analytics, filter_options }: Props) {
    const selectedUser = filter_options.users.find((user) => String(user.id) === String(filters.user_id ?? ''));

    const [form, setForm] = useState({
        module_name: filters.module_name ?? '',
        action_type: filters.action_type ?? '',
        user_query: selectedUser ? `${selectedUser.name} (#${selectedUser.id})` : '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
    });

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();

        const matchedUser = filter_options.users.find((user) => `${user.name} (#${user.id})` === form.user_query);

        router.get(
            route('executive.audit.business'),
            {
                module_name: form.module_name,
                action_type: form.action_type,
                user_id: matchedUser ? matchedUser.id : '',
                from_date: form.from_date,
                to_date: form.to_date,
            },
            { preserveState: true, replace: true },
        );
    };

    const moduleSummary = analytics.module_summary.map((row) => ({
        label: formatModuleLabel(row.label || 'N/A'),
        value: row.total,
    }));

    const actionSummary = analytics.action_summary.map((row) => ({
        label: formatActionLabel(row.label || 'N/A'),
        value: row.total,
    }));

    const auditTrend = analytics.daily_trend.map((row) => ({
        label: formatDateLabel(row.label),
        value: row.total,
    }));

    const exportCsv = () => {
        const rows = [
            ['timestamp', 'user', 'module', 'action', 'reference', 'office'],
            ...auditLogs.data.map((log) => [
                new Date(log.created_at).toISOString(),
                log.user?.name ?? 'System',
                log.module_name,
                log.action_type,
                `${log.reference_type}#${log.reference_id}`,
                log.office?.name ?? 'N/A',
            ]),
        ];

        const csv = rows.map((row) => row.map((column) => `"${String(column).replaceAll('"', '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = 'governance-logs.csv';
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.URL.revokeObjectURL(url);
    };

    return (
        <AuthenticatedLayout header="Governance Logs">
            <Head title="Governance Logs" />
            <div className="space-y-6 py-8">
                <section className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-200 bg-white px-5 py-4 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <div>
                        <h2 className="text-2xl font-semibold text-slate-900">Governance Logs</h2>
                        <p className="text-sm text-slate-600">Governance activity evidence</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button type="button" onClick={() => window.print()} className="rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                            Export PDF
                        </button>
                        <button type="button" onClick={exportCsv} className="rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            Export CSV
                        </button>
                    </div>
                </section>

                <section className="rounded-2xl border border-red-200 bg-white p-5 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <form onSubmit={onSubmit} className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-0 flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            <select className="h-10 rounded-lg border-red-200 text-sm" value={form.module_name} onChange={(e) => setForm((v) => ({ ...v, module_name: e.target.value }))}>
                                <option value="">All modules</option>
                                {filter_options.modules.map((moduleName) => (
                                    <option key={moduleName} value={moduleName}>{formatModuleLabel(moduleName)}</option>
                                ))}
                            </select>

                            <select className="h-10 rounded-lg border-red-200 text-sm" value={form.action_type} onChange={(e) => setForm((v) => ({ ...v, action_type: e.target.value }))}>
                                <option value="">All actions</option>
                                {filter_options.actions.map((actionName) => (
                                    <option key={actionName} value={actionName}>{formatActionLabel(actionName)}</option>
                                ))}
                            </select>

                            <input
                                list="governance-users"
                                className="h-10 rounded-lg border-red-200 text-sm"
                                placeholder="Search user"
                                value={form.user_query}
                                onChange={(e) => setForm((v) => ({ ...v, user_query: e.target.value }))}
                            />
                            <datalist id="governance-users">
                                {filter_options.users.map((user) => (
                                    <option key={user.id} value={`${user.name} (#${user.id})`} />
                                ))}
                            </datalist>

                            <input type="date" className="h-10 rounded-lg border-red-200 text-sm" value={form.from_date} onChange={(e) => setForm((v) => ({ ...v, from_date: e.target.value }))} />
                            <input type="date" className="h-10 rounded-lg border-red-200 text-sm" value={form.to_date} onChange={(e) => setForm((v) => ({ ...v, to_date: e.target.value }))} />
                        </div>

                        <div className="ml-auto flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => router.get(route('executive.audit.business'))}
                                className="h-10 rounded-lg border border-red-300 px-4 text-sm font-semibold text-red-700 hover:bg-red-50"
                            >
                                Reset
                            </button>
                            <button className="h-10 rounded-lg bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800" type="submit">
                                Apply
                            </button>
                        </div>
                    </form>
                </section>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-12">
                    <ExecutiveChartCard
                        title="Activity Trend"
                        subtitle="Daily event volume"
                        tone="critical"
                    >
                        <TrendLineChart
                            data={auditTrend}
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard
                        title="Module Mix"
                        subtitle="Activity by module"
                        tone="neutral"
                    >
                        <HorizontalBarChart
                            data={moduleSummary}
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-6">
                    <ExecutiveChartCard
                        title="Action Mix"
                        subtitle="Event type distribution"
                        tone="default"
                    >
                        <DonutChart
                            data={actionSummary}
                            centerLabel="Actions"
                            emptyText="No data in current filter scope"
                        />
                    </ExecutiveChartCard>
                    </div>
                </div>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-xl font-semibold text-slate-900">Latest Business Logs</h3>
                    </div>

                    {auditLogs.data.length === 0 ? (
                        <p className="px-6 py-10 text-sm text-slate-500">No data in current filter scope</p>
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
                                            <td className="px-4 py-3 text-red-700">{formatActionLabel(log.action_type)}</td>
                                            <td className="px-4 py-3 text-slate-600">
                                                <p>{formatModuleLabel(log.module_name)}</p>
                                                <p className="text-xs">{log.office?.name ?? 'N/A'} - {formatReferenceLabel(log.reference_type)} #{log.reference_id}</p>
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

function formatReferenceLabel(value: string): string {
    const aliases: Record<string, string> = {
        'App\\Models\\Ppmp': 'PPMP Record',
        'App\\Models\\SupplyRequest': 'Supply Request',
        'App\\Models\\PurchaseRequest': 'Purchase Request',
        'App\\Models\\PurchaseOrder': 'Purchase Order',
        'App\\Models\\DistributionRecord': 'Distribution Record',
        'App\\Models\\Claim': 'Claim Record',
        'App\\Models\\PublicDisclosure': 'Public Disclosure',
        'App\\Models\\Alert': 'Alert Record',
        'App\\Models\\Anomaly': 'Anomaly Record',
    };

    return aliases[value] ?? value.split('\\').pop()?.replaceAll('_', ' ') ?? 'Record';
}
