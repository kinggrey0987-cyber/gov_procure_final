import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import { Head, useForm, usePage } from '@inertiajs/react';

type BackupRow = {
    backup_id: number;
    backup_type: string;
    file_name: string;
    file_size: number | null;
    backup_status: string;
    created_at?: string | null;
    created_by?: {
        id: number;
        name: string;
    } | null;
    notes?: string | null;
    can_download: boolean;
};

type Props = {
    records: {
        data: BackupRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    backupTypes: string[];
};

export default function AdminSystemBackupsIndex({ records, backupTypes }: Props) {
    const page = usePage().props as {
        flash?: {
            success?: string;
            error?: string;
        };
    };

    const { data, setData, post, processing, errors } = useForm({
        backup_type: backupTypes[0] ?? 'database_backup',
        notes: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route('admin-tech.system-backups.store'));
    };

    return (
        <AuthenticatedLayout header="System Backups">
            <Head title="System Backups" />

            <div className="space-y-4 py-6">
                <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="mb-3">
                        <h3 className="text-lg font-semibold text-slate-800">Create System Backup</h3>
                        <p className="text-sm text-slate-500">Technical backup management for administrative operations.</p>
                    </div>

                    {page.flash?.success ? <div className="mb-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{page.flash.success}</div> : null}
                    {page.flash?.error ? <div className="mb-3 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{page.flash.error}</div> : null}

                    <form onSubmit={submit} className="grid gap-3 md:grid-cols-4">
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Backup Type</label>
                            <select
                                value={data.backup_type}
                                onChange={(event) => setData('backup_type', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                {backupTypes.map((type) => (
                                    <option key={type} value={type}>
                                        {type.replaceAll('_', ' ')}
                                    </option>
                                ))}
                            </select>
                            {errors.backup_type ? <p className="mt-1 text-xs text-rose-600">{errors.backup_type}</p> : null}
                        </div>

                        <div className="md:col-span-3">
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Notes (optional)</label>
                            <input
                                value={data.notes}
                                onChange={(event) => setData('notes', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="Add backup notes"
                            />
                            {errors.notes ? <p className="mt-1 text-xs text-rose-600">{errors.notes}</p> : null}
                        </div>

                        <div className="md:col-span-4">
                            <button type="submit" disabled={processing} className="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60">
                                {processing ? 'Creating…' : 'Create Backup'}
                            </button>
                        </div>
                    </form>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Backup Type</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">File</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Size</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Created At</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Created By</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Notes</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {records.data.map((row) => (
                                <tr key={row.backup_id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3">{row.backup_type.replaceAll('_', ' ')}</td>
                                    <td className="px-4 py-3">{row.file_name}</td>
                                    <td className="px-4 py-3">{formatBytes(row.file_size)}</td>
                                    <td className="px-4 py-3">
                                        <span className={statusClass(row.backup_status)}>{row.backup_status}</span>
                                    </td>
                                    <td className="px-4 py-3">{formatDate(row.created_at)}</td>
                                    <td className="px-4 py-3">{row.created_by?.name ?? 'System'}</td>
                                    <td className="px-4 py-3">{row.notes ?? '-'}</td>
                                    <td className="px-4 py-3 text-right">
                                        {row.can_download ? (
                                            <a href={route('admin-tech.system-backups.download', row.backup_id)} className="font-semibold text-red-700 hover:text-red-900">
                                                Download
                                            </a>
                                        ) : (
                                            <span className="text-slate-400">Unavailable</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {records.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-10 text-center text-slate-500">
                                        No system backups yet.
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

function statusClass(status: string): string {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const lookup: Record<string, string> = {
        completed: `${base} bg-emerald-100 text-emerald-700`,
        pending: `${base} bg-amber-100 text-amber-700`,
        failed: `${base} bg-rose-100 text-rose-700`,
    };

    return lookup[status] ?? `${base} bg-slate-100 text-slate-700`;
}

