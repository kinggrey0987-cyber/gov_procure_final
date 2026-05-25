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
    date_prepared?: string | null;
    last_updated?: string | null;
};

type Props = {
    ppmps: { data: PpmpRow[]; links: Array<{ url: string | null; label: string; active: boolean }> };
    filters?: {
        status?: string;
        year?: string;
        month?: string;
    };
    filter_options?: {
        years: number[];
    };
};

export default function PpmpIndex({ ppmps, filters, filter_options }: Props) {
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
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">PPMP</h2>}>
            <Head title="PPMP" />

            <div className="space-y-5 py-4">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 className="text-2xl font-semibold text-slate-800">Project Procurement Management Plan</h3>
                            <p className="text-sm text-slate-600">Prepare and submit annual PPMP plans for your office.</p>
                            <Link href={route('office.tracking')} className="mt-3 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Back to Dashboard
                            </Link>
                        </div>
                        <Link href={route('office.ppmp.create')} className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                            Create PPMP
                        </Link>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-4">
                        <h4 className="text-lg font-semibold text-slate-800">PPMP List</h4>
                        <div className="mt-3 grid gap-2 md:grid-cols-4">
                            <select
                                value={status}
                                onChange={(event) => setStatus(event.target.value)}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">All statuses</option>
                                {['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'RETURNED_FOR_REVISION'].map((option) => (
                                    <option key={option} value={option}>{option.replaceAll('_', ' ')}</option>
                                ))}
                            </select>
                            <select
                                value={year}
                                onChange={(event) => setYear(event.target.value)}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
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
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
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
                            <button
                                type="button"
                                onClick={() => router.get(route('office.ppmp.index'))}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                    {ppmps.data.length === 0 ? (
                        <div className="px-6 py-10 text-sm text-slate-600">No PPMP entries yet.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">PPMP ID</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Fiscal Year</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Date Prepared</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Last Updated</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {ppmps.data.map((ppmp) => (
                                        <tr key={ppmp.ppmp_id}>
                                            <td className="px-4 py-3 font-semibold text-red-700">{ppmp.ppmp_code}</td>
                                            <td className="px-4 py-3 text-slate-700">{ppmp.fiscal_year}</td>
                                            <td className="px-4 py-3 text-slate-700">{ppmp.date_prepared ?? '-'}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={ppmp.status} />
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">{formatDateTime(ppmp.last_updated)}</td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="inline-flex flex-wrap justify-end gap-2">
                                                <Link
                                                    href={route('office.ppmp.show', ppmp.ppmp_id)}
                                                    className="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                                >
                                                    View Details
                                                </Link>
                                                    {ppmp.status === 'DRAFT' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setDraftToDelete(ppmp)}
                                                            className="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50"
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
                </section>

                <PaginationNav links={ppmps.links} preserveState={false} />
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

function StatusBadge({ status }: { status: string }) {
    const normalized = status.toUpperCase();
    const style =
        normalized === 'APPROVED'
            ? 'bg-emerald-100 text-emerald-700'
            : normalized === 'SUBMITTED'
                ? 'bg-blue-100 text-blue-700'
                : normalized === 'RETURNED_FOR_REVISION'
                    ? 'bg-amber-100 text-amber-700'
                    : normalized === 'REJECTED'
                        ? 'bg-rose-100 text-rose-700'
                        : 'bg-slate-100 text-slate-700';

    return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${style}`}>{status.replaceAll('_', ' ')}</span>;
}





