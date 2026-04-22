import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
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
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Inventory Movement Logs</h2>}>
            <Head title="Inventory Movements" />
            <div className="py-8">
                <div className="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="grid gap-3 md:grid-cols-6">
                        <div className="md:col-span-4">
                            <label htmlFor="movement-log-search" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Search logs
                            </label>
                            <input
                                id="movement-log-search"
                                type="text"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search item, source reference, date"
                                className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                            />
                        </div>
                        <div>
                            <label htmlFor="movement-log-type" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Type
                            </label>
                            <select
                                id="movement-log-type"
                                value={movementTypeFilter}
                                onChange={(event) => setMovementTypeFilter(event.target.value)}
                                className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                            >
                                {movementTypeOptions.map((status) => (
                                    <option key={status} value={status}>
                                        {status === 'ALL' ? 'All types' : status.replaceAll('_', ' ')}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="flex items-end">
                            <button
                                type="button"
                                onClick={() => {
                                    setSearch('');
                                    setMovementTypeFilter('ALL');
                                }}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                    <p className="mt-3 text-xs text-slate-500">Showing {filteredRows.length} of {movements.data.length} movement log(s).</p>
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Movement Type</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Item / Inventory</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Quantity</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Date</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Reference Source</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {filteredRows.map((row) => (
                                <tr key={row.movement_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 text-slate-700">{humanizeEnum(row.movement_type)}</td>
                                    <td className="px-4 py-3 text-slate-700">
                                        <p>{row.item_name}</p>
                                        <p className="text-xs text-slate-500">Inventory #{row.inventory_id}</p>
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold text-slate-700">{row.quantity.toFixed(2)}</td>
                                    <td className="px-4 py-3 text-slate-600">{row.movement_date}</td>
                                    <td className="px-4 py-3 text-slate-600">{row.reference_label}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={route('inventory.inventory-movements.show', row.movement_id)}
                                            className="text-sm font-semibold text-blue-600 hover:text-blue-800"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {filteredRows.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No movement logs matched the selected filters.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
