import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import MonthFilterSelect from '@/Components/MonthFilterSelect';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type AlertRow = {
    alerts_id: number;
    alert_type: string;
    severity: string;
    status: string;
    detected_at: string;
};

type Props = {
    alerts: {
        data: AlertRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        status?: string;
        severity?: string;
        year?: string;
        month?: string;
        search?: string;
    };
    filter_options?: {
        years: number[];
    };
    permissions?: {
        review: boolean;
    };
};

export default function ExecutiveAlertIndex({ alerts, filters, filter_options, permissions }: Props) {
    const canReview = permissions?.review === true;
    const [status, setStatus] = useState(filters.status ?? '');
    const [severity, setSeverity] = useState(filters.severity ?? '');
    const [year, setYear] = useState(filters.year ?? '');
    const [month, setMonth] = useState(filters.month ?? '');
    const [search, setSearch] = useState(filters.search ?? '');
    const debouncedSearch = useDebouncedValue(search, 450);
    const hasMountedRef = useRef(false);

    useEffect(() => {
        setStatus(filters.status ?? '');
        setSeverity(filters.severity ?? '');
        setYear(filters.year ?? '');
        setMonth(filters.month ?? '');
        setSearch(filters.search ?? '');
    }, [filters.month, filters.search, filters.severity, filters.status, filters.year]);

    const activeFilters = useMemo(
        () => ({
            status,
            severity,
            year,
            month,
            search: debouncedSearch,
        }),
        [status, severity, year, month, debouncedSearch],
    );

    useEffect(() => {
        if (!hasMountedRef.current) {
            hasMountedRef.current = true;

            return;
        }

        router.get(route('executive.alerts.index'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['alerts', 'filters'],
        });
    }, [activeFilters]);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Alerts</h2>}>
            <Head title="Alerts" />
            <div className="py-10">
                <div className="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-2 rounded-xl border bg-white p-3 md:grid-cols-5">
                        <select
                            className="rounded border px-3 py-2 text-sm"
                            value={severity}
                            onChange={(event) => setSeverity(event.target.value)}
                        >
                            <option value="">All severities</option>
                            {['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'].map((option) => <option key={option} value={option}>{option}</option>)}
                        </select>
                        <select
                            className="rounded border px-3 py-2 text-sm"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            <option value="">All statuses</option>
                            {['OPEN', 'IN_REVIEW', 'RESOLVED', 'DISMISSED'].map((option) => <option key={option} value={option}>{option.replaceAll('_', ' ')}</option>)}
                        </select>
                        <MonthFilterSelect
                            className="rounded border px-3 py-2 text-sm"
                            month={month}
                            year={year}
                            yearOptions={filter_options?.years ?? []}
                            onMonthChange={setMonth}
                            onYearChange={setYear}
                        />
                        <input
                            className="rounded border px-3 py-2 text-sm"
                            value={search}
                            placeholder="Search alert type or office"
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    <div className="responsive-table-wrap rounded border bg-white">
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left">Type</th>
                                    <th className="px-4 py-2 text-left">Severity</th>
                                    <th className="px-4 py-2 text-left">Status</th>
                                    <th className="px-4 py-2 text-left">Detected</th>
                                    <th className="px-4 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {alerts.data.map((row) => (
                                    <tr key={row.alerts_id}>
                                        <td className="px-4 py-2">{row.alert_type}</td>
                                        <td className="px-4 py-2">{row.severity}</td>
                                        <td className="px-4 py-2">{row.status}</td>
                                        <td className="px-4 py-2">{row.detected_at}</td>
                                        <td className="px-4 py-2">
                                            {canReview ? (
                                                <Link href={route('executive.alerts.show', row.alerts_id)} className="rounded bg-indigo-600 px-3 py-1 text-xs text-white">
                                                    Open Executive Review
                                                </Link>
                                            ) : (
                                                <span className="rounded bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                                    Queue visibility only
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                        </tbody>
                    </table>

                    {alerts.links && alerts.links.length > 3 && (
                        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/70 px-4 py-2.5">
                            <span className="text-xs font-medium text-slate-500">
                                Showing {alerts.data.length} alerts
                            </span>
                            <PaginationNav links={alerts.links} preserveState={false} />
                        </div>
                    )}
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
);
}





