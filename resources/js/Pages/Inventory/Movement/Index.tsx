import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type MovementRow = {
    movement_id: number;
    movement_type: string;
    inventory_id: number | string;
    item_name: string;
    quantity: number;
    movement_date: string;
    reference_label: string;
};

type Props = {
    movements: {
        data: MovementRow[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
};

export default function MovementIndex({ movements }: Props) {
    const [search, setSearch] = useState('');
    const [movementTypeFilter, setMovementTypeFilter] = useState('ALL');

    const movementTypeOptions = useMemo(
        () => ['ALL', ...Array.from(new Set(movements.data.map((row) => row.movement_type.toUpperCase())))],
        [movements.data],
    );

    const filteredRows = useMemo(() => {
        const keyword = search.trim().toLowerCase();

        return movements.data.filter((row) => {
            const normalizedType = row.movement_type.toUpperCase();
            const matchesType = movementTypeFilter === 'ALL' || normalizedType === movementTypeFilter;

            if (!matchesType) {
                return false;
            }

            if (keyword.length === 0) {
                return true;
            }

            return [
                row.item_name,
                row.reference_label,
                row.movement_date,
                row.inventory_id,
                humanizeEnum(row.movement_type),
            ]
                .join(' ')
                .toLowerCase()
                .includes(keyword);
        });
    }, [movements.data, movementTypeFilter, search]);

    return (
        <AuthenticatedLayout header="Inventory Movement Logs">
            <Head title="Inventory Movements" />

            <div className="space-y-3">
                {/* Compact Filter Bar */}
                <div className="shrink-0 rounded-xl border border-slate-200 bg-white p-2 shadow-xs">
                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            type="text"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search item, source reference, date..."
                            className="h-8 flex-1 min-w-[200px] rounded-lg border border-slate-300 px-2.5 text-xs focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        />
                        <select
                            value={movementTypeFilter}
                            onChange={(event) => setMovementTypeFilter(event.target.value)}
                            className="h-8 rounded-lg border border-slate-300 pl-2.5 pr-8 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        >
                            {movementTypeOptions.map((status) => (
                                <option key={status} value={status}>
                                    {status === 'ALL' ? 'All types' : status.replaceAll('_', ' ')}
                                </option>
                            ))}
                        </select>
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                setMovementTypeFilter('ALL');
                            }}
                            className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                {/* Snug Movements Table with Pagination Attached Directly Below Records */}
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-xs">
                            <thead className="bg-slate-50 shadow-xs">
                                <tr>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Movement Type</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Item / Inventory</th>
                                    <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Quantity</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Date</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Reference Source</th>
                                    <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {filteredRows.map((row) => (
                                    <tr key={row.movement_id} className="hover:bg-slate-50/60">
                                        <td className="px-3.5 py-2 font-medium text-slate-700">{humanizeEnum(row.movement_type)}</td>
                                        <td className="px-3.5 py-2 text-slate-800">
                                            <p className="font-semibold text-slate-900">{row.item_name}</p>
                                            <p className="text-[10px] text-slate-500">Inventory #{row.inventory_id}</p>
                                        </td>
                                        <td className="px-3.5 py-2 text-right font-bold text-slate-900">{row.quantity.toFixed(2)}</td>
                                        <td className="px-3.5 py-2 text-slate-500">{formatDate(row.movement_date)}</td>
                                        <td className="px-3.5 py-2 text-slate-600">{row.reference_label}</td>
                                        <td className="px-3.5 py-2 text-right">
                                            <Link
                                                href={route('inventory.inventory-movements.show', row.movement_id)}
                                                className="font-semibold text-xs text-red-600 hover:text-red-800"
                                            >
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                                {filteredRows.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-xs text-slate-500">
                                            No movement logs matched the selected filters.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-slate-200 bg-slate-50/70 px-3.5 py-2 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs text-slate-500 font-medium">
                            Showing <span className="font-semibold text-slate-700">{filteredRows.length}</span> movement logs
                        </p>
                        <PaginationNav links={movements.links} preserveState={false} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const datePart = value.match(/^\d{4}-\d{2}-\d{2}/)?.[0];
    const parsed = new Date(datePart ? `${datePart}T00:00:00` : value);

    return Number.isNaN(parsed.getTime())
        ? value
        : parsed.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}





