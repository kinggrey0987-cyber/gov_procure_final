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
    const statusOptions = ['OPEN', 'PENDING_REPLACEMENT', 'RESOLVED', 'REJECTED'];

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
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">{canProcess ? 'Claims Review' : 'Claims and Adjustments'}</h2>
                        <p className="mt-1 text-xs font-normal text-slate-500">
                            {canProcess ? 'GSO reviews and resolves claims filed by offices.' : 'Office users file claims for issues found after distribution release or receipt.'}
                        </p>
                    </div>
                    {canCreate ? (
                        <Link href={route(routeNames.create)} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            File Claim
                        </Link>
                    ) : null}
                </div>
            }
        >
            <Head title="Claims" />
            <div className="py-8">
                <div className="mb-4 grid gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm md:grid-cols-5">
                    <select
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">All statuses</option>
                        {statusOptions.map((option) => <option key={option} value={option}>{displayClaimStatus(option)}</option>)}
                    </select>
                    <select
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
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
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
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
                    <input
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        value={search}
                        placeholder="Search claim code, type, office"
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <button
                        type="button"
                        onClick={() => router.get(route(routeNames.index))}
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Reset
                    </button>
                </div>

                <div className="mb-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-sm text-slate-600">
                        {canProcess
                            ? 'Review office-filed post-distribution claims and record GSO resolution decisions.'
                            : 'Use this page to file and track post-distribution issues for supplies released or received by your office.'}
                    </p>
                </div>

                <div className="responsive-table-wrap rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Code</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Type</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Office</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {claims.data.map((row) => (
                                <tr key={row.claims_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 font-mono text-xs text-slate-700">{row.claim_code}</td>
                                    <td className="px-4 py-3 text-slate-700">{row.claim_type}</td>
                                    <td className="px-4 py-3">
                                        <span className={statusClass(row.status)}>{displayClaimStatus(row.status)}</span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{row.office?.name ?? row.office_id ?? '-'}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={route(routeNames.show, row.claims_id)} className="text-sm font-semibold text-red-700 hover:text-red-900">
                                            {canProcess && ['OPEN', 'IN_REVIEW', 'PENDING_REPLACEMENT', 'PARTIALLY_RESOLVED'].includes(row.status) ? 'Review' : 'View'}
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4">
                    <PaginationNav links={claims.links} preserveState={false} />
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





