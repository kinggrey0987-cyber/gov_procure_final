import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import MonthFilterSelect from '@/Components/MonthFilterSelect';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type RecordRow = {
    distribution_record_id: number;
    distribution_code: string;
    status: string;
    release_date?: string | null;
    supply_request?: { request_code?: string | null } | null;
    items_count: number;
    claims_count: number;
    office?: { name?: string | null } | null;
    office_id?: number | string;
};

type Props = {
    records: {
        data: RecordRow[];
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

export default function DistributionIndex({ records, filters, filter_options }: Props) {
    const [search, setSearch] = useState(filters?.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters?.status ?? '');
    const [year, setYear] = useState(filters?.year ?? '');
    const [month, setMonth] = useState(filters?.month ?? '');
    const debouncedSearch = useDebouncedValue(search, 450);
    const hasMountedRef = useRef(false);

    useEffect(() => {
        setSearch(filters?.search ?? '');
        setStatusFilter(filters?.status ?? '');
        setYear(filters?.year ?? '');
        setMonth(filters?.month ?? '');
    }, [filters?.month, filters?.search, filters?.status, filters?.year]);

    const activeFilters = useMemo(
        () => ({
            status: statusFilter,
            year,
            month,
            search: debouncedSearch,
        }),
        [statusFilter, year, month, debouncedSearch],
    );

    useEffect(() => {
        if (!hasMountedRef.current) {
            hasMountedRef.current = true;

            return;
        }

        router.get(route('inventory.distribution-records.index'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['records', 'filters'],
        });
    }, [activeFilters]);

    const filteredRecords = useMemo(() => {
        const keyword = debouncedSearch.trim().toLowerCase();

        return records.data.filter((row) => {
            const matchesStatus = statusFilter === '' || row.status === statusFilter;

            if (!matchesStatus) {
                return false;
            }

            if (!keyword) {
                return true;
            }

            const haystack = [
                row.distribution_code,
                row.supply_request?.request_code ?? '',
                row.office?.name ?? String(row.office_id ?? ''),
                displayDistributionStatus(row.status),
            ]
                .join(' ')
                .toLowerCase();

            return haystack.includes(keyword);
        });
    }, [records.data, debouncedSearch, statusFilter]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Distribution Records</h2>
                    <Link href={route('inventory.distribution-records.create')} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                        Open Supply Distribution
                    </Link>
                </div>
            }
        >
            <Head title="Distribution Records" />
            <div className="py-8">
                <div className="mb-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-sm text-slate-600">
                        This page is for historical distribution records and audit viewing only. Operational release processing is done in Supply Distribution.
                    </p>
                </div>

                <div className="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="grid gap-3 md:grid-cols-[1fr_220px_180px_180px_auto] md:items-end">
                        <div>
                            <label htmlFor="distribution-search" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Search
                            </label>
                                <input
                                    id="distribution-search"
                                    type="text"
                                    value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Code, office request, office, status"
                                className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                            />
                        </div>

                        <div>
                            <label htmlFor="distribution-year" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Year
                            </label>
                            <select
                                id="distribution-year"
                                value={year}
                                onChange={(event) => setYear(event.target.value)}
                                className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                            >
                                <option value="">All years</option>
                                {(filter_options?.years ?? []).map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label htmlFor="distribution-month" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Month
                            </label>
                            <select
                                id="distribution-month"
                                value={month}
                                onChange={(event) => setMonth(event.target.value)}
                                className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
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
                        </div>

                        <div>
                            <label htmlFor="distribution-status" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Status
                            </label>
                            <select
                                id="distribution-status"
                                value={statusFilter}
                                onChange={(event) => setStatusFilter(event.target.value)}
                                className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                            >
                                <option value="">All statuses</option>
                                <option value="DRAFT">Draft</option>
                                <option value="RELEASED">Released</option>
                                <option value="RECEIVED">Received</option>
                            </select>
                        </div>

                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                setStatusFilter('');
                                setMonth('');
                            }}
                            className="inline-flex h-[42px] items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Reset Filters
                        </button>
                    </div>

                    <p className="mt-3 text-xs text-slate-500">
                        Showing {filteredRecords.length} of {records.data.length} record(s) on this page.
                    </p>
                </div>

                <div className="responsive-table-wrap rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Code</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Linked Office Request</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Office</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Release Date</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Items / Claims</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {filteredRecords.map((row) => (
                                <tr key={row.distribution_record_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 font-mono text-xs text-slate-700">{row.distribution_code}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-slate-700">{row.supply_request?.request_code ?? '-'}</td>
                                    <td className="px-4 py-3 text-slate-700">{row.office?.name ?? row.office_id ?? '-'}</td>
                                    <td className="px-4 py-3">
                                        <span className={statusClass(row.status)}>{displayDistributionStatus(row.status)}</span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{row.release_date || '-'}</td>
                                    <td className="px-4 py-3 text-slate-600">{row.items_count} released / {row.claims_count} claim(s)</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={route('inventory.distribution-records.show', row.distribution_record_id)}
                                            className="text-sm font-semibold text-red-700 hover:text-red-900"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {filteredRecords.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No records matched the selected filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <PaginationNav links={records.links} preserveState={false} />
            </div>
        </AuthenticatedLayout>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        DRAFT: `${base} bg-slate-100 text-slate-700`,
        RELEASED: `${base} bg-blue-100 text-blue-700`,
        RECEIVED: `${base} bg-emerald-100 text-emerald-700`,
        CANCELLED: `${base} bg-rose-100 text-rose-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function displayDistributionStatus(status: string): string {
    const map: Record<string, string> = {
        RELEASED: 'Released to Office',
        RECEIVED: 'Received by Office',
    };

    return map[status] ?? status;
}





