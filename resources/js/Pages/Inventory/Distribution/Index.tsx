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
            header="Distribution Records"
            headerActions={
                <Link
                    href={route('inventory.distribution-records.create')}
                    className="inline-flex h-8 items-center rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 shadow-sm transition"
                >
                    <svg className="mr-1.5 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                    </svg>
                    New Distribution
                </Link>
            }
        >
            <Head title="Distribution Records" />

            <div className="space-y-2 lg:h-[calc(100vh-8.5rem)] flex flex-col justify-between overflow-y-auto lg:overflow-hidden">
                {/* Compact Filter Bar */}
                <div className="shrink-0 rounded-xl border border-slate-200 bg-white p-2 shadow-xs">
                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            type="text"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search code, office request, office..."
                            className="h-8 flex-1 min-w-[180px] rounded-lg border border-slate-300 px-2.5 text-xs focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        />
                        <select
                            value={year}
                            onChange={(event) => setYear(event.target.value)}
                            className="h-8 rounded-lg border border-slate-300 px-2.5 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
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
                            className="h-8 rounded-lg border border-slate-300 px-2.5 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
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
                        <select
                            value={statusFilter}
                            onChange={(event) => setStatusFilter(event.target.value)}
                            className="h-8 rounded-lg border border-slate-300 px-2.5 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        >
                            <option value="">All statuses</option>
                            <option value="DRAFT">Draft</option>
                            <option value="RELEASED">Released</option>
                            <option value="RECEIVED">Received</option>
                        </select>
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                setStatusFilter('');
                                setMonth('');
                            }}
                            className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                {/* High-Density Distribution Table with Sticky Headers and Internal Scroll */}
                <div className="flex-1 min-h-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs flex flex-col">
                    <div className="flex-1 overflow-y-auto min-h-0">
                        <table className="min-w-full divide-y divide-slate-200 text-xs">
                            <thead className="sticky top-0 z-10 bg-slate-50 shadow-xs">
                                <tr>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Code</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Linked Office Request</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Office</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Release Date</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Items / Claims</th>
                                    <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {filteredRecords.map((row) => (
                                    <tr key={row.distribution_record_id} className="hover:bg-slate-50/60">
                                        <td className="px-3.5 py-2 font-mono text-[11px] font-semibold text-slate-700">{row.distribution_code}</td>
                                        <td className="px-3.5 py-2 font-mono text-[11px] text-slate-600">{row.supply_request?.request_code ?? '-'}</td>
                                        <td className="px-3.5 py-2 text-slate-700">{row.office?.name ?? row.office_id ?? '-'}</td>
                                        <td className="px-3.5 py-2">
                                            <span className={statusClass(row.status)}>{displayDistributionStatus(row.status)}</span>
                                        </td>
                                        <td className="px-3.5 py-2 text-slate-500">{formatDate(row.release_date)}</td>
                                        <td className="px-3.5 py-2 text-slate-600">{row.items_count} released / {row.claims_count} claim(s)</td>
                                        <td className="px-3.5 py-2 text-right">
                                            <Link
                                                href={route('inventory.distribution-records.show', row.distribution_record_id)}
                                                className="font-semibold text-xs text-red-600 hover:text-red-800"
                                            >
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                                {filteredRecords.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-8 text-center text-xs text-slate-500">
                                            No records matched the selected filters.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="shrink-0 border-t border-slate-200 bg-slate-50/70 px-3 py-1.5">
                        <PaginationNav links={records.links} preserveState={false} />
                    </div>
                </div>
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

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const datePart = value.match(/^\d{4}-\d{2}-\d{2}/)?.[0];
    const parsed = new Date(datePart ? `${datePart}T00:00:00` : value);

    return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

function displayDistributionStatus(status: string): string {
    const map: Record<string, string> = {
        RELEASED: 'Released to Office',
        RECEIVED: 'Received by Office',
    };

    return map[status] ?? status;
}





