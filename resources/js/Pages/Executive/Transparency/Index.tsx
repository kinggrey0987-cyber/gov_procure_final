import { ExecutiveChartCard, HorizontalBarChart } from '@/Components/Executive/Charts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type DisclosureRow = {
    disclosure_id: number;
    title: string;
    type: string;
    status: string;
    published_at: string | null;
    publisher?: { id: number; name: string } | null;
};

type Props = {
    filters: {
        status?: string | null;
        type?: string | null;
        from_date?: string | null;
        to_date?: string | null;
    };
    filter_options: {
        statuses: string[];
        types: string[];
    };
    totals: {
        disclosure_count: number;
        published_count: number;
        draft_count: number;
    };
    status_breakdown: Array<{ status: string; total: number }>;
    type_breakdown: Array<{ type: string; total: number }>;
    recent_disclosures: DisclosureRow[];
};

export default function TransparencyMonitoringIndex({
    filters,
    filter_options,
    totals,
    status_breakdown,
    type_breakdown,
    recent_disclosures,
}: Props) {
    const [form, setForm] = useState({
        status: filters.status ?? '',
        type: filters.type ?? '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
    });

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('executive.transparency-monitoring'), form, { preserveState: true, replace: true });
    };

    const pendingOrOtherCount = Math.max(totals.disclosure_count - totals.published_count - totals.draft_count, 0);
    const publicationStateSnapshot = [
        { label: 'Published', value: totals.published_count },
        { label: 'Draft', value: totals.draft_count },
        { label: 'Pending or Other', value: pendingOrOtherCount },
    ];

    return (
        <AuthenticatedLayout header="Transparency Monitoring">
            <Head title="Transparency Monitoring" />

            <div className="space-y-6 py-8">
                <section className="rounded-2xl border border-red-200 bg-white p-6 shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                    <div className="mb-4">
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-700">Transparency Controls</p>
                        <h3 className="text-xl font-semibold text-slate-900">Filter Transparency Scope</h3>
                        <p className="mt-1 text-sm text-slate-600">Review disclosure health by status, type, and published timeframe.</p>
                    </div>

                    <form onSubmit={onSubmit} className="grid gap-3 md:grid-cols-5">
                        <select
                            className="rounded-lg border-slate-300 text-sm"
                            value={form.status}
                            onChange={(event) => setForm((current) => ({ ...current, status: event.target.value }))}
                        >
                            <option value="">All statuses</option>
                            {filter_options.statuses.map((status) => (
                                <option key={status} value={status}>
                                    {status}
                                </option>
                            ))}
                        </select>

                        <select
                            className="rounded-lg border-slate-300 text-sm"
                            value={form.type}
                            onChange={(event) => setForm((current) => ({ ...current, type: event.target.value }))}
                        >
                            <option value="">All disclosure types</option>
                            {filter_options.types.map((type) => (
                                <option key={type} value={type}>
                                    {type}
                                </option>
                            ))}
                        </select>

                        <input
                            type="date"
                            className="rounded-lg border-slate-300 text-sm"
                            value={form.from_date}
                            onChange={(event) => setForm((current) => ({ ...current, from_date: event.target.value }))}
                        />

                        <input
                            type="date"
                            className="rounded-lg border-slate-300 text-sm"
                            value={form.to_date}
                            onChange={(event) => setForm((current) => ({ ...current, to_date: event.target.value }))}
                        />

                        <button type="submit" className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            Apply
                        </button>
                    </form>

                    <div className="mt-4">
                        <Link href={route('executive.disclosures.index')} className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                            Open Disclosure Review
                        </Link>
                    </div>
                </section>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card label="Disclosures" value={totals.disclosure_count} />
                    <Card label="Published" value={totals.published_count} />
                    <Card label="Draft" value={totals.draft_count} />
                </div>

                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-7">
                    <ExecutiveChartCard
                        title="Published vs Draft or Pending"
                        subtitle="Topline publication state for the current transparency scope"
                        eyebrow="Publication Snapshot"
                        tone="critical"
                    >
                        <HorizontalBarChart
                            data={publicationStateSnapshot}
                            emptyText="No disclosures are available in the current transparency scope."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-5">
                    <ExecutiveChartCard title="Status Breakdown" subtitle="Distribution by disclosure status" eyebrow="Governance State" tone="default">
                        <HorizontalBarChart
                            data={status_breakdown.map((row) => ({ label: row.status || 'N/A', value: row.total }))}
                            emptyText="No status breakdown available for the selected filters."
                        />
                    </ExecutiveChartCard>
                    </div>

                    <div className="xl:col-span-12">
                    <ExecutiveChartCard title="Type Breakdown" subtitle="Distribution by disclosure type" eyebrow="Disclosure Mix" tone="warning">
                        <HorizontalBarChart
                            data={type_breakdown.map((row) => ({ label: row.type || 'N/A', value: row.total }))}
                            emptyText="No type breakdown available for the selected filters."
                        />
                    </ExecutiveChartCard>
                    </div>
                </div>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-xl font-semibold text-slate-800">Recent Disclosures</h3>
                    </div>
                    {recent_disclosures.length === 0 ? (
                        <p className="px-6 py-8 text-sm text-slate-500">No disclosures found for the selected filters.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left">Title</th>
                                        <th className="px-4 py-2 text-left">Type</th>
                                        <th className="px-4 py-2 text-left">Status</th>
                                        <th className="px-4 py-2 text-left">Publisher</th>
                                        <th className="px-4 py-2 text-left">Published</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {recent_disclosures.map((row) => (
                                        <tr key={row.disclosure_id}>
                                            <td className="px-4 py-2">{row.title}</td>
                                            <td className="px-4 py-2">{row.type}</td>
                                            <td className="px-4 py-2">{row.status}</td>
                                            <td className="px-4 py-2">{row.publisher?.name ?? 'N/A'}</td>
                                            <td className="px-4 py-2">{row.published_at ? new Date(row.published_at).toLocaleDateString() : 'N/A'}</td>
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

function Card({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-2xl border border-red-200 bg-gradient-to-r from-red-50 to-white p-6 shadow-[0_8px_18px_rgba(15,23,42,0.06)]">
            <p className="text-sm font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-2 text-4xl font-bold text-red-700">{value}</p>
        </div>
    );
}
