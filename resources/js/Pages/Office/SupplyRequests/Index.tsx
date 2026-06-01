import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import MonthFilterSelect from '@/Components/MonthFilterSelect';
import Modal from '@/Components/Modal';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type RequestRow = {
    supply_request_id: number;
    request_code: string;
    status: string;
    raw_status?: string;
    request_date?: string | null;
    items_count?: number;
};

type Props = {
    supplyRequests: {
        data: RequestRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters?: {
        status?: string;
        year?: string;
        month?: string;
        search?: string;
    };
    filter_options?: {
        years: number[];
    };
};

export default function SupplyRequestsIndex({ supplyRequests, filters, filter_options }: Props) {
    const [status, setStatus] = useState(filters?.status ?? '');
    const [year, setYear] = useState(filters?.year ?? '');
    const [month, setMonth] = useState(filters?.month ?? '');
    const [search, setSearch] = useState(filters?.search ?? '');
    const [draftToDelete, setDraftToDelete] = useState<RequestRow | null>(null);
    const debouncedSearch = useDebouncedValue(search, 450);
    const hasMountedRef = useRef(false);

    useEffect(() => {
        setStatus(filters?.status ?? '');
        setYear(filters?.year ?? '');
        setMonth(filters?.month ?? '');
        setSearch(filters?.search ?? '');
    }, [filters?.month, filters?.search, filters?.status, filters?.year]);

    const activeFilters = useMemo(
        () => ({
            status,
            year,
            month,
            search: debouncedSearch,
        }),
        [month, status, year, debouncedSearch],
    );

    useEffect(() => {
        if (!hasMountedRef.current) {
            hasMountedRef.current = true;

            return;
        }

        router.get(route('office.supply-requests.index'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['supplyRequests', 'filters'],
        });
    }, [activeFilters]);

    const confirmDeleteDraft = () => {
        if (!draftToDelete || (draftToDelete.raw_status ?? draftToDelete.status) !== 'DRAFT') {
            return;
        }

        router.delete(route('office.supply-requests.destroy', draftToDelete.supply_request_id), {
            preserveScroll: true,
            onFinish: () => setDraftToDelete(null),
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Request Tracking</h2>}
        >
            <Head title="Request Tracking" />

            <div className="space-y-5 py-4">
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 className="text-2xl font-semibold text-slate-800">Track Your Requests</h3>
                            <p className="text-sm text-slate-600">Single tracking view for all office supply requests.</p>
                            <Link
                                href={route('office.tracking')}
                                className="mt-3 inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Back to Dashboard
                            </Link>
                        </div>
                        <div className="flex items-center gap-2">
                            <Link
                                href={route('office.supply-requests.create')}
                                className="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                            >
                                Create New Request
                            </Link>
                        </div>
                    </div>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-4">
                        <h4 className="text-lg font-semibold text-slate-800">Request List</h4>
                        <div className="mt-3 grid gap-2 md:grid-cols-5">
                            <select
                                value={status}
                                onChange={(event) => setStatus(event.target.value)}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">All statuses</option>
                                {['DRAFT', 'SUBMITTED', 'IN_REVIEW', 'PROCUREMENT_PLACED', 'PROCESSING', 'PARTIALLY_RELEASED', 'PARTIALLY_RECEIVED', 'RELEASED', 'RECEIVED', 'REJECTED', 'RETURNED_FOR_REVISION'].map((option) => (
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
                            <input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search request code, purpose, status"
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <button
                                type="button"
                                onClick={() => router.get(route('office.supply-requests.index'))}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Reset
                            </button>
                        </div>
                    </div>

                    {supplyRequests.data.length === 0 ? (
                        <div className="px-6 py-10 text-sm text-slate-600">No requests found. Create your first supply request.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Request ID</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Date Submitted</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Items</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white">
                                    {supplyRequests.data.map((row) => (
                                        <tr key={row.supply_request_id}>
                                            <td className="px-4 py-3 font-semibold text-red-700">{row.request_code}</td>
                                            <td className="px-4 py-3 text-slate-700">{formatDate(row.request_date)}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={row.status} />
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">{row.items_count ?? 0} item(s)</td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="inline-flex flex-wrap justify-end gap-2">
                                                <Link
                                                    href={route('office.supply-requests.show', row.supply_request_id)}
                                                    className="inline-flex items-center rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                                >
                                                    View Details
                                                </Link>
                                                    {(row.raw_status ?? row.status) === 'DRAFT' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setDraftToDelete(row)}
                                                            className="inline-flex items-center rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50"
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
                </div>

                <PaginationNav links={supplyRequests.links} preserveState={false} />
            </div>

            <Modal show={draftToDelete !== null} maxWidth="md" onClose={() => setDraftToDelete(null)}>
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-slate-900">Delete draft supply request?</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        Delete draft supply request {draftToDelete?.request_code}? This will remove it from your request list.
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
                            Delete Request
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

function StatusBadge({ status }: { status: string }) {
    const normalized = status.toUpperCase();
    const style =
        normalized === 'APPROVED' || normalized === 'COMPLETED'
            ? 'bg-emerald-100 text-emerald-700'
            : normalized === 'IN_REVIEW' || normalized === 'PROCESSING' || normalized === 'RELEASED' || normalized === 'PARTIALLY_RELEASED' || normalized === 'PROCUREMENT_PO_DRAFT' || normalized === 'PROCUREMENT_PLACED'
                ? 'bg-blue-100 text-blue-700'
                : normalized === 'REJECTED' || normalized === 'RETURNED_FOR_REVISION'
                    ? 'bg-rose-100 text-rose-700'
                    : normalized === 'PARTIALLY_RECEIVED'
                        ? 'bg-orange-100 text-orange-700'
                    : 'bg-amber-100 text-amber-700';

    return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${style}`}>{status.replaceAll('_', ' ')}</span>;
}





