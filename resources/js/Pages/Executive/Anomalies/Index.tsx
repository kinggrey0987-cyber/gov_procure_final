import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import MonthFilterSelect from '@/Components/MonthFilterSelect';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type AnomalyRow = {
    anomaly_id: number;
    anomaly_type: string;
    severity: string;
    status: string;
    detected_at: string;
};

type Props = {
    anomalies: {
        data: AnomalyRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { status?: string; severity?: string; anomaly_type?: string; year?: string; month?: string; search?: string };
    filter_options: { anomaly_types: string[]; years: number[] };
};

export default function ExecutiveAnomalyIndex({ anomalies, filters, filter_options }: Props) {
    const [status, setStatus] = useState(filters.status ?? '');
    const [severity, setSeverity] = useState(filters.severity ?? '');
    const [anomalyType, setAnomalyType] = useState(filters.anomaly_type ?? '');
    const [year, setYear] = useState(filters.year ?? '');
    const [month, setMonth] = useState(filters.month ?? '');
    const [search, setSearch] = useState(filters.search ?? '');
    const hasMountedRef = useRef(false);

    const debouncedSearch = useDebouncedValue(search, 450);

    useEffect(() => {
        setStatus(filters.status ?? '');
        setSeverity(filters.severity ?? '');
        setAnomalyType(filters.anomaly_type ?? '');
        setYear(filters.year ?? '');
        setMonth(filters.month ?? '');
        setSearch(filters.search ?? '');
    }, [filters.anomaly_type, filters.month, filters.search, filters.severity, filters.status, filters.year]);

    const activeFilters = useMemo(
        () => ({
            status,
            severity,
            anomaly_type: anomalyType,
            year,
            month,
            search: debouncedSearch,
        }),
        [status, severity, anomalyType, year, month, debouncedSearch],
    );

    useEffect(() => {
        if (!hasMountedRef.current) {
            hasMountedRef.current = true;

            return;
        }

        router.get(route('executive.anomalies.index'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['anomalies', 'filters', 'filter_options'],
        });
    }, [activeFilters]);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Executive Anomalies</h2>}>
            <Head title="Executive Anomalies" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-2 rounded-xl border bg-white p-3 md:grid-cols-6">
                        <select
                            className="rounded border px-3 py-2 text-sm"
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                        >
                            <option value="">All statuses</option>
                            {['OPEN', 'IN_REVIEW', 'RESOLVED', 'DISMISSED'].map((option) => <option key={option} value={option}>{option.replaceAll('_', ' ')}</option>)}
                        </select>
                        <select
                            className="rounded border px-3 py-2 text-sm"
                            value={severity}
                            onChange={(e) => setSeverity(e.target.value)}
                        >
                            <option value="">All severities</option>
                            {['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'].map((option) => <option key={option} value={option}>{option}</option>)}
                        </select>
                        <select
                            className="rounded border px-3 py-2 text-sm"
                            value={anomalyType}
                            onChange={(e) => setAnomalyType(e.target.value)}
                        >
                            <option value="">All anomaly types</option>
                            {filter_options.anomaly_types.map((option) => (
                                <option key={option} value={option}>{formatAnomalyType(option)}</option>
                            ))}
                        </select>
                        <MonthFilterSelect
                            className="rounded border px-3 py-2 text-sm"
                            month={month}
                            year={year}
                            yearOptions={filter_options.years}
                            onMonthChange={setMonth}
                            onYearChange={setYear}
                        />
                        <input
                            className="rounded border px-3 py-2 text-sm"
                            value={search}
                            placeholder="Search office/type/status"
                            onChange={(e) => setSearch(e.target.value)}
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
                                {anomalies.data.map((row) => (
                                    <tr key={row.anomaly_id}>
                                        <td className="px-4 py-2">{formatAnomalyType(row.anomaly_type)}</td>
                                        <td className="px-4 py-2">{row.severity}</td>
                                        <td className="px-4 py-2">{row.status}</td>
                                        <td className="px-4 py-2">{row.detected_at}</td>
                                        <td className="px-4 py-2">
                                            <Link href={route('executive.anomalies.show', row.anomaly_id)} className="rounded bg-indigo-600 px-3 py-1 text-xs text-white">
                                                Open Executive Review
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <PaginationNav links={anomalies.links} preserveState={false} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function formatAnomalyType(value: string): string {
    const aliases: Record<string, string> = {
        PRICE_Z_SCORE: 'Price Anomaly',
        PRICE_ANOMALY: 'Price Anomaly',
        Z_SCORE_PRICE_ANOMALY: 'Price Anomaly',
        FREQUENT_REQUEST_WINDOW: 'Frequent Request Spike',
        FREQUENT_REQUEST_SPIKE: 'Frequent Request Spike',
        EXCESSIVE_QUANTITY: 'Excessive Quantity Anomaly',
        PPMP_OVER_REQUEST: 'PPMP Over-Request',
        PPMP_EARLY_DEPLETION: 'PPMP Early Depletion',
        PPMP_FREQUENCY_IRREGULARITY: 'PPMP Frequency Irregularity',
    };

    const normalized = value.toUpperCase();

    if (aliases[normalized]) {
        return aliases[normalized];
    }

    return value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}





