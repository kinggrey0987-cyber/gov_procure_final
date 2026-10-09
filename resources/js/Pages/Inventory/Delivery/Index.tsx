import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import MonthFilterSelect from '@/Components/MonthFilterSelect';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type DeliveryRow = {
    delivery_id: number;
    delivery_code: string;
    receipt_status: string;
    batch_number?: number;
    total_batches?: number;
    delivery_schedule_type?: string;
    is_final_batch?: boolean;
    batch_label?: string;
    created_at: string;
    purchase_order?: {
        po_number?: string;
    };
    supplier?: {
        supplier_name?: string;
    };
    receiver?: {
        name?: string;
    };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    deliveries: {
        data: DeliveryRow[];
        links: PaginationLink[];
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

export default function DeliveryIndex({ deliveries, filters, filter_options }: Props) {
    const [status, setStatus] = useState(filters?.status ?? '');
    const [year, setYear] = useState(filters?.year ?? '');
    const [month, setMonth] = useState(filters?.month ?? '');
    const [search, setSearch] = useState(filters?.search ?? '');
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
        [status, year, month, debouncedSearch],
    );

    useEffect(() => {
        if (!hasMountedRef.current) {
            hasMountedRef.current = true;

            return;
        }

        router.get(route('inventory.deliveries.index'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['deliveries', 'filters'],
        });
    }, [activeFilters]);

    return (
        <AuthenticatedLayout
            header="GSO Delivery Monitoring & Dock Intake"
            headerActions={
                <Link
                    href={route('inventory.deliveries.create')}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 transition"
                >
                    <span>+</span>
                    <span>Create Delivery Intake / Batch</span>
                </Link>
            }
        >
            <Head title="GSO Deliveries" />

            <div className="space-y-3">
                {/* Compact Filter Bar */}
                <div className="shrink-0 rounded-xl border border-slate-200 bg-white p-2 shadow-xs">
                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            className="h-8 flex-1 min-w-[180px] rounded-lg border border-slate-300 px-2.5 text-xs focus:border-red-500 focus:ring-1 focus:ring-red-500"
                            value={search}
                            placeholder="Search delivery code, PO, supplier..."
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <select
                            className="h-8 rounded-lg border border-slate-300 pl-2.5 pr-8 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            <option value="">All statuses</option>
                            <option value="PENDING">{humanizeEnum('PENDING')}</option>
                            <option value="RECEIVED">{humanizeEnum('RECEIVED')}</option>
                            <option value="PARTIALLY_RECEIVED">{humanizeEnum('PARTIALLY_RECEIVED')}</option>
                            <option value="VERIFIED">{humanizeEnum('VERIFIED')}</option>
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

                {/* Snug Deliveries Table with Pagination Attached Directly Below Records */}
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-xs">
                            <thead className="bg-slate-50 shadow-xs">
                                <tr>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Code</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">PO</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Batch</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Supplier</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Receipt Status</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Received By</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Date</th>
                                    <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {deliveries.data.map((delivery) => (
                                    <tr key={delivery.delivery_id} className="hover:bg-slate-50/60">
                                        <td className="px-3.5 py-2 font-mono text-[11px] font-semibold text-slate-700">{delivery.delivery_code}</td>
                                        <td className="px-3.5 py-2 text-slate-700">{delivery.purchase_order?.po_number ?? '-'}</td>
                                        <td className="px-3.5 py-2">
                                            <span className="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 border border-indigo-200">
                                                {delivery.batch_label || `Batch #${delivery.batch_number ?? 1}`}
                                                {delivery.is_final_batch && (
                                                    <span className="text-[10px] font-normal text-indigo-500">(Final)</span>
                                                )}
                                            </span>
                                        </td>
                                        <td className="px-3.5 py-2 text-slate-700">{delivery.supplier?.supplier_name ?? '-'}</td>
                                        <td className="px-3.5 py-2">
                                            <span className={statusClass(delivery.receipt_status)}>{humanizeEnum(delivery.receipt_status)}</span>
                                        </td>
                                        <td className="px-3.5 py-2 text-slate-700">{delivery.receiver?.name ?? '-'}</td>
                                        <td className="px-3.5 py-2 text-slate-500">{new Date(delivery.created_at).toLocaleDateString()}</td>
                                        <td className="px-3.5 py-2 text-right">
                                            <div className="flex justify-end gap-2 text-xs">
                                                {delivery.receipt_status === 'PENDING' && (
                                                    <Link href={route('inventory.deliveries.receive', delivery.delivery_id)} className="font-semibold text-amber-600 hover:text-amber-800">
                                                        Open Receiving
                                                    </Link>
                                                )}
                                                {delivery.receipt_status === 'RECEIVED' && (
                                                    <Link
                                                        href={route('inventory.delivery-verifications.create', delivery.delivery_id)}
                                                        className="font-semibold text-emerald-600 hover:text-emerald-800"
                                                    >
                                                        Verify
                                                    </Link>
                                                )}
                                                <Link href={route('inventory.deliveries.show', delivery.delivery_id)} className="font-semibold text-red-600 hover:text-red-800">
                                                    View
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {deliveries.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-8 text-center text-xs text-slate-500">
                                            No delivery intake records found.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-slate-200 bg-slate-50/70 px-3.5 py-2 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs text-slate-500 font-medium">
                            Showing <span className="font-semibold text-slate-700">{deliveries.data.length}</span> delivery records
                        </p>
                        <PaginationNav links={deliveries.links} preserveState={false} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        PENDING: `${base} bg-amber-100 text-amber-700`,
        RECEIVED: `${base} bg-blue-100 text-blue-700`,
        VERIFIED: `${base} bg-emerald-100 text-emerald-700`,
        PARTIALLY_RECEIVED: `${base} bg-orange-100 text-orange-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}





