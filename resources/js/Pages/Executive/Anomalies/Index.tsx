import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

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
    filters: { status?: string; severity?: string; anomaly_type?: string; search?: string };
};

export default function ExecutiveAnomalyIndex({ anomalies, filters }: Props) {
    const [status, setStatus] = useState(filters.status ?? '');
    const [severity, setSeverity] = useState(filters.severity ?? '');
    const [anomalyType, setAnomalyType] = useState(filters.anomaly_type ?? '');
    const [search, setSearch] = useState(filters.search ?? '');

    const debouncedSearch = useDebouncedValue(search, 450);

    useEffect(() => {
        setStatus(filters.status ?? '');
        setSeverity(filters.severity ?? '');
        setAnomalyType(filters.anomaly_type ?? '');
        setSearch(filters.search ?? '');
    }, [filters.anomaly_type, filters.search, filters.severity, filters.status]);

    const activeFilters = useMemo(
        () => ({
            status,
            severity,
            anomaly_type: anomalyType,
            search: debouncedSearch,
        }),
        [status, severity, anomalyType, debouncedSearch],
    );

    useEffect(() => {
        router.get(route('executive.anomalies.index'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['anomalies', 'filters'],
        });
    }, [activeFilters]);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Executive Anomalies</h2>}>
            <Head title="Executive Anomalies" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-2 rounded-xl border bg-white p-3 md:grid-cols-4">
                        <input
                            className="rounded border px-3 py-2 text-sm"
                            value={status}
                            placeholder="Status"
                            onChange={(e) => setStatus(e.target.value)}
                        />
                        <input
                            className="rounded border px-3 py-2 text-sm"
                            value={severity}
                            placeholder="Severity"
                            onChange={(e) => setSeverity(e.target.value)}
                        />
                        <input
                            className="rounded border px-3 py-2 text-sm"
                            value={anomalyType}
                            placeholder="Anomaly type"
                            onChange={(e) => setAnomalyType(e.target.value)}
                        />
                        <input
                            className="rounded border px-3 py-2 text-sm"
                            value={search}
                            placeholder="Search office/type/status"
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <button
                            className="rounded bg-indigo-600 px-4 py-2 text-sm text-white md:col-span-4 md:justify-self-start"
                            onClick={() => router.post(route('executive.anomalies.detect'))}
                        >
                            Run Detection
                        </button>
                    </div>

                    <div className="overflow-hidden rounded border bg-white">
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

                    <PaginationNav links={anomalies.links} />
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
