import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import { Head, router, useForm, usePage } from '@inertiajs/react';

type ArchiveRow = {
    archive_id: number;
    archive_year: number;
    archive_scope: string;
    file_name: string;
    file_size: number | null;
    generated_at?: string | null;
    generated_by?: {
        id: number;
        name: string;
    } | null;
    notes?: string | null;
    can_download: boolean;
    can_delete: boolean;
};

type Props = {
    records: {
        data: ArchiveRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    archiveScopes: string[];
    yearOptions: number[];
};

export default function ExecutiveRecordArchivesIndex({ records, archiveScopes, yearOptions }: Props) {
    const page = usePage().props as {
        flash?: {
            success?: string;
            error?: string;
        };
    };

    const { data, setData, post, processing, errors } = useForm({
        archive_year: yearOptions[0] ?? new Date().getFullYear(),
        archive_scope: archiveScopes[0] ?? 'governance_yearly',
        notes: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route('executive.record-archives.store'));
    };

    const deleteArchive = (archiveId: number) => {
        if (!window.confirm('Delete this record archive? This action cannot be undone.')) {
            return;
        }

        router.delete(route('executive.record-archives.destroy', archiveId));
    };

    return (
        <AuthenticatedLayout header="Record Archives">
            <Head title="Record Archives" />

            <div className="space-y-4 py-6">
                <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="mb-3">
                        <h3 className="text-lg font-semibold text-slate-800">Generate Governance Archive</h3>
                        <p className="text-sm text-slate-500">Yearly governance-level archive package for executive records.</p>
                    </div>

                    {page.flash?.success ? <div className="mb-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{page.flash.success}</div> : null}
                    {page.flash?.error ? <div className="mb-3 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{page.flash.error}</div> : null}

                    <form onSubmit={submit} className="grid gap-3 md:grid-cols-4">
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Archive Year</label>
                            <select
                                value={data.archive_year}
                                onChange={(event) => setData('archive_year', Number(event.target.value))}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                {yearOptions.map((year) => (
                                    <option key={year} value={year}>
                                        {year}
                                    </option>
                                ))}
                            </select>
                            {errors.archive_year ? <p className="mt-1 text-xs text-rose-600">{errors.archive_year}</p> : null}
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Archive Scope</label>
                            <select
                                value={data.archive_scope}
                                onChange={(event) => setData('archive_scope', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                {archiveScopes.map((scope) => (
                                    <option key={scope} value={scope}>
                                        {scope.replaceAll('_', ' ')}
                                    </option>
                                ))}
                            </select>
                            {errors.archive_scope ? <p className="mt-1 text-xs text-rose-600">{errors.archive_scope}</p> : null}
                        </div>

                        <div className="md:col-span-2">
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Notes (optional)</label>
                            <input
                                value={data.notes}
                                onChange={(event) => setData('notes', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="Add archive context"
                            />
                            {errors.notes ? <p className="mt-1 text-xs text-rose-600">{errors.notes}</p> : null}
                        </div>

                        <div className="md:col-span-4">
                            <button type="submit" disabled={processing} className="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60">
                                {processing ? 'Generating…' : 'Generate Archive'}
                            </button>
                        </div>
                    </form>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Year</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Scope</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">File</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Size</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Generated At</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Generated By</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Notes</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {records.data.map((row) => (
                                <tr key={row.archive_id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3">{row.archive_year}</td>
                                    <td className="px-4 py-3">{row.archive_scope.replaceAll('_', ' ')}</td>
                                    <td className="px-4 py-3">{row.file_name}</td>
                                    <td className="px-4 py-3">{formatBytes(row.file_size)}</td>
                                    <td className="px-4 py-3">{formatDate(row.generated_at)}</td>
                                    <td className="px-4 py-3">{row.generated_by?.name ?? 'System'}</td>
                                    <td className="px-4 py-3">{row.notes ?? '-'}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="inline-flex items-center gap-3">
                                            {row.can_download ? (
                                                <a href={route('executive.record-archives.download', row.archive_id)} className="font-semibold text-red-700 hover:text-red-900">
                                                    Download
                                                </a>
                                            ) : (
                                                <span className="text-slate-400">Unavailable</span>
                                            )}
                                            {row.can_delete ? (
                                                <button
                                                    type="button"
                                                    onClick={() => deleteArchive(row.archive_id)}
                                                    className="font-semibold text-rose-700 hover:text-rose-900"
                                                >
                                                    Delete
                                                </button>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {records.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-10 text-center text-slate-500">
                                        No record archives yet.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>

                <PaginationNav links={records.links} />
            </div>
        </AuthenticatedLayout>
    );
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString();
}

function formatBytes(value: number | null): string {
    if (value === null || Number.isNaN(value)) {
        return '-';
    }

    if (value < 1024) {
        return `${value} B`;
    }

    if (value < 1024 * 1024) {
        return `${(value / 1024).toFixed(1)} KB`;
    }

    return `${(value / (1024 * 1024)).toFixed(2)} MB`;
}

