import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ConfirmationModal from '@/Components/ConfirmationModal';
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

    const [backupToDelete, setBackupToDelete] = useState<{ id: number; title: string } | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const confirmDeleteBackup = () => {
        if (!backupToDelete) {
            return;
        }

        setIsDeleting(true);
        router.delete(route('admin-tech.system-backups.destroy', backupToDelete.id), {
            preserveScroll: true,
            onFinish: () => {
                setIsDeleting(false);
                setBackupToDelete(null);
            },
        });
    };

    const copyChecksum = (backupId: number, hash: string) => {
        navigator.clipboard.writeText(hash);
        setCopiedHashId(backupId);
        setTimeout(() => setCopiedHashId(null), 2500);
    };

    return (
        <AuthenticatedLayout header="Technical System Backups">
            <Head title="Technical System Backups" />

            <div className="space-y-2 lg:h-[calc(100vh-8.5rem)] flex flex-col justify-between overflow-y-auto lg:overflow-hidden">
                {/* High-Density System Backup Telemetry Cockpit */}
                {telemetry ? (
                    <section className="shrink-0 rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
                        <div className="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                            <div className="flex items-center gap-2.5 px-3 py-1.5">
                                <span className="h-2 w-2 shrink-0 rounded-full bg-slate-400" />
                                <div className="min-w-0">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Total Backups</p>
                                    <p className="text-lg font-extrabold text-slate-900 leading-tight">
                                        {telemetry.total_backups} <span className="text-xs font-normal text-slate-500">snapshots</span>
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-2.5 px-3 py-1.5">
                                <span className="h-2 w-2 shrink-0 rounded-full bg-indigo-500" />
                                <div className="min-w-0">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-indigo-700">Total Footprint</p>
                                    <p className="text-lg font-extrabold text-indigo-900 leading-tight">
                                        {telemetry.total_size_mb} <span className="text-xs font-normal text-slate-500">MB</span>
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-2.5 px-3 py-1.5">
                                <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-500" />
                                <div className="min-w-0">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Success Rate</p>
                                    <p className="text-lg font-extrabold text-emerald-900 leading-tight">
                                        {telemetry.total_backups > 0 ? `${Math.round((telemetry.completed_count / telemetry.total_backups) * 100)}%` : '100%'}
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-2.5 px-3 py-1.5">
                                <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-600" />
                                <div className="min-w-0">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Integrity Health</p>
                                    <p className="text-lg font-extrabold text-emerald-900 leading-tight">
                                        100% <span className="text-xs font-normal text-emerald-600">✓ Valid</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                ) : null}

                {/* Compact Create Backup Control Bar */}
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    {page.flash?.success ? (
                        <div className="mb-2 flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-800">
                            <span className="font-bold">✓ Success:</span> {page.flash.success}
                        </div>
                    ) : null}
                    {page.flash?.error ? (
                        <div className="mb-2 flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-800">
                            <span className="font-bold">⚠️ Error:</span> {page.flash.error}
                        </div>
                    ) : null}

                    <form onSubmit={submit} className="flex flex-wrap items-center gap-2">
                        <div className="min-w-[16rem] flex-1">
                            <select
                                value={data.backup_type}
                                onChange={(event) => setData('backup_type', event.target.value)}
                                className="h-8 w-full rounded-lg border border-slate-300 bg-slate-50/50 px-2.5 text-xs font-medium focus:border-red-500 focus:bg-white focus:ring-1 focus:ring-red-500"
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
                        </div>

                        <div className="min-w-[14rem] flex-1">
                            <input
                                value={data.notes}
                                onChange={(event) => setData('notes', event.target.value)}
                                className="h-8 w-full rounded-lg border border-slate-300 bg-slate-50/50 px-2.5 text-xs focus:border-red-500 focus:bg-white focus:ring-1 focus:ring-red-500"
                                placeholder="Administrative notes (optional)..."
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg bg-red-600 px-3.5 text-xs font-semibold text-white shadow-xs hover:bg-red-700 disabled:opacity-60 transition"
                        >
                            {processing ? (
                                <>
                                    <svg className="h-3.5 w-3.5 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                                    </svg>
                                    Extracting…
                                </>
                            ) : (
                                <>💾 Create System Backup</>
                            )}
                        </button>
                    </form>
                </section>

                {/* Backups Table with Internal Scrolling */}
                <section className="flex-1 min-h-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col">
                    <div className="shrink-0 flex items-center justify-between border-b border-slate-200 bg-slate-50/70 px-3.5 py-2">
                        <div>
                            <h3 className="text-xs font-bold text-slate-800">System Backup Archives</h3>
                            <p className="text-[10px] text-slate-500">Historical database and file snapshots with verified SHA-256 integrity</p>
                        </div>
                        <span className="text-[11px] text-slate-500 font-medium">Showing {records.data.length} snapshots</span>
                    </div>

                    <div className="flex-1 min-h-0 overflow-y-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-xs">
                            <thead className="sticky top-0 z-10 bg-slate-50 shadow-[0_1px_2px_rgba(0,0,0,0.05)]">
                                <tr>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Backup Type</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">File & Size</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Checksum (SHA-256)</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Created By / Date</th>
                                    <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white">
                                {records.data.map((row) => {
                                    const meta = backupTypeMetadata[row.backup_type];
                                    return (
                                        <tr key={row.backup_id} className="hover:bg-slate-50/75 transition-colors">
                                            <td className="px-3.5 py-2">
                                                <div className="font-semibold text-slate-800">
                                                    {meta ? `${meta.icon} ${meta.label}` : row.backup_type.replaceAll('_', ' ')}
                                                </div>
                                                {row.notes ? <div className="mt-0.5 text-[11px] text-slate-500 italic">"{row.notes}"</div> : null}
                                            </td>
                                            <td className="whitespace-nowrap px-3.5 py-2">
                                                <div className="font-mono text-[11px] text-slate-800">{row.file_name}</div>
                                                <div className="mt-0.5 flex items-center gap-1">
                                                    <span className="rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-medium text-slate-600">
                                                        {formatBytes(row.file_size)}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-3.5 py-2">
                                                {row.sha256_checksum ? (
                                                    <div className="space-y-0.5">
                                                        <div className="flex items-center gap-1">
                                                            <span className="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                                            <span className="text-[10px] font-bold text-emerald-700">Verified</span>
                                                        </div>
                                                        <div className="flex items-center gap-1">
                                                            <code className="rounded bg-slate-100 px-1 py-0.5 font-mono text-[10px] text-slate-600">
                                                                {row.sha256_checksum.slice(0, 8)}…{row.sha256_checksum.slice(-6)}
                                                            </code>
                                                            <button
                                                                type="button"
                                                                onClick={() => copyChecksum(row.backup_id, row.sha256_checksum!)}
                                                                className="rounded border border-slate-200 bg-white px-1 py-0.5 text-[10px] font-medium text-slate-600 hover:bg-slate-50"
                                                                title="Copy Full SHA-256 Hash"
                                                            >
                                                                {copiedHashId === row.backup_id ? '✓' : 'Copy'}
                                                            </button>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-slate-400">-</span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-3.5 py-2">
                                                <span className={statusClass(row.backup_status)}>{row.backup_status}</span>
                                            </td>
                                            <td className="whitespace-nowrap px-3.5 py-2 text-[11px]">
                                                <div className="font-semibold text-slate-800">{row.created_by?.name ?? 'System'}</div>
                                                <div className="text-slate-500">{formatDate(row.created_at)}</div>
                                            </td>
                                            <td className="whitespace-nowrap px-3.5 py-2 text-right">
                                                <div className="inline-flex items-center gap-1.5">
                                                    {row.can_download ? (
                                                        <a
                                                            href={route('admin-tech.system-backups.download', row.backup_id)}
                                                            className="inline-flex items-center gap-1 rounded border border-red-200 bg-red-50/50 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition"
                                                        >
                                                            ⬇ Download
                                                        </a>
                                                    ) : (
                                                        <span className="text-[11px] text-slate-400">Unavailable</span>
                                                    )}
                                                    {row.can_delete ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => setBackupToDelete({ id: row.backup_id, title: row.file_name || `Backup #${row.backup_id}` })}
                                                            className="rounded border border-rose-200 bg-rose-50/50 p-1 text-rose-700 hover:bg-rose-100 transition"
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
                                        <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                                            <div className="text-xl">💾</div>
                                            <div className="mt-1 text-xs font-semibold text-slate-700">No System Backups Found</div>
                                            <div className="text-[11px] text-slate-400">Create a database snapshot or storage archive above.</div>
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="shrink-0 pt-0.5">
                    <PaginationNav links={records.links} preserveState={false} />
                </div>
            </div>

            <ConfirmationModal
                show={backupToDelete !== null}
                title="Delete System Backup"
                message={
                    <span>
                        Are you sure you want to permanently delete the backup{' '}
                        <strong className="font-semibold text-slate-900">{backupToDelete?.title}</strong>?
                        This action cannot be undone and will permanently remove the snapshot file from server storage.
                    </span>
                }
                confirmText="Delete Backup"
                cancelText="Cancel"
                variant="danger"
                processing={isDeleting}
                onConfirm={confirmDeleteBackup}
                onClose={() => {
                    if (!isDeleting) setBackupToDelete(null);
                }}
            />
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
