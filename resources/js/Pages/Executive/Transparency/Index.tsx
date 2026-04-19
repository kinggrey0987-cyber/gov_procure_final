import { DonutChart, ExecutiveChartCard, HorizontalBarChart, TrendLineChart } from '@/Components/Executive/Charts';
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
        tab?: 'overview' | 'published' | 'drafts';
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
    office_publication_mix: Array<{ label: string; total: number }>;
    monthly_publication_trend: Array<{ month: string; total: number }>;
    recent_disclosures: DisclosureRow[];
    published_disclosures: Array<DisclosureRow & { file_path?: string | null }>;
    draft_disclosures: Array<DisclosureRow & { file_path?: string | null; updated_at?: string | null }>;
};

export default function TransparencyMonitoringIndex({
    filters,
    filter_options,
    totals,
    status_breakdown,
    type_breakdown,
    office_publication_mix,
    monthly_publication_trend,
    recent_disclosures,
    published_disclosures,
    draft_disclosures,
}: Props) {
    const [form, setForm] = useState({
        status: filters.status ?? '',
        type: filters.type ?? '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
    });

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            route('executive.transparency-monitoring'),
            {
                ...form,
                tab: activeTab,
            },
            { preserveState: true, replace: true },
        );
    };

    const pendingOrOtherCount = Math.max(totals.disclosure_count - totals.published_count - totals.draft_count, 0);
    const publicationStateSnapshot = [
        { label: 'Published', value: totals.published_count },
        { label: 'Draft', value: totals.draft_count },
        { label: 'Pending or Other', value: pendingOrOtherCount },
    ];

    const activeTab = filters.tab ?? 'overview';

    const switchTab = (tab: 'overview' | 'published' | 'drafts') => {
        router.get(
            route('executive.transparency-monitoring'),
            {
                ...form,
                tab,
            },
            { preserveState: true, replace: true },
        );
    };

    const exportCsv = () => {
        const lines = [
            ['disclosure_id', 'title', 'type', 'status', 'published_at'],
            ...recent_disclosures.map((row) => [
                String(row.disclosure_id),
                row.title,
                row.type,
                row.status,
                row.published_at ?? '',
            ]),
        ];

        const csv = lines.map((row) => row.map((column) => `"${String(column).replaceAll('"', '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = 'transparency-disclosures.csv';
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.URL.revokeObjectURL(url);
    };

    return (
        <AuthenticatedLayout header="Transparency & Disclosures">
            <Head title="Transparency & Disclosures" />

            <div className="space-y-6 py-8">
                <section className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-200 bg-white px-5 py-4 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <div>
                        <h2 className="text-2xl font-semibold text-slate-900">Transparency & Disclosures</h2>
                        <p className="text-sm text-slate-600">Disclosure governance module</p>
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
                        <div className="grid min-w-0 flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <select
                                className="h-10 rounded-lg border-slate-300 text-sm"
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
                                className="h-10 rounded-lg border-slate-300 text-sm"
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
                                className="h-10 rounded-lg border-slate-300 text-sm"
                                value={form.from_date}
                                onChange={(event) => setForm((current) => ({ ...current, from_date: event.target.value }))}
                            />

                            <input
                                type="date"
                                className="h-10 rounded-lg border-slate-300 text-sm"
                                value={form.to_date}
                                onChange={(event) => setForm((current) => ({ ...current, to_date: event.target.value }))}
                            />
                        </div>

                        <div className="ml-auto flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => router.get(route('executive.transparency-monitoring'))}
                                className="h-10 rounded-lg border border-red-300 px-4 text-sm font-semibold text-red-700 hover:bg-red-50"
                            >
                                Reset
                            </button>
                            <button type="submit" className="h-10 rounded-lg bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800">
                                Apply
                            </button>
                        </div>
                    </form>
                </section>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card label="Disclosures" value={totals.disclosure_count} />
                    <Card label="Published" value={totals.published_count} />
                    <Card label="Draft" value={totals.draft_count} />
                </div>

                <section className="rounded-2xl border border-red-200 bg-white p-2 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
                    <div className="grid grid-cols-3 gap-2">
                        <button
                            type="button"
                            onClick={() => switchTab('overview')}
                            className={`rounded-lg px-3 py-2 text-sm font-semibold ${activeTab === 'overview' ? 'bg-red-700 text-white' : 'bg-red-50 text-red-700'}`}
                        >
                            Overview
                        </button>
                        <button
                            type="button"
                            onClick={() => switchTab('published')}
                            className={`rounded-lg px-3 py-2 text-sm font-semibold ${activeTab === 'published' ? 'bg-red-700 text-white' : 'bg-red-50 text-red-700'}`}
                        >
                            Published
                        </button>
                        <button
                            type="button"
                            onClick={() => switchTab('drafts')}
                            className={`rounded-lg px-3 py-2 text-sm font-semibold ${activeTab === 'drafts' ? 'bg-red-700 text-white' : 'bg-red-50 text-red-700'}`}
                        >
                            Drafts / Review
                        </button>
                    </div>
                </section>

                {activeTab === 'overview' && (
                    <div className="grid gap-6 xl:grid-cols-12">
                        <div className="xl:col-span-12">
                        <ExecutiveChartCard
                            title="Publication Trend"
                            subtitle="Monthly publication movement"
                            eyebrow="Trend"
                            tone="critical"
                        >
                            <TrendLineChart
                                data={monthly_publication_trend.map((row) => ({ label: formatMonth(row.month), value: row.total }))}
                                emptyText="No data in current filter scope"
                            />
                        </ExecutiveChartCard>
                        </div>

                        <div className="xl:col-span-4">
                        <ExecutiveChartCard title="Status Mix" subtitle="Disclosure state" eyebrow="Status" tone="default">
                            <DonutChart
                                data={status_breakdown.map((row) => ({ label: row.status || 'N/A', value: row.total }))}
                                centerLabel="Status"
                                emptyText="No data in current filter scope"
                            />
                        </ExecutiveChartCard>
                        </div>

                        <div className="xl:col-span-4">
                        <ExecutiveChartCard title="Type Mix" subtitle="Disclosure categories" eyebrow="Type" tone="warning">
                            <HorizontalBarChart
                                data={type_breakdown.map((row) => ({ label: row.type || 'N/A', value: row.total }))}
                                emptyText="No data in current filter scope"
                            />
                        </ExecutiveChartCard>
                        </div>

                        <div className="xl:col-span-4">
                        <ExecutiveChartCard title="Publication Snapshot" subtitle="Published, draft, and pending" eyebrow="Snapshot" tone="neutral">
                            <HorizontalBarChart
                                data={publicationStateSnapshot}
                                emptyText="No data in current filter scope"
                            />
                        </ExecutiveChartCard>
                        </div>

                        <div className="xl:col-span-12">
                        <ExecutiveChartCard title="Office Publication Mix" subtitle="Published disclosures by office" eyebrow="Office Mix" tone="neutral">
                            <HorizontalBarChart
                                data={office_publication_mix.map((row) => ({ label: row.label || 'N/A', value: row.total }))}
                                emptyText="No data in current filter scope"
                            />
                        </ExecutiveChartCard>
                        </div>
                    </div>
                )}

                {activeTab === 'published' && (
                    <section className="space-y-3">
                        {published_disclosures.length === 0 ? (
                            <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-600">No data in current filter scope</div>
                        ) : (
                            published_disclosures.map((row) => (
                                <article key={row.disclosure_id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="text-lg font-semibold text-slate-900">{row.title}</p>
                                            <p className="text-sm text-slate-600">{row.type} | {row.publisher?.name ?? 'N/A'}</p>
                                        </div>
                                        <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">{row.status}</span>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center gap-2">
                                        <Link href={route('executive.disclosures.show', row.disclosure_id)} className="rounded-lg bg-red-700 px-3 py-2 text-xs font-semibold text-white hover:bg-red-800">
                                            View
                                        </Link>
                                        {row.file_path ? (
                                            <a href={route('public.disclosures.download', row.disclosure_id)} className="rounded-lg border border-red-300 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">
                                                Download File
                                            </a>
                                        ) : null}
                                    </div>
                                </article>
                            ))
                        )}
                    </section>
                )}

                {activeTab === 'drafts' && (
                    <section className="space-y-3">
                        <div className="flex justify-end">
                            <Link href={route('executive.disclosures.create')} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                                New Disclosure
                            </Link>
                        </div>

                        {draft_disclosures.length === 0 ? (
                            <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-600">No data in current filter scope</div>
                        ) : (
                            draft_disclosures.map((row) => (
                                <article key={row.disclosure_id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="text-lg font-semibold text-slate-900">{row.title}</p>
                                            <p className="text-sm text-slate-600">{row.type}</p>
                                        </div>
                                        <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">{row.status}</span>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center gap-2">
                                        <Link href={route('executive.disclosures.show', row.disclosure_id)} className="rounded-lg border border-red-300 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">
                                            Review
                                        </Link>
                                        <Link href={route('executive.disclosures.edit', row.disclosure_id)} className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Edit
                                        </Link>
                                    </div>
                                </article>
                            ))
                        )}
                    </section>
                )}
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
