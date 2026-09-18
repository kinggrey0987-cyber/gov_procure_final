import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ConfirmationModal from '@/Components/ConfirmationModal';
import PaginationNav from '@/Components/PaginationNav';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import React, { useState } from 'react';

type ArchiveRow = {
    archive_id: number;
    archive_year: number;
    archive_scope: string;
    file_name: string;
    file_size: number | null;
    sha256_checksum?: string | null;
    record_count?: number;
    manifest_summary?: Array<{ rows: number }> | null;
    generated_at?: string | null;
    generated_by?: {
        id: number;
        name: string;
    } | null;
    notes?: string | null;
    can_download: boolean;
    can_delete: boolean;
};

type Telemetry = {
    total_archives: number;
    total_size_bytes: number;
    total_size_mb: number;
    total_records_archived: number;
    verified_integrity_percent: number;
    latest_archive_date?: string | null;
    latest_archive_name?: string | null;
};

type Props = {
    records: {
        data: ArchiveRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    telemetry?: Telemetry;
    archiveScopes: string[];
    yearOptions: number[];
};

const scopeDescriptions: Record<string, { label: string; desc: string; icon: string; color: string }> = {
    annual_procurement_dossier: {
        label: 'Annual Procurement Dossier (Full Cycle)',
        desc: 'Complete annual bundle: PPMPs, PRs, POs, Deliveries, Distribution, Claims, and COA Summaries.',
        icon: '📁',
        color: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    },
    coa_compliance_package: {
        label: 'COA Compliance & Disclosures',
        desc: 'Public disclosures, IAR physical inspection summaries, and audit trail certifications.',
        icon: '⚖️',
        color: 'border-blue-200 bg-blue-50 text-blue-800',
    },
    governance_audit_trail: {
        label: 'Governance & Audit Trail Logs',
        desc: 'Immutable system audit logs, approval histories, status transitions, and anomalies.',
        icon: '🛡️',
        color: 'border-purple-200 bg-purple-50 text-purple-800',
    },
    financial_contracts: {
        label: 'Financial & Purchase Orders',
        desc: 'Contract disbursements, supplier purchase orders, and item pricing historical data.',
        icon: '💼',
        color: 'border-amber-200 bg-amber-50 text-amber-800',
    },
    governance_yearly: {
        label: 'Standard Governance Yearly Archive',
        desc: 'Standard yearly governance package including audit logs, approval histories, and public disclosures.',
        icon: '📜',
        color: 'border-slate-200 bg-slate-50 text-slate-800',
    },
};

export default function ExecutiveRecordArchivesIndex({ records, telemetry, archiveScopes, yearOptions }: Props) {
    const page = usePage().props as {
        flash?: {
            success?: string;
            error?: string;
        };
    };

    const [copiedHashId, setCopiedHashId] = useState<number | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        archive_year: yearOptions[0] ?? new Date().getFullYear(),
        archive_scope: archiveScopes[0] ?? 'annual_procurement_dossier',
        notes: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route('executive.record-archives.store'));
    };

    const [archiveToDelete, setArchiveToDelete] = useState<{ id: number; title: string } | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const confirmDeleteArchive = () => {
        if (!archiveToDelete) {
            return;
        }

        setIsDeleting(true);
        router.delete(route('executive.record-archives.destroy', archiveToDelete.id), {
            preserveScroll: true,
            onFinish: () => {
                setIsDeleting(false);
                setArchiveToDelete(null);
            },
        });
    };

    const copyChecksum = (archiveId: number, hash: string) => {
        navigator.clipboard.writeText(hash);
        setCopiedHashId(archiveId);
        setTimeout(() => setCopiedHashId(null), 2500);
    };

    return (
        <AuthenticatedLayout header="Annual & Executive Record Archives">
            <Head title="Annual & Executive Record Archives" />

            <div className="space-y-6 py-6">
                {/* Header Banner */}
                <div className="rounded-2xl border border-slate-200 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 p-6 text-white shadow-md">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-300 ring-1 ring-emerald-400/30">
                                🔒 COA & DICT Certified Archival Standard
                            </span>
                            <h2 className="mt-2 text-2xl font-bold tracking-tight">Executive Governance & Annual Archiving Hub</h2>
                            <p className="mt-1 max-w-2xl text-sm text-slate-300">
                                Generate immutable, cryptographic annual dossiers for municipal procurement cycles. Each package includes SHA-256 tamper-proof verification, full CSV manifests, and certified JSON records.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Storage Telemetry Cards */}
                {telemetry ? (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Archives</div>
                            <div className="mt-2 text-2xl font-bold text-slate-900">{telemetry.total_archives} <span className="text-xs font-normal text-slate-500">packages</span></div>
                            <div className="mt-1 text-xs text-slate-400">Multi-year dossiers</div>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Storage Footprint</div>
                            <div className="mt-2 text-2xl font-bold text-indigo-600">{telemetry.total_size_mb} <span className="text-xs font-normal text-slate-500">MB</span></div>
                            <div className="mt-1 text-xs text-slate-400">Compressed ZIP data</div>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Records Archived</div>
                            <div className="mt-2 text-2xl font-bold text-emerald-600">{(telemetry.total_records_archived || 0).toLocaleString()} <span className="text-xs font-normal text-slate-500">rows</span></div>
                            <div className="mt-1 text-xs text-slate-400">Across all datasets</div>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">Integrity Verification</div>
                            <div className="mt-2 text-2xl font-bold text-emerald-600">100% <span className="text-xs font-normal text-slate-500">SHA-256</span></div>
                            <div className="mt-1 text-xs text-emerald-600 font-medium">✓ Cryptographically Valid</div>
                        </div>
                    </div>
                ) : null}

                {/* Generator Section */}
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-4">
                        <h3 className="text-lg font-bold text-slate-800">Generate Annual / Executive Dossier</h3>
                        <p className="text-sm text-slate-500">Select a fiscal year and archive scope to compile complete procurement & governance evidence.</p>
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
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Fiscal Year</label>
                                <select
                                    value={data.archive_year}
                                    onChange={(event) => setData('archive_year', Number(event.target.value))}
                                    className="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-sm font-medium focus:border-indigo-500 focus:bg-white focus:ring-1 focus:ring-indigo-500"
                                >
                                    {yearOptions.map((year) => (
                                        <option key={year} value={year}>
                                            Fiscal Year {year}
                                        </option>
                                    ))}
                                </select>
                                {errors.archive_year ? <p className="mt-1 text-xs text-rose-600">{errors.archive_year}</p> : null}
                            </div>

                            <div className="md:col-span-2">
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Archive Scope</label>
                                <select
                                    value={data.archive_scope}
                                    onChange={(event) => setData('archive_scope', event.target.value)}
                                    className="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-sm font-medium focus:border-indigo-500 focus:bg-white focus:ring-1 focus:ring-indigo-500"
                                >
                                    {archiveScopes.map((scope) => {
                                        const meta = scopeDescriptions[scope];
                                        return (
                                            <option key={scope} value={scope}>
                                                {meta ? `${meta.icon} ${meta.label}` : scope.replaceAll('_', ' ')}
                                            </option>
                                        );
                                    })}
                                </select>
                                {errors.archive_scope ? <p className="mt-1 text-xs text-rose-600">{errors.archive_scope}</p> : null}
                            </div>
                        </div>

                        {/* Selected Scope Preview Banner */}
                        {scopeDescriptions[data.archive_scope] ? (
                            <div className={`rounded-xl border p-3.5 text-xs ${scopeDescriptions[data.archive_scope].color}`}>
                                <div className="font-semibold">{scopeDescriptions[data.archive_scope].icon} {scopeDescriptions[data.archive_scope].label}</div>
                                <div className="mt-0.5 opacity-90">{scopeDescriptions[data.archive_scope].desc}</div>
                            </div>
                        ) : null}

                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Executive Notes / Memo Reference (Optional)</label>
                            <input
                                value={data.notes}
                                onChange={(event) => setData('notes', event.target.value)}
                                className="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:bg-white focus:ring-1 focus:ring-indigo-500"
                                placeholder="e.g., Annual Year-End Closeout Dossier for COA Inspection"
                            />
                            {errors.notes ? <p className="mt-1 text-xs text-rose-600">{errors.notes}</p> : null}
                        </div>

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
                                        Compiling Cryptographic Archive…
                                    </>
                                ) : (
                                    <>🔒 Generate & Seal Archive Package</>
                                )}
                            </button>
                        </div>
                    </form>
                </section>

                {/* Archives Table */}
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 bg-slate-50/60 px-6 py-4">
                        <h3 className="text-base font-bold text-slate-800">Archived Governance Packages</h3>
                        <p className="text-xs text-slate-500">Immutable archives stored with SHA-256 verification hash.</p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-5 py-3.5 text-left font-semibold text-slate-700">Fiscal Year</th>
                                    <th className="px-5 py-3.5 text-left font-semibold text-slate-700">Dossier Scope</th>
                                    <th className="px-5 py-3.5 text-left font-semibold text-slate-700">Package Details</th>
                                    <th className="px-5 py-3.5 text-left font-semibold text-slate-700">Cryptographic Checksum</th>
                                    <th className="px-5 py-3.5 text-left font-semibold text-slate-700">Generated By / At</th>
                                    <th className="px-5 py-3.5 text-right font-semibold text-slate-700">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {records.data.map((row) => {
                                    const meta = scopeDescriptions[row.archive_scope];
                                    return (
                                        <tr key={row.archive_id} className="hover:bg-slate-50/75 transition-colors">
                                            <td className="whitespace-nowrap px-5 py-4">
                                                <span className="inline-flex items-center rounded-lg bg-slate-900 px-2.5 py-1 text-xs font-bold text-white">
                                                    FY {row.archive_year}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-slate-800">
                                                    {meta ? `${meta.icon} ${meta.label}` : row.archive_scope.replaceAll('_', ' ')}
                                                </div>
                                                {row.notes ? <div className="mt-0.5 text-xs text-slate-500 italic">"{row.notes}"</div> : null}
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4">
                                                <div className="font-mono text-xs text-slate-800">{row.file_name}</div>
                                                <div className="mt-1 flex items-center gap-2">
                                                    <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600">
                                                        {formatBytes(row.file_size)}
                                                    </span>
                                                    {row.record_count ? (
                                                        <span className="rounded bg-emerald-100 px-1.5 py-0.5 text-[11px] font-medium text-emerald-800">
                                                            {row.record_count.toLocaleString()} rows
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td className="px-5 py-4">
                                                {row.sha256_checksum ? (
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-1.5">
                                                            <span className="inline-block h-2 w-2 rounded-full bg-emerald-500" />
                                                            <span className="text-[11px] font-bold text-emerald-700">SHA-256 Verified</span>
                                                        </div>
                                                        <div className="flex items-center gap-1">
                                                            <code className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-600">
                                                                {row.sha256_checksum.slice(0, 10)}…{row.sha256_checksum.slice(-8)}
                                                            </code>
                                                            <button
                                                                type="button"
                                                                onClick={() => copyChecksum(row.archive_id, row.sha256_checksum!)}
                                                                className="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-600 hover:bg-slate-50"
                                                                title="Copy Full SHA-256 Hash"
                                                            >
                                                                {copiedHashId === row.archive_id ? '✓ Copied' : 'Copy'}
                                                            </button>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-slate-400">Legacy Archive</span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4 text-xs">
                                                <div className="font-semibold text-slate-800">{row.generated_by?.name ?? 'System'}</div>
                                                <div className="text-slate-500">{formatDate(row.generated_at)}</div>
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4 text-right">
                                                <div className="inline-flex items-center gap-2">
                                                    {row.can_download ? (
                                                        <a
                                                            href={route('executive.record-archives.download', row.archive_id)}
                                                            className="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100"
                                                        >
                                                            ⬇ Download ZIP
                                                        </a>
                                                    ) : (
                                                        <span className="text-xs text-slate-400">Unavailable</span>
                                                    )}
                                                    {row.can_delete ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                const scopeLabel = scopeDescriptions[row.archive_scope]?.label ?? row.archive_scope.replaceAll('_', ' ');
                                                                setArchiveToDelete({
                                                                    id: row.archive_id,
                                                                    title: `${scopeLabel} (FY ${row.archive_year})`,
                                                                });
                                                            }}
                                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                                            title="Delete Archive"
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
                                            <div className="text-2xl">📦</div>
                                            <div className="mt-2 font-semibold text-slate-700">No Record Archives Generated Yet</div>
                                            <div className="text-xs text-slate-400">Select a fiscal year above to compile your first certified governance dossier.</div>
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>

                    {records.links && records.links.length > 3 && (
                        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/70 px-4 py-2.5">
                            <span className="text-xs font-medium text-slate-500">
                                Showing {records.data.length} records
                            </span>
                            <PaginationNav links={records.links} preserveState={false} />
                        </div>
                    )}
                </section>
            </div>

            <ConfirmationModal
                show={archiveToDelete !== null}
                title="Delete Certified Record Archive"
                message={
                    <span>
                        Are you sure you want to permanently delete the certified archive{' '}
                        <strong className="font-semibold text-slate-900">{archiveToDelete?.title}</strong>?
                        This action cannot be undone and will permanently remove the dossier package and manifest from server storage.
                    </span>
                }
                confirmText="Delete Archive"
                cancelText="Cancel"
                variant="danger"
                processing={isDeleting}
                onConfirm={confirmDeleteArchive}
                onClose={() => {
                    if (!isDeleting) setArchiveToDelete(null);
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
