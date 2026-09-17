import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import MonthFilterSelect from '@/Components/MonthFilterSelect';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type ClaimRow = {
    claims_id: number;
    claim_code: string;
    claim_type: string;
    status: string;
    office?: { name?: string | null } | null;
    office_id?: number | string;
};

type Props = {
    claims: {
        data: ClaimRow[];
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
    routes?: {
        index: string;
        create: string;
        show: string;
    };
    permissions?: {
        create: boolean;
        process: boolean;
    };
};

export default function ClaimIndex({ claims, filters, filter_options, routes, permissions }: Props) {
    const routeNames = routes ?? {
        index: 'inventory.claims.index',
        create: 'inventory.claims.create',
        show: 'inventory.claims.show',
    };
    const canCreate = permissions?.create ?? false;
    const canProcess = permissions?.process ?? false;
    const [status, setStatus] = useState(filters?.status ?? '');
    const [year, setYear] = useState(filters?.year ?? '');
    const [month, setMonth] = useState(filters?.month ?? '');
    const [search, setSearch] = useState(filters?.search ?? '');
    const debouncedSearch = useDebouncedValue(search, 450);
    const hasMountedRef = useRef(false);
    const statusOptions = ['OPEN', 'IN_REVIEW', 'PENDING_REPLACEMENT', 'PARTIALLY_RESOLVED', 'RESOLVED', 'REJECTED'];

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
        [status, year, month, debouncedSearch],
    );

    useEffect(() => {
        if (!hasMountedRef.current) {
            hasMountedRef.current = true;

            return;
        }

        router.get(route(routeNames.index), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['claims', 'filters'],
        });
    }, [activeFilters]);

    return (
        <AuthenticatedLayout
            header={canProcess ? 'Claims Review' : 'Claims and Adjustments'}
            headerActions={
                canCreate ? (
                    <Link
                        href={route(routeNames.create)}
                        className="inline-flex h-8 items-center rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 shadow-sm transition"
                    >
                        <svg className="mr-1.5 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                        </svg>
                        File Claim
                    </Link>
                ) : undefined
            }
        >
            <Head title="Claims" />

            <div className="space-y-3">
                {/* Compact Filter Bar */}
                <div className="shrink-0 rounded-xl border border-slate-200 bg-white p-2 shadow-xs">
                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            className="h-8 flex-1 min-w-[180px] rounded-lg border border-slate-300 px-2.5 text-xs focus:border-red-500 focus:ring-1 focus:ring-red-500"
                            value={search}
                            placeholder="Search claim code, type, office..."
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <select
                            className="h-8 rounded-lg border border-slate-300 pl-2.5 pr-8 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            <option value="">All statuses</option>
                            {statusOptions.map((option) => (
                                <option key={option} value={option}>{displayClaimStatus(option)}</option>
                            ))}
                        </select>
                        <select
                            className="h-8 rounded-lg border border-slate-300 pl-2.5 pr-8 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                            value={year}
                            onChange={(event) => setYear(event.target.value)}
                        >
                            <option value="">All years</option>
                            {(filter_options?.years ?? []).map((option) => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                        <select
                            className="h-8 rounded-lg border border-slate-300 pl-2.5 pr-8 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                            value={month}
                            onChange={(event) => setMonth(event.target.value)}
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
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </select>
                        <button
                            type="button"
                            className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs"
                            onClick={() => {
                                setSearch('');
                                setStatus('');
                                setYear('');
                                setMonth('');
                            }}
                        >
                            Reset
                        </button>
                    </div>
                </div>

                {/* Snug Claims Table with Pagination Attached Directly Below Records */}
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-xs">
                            <thead className="bg-slate-50 shadow-xs">
                                <tr>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Code</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Type</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Office</th>
                                    <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {claims.data.map((row) => (
                                    <tr key={row.claims_id} className="hover:bg-slate-50/60">
                                        <td className="px-3.5 py-2 font-mono text-[11px] font-semibold text-slate-700">{row.claim_code}</td>
                                        <td className="px-3.5 py-2 text-slate-700">{row.claim_type}</td>
                                        <td className="px-3.5 py-2">
                                            <span className={statusClass(row.status)}>{displayClaimStatus(row.status)}</span>
                                        </td>
                                        <td className="px-3.5 py-2 text-slate-600">{row.office?.name ?? row.office_id ?? '-'}</td>
                                        <td className="px-3.5 py-2 text-right">
                                            <Link href={route(routeNames.show, row.claims_id)} className="font-semibold text-xs text-red-600 hover:text-red-800">
                                                {canProcess && ['OPEN', 'IN_REVIEW', 'PENDING_REPLACEMENT', 'PARTIALLY_RESOLVED'].includes(row.status) ? 'Review' : 'View'}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                                {claims.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-8 text-center text-xs text-slate-500">
                                            No claim records found.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-slate-200 bg-slate-50/70 px-3.5 py-2 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs text-slate-500 font-medium">
                            Showing <span className="font-semibold text-slate-700">{claims.data.length}</span> claim records
                        </p>
                        <PaginationNav links={claims.links} preserveState={false} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function displayClaimStatus(status: string): string {
    const map: Record<string, string> = {
        OPEN: 'Pending Review',
        FILED: 'Pending Review',
        IN_REVIEW: 'In Claim Review',
        PENDING_REPLACEMENT: 'Pending Replacement',
        PARTIALLY_RESOLVED: 'Partially Resolved',
        RESOLVED: 'Resolved',
        REJECTED: 'Rejected',
    };

    return map[status] ?? status;
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        OPEN: `${base} bg-amber-100 text-amber-700`,
        FILED: `${base} bg-amber-100 text-amber-700`,
        IN_REVIEW: `${base} bg-blue-100 text-blue-700`,
        PENDING_REPLACEMENT: `${base} bg-orange-100 text-orange-700`,
        PARTIALLY_RESOLVED: `${base} bg-cyan-100 text-cyan-700`,
        RESOLVED: `${base} bg-emerald-100 text-emerald-700`,
        REJECTED: `${base} bg-rose-100 text-rose-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}




