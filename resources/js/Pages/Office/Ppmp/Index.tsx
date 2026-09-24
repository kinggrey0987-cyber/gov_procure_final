import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import Modal from '@/Components/Modal';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type PpmpRow = {
    ppmp_id: number;
    ppmp_code: string;
    fiscal_year: number;
    status: string;
    is_projected?: boolean;
    is_past?: boolean;
    date_prepared?: string | null;
    last_updated?: string | null;
};

type Props = {
    ppmps: { data: PpmpRow[]; links: Array<{ url: string | null; label: string; active: boolean }> };
    currentFiscalYear?: number;
    filters?: {
        status?: string;
        year?: string;
        month?: string;
    };
    filter_options?: {
        years: number[];
    };
};

export default function PpmpIndex({ ppmps, currentFiscalYear, filters, filter_options }: Props) {
    const [status, setStatus] = useState(filters?.status ?? '');
    const [year, setYear] = useState(filters?.year ?? '');
    const [month, setMonth] = useState(filters?.month ?? '');
    const [draftToDelete, setDraftToDelete] = useState<PpmpRow | null>(null);
    const hasMountedRef = useRef(false);

    useEffect(() => {
        setStatus(filters?.status ?? '');
        setYear(filters?.year ?? '');
        setMonth(filters?.month ?? '');
    }, [filters?.month, filters?.status, filters?.year]);

    const activeFilters = useMemo(() => ({ status, year, month }), [month, status, year]);

    useEffect(() => {
        if (!hasMountedRef.current) {
            hasMountedRef.current = true;

            return;
        }

        router.get(route('office.ppmp.index'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['ppmps', 'filters'],
        });
    }, [activeFilters]);

    const confirmDeleteDraft = () => {
        if (!draftToDelete || draftToDelete.status !== 'DRAFT') {
            return;
        }

        router.delete(route('office.ppmp.destroy', draftToDelete.ppmp_id), {
            preserveScroll: true,
            onFinish: () => setDraftToDelete(null),
        });
    };

    return (
        <AuthenticatedLayout
            header="Project Procurement Management Plan (PPMP)"
            headerActions={
                <Link
                    href={route('office.ppmp.create')}
                    className="inline-flex h-8 items-center gap-1.5 rounded-lg bg-red-600 px-3 text-xs font-semibold text-white shadow-sm hover:bg-red-700 transition"
                >
                    <span className="text-sm leading-none">＋</span>
                    <span>Create PPMP</span>
                </Link>
            }
        >
            <Head title="PPMP" />

            <div className="space-y-3">
                {/* Compact Control & Filter Header */}
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <h3 className="text-xs font-bold text-slate-800">Filter PPMP Plans</h3>
                            <span className="hidden text-[11px] text-slate-500 sm:inline">• Prepare, submit, and track annual procurement management plans</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-[11px] text-slate-500">Showing {ppmps.data.length} records</span>
                            <button
                                type="button"
                                onClick={() => router.get(route('office.ppmp.index'))}
                                className="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition shadow-2xs"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                    <div className="mt-2 grid gap-2 sm:grid-cols-3">
                        <select
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                            className="h-8 rounded-lg border-slate-300 pl-2.5 pr-8 py-1 text-xs"
                        >
                            <option value="">All statuses</option>
                            {['DRAFT', 'SUBMITTED', 'APPROVED', 'CLOSED', 'REJECTED', 'RETURNED_FOR_REVISION'].map((option) => (
                                <option key={option} value={option}>{option.replaceAll('_', ' ')}</option>
                            ))}
                        </select>
                        <select
                            value={year}
                            onChange={(event) => setYear(event.target.value)}
                            className="h-8 rounded-lg border-slate-300 pl-2.5 pr-8 py-1 text-xs"
                        >
                            <option value="">All years</option>
                            {(filter_options?.years ?? []).map((option) => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                        <select
                            value={month}
                            onChange={(event) => setMonth(event.target.value)}
                            className="h-8 rounded-lg border-slate-300 pl-2.5 pr-8 py-1 text-xs"
                        >
                            <option value="">All months</option>
                            {[
                                ['01', 'January'],
                                ['02', 'February'],
                                ['03', 'March'],
                                ['04', 'April'],
                                ['05', 'May'],
                                ['06', 'June'],
                                ['07', 'July'],
                                ['08', 'August'],
                                ['09', 'September'],
                                ['10', 'October'],
                                ['11', 'November'],
                                ['12', 'December'],
                            ].map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </div>
                </section>

                {/* Snug Table Container with Pagination Attached Directly Below Records */}
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    {ppmps.data.length === 0 ? (
                        <div className="px-4 py-8 text-center text-sm text-slate-500">No PPMP entries yet.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-xs sm:text-sm">
                                <thead className="bg-slate-50 shadow-xs">
                                    <tr>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">PPMP ID</th>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Fiscal Year</th>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Date Prepared</th>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Last Updated</th>
                                        <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white">
                                    {ppmps.data.map((ppmp) => (
                                        <tr key={ppmp.ppmp_id} className="hover:bg-slate-50/60 transition-colors">
                                            <td className="px-3.5 py-2 font-semibold text-red-700">{ppmp.ppmp_code}</td>
                                            <td className="px-3.5 py-2 text-slate-700 font-medium">{ppmp.fiscal_year}</td>
                                            <td className="px-3.5 py-2 text-slate-700">{ppmp.date_prepared ?? '-'}</td>
                                            <td className="px-3.5 py-2">
                                                <StatusBadge status={ppmp.status} isProjected={ppmp.is_projected} />
                                            </td>
                                            <td className="px-3.5 py-2 text-slate-700 text-xs">{formatDateTime(ppmp.last_updated)}</td>
                                            <td className="px-3.5 py-2 text-right">
                                                <div className="inline-flex flex-wrap justify-end gap-1.5">
                                                    <Link
                                                        href={route('office.ppmp.show', ppmp.ppmp_id)}
                                                        className="rounded border border-red-200 bg-red-50/50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition"
                                                    >
                                                        View Details
                                                    </Link>
                                                    {ppmp.status === 'DRAFT' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setDraftToDelete(ppmp)}
                                                            className="rounded border border-rose-200 bg-rose-50/50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition"
                                                        >
                                                            Delete
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {ppmps.links && ppmps.links.length > 3 && (
                        <div className="border-t border-slate-200 bg-slate-50/70 px-3.5 py-2 flex flex-wrap items-center justify-between gap-2">
                            <p className="text-xs text-slate-500 font-medium">
                                Showing <span className="font-semibold text-slate-700">{ppmps.data.length}</span> PPMP plans
                            </p>
                            <PaginationNav links={ppmps.links} preserveState={false} />
                        </div>
                    )}
                </section>
            </div>

            <Modal show={draftToDelete !== null} maxWidth="md" onClose={() => setDraftToDelete(null)}>
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-slate-900">Delete draft PPMP?</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        Delete draft {draftToDelete?.ppmp_code}? This will remove it from your PPMP list.
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setDraftToDelete(null)}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={confirmDeleteDraft}
                            className="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700"
                        >
                            Delete PPMP
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}

function formatDateTime(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function StatusBadge({ status, isProjected }: { status: string; isProjected?: boolean }) {
    if (isProjected) {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-800">
                <span className="h-1.5 w-1.5 rounded-full bg-purple-600"></span>
                <span>Projected</span>
            </span>
        );
    }

    const normalized = status.toUpperCase();
    const style =
        normalized === 'APPROVED'
            ? 'bg-emerald-100 text-emerald-700'
            : normalized === 'CLOSED'
                ? 'bg-slate-200 text-slate-600 font-medium'
                : normalized === 'SUBMITTED'
                    ? 'bg-blue-100 text-blue-700'
                    : normalized === 'RETURNED_FOR_REVISION'
                        ? 'bg-amber-100 text-amber-700'
                        : normalized === 'REJECTED'
                            ? 'bg-rose-100 text-rose-700'
                            : 'bg-slate-100 text-slate-700';

    return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${style}`}>{status.replaceAll('_', ' ')}</span>;
}





