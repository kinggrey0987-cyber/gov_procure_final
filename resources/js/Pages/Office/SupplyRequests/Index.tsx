import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import MonthFilterSelect from '@/Components/MonthFilterSelect';
import Modal from '@/Components/Modal';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type FollowUpContext = {
    is_follow_up: boolean;
    type: string;
    label: string;
    parent?: { supply_request_id: number; request_code: string } | null;
    children?: Array<{ supply_request_id: number; request_code: string; status: string; reason?: string }>;
};

type RequestRow = {
    supply_request_id: number;
    request_code: string;
    status: string;
    raw_status?: string;
    priority_level?: string;
    request_date?: string | null;
    items_count?: number;
    follow_up_context?: FollowUpContext | null;
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
    const [submittingDraftId, setSubmittingDraftId] = useState<number | null>(null);
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

    const submitDraft = (row: RequestRow) => {
        if ((row.raw_status ?? row.status) !== 'DRAFT') {
            return;
        }

        router.post(
            route('office.supply-requests.submit', row.supply_request_id),
            { redirect_to: 'index' },
            {
                preserveScroll: true,
                onStart: () => setSubmittingDraftId(row.supply_request_id),
                onFinish: () => setSubmittingDraftId(null),
            },
        );
    };

    return (
        <AuthenticatedLayout
            header="Request Tracking"
            headerActions={
                <Link
                    href={route('office.supply-requests.create')}
                    className="inline-flex h-8 items-center gap-1.5 rounded-lg bg-red-600 px-3 text-xs font-semibold text-white shadow-sm hover:bg-red-700 transition"
                >
                    <span className="text-sm leading-none">＋</span>
                    <span>Create New Request</span>
                </Link>
            }
        >
            <Head title="Request Tracking" />

            <div className="space-y-3">
                {/* Compact Control & Filter Header */}
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <h3 className="text-xs font-bold text-slate-800">Filter Supply Requests</h3>
                            <span className="hidden text-[11px] text-slate-500 sm:inline">• Track requisition lifecycle, approval stages, and fulfillment status</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-[11px] text-slate-500">Showing {supplyRequests.data.length} records</span>
                            <button
                                type="button"
                                onClick={() => router.get(route('office.supply-requests.index'))}
                                className="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition shadow-2xs"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                    <div className="mt-2 grid gap-2 sm:grid-cols-[10rem_7rem_7rem_minmax(0,1fr)]">
                        <select
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                            className="h-8 rounded-lg border-slate-300 pl-2.5 pr-8 py-1 text-xs"
                        >
                            <option value="">All statuses</option>
                            {['DRAFT', 'SUBMITTED', 'IN_REVIEW', 'PROCUREMENT_PLACED', 'PROCESSING', 'PARTIALLY_RELEASED', 'PARTIALLY_RECEIVED', 'RELEASED', 'RECEIVED', 'COMPLETED', 'REJECTED', 'RETURNED_FOR_REVISION'].map((option) => (
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
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search request code, purpose, status..."
                            className="h-8 rounded-lg border-slate-300 py-1 text-xs"
                        />
                    </div>
                </section>

                                {/* Urgent Pinned Banner */}
                {supplyRequests.data.some((r) => r.priority_level === 'URGENT') && (
                    <div className="rounded-xl border-2 border-red-300 bg-red-50 p-3.5 shadow-xs">
                        <div className="flex items-center gap-2 font-bold text-red-900 text-xs sm:text-sm">
                            <span className="flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-white shadow-2xs">
                                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </span>
                            <span className="uppercase tracking-wider">Urgent Requests Pinned to Top</span>
                            <span className="ml-auto rounded-full bg-red-600 px-2.5 py-0.5 text-[10px] text-white font-black tracking-wider uppercase">
                                {supplyRequests.data.filter((r) => r.priority_level === 'URGENT').length} URGENT PINNED
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-red-700 pl-8">
                            Items tagged as Urgent are prioritized at the top of your request history and expedited for review.
                        </p>
                    </div>
                )}

                {/* Snug Table Container with Pagination Attached Directly Below Records */}
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    {supplyRequests.data.length === 0 ? (
                        <div className="px-4 py-8 text-center text-sm text-slate-500">No requests found. Create your first supply request.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-xs">
                                <thead className="bg-slate-50 shadow-xs">
                                    <tr>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Request ID</th>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Priority</th>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Date Submitted</th>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                        <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Items</th>
                                        <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white">
                                    {supplyRequests.data.map((row) => {
                                        const isUrgent = row.priority_level === 'URGENT';
                                        return (
                                            <tr key={row.supply_request_id} className={`hover:bg-slate-50/60 transition-colors ${isUrgent ? 'border-l-4 border-l-red-600 bg-red-50/20' : ''}`}>
                                                <td className="px-3.5 py-2 font-semibold text-red-700">
                                                    <div className="flex flex-col">
                                                        <span>{row.request_code}</span>
                                                        {row.follow_up_context?.is_follow_up && row.follow_up_context.parent && (
                                                            <span className="mt-0.5 inline-flex w-fit items-center gap-1 rounded bg-amber-50 border border-amber-200 px-1.5 py-0.5 text-[10px] font-bold text-amber-900 shadow-2xs">
                                                                <svg className="h-3 w-3 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                                </svg>
                                                                Follow-up of {row.follow_up_context.parent.request_code}
                                                            </span>
                                                        )}
                                                        {!row.follow_up_context?.is_follow_up && (row.follow_up_context?.children?.length ?? 0) > 0 && (
                                                            <span className="mt-0.5 inline-flex w-fit items-center gap-1 rounded bg-blue-50 border border-blue-200 px-1.5 py-0.5 text-[10px] font-bold text-blue-900 shadow-2xs">
                                                                <svg className="h-3 w-3 shrink-0 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                                Has Follow-Up ({row.follow_up_context!.children!.length} batch{(row.follow_up_context!.children!.length ?? 0) === 1 ? '' : 'es'})
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                            <td className="px-3.5 py-2">
                                                {isUrgent ? (
                                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-red-300 bg-red-100 px-2 py-0.5 text-[10px] font-black text-red-800 shadow-2xs uppercase tracking-wide">
                                                        <span className="h-1.5 w-1.5 rounded-full bg-red-600"></span> Urgent
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">
                                                        Normal
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3.5 py-2 text-slate-700">{formatDate(row.request_date)}</td>
                                            <td className="px-3.5 py-2">
                                                <StatusBadge status={row.status} />
                                            </td>
                                            <td className="px-3.5 py-2 text-slate-700">{row.items_count ?? 0} item(s)</td>
                                            <td className="px-3.5 py-2 text-right">
                                                <div className="inline-flex flex-wrap justify-end gap-1.5">
                                                    <Link
                                                        href={route('office.supply-requests.show', row.supply_request_id)}
                                                        className="inline-flex items-center rounded border border-red-200 bg-red-50/50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition"
                                                    >
                                                        View Details
                                                    </Link>
                                                    {(row.raw_status ?? row.status) === 'DRAFT' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => submitDraft(row)}
                                                            disabled={submittingDraftId === row.supply_request_id}
                                                            className="inline-flex items-center rounded bg-red-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60 transition"
                                                        >
                                                            {submittingDraftId === row.supply_request_id ? 'Submitting...' : 'Submit'}
                                                        </button>
                                                    )}
                                                    {(row.raw_status ?? row.status) === 'DRAFT' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setDraftToDelete(row)}
                                                            className="inline-flex items-center rounded border border-rose-200 bg-rose-50/50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition"
                                                        >
                                                            Delete
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <div className="border-t border-slate-200 bg-slate-50/70 px-3.5 py-2 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs text-slate-500 font-medium">
                            Showing <span className="font-semibold text-slate-700">{supplyRequests.data.length}</span> supply requests
                        </p>
                        <PaginationNav links={supplyRequests.links} preserveState={false} />
                    </div>
                </section>
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




