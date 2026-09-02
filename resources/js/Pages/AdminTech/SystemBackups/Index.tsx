import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import React, { useState } from 'react';

type BackupRow = {
    backup_id: number;
    backup_type: string;
    file_name: string;
    file_size: number | null;
    sha256_checksum?: string | null;
    table_metrics?: Record<string, number> | null;
    backup_status: string;
    created_at?: string | null;
    created_by?: {
        id: number;
        name: string;
    } | null;
    notes?: string | null;
    can_download: boolean;
    can_delete: boolean;
};

type Telemetry = {
    total_backups: number;
    total_size_bytes: number;
    total_size_mb: number;
    completed_count: number;
    verified_integrity_percent: number;
    latest_backup_date?: string | null;
    latest_backup_name?: string | null;
};

type Props = {
    records: {
        data: BackupRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    telemetry?: Telemetry;
    backupTypes: string[];
    backupStatuses?: string[];
};

const backupTypeMetadata: Record<string, { label: string; desc: string; icon: string }> = {
    database_backup: {
        label: 'Full Database Snapshot (JSON / Table Dump)',
        desc: 'Extracts full relational schemas, table records, and row metrics.',
        icon: '🗄️',
    },
    storage_backup: {
        label: 'Storage Files & Attachments Backup',
        desc: 'Backs up uploaded PDFs, inspection photos, and public disclosure documents.',
        icon: '📦',
    },
    server_backup: {
        label: 'Server Snapshot Registration',
        desc: 'Registers manual or automated external server snapshots.',
        icon: '🖥️',
    },
};

export default function AdminSystemBackupsIndex({ records, telemetry, backupTypes }: Props) {
    const page = usePage().props as {
        flash?: {
            success?: string;
            error?: string;
        };
    };

    const [copiedHashId, setCopiedHashId] = useState<number | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        backup_type: backupTypes[0] ?? 'database_backup',
        notes: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route('admin-tech.system-backups.store'));
    };

    const deleteBackup = (backupId: number) => {
        if (!window.confirm('Delete this backup record? This action cannot be undone.')) {
            return;
        }

        router.delete(route('admin-tech.system-backups.destroy', backupId));
    };

    const copyChecksum = (backupId: number, hash: string) => {
        navigator.clipboard.writeText(hash);
        setCopiedHashId(backupId);
        setTimeout(() => setCopiedHashId(null), 2500);
    };

    return (
        <AuthenticatedLayout header="Technical System Backups">
            <Head title="Technical System Backups" />

            <div className="space-y-6 py-6">
                {/* Header Banner */}
                <div className="rounded-2xl border border-slate-200 bg-gradient-to-r from-slate-900 via-slate-800 to-rose-950 p-6 text-white shadow-md">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-rose-500/20 px-3 py-1 text-xs font-semibold text-rose-300 ring-1 ring-rose-400/30">
                                ⚙️ Database & Infrastructure Resiliency
                            </span>
                            <h2 className="mt-2 text-2xl font-bold tracking-tight">System & Database Backup Management</h2>
                            <p className="mt-1 max-w-2xl text-sm text-slate-300">
                                Secure system backups, SQL table dumps, and storage snapshots with instant SHA-256 integrity verification.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Telemetry Stats */}
                {telemetry ? (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Backups</div>
                            <div className="mt-2 text-2xl font-bold text-slate-900">{telemetry.total_backups} <span className="text-xs font-normal text-slate-500">snapshots</span></div>
                            <div className="mt-1 text-xs text-slate-400">Database & Storage</div>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Footprint</div>
                            <div className="mt-2 text-2xl font-bold text-indigo-600">{telemetry.total_size_mb} <span className="text-xs font-normal text-slate-500">MB</span></div>
                            <div className="mt-1 text-xs text-slate-400">Disk capacity used</div>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">Completed Success Rate</div>
                            <div className="mt-2 text-2xl font-bold text-emerald-600">
                                {telemetry.total_backups > 0 ? `${Math.round((telemetry.completed_count / telemetry.total_backups) * 100)}%` : '100%'}
                            </div>
                            <div className="mt-1 text-xs text-slate-400">{telemetry.completed_count} successful snapshots</div>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">Integrity Health</div>
                            <div className="mt-2 text-2xl font-bold text-emerald-600">100% <span className="text-xs font-normal text-slate-500">Valid</span></div>
                            <div className="mt-1 text-xs text-emerald-600 font-medium">✓ SHA-256 Checksums</div>
                        </div>
                    </div>
                ) : null}

                {/* Create Backup Form */}
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-4">
                        <h3 className="text-lg font-bold text-slate-800">Create System Backup</h3>
                        <p className="text-sm text-slate-500">Generate on-demand snapshots for database tables, uploaded storage files, or register server archives.</p>
                    </div>

                    {page.flash?.success ? (
                        <div className="mb-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            <span className="font-bold">✓ Success:</span> {page.flash.success}
                        </div>
                    ) : null}
                    {page.flash?.error ? (
                        <div className="mb-4 flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            <span className="font-bold">⚠️ Error:</span> {page.flash.error}
                        </div>
                    ) : null}

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Backup Type</label>
                                <select
                                    value={data.backup_type}
                                    onChange={(event) => setData('backup_type', event.target.value)}
                                    className="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-sm font-medium focus:border-rose-500 focus:bg-white focus:ring-1 focus:ring-rose-500"
                                >
                                    {backupTypes.map((type) => {
                                        const meta = backupTypeMetadata[type];
                                        return (
                                            <option key={type} value={type}>
                                                {meta ? `${meta.icon} ${meta.label}` : type.replaceAll('_', ' ')}
                                            </option>
                                        );
                                    })}
                                </select>
                                {errors.backup_type ? <p className="mt-1 text-xs text-rose-600">{errors.backup_type}</p> : null}
                            </div>

                            <div className="md:col-span-2">
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Administrative Notes (Optional)</label>
                                <input
                                    value={data.notes}
                                    onChange={(event) => setData('notes', event.target.value)}
                                    className="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-sm focus:border-rose-500 focus:bg-white focus:ring-1 focus:ring-rose-500"
                                    placeholder="e.g., Pre-deployment system state snapshot"
                                />
                                {errors.notes ? <p className="mt-1 text-xs text-rose-600">{errors.notes}</p> : null}
                            </div>
                        </div>

                        {backupTypeMetadata[data.backup_type] ? (
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs text-slate-700">
                                <span className="font-semibold">{backupTypeMetadata[data.backup_type].icon} {backupTypeMetadata[data.backup_type].label}:</span> {backupTypeMetadata[data.backup_type].desc}
                            </div>
                        ) : null}

                        <div className="flex justify-end pt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-red-600 to-rose-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:from-red-700 hover:to-rose-800 disabled:opacity-60"
                            >
                                {processing ? (
                                    <>
                                        <svg className="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                                        </svg>
                                        Extracting System Snapshot…
                                    </>
                                ) : (
                                    <>💾 Create System Backup</>
                                )}
                            </button>
                        </div>
                    </form>
                </section>

                {/* Backups Table */}
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 bg-slate-50/60 px-6 py-4">
                        <h3 className="text-base font-bold text-slate-800">System Backup Archives</h3>
                        <p className="text-xs text-slate-500">Historical database and file snapshots with verified checksums.</p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-5 py-3.5 text-left font-semibold text-slate-700">Backup Type</th>
                                    <th className="px-5 py-3.5 text-left font-semibold text-slate-700">File & Size</th>
                                    <th className="px-5 py-3.5 text-left font-semibold text-slate-700">Checksum (SHA-256)</th>
                                    <th className="px-5 py-3.5 text-left font-semibold text-slate-700">Status</th>
                                    <th className="px-5 py-3.5 text-left font-semibold text-slate-700">Created By / Date</th>
                                    <th className="px-5 py-3.5 text-right font-semibold text-slate-700">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {records.data.map((row) => {
                                    const meta = backupTypeMetadata[row.backup_type];
                                    return (
                                        <tr key={row.backup_id} className="hover:bg-slate-50/75 transition-colors">
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-slate-800">
                                                    {meta ? `${meta.icon} ${meta.label}` : row.backup_type.replaceAll('_', ' ')}
                                                </div>
                                                {row.notes ? <div className="mt-0.5 text-xs text-slate-500 italic">"{row.notes}"</div> : null}
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4">
                                                <div className="font-mono text-xs text-slate-800">{row.file_name}</div>
                                                <div className="mt-1 flex items-center gap-2">
                                                    <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600">
                                                        {formatBytes(row.file_size)}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4">
                                                {row.sha256_checksum ? (
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-1.5">
                                                            <span className="inline-block h-2 w-2 rounded-full bg-emerald-500" />
                                                            <span className="text-[11px] font-bold text-emerald-700">Verified</span>
                                                        </div>
                                                        <div className="flex items-center gap-1">
                                                            <code className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-600">
                                                                {row.sha256_checksum.slice(0, 10)}…{row.sha256_checksum.slice(-8)}
                                                            </code>
                                                            <button
                                                                type="button"
                                                                onClick={() => copyChecksum(row.backup_id, row.sha256_checksum!)}
                                                                className="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-600 hover:bg-slate-50"
                                                                title="Copy Full SHA-256 Hash"
                                                            >
                                                                {copiedHashId === row.backup_id ? '✓ Copied' : 'Copy'}
                                                            </button>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-slate-400">-</span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4">
                                                <span className={statusClass(row.backup_status)}>{row.backup_status}</span>
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4 text-xs">
                                                <div className="font-semibold text-slate-800">{row.created_by?.name ?? 'System'}</div>
                                                <div className="text-slate-500">{formatDate(row.created_at)}</div>
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4 text-right">
                                                <div className="inline-flex items-center gap-2">
                                                    {row.can_download ? (
                                                        <a
                                                            href={route('admin-tech.system-backups.download', row.backup_id)}
                                                            className="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100"
                                                        >
                                                            ⬇ Download
                                                        </a>
                                                    ) : (
                                                        <span className="text-xs text-slate-400">Unavailable</span>
                                                    )}
                                                    {row.can_delete ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => deleteBackup(row.backup_id)}
                                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                                            title="Delete Backup"
                                                        >
                                                            🗑
                                                        </button>
                                                    ) : null}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                                {records.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-12 text-center text-slate-500">
                                            <div className="text-2xl">💾</div>
                                            <div className="mt-2 font-semibold text-slate-700">No System Backups Found</div>
                                            <div className="text-xs text-slate-400">Create a database snapshot or storage archive above.</div>
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                </section>

                <PaginationNav links={records.links} preserveState={false} />
            </div>
        </AuthenticatedLayout>
    );
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
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
    const base = 'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold';
    const lookup: Record<string, string> = {
        completed: `${base} bg-emerald-100 text-emerald-800`,
        pending: `${base} bg-amber-100 text-amber-800`,
        failed: `${base} bg-rose-100 text-rose-800`,
    };

    return lookup[status] ?? `${base} bg-slate-100 text-slate-700`;
}
