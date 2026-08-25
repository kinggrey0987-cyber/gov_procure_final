import PaginationNav from '@/Components/PaginationNav';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type DisclosureRow = {
    disclosure_id: number;
    title: string;
    type: string;
    published_at: string;
    primary_source?: string | null;
    linked_records_count?: number;
};

type Props = {
    disclosures: {
        data: DisclosureRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page?: number;
    };
    filters?: {
        year?: string;
        type?: string;
        search?: string;
    };
    availableYears?: string[];
    availableTypes?: string[];
};

export default function PublicDisclosureIndex({ disclosures, filters = {}, availableYears = ['2026'], availableTypes = [] }: Props) {
    const rows = disclosures.data ?? [];
    const currentPage = disclosures.current_page || undefined;

    const [search, setSearch] = useState(filters.search || '');
    const [selectedYear, setSelectedYear] = useState(filters.year || '');
    const [selectedType, setSelectedType] = useState(filters.type || '');

    const handleFilterChange = (newYear: string, newType: string, newSearch: string) => {
        router.get(
            route('public.disclosures.index'),
            {
                year: newYear || undefined,
                type: newType || undefined,
                search: newSearch || undefined,
            },
            {
                preserveState: true,
                replace: true,
            }
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        handleFilterChange(selectedYear, selectedType, search);
    };

    const handleReset = () => {
        setSearch('');
        setSelectedYear('');
        setSelectedType('');
        router.get(route('public.disclosures.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Public Disclosures" />
            <div className="min-h-screen bg-gradient-to-b from-red-50 via-white to-red-50 py-12">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-2xl border border-red-200 bg-white p-6 shadow-[0_10px_24px_rgba(15,23,42,0.08)] sm:p-8">
                        <div className="mb-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-stretch">
                            <div>
                                <Link
                                    href={route('landing')}
                                    className="inline-flex items-center gap-2 rounded-full border border-red-700 bg-red-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-red-900/20 transition-all hover:-translate-y-0.5 hover:bg-red-800 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2"
                                >
                                    <span aria-hidden="true">←</span>
                                    Back to Homepage
                                </Link>

                                <h1 className="mt-6 text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl">Public Disclosure Packages</h1>
                                <p className="mt-3 max-w-3xl text-base leading-7 text-slate-600">
                                    Published procurement packages with official PDF downloads and source traceability.
                                </p>
                            </div>

                            <div className="flex rounded-2xl border border-slate-200 bg-slate-50 px-6 py-5 shadow-sm lg:min-h-40 lg:flex-col lg:items-center lg:justify-center">
                                <div>
                                    <p className="text-sm font-semibold uppercase tracking-wide text-slate-600">Published on this page</p>
                                    <p className="mt-2 text-5xl font-black tracking-tight text-slate-900">{rows.length}</p>
                                    <p className="mt-1 text-sm font-medium text-slate-600">public disclosure packages</p>
                                </div>
                            </div>
                        </div>

                        {/* Search and Filters Bar */}
                        <div className="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
                            <form onSubmit={handleSearchSubmit} className="grid gap-3 sm:grid-cols-12 sm:items-center">
                                <div className="sm:col-span-4">
                                    <label htmlFor="search" className="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Search Disclosures
                                    </label>
                                    <div className="relative mt-1">
                                        <input
                                            id="search"
                                            type="text"
                                            placeholder="Search by title or keyword..."
                                            value={search}
                                            onChange={(e) => setSearch(e.target.value)}
                                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-red-600 focus:outline-none focus:ring-1 focus:ring-red-600"
                                        />
                                    </div>
                                </div>

                                <div className="sm:col-span-3">
                                    <label htmlFor="year-filter" className="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Fiscal Year
                                    </label>
                                    <select
                                        id="year-filter"
                                        value={selectedYear}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            setSelectedYear(val);
                                            handleFilterChange(val, selectedType, search);
                                        }}
                                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:border-red-600 focus:outline-none focus:ring-1 focus:ring-red-600"
                                    >
                                        <option value="">All Fiscal Years</option>
                                        {availableYears.map((yr) => (
                                            <option key={yr} value={yr}>
                                                Year {yr}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="sm:col-span-3">
                                    <label htmlFor="type-filter" className="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Category / Type
                                    </label>
                                    <select
                                        id="type-filter"
                                        value={selectedType}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            setSelectedType(val);
                                            handleFilterChange(selectedYear, val, search);
                                        }}
                                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:border-red-600 focus:outline-none focus:ring-1 focus:ring-red-600"
                                    >
                                        <option value="">All Categories / Types</option>
                                        {availableTypes.length > 0 ? (
                                            availableTypes.map((t) => (
                                                <option key={t} value={t}>
                                                    {formatTypeLabel(t)}
                                                </option>
                                            ))
                                        ) : (
                                            <>
                                                <option value="PROCUREMENT">Procurement Package</option>
                                                <option value="PPMP">PPMP Package</option>
                                                <option value="PURCHASE_ORDER">Purchase Order Package</option>
                                                <option value="DISTRIBUTION">Distribution Package</option>
                                            </>
                                        )}
                                    </select>
                                </div>

                                <div className="flex items-end gap-2 sm:col-span-2 sm:mt-5">
                                    <button
                                        type="submit"
                                        className="flex-1 rounded-lg bg-red-700 px-3 py-2 text-center text-xs font-bold text-white shadow-sm transition-colors hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600"
                                    >
                                        Search
                                    </button>
                                    {(selectedYear || selectedType || search) && (
                                        <button
                                            type="button"
                                            onClick={handleReset}
                                            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-100"
                                        >
                                            Reset
                                        </button>
                                    )}
                                </div>
                            </form>
                        </div>

                        <div className="mt-5 grid gap-3 md:grid-cols-3">
                            <SummaryCard label="Packages on Page" value={String(rows.length)} />
                            <SummaryCard label="Linked Source Records" value={String(rows.reduce((sum, row) => sum + (row.linked_records_count ?? 0), 0))} />
                            <SummaryCard label="Official Downloads" value="Available per package" />
                        </div>

                        <div className="mt-6 overflow-hidden rounded-xl border border-red-200">
                            {rows.length === 0 ? (
                                <div className="px-6 py-12 text-center">
                                    <p className="text-base font-medium text-slate-800">No public disclosures available yet.</p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-red-100 text-sm">
                                        <thead className="bg-red-50/80">
                                            <tr>
                                                 <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-700">Title</th>
                                                 <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-700">Type</th>
                                                <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-700">Primary Source</th>
                                                <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-700">Linked Records</th>
                                                 <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-700">Published</th>
                                                 <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-700">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-red-100 bg-white">
                                            {rows.map((row) => (
                                                <tr key={row.disclosure_id} className="transition-colors hover:bg-red-50/50">
                                                    <td className="px-5 py-3.5 text-sm font-medium text-slate-900">{row.title}</td>
                                                     <td className="px-5 py-3.5 text-slate-700">
                                                         <span className="inline-flex rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                                                             {formatTypeLabel(row.type)}
                                                         </span>
                                                     </td>
                                                    <td className="px-5 py-3.5 text-sm font-semibold text-slate-800">{row.primary_source || 'Disclosure package'}</td>
                                                    <td className="px-5 py-3.5 text-slate-700">{row.linked_records_count ?? 0} linked</td>
                                                     <td className="px-5 py-3.5 text-slate-700">{formatPublishedDate(row.published_at)}</td>
                                                     <td className="px-5 py-3.5">
                                                        <div className="flex flex-wrap gap-2">
                                                            <Link
                                                                href={buildDisclosureHref(row.disclosure_id, currentPage)}
                                                                className="inline-flex rounded-lg bg-red-700 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-red-800"
                                                            >
                                                                View Details
                                                            </Link>
                                                            <a
                                                                href={route('public.disclosures.download', row.disclosure_id)}
                                                                className="inline-flex rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                                            >
                                                                Download Package
                                                            </a>
                                                        </div>
                                                     </td>
                                                 </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>

                        <div className="mt-5">
                            <PaginationNav links={disclosures.links} preserveState={false} />
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

function SummaryCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-1 text-lg font-bold text-slate-900">{value}</p>
        </div>
    );
}

function buildDisclosureHref(disclosureId: number, page?: number): string {
    const base = `/public/disclosures/${disclosureId}`;
    return page ? `${base}?page=${page}` : base;
}

function formatTypeLabel(value: string): string {
    return value
        .replaceAll('_', ' ')
        .toLowerCase()
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatPublishedDate(value: string): string {
    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    const date = parsed.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    const time = parsed.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });

    return `${date} • ${time}`;
}





