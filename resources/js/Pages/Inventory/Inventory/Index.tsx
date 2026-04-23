import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

type InventoryRow = {
    inventory_id: number;
    item_id: number;
    reserved_quantity: string;
    available_stock: string;
    stock_on_hand: string;
    released_quantity: string;
    low_stock_threshold: string;
    item?: {
        item_name?: string;
        category?: {
            category_name?: string;
        };
    };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    inventories: {
        data: InventoryRow[];
        links: PaginationLink[];
    };
    categories: Array<{
        item_category_id: number;
        category_name: string;
    }>;
    filters: {
        q?: string;
        category_id?: number | null;
        stock_status?: string;
    };
};

export default function InventoryIndex({ inventories, categories, filters }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const [categoryId, setCategoryId] = useState(String(filters.category_id ?? ''));
    const [stockStatus, setStockStatus] = useState(filters.stock_status ?? '');
    const debouncedQ = useDebouncedValue(q, 450);

    useEffect(() => {
        setQ(filters.q ?? '');
        setCategoryId(String(filters.category_id ?? ''));
        setStockStatus(filters.stock_status ?? '');
    }, [filters.category_id, filters.q, filters.stock_status]);

    const activeFilters = useMemo(
        () => ({
            q: debouncedQ,
            category_id: categoryId,
            stock_status: stockStatus,
        }),
        [debouncedQ, categoryId, stockStatus],
    );

    useEffect(() => {
        router.get(route('inventory.inventory.index'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['inventories', 'filters', 'categories'],
        });
    }, [activeFilters]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Inventory Monitoring</h2>
                    <Link href={route('inventory.inventory.index')} className="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">
                        Refresh
                    </Link>
                </div>
            }
        >
            <Head title="Inventory" />

            <div className="space-y-4 py-8">
                <div className="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5">
                    <input
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder="Search item name"
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2"
                    />
                    <select value={categoryId} onChange={(event) => setCategoryId(event.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All categories</option>
                        {categories.map((category) => (
                            <option key={category.item_category_id} value={category.item_category_id}>
                                {category.category_name}
                            </option>
                        ))}
                    </select>
                    <select value={stockStatus} onChange={(event) => setStockStatus(event.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All stock statuses</option>
                        <option value="HEALTHY">Healthy Stock</option>
                        <option value="AT_REORDER_POINT">At Reorder Point</option>
                        <option value="LOW">Low Stock</option>
                        <option value="CRITICAL">Critical Stock</option>
                    </select>
                    <Link href={route('inventory.inventory.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </Link>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-4 text-xs text-slate-600 shadow-sm">
                    Low Stock Threshold is the reorder point. Items strictly below this level are active low-stock cases for procurement initiation; items equal to threshold are advisory only.
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Category</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Available</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">On Hand</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Reserved</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Released</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Reorder Point</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Stock Signal</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {inventories.data.map((row) => {
                                const available = Number(row.available_stock);
                                const threshold = Number(row.low_stock_threshold);
                                const deficit = Math.max(threshold - available, 0);
                                const isCritical = available <= 0;
                                const isLow = available < threshold && available > 0;
                                const isAtReorder = available === threshold;
                                const signalClass = isCritical
                                    ? 'bg-rose-100 text-rose-700'
                                    : isLow
                                    ? 'bg-amber-100 text-amber-700'
                                    : isAtReorder
                                    ? 'bg-blue-100 text-blue-700'
                                    : 'bg-emerald-100 text-emerald-700';
                                const signalLabel = isCritical
                                    ? 'Critical Stock'
                                    : isLow
                                    ? 'Low Stock'
                                    : isAtReorder
                                    ? 'At Reorder Point'
                                    : 'Healthy Stock';

                                return (
                                    <tr key={row.inventory_id} className="hover:bg-slate-50/60">
                                        <td className="px-4 py-3 text-slate-700">
                                            <p>{row.item?.item_name ?? `Item #${row.item_id}`}</p>
                                            <p className="text-xs text-slate-500">ID: {row.item_id}</p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{row.item?.category?.category_name ?? '-'}</td>
                                        <td className={`px-4 py-3 text-right font-semibold ${isCritical || isLow ? 'text-amber-700' : 'text-slate-700'}`}>{row.available_stock}</td>
                                        <td className="px-4 py-3 text-right text-slate-700">{row.stock_on_hand}</td>
                                        <td className="px-4 py-3 text-right text-slate-700">{row.reserved_quantity}</td>
                                        <td className="px-4 py-3 text-right text-slate-700">{row.released_quantity}</td>
                                        <td className="px-4 py-3 text-right text-slate-700">{row.low_stock_threshold}</td>
                                        <td className="px-4 py-3 text-slate-700">
                                            <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${signalClass}`}>
                                                {signalLabel}
                                            </span>
                                            {deficit > 0 ? <p className="mt-1 text-xs text-rose-600">Deficit: {deficit.toFixed(2)}</p> : null}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="space-y-1">
                                                <Link href={route('inventory.inventory.show', row.inventory_id)} className="block text-sm font-semibold text-blue-600 hover:text-blue-800">
                                                    View
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                            {inventories.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No inventory records available.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>

                <PaginationNav links={inventories.links} />
            </div>
        </AuthenticatedLayout>
    );
}
